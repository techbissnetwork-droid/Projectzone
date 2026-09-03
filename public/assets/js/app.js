/* TECHBISS runtime. No dependencies, deferred, ~7KB. Every behaviour is a
   progressive enhancement: the platform is fully usable with JS disabled. */
(function () {
  'use strict';

  var doc = document;
  var root = doc.documentElement;
  var $ = function (s, c) { return (c || doc).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || doc).querySelectorAll(s)); };
  var on = function (el, ev, fn, o) { if (el) el.addEventListener(ev, fn, o || false); };
  var mqDesktop = window.matchMedia('(min-width: 1024px)');
  var mqFine = window.matchMedia('(hover: hover) and (pointer: fine)');
  var reduced = window.matchMedia('(prefers-reduced-motion: reduce)');

  /* ---- theme ------------------------------------------------------------ */
  function initTheme() {
    $$('[data-theme-toggle]').forEach(function (btn) {
      on(btn, 'click', function () {
        var next = root.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
        root.setAttribute('data-theme', next);
        btn.setAttribute('aria-label', next === 'light' ? 'Switch to dark theme' : 'Switch to light theme');
        try { localStorage.setItem('tb-theme', next); } catch (e) {}
      });
    });
  }

  /* ---- header elevation (observer, not a scroll handler) ---------------- */
  function initHeader() {
    var header = $('[data-header]');
    if (!header) return;
    var sentinel = doc.createElement('div');
    sentinel.setAttribute('aria-hidden', 'true');
    sentinel.style.cssText = 'position:absolute;top:0;left:0;width:1px;height:1px;pointer-events:none';
    doc.body.prepend(sentinel);
    if (!('IntersectionObserver' in window)) return;
    new IntersectionObserver(function (entries) {
      header.classList.toggle('is-stuck', !entries[0].isIntersecting);
    }, { rootMargin: '-4px 0px 0px 0px' }).observe(sentinel);
  }

  /* ---- desktop mega menu ------------------------------------------------ */
  function initMega() {
    var triggers = $$('[data-mega-trigger]');
    if (!triggers.length) return;
    var openKey = null, closeTimer = null;

    function close() {
      $$('[data-mega]').forEach(function (p) { p.classList.remove('is-open'); });
      triggers.forEach(function (t) { t.classList.remove('is-open'); t.setAttribute('aria-expanded', 'false'); });
      openKey = null;
    }
    function open(key) {
      if (!mqDesktop.matches) return;
      clearTimeout(closeTimer);
      if (openKey === key) return;
      close();
      var panel = $('[data-mega="' + key + '"]');
      var trig = $('[data-mega-trigger="' + key + '"]');
      if (!panel || !trig) return;
      panel.classList.add('is-open');
      trig.classList.add('is-open');
      trig.setAttribute('aria-expanded', 'true');
      openKey = key;
    }

    triggers.forEach(function (trig) {
      var key = trig.getAttribute('data-mega-trigger');
      on(trig, 'click', function (e) {
        if (!mqDesktop.matches) return;
        e.preventDefault();
        openKey === key ? close() : open(key);
      });
      on(trig, 'mouseenter', function () { if (mqFine.matches) open(key); });
      on(trig, 'focus', function () { open(key); });
      on(trig, 'mouseleave', function () { closeTimer = setTimeout(close, 180); });
    });

    $$('[data-mega]').forEach(function (panel) {
      on(panel, 'mouseenter', function () { clearTimeout(closeTimer); });
      on(panel, 'mouseleave', function () { closeTimer = setTimeout(close, 180); });
    });

    on(doc, 'keydown', function (e) { if (e.key === 'Escape') close(); });
    on(doc, 'click', function (e) {
      if (openKey && !e.target.closest('[data-mega]') && !e.target.closest('[data-mega-trigger]')) close();
    });
    on(doc, 'focusin', function (e) {
      if (openKey && !e.target.closest('[data-mega]') && !e.target.closest('[data-mega-trigger]')) close();
    });
  }

  /* ---- mobile drawer ---------------------------------------------------- */
  function initDrawer() {
    var drawer = $('[data-drawer]');
    var burger = $('[data-drawer-open]');
    if (!drawer || !burger) return;
    var panel = $('.drawer__panel', drawer);
    var lastFocus = null;

    function setOpen(open) {
      drawer.classList.toggle('is-open', open);
      burger.setAttribute('aria-expanded', String(open));
      drawer.setAttribute('aria-hidden', String(!open));
      doc.body.style.overflow = open ? 'hidden' : '';
      if (open) {
        lastFocus = doc.activeElement;
        var first = $('a,button', panel);
        if (first) first.focus();
      } else if (lastFocus) {
        lastFocus.focus();
      }
    }

    on(burger, 'click', function () { setOpen(!drawer.classList.contains('is-open')); });
    $$('[data-drawer-close]', drawer).forEach(function (el) { on(el, 'click', function () { setOpen(false); }); });
    on(doc, 'keydown', function (e) {
      if (e.key === 'Escape' && drawer.classList.contains('is-open')) setOpen(false);
      if (e.key === 'Tab' && drawer.classList.contains('is-open')) {
        var f = $$('a[href],button:not([disabled]),input,select,textarea', panel).filter(function (n) { return n.offsetParent !== null; });
        if (!f.length) return;
        var first = f[0], last = f[f.length - 1];
        if (e.shiftKey && doc.activeElement === first) { e.preventDefault(); last.focus(); }
        else if (!e.shiftKey && doc.activeElement === last) { e.preventDefault(); first.focus(); }
      }
    });
    on(mqDesktop, 'change', function (e) { if (e.matches) setOpen(false); });

    // Collapsible sections inside the drawer.
    $$('[data-drawer-sub]', drawer).forEach(function (btn) {
      var sub = doc.getElementById(btn.getAttribute('aria-controls'));
      if (!sub) return;
      on(btn, 'click', function () {
        var open = btn.getAttribute('aria-expanded') === 'true';
        btn.setAttribute('aria-expanded', String(!open));
        sub.style.maxHeight = open ? '0px' : sub.scrollHeight + 'px';
      });
    });
  }

  /* ---- reveal on scroll ------------------------------------------------- */
  function initReveal() {
    var items = $$('[data-reveal]');
    if (!items.length) return;
    if (!('IntersectionObserver' in window) || reduced.matches) {
      items.forEach(function (el) { el.classList.add('is-in'); });
      return;
    }
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        var el = entry.target;
        var delay = parseInt(el.getAttribute('data-reveal') || '0', 10) || 0;
        if (delay) el.style.transitionDelay = delay + 'ms';
        el.classList.add('is-in');
        io.unobserve(el);
      });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.05 });
    items.forEach(function (el) { io.observe(el); });
  }

  /* ---- animated counters ------------------------------------------------ */
  function initCounters() {
    var els = $$('[data-count]');
    if (!els.length || !('IntersectionObserver' in window) || reduced.matches) return;
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        var el = entry.target;
        io.unobserve(el);
        var target = parseFloat(el.getAttribute('data-count'));
        if (isNaN(target)) return;
        var decimals = (el.getAttribute('data-count').split('.')[1] || '').length;
        var start = performance.now(), dur = 1100;
        (function frame(now) {
          var p = Math.min(1, (now - start) / dur);
          var eased = 1 - Math.pow(1 - p, 3);
          el.textContent = (target * eased).toFixed(decimals);
          if (p < 1) requestAnimationFrame(frame);
        })(start);
      });
    }, { threshold: 0.4 });
    els.forEach(function (el) { io.observe(el); });
  }

  /* ---- sticky mobile action bar ----------------------------------------- */
  function initActionBar() {
    var bar = $('[data-actionbar]');
    var anchor = $('[data-actionbar-after]');
    if (!bar || !anchor || !('IntersectionObserver' in window)) return;
    new IntersectionObserver(function (entries) {
      bar.classList.toggle('is-visible', !entries[0].isIntersecting);
    }, { rootMargin: '0px' }).observe(anchor);
  }

  /* ---- pointer sheen + tilt (desktop pointer only) ---------------------- */
  function initPointer() {
    if (!mqFine.matches || !mqDesktop.matches || reduced.matches) return;

    $$('.spotlight').forEach(function (el) {
      on(el, 'pointermove', function (e) {
        var r = el.getBoundingClientRect();
        el.style.setProperty('--mx', ((e.clientX - r.left) / r.width * 100) + '%');
        el.style.setProperty('--my', ((e.clientY - r.top) / r.height * 100) + '%');
      });
    });

    $$('.tilt').forEach(function (el) {
      var raf = null;
      on(el, 'pointermove', function (e) {
        if (raf) return;
        raf = requestAnimationFrame(function () {
          raf = null;
          var r = el.getBoundingClientRect();
          var x = (e.clientX - r.left) / r.width - 0.5;
          var y = (e.clientY - r.top) / r.height - 0.5;
          var max = parseFloat(el.getAttribute('data-tilt') || '6');
          el.style.transform = 'perspective(900px) rotateX(' + (-y * max).toFixed(2) + 'deg) rotateY(' + (x * max).toFixed(2) + 'deg)';
        });
      });
      on(el, 'pointerleave', function () { el.style.transform = ''; });
    });
  }

  /* ---- marketplace ------------------------------------------------------ */
  function initMarketplace() {
    var toggle = $('[data-filters-toggle]');
    var filters = $('[data-filters]');
    if (toggle && filters) {
      on(toggle, 'click', function () {
        var open = filters.classList.toggle('is-open');
        toggle.setAttribute('aria-expanded', String(open));
      });
    }

    // Live search: enhances the form, which still works as a normal GET.
    var form = $('[data-search-form]');
    var input = form && $('input[name="q"]', form);
    var results = $('[data-search-results]');
    var count = $('[data-search-count]');
    if (form && input && results) {
      var timer = null, controller = null;
      on(input, 'input', function () {
        clearTimeout(timer);
        timer = setTimeout(function () {
          if (controller) controller.abort();
          controller = new AbortController();
          var params = new URLSearchParams(new FormData(form));
          fetch(form.getAttribute('data-search-endpoint') + '?' + params.toString(), {
            signal: controller.signal,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
          })
            .then(function (r) { return r.json(); })
            .then(function (data) {
              if (!data || typeof data.html !== 'string') return;
              results.innerHTML = data.html;
              if (count) count.textContent = data.label || '';
              var url = new URL(window.location.href);
              url.search = params.toString();
              history.replaceState({}, '', url);
              initPointer();
            })
            .catch(function () { /* offline or aborted — the form still submits */ });
        }, 260);
      });
    }

    // Product gallery.
    var stage = $('[data-gallery-stage]');
    if (stage) {
      $$('[data-gallery-thumb]').forEach(function (btn) {
        on(btn, 'click', function () {
          $$('[data-gallery-thumb]').forEach(function (b) { b.setAttribute('aria-selected', 'false'); });
          btn.setAttribute('aria-selected', 'true');
          stage.innerHTML = btn.innerHTML;
        });
      });
    }
  }

  /* ---- copy to clipboard ------------------------------------------------ */
  function initCopy() {
    $$('[data-copy]').forEach(function (btn) {
      on(btn, 'click', function () {
        var target = doc.getElementById(btn.getAttribute('data-copy'));
        if (!target || !navigator.clipboard) return;
        navigator.clipboard.writeText(target.textContent.trim()).then(function () {
          var original = btn.textContent;
          btn.textContent = 'Copied';
          setTimeout(function () { btn.textContent = original; }, 1600);
        });
      });
    });
  }

  /* ---- installer async checks ------------------------------------------ */
  function initInstaller() {
    $$('[data-async-form]').forEach(function (form) {
      var out = doc.getElementById(form.getAttribute('data-async-target'));
      if (!out) return;
      on(form, 'submit', function (e) {
        e.preventDefault();
        var btn = $('[type="submit"]', form);
        if (btn) { btn.setAttribute('aria-disabled', 'true'); btn.dataset.label = btn.textContent; btn.textContent = 'Checking…'; }
        fetch(form.action, {
          method: 'POST',
          body: new FormData(form),
          headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
          .then(function (r) { return r.json(); })
          .then(function (data) { out.innerHTML = data.html || ''; })
          .catch(function () { out.innerHTML = '<div class="alert alert--bad">Could not reach the server. Check your connection and try again.</div>'; })
          .finally(function () {
            if (btn) { btn.removeAttribute('aria-disabled'); btn.textContent = btn.dataset.label || 'Test'; }
          });
      });
    });
  }

  /* ---- boot ------------------------------------------------------------- */
  function boot() {
    initTheme();
    initHeader();
    initMega();
    initDrawer();
    initReveal();
    initCounters();
    initActionBar();
    initPointer();
    initMarketplace();
    initCopy();
    initInstaller();
  }

  doc.readyState === 'loading' ? on(doc, 'DOMContentLoaded', boot) : boot();
})();
