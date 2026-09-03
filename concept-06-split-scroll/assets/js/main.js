/* ==========================================================================
   TECHBISS — Split Scroll — motion system
   Standard nav/reveal/counter/filmstrip system shared across concepts, plus
   the split-scroll narrative: an IntersectionObserver watches a thin band
   at the vertical center of the viewport and, for each of the five right-
   column blocks it can see, keeps the one with the largest slice of that
   band as "active" — toggling matching classes on the left-column sticky
   panel, its progress dot, and the block itself. Defensive against missing
   CDN libs / reduced motion / no IntersectionObserver.
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
    }, { threshold: 0.2, rootMargin: '0px 0px -8% 0px' });
    revealEls.forEach(function(el){ io.observe(el); });
    setTimeout(function(){
      revealEls.forEach(function(el){ el.classList.add('reveal-in'); });
    }, 2500);
  }
  document.querySelectorAll('[data-stagger]').forEach(function(group){
    Array.prototype.forEach.call(group.children, function(child, i){
      if (child.hasAttribute('data-reveal')) child.setAttribute('data-reveal-delay', i * 80);
    });
  });

  /* ---------------- Animated counters (supports data-prefix + data-suffix) ---------------- */
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

  /* ---------------- Split-scroll narrative ----------------
     Left column (sticky device-frame card, panels stacked absolute/crossfade)
     stays in sync with whichever right-column block currently owns the
     center of the viewport. A block "owns" the center when it has the
     largest intersectionRatio against a thin -40%/-40% rootMargin band —
     that guarantees exactly one block (and its matching panel) is ever
     marked active, with no flicker when the band briefly spans a boundary
     between two blocks. */
  var narrative = document.querySelector('[data-scroll-narrative]');
  if (narrative) {
    var nBlocks = narrative.querySelectorAll('[data-narrative-block]');
    var nPanels = narrative.querySelectorAll('[data-narrative-panel]');
    var nDots = narrative.querySelectorAll('[data-dot]');
    var current = '1';

    function setActive(id){
      current = id;
      nPanels.forEach(function(p){ p.classList.toggle('is-active', p.getAttribute('data-narrative-panel') === id); });
      nBlocks.forEach(function(b){ b.classList.toggle('is-active', b.getAttribute('data-narrative-block') === id); });
      nDots.forEach(function(d){ d.classList.toggle('is-active', d.getAttribute('data-dot') === id); });
    }

    if (nBlocks.length && 'IntersectionObserver' in window) {
      var ratios = Object.create(null);
      var narrativeIO = new IntersectionObserver(function(entries){
        entries.forEach(function(entry){
          var id = entry.target.getAttribute('data-narrative-block');
          if (entry.isIntersecting) ratios[id] = entry.intersectionRatio;
          else delete ratios[id];
        });
        var bestId = null, bestRatio = 0;
        Object.keys(ratios).forEach(function(id){
          if (ratios[id] > bestRatio) { bestRatio = ratios[id]; bestId = id; }
        });
        if (bestId && bestId !== current) setActive(bestId);
      }, { rootMargin: '-40% 0px -40% 0px', threshold: [0, 0.1, 0.25, 0.5, 0.75, 1] });
      nBlocks.forEach(function(b){ narrativeIO.observe(b); });
      narrative.classList.add('is-ready');
    }
    setActive(current);
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
    if (heroTitle) gsap.fromTo(heroTitle, { y: 26, opacity: 0 }, { y: 0, opacity: 1, duration: 1, ease: 'power2.out', delay: .1 });
  }

  var path = location.pathname.split('/').pop() || 'index.html';
  document.querySelectorAll('a[href]').forEach(function(a){
    if (a.getAttribute('href') === path) a.setAttribute('aria-current','page');
  });
})();
