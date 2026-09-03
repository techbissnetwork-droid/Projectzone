/* ==========================================================================
   TECHBISS — Concept C: Bold Editorial — motion system
   Snappy and tactile: buttons/cards press via CSS on hover/active, so JS
   stays focused on reveals, nav and counters. No cursor follower, no
   magnetic pull — the hard-shadow press effect IS the interaction language.
   ========================================================================== */
(function(){
  "use strict";
  var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var hasGSAP = typeof window.gsap !== 'undefined';
  var hasLenis = typeof window.Lenis !== 'undefined';

  if (hasGSAP && window.ScrollTrigger) gsap.registerPlugin(ScrollTrigger);

  var lenis = null;
  if (hasLenis && !reduced) {
    lenis = new Lenis({ duration: 0.85, smoothWheel: true, wheelMultiplier: 1 });
    document.documentElement.classList.add('has-lenis');
    function raf(time){ lenis.raf(time); requestAnimationFrame(raf); }
    requestAnimationFrame(raf);
    if (hasGSAP) lenis.on('scroll', ScrollTrigger.update);
  }

  var header = document.querySelector('[data-header]');
  function onScroll(){ if (header) header.classList.toggle('is-scrolled', window.scrollY > 16); }
  onScroll();
  window.addEventListener('scroll', onScroll, { passive: true });

  document.querySelectorAll('.has-menu').forEach(function(item){
    var trigger = item.querySelector('.nav-link');
    if (!trigger) return;
    function open(){ item.classList.add('is-open'); trigger.setAttribute('aria-expanded','true'); }
    function close(){ item.classList.remove('is-open'); trigger.setAttribute('aria-expanded','false'); }
    item.addEventListener('mouseenter', open);
    item.addEventListener('mouseleave', close);
    trigger.addEventListener('click', function(e){ e.preventDefault(); item.classList.contains('is-open') ? close() : open(); });
    trigger.addEventListener('focus', open);
  });
  document.addEventListener('keydown', function(e){
    if (e.key === 'Escape') document.querySelectorAll('.has-menu.is-open').forEach(function(i){ i.classList.remove('is-open'); });
  });

  var navToggle = document.querySelector('[data-nav-toggle]');
  var mobileNav = document.querySelector('[data-mobile-nav]');
  if (navToggle && mobileNav) {
    navToggle.addEventListener('click', function(){
      var open = mobileNav.classList.toggle('is-open');
      navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      document.body.style.overflow = open ? 'hidden' : '';
    });
    mobileNav.querySelectorAll('.mobile-sub-toggle').forEach(function(btn){
      btn.addEventListener('click', function(){
        var panel = btn.nextElementSibling;
        var open = panel.classList.toggle('is-open');
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
      });
    });
    mobileNav.querySelectorAll('a').forEach(function(a){
      a.addEventListener('click', function(){
        mobileNav.classList.remove('is-open');
        navToggle.setAttribute('aria-expanded','false');
        document.body.style.overflow = '';
      });
    });
  }

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
    }, { threshold: 0.15, rootMargin: '0px 0px -8% 0px' });
    revealEls.forEach(function(el){ io.observe(el); });
    setTimeout(function(){ revealEls.forEach(function(el){ el.classList.add('reveal-in'); }); }, 2500);
  }
  document.querySelectorAll('[data-stagger]').forEach(function(group){
    Array.prototype.forEach.call(group.children, function(child, i){
      if (child.hasAttribute('data-reveal')) child.setAttribute('data-reveal-delay', i * 70);
    });
  });

  var counters = document.querySelectorAll('[data-count]');
  if (counters.length) {
    var counterIO = new IntersectionObserver(function(entries){
      entries.forEach(function(entry){
        if (!entry.isIntersecting) return;
        var el = entry.target;
        var target = parseFloat(el.getAttribute('data-count'));
        var suffix = el.getAttribute('data-suffix') || '';
        var decimals = (el.getAttribute('data-count').split('.')[1] || '').length;
        if (reduced) { el.textContent = target.toFixed(decimals) + suffix; }
        else {
          var start = performance.now(), dur = 1000;
          function tick(now){
            var p = Math.min(1, (now - start) / dur);
            var eased = 1 - Math.pow(1 - p, 3);
            el.textContent = (target * eased).toFixed(decimals) + suffix;
            if (p < 1) requestAnimationFrame(tick);
          }
          requestAnimationFrame(tick);
        }
        counterIO.unobserve(el);
      });
    }, { threshold: 0.5 });
    counters.forEach(function(el){ counterIO.observe(el); });
  }

  document.querySelectorAll('.accordion-item').forEach(function(item){
    var trigger = item.querySelector('.accordion-trigger');
    var panel = item.querySelector('.accordion-panel');
    if (!trigger || !panel) return;
    trigger.addEventListener('click', function(){
      var isOpen = item.classList.contains('is-open');
      var group = item.closest('.accordion');
      if (group) group.querySelectorAll('.accordion-item.is-open').forEach(function(other){
        if (other !== item) { other.classList.remove('is-open'); other.querySelector('.accordion-panel').style.maxHeight = ''; }
      });
      if (isOpen) { item.classList.remove('is-open'); panel.style.maxHeight = ''; }
      else { item.classList.add('is-open'); panel.style.maxHeight = panel.scrollHeight + 'px'; }
    });
  });

  document.querySelectorAll('.filmstrip').forEach(function(strip){
    var isDown = false, startX, scrollLeft;
    strip.addEventListener('pointerdown', function(e){
      isDown = true; strip.classList.add('is-dragging'); startX = e.clientX; scrollLeft = strip.scrollLeft; strip.setPointerCapture(e.pointerId);
    });
    strip.addEventListener('pointermove', function(e){ if (isDown) strip.scrollLeft = scrollLeft - (e.clientX - startX); });
    ['pointerup','pointerleave','pointercancel'].forEach(function(ev){
      strip.addEventListener(ev, function(){ isDown = false; strip.classList.remove('is-dragging'); });
    });
    var wrap = strip.closest('.filmstrip-wrap');
    if (wrap) {
      var prev = wrap.querySelector('[data-scroll-prev]');
      var next = wrap.querySelector('[data-scroll-next]');
      var step = function(){ return (strip.querySelector('.work-card') && strip.querySelector('.work-card').offsetWidth + 22) || 320; };
      if (next) next.addEventListener('click', function(){ strip.scrollBy({ left: step(), behavior: 'smooth' }); });
      if (prev) prev.addEventListener('click', function(){ strip.scrollBy({ left: -step(), behavior: 'smooth' }); });
    }
  });

  if (hasGSAP && !reduced) {
    var heroTitle = document.querySelector('[data-hero-title]');
    if (heroTitle) gsap.fromTo(heroTitle, { y: 22, opacity: 0 }, { y: 0, opacity: 1, duration: .65, ease: 'power3.out', delay: .05 });
  }

  var path = location.pathname.split('/').pop() || 'index.html';
  document.querySelectorAll('a[href]').forEach(function(a){
    if (a.getAttribute('href') === path) a.setAttribute('aria-current','page');
  });
})();
