/* ==========================================================================
   TECHBISS PLATFORM — shared motion + chrome system
   Loaded on every page, after gsap / ScrollTrigger / lenis (each optional and
   defensively checked). Owns: theme toggle, header scroll state, mobile nav,
   scroll reveals, animated counters, active-nav highlighting, Lenis/GSAP init.
   ========================================================================== */
(function(){
  "use strict";
  var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var hasGSAP = typeof window.gsap !== 'undefined';
  var hasLenis = typeof window.Lenis !== 'undefined';

  if (hasGSAP && window.ScrollTrigger) gsap.registerPlugin(ScrollTrigger);

  var lenis = null;
  if (hasLenis && !reduced) {
    lenis = new Lenis({ duration: 1.0, smoothWheel: true, wheelMultiplier: 1 });
    document.documentElement.classList.add('has-lenis');
    (function raf(time){ lenis.raf(time); requestAnimationFrame(raf); })();
    if (hasGSAP && window.ScrollTrigger) lenis.on('scroll', ScrollTrigger.update);
  }

  /* ---------------- Theme system ---------------- */
  var THEME_KEY = 'techbiss-theme';
  var root = document.documentElement;

  function systemPrefersDark(){
    return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
  }
  function storedChoice(){
    try { return localStorage.getItem(THEME_KEY) || 'system'; } catch (e) { return 'system'; }
  }
  function applyTheme(choice){
    var resolved = choice === 'system' ? (systemPrefersDark() ? 'dark' : 'light') : choice;
    root.setAttribute('data-theme', resolved);
    document.querySelectorAll('[data-theme-choice]').forEach(function(btn){
      btn.setAttribute('aria-pressed', btn.getAttribute('data-theme-choice') === choice ? 'true' : 'false');
    });
  }
  function setChoice(choice){
    try { localStorage.setItem(THEME_KEY, choice); } catch (e) {}
    applyTheme(choice);
  }
  applyTheme(storedChoice());
  document.querySelectorAll('[data-theme-choice]').forEach(function(btn){
    btn.addEventListener('click', function(){ setChoice(btn.getAttribute('data-theme-choice')); });
  });
  if (window.matchMedia) {
    var mq = window.matchMedia('(prefers-color-scheme: dark)');
    var onSystemChange = function(){ if (storedChoice() === 'system') applyTheme('system'); };
    if (mq.addEventListener) mq.addEventListener('change', onSystemChange);
    else if (mq.addListener) mq.addListener(onSystemChange);
  }
  /* Gate the theme-change CSS transition off until after first paint (double rAF), so
     the correct theme applied pre-paint by the head-blocking script never animates in,
     and navigating between pages never shows a transition-flash. */
  requestAnimationFrame(function(){
    requestAnimationFrame(function(){ root.classList.add('theme-ready'); });
  });

  /* ---------------- Header scroll state ---------------- */
  var header = document.querySelector('[data-header]');
  function onScroll(){ if (header) header.classList.toggle('is-scrolled', window.scrollY > 18); }
  onScroll();
  window.addEventListener('scroll', onScroll, { passive: true });

  /* ---------------- Mobile nav ---------------- */
  var navToggle = document.querySelector('[data-nav-toggle]');
  var mobileNav = document.querySelector('[data-mobile-nav]');
  if (navToggle && mobileNav) {
    var setOpen = function(open){
      mobileNav.classList.toggle('is-open', open);
      navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      document.body.style.overflow = open ? 'hidden' : '';
    };
    navToggle.addEventListener('click', function(){
      setOpen(!mobileNav.classList.contains('is-open'));
    });
    mobileNav.querySelectorAll('a').forEach(function(a){
      a.addEventListener('click', function(){ setOpen(false); });
    });
    document.addEventListener('keydown', function(e){
      if (e.key === 'Escape' && mobileNav.classList.contains('is-open')) { setOpen(false); navToggle.focus(); }
    });
  }

  /* ---------------- Scroll reveals ---------------- */
  var revealEls = document.querySelectorAll('[data-reveal]');
  if (reduced || !('IntersectionObserver' in window)) {
    revealEls.forEach(function(el){ el.classList.add('reveal-in'); });
  } else {
    var io = new IntersectionObserver(function(entries){
      entries.forEach(function(entry){
        if (entry.isIntersecting) {
          var delay = entry.target.getAttribute('data-reveal-delay');
          entry.target.style.transitionDelay = delay ? (delay + 'ms') : '0ms';
          entry.target.classList.add('reveal-in');
          io.unobserve(entry.target);
        }
      });
    }, { threshold: 0.16, rootMargin: '0px 0px -8% 0px' });
    revealEls.forEach(function(el){ io.observe(el); });
    setTimeout(function(){
      revealEls.forEach(function(el){ el.classList.add('reveal-in'); });
    }, 2500);
  }
  document.querySelectorAll('[data-stagger]').forEach(function(group){
    Array.prototype.forEach.call(group.children, function(child, i){
      if (child.hasAttribute('data-reveal') && !child.hasAttribute('data-reveal-delay')) {
        child.setAttribute('data-reveal-delay', i * 70);
      }
    });
  });

  /* Global safety net: force-reveal everything if a CDN script errors after this file
     has already run its own reveal pass (belt-and-suspenders alongside the inline
     first-paint script's own error/timeout handlers). */
  window.addEventListener('error', function(){
    document.querySelectorAll('[data-reveal]:not(.reveal-in)').forEach(function(el){ el.classList.add('reveal-in'); });
  });

  /* ---------------- Animated counters (data-count, optional data-prefix/suffix) ---------------- */
  var counters = document.querySelectorAll('[data-count]');
  if (counters.length) {
    var renderCount = function(el, value){
      var suffix = el.getAttribute('data-suffix') || '';
      var prefix = el.getAttribute('data-prefix') || '';
      var raw = el.getAttribute('data-count');
      var decimals = (raw.split('.')[1] || '').length;
      el.textContent = prefix + value.toFixed(decimals) + suffix;
    };
    var counterIO = new IntersectionObserver(function(entries){
      entries.forEach(function(entry){
        if (!entry.isIntersecting) return;
        var el = entry.target;
        var target = parseFloat(el.getAttribute('data-count'));
        if (reduced) { renderCount(el, target); }
        else {
          var start = performance.now(), dur = 1200;
          (function tick(now){
            var p = Math.min(1, (now - start) / dur);
            var eased = 1 - Math.pow(1 - p, 3);
            renderCount(el, target * eased);
            if (p < 1) requestAnimationFrame(tick);
          })(start);
        }
        counterIO.unobserve(el);
      });
    }, { threshold: 0.4 });
    counters.forEach(function(el){ counterIO.observe(el); });
  }

  /* ---------------- Accordion (generic, used for expandable cards) ---------------- */
  document.querySelectorAll('[data-accordion-trigger]').forEach(function(trigger){
    trigger.addEventListener('click', function(){
      var item = trigger.closest('[data-accordion-item]');
      if (!item) return;
      var open = item.classList.toggle('is-expanded');
      trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  });

  /* ---------------- Active nav link ---------------- */
  var path = location.pathname.split('/').pop() || 'index.html';
  document.querySelectorAll('a[href]').forEach(function(a){
    var href = a.getAttribute('href').split('?')[0].split('#')[0];
    if (href === path) a.setAttribute('aria-current', 'page');
  });

  /* expose small helpers other page scripts reuse */
  window.TECHBISS = window.TECHBISS || {};
  window.TECHBISS.reduced = reduced;
  window.TECHBISS.hasGSAP = hasGSAP;
})();
