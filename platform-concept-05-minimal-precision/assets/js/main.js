/* ==========================================================================
   TECHBISS — Minimal Precision — shared runtime
   Theme system, header/mobile-nav, scroll-reveal, legal modal, and small
   reusable component helpers (accordion, tabs) used across every page.
   Defensive against missing CDN libs and prefers-reduced-motion.
   ========================================================================== */
(function () {
  "use strict";

  var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var root = document.documentElement;

  /* ---------------- Theme ---------------- */
  var THEME_KEY = 'techbiss-theme';
  var mql = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;

  function getStoredPref() {
    try { return localStorage.getItem(THEME_KEY) || 'system'; } catch (e) { return 'system'; }
  }
  function resolveTheme(pref) {
    if (pref === 'dark' || pref === 'light') return pref;
    return (mql && mql.matches) ? 'dark' : 'light';
  }
  function applyTheme(pref) {
    var resolved = resolveTheme(pref);
    root.setAttribute('data-theme', resolved);
    var btns = document.querySelectorAll('[data-theme-btn]');
    for (var i = 0; i < btns.length; i++) {
      var active = btns[i].getAttribute('data-theme-btn') === pref;
      btns[i].setAttribute('aria-pressed', active ? 'true' : 'false');
      btns[i].classList.toggle('is-active', active);
    }
  }
  function setThemePref(pref) {
    try { localStorage.setItem(THEME_KEY, pref); } catch (e) {}
    applyTheme(pref);
  }
  document.querySelectorAll('[data-theme-btn]').forEach(function (btn) {
    btn.addEventListener('click', function () { setThemePref(btn.getAttribute('data-theme-btn')); });
  });
  applyTheme(getStoredPref());
  if (mql) {
    var mqlHandler = function () { if (getStoredPref() === 'system') applyTheme('system'); };
    if (mql.addEventListener) mql.addEventListener('change', mqlHandler);
    else if (mql.addListener) mql.addListener(mqlHandler);
  }
  // Enable theme-change transitions only after first paint, so navigating
  // between pages (already on the right theme via the blocking head script)
  // never animates from a "wrong" state.
  requestAnimationFrame(function () {
    requestAnimationFrame(function () { root.classList.add('tb-transitions'); });
  });

  /* ---------------- Header scroll shade + mobile nav ---------------- */
  var header = document.querySelector('[data-header]');
  function onScroll() { if (header) header.classList.toggle('is-scrolled', window.scrollY > 12); }
  onScroll();
  window.addEventListener('scroll', onScroll, { passive: true });

  var navToggle = document.querySelector('[data-nav-toggle]');
  var mobileNav = document.querySelector('[data-mobile-nav]');
  if (navToggle && mobileNav) {
    navToggle.addEventListener('click', function () {
      var open = !mobileNav.classList.contains('is-open');
      mobileNav.classList.toggle('is-open', open);
      navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      document.body.style.overflow = open ? 'hidden' : '';
    });
    mobileNav.querySelectorAll('a').forEach(function (a) {
      a.addEventListener('click', function () {
        mobileNav.classList.remove('is-open');
        navToggle.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';
      });
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && mobileNav.classList.contains('is-open')) {
        mobileNav.classList.remove('is-open');
        navToggle.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';
      }
    });
  }

  /* ---------------- Legal modal (Privacy / Terms) ---------------- */
  var legalModal = document.querySelector('[data-legal-modal]');
  if (legalModal) {
    var legalPanels = legalModal.querySelectorAll('[data-legal-panel]');
    var lastFocused = null;
    function openLegal(which) {
      legalPanels.forEach(function (p) { p.hidden = p.getAttribute('data-legal-panel') !== which; });
      lastFocused = document.activeElement;
      legalModal.classList.add('is-open');
      legalModal.setAttribute('aria-hidden', 'false');
      var closeBtn = legalModal.querySelector('.legal-modal-close');
      if (closeBtn) closeBtn.focus();
    }
    function closeLegal() {
      legalModal.classList.remove('is-open');
      legalModal.setAttribute('aria-hidden', 'true');
      if (lastFocused && lastFocused.focus) lastFocused.focus();
    }
    document.querySelectorAll('[data-legal-trigger]').forEach(function (btn) {
      btn.addEventListener('click', function () { openLegal(btn.getAttribute('data-legal-trigger')); });
    });
    legalModal.querySelectorAll('[data-legal-close]').forEach(function (el) {
      el.addEventListener('click', closeLegal);
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && legalModal.classList.contains('is-open')) closeLegal();
    });
  }

  /* ---------------- Scroll reveal ---------------- */
  document.querySelectorAll('[data-reveal-stagger]').forEach(function (group) {
    var children = group.querySelectorAll('[data-reveal]');
    children.forEach(function (child, i) {
      if (!child.hasAttribute('data-reveal-delay')) child.setAttribute('data-reveal-delay', String(i * 70));
    });
  });
  var revealEls = document.querySelectorAll('[data-reveal]');
  if (reduced || !('IntersectionObserver' in window)) {
    revealEls.forEach(function (el) { el.classList.add('reveal-in'); });
  } else {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          var delay = entry.target.getAttribute('data-reveal-delay');
          if (delay) {
            setTimeout(function () { entry.target.classList.add('reveal-in'); }, parseInt(delay, 10));
          } else {
            entry.target.classList.add('reveal-in');
          }
          io.unobserve(entry.target);
        }
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -8% 0px' });
    revealEls.forEach(function (el) { io.observe(el); });
  }

  /* ---------------- Generic accordion ---------------- */
  document.querySelectorAll('[data-accordion]').forEach(function (acc) {
    acc.querySelectorAll('.accordion-item').forEach(function (item) {
      var trigger = item.querySelector('.accordion-trigger');
      var panel = item.querySelector('.accordion-panel');
      if (!trigger || !panel) return;
      trigger.setAttribute('aria-expanded', 'false');
      trigger.addEventListener('click', function () {
        var isOpen = item.classList.contains('is-open');
        if (isOpen) {
          item.classList.remove('is-open');
          trigger.setAttribute('aria-expanded', 'false');
          panel.style.maxHeight = '';
        } else {
          item.classList.add('is-open');
          trigger.setAttribute('aria-expanded', 'true');
          panel.style.maxHeight = panel.scrollHeight + 'px';
        }
      });
    });
  });

  /* ---------------- Generic tabs (role="tablist" via data-tabs) ---------------- */
  window.TB = window.TB || {};
  window.TB.initTabs = function (root, onChange) {
    if (!root) return;
    var tabs = Array.prototype.slice.call(root.querySelectorAll('[role="tab"]'));
    function select(tab, focus) {
      tabs.forEach(function (t) {
        var isSel = t === tab;
        t.setAttribute('aria-selected', isSel ? 'true' : 'false');
        t.tabIndex = isSel ? 0 : -1;
        var panel = document.getElementById(t.getAttribute('aria-controls'));
        if (panel) panel.hidden = !isSel;
      });
      if (focus) tab.focus();
      if (onChange) onChange(tab);
    }
    tabs.forEach(function (tab, i) {
      tab.addEventListener('click', function () { select(tab, false); });
      tab.addEventListener('keydown', function (e) {
        var idx = tabs.indexOf(tab);
        if (e.key === 'ArrowRight' || e.key === 'ArrowDown') { e.preventDefault(); select(tabs[(idx + 1) % tabs.length], true); }
        if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') { e.preventDefault(); select(tabs[(idx - 1 + tabs.length) % tabs.length], true); }
      });
    });
    return { select: select, tabs: tabs };
  };

  /* ---------------- Process sequence: scroll-reactive active row ---------------- */
  var processList = document.querySelector('[data-process]');
  if (processList) {
    var processRows = processList.querySelectorAll('.process-row');
    if (processRows.length && 'IntersectionObserver' in window) {
      var setActive = function (row) {
        processRows.forEach(function (r) { r.classList.toggle('is-active', r === row); });
      };
      setActive(processRows[0]);
      var procIO = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) setActive(entry.target);
        });
      }, { threshold: 0.5, rootMargin: '-35% 0px -35% 0px' });
      processRows.forEach(function (r) { procIO.observe(r); });
    } else {
      processRows.forEach(function (r) { r.classList.add('is-active'); });
    }
  }

  /* ---------------- Footer year-agnostic copyright already static in markup ---------------- */

  /* ---------------- Optional motion libs (progressive enhancement only) ---------------- */
  var hasGSAP = typeof window.gsap !== 'undefined';
  var hasLenis = typeof window.Lenis !== 'undefined';
  if (hasGSAP && window.ScrollTrigger) { try { gsap.registerPlugin(ScrollTrigger); } catch (e) {} }
  if (hasLenis && !reduced) {
    try {
      var lenis = new Lenis({ duration: 1.0, smoothWheel: true, wheelMultiplier: 1 });
      document.documentElement.classList.add('has-lenis');
      var raf = function (time) { lenis.raf(time); requestAnimationFrame(raf); };
      requestAnimationFrame(raf);
      if (hasGSAP && window.ScrollTrigger) lenis.on('scroll', ScrollTrigger.update);
    } catch (e) {}
  }
})();
