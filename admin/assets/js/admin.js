/* =====================================================================
   TECHBISS — admin panel behaviour
   Vanilla JS. Drag-to-reorder, media picker, repeaters, confirmations.
   ===================================================================== */
(function () {
  'use strict';

  var $  = function (s, c) { return (c || document).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };
  var CSRF = (window.TECHBISS_ADMIN || {}).csrf || '';
  var BASE = (window.TECHBISS_ADMIN || {}).base || '/';

  function on(el, evt, fn, opts) { if (el) el.addEventListener(evt, fn, opts); }

  // Anything a real Tab press would land on.
  var FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), ' +
                  'textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

  // aria-modal="true" is a promise to assistive tech, not a browser feature:
  // without this, Tab walks straight out of the dialog and into the form behind.
  function trapTab(container, e) {
    if (e.key !== 'Tab') return;
    var nodes = $$(FOCUSABLE, container).filter(function (el) {
      return el.offsetWidth || el.offsetHeight || el.getClientRects().length;
    });
    if (!nodes.length) return;
    var first = nodes[0];
    var last  = nodes[nodes.length - 1];
    var active = document.activeElement;
    var outside = !container.contains(active);
    if (e.shiftKey && (active === first || outside)) {
      e.preventDefault();
      last.focus();
    } else if (!e.shiftKey && (active === last || outside)) {
      e.preventDefault();
      first.focus();
    }
  }

  function toast(msg, type) { if (window.techbissToast) window.techbissToast(msg, type || 'info'); }

  function post(url, data) {
    var body = data instanceof FormData ? data : new FormData();
    if (!(data instanceof FormData) && data) {
      Object.keys(data).forEach(function (k) {
        if (Array.isArray(data[k])) data[k].forEach(function (v) { body.append(k + '[]', v); });
        else body.append(k, data[k]);
      });
    }
    if (!body.has('csrf_token')) body.append('csrf_token', CSRF);
    return fetch(url, {
      method: 'POST',
      body: body,
      headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
      credentials: 'same-origin'
    }).then(function (r) { return r.json().catch(function () { return { ok: false, message: 'Unexpected server response.' }; }); });
  }

  /* -------------------------------------------------------------------
     Sidebar (mobile)
     ------------------------------------------------------------------- */
  function initSidebar() {
    var toggle = $('[data-sidebar-toggle]');
    on(toggle, 'click', function () {
      document.body.classList.toggle('sidebar-open');
      toggle.setAttribute('aria-expanded', document.body.classList.contains('sidebar-open') ? 'true' : 'false');
    });
    on(document, 'click', function (e) {
      if (!document.body.classList.contains('sidebar-open')) return;
      if (e.target.closest('.sidebar') || e.target.closest('[data-sidebar-toggle]')) return;
      document.body.classList.remove('sidebar-open');
      if (toggle) toggle.setAttribute('aria-expanded', 'false');
    });
    on(document, 'keydown', function (e) {
      if (e.key === 'Escape') document.body.classList.remove('sidebar-open');
    });
  }

  /* -------------------------------------------------------------------
     Destructive actions always confirm first
     ------------------------------------------------------------------- */
  function initConfirm() {
    on(document, 'submit', function (e) {
      var form = e.target;
      if (!form.matches || !form.matches('[data-confirm]')) return;
      var message = form.getAttribute('data-confirm') || 'Are you sure? This cannot be undone.';
      if (!window.confirm(message)) e.preventDefault();
    });
  }

  /* -------------------------------------------------------------------
     Slug auto-fill from a source field, until the slug is hand-edited
     ------------------------------------------------------------------- */
  function slugify(value) {
    return String(value).toLowerCase().trim()
      .normalize('NFD').replace(/[̀-ͯ]/g, '')
      .replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
  }

  function initSlug() {
    $$('[data-slug-from]').forEach(function (slugInput) {
      var source = $('[name="' + slugInput.getAttribute('data-slug-from') + '"]');
      if (!source) return;
      var touched = slugInput.value.trim() !== '';
      on(slugInput, 'input', function () { touched = true; });
      on(source, 'input', function () {
        if (!touched) slugInput.value = slugify(source.value);
      });
      var btn = $('[data-slug-regenerate]');
      on(btn, 'click', function () { slugInput.value = slugify(source.value); touched = false; });
    });
  }

  /* -------------------------------------------------------------------
     Character counters on length-limited fields
     ------------------------------------------------------------------- */
  function initCounters() {
    $$('[data-counter]').forEach(function (field) {
      var max = parseInt(field.getAttribute('maxlength'), 10);
      if (!max) return;
      var out = document.createElement('span');
      out.className = 'hint';
      out.style.marginLeft = 'auto';
      var wrap = field.closest('.field');
      var label = wrap && wrap.querySelector('.label');
      if (label) label.appendChild(out);
      var update = function () {
        var n = field.value.length;
        out.textContent = n + ' / ' + max;
        out.style.color = n > max * 0.92 ? 'var(--warning)' : 'var(--text-faint)';
      };
      on(field, 'input', update);
      update();
    });
  }

  /* -------------------------------------------------------------------
     Drag-to-reorder lists (tables and repeaters)
     ------------------------------------------------------------------- */
  function initSortable() {
    $$('[data-sortable]').forEach(function (list) {
      var url = list.getAttribute('data-sortable');
      var itemSelector = list.getAttribute('data-sortable-item') || '[data-id]';
      var dragging = null;

      $$(itemSelector, list).forEach(function (item) { prepare(item); });

      function prepare(item) {
        var handle = item.querySelector('[data-drag-handle]');
        if (!handle) return;
        handle.setAttribute('draggable', 'true');
        on(handle, 'dragstart', function (e) {
          dragging = item;
          item.classList.add('is-dragging');
          e.dataTransfer.effectAllowed = 'move';
          try { e.dataTransfer.setData('text/plain', item.getAttribute('data-id') || ''); } catch (err) {}
        });
        on(handle, 'dragend', function () {
          if (dragging) dragging.classList.remove('is-dragging');
          $$(itemSelector, list).forEach(function (i) { i.classList.remove('drop-target'); });
          dragging = null;
          persist();
        });
      }

      on(list, 'dragover', function (e) {
        if (!dragging) return;
        e.preventDefault();
        var target = e.target.closest ? e.target.closest(itemSelector) : null;
        if (!target || target === dragging || !list.contains(target)) return;
        $$(itemSelector, list).forEach(function (i) { i.classList.remove('drop-target'); });
        target.classList.add('drop-target');
        var rect = target.getBoundingClientRect();
        var after = (e.clientY - rect.top) > rect.height / 2;
        target.parentNode.insertBefore(dragging, after ? target.nextSibling : target);
      });

      on(list, 'drop', function (e) { e.preventDefault(); });

      function persist() {
        if (!url) { renumber(); return; }
        var order = $$(itemSelector, list).map(function (i) { return i.getAttribute('data-id'); }).filter(Boolean);
        if (!order.length) return;
        post(url, { order: order }).then(function (data) {
          if (data.ok) toast(data.message || 'Order saved.', 'success');
          else toast(data.message || 'Could not save the new order.', 'error');
        }).catch(function () { toast('Could not reach the server.', 'error'); });
      }

      function renumber() {
        $$(itemSelector, list).forEach(function (item, i) {
          $$('[data-index-token]', item).forEach(function (field) {
            var name = field.getAttribute('name');
            if (name) field.setAttribute('name', name.replace(/\[\d+\]/, '[' + i + ']'));
          });
        });
      }
    });
  }

  /* -------------------------------------------------------------------
     Repeaters: add / remove rows on the client
     ------------------------------------------------------------------- */
  function initRepeaters() {
    $$('[data-repeater]').forEach(function (root) {
      var list = $('[data-repeater-list]', root);
      var tpl = $('[data-repeater-template]', root);
      if (!list || !tpl) return;

      var addBtn = $('[data-repeater-add]', root);
      on(addBtn, 'click', function () {
        var index = Date.now() % 100000;
        var html = tpl.innerHTML.replace(/__INDEX__/g, String(index));
        var wrap = document.createElement('div');
        wrap.innerHTML = html.trim();
        var node = wrap.firstElementChild;
        list.appendChild(node);
        var first = node.querySelector('input, textarea, select');
        if (first) first.focus();
      });

      on(list, 'click', function (e) {
        var btn = e.target.closest('[data-repeater-remove]');
        if (!btn) return;
        var row = btn.closest('[data-repeater-row]');
        if (row) row.remove();
      });
    });
  }

  /* -------------------------------------------------------------------
     Icon and accent pickers
     ------------------------------------------------------------------- */
  function initPickers() {
    $$('[data-icon-picker]').forEach(function (picker) {
      var input = $('[name="' + picker.getAttribute('data-icon-picker') + '"]');
      on(picker, 'click', function (e) {
        var btn = e.target.closest('.icon-picker__item');
        if (!btn || !input) return;
        input.value = btn.getAttribute('data-icon');
        $$('.icon-picker__item', picker).forEach(function (i) { i.classList.remove('is-selected'); });
        btn.classList.add('is-selected');
      });
    });

    $$('[data-accent-picker]').forEach(function (picker) {
      var input = $('[name="' + picker.getAttribute('data-accent-picker') + '"]');
      on(picker, 'click', function (e) {
        var btn = e.target.closest('.accent-swatch');
        if (!btn || !input) return;
        input.value = btn.getAttribute('data-accent');
        $$('.accent-swatch', picker).forEach(function (i) { i.classList.remove('is-selected'); });
        btn.classList.add('is-selected');
      });
    });
  }

  /* -------------------------------------------------------------------
     Media picker modal
     ------------------------------------------------------------------- */
  var picker = null;
  var pickerReturn = null;

  function openPicker(onChoose, multiple) {
    if (picker) closePicker();

    // Whatever opened the dialog gets focus back when it closes; without it the
    // browser drops focus to the top of the document or to a random field.
    pickerReturn = document.activeElement;

    picker = document.createElement('div');
    picker.className = 'modal';
    picker.setAttribute('role', 'dialog');
    picker.setAttribute('aria-modal', 'true');
    picker.setAttribute('aria-label', 'Choose an image');
    picker.innerHTML =
      '<div class="modal__dialog" style="width:min(100%,860px)">' +
        '<div class="modal__head">' +
          '<div><strong>Media library</strong>' +
          '<div class="hint">Choose an existing image or upload a new one.</div></div>' +
          '<button type="button" class="icon-btn" data-picker-close aria-label="Close">' +
            '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg>' +
          '</button>' +
        '</div>' +
        '<div class="modal__body">' +
          '<div class="toolbar">' +
            '<div class="input-group toolbar__search">' +
              '<input class="input" type="search" placeholder="Search media" data-picker-search>' +
            '</div>' +
            '<label class="btn btn--ghost btn--sm" style="cursor:pointer">' +
              'Upload<input type="file" accept="image/*" multiple hidden data-picker-upload>' +
            '</label>' +
          '</div>' +
          '<div class="media-grid" data-picker-grid style="max-height:52vh;overflow-y:auto"></div>' +
        '</div>' +
        '<div class="modal__foot">' +
          '<button type="button" class="btn btn--ghost" data-picker-close>Cancel</button>' +
          '<button type="button" class="btn btn--primary" data-picker-confirm disabled>Use selected</button>' +
        '</div>' +
      '</div>';
    document.body.appendChild(picker);
    document.body.classList.add('no-scroll');

    var grid = $('[data-picker-grid]', picker);
    var confirm = $('[data-picker-confirm]', picker);
    var selected = [];

    function load(query) {
      grid.innerHTML = '<div class="skeleton skeleton--card"></div><div class="skeleton skeleton--card"></div><div class="skeleton skeleton--card"></div>';
      fetch(BASE + 'admin/media/browse?q=' + encodeURIComponent(query || ''), {
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        credentials: 'same-origin'
      })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (!data.ok || !data.items.length) {
            grid.innerHTML = '<div class="empty-state" style="grid-column:1/-1"><h3>No images yet</h3><p>Upload one using the button above.</p></div>';
            return;
          }
          grid.innerHTML = '';
          data.items.forEach(function (item) {
            var el = document.createElement('div');
            el.className = 'media-item';
            el.setAttribute('data-path', item.path);
            el.innerHTML = '<div class="media-item__thumb"><img src="' + item.thumb + '" alt="" loading="lazy"></div>' +
                           '<div class="media-item__body"><div class="media-item__name">' + escapeHtml(item.name) + '</div></div>';
            on(el, 'click', function () {
              if (!multiple) {
                $$('.media-item', grid).forEach(function (m) { m.classList.remove('is-selected'); });
                selected = [item.path];
              } else {
                var i = selected.indexOf(item.path);
                if (i >= 0) selected.splice(i, 1); else selected.push(item.path);
              }
              el.classList.toggle('is-selected', selected.indexOf(item.path) >= 0);
              confirm.disabled = selected.length === 0;
            });
            grid.appendChild(el);
          });
        })
        .catch(function () { grid.innerHTML = '<p class="hint">Could not load the media library.</p>'; });
    }

    var search = $('[data-picker-search]', picker);
    var timer;
    on(search, 'input', function () {
      clearTimeout(timer);
      timer = setTimeout(function () { load(search.value); }, 260);
    });

    on($('[data-picker-upload]', picker), 'change', function (e) {
      var files = e.target.files;
      if (!files || !files.length) return;
      var fd = new FormData();
      for (var i = 0; i < files.length; i++) fd.append('files[]', files[i]);
      fd.append('folder', 'general');
      toast('Uploading…', 'info');
      post(BASE + 'admin/media/upload', fd).then(function (data) {
        toast(data.message || (data.ok ? 'Uploaded.' : 'Upload failed.'), data.ok ? 'success' : 'error');
        if (data.ok) load(search.value);
      });
    });

    $$('[data-picker-close]', picker).forEach(function (b) { on(b, 'click', closePicker); });
    on(picker, 'click', function (e) { if (e.target === picker) closePicker(); });
    on(document, 'keydown', pickerKeys);
    on(confirm, 'click', function () {
      if (selected.length) onChoose(multiple ? selected : selected[0]);
      closePicker();
    });

    load('');

    // Focus has to move into the dialog, or the first Tab carries on through
    // the page behind it as if the dialog were not there.
    var closeBtn = $('[data-picker-close]', picker);
    if (closeBtn) closeBtn.focus();
  }

  function pickerKeys(e) {
    if (!picker) return;
    if (e.key === 'Escape') closePicker();
    else if (e.key === 'Tab') trapTab(picker, e);
  }

  function closePicker() {
    if (!picker) return;
    document.removeEventListener('keydown', pickerKeys);
    document.body.classList.remove('no-scroll');
    if (picker.parentNode) picker.parentNode.removeChild(picker);
    picker = null;
    if (pickerReturn && document.contains(pickerReturn)) pickerReturn.focus();
    pickerReturn = null;
  }

  function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  function initMediaFields() {
    $$('[data-media-field]').forEach(function (field) {
      var input = $('input[type="hidden"], input[type="text"]', field);
      var preview = $('.media-field__preview', field);
      var pathEl = $('.media-field__path', field);

      function render(path) {
        if (input) input.value = path || '';
        if (pathEl) pathEl.textContent = path || 'No image selected';
        if (preview) {
          preview.innerHTML = path
            ? '<img src="' + (/^https?:/.test(path) ? path : BASE + path) + '" alt="">'
            : '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="4.5" width="18" height="15" rx="2"/><path d="m4 17 5-4.5 4 3.5 3-2.5 4 3.5"/></svg>';
        }
      }

      on($('[data-media-choose]', field), 'click', function () { openPicker(render, false); });
      on($('[data-media-clear]', field), 'click', function () { render(''); });
    });

    // Gallery manager (portfolio)
    $$('[data-gallery]').forEach(function (gallery) {
      var list = $('[data-gallery-list]', gallery);
      on($('[data-gallery-add]', gallery), 'click', function () {
        openPicker(function (paths) {
          (Array.isArray(paths) ? paths : [paths]).forEach(function (path) {
            var item = document.createElement('div');
            item.className = 'gallery-manager__item';
            item.innerHTML = '<img src="' + (/^https?:/.test(path) ? path : BASE + path) + '" alt="">' +
              '<input type="hidden" name="gallery[]" value="' + escapeHtml(path) + '">' +
              '<button type="button" class="gallery-manager__remove" aria-label="Remove image">' +
              '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg></button>';
            on($('.gallery-manager__remove', item), 'click', function () { item.remove(); });
            list.insertBefore(item, list.lastElementChild);
          });
        }, true);
      });

      $$('.gallery-manager__remove', gallery).forEach(function (btn) {
        on(btn, 'click', function () {
          var item = btn.closest('.gallery-manager__item');
          var form = btn.getAttribute('data-delete-url');
          if (form) {
            if (!window.confirm('Remove this image from the project?')) return;
            post(form, {}).then(function (data) {
              if (data.ok) { item.remove(); toast('Image removed.', 'success'); }
              else toast(data.message || 'Could not remove the image.', 'error');
            });
          } else {
            item.remove();
          }
        });
      });
    });
  }

  /* -------------------------------------------------------------------
     Media library page: drag-and-drop upload
     ------------------------------------------------------------------- */
  function initDropzone() {
    var zone = $('[data-dropzone]');
    if (!zone) return;
    var input = $('input[type="file"]', zone);
    var form = zone.closest('form');

    ['dragenter', 'dragover'].forEach(function (evt) {
      on(zone, evt, function (e) { e.preventDefault(); zone.classList.add('is-over'); });
    });
    ['dragleave', 'drop'].forEach(function (evt) {
      on(zone, evt, function (e) { e.preventDefault(); zone.classList.remove('is-over'); });
    });
    on(zone, 'drop', function (e) {
      if (!e.dataTransfer.files.length || !input || !form) return;
      input.files = e.dataTransfer.files;
      form.submit();
    });
    on(input, 'change', function () { if (input.files.length && form) form.submit(); });
  }

  /* -------------------------------------------------------------------
     Inline publish toggles
     ------------------------------------------------------------------- */
  function initToggles() {
    $$('[data-toggle-url]').forEach(function (el) {
      on(el, 'change', function () {
        post(el.getAttribute('data-toggle-url'), { column: el.getAttribute('data-toggle-column') || 'is_published' })
          .then(function (data) {
            if (data.ok) toast('Updated.', 'success');
            else { el.checked = !el.checked; toast(data.message || 'Could not update.', 'error'); }
          })
          .catch(function () { el.checked = !el.checked; toast('Could not reach the server.', 'error'); });
      });
    });
  }

  /* -------------------------------------------------------------------
     Filters that submit on change
     ------------------------------------------------------------------- */
  function initFilters() {
    $$('[data-autosubmit]').forEach(function (el) {
      on(el, 'change', function () {
        var form = el.closest('form');
        if (form) form.submit();
      });
    });
    $$('[data-search-submit]').forEach(function (el) {
      var timer;
      on(el, 'input', function () {
        clearTimeout(timer);
        timer = setTimeout(function () {
          var form = el.closest('form');
          if (form) form.submit();
        }, 520);
      });
    });
  }

  /* -------------------------------------------------------------------
     Prevent accidental navigation away from an edited form
     ------------------------------------------------------------------- */
  function initDirtyGuard() {
    $$('form[data-dirty-guard]').forEach(function (form) {
      var dirty = false;
      on(form, 'input', function () { dirty = true; });
      on(form, 'change', function () { dirty = true; });
      on(form, 'submit', function () { dirty = false; });
      window.addEventListener('beforeunload', function (e) {
        if (!dirty) return;
        e.preventDefault();
        e.returnValue = '';
      });
    });
  }

  /* -------------------------------------------------------------------
     Status colour preview on package pricing
     ------------------------------------------------------------------- */
  function initPricingPreview() {
    var root = $('[data-pricing-preview]');
    if (!root) return;
    var regular = $('[name="regular_price"]');
    var prepaid = $('[name="prepaid_price"]');
    var custom = $('[name="is_custom_quote"]');
    var out = $('[data-pricing-output]', root);
    if (!regular || !prepaid || !out) return;

    function update() {
      if (custom && custom.checked) {
        out.innerHTML = '<span class="status-dot status-dot--info">Shown as “Custom quote” — no price is published.</span>';
        return;
      }
      var r = parseFloat(regular.value) || 0;
      var p = parseFloat(prepaid.value);
      if (!r) {
        out.innerHTML = '<span class="status-dot status-dot--warn">Enter a regular price, or mark this as a custom quote.</span>';
      } else if (isNaN(p) || p <= 0) {
        out.innerHTML = '<span class="status-dot status-dot--draft">No prepaid discount. The site will show ' + fmt(r) + ' only.</span>';
      } else if (p >= r) {
        out.innerHTML = '<span class="status-dot status-dot--danger">The prepaid price must be below the regular price, or left empty.</span>';
      } else {
        var save = r - p;
        var pct = Math.round((save / r) * 100);
        out.innerHTML = '<span class="status-dot status-dot--live">Visitors see ' + fmt(p) + ', was ' + fmt(r) +
          ', saving ' + fmt(save) + ' (' + pct + '%).</span>';
      }
    }
    function fmt(n) {
      var sym = root.getAttribute('data-currency') || '$';
      return sym + n.toLocaleString(undefined, { maximumFractionDigits: 2 });
    }
    [regular, prepaid, custom].forEach(function (el) {
      if (el) { on(el, 'input', update); on(el, 'change', update); }
    });
    update();
  }

  /* -------------------------------------------------------------------
     Boot
     ------------------------------------------------------------------- */
  function boot() {
    initSidebar();
    initConfirm();
    initSlug();
    initCounters();
    initSortable();
    initRepeaters();
    initPickers();
    initMediaFields();
    initDropzone();
    initToggles();
    initFilters();
    initDirtyGuard();
    initPricingPreview();
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
})();
