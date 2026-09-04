/* ==========================================================================
   TECHBISS Platform — shared behavior (every page loads this)
   Theme toggle (3-way, persisted, live system updates), header scroll state,
   mobile nav (with the documented stacking fix), scroll reveals, counters,
   and defensive GSAP/Lenis smooth-scroll init. Nothing here depends on a
   CDN succeeding — every enhancement is feature-detected first.
   ========================================================================== */
(function(){
  "use strict";
  var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var isCoarse = window.matchMedia && window.matchMedia('(hover: none), (pointer: coarse)').matches;
  var hasGSAP = typeof window.gsap !== 'undefined';
  var hasLenis = typeof window.Lenis !== 'undefined';

  /* ---------------- Theme system ---------------- */
  var THEME_KEY = 'techbiss-theme';
  var mql = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;

  function storedMode(){
    try { return localStorage.getItem(THEME_KEY) || 'system'; } catch (e) { return 'system'; }
  }
  function resolveTheme(mode){
    if (mode === 'dark' || mode === 'light') return mode;
    return (mql && mql.matches) ? 'dark' : 'light';
  }
  function applyTheme(mode){
    document.documentElement.setAttribute('data-theme', resolveTheme(mode));
    document.querySelectorAll('[data-theme-choice]').forEach(function(btn){
      var isActive = btn.getAttribute('data-theme-choice') === mode;
      btn.setAttribute('aria-pressed', isActive ? 'true' : 'false');
    });
  }
  function setMode(mode){
    try { localStorage.setItem(THEME_KEY, mode); } catch (e) {}
    applyTheme(mode);
  }
  applyTheme(storedMode());

  document.querySelectorAll('[data-theme-choice]').forEach(function(btn){
    btn.addEventListener('click', function(){ setMode(btn.getAttribute('data-theme-choice')); });
  });
  if (mql) {
    var onSystemChange = function(){ if (storedMode() === 'system') applyTheme('system'); };
    if (mql.addEventListener) mql.addEventListener('change', onSystemChange);
    else if (mql.addListener) mql.addListener(onSystemChange);
  }
  /* Gate the background/color transition until after first paint, so
     navigating between pages (which already sets the right theme via the
     blocking head script) never shows a cross-fade flash. */
  requestAnimationFrame(function(){
    requestAnimationFrame(function(){ document.body.classList.add('theme-anim-ready'); });
  });

  /* ---------------- Smooth scroll (Lenis) — defensive, reduced-motion aware ---------------- */
  if (hasGSAP && window.ScrollTrigger) gsap.registerPlugin(ScrollTrigger);
  var lenis = null;
  if (hasLenis && !reduced) {
    lenis = new Lenis({ duration: 1.0, smoothWheel: true, wheelMultiplier: 1 });
    document.documentElement.classList.add('has-lenis');
    function raf(time){ lenis.raf(time); requestAnimationFrame(raf); }
    requestAnimationFrame(raf);
    if (hasGSAP) lenis.on('scroll', ScrollTrigger.update);
  }

  /* ---------------- Header scroll state ---------------- */
  var header = document.querySelector('[data-header]');
  function onScroll(){ if (header) header.classList.toggle('is-scrolled', window.scrollY > 16); }
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
      if (e.key === 'Escape' && mobileNav.classList.contains('is-open')) setOpen(false);
    });
  }

  /* ---------------- Active nav link ---------------- */
  var path = location.pathname.split('/').pop() || 'index.html';
  document.querySelectorAll('a[href]').forEach(function(a){
    var href = a.getAttribute('href').split('?')[0].split('#')[0];
    if (href === path) a.setAttribute('aria-current', 'page');
  });

  /* ---------------- Scroll reveals ---------------- */
  var revealEls = document.querySelectorAll('[data-reveal]');
  if (revealEls.length) {
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
      }, { threshold: 0.15, rootMargin: '0px 0px -8% 0px' });
      revealEls.forEach(function(el){ io.observe(el); });
      setTimeout(function(){
        revealEls.forEach(function(el){ el.classList.add('reveal-in'); });
      }, 2500);
    }
  }
  document.querySelectorAll('[data-stagger]').forEach(function(group){
    Array.prototype.forEach.call(group.children, function(child, i){
      if (child.hasAttribute('data-reveal')) child.setAttribute('data-reveal-delay', i * 70);
    });
  });

  /* ---------------- Animated counters ---------------- */
  var counters = document.querySelectorAll('[data-count]');
  if (counters.length && 'IntersectionObserver' in window) {
    var counterIO = new IntersectionObserver(function(entries){
      entries.forEach(function(entry){
        if (!entry.isIntersecting) return;
        var el = entry.target;
        var raw = el.getAttribute('data-count');
        var target = parseFloat(raw);
        var suffix = el.getAttribute('data-suffix') || '';
        var prefix = el.getAttribute('data-prefix') || '';
        var decimals = (raw.split('.')[1] || '').length;
        if (reduced) { el.textContent = prefix + target.toFixed(decimals) + suffix; }
        else {
          var start = performance.now(), dur = 1200;
          function tick(now){
            var p = Math.min(1, (now - start) / dur);
            var eased = 1 - Math.pow(1 - p, 3);
            el.textContent = prefix + (target * eased).toFixed(decimals) + suffix;
            if (p < 1) requestAnimationFrame(tick);
          }
          requestAnimationFrame(tick);
        }
        counterIO.unobserve(el);
      });
    }, { threshold: 0.5 });
    counters.forEach(function(el){ counterIO.observe(el); });
  }

  /* ---------------- Accordion (feature groups, FAQ-style panels) ---------------- */
  document.querySelectorAll('.accordion-item').forEach(function(item){
    var trigger = item.querySelector('.accordion-trigger');
    var panel = item.querySelector('.accordion-panel');
    if (!trigger || !panel) return;
    trigger.addEventListener('click', function(){
      var isOpen = item.classList.contains('is-open');
      if (isOpen) { item.classList.remove('is-open'); panel.style.maxHeight = ''; trigger.setAttribute('aria-expanded','false'); }
      else { item.classList.add('is-open'); panel.style.maxHeight = panel.scrollHeight + 'px'; trigger.setAttribute('aria-expanded','true'); }
    });
  });

  /* ---------------- Magnetic hover (fine pointers only) ---------------- */
  if (!isCoarse && !reduced) {
    document.querySelectorAll('.magnetic').forEach(function(el){
      el.addEventListener('mousemove', function(e){
        var r = el.getBoundingClientRect();
        var x = (e.clientX - r.left - r.width/2) * 0.2;
        var y = (e.clientY - r.top - r.height/2) * 0.2;
        el.style.transform = 'translate('+x+'px,'+y+'px)';
      });
      el.addEventListener('mouseleave', function(){ el.style.transform = ''; });
    });
  }

  window.TECHBISS = window.TECHBISS || {};
  window.TECHBISS.reduced = reduced;
  window.TECHBISS.hasGSAP = hasGSAP;
})();
