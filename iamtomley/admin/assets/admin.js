/* ============================================================================
   Admin panel behaviour.

   The only interactive piece is the "Detect" button: it takes whatever address
   is in a URL field, asks the server to find that site's logo or preview image,
   and drops the saved path into the form with a live preview. Everything else
   on these pages is a plain form post.
   ========================================================================== */
(function () {
  'use strict';

  var cfg = window.ADMIN || {};
  var pick = function (sel) { return sel ? document.querySelector(sel) : null; };

  /** Show a short status line under the button. */
  function say(el, text, tone) {
    if (!el) return;
    el.textContent = text;
    el.className = 'detect-note' + (tone ? ' ' + tone : '');
  }

  /** Point a preview <img> at a path, or hide it when there is nothing to show. */
  function preview(el, src) {
    if (!el) return;
    if (src) {
      el.src = src;
      el.hidden = false;
    } else {
      el.removeAttribute('src');
      el.hidden = true;
    }
  }

  /* ── Detect an image from a URL ─────────────────────────────────────────── */
  document.querySelectorAll('[data-detect]').forEach(function (btn) {
    var urlEl  = pick(btn.dataset.detect);
    var pathEl = pick(btn.dataset.detectTarget);
    var prevEl = pick(btn.dataset.detectPreview);
    var nameEl = pick(btn.dataset.detectPrefix);
    var noteEl = pick(btn.dataset.detectNote);
    var titleEl = pick(btn.dataset.detectTitle);
    var descEl  = pick(btn.dataset.detectDesc);

    btn.addEventListener('click', function () {
      if (!urlEl || !pathEl) return;
      var url = (urlEl.value || '').trim();
      if (!url || url === '#') {
        say(noteEl, 'Enter the site address first, then press Detect.', 'warn');
        urlEl.focus();
        return;
      }

      var original = btn.innerHTML;
      btn.disabled = true;
      btn.textContent = 'Looking…';
      say(noteEl, 'Reading ' + url + ' — this can take a few seconds.', '');

      var body = new URLSearchParams();
      body.set('csrf', cfg.csrf || '');
      body.set('url', url);
      body.set('prefix', nameEl ? (nameEl.value || 'site') : 'site');

      fetch(cfg.detectUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString()
      })
        .then(function (r) { return r.json().catch(function () { return { ok: false, error: 'The server sent back an unexpected response.' }; }); })
        .then(function (data) {
          if (!data || !data.ok) {
            say(noteEl, (data && data.error) || 'Nothing could be found at that address.', 'warn');
            return;
          }
          // Fill what is empty; never overwrite something already typed.
          var filled = [];
          if (data.path) {
            pathEl.value = data.path;
            preview(prevEl, data.src);
            filled.push('the ' + (data.kind || 'image').toLowerCase());
          }
          if (titleEl && data.title && !titleEl.value.trim()) {
            titleEl.value = data.title;
            filled.push('the name');
          }
          if (descEl && data.description && !descEl.value.trim()) {
            descEl.value = data.description;
            filled.push('the description');
          }
          if (!filled.length) {
            say(noteEl, data.note || 'Nothing new to fill in — your fields are already filled.', 'warn');
            return;
          }
          var list = filled.length > 1
            ? filled.slice(0, -1).join(', ') + ' and ' + filled[filled.length - 1]
            : filled[0];
          say(noteEl, 'Filled in ' + list + (data.note ? ' (' + data.note + ')' : '') + ' — save the form to keep it.', 'ok');
        })
        .catch(function () {
          say(noteEl, 'Could not reach the server. Check your connection and try again.', 'warn');
        })
        .then(function () {
          btn.disabled = false;
          btn.innerHTML = original;
        });
    });
  });

  /* ── Clear the chosen image ─────────────────────────────────────────────── */
  document.querySelectorAll('[data-clear-image]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var pathEl = pick(btn.dataset.clearImage);
      var prevEl = pick(btn.dataset.clearPreview);
      var noteEl = pick(btn.dataset.clearNote);
      if (pathEl) pathEl.value = '';
      preview(prevEl, '');
      say(noteEl, 'Image cleared — save the form to apply it.', '');
    });
  });

  /* ── Typing a path by hand updates the preview too ──────────────────────── */
  document.querySelectorAll('[data-preview-for]').forEach(function (img) {
    var pathEl = pick(img.dataset.previewFor);
    if (!pathEl) return;
    pathEl.addEventListener('input', function () {
      var v = (pathEl.value || '').trim();
      preview(img, v ? (v.charAt(0) === '/' ? (cfg.base || '') + v : v) : '');
    });
  });

  /* ── Read every project's website in one pass ───────────────────────────── */
  // One site at a time from here rather than all of them server-side, so a slow
  // site cannot time the page out and you can watch the rows fill in.
  (function detectAll() {
    var start = document.getElementById('detectAllStart');
    var form  = document.getElementById('detectAllForm');
    if (!start || !form) return;

    var rows = [].slice.call(document.querySelectorAll('.detect-row'));
    var progress = document.getElementById('detectAllProgress');

    /** Remember a downloaded picture so an unticked one can be cleaned up. */
    function remember(path) {
      var h = document.createElement('input');
      h.type = 'hidden'; h.name = 'fetched[]'; h.value = path;
      form.appendChild(h);
    }

    function setRow(row, text, tone) {
      var el = row.querySelector('.detect-status');
      if (!el) return;
      el.textContent = text;
      el.className = 'detect-note detect-status' + (tone ? ' ' + tone : '');
    }

    start.addEventListener('click', function () {
      start.disabled = true;
      var label = start.textContent;
      var i = 0, found = 0;

      (function step() {
        if (i >= rows.length) {
          start.disabled = false;
          start.textContent = 'Read them again';
          say(progress, found
            ? 'Done — ' + found + ' of ' + rows.length + ' sites had something. Check the rows, edit anything you like, then apply.'
            : 'Done — nothing could be read from those sites.', found ? 'ok' : 'warn');
          return;
        }

        var row = rows[i];
        i += 1;
        start.textContent = 'Reading ' + i + ' of ' + rows.length + '…';
        say(progress, 'Reading ' + row.dataset.url + ' …', '');
        setRow(row, 'reading…', '');

        var body = new URLSearchParams();
        body.set('csrf', cfg.csrf || '');
        body.set('url', row.dataset.url || '');
        var titleEl = row.querySelector('.detect-title');
        body.set('prefix', (titleEl && titleEl.value) || 'site');

        fetch(cfg.detectUrl, {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: body.toString()
        })
          .then(function (r) { return r.json().catch(function () { return { ok: false, error: 'unexpected reply' }; }); })
          .then(function (d) {
            if (!d || !d.ok) { setRow(row, (d && d.error) || 'could not be read', 'warn'); return; }
            var bits = [];
            if (d.title && titleEl) { titleEl.value = d.title; bits.push('name'); }
            var descEl = row.querySelector('.detect-desc');
            if (d.description && descEl) { descEl.value = d.description; bits.push('description'); }
            if (d.path) {
              var pathEl = row.querySelector('.detect-image');
              if (pathEl) pathEl.value = d.path;
              preview(row.querySelector('.image-preview'), d.src);
              remember(d.path);
              bits.push('picture');
            }
            var box = row.querySelector('.detect-apply');
            if (bits.length) {
              if (box) { box.disabled = false; box.checked = true; }
              found += 1;
              setRow(row, 'found ' + bits.join(', '), 'ok');
            } else {
              setRow(row, d.note || 'nothing found', 'warn');
            }
          })
          .catch(function () { setRow(row, 'could not reach the server', 'warn'); })
          .then(step);
      })();
    });

    var tick = function (on) {
      document.querySelectorAll('.detect-apply').forEach(function (b) {
        if (!b.disabled) b.checked = on;
      });
    };
    var all = document.getElementById('detectAllTickAll');
    var none = document.getElementById('detectAllTickNone');
    if (all)  all.addEventListener('click', function () { tick(true); });
    if (none) none.addEventListener('click', function () { tick(false); });
  })();

  /* ── Does this server actually rewrite clean URLs? ──────────────────────── */
  // Ask it for /sitemap (no .php). If the rewrite is in force the server hands
  // back the sitemap; if it is not, that address simply does not exist.
  document.querySelectorAll('[data-cleanurlTest], [data-cleanurl-test]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var out = pick(btn.dataset.cleanurlTest);
      say(out, 'Checking…', '');
      fetch((cfg.base || '') + '/sitemap', { credentials: 'same-origin' })
        .then(function (r) {
          if (!r.ok) return { works: false };
          return r.text().then(function (t) { return { works: t.indexOf('<urlset') !== -1 }; });
        })
        .then(function (r) {
          if (r.works) {
            say(out, 'Your server rewrites clean addresses — .php can be hidden. Set the box on the left to Always if it is not already off.', 'ok');
          } else {
            say(out, 'Your server did not serve /sitemap without the .php, so links keep it. Ask your host to switch on mod_rewrite and AllowOverride All for this folder.', 'warn');
          }
        })
        .catch(function () { say(out, 'The check could not run — try reloading the page.', 'warn'); });
    });
  });

  /* ── Game form: only show the fields the chosen source needs ────────────── */
  document.querySelectorAll('[data-source-select]').forEach(function (sel) {
    var sync = function () {
      document.querySelectorAll('[data-source-only]').forEach(function (el) {
        var wanted = el.dataset.sourceOnly.split(/\s+/);
        el.hidden = wanted.indexOf(sel.value) === -1;
      });
    };
    sel.addEventListener('change', sync);
    sync();
  });
})();
