/* ==========================================================================
   TECHBISS — Warm Authority — shared motion & interaction system
   Cross-page mechanisms only: theme toggle, header/mobile-nav, scroll reveal,
   counters, draggable filmstrips, a generic expand/collapse helper, a generic
   modal helper, and SVG "draw in on scroll" for the hero ecosystem diagram.
   Page-specific logic (marketplace filters, the installer wizard, dashboard
   tabs, the login form) lives inline at the bottom of each page, same as this
   system's precedent elsewhere in the project.
   Defensive against missing CDN libs / reduced motion throughout.
   ========================================================================== */

/* ---------------- Theme toggle (runs first, no dependencies) ---------------- */
(function(){
  "use strict";
  var STORAGE_KEY = 'techbiss-theme';
  var root = document.documentElement;
  var mql = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;

  function getStored(){
    try {
      var t = localStorage.getItem(STORAGE_KEY);
      return (t === 'light' || t === 'dark' || t === 'system') ? t : 'system';
    } catch (e) { return 'system'; }
  }

  function applyTheme(pref){
    var actual = pref === 'system' ? ((mql && mql.matches) ? 'dark' : 'light') : pref;
    root.setAttribute('data-theme', actual);
    document.querySelectorAll('[data-theme-toggle]').forEach(function(group){
      group.querySelectorAll('[role="radio"]').forEach(function(btn){
        var checked = btn.getAttribute('data-value') === pref;
        btn.setAttribute('aria-checked', checked ? 'true' : 'false');
        btn.tabIndex = checked ? 0 : -1;
      });
    });
  }

  function setTheme(pref){
    try { localStorage.setItem(STORAGE_KEY, pref); } catch (e) {}
    applyTheme(pref);
  }

  applyTheme(getStored());

  document.querySelectorAll('[data-theme-toggle]').forEach(function(group){
    var buttons = Array.prototype.slice.call(group.querySelectorAll('[role="radio"]'));
    buttons.forEach(function(btn){
      btn.addEventListener('click', function(){ setTheme(btn.getAttribute('data-value')); });
    });
    group.addEventListener('keydown', function(e){
      if (['ArrowRight','ArrowDown','ArrowLeft','ArrowUp'].indexOf(e.key) === -1) return;
      e.preventDefault();
      var idx = buttons.findIndex(function(b){ return b.getAttribute('aria-checked') === 'true'; });
      var dir = (e.key === 'ArrowRight' || e.key === 'ArrowDown') ? 1 : -1;
      var next = buttons[(idx + dir + buttons.length) % buttons.length];
      next.focus();
      setTheme(next.getAttribute('data-value'));
    });
  });

  if (mql) {
    mql.addEventListener('change', function(){
      if (getStored() === 'system') applyTheme('system');
    });
  }

  /* Enable the theme-fade transition only after first paint, so the
     attribute the head script already set never itself animates. */
  function markReady(){ document.body && document.body.classList.add('theme-ready'); }
  if (document.body) requestAnimationFrame(function(){ requestAnimationFrame(markReady); });
  else document.addEventListener('DOMContentLoaded', function(){ requestAnimationFrame(function(){ requestAnimationFrame(markReady); }); });
})();

