/* ==========================================================================
   TECHBISS — Before / After — motion system
   Standard nav/reveal/counter/filmstrip system (same engineering patterns as
   the rest of the site) plus the one bespoke piece for this concept: a
   draggable before/after comparison slider driven by pointer events and
   keyboard arrows, which sets a single --split CSS custom property that
   CSS alone turns into clip-path + handle position.
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

  /* ---------------- Scroll reveals (also activates the before/after ring) ---------------- */
  var revealEls = document.querySelectorAll('[data-reveal]');
  function activateBeforeAfter(root){
    if (root.classList && root.classList.contains('ba-after')) root.classList.add('is-active');
    if (root.querySelectorAll) root.querySelectorAll('.ba-after').forEach(function(f){ f.classList.add('is-active'); });
  }
  if (reduced || !('IntersectionObserver' in window)) {
    revealEls.forEach(function(el){ el.classList.add('reveal-in'); activateBeforeAfter(el); });
  } else {
    var io = new IntersectionObserver(function(entries){
      entries.forEach(function(entry){
        if (entry.isIntersecting) {
          var delay = entry.target.getAttribute('data-reveal-delay');
          entry.target.style.transitionDelay = delay ? (delay + 'ms') : '0ms';
          entry.target.classList.add('reveal-in');
          activateBeforeAfter(entry.target);
          io.unobserve(entry.target);
        }
      });
    }, { threshold: 0.2, rootMargin: '0px 0px -8% 0px' });
    revealEls.forEach(function(el){ io.observe(el); });
    setTimeout(function(){
      revealEls.forEach(function(el){ el.classList.add('reveal-in'); activateBeforeAfter(el); });
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

  /* ---------------- Before/After comparison slider ---------------- */
  document.querySelectorAll('[data-ba-slider]').forEach(function(slider){
    var handle = slider.querySelector('[data-ba-handle]');
    if (!handle) return;
    var min = 4, max = 96, step = 4;
    var current = 50;
    var raw = parseFloat((slider.style.getPropertyValue('--split') || '50').replace('%',''));
    if (!isNaN(raw)) current = raw;

    function apply(val){
      current = Math.max(min, Math.min(max, val));
      var rounded = Math.round(current);
      slider.style.setProperty('--split', current + '%');
      handle.setAttribute('aria-valuenow', String(rounded));
      handle.setAttribute('aria-valuetext', rounded + '% — ' + (rounded < 50 ? 'closer to before' : rounded > 50 ? 'closer to after' : 'halfway'));
    }

    function pctFromClientX(clientX){
      var rect = slider.getBoundingClientRect();
      var x = clientX - rect.left;
      return (x / rect.width) * 100;
    }

    var dragging = false;
    handle.addEventListener('pointerdown', function(e){
      dragging = true;
      slider.classList.add('is-dragging');
      try { handle.setPointerCapture(e.pointerId); } catch (err) {}
      apply(pctFromClientX(e.clientX));
      e.preventDefault();
    });
    handle.addEventListener('pointermove', function(e){
      if (!dragging) return;
      apply(pctFromClientX(e.clientX));
      e.preventDefault();
    });
    ['pointerup','pointercancel'].forEach(function(ev){
      handle.addEventListener(ev, function(){
        dragging = false;
        slider.classList.remove('is-dragging');
      });
    });

    handle.addEventListener('keydown', function(e){
      if (e.key === 'ArrowLeft' || e.key === 'Down' || e.key === 'ArrowDown') { apply(current - step); e.preventDefault(); }
      else if (e.key === 'ArrowRight' || e.key === 'Up' || e.key === 'ArrowUp') { apply(current + step); e.preventDefault(); }
      else if (e.key === 'Home') { apply(min); e.preventDefault(); }
      else if (e.key === 'End') { apply(max); e.preventDefault(); }
    });

    apply(current);
  });

  /* ---------------- Accordion (FAQ etc.) ---------------- */
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
      var stepDist = function(){ return (strip.querySelector('.work-card') && strip.querySelector('.work-card').offsetWidth + 19) || 320; };
      if (next) next.addEventListener('click', function(){ strip.scrollBy({ left: stepDist(), behavior: 'smooth' }); });
      if (prev) prev.addEventListener('click', function(){ strip.scrollBy({ left: -stepDist(), behavior: 'smooth' }); });
    }
  });

  if (hasGSAP && !reduced) {
    var heroTitle = document.querySelector('[data-hero-title]');
    if (heroTitle) gsap.fromTo(heroTitle, { y: 22, opacity: 0 }, { y: 0, opacity: 1, duration: 1, ease: 'power2.out', delay: .1 });
  }

  var path = location.pathname.split('/').pop() || 'index.html';
  document.querySelectorAll('a[href]').forEach(function(a){
    if (a.getAttribute('href') === path) a.setAttribute('aria-current','page');
  });
})();
