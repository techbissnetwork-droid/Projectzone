/* ==========================================================================
   TECHBISS — Concept 03: Immersive Digital — motion system
   The most expressive of the three: pinned storytelling, layered parallax,
   tilting cards, blend-mode cursor. Everything is gated behind
   prefers-reduced-motion and defensive checks for the CDN libraries.
   ========================================================================== */
(function(){
  "use strict";
  var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var isCoarse = window.matchMedia && window.matchMedia('(hover: none), (pointer: coarse)').matches;
  var hasGSAP = typeof window.gsap !== 'undefined';
  var hasLenis = typeof window.Lenis !== 'undefined';

  if (hasGSAP && window.ScrollTrigger) gsap.registerPlugin(ScrollTrigger);

  /* ---------------- Smooth scroll (Lenis) ---------------- */
  var lenis = null;
  if (hasLenis && !reduced) {
    lenis = new Lenis({ duration: 1.1, smoothWheel: true, wheelMultiplier: 1 });
    document.documentElement.classList.add('has-lenis');
    function raf(time){ lenis.raf(time); requestAnimationFrame(raf); }
    requestAnimationFrame(raf);
    if (hasGSAP) lenis.on('scroll', ScrollTrigger.update);
  }

  /* ---------------- Header scroll state ---------------- */
  var header = document.querySelector('[data-header]');
  function onScroll(){ if (header) header.classList.toggle('is-scrolled', window.scrollY > 24); }
  onScroll();
  window.addEventListener('scroll', onScroll, { passive: true });

  /* ---------------- Desktop mega menu ---------------- */
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

  /* ---------------- Mobile nav ---------------- */
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

  /* ---------------- Cursor (blend-mode) ---------------- */
  if (!isCoarse && !reduced) {
    var dot = document.querySelector('.cursor-dot');
    var ring = document.querySelector('.cursor-ring');
    if (dot && ring) {
      var mx = -100, my = -100, rx = -100, ry = -100;
      window.addEventListener('mousemove', function(e){ mx = e.clientX; my = e.clientY; dot.style.transform = 'translate('+mx+'px,'+my+'px) translate(-50%,-50%)'; });
      (function loop(){ rx += (mx-rx)*0.18; ry += (my-ry)*0.18; ring.style.transform = 'translate('+rx+'px,'+ry+'px) translate(-50%,-50%)'; requestAnimationFrame(loop); })();
      document.querySelectorAll('a, button, .magnetic, input, textarea, [data-tilt], [data-cursor-hover]').forEach(function(el){
        el.addEventListener('mouseenter', function(){ ring.classList.add('is-hover'); });
        el.addEventListener('mouseleave', function(){ ring.classList.remove('is-hover'); });
      });
    }
  }

  /* ---------------- Magnetic buttons ---------------- */
  if (!isCoarse && !reduced) {
    document.querySelectorAll('.magnetic').forEach(function(el){
      el.addEventListener('mousemove', function(e){
        var r = el.getBoundingClientRect();
        var x = (e.clientX - r.left - r.width/2) * 0.3;
        var y = (e.clientY - r.top - r.height/2) * 0.3;
        el.style.transform = 'translate('+x+'px,'+y+'px)';
      });
      el.addEventListener('mouseleave', function(){ el.style.transform = ''; });
    });
  }

  /* ---------------- Card tilt ---------------- */
  if (!isCoarse && !reduced) {
    document.querySelectorAll('[data-tilt]').forEach(function(el){
      el.addEventListener('mousemove', function(e){
        var r = el.getBoundingClientRect();
        var px = (e.clientX - r.left) / r.width - 0.5;
        var py = (e.clientY - r.top) / r.height - 0.5;
        el.style.transform = 'perspective(900px) rotateX(' + (py * -8) + 'deg) rotateY(' + (px * 10) + 'deg) translateZ(4px)';
      });
      el.addEventListener('mouseleave', function(){ el.style.transform = ''; });
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
    }, { threshold: 0.15, rootMargin: '0px 0px -8% 0px' });
    revealEls.forEach(function(el){ io.observe(el); });
    setTimeout(function(){ revealEls.forEach(function(el){ el.classList.add('reveal-in'); }); }, 2500);
  }
  document.querySelectorAll('[data-stagger]').forEach(function(group){
    Array.prototype.forEach.call(group.children, function(child, i){
      if (child.hasAttribute('data-reveal')) child.setAttribute('data-reveal-delay', i * 90);
    });
  });

  /* ---------------- Animated stat counters ---------------- */
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
          var start = performance.now(), dur = 1400;
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

  /* ---------------- Accordion ---------------- */
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

  /* ---------------- Filmstrip drag-to-scroll + progress ---------------- */
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
    var fill = wrap ? wrap.querySelector('.progress-fill') : null;
    function updateProgress(){
      if (!fill) return;
      var max = strip.scrollWidth - strip.clientWidth;
      var ratio = max > 0 ? strip.scrollLeft / max : 0;
      var visible = Math.min(1, strip.clientWidth / strip.scrollWidth);
      fill.style.width = Math.max(visible, 0.12) * 100 + '%';
      fill.style.left = ratio * (1 - Math.max(visible, 0.12)) * 100 + '%';
    }
    strip.addEventListener('scroll', updateProgress, { passive: true });
    updateProgress();
    if (wrap) {
      var prev = wrap.querySelector('[data-scroll-prev]');
      var next = wrap.querySelector('[data-scroll-next]');
      var step = function(){ return (strip.querySelector('.work-card') && strip.querySelector('.work-card').offsetWidth + 24) || 320; };
      if (next) next.addEventListener('click', function(){ strip.scrollBy({ left: step(), behavior: 'smooth' }); });
      if (prev) prev.addEventListener('click', function(){ strip.scrollBy({ left: -step(), behavior: 'smooth' }); });
    }
  });

  /* ---------------- Pinned storytelling ---------------- */
  document.querySelectorAll('.pin-section').forEach(function(section){
    var panels = section.querySelectorAll('.pin-panel');
    var glow = section.querySelector('.pin-visual-inner .glow');
    if (!panels.length) return;
    var gradients = { violet: 'radial-gradient(circle,#7c5cff,transparent 65%)', cyan: 'radial-gradient(circle,#34e5ff,transparent 65%)', magenta: 'radial-gradient(circle,#ff4fd8,transparent 65%)' };
    var panelIO = new IntersectionObserver(function(entries){
      entries.forEach(function(entry){
        if (entry.isIntersecting) {
          panels.forEach(function(p){ p.classList.remove('is-active'); });
          entry.target.classList.add('is-active');
          if (glow) { var key = entry.target.getAttribute('data-glow') || 'violet'; glow.style.background = gradients[key] || gradients.violet; }
        }
      });
    }, { threshold: 0.5, rootMargin: '-20% 0px -20% 0px' });
    panels.forEach(function(p){ panelIO.observe(p); });
  });

  /* ---------------- Parallax layers ---------------- */
  if (hasGSAP && !reduced) {
    document.querySelectorAll('[data-parallax]').forEach(function(el){
      gsap.to(el, {
        yPercent: parseFloat(el.getAttribute('data-parallax')) || 15,
        ease: 'none',
        scrollTrigger: { trigger: el.closest('section') || el, start: 'top bottom', end: 'bottom top', scrub: true }
      });
    });
    var heroTitle = document.querySelector('[data-hero-title]');
    if (heroTitle) gsap.fromTo(heroTitle, { y: 34, opacity: 0 }, { y: 0, opacity: 1, duration: 1.1, ease: 'power3.out', delay: .15 });
  }

  /* ---------------- Current nav link highlight ---------------- */
  var path = location.pathname.split('/').pop() || 'index.html';
  document.querySelectorAll('a[href]').forEach(function(a){
    if (a.getAttribute('href') === path) a.setAttribute('aria-current','page');
  });
})();