/* ---------------- Everything else ---------------- */
(function(){
  "use strict";
  var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var isCoarse = window.matchMedia && window.matchMedia('(hover: none), (pointer: coarse)').matches;
  var hasGSAP = typeof window.gsap !== 'undefined';
  var hasLenis = typeof window.Lenis !== 'undefined';

  if (hasGSAP && window.ScrollTrigger) gsap.registerPlugin(ScrollTrigger);

  if (hasLenis && !reduced && !isCoarse) {
    var lenis = new Lenis({ duration: 1.0, smoothWheel: true, wheelMultiplier: 1 });
    document.documentElement.classList.add('has-lenis');
    (function raf(time){ lenis.raf(time); requestAnimationFrame(raf); })();
    if (hasGSAP && window.ScrollTrigger) lenis.on('scroll', ScrollTrigger.update);
  }

  /* ---------------- Header scroll state ---------------- */
  var header = document.querySelector('[data-header]');
  function onScroll(){ if (header) header.classList.toggle('is-scrolled', window.scrollY > 18); }
  onScroll();
  window.addEventListener('scroll', onScroll, { passive: true });

  /* ---------------- Mobile nav ---------------- */
  var navToggle = document.querySelector('[data-nav-toggle]');
  var mobileNav = document.querySelector('[data-mobile-nav]');
  if (navToggle && mobileNav) {
    navToggle.addEventListener('click', function(){
      var open = mobileNav.classList.toggle('is-open');
      navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      document.body.style.overflow = open ? 'hidden' : '';
    });
    mobileNav.querySelectorAll('a').forEach(function(a){
      a.addEventListener('click', function(){
        mobileNav.classList.remove('is-open');
        navToggle.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';
      });
    });
    document.addEventListener('keydown', function(e){
      if (e.key === 'Escape' && mobileNav.classList.contains('is-open')) {
        mobileNav.classList.remove('is-open');
        navToggle.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';
        navToggle.focus();
      }
    });
  }

  /* ---------------- Magnetic hover (desktop, fine pointer only) ---------------- */
  if (!isCoarse && !reduced) {
    document.querySelectorAll('.magnetic').forEach(function(el){
      el.addEventListener('mousemove', function(e){
        var r = el.getBoundingClientRect();
        var x = (e.clientX - r.left - r.width / 2) * 0.22;
        var y = (e.clientY - r.top - r.height / 2) * 0.22;
        el.style.transform = 'translate(' + x + 'px,' + y + 'px)';
      });
      el.addEventListener('mouseleave', function(){ el.style.transform = ''; });
    });
  }

  /* ---------------- Scroll reveals ---------------- */
  var revealEls = document.querySelectorAll('[data-reveal]');
  function showReveal(el){ el.classList.add('reveal-in'); }
  if (reduced || !('IntersectionObserver' in window)) {
    revealEls.forEach(showReveal);
  } else {
    var io = new IntersectionObserver(function(entries){
      entries.forEach(function(entry){
        if (!entry.isIntersecting) return;
        var delay = entry.target.getAttribute('data-reveal-delay');
        entry.target.style.transitionDelay = delay ? (delay + 'ms') : '0ms';
        showReveal(entry.target);
        io.unobserve(entry.target);
      });
    }, { threshold: 0.16, rootMargin: '0px 0px -8% 0px' });
    revealEls.forEach(function(el){ io.observe(el); });
    setTimeout(function(){ revealEls.forEach(function(el){ if (!el.classList.contains('reveal-in')) showReveal(el); }); }, 2500);
  }
  document.querySelectorAll('[data-stagger]').forEach(function(group){
    Array.prototype.forEach.call(group.children, function(child, i){
      if (child.hasAttribute('data-reveal')) child.setAttribute('data-reveal-delay', i * 70);
    });
  });

  /* ---------------- Animated counters ---------------- */
  var counters = document.querySelectorAll('[data-count]');
  if (counters.length) {
    var counterIO = new IntersectionObserver(function(entries){
      entries.forEach(function(entry){
        if (!entry.isIntersecting) return;
        var el = entry.target;
        var target = parseFloat(el.getAttribute('data-count'));
        var suffix = el.getAttribute('data-suffix') || '';
        var prefix = el.getAttribute('data-prefix') || '';
        var decimals = (el.getAttribute('data-count').split('.')[1] || '').length;
        if (reduced) {
          el.textContent = prefix + target.toFixed(decimals) + suffix;
        } else {
          var start = performance.now(), dur = 1200;
          (function tick(now){
            var p = Math.min(1, (now - start) / dur);
            var eased = 1 - Math.pow(1 - p, 3);
            el.textContent = prefix + (target * eased).toFixed(decimals) + suffix;
            if (p < 1) requestAnimationFrame(tick);
          })(start);
        }
        counterIO.unobserve(el);
      });
    }, { threshold: 0.4 });
    counters.forEach(function(el){ counterIO.observe(el); });
  }

  /* ---------------- Draggable filmstrips ---------------- */
  document.querySelectorAll('.filmstrip').forEach(function(strip){
    var isDown = false, startX, scrollLeft;
    strip.addEventListener('pointerdown', function(e){
      isDown = true; strip.classList.add('is-dragging'); startX = e.clientX; scrollLeft = strip.scrollLeft;
      try { strip.setPointerCapture(e.pointerId); } catch (err) {}
    });
    strip.addEventListener('pointermove', function(e){ if (isDown) strip.scrollLeft = scrollLeft - (e.clientX - startX); });
    ['pointerup', 'pointerleave', 'pointercancel'].forEach(function(ev){
      strip.addEventListener(ev, function(){ isDown = false; strip.classList.remove('is-dragging'); });
    });
    var wrap = strip.closest('.filmstrip-wrap');
    if (wrap) {
      var prev = wrap.querySelector('[data-scroll-prev]');
      var next = wrap.querySelector('[data-scroll-next]');
      var stepDist = function(){
        var card = strip.firstElementChild;
        return (card && card.getBoundingClientRect().width + 21) || 320;
      };
      if (next) next.addEventListener('click', function(){ strip.scrollBy({ left: stepDist(), behavior: reduced ? 'auto' : 'smooth' }); });
      if (prev) prev.addEventListener('click', function(){ strip.scrollBy({ left: -stepDist(), behavior: reduced ? 'auto' : 'smooth' }); });
    }
  });

  /* ---------------- Generic expand/collapse (service cards, tickets, FAQs) ----------------
     Any button[data-expand-toggle][aria-controls="panel-id"] toggles the
     max-height of #panel-id and flips aria-expanded + an .is-open class on
     the closest [data-expand-item] wrapper (for chevron/icon styling). Each
     toggle is independent — no "close siblings" grouping, since these are
     grids/lists of independently-openable items, not single-select accordions. */
  document.querySelectorAll('[data-expand-toggle]').forEach(function(btn){
    var panel = document.getElementById(btn.getAttribute('aria-controls'));
    if (!panel) return;
    var item = btn.closest('[data-expand-item]') || btn.parentElement;
    btn.addEventListener('click', function(){
      var open = btn.getAttribute('aria-expanded') === 'true';
      btn.setAttribute('aria-expanded', open ? 'false' : 'true');
      if (item) item.classList.toggle('is-open', !open);
      panel.style.maxHeight = open ? '' : panel.scrollHeight + 'px';
    });
  });
  /* Keep open expand-panels correctly sized across viewport/font changes. */
  window.addEventListener('resize', function(){
    document.querySelectorAll('[data-expand-toggle][aria-expanded="true"]').forEach(function(btn){
      var panel = document.getElementById(btn.getAttribute('aria-controls'));
      if (panel) panel.style.maxHeight = panel.scrollHeight + 'px';
    });
  });

  /* ---------------- Generic modal ----------------
     [data-modal-open][data-modal-target="#id"] opens #id; inside a modal,
     [data-modal-close] and the overlay backdrop close it; Escape closes the
     topmost open modal; focus moves to the modal on open and back to the
     trigger on close. */
  var lastModalTrigger = null;
  function openModal(overlay, trigger){
    lastModalTrigger = trigger || document.activeElement;
    overlay.classList.add('is-open');
    overlay.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    var modal = overlay.querySelector('.modal');
    if (modal) { modal.setAttribute('tabindex', '-1'); modal.focus({ preventScroll: true }); }
  }
  function closeModal(overlay){
    overlay.classList.remove('is-open');
    overlay.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
    if (lastModalTrigger && typeof lastModalTrigger.focus === 'function') lastModalTrigger.focus({ preventScroll: true });
  }
  document.querySelectorAll('[data-modal-open]').forEach(function(btn){
    var target = document.querySelector(btn.getAttribute('data-modal-target'));
    if (!target) return;
    btn.addEventListener('click', function(){ openModal(target, btn); });
  });
  document.querySelectorAll('.modal-overlay').forEach(function(overlay){
    overlay.addEventListener('click', function(e){ if (e.target === overlay) closeModal(overlay); });
    overlay.querySelectorAll('[data-modal-close]').forEach(function(btn){
      btn.addEventListener('click', function(){ closeModal(overlay); });
    });
  });
  document.addEventListener('keydown', function(e){
    if (e.key !== 'Escape') return;
    document.querySelectorAll('.modal-overlay.is-open').forEach(function(overlay){ closeModal(overlay); });
  });
  window.techbissModal = { open: openModal, close: closeModal };

  /* ---------------- SVG "draw in on scroll" (hero ecosystem diagram) ---------------- */
  document.querySelectorAll('[data-draw-path]').forEach(function(path){
    var len = 0;
    try { len = path.getTotalLength(); } catch (e) {}
    if (!len || reduced) return;
    path.style.strokeDasharray = len;
    path.style.strokeDashoffset = len;
    path.style.transition = 'stroke-dashoffset 1.8s ' + (hasGSAP ? 'cubic-bezier(.22,1,.36,1)' : 'ease') + ' .1s';
  });
  var ecoSections = document.querySelectorAll('[data-eco-trigger]');
  if (ecoSections.length) {
    function activateEco(section){
      section.classList.add('is-active');
      var path = section.querySelector('[data-draw-path]');
      if (path && !reduced) path.style.strokeDashoffset = '0';
      var nodes = section.querySelectorAll('.eco-node');
      nodes.forEach(function(node, i){
        node.style.transitionDelay = reduced ? '0ms' : (300 + i * 90) + 'ms';
        node.classList.add('reveal-in');
      });
    }
    if (reduced || !('IntersectionObserver' in window)) {
      ecoSections.forEach(activateEco);
    } else {
      var ecoIO = new IntersectionObserver(function(entries){
        entries.forEach(function(entry){
          if (!entry.isIntersecting) return;
          activateEco(entry.target);
          ecoIO.unobserve(entry.target);
        });
      }, { threshold: 0.3 });
      ecoSections.forEach(function(s){ ecoIO.observe(s); });
      setTimeout(function(){ ecoSections.forEach(function(s){ if (!s.classList.contains('is-active')) activateEco(s); }); }, 2500);
    }
  }

  /* ---------------- Scroll-reactive process timeline ----------------
     A horizontal rail (Discover → Design → Build → Launch → Grow) that
     lights up node-by-node the first time it's scrolled into view, rather
     than trying to tie activation to horizontal scroll position (the row
     has no per-item vertical stagger to observe, since all five sit at the
     same height). */
  document.querySelectorAll('[data-timeline]').forEach(function(rail){
    var items = Array.prototype.slice.call(rail.querySelectorAll('[data-timeline-item]'));
    if (!items.length) return;
    function activateUpTo(idx){
      items.forEach(function(it, i){
        it.classList.toggle('is-done', i < idx);
        it.classList.toggle('is-active', i === idx);
      });
      rail.style.setProperty('--fill', String(idx / (items.length - 1)));
    }
    function playSequence(){
      if (reduced) { activateUpTo(items.length - 1); return; }
      items.forEach(function(_, i){ setTimeout(function(){ activateUpTo(i); }, i * 260); });
    }
    if (!('IntersectionObserver' in window)) { playSequence(); return; }
    var tIO = new IntersectionObserver(function(entries){
      entries.forEach(function(entry){
        if (!entry.isIntersecting) return;
        playSequence();
        tIO.unobserve(entry.target);
      });
    }, { threshold: 0.35 });
    tIO.observe(rail);
  });

  /* ---------------- Current-page nav highlighting ---------------- */
  var path = (location.pathname.split('/').pop() || 'index.html');
  document.querySelectorAll('a[href]').forEach(function(a){
    var href = a.getAttribute('href').split('?')[0].split('#')[0];
    if (href === path || (href === '' && path === 'index.html') || (href === 'index.html' && path === '')) {
      a.setAttribute('aria-current', 'page');
    }
  });
})();
