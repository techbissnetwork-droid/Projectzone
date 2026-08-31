/* =====================================================================
   TECHBISS — public site behaviour
   Vanilla ES2020. No dependencies. Everything degrades gracefully when
   JavaScript is unavailable, and every motion effect respects
   prefers-reduced-motion.
   ===================================================================== */
(function () {
  'use strict';

  var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
  var canHover     = window.matchMedia('(hover: hover) and (pointer: fine)');
  var cfg          = window.TECHBISS || {};

  var $  = function (sel, ctx) { return (ctx || document).querySelector(sel); };
  var $$ = function (sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); };

  function on(el, evt, fn, opts) { if (el) el.addEventListener(evt, fn, opts); }

  function throttleFrame(fn) {
    var queued = false;
    return function () {
      var args = arguments, self = this;
      if (queued) return;
      queued = true;
      requestAnimationFrame(function () { queued = false; fn.apply(self, args); });
    };
  }

  /* -------------------------------------------------------------------
     Loading screen
     Removed as soon as the document is interactive, with a hard ceiling
     so a slow asset can never leave a visitor staring at it.
     ------------------------------------------------------------------- */
  function initLoader() {
    var loader = $('.loader');
    if (!loader) return;
    var done = false;
    var finish = function () {
      if (done) return;
      done = true;
      loader.classList.add('is-done');
      document.body.classList.remove('is-loading');
      window.setTimeout(function () { if (loader.parentNode) loader.parentNode.removeChild(loader); }, 450);
    };
    if (document.readyState === 'complete') {
      window.setTimeout(finish, 120);
    } else {
      on(window, 'load', function () { window.setTimeout(finish, 90); });
    }
    // Never block for longer than this, whatever else is happening.
    window.setTimeout(finish, 2200);
  }

  /* -------------------------------------------------------------------
     Theme
     ------------------------------------------------------------------- */
  function initTheme() {
    var toggle = $('[data-theme-toggle]');
    if (!toggle) return;
    on(toggle, 'click', function () {
      var next = document.documentElement.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
      document.documentElement.setAttribute('data-theme', next);
      try { localStorage.setItem('techbiss-theme', next); } catch (e) { /* private mode */ }
      toggle.setAttribute('aria-label', next === 'light' ? 'Switch to dark theme' : 'Switch to light theme');
    });
  }

  /* -------------------------------------------------------------------
     Header: sticky state + reading progress
     ------------------------------------------------------------------- */
  function initHeader() {
    var header = $('.site-header');
    var progress = $('.read-progress');
    if (!header && !progress) return;

    var update = throttleFrame(function () {
      var y = window.scrollY || document.documentElement.scrollTop;
      if (header) header.classList.toggle('is-stuck', y > 12);
      if (progress) {
        var h = document.documentElement.scrollHeight - window.innerHeight;
        progress.style.setProperty('--progress', h > 0 ? Math.min(1, y / h).toFixed(4) : '0');
      }
    });
    on(window, 'scroll', update, { passive: true });
    on(window, 'resize', update, { passive: true });
    update();
  }

  /* -------------------------------------------------------------------
     Navigation: desktop dropdowns + mobile drawer
     ------------------------------------------------------------------- */
  function initNav() {
    var items = $$('.nav__item--has-children');

    items.forEach(function (item) {
      var trigger = $('.nav__link', item);
      if (!trigger) return;
      on(trigger, 'click', function (e) {
        // On touch, the first tap opens the menu instead of following the link.
        if (!canHover.matches || trigger.getAttribute('href') === '#' || !trigger.getAttribute('href')) {
          e.preventDefault();
          var open = item.classList.toggle('is-open');
          trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
        }
      });
      on(trigger, 'keydown', function (e) {
        if (e.key === 'ArrowDown') {
          e.preventDefault();
          item.classList.add('is-open');
          trigger.setAttribute('aria-expanded', 'true');
          var first = $('.nav__dropdown-link', item);
          if (first) first.focus();
        }
      });
      on(item, 'focusout', function (e) {
        if (!item.contains(e.relatedTarget)) {
          item.classList.remove('is-open');
          trigger.setAttribute('aria-expanded', 'false');
        }
      });
    });

    on(document, 'click', function (e) {
      items.forEach(function (item) {
        if (!item.contains(e.target)) {
          item.classList.remove('is-open');
          var t = $('.nav__link', item);
          if (t) t.setAttribute('aria-expanded', 'false');
        }
      });
    });

    // Mobile drawer
    var toggle = $('[data-nav-toggle]');
    var drawer = $('.mobile-nav');
    if (toggle && drawer) {
      var setOpen = function (open) {
        drawer.classList.toggle('is-open', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        document.body.classList.toggle('no-scroll', open);
        drawer.setAttribute('aria-hidden', open ? 'false' : 'true');
      };
      on(toggle, 'click', function () { setOpen(!drawer.classList.contains('is-open')); });
      on(document, 'keydown', function (e) {
        if (e.key === 'Escape' && drawer.classList.contains('is-open')) { setOpen(false); toggle.focus(); }
      });
      // Close when a real destination is chosen.
      $$('.mobile-nav__link[href], .mobile-nav__sublink', drawer).forEach(function (link) {
        on(link, 'click', function () { if (link.getAttribute('href')) setOpen(false); });
      });
      var mq = window.matchMedia('(min-width: 1025px)');
      var onChange = function () { if (mq.matches) setOpen(false); };
      if (mq.addEventListener) mq.addEventListener('change', onChange);
    }

    // Collapsible groups inside the drawer
    $$('.mobile-nav__group').forEach(function (group) {
      var trigger = $('.mobile-nav__link', group);
      on(trigger, 'click', function (e) {
        e.preventDefault();
        var open = group.getAttribute('data-open') !== 'true';
        group.setAttribute('data-open', open ? 'true' : 'false');
        trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
      });
    });
  }

  /* -------------------------------------------------------------------
     Scroll reveal — IntersectionObserver, one-shot, cheap
     ------------------------------------------------------------------- */
  function initReveal() {
    var targets = $$('[data-reveal]');
    if (!targets.length) return;
    if (reduceMotion.matches || !('IntersectionObserver' in window)) {
      targets.forEach(function (el) { el.classList.add('is-visible'); });
      return;
    }
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
          io.unobserve(entry.target);
        }
      });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.05 });
    targets.forEach(function (el) { io.observe(el); });
  }

  /* -------------------------------------------------------------------
     Animated counters for the stats band
     ------------------------------------------------------------------- */
  function initCounters() {
    var els = $$('[data-count]');
    if (!els.length || !('IntersectionObserver' in window)) return;
    if (reduceMotion.matches) return;

    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        var el = entry.target;
        io.unobserve(el);
        var target = parseFloat(el.getAttribute('data-count'));
        if (isNaN(target)) return;
        var decimals = (el.getAttribute('data-count').split('.')[1] || '').length;
        var start = performance.now();
        var dur = 1100;
        var step = function (now) {
          var p = Math.min(1, (now - start) / dur);
          var eased = 1 - Math.pow(1 - p, 3);
          el.textContent = (target * eased).toFixed(decimals);
          if (p < 1) requestAnimationFrame(step);
          else el.textContent = target.toFixed(decimals);
        };
        requestAnimationFrame(step);
      });
    }, { threshold: 0.4 });
    els.forEach(function (el) { io.observe(el); });
  }

  /* -------------------------------------------------------------------
     Cursor enhancement — desktop pointers only
     ------------------------------------------------------------------- */
  function initCursor() {
    if (!canHover.matches || reduceMotion.matches || cfg.cursor === false) return;

    var dot  = document.createElement('div');
    var ring = document.createElement('div');
    dot.className = 'cursor-dot';
    ring.className = 'cursor-ring';
    dot.setAttribute('aria-hidden', 'true');
    ring.setAttribute('aria-hidden', 'true');
    document.body.appendChild(dot);
    document.body.appendChild(ring);
    document.body.classList.add('has-cursor-fx');

    var mx = window.innerWidth / 2, my = window.innerHeight / 2;
    var rx = mx, ry = my;

    on(document, 'mousemove', function (e) {
      mx = e.clientX; my = e.clientY;
      dot.style.transform = 'translate(' + mx + 'px,' + my + 'px) translate(-50%,-50%)';
      document.body.classList.remove('cursor-hidden');
    }, { passive: true });

    on(document, 'mouseleave', function () { document.body.classList.add('cursor-hidden'); });

    (function loop() {
      rx += (mx - rx) * 0.16;
      ry += (my - ry) * 0.16;
      ring.style.transform = 'translate(' + rx.toFixed(2) + 'px,' + ry.toFixed(2) + 'px) translate(-50%,-50%)';
      requestAnimationFrame(loop);
    })();

    var hotSelector = 'a, button, [role="button"], input, textarea, select, .card--interactive, summary';
    on(document, 'mouseover', function (e) {
      if (e.target.closest && e.target.closest(hotSelector)) document.body.classList.add('cursor-hot');
    });
    on(document, 'mouseout', function (e) {
      if (e.target.closest && e.target.closest(hotSelector)) document.body.classList.remove('cursor-hot');
    });
  }

  /* -------------------------------------------------------------------
     Magnetic buttons + cursor-aware card highlight
     ------------------------------------------------------------------- */
  function initMagnetic() {
    if (!canHover.matches || reduceMotion.matches) return;

    $$('[data-magnetic]').forEach(function (el) {
      var strength = parseFloat(el.getAttribute('data-magnetic')) || 0.28;
      var reset = function () { el.style.transform = ''; };
      on(el, 'mousemove', function (e) {
        var r = el.getBoundingClientRect();
        var x = (e.clientX - r.left - r.width / 2) * strength;
        var y = (e.clientY - r.top - r.height / 2) * strength;
        el.style.transform = 'translate(' + x.toFixed(2) + 'px,' + y.toFixed(2) + 'px)';
      });
      on(el, 'mouseleave', reset);
      on(el, 'blur', reset);
    });

    $$('.card--spotlight').forEach(function (card) {
      on(card, 'mousemove', function (e) {
        var r = card.getBoundingClientRect();
        card.style.setProperty('--mx', (e.clientX - r.left) + 'px');
        card.style.setProperty('--my', (e.clientY - r.top) + 'px');
      });
    });
  }

  /* -------------------------------------------------------------------
     Accordion — animated, keyboard accessible, deep-linkable
     ------------------------------------------------------------------- */
  function initAccordion() {
    $$('[data-accordion]').forEach(function (root) {
      var single = root.getAttribute('data-accordion') === 'single';
      var triggers = $$('.accordion__trigger', root);

      triggers.forEach(function (trigger) {
        var panel = document.getElementById(trigger.getAttribute('aria-controls'));
        if (!panel) return;
        on(trigger, 'click', function () {
          var open = trigger.getAttribute('aria-expanded') === 'true';
          if (single && !open) {
            triggers.forEach(function (t) {
              var p = document.getElementById(t.getAttribute('aria-controls'));
              t.setAttribute('aria-expanded', 'false');
              if (p) p.setAttribute('data-open', 'false');
            });
          }
          trigger.setAttribute('aria-expanded', open ? 'false' : 'true');
          panel.setAttribute('data-open', open ? 'false' : 'true');
        });
      });

      // Open the item referenced by the URL hash, if any.
      if (window.location.hash) {
        var target = root.querySelector(window.location.hash.replace(/[^#\w-]/g, ''));
        if (target) {
          var trg = target.closest('.accordion__item');
          var btn = trg && $('.accordion__trigger', trg);
          if (btn) btn.click();
        }
      }
    });
  }

  /* -------------------------------------------------------------------
     Toasts
     ------------------------------------------------------------------- */
  var ICONS = {
    success: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="icon"><circle cx="12" cy="12" r="9"/><path d="m8.5 12.2 2.4 2.4 4.6-5"/></svg>',
    error:   '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="icon"><circle cx="12" cy="12" r="9"/><path d="m9 9 6 6M15 9l-6 6"/></svg>',
    warning: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="icon"><path d="M12 3.5 21.5 20H2.5L12 3.5Z"/><path d="M12 10v4M12 17h.01"/></svg>',
    info:    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="icon"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/></svg>'
  };

  function toast(message, type, timeout) {
    type = type || 'info';
    var stack = $('.toast-stack');
    if (!stack) {
      stack = document.createElement('div');
      stack.className = 'toast-stack';
      stack.setAttribute('role', 'status');
      stack.setAttribute('aria-live', 'polite');
      document.body.appendChild(stack);
    }
    var el = document.createElement('div');
    el.className = 'toast toast--' + type;
    el.innerHTML = (ICONS[type] || ICONS.info) + '<div>' + String(message).replace(/[<>]/g, '') + '</div>';
    var close = document.createElement('button');
    close.className = 'toast__close';
    close.type = 'button';
    close.setAttribute('aria-label', 'Dismiss');
    close.innerHTML = '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg>';
    el.appendChild(close);
    stack.appendChild(el);

    var remove = function () {
      el.classList.add('is-leaving');
      window.setTimeout(function () { if (el.parentNode) el.parentNode.removeChild(el); }, 240);
    };
    on(close, 'click', remove);
    window.setTimeout(remove, timeout || 5200);
  }
  window.techbissToast = toast;

  /* -------------------------------------------------------------------
     Forms: client-side hints, submit lock, async submission
     Server-side validation remains authoritative in every case.
     ------------------------------------------------------------------- */
  function initForms() {
    $$('form[data-form]').forEach(function (form) {
      var submitBtn = form.querySelector('[type="submit"]');
      var isAsync   = form.getAttribute('data-form') === 'async';

      // Clear the invalid state as soon as the visitor starts fixing it.
      $$('.input, .textarea, .select', form).forEach(function (field) {
        on(field, 'input', function () {
          field.classList.remove('is-invalid');
          var err = field.parentNode && field.parentNode.querySelector('.field-error');
          if (err && err.getAttribute('data-live') === 'true') err.remove();
        });
      });

      on(form, 'submit', function (e) {
        if (!form.checkValidity()) {
          e.preventDefault();
          var firstInvalid = form.querySelector(':invalid');
          if (firstInvalid) {
            firstInvalid.classList.add('is-invalid');
            firstInvalid.focus();
            firstInvalid.scrollIntoView({ block: 'center', behavior: reduceMotion.matches ? 'auto' : 'smooth' });
          }
          return;
        }
        if (!isAsync) {
          if (submitBtn) {
            submitBtn.classList.add('is-loading');
            submitBtn.disabled = true;
            // Re-enable if the browser restores the page from bfcache.
            window.setTimeout(function () { submitBtn.classList.remove('is-loading'); submitBtn.disabled = false; }, 12000);
          }
          return;
        }

        e.preventDefault();
        if (submitBtn) { submitBtn.classList.add('is-loading'); submitBtn.disabled = true; }

        fetch(form.action, {
          method: 'POST',
          body: new FormData(form),
          headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
          credentials: 'same-origin'
        })
          .then(function (r) { return r.json().catch(function () { return { ok: false, message: 'Unexpected response from the server.' }; }); })
          .then(function (data) {
            if (data.ok) {
              toast(data.message || 'Thank you — we have received your message.', 'success', 6500);
              form.reset();
              if (data.redirect) window.setTimeout(function () { window.location.href = data.redirect; }, 900);
            } else {
              toast(data.message || 'Please check the form and try again.', 'error', 6500);
              if (data.errors) {
                Object.keys(data.errors).forEach(function (name) {
                  var field = form.querySelector('[name="' + name + '"]');
                  if (!field) return;
                  field.classList.add('is-invalid');
                  var wrap = field.closest('.field') || field.parentNode;
                  if (wrap && !wrap.querySelector('.field-error')) {
                    var msg = document.createElement('span');
                    msg.className = 'field-error';
                    msg.setAttribute('data-live', 'true');
                    msg.textContent = data.errors[name];
                    wrap.appendChild(msg);
                  }
                });
              }
            }
          })
          .catch(function () { toast('We could not reach the server. Please try again.', 'error'); })
          .finally(function () {
            if (submitBtn) { submitBtn.classList.remove('is-loading'); submitBtn.disabled = false; }
          });
      });
    });
  }

  /* -------------------------------------------------------------------
     Multi-step wizard
     ------------------------------------------------------------------- */
  function initWizard() {
    var wizard = $('[data-wizard]');
    if (!wizard) return;

    var panels = $$('.wizard__panel', wizard);
    var pips   = $$('.wizard__pip', wizard);
    var bar    = $('.wizard__bar-fill', wizard);
    var form   = wizard.closest('form') || $('form', wizard);
    var index  = 0;

    function validatePanel(panel) {
      var fields = $$('input, textarea, select', panel);
      var valid = true;
      fields.forEach(function (f) {
        if (f.type === 'hidden' || f.disabled) return;
        if (!f.checkValidity()) {
          f.classList.add('is-invalid');
          if (valid) { f.focus(); f.reportValidity && f.reportValidity(); }
          valid = false;
        }
      });
      // Groups marked as required need at least one checkbox selected.
      $$('[data-require-one]', panel).forEach(function (group) {
        var checked = $$('input[type="checkbox"]', group).some(function (c) { return c.checked; });
        var msg = $('.field-error', group);
        if (!checked) {
          valid = false;
          if (!msg) {
            msg = document.createElement('span');
            msg.className = 'field-error';
            msg.textContent = 'Please choose at least one option.';
            group.appendChild(msg);
          }
        } else if (msg) {
          msg.remove();
        }
      });
      return valid;
    }

    function show(next, skipValidation) {
      if (next > index && !skipValidation && !validatePanel(panels[index])) return;
      index = Math.max(0, Math.min(panels.length - 1, next));
      panels.forEach(function (p, i) { p.classList.toggle('is-active', i === index); });
      pips.forEach(function (pip, i) {
        pip.setAttribute('data-state', i === index ? 'current' : (i < index ? 'done' : 'todo'));
      });
      if (bar) bar.style.setProperty('--progress', (((index + 1) / panels.length) * 100).toFixed(2) + '%');
      updateSummary();
      var head = $('.wizard__panel-head', panels[index]);
      if (head) {
        wizard.scrollIntoView({ block: 'start', behavior: reduceMotion.matches ? 'auto' : 'smooth' });
        var focusable = panels[index].querySelector('input:not([type=hidden]), textarea, select, button');
        if (focusable && canHover.matches) window.setTimeout(function () { focusable.focus({ preventScroll: true }); }, 260);
      }
    }

    function updateSummary() {
      var summary = $('[data-wizard-summary]', wizard);
      if (!summary || !form) return;
      var rows = [];
      var add = function (label, value) {
        if (value) rows.push('<div class="wizard__summary-row"><span class="wizard__summary-label">' + label +
          '</span><span class="wizard__summary-value">' + String(value).replace(/[<>]/g, '') + '</span></div>');
      };
      var val = function (name) { var f = form.querySelector('[name="' + name + '"]'); return f ? f.value.trim() : ''; };
      add('Business', val('business_name'));
      add('Contact', val('name'));
      var services = $$('[name="services_needed[]"]:checked', form).map(function (c) {
        var lbl = c.closest('.option-card');
        var t = lbl && lbl.querySelector('.option-card__title');
        return t ? t.textContent.trim() : c.value;
      });
      add('Needs', services.join(', '));
      add('Budget', (form.querySelector('[name="budget_range"]:checked') || {}).value || '');
      add('Timeline', (form.querySelector('[name="timeline"]:checked') || {}).value || '');
      summary.innerHTML = rows.length ? rows.join('') :
        '<p class="text-muted" style="font-size:var(--fs-sm)">Complete the earlier steps and your answers will appear here.</p>';
    }

    $$('[data-wizard-next]', wizard).forEach(function (btn) {
      on(btn, 'click', function (e) { e.preventDefault(); show(index + 1); });
    });
    $$('[data-wizard-prev]', wizard).forEach(function (btn) {
      on(btn, 'click', function (e) { e.preventDefault(); show(index - 1, true); });
    });
    pips.forEach(function (pip, i) {
      on(pip, 'click', function () { if (i < index) show(i, true); });
    });
    if (form) {
      on(form, 'change', updateSummary);
      on(form, 'submit', function (e) {
        // Validate every panel before the final submission.
        for (var i = 0; i < panels.length; i++) {
          if (!validatePanel(panels[i])) { e.preventDefault(); show(i, true); return; }
        }
      });
    }
    show(0, true);
  }

  /* -------------------------------------------------------------------
     Package selection highlight + checkout totals
     ------------------------------------------------------------------- */
  function initCheckout() {
    var root = $('[data-checkout]');
    if (!root) return;

    var currency = root.getAttribute('data-currency') || '$';
    var base     = parseFloat(root.getAttribute('data-base')) || 0;
    var regular  = parseFloat(root.getAttribute('data-regular')) || 0;

    function fmt(n) {
      return currency + n.toLocaleString(undefined, {
        minimumFractionDigits: n % 1 === 0 ? 0 : 2,
        maximumFractionDigits: 2
      });
    }

    function recalc() {
      var addonTotal = 0;
      $$('input[name="addons[]"]:checked', root).forEach(function (c) {
        addonTotal += parseFloat(c.getAttribute('data-price')) || 0;
      });
      var total = base + addonTotal;
      var setText = function (sel, value) { var el = $(sel, root); if (el) el.textContent = value; };
      setText('[data-total-addons]', fmt(addonTotal));
      setText('[data-total-final]', fmt(total));
      var savingEl = $('[data-total-saving]', root);
      if (savingEl && regular > base) savingEl.textContent = fmt(regular - base);
    }

    $$('input[name="addons[]"]', root).forEach(function (c) { on(c, 'change', recalc); });
    recalc();

    // Highlight the chosen package card
    $$('[data-package-option]', document).forEach(function (input) {
      on(input, 'change', function () {
        $$('.package-card').forEach(function (card) { card.classList.remove('package-card--selected'); });
        var card = input.closest('.package-card');
        if (card) card.classList.add('package-card--selected');
      });
      if (input.checked) {
        var card = input.closest('.package-card');
        if (card) card.classList.add('package-card--selected');
      }
    });
  }

  /* -------------------------------------------------------------------
     Gallery lightbox — keyboard and swipe friendly
     ------------------------------------------------------------------- */
  function initLightbox() {
    var items = $$('[data-lightbox]');
    if (!items.length) return;

    var box, imgEl, countEl, current = 0;

    function render() {
      var src = items[current].getAttribute('data-lightbox');
      var alt = items[current].getAttribute('data-lightbox-alt') || '';
      imgEl.src = src;
      imgEl.alt = alt;
      if (countEl) countEl.textContent = (current + 1) + ' / ' + items.length;
    }

    function open(i) {
      current = i;
      box = document.createElement('div');
      box.className = 'lightbox';
      box.setAttribute('role', 'dialog');
      box.setAttribute('aria-modal', 'true');
      box.setAttribute('aria-label', 'Project image viewer');
      box.innerHTML =
        '<button class="lightbox__close" type="button" aria-label="Close viewer">' +
        '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg></button>' +
        (items.length > 1 ? '<button class="lightbox__nav lightbox__nav--prev" type="button" aria-label="Previous image"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m15 6-6 6 6 6"/></svg></button>' +
        '<button class="lightbox__nav lightbox__nav--next" type="button" aria-label="Next image"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m9 6 6 6-6 6"/></svg></button>' +
        '<span class="lightbox__count"></span>' : '') +
        '<img alt="">';
      document.body.appendChild(box);
      document.body.classList.add('no-scroll');
      imgEl = $('img', box);
      countEl = $('.lightbox__count', box);
      render();

      on($('.lightbox__close', box), 'click', close);
      var prev = $('.lightbox__nav--prev', box), next = $('.lightbox__nav--next', box);
      if (prev) on(prev, 'click', function () { current = (current - 1 + items.length) % items.length; render(); });
      if (next) on(next, 'click', function () { current = (current + 1) % items.length; render(); });
      on(box, 'click', function (e) { if (e.target === box) close(); });
      on(document, 'keydown', keys);

      // Swipe on touch
      var sx = 0;
      on(box, 'touchstart', function (e) { sx = e.touches[0].clientX; }, { passive: true });
      on(box, 'touchend', function (e) {
        var dx = e.changedTouches[0].clientX - sx;
        if (Math.abs(dx) > 48 && items.length > 1) {
          current = (current + (dx < 0 ? 1 : -1) + items.length) % items.length;
          render();
        }
      }, { passive: true });

      $('.lightbox__close', box).focus();
    }

    function keys(e) {
      if (!box) return;
      if (e.key === 'Escape') close();
      else if (e.key === 'ArrowRight' && items.length > 1) { current = (current + 1) % items.length; render(); }
      else if (e.key === 'ArrowLeft' && items.length > 1) { current = (current - 1 + items.length) % items.length; render(); }
    }

    function close() {
      if (!box) return;
      document.removeEventListener('keydown', keys);
      document.body.classList.remove('no-scroll');
      if (box.parentNode) box.parentNode.removeChild(box);
      box = null;
      if (items[current]) items[current].focus();
    }

    items.forEach(function (item, i) {
      item.setAttribute('tabindex', '0');
      item.setAttribute('role', 'button');
      on(item, 'click', function () { open(i); });
      on(item, 'keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); open(i); }
      });
    });
  }

  /* -------------------------------------------------------------------
     Copy-to-clipboard buttons
     ------------------------------------------------------------------- */
  function initCopy() {
    $$('[data-copy]').forEach(function (btn) {
      on(btn, 'click', function () {
        var text = btn.getAttribute('data-copy');
        var done = function () { toast('Copied to clipboard.', 'success', 2400); };
        if (navigator.clipboard && window.isSecureContext) {
          navigator.clipboard.writeText(text).then(done).catch(function () { toast('Could not copy.', 'error'); });
        } else {
          var ta = document.createElement('textarea');
          ta.value = text;
          ta.style.position = 'fixed';
          ta.style.opacity = '0';
          document.body.appendChild(ta);
          ta.select();
          try { document.execCommand('copy'); done(); } catch (e) { toast('Could not copy.', 'error'); }
          document.body.removeChild(ta);
        }
      });
    });
  }

  /* -------------------------------------------------------------------
     Auto-submitting filter controls
     ------------------------------------------------------------------- */
  function initFilters() {
    $$('[data-autosubmit]').forEach(function (el) {
      on(el, 'change', function () {
        var form = el.closest('form');
        if (form) form.submit();
      });
    });
  }

  /* -------------------------------------------------------------------
     Page transitions — a short cross-fade on same-origin navigation
     ------------------------------------------------------------------- */
  function initTransitions() {
    if (reduceMotion.matches || cfg.transitions === false) return;
    var veil = $('.page-veil');
    if (!veil) return;

    document.body.classList.add('is-entering');

    on(document, 'click', function (e) {
      var link = e.target.closest && e.target.closest('a');
      if (!link) return;
      if (e.metaKey || e.ctrlKey || e.shiftKey || e.button !== 0) return;
      var href = link.getAttribute('href');
      if (!href || href.charAt(0) === '#' || link.target === '_blank' || link.hasAttribute('download')) return;
      if (link.hasAttribute('data-no-transition')) return;
      var url;
      try { url = new URL(link.href, window.location.href); } catch (err) { return; }
      if (url.origin !== window.location.origin) return;
      if (url.pathname === window.location.pathname && url.search === window.location.search) return;
      if (/^(mailto:|tel:)/i.test(href)) return;

      e.preventDefault();
      veil.classList.add('is-active');
      window.setTimeout(function () { window.location.href = link.href; }, 170);
    });

    // Clear the veil when returning through the back/forward cache.
    on(window, 'pageshow', function (e) { if (e.persisted) veil.classList.remove('is-active'); });
  }

  /* -------------------------------------------------------------------
     Server-rendered flash messages become toasts
     ------------------------------------------------------------------- */
  function initFlash() {
    $$('[data-flash]').forEach(function (el) {
      toast(el.getAttribute('data-flash-message') || el.textContent.trim(), el.getAttribute('data-flash') || 'info', 6000);
      el.remove();
    });
  }

  /* -------------------------------------------------------------------
     Boot
     ------------------------------------------------------------------- */
  function boot() {
    initLoader();
    initTheme();
    initHeader();
    initNav();
    initReveal();
    initCounters();
    initAccordion();
    initForms();
    initWizard();
    initCheckout();
    initLightbox();
    initCopy();
    initFilters();
    initTransitions();
    initFlash();
    if (cfg.cursor !== false) initCursor();
    initMagnetic();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
