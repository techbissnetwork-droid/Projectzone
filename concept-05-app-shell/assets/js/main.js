/* ==========================================================================
   TECHBISS — App Shell — motion system
   Adds the interactive hero tab-switcher on top of the standard nav/
   reveal/counter/accordion/filmstrip system shared across concepts.
   Defensive against missing CDN libs / reduced motion.
   ========================================================================== */
(function(){
  "use strict";
  var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var isCoarse = window.matchMedia && window.matchMedia('(hover: none), (pointer: coarse)').matches;
  var hasGSAP = typeof window.gsap !== 'undefined';
  var hasLenis = typeof window.Lenis !== 'undefined';

  if (hasGSAP && window.ScrollTrigger) gsap.registerPlugin(ScrollTrigger);

  var lenis = null;
  if (hasLenis && !reduced) {
    lenis = new Lenis({ duration: 1.0, smoothWheel: true, wheelMultiplier: 1 });
    document.documentElement.classList.add('has-lenis');
    function raf(time){ lenis.raf(time); requestAnimationFrame(raf); }
    requestAnimationFrame(raf);
    if (hasGSAP) lenis.on('scroll', ScrollTrigger.update);
  }

  var header = document.querySelector('[data-header]');
  function onScroll(){ if (header) header.classList.toggle('is-scrolled', window.scrollY > 24); }
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

  /* ---------------- Hero tab switcher ---------------- */
  var switcher = document.querySelector('[data-switcher]');
  if (switcher) {
    var tabs = Array.prototype.slice.call(switcher.querySelectorAll('button'));
    var thumb = switcher.querySelector('.switcher-thumb');
    var panels = document.querySelectorAll('.tab-panel');
    function moveThumb(btn){
      if (!thumb) return;
      thumb.style.width = btn.offsetWidth + 'px';
      thumb.style.transform = 'translateX(' + btn.offsetLeft + 'px)';
    }
    function activate(index){
      tabs.forEach(function(t, i){ t.setAttribute('aria-selected', i === index ? 'true' : 'false'); });
      panels.forEach(function(p){ p.classList.toggle('is-active', p.getAttribute('data-panel') === String(index + 1)); });
      moveThumb(tabs[index]);
    }
    tabs.forEach(function(btn, i){
      btn.addEventListener('click', function(){ activate(i); });
    });
    var initialIndex = tabs.findIndex(function(t){ return t.getAttribute('aria-selected') === 'true'; });
    activate(initialIndex >= 0 ? initialIndex : 0);
    window.addEventListener('resize', function(){
      var current = tabs.findIndex(function(t){ return t.getAttribute('aria-selected') === 'true'; });
      moveThumb(tabs[current >= 0 ? current : 0]);
    });
  }

  if (!isCoarse && !reduced) {
    document.querySelectorAll('.magnetic').forEach(function(el){
      el.addEventListener('mousemove', function(e){
        var r = el.getBoundingClientRect();
        var x = (e.clientX - r.left - r.width/2) * 0.25;
        var y = (e.clientY - r.top - r.height/2) * 0.25;
        el.style.transform = 'translate('+x+'px,'+y+'px)';
      });
      el.addEventListener('mouseleave', function(){ el.style.transform = ''; });
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
    }, { threshold: 0.2, rootMargin: '0px 0px -8% 0px' });
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
        var prefix = el.getAttribute('data-prefix') || '';
        var decimals = (el.getAttribute('data-count').split('.')[1] || '').length;
        if (reduced) { el.textContent = prefix + target.toFixed(decimals) + suffix; }
        else {
          var start = performance.now(), dur = 1300;
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
      var step = function(){ return (strip.querySelector('.work-card') && strip.querySelector('.work-card').offsetWidth + 21) || 320; };
      if (next) next.addEventListener('click', function(){ strip.scrollBy({ left: step(), behavior: 'smooth' }); });
      if (prev) prev.addEventListener('click', function(){ strip.scrollBy({ left: -step(), behavior: 'smooth' }); });
    }
  });

  if (hasGSAP && !reduced) {
    var heroTitle = document.querySelector('[data-hero-title]');
    if (heroTitle) gsap.fromTo(heroTitle, { y: 22, opacity: 0 }, { y: 0, opacity: 1, duration: .9, ease: 'power2.out', delay: .1 });
  }

  var path = location.pathname.split('/').pop() || 'index.html';
  document.querySelectorAll('a[href]').forEach(function(a){
    if (a.getAttribute('href') === path) a.setAttribute('aria-current','page');
  });
})();
