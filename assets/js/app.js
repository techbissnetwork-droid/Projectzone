/* =====================================================================
   TECHBISS — public site behaviour
   Vanilla ES2020. No dependencies. Everything degrades gracefully when
   JavaScript is unavailable, and every motion effect respects
   prefers-reduced-motion.
   ===================================================================== */
(function () {
  'use strict';

  var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
  var canHover     = window.matchMedia('(hover: hover) and (pointer: fine)');
  var cfg          = window.TECHBISS || {};

  var $  = function (sel, ctx) { return (ctx || document).querySelector(sel); };
  var $$ = function (sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); };

  function on(el, evt, fn, opts) { if (el) el.addEventListener(evt, fn, opts); }

  // Anything focusable that a real Tab press would land on. Kept in one place
  // because both modal surfaces below have to walk the same list.
  var FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), ' +
                  'textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

  // A dialog with aria-modal="true" still lets Tab walk into the page behind
  // it — the attribute is a promise to assistive tech, not a browser feature —
  // so the wrap has to be done by hand.
  function trapTab(container, e) {
    if (e.key !== 'Tab') return;
    var nodes = $$(FOCUSABLE, container).filter(function (el) {
      return el.offsetWidth || el.offsetHeight || el.getClientRects().length;
    });
    if (!nodes.length) return;
    var first = nodes[0];
    var last  = nodes[nodes.length - 1];
    var active = document.activeElement;
    var outside = !container.contains(active);
    if (e.shiftKey && (active === first || outside)) {
      e.preventDefault();
      last.focus();
    } else if (!e.shiftKey && (active === last || outside)) {
      e.preventDefault();
      first.focus();
    }
  }

  function throttleFrame(fn) {
    var queued = false;
    return function () {
      var args = arguments, self = this;
      if (queued) return;
      queued = true;
      requestAnimationFrame(function () { queued = false; fn.apply(self, args); });
    };
  }

  /* -------------------------------------------------------------------
     Loading screen
     Removed as soon as the document is interactive, with a hard ceiling
     so a slow asset can never leave a visitor staring at it.
     ------------------------------------------------------------------- */
  function initLoader() {
    var loader = $('.loader');
    if (!loader) return;
    var pctEl  = $('.loader__pct', loader);
    var barEl  = $('.loader__line span', loader);
    var done = false;
    var finish = function () {
      if (done) return;
      done = true;
      if (pctEl) pctEl.textContent = '100%';
      if (barEl) barEl.style.width = '100%';
      loader.classList.add('is-done');
      document.body.classList.remove('is-loading');
      window.setTimeout(function () { if (loader.parentNode) loader.parentNode.removeChild(loader); }, 750);
    };
    // A steadily-climbing percentage, not a real progress measurement — the
    // page has no long asset fetch to report on, so this exists purely to
    // make the brief wait read as "counting up" rather than "stuck".
    if (pctEl && !reduceMotion.matches) {
      var p = 0;
      var tick = window.setInterval(function () {
        p = Math.min(100, p + Math.random() * 18 + 6);
        pctEl.textContent = Math.floor(p) + '%';
        if (barEl) barEl.style.width = p + '%';
        if (p >= 100) window.clearInterval(tick);
      }, 110);
    }
    if (document.readyState === 'complete') {
      window.setTimeout(finish, 120);
    } else {
      on(window, 'load', function () { window.setTimeout(finish, 90); });
    }
    // Never block for longer than this, whatever else is happening.
    window.setTimeout(finish, 2200);
  }

  /* -------------------------------------------------------------------
     Scroll progress — a thin fixed bar tracking how far down the page the
     visitor has read. Pure read of scroll position, no state of its own.
     ------------------------------------------------------------------- */
  function initScrollProgress() {
    var bar = $('.scroll-progress > span');
    if (!bar) return;
    var update = throttleFrame(function () {
      var doc = document.documentElement;
      var max = doc.scrollHeight - doc.clientHeight;
      bar.style.width = (max > 0 ? (window.scrollY / max) * 100 : 0) + '%';
    });
    on(window, 'scroll', update, { passive: true });
    on(window, 'resize', update);
    update();
  }

  /* -------------------------------------------------------------------
     Theme
     ------------------------------------------------------------------- */
  function initTheme() {
    var toggle = $('[data-theme-toggle]');
    if (!toggle) return;

    // The markup ships a state-free "Switch theme", which tells a screen reader
    // nothing about what is on now or what the press will do. Describe both
    // from the first render, not only after someone has already clicked once.
    function syncLabel() {
      var light = document.documentElement.getAttribute('data-theme') === 'light';
      toggle.setAttribute('aria-pressed', light ? 'false' : 'true');
      toggle.setAttribute('aria-label', light ? 'Switch to dark theme' : 'Switch to light theme');
    }
    syncLabel();

    on(toggle, 'click', function () {
      var next = document.documentElement.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
      document.documentElement.setAttribute('data-theme', next);
      try { localStorage.setItem('techbiss-theme', next); } catch (e) { /* private mode */ }
      syncLabel();
    });
  }

  /* -------------------------------------------------------------------
     Header: sticky state + reading progress
     ------------------------------------------------------------------- */
  function initHeader() {
    var header = $('.site-header');
    var progress = $('.read-progress');
    if (!header && !progress) return;

    var update = throttleFrame(function () {
      var y = window.scrollY || document.documentElement.scrollTop;
      if (header) header.classList.toggle('is-stuck', y > 12);
      if (progress) {
        var h = document.documentElement.scrollHeight - window.innerHeight;
        progress.style.setProperty('--progress', h > 0 ? Math.min(1, y / h).toFixed(4) : '0');
      }
    });
    on(window, 'scroll', update, { passive: true });
    on(window, 'resize', update, { passive: true });
    update();
  }

  /* -------------------------------------------------------------------
     Navigation: desktop dropdowns + mobile drawer
     ------------------------------------------------------------------- */
  function initNav() {
    var items = $$('.nav__item--has-children');

    items.forEach(function (item) {
      var trigger = $('.nav__link', item);
      if (!trigger) return;
      on(trigger, 'click', function (e) {
        // On touch, the first tap opens the menu instead of following the link.
        if (!canHover.matches || trigger.getAttribute('href') === '#' || !trigger.getAttribute('href')) {
          e.preventDefault();
          var open = item.classList.toggle('is-open');
          trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
        }
      });
      on(trigger, 'keydown', function (e) {
        if (e.key === 'ArrowDown') {
          e.preventDefault();
          item.classList.add('is-open');
          trigger.setAttribute('aria-expanded', 'true');
          var first = $('.nav__dropdown-link', item);
          if (first) first.focus();
        }
      });
      on(item, 'focusout', function (e) {
        if (!item.contains(e.relatedTarget)) {
          item.classList.remove('is-open');
          trigger.setAttribute('aria-expanded', 'false');
        }
      });
    });

    on(document, 'click', function (e) {
      items.forEach(function (item) {
        if (!item.contains(e.target)) {
          item.classList.remove('is-open');
          var t = $('.nav__link', item);
          if (t) t.setAttribute('aria-expanded', 'false');
        }
      });
    });

    // Mobile drawer
    var toggle = $('[data-nav-toggle]');
    var drawer = $('.mobile-nav');
    if (toggle && drawer) {
      // While the drawer is open everything behind it is inert, or Tab walks
      // off into a page nobody can see. The catch is that the control which
      // closes the drawer is the same toggle that opened it, and it lives
      // inside the header — inerting the header outright would leave it
      // unclickable. So we inert every sibling along the path from the body
      // down to the toggle, which leaves exactly the toggle and the drawer
      // reachable. The toast stack is spared so an open toast stays dismissable.
      var inerted = [];
      var lockBackground = function (lock) {
        inerted.forEach(function (el) { el.removeAttribute('inert'); });
        inerted = [];
        if (!lock) return;
        var node = toggle;
        while (node && node !== document.body && node.parentNode) {
          Array.prototype.forEach.call(node.parentNode.children, function (sib) {
            if (sib === node || sib === drawer || sib.hasAttribute('inert')) return;
            if (sib.classList && sib.classList.contains('toast-stack')) return;
            sib.setAttribute('inert', '');
            inerted.push(sib);
          });
          node = node.parentNode;
        }
      };

      var setOpen = function (open) {
        drawer.classList.toggle('is-open', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        document.body.classList.toggle('no-scroll', open);
        drawer.setAttribute('aria-hidden', open ? 'false' : 'true');
        lockBackground(open);
      };
      on(toggle, 'click', function () { setOpen(!drawer.classList.contains('is-open')); });
      on(document, 'keydown', function (e) {
        if (e.key === 'Escape' && drawer.classList.contains('is-open')) { setOpen(false); toggle.focus(); }
      });
      // Close when a real destination is chosen.
      $$('.mobile-nav__link[href], .mobile-nav__sublink', drawer).forEach(function (link) {
        on(link, 'click', function () { if (link.getAttribute('href')) setOpen(false); });
      });
      var mq = window.matchMedia('(min-width: 1025px)');
      var onChange = function () { if (mq.matches) setOpen(false); };
      if (mq.addEventListener) mq.addEventListener('change', onChange);
    }

    // Collapsible groups inside the drawer
    $$('.mobile-nav__group').forEach(function (group) {
      var trigger = $('.mobile-nav__link', group);
      on(trigger, 'click', function (e) {
        e.preventDefault();
        var open = group.getAttribute('data-open') !== 'true';
        group.setAttribute('data-open', open ? 'true' : 'false');
        trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
      });
    });
  }

  /* -------------------------------------------------------------------
     Scroll reveal — IntersectionObserver, one-shot, cheap
     ------------------------------------------------------------------- */
  function initReveal() {
    var targets = $$('[data-reveal]');
    if (!targets.length) return;
    if (reduceMotion.matches || !('IntersectionObserver' in window)) {
      targets.forEach(function (el) { el.classList.add('is-visible'); });
      return;
    }
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
          io.unobserve(entry.target);
        }
      });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.05 });
    targets.forEach(function (el) { io.observe(el); });

    // Keyboard users can reach a section before it has scrolled into view —
    // without this they would be focusing something still at opacity: 0.
    on(document, 'focusin', function (e) {
      var el = e.target.closest && e.target.closest('[data-reveal]');
      while (el) {
        el.classList.add('is-visible');
        el = el.parentNode && el.parentNode.closest ? el.parentNode.closest('[data-reveal]') : null;
      }
    });
  }

  /* -------------------------------------------------------------------
     Animated counters for the stats band
     ------------------------------------------------------------------- */
  function initCounters() {
    var els = $$('[data-count]');
    if (!els.length) return;

    // The markup ships "0" so the animation has somewhere to start. Whenever we
    // are not going to animate — reduced motion, or no IntersectionObserver —
    // the final value has to be written immediately, or the number stays a
    // permanent zero and the section reads as broken.
    function settle(el) {
      var target = parseFloat(el.getAttribute('data-count'));
      if (isNaN(target)) return;
      var decimals = (el.getAttribute('data-count').split('.')[1] || '').length;
      el.textContent = target.toFixed(decimals);
    }

    if (reduceMotion.matches || !('IntersectionObserver' in window)) {
      els.forEach(settle);
      return;
    }

    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        var el = entry.target;
        io.unobserve(el);
        var target = parseFloat(el.getAttribute('data-count'));
        if (isNaN(target)) return;
        var decimals = (el.getAttribute('data-count').split('.')[1] || '').length;
        var start = performance.now();
        var dur = 1100;
        var step = function (now) {
          var p = Math.min(1, (now - start) / dur);
          var eased = 1 - Math.pow(1 - p, 3);
          el.textContent = (target * eased).toFixed(decimals);
          if (p < 1) requestAnimationFrame(step);
          else el.textContent = target.toFixed(decimals);
        };
        requestAnimationFrame(step);
      });
    }, { threshold: 0.4 });
    els.forEach(function (el) { io.observe(el); });

    on(reduceMotion, 'change', function () {
      if (reduceMotion.matches) {
        els.forEach(function (el) {
          io.unobserve(el);
          settle(el);
        });
      }
    });
  }

  /* -------------------------------------------------------------------
     Cursor enhancement — desktop pointers only
     ------------------------------------------------------------------- */
  function initCursor() {
    if (!canHover.matches || reduceMotion.matches || cfg.cursor === false) return;

    var dot  = document.createElement('div');
    var ring = document.createElement('div');
    dot.className = 'cursor-dot';
    ring.className = 'cursor-ring';
    dot.setAttribute('aria-hidden', 'true');
    ring.setAttribute('aria-hidden', 'true');
    document.body.appendChild(dot);
    document.body.appendChild(ring);
    document.body.classList.add('has-cursor-fx');

    var mx = window.innerWidth / 2, my = window.innerHeight / 2;
    var rx = mx, ry = my;

    on(document, 'mousemove', function (e) {
      mx = e.clientX; my = e.clientY;
      dot.style.transform = 'translate(' + mx + 'px,' + my + 'px) translate(-50%,-50%)';
      document.body.classList.remove('cursor-hidden');
    }, { passive: true });

    on(document, 'mouseleave', function () { document.body.classList.add('cursor-hidden'); });

    (function loop() {
      rx += (mx - rx) * 0.16;
      ry += (my - ry) * 0.16;
      ring.style.transform = 'translate(' + rx.toFixed(2) + 'px,' + ry.toFixed(2) + 'px) translate(-50%,-50%)';
      requestAnimationFrame(loop);
    })();

    var hotSelector = 'a, button, [role="button"], input, textarea, select, .card--interactive, summary';
    on(document, 'mouseover', function (e) {
      if (e.target.closest && e.target.closest(hotSelector)) document.body.classList.add('cursor-hot');
    });
    on(document, 'mouseout', function (e) {
      if (e.target.closest && e.target.closest(hotSelector)) document.body.classList.remove('cursor-hot');
    });
  }

  /* -------------------------------------------------------------------
     Magnetic buttons + cursor-aware card highlight
     ------------------------------------------------------------------- */
  function initMagnetic() {
    if (!canHover.matches || reduceMotion.matches) return;

    $$('[data-magnetic]').forEach(function (el) {
      var strength = parseFloat(el.getAttribute('data-magnetic')) || 0.28;
      var reset = function () { el.style.transform = ''; };
      on(el, 'mousemove', function (e) {
        var r = el.getBoundingClientRect();
        var x = (e.clientX - r.left - r.width / 2) * strength;
        var y = (e.clientY - r.top - r.height / 2) * strength;
        el.style.transform = 'translate(' + x.toFixed(2) + 'px,' + y.toFixed(2) + 'px)';
      });
      on(el, 'mouseleave', reset);
      on(el, 'blur', reset);
    });

    $$('.card--spotlight').forEach(function (card) {
      on(card, 'mousemove', function (e) {
        var r = card.getBoundingClientRect();
        var mx = e.clientX - r.left, my = e.clientY - r.top;
        card.style.setProperty('--mx', mx + 'px');
        card.style.setProperty('--my', my + 'px');
        // A light 3D tilt toward the cursor, on top of the existing glow —
        // capped low (8deg) so it reads as responsive, not gimmicky.
        var rx = (my / r.height - 0.5) * -8;
        var ry = (mx / r.width - 0.5) * 8;
        card.style.transform = 'perspective(900px) rotateX(' + rx.toFixed(2) + 'deg) rotateY(' + ry.toFixed(2) + 'deg) translateY(-4px)';
      });
      on(card, 'mouseleave', function () { card.style.transform = ''; });
    });
  }

  /* -------------------------------------------------------------------
     Depth scroll — background layers move at their own rate as the page
     scrolls. Desktop and touch alike; only reduced motion turns it off.
     ------------------------------------------------------------------- */
  function initParallax() {
    var layers = $$('[data-parallax]');
    if (!layers.length || reduceMotion.matches) return;

    var update = throttleFrame(function () {
      var vh = window.innerHeight;
      layers.forEach(function (el) {
        var rate = parseFloat(el.getAttribute('data-parallax')) || 0.15;
        var r = el.getBoundingClientRect();
        var center = r.top + r.height / 2;
        var offset = (center - vh / 2) * rate;
        el.style.transform = 'translateY(' + offset.toFixed(1) + 'px)';
      });
    });
    on(window, 'scroll', update, { passive: true });
    on(window, 'resize', update);
    update();
  }

  /* -------------------------------------------------------------------
     Scroll scenes — continuous scroll-linked motion, not one-shot like
     data-reveal. Writes --scroll-progress (0 to 1, how far the element has
     traveled through the viewport) on every [data-scene] element; CSS owns
     what that number does to transform/opacity, same bridge pattern as the
     cursor/band spotlight's --mx/--sx custom properties above. An
     IntersectionObserver keeps only near-viewport elements in the per-frame
     loop — data-parallax is a handful of hero layers and can afford to
     touch every one of them every scroll frame, but data-scene is meant to
     be used liberally across a whole page, so it needs the gate from the
     start.
     ------------------------------------------------------------------- */
  function initScrollScene() {
    var scenes = $$('[data-scene]');
    if (!scenes.length || reduceMotion.matches || !('IntersectionObserver' in window)) return;

    var active = [];
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        var i = active.indexOf(entry.target);
        if (entry.isIntersecting) {
          if (i === -1) active.push(entry.target);
        } else if (i !== -1) {
          active.splice(i, 1);
        }
      });
    }, { rootMargin: '200px 0px 200px 0px', threshold: 0 });
    scenes.forEach(function (el) { io.observe(el); });

    var update = throttleFrame(function () {
      var vh = window.innerHeight;
      active.forEach(function (el) {
        var r = el.getBoundingClientRect();
        var total = vh + r.height;
        var p = total > 0 ? (vh - r.top) / total : 0;
        if (p < 0) p = 0; else if (p > 1) p = 1;
        el.style.setProperty('--scroll-progress', p.toFixed(3));
      });
    });
    on(window, 'scroll', update, { passive: true });
    on(window, 'resize', update);
    update();
  }

  /* -------------------------------------------------------------------
     Cursor spotlight over a CTA band's grid — desktop pointers only
     ------------------------------------------------------------------- */
  function initSpotlightBand() {
    if (!canHover.matches || reduceMotion.matches) return;

    $$('.cta-band').forEach(function (band) {
      var spot = band.querySelector('[data-spotlight]');
      if (!spot) return;
      on(band, 'mousemove', function (e) {
        var r = band.getBoundingClientRect();
        spot.style.setProperty('--sx', (e.clientX - r.left) + 'px');
        spot.style.setProperty('--sy', (e.clientY - r.top) + 'px');
        spot.classList.add('is-active');
      });
      on(band, 'mouseleave', function () { spot.classList.remove('is-active'); });
    });
  }

  /* -------------------------------------------------------------------
     Ambient background — a soft drifting-gradient canvas confined to a
     hero band. Reserved for a couple of flagship moments (marked with
     canvas[data-ambient] in the template), never sitewide: this is the one
     genuinely continuous render loop in the file, so it is the most
     strictly gated. It only ever starts if reduced motion is off, the
     canvas is actually on/near screen, and the tab is visible — and it
     stops the instant any of those stop being true, not just at load.
     ------------------------------------------------------------------- */
  function initAmbient() {
    var canvas = $('canvas[data-ambient]');
    if (!canvas || !canvas.getContext) return;

    var ctx = canvas.getContext('2d');
    if (!ctx) return;

    var dpr = Math.min(window.devicePixelRatio || 1, 2);
    var w = 0, h = 0;
    var colors = ['#26d9c9', '#c8ff4d', '#ff5d8f'];
    var blobs = [
      { x: 0.22, y: 0.38, r: 0.42, dx: 0.05, dy: 0.035, freq: 0.05, phase: 0,   c: 0 },
      { x: 0.74, y: 0.26, r: 0.34, dx: 0.045, dy: 0.05,  freq: 0.045, phase: 2.1, c: 1 },
      { x: 0.52, y: 0.72, r: 0.38, dx: 0.04, dy: 0.045,  freq: 0.038, phase: 4.2, c: 2 },
    ];

    function toRgba(color, alpha) {
      color = (color || '').trim();
      if (color.charAt(0) === '#') {
        var hex = color.slice(1);
        if (hex.length === 3) hex = hex.replace(/./g, function (c) { return c + c; });
        var num = parseInt(hex, 16);
        return 'rgba(' + [(num >> 16) & 255, (num >> 8) & 255, num & 255].join(',') + ',' + alpha + ')';
      }
      var m = color.match(/rgba?\(([^)]+)\)/);
      return m ? 'rgba(' + m[1].split(',').slice(0, 3).join(',') + ',' + alpha + ')' : color;
    }

    function readColors() {
      var cs = getComputedStyle(document.documentElement);
      colors = [
        cs.getPropertyValue('--cyan').trim() || colors[0],
        cs.getPropertyValue('--lime').trim() || colors[1],
        cs.getPropertyValue('--rose').trim() || colors[2],
      ];
    }
    readColors();

    function resize() {
      var r = canvas.getBoundingClientRect();
      w = Math.max(1, Math.round(r.width));
      h = Math.max(1, Math.round(r.height));
      canvas.width = w * dpr;
      canvas.height = h * dpr;
      ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    }
    resize();
    on(window, 'resize', throttleFrame(resize));

    var raf = null, start = null, running = false;
    function frame(ts) {
      if (!running) return;
      if (start === null) start = ts;
      var t = (ts - start) / 1000;
      ctx.clearRect(0, 0, w, h);
      ctx.globalCompositeOperation = 'lighter';
      blobs.forEach(function (b) {
        var cx = (b.x + Math.sin(t * b.freq + b.phase) * b.dx) * w;
        var cy = (b.y + Math.cos(t * b.freq * 0.9 + b.phase) * b.dy) * h;
        var radius = b.r * Math.max(w, h);
        var g = ctx.createRadialGradient(cx, cy, 0, cx, cy, radius);
        g.addColorStop(0, toRgba(colors[b.c], 0.3));
        g.addColorStop(1, toRgba(colors[b.c], 0));
        ctx.fillStyle = g;
        ctx.beginPath();
        ctx.arc(cx, cy, radius, 0, Math.PI * 2);
        ctx.fill();
      });
      ctx.globalCompositeOperation = 'source-over';
      raf = requestAnimationFrame(frame);
    }

    function play() {
      if (running) return;
      running = true;
      start = null;
      raf = requestAnimationFrame(frame);
      requestAnimationFrame(function () { canvas.classList.add('is-ready'); });
    }
    function pause() {
      running = false;
      if (raf) cancelAnimationFrame(raf);
    }

    var onScreen = false, tabVisible = !document.hidden;
    function sync() {
      if (onScreen && tabVisible && !reduceMotion.matches) play(); else pause();
    }

    var io = new IntersectionObserver(function (entries) {
      onScreen = entries[0].isIntersecting;
      sync();
    }, { threshold: 0 });
    io.observe(canvas);

    on(document, 'visibilitychange', function () { tabVisible = !document.hidden; sync(); });
    on(reduceMotion, 'change', sync);
  }

  /* -------------------------------------------------------------------
     Accordion — animated, keyboard accessible, deep-linkable
     ------------------------------------------------------------------- */
  function initAccordion() {
    // A collapsed panel is only squashed to 0fr, which still leaves every
    // answer in the accessibility tree, so `hidden` has to come along for the
    // ride. It cannot be set straight away on collapse — `hidden` is
    // display:none and would cut the row-track transition dead — so it waits
    // for the transition to end, with a timeout for the cases where
    // transitionend never fires (reduced motion, a background tab).
    var HIDE_AFTER = 400; // comfortably past --t-base (240ms)

    // `hidden` alone is not enough here: .accordion__panel carries a class-level
    // display:grid, which outranks the user-agent [hidden] rule, so the panel
    // would stay laid out and its text would stay in the accessibility tree.
    // The inline display does the actual hiding; the attribute keeps the
    // semantics right and stays correct if a [hidden] rule ever lands in CSS.
    function reveal(panel) {
      panel.hidden = false;
      panel.style.display = '';
    }
    function conceal(panel) {
      panel.hidden = true;
      panel.style.display = 'none';
    }

    function setPanel(trigger, panel, open) {
      trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
      if (open) {
        reveal(panel);
        // Read back a layout value so the panel animates out of 0fr instead of
        // snapping open straight from display:none.
        void panel.offsetHeight;
        panel.setAttribute('data-open', 'true');
        return;
      }
      panel.setAttribute('data-open', 'false');
      var hide = function () {
        // It may have been reopened while the collapse was still running.
        if (panel.getAttribute('data-open') === 'false') conceal(panel);
      };
      var done = function (e) {
        if (e.target !== panel || e.propertyName !== 'grid-template-rows') return;
        panel.removeEventListener('transitionend', done);
        hide();
      };
      panel.addEventListener('transitionend', done);
      window.setTimeout(function () {
        panel.removeEventListener('transitionend', done);
        hide();
      }, HIDE_AFTER);
    }

    $$('[data-accordion]').forEach(function (root) {
      var single = root.getAttribute('data-accordion') === 'single';
      var triggers = $$('.accordion__trigger', root);

      triggers.forEach(function (trigger) {
        var panel = document.getElementById(trigger.getAttribute('aria-controls'));
        if (!panel) return;
        if (trigger.getAttribute('aria-expanded') !== 'true') conceal(panel);
        on(trigger, 'click', function () {
          var open = trigger.getAttribute('aria-expanded') === 'true';
          if (single && !open) {
            triggers.forEach(function (t) {
              var p = document.getElementById(t.getAttribute('aria-controls'));
              if (p && t !== trigger) setPanel(t, p, false);
            });
          }
          setPanel(trigger, panel, !open);
        });
      });

      // Open the item referenced by the URL hash, if any.
      if (window.location.hash) {
        var target = root.querySelector(window.location.hash.replace(/[^#\w-]/g, ''));
        if (target) {
          var trg = target.closest('.accordion__item');
          var btn = trg && $('.accordion__trigger', trg);
          if (btn) btn.click();
        }
      }
    });
  }

  /* -------------------------------------------------------------------
     Toasts
     ------------------------------------------------------------------- */
  var ICONS = {
    success: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="icon"><circle cx="12" cy="12" r="9"/><path d="m8.5 12.2 2.4 2.4 4.6-5"/></svg>',
    error:   '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="icon"><circle cx="12" cy="12" r="9"/><path d="m9 9 6 6M15 9l-6 6"/></svg>',
    warning: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="icon"><path d="M12 3.5 21.5 20H2.5L12 3.5Z"/><path d="M12 10v4M12 17h.01"/></svg>',
    info:    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="icon"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/></svg>'
  };

  function toast(message, type, timeout) {
    type = type || 'info';
    // The layout ships an empty .toast-stack so the live region has been in the
    // DOM long before anything is written into it — a region created and filled
    // in the same tick is routinely missed by screen readers. Building one here
    // is only the fallback for pages that do not use that layout.
    var stack = $('.toast-stack');
    if (!stack) {
      stack = document.createElement('div');
      stack.className = 'toast-stack';
      document.body.appendChild(stack);
    }
    if (!stack.hasAttribute('role')) stack.setAttribute('role', 'status');
    if (!stack.hasAttribute('aria-live')) stack.setAttribute('aria-live', 'polite');
    var el = document.createElement('div');
    el.className = 'toast toast--' + type;
    el.innerHTML = (ICONS[type] || ICONS.info) + '<div>' + String(message).replace(/[<>]/g, '') + '</div>';
    var close = document.createElement('button');
    close.className = 'toast__close';
    close.type = 'button';
    close.setAttribute('aria-label', 'Dismiss');
    close.innerHTML = '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg>';
    el.appendChild(close);
    stack.appendChild(el);

    var remove = function () {
      el.classList.add('is-leaving');
      window.setTimeout(function () { if (el.parentNode) el.parentNode.removeChild(el); }, 240);
    };
    on(close, 'click', remove);
    window.setTimeout(remove, timeout || 5200);
  }
  window.techbissToast = toast;

  /* -------------------------------------------------------------------
     Forms: client-side hints, submit lock, async submission
     Server-side validation remains authoritative in every case.
     ------------------------------------------------------------------- */

  // An error a screen reader never hears is not an error message. Messages the
  // server renders are already wired up; these do the same for the ones built
  // here, and keep aria-invalid in step with the visible invalid state.
  var errSeq = 0;
  function errorId(name) {
    var base = 'err-live-' + String(name || 'field').replace(/[^a-zA-Z0-9_-]+/g, '-');
    if (!document.getElementById(base)) return base;
    errSeq += 1;
    return base + '-' + errSeq;
  }
  function describe(el, id) {
    if (!el || !id) return;
    var current = (el.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean);
    if (current.indexOf(id) === -1) current.push(id);
    el.setAttribute('aria-describedby', current.join(' '));
    el.setAttribute('aria-invalid', 'true');
  }
  function undescribe(el, id) {
    if (!el) return;
    el.removeAttribute('aria-invalid');
    if (!id) return;
    var current = (el.getAttribute('aria-describedby') || '').split(/\s+/).filter(function (v) { return v && v !== id; });
    if (current.length) el.setAttribute('aria-describedby', current.join(' '));
    else el.removeAttribute('aria-describedby');
  }

  function initForms() {
    $$('form[data-form]').forEach(function (form) {
      var submitBtn = form.querySelector('[type="submit"]');
      var isAsync   = form.getAttribute('data-form') === 'async';

      // Clear the invalid state as soon as the visitor starts fixing it.
      $$('.input, .textarea, .select', form).forEach(function (field) {
        on(field, 'input', function () {
          field.classList.remove('is-invalid');
          var err = field.parentNode && field.parentNode.querySelector('.field-error');
          if (err && err.getAttribute('data-live') === 'true') {
            undescribe(field, err.id);
            err.remove();
          } else {
            // A message the server rendered stays on screen, so it stays in the
            // description; only the invalid state is cleared.
            field.removeAttribute('aria-invalid');
          }
        });
      });

      on(form, 'submit', function (e) {
        if (!form.checkValidity()) {
          e.preventDefault();
          var firstInvalid = form.querySelector(':invalid');
          if (firstInvalid) {
            firstInvalid.classList.add('is-invalid');
            firstInvalid.focus();
            firstInvalid.scrollIntoView({ block: 'center', behavior: reduceMotion.matches ? 'auto' : 'smooth' });
          }
          return;
        }
        if (!isAsync) {
          if (submitBtn) {
            submitBtn.classList.add('is-loading');
            submitBtn.disabled = true;
            // Re-enable if the browser restores the page from bfcache.
            window.setTimeout(function () { submitBtn.classList.remove('is-loading'); submitBtn.disabled = false; }, 12000);
          }
          return;
        }

        e.preventDefault();
        if (submitBtn) { submitBtn.classList.add('is-loading'); submitBtn.disabled = true; }

        fetch(form.action, {
          method: 'POST',
          body: new FormData(form),
          headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
          credentials: 'same-origin'
        })
          .then(function (r) { return r.json().catch(function () { return { ok: false, message: 'Unexpected response from the server.' }; }); })
          .then(function (data) {
            if (data.ok) {
              toast(data.message || 'Thank you — we have received your message.', 'success', 6500);
              form.reset();
              if (data.redirect) window.setTimeout(function () { window.location.href = data.redirect; }, 900);
            } else {
              toast(data.message || 'Please check the form and try again.', 'error', 6500);
              if (data.errors) {
                Object.keys(data.errors).forEach(function (name) {
                  var field = form.querySelector('[name="' + name + '"]');
                  if (!field) return;
                  field.classList.add('is-invalid');
                  var wrap = field.closest('.field') || field.parentNode;
                  if (wrap && !wrap.querySelector('.field-error')) {
                    var msg = document.createElement('span');
                    msg.className = 'field-error';
                    msg.id = errorId(name);
                    msg.setAttribute('role', 'alert');
                    msg.setAttribute('data-live', 'true');
                    msg.textContent = data.errors[name];
                    wrap.appendChild(msg);
                    describe(field, msg.id);
                  } else {
                    field.setAttribute('aria-invalid', 'true');
                  }
                });
              }
            }
          })
          .catch(function () { toast('We could not reach the server. Please try again.', 'error'); })
          .finally(function () {
            if (submitBtn) { submitBtn.classList.remove('is-loading'); submitBtn.disabled = false; }
          });
      });
    });
  }

  /* -------------------------------------------------------------------
     Gallery lightbox — keyboard and swipe friendly
     ------------------------------------------------------------------- */
  function initLightbox() {
    var items = $$('[data-lightbox]');
    if (!items.length) return;

    var box, imgEl, countEl, current = 0;

    function render() {
      var src = items[current].getAttribute('data-lightbox');
      var alt = items[current].getAttribute('data-lightbox-alt') || '';
      imgEl.src = src;
      imgEl.alt = alt;
      if (countEl) countEl.textContent = (current + 1) + ' / ' + items.length;
    }

    function open(i) {
      current = i;
      box = document.createElement('div');
      box.className = 'lightbox';
      box.setAttribute('role', 'dialog');
      box.setAttribute('aria-modal', 'true');
      box.setAttribute('aria-label', 'Project image viewer');
      box.innerHTML =
        '<button class="lightbox__close" type="button" aria-label="Close viewer">' +
        '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg></button>' +
        (items.length > 1 ? '<button class="lightbox__nav lightbox__nav--prev" type="button" aria-label="Previous image"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m15 6-6 6 6 6"/></svg></button>' +
        '<button class="lightbox__nav lightbox__nav--next" type="button" aria-label="Next image"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m9 6 6 6-6 6"/></svg></button>' +
        '<span class="lightbox__count" aria-live="polite"></span>' : '') +
        '<img alt="">';
      document.body.appendChild(box);
      document.body.classList.add('no-scroll');
      imgEl = $('img', box);
      countEl = $('.lightbox__count', box);
      render();

      on($('.lightbox__close', box), 'click', close);
      var prev = $('.lightbox__nav--prev', box), next = $('.lightbox__nav--next', box);
      if (prev) on(prev, 'click', function () { current = (current - 1 + items.length) % items.length; render(); });
      if (next) on(next, 'click', function () { current = (current + 1) % items.length; render(); });
      on(box, 'click', function (e) { if (e.target === box) close(); });
      on(document, 'keydown', keys);

      // Swipe on touch
      var sx = 0;
      on(box, 'touchstart', function (e) { sx = e.touches[0].clientX; }, { passive: true });
      on(box, 'touchend', function (e) {
        var dx = e.changedTouches[0].clientX - sx;
        if (Math.abs(dx) > 48 && items.length > 1) {
          current = (current + (dx < 0 ? 1 : -1) + items.length) % items.length;
          render();
        }
      }, { passive: true });

      $('.lightbox__close', box).focus();
    }

    function keys(e) {
      if (!box) return;
      if (e.key === 'Tab') trapTab(box, e);
      else if (e.key === 'Escape') close();
      else if (e.key === 'ArrowRight' && items.length > 1) { current = (current + 1) % items.length; render(); }
      else if (e.key === 'ArrowLeft' && items.length > 1) { current = (current - 1 + items.length) % items.length; render(); }
    }

    function close() {
      if (!box) return;
      document.removeEventListener('keydown', keys);
      document.body.classList.remove('no-scroll');
      if (box.parentNode) box.parentNode.removeChild(box);
      box = null;
      if (items[current]) items[current].focus();
    }

    items.forEach(function (item, i) {
      item.setAttribute('tabindex', '0');
      item.setAttribute('role', 'button');
      on(item, 'click', function () { open(i); });
      on(item, 'keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); open(i); }
      });
    });
  }

  /* -------------------------------------------------------------------
     Copy-to-clipboard buttons
     ------------------------------------------------------------------- */
  function initCopy() {
    $$('[data-copy]').forEach(function (btn) {
      on(btn, 'click', function () {
        var text = btn.getAttribute('data-copy');
        var done = function () { toast('Copied to clipboard.', 'success', 2400); };
        if (navigator.clipboard && window.isSecureContext) {
          navigator.clipboard.writeText(text).then(done).catch(function () { toast('Could not copy.', 'error'); });
        } else {
          var ta = document.createElement('textarea');
          ta.value = text;
          ta.style.position = 'fixed';
          ta.style.opacity = '0';
          document.body.appendChild(ta);
          ta.select();
          try { document.execCommand('copy'); done(); } catch (e) { toast('Could not copy.', 'error'); }
          document.body.removeChild(ta);
        }
      });
    });
  }

  /* -------------------------------------------------------------------
     Auto-submitting filter controls
     ------------------------------------------------------------------- */
  function initFilters() {
    $$('[data-autosubmit]').forEach(function (el) {
      on(el, 'change', function () {
        var form = el.closest('form');
        if (form) form.submit();
      });
    });
  }

  /* -------------------------------------------------------------------
     Page transitions — a short cross-fade on same-origin navigation
     ------------------------------------------------------------------- */
  function initTransitions() {
    if (reduceMotion.matches || cfg.transitions === false) return;
    var veil = $('.page-veil');
    if (!veil) return;

    document.body.classList.add('is-entering');

    on(document, 'click', function (e) {
      var link = e.target.closest && e.target.closest('a');
      if (!link) return;
      if (e.metaKey || e.ctrlKey || e.shiftKey || e.button !== 0) return;
      var href = link.getAttribute('href');
      if (!href || href.charAt(0) === '#' || link.target === '_blank' || link.hasAttribute('download')) return;
      if (link.hasAttribute('data-no-transition')) return;
      var url;
      try { url = new URL(link.href, window.location.href); } catch (err) { return; }
      if (url.origin !== window.location.origin) return;
      if (url.pathname === window.location.pathname && url.search === window.location.search) return;
      if (/^(mailto:|tel:)/i.test(href)) return;

      e.preventDefault();
      veil.classList.add('is-active');
      window.setTimeout(function () { window.location.href = link.href; }, 170);
    });

    // Clear the veil when returning through the back/forward cache.
    on(window, 'pageshow', function (e) { if (e.persisted) veil.classList.remove('is-active'); });
  }

  /* -------------------------------------------------------------------
     Server-rendered flash messages become toasts
     ------------------------------------------------------------------- */
  function initFlash() {
    $$('[data-flash]').forEach(function (el) {
      toast(el.getAttribute('data-flash-message') || el.textContent.trim(), el.getAttribute('data-flash') || 'info', 6000);
      el.remove();
    });
  }

  /* -------------------------------------------------------------------
     Boot
     ------------------------------------------------------------------- */
  function boot() {
    initLoader();
    initScrollProgress();
    initTheme();
    initHeader();
    initNav();
    initReveal();
    initCounters();
    initAccordion();
    initForms();
    initLightbox();
    initCopy();
    initFilters();
    initTransitions();
    initFlash();
    if (cfg.cursor !== false) initCursor();
    initMagnetic();
    initParallax();
    initScrollScene();
    initSpotlightBand();
    if (cfg.ambient !== false) initAmbient();
    initSliders();
    initAutoOpen();
    initTilt();
    initRail();
    initSkew();
  }

  /* -------------------------------------------------------------------
     Bento card tilt — a real perspective rotate on pointer move, plus a
     cursor-tracked glow position (written as --gx/--gy, same JS-writes-
     a-number / CSS-does-the-rest split as every other spotlight effect
     here). No-ops without a fine pointer, under reduced motion, or when
     no [data-tilt] element exists on the page.
     ------------------------------------------------------------------- */
  function initTilt() {
    if (reduceMotion.matches || !canHover.matches) return;
    var cards = $$('[data-tilt]');
    if (!cards.length) return;
    cards.forEach(function (card) {
      on(card, 'mousemove', function (e) {
        var r = card.getBoundingClientRect();
        var px = (e.clientX - r.left) / r.width, py = (e.clientY - r.top) / r.height;
        var rx = (py - 0.5) * -8, ry = (px - 0.5) * 10;
        card.style.transform = 'perspective(700px) rotateX(' + rx + 'deg) rotateY(' + ry + 'deg) translateZ(0)';
        card.style.setProperty('--gx', (px * 100) + '%');
        card.style.setProperty('--gy', (py * 100) + '%');
      });
      on(card, 'mouseleave', function () {
        card.style.transition = 'transform 420ms ' + getComputedStyle(document.documentElement).getPropertyValue('--ease-out');
        card.style.transform = 'none';
        window.setTimeout(function () { card.style.transition = ''; }, 420);
      });
    });
  }

  /* -------------------------------------------------------------------
     Process rail — fills a vertical line as the visitor scrolls through
     the steps beside it, and marks whichever step is currently in the
     read band as active. Pure scroll-position read, no state kept
     beyond what's visible on screen right now.
     ------------------------------------------------------------------- */
  function initRail() {
    var line = $('.process-rail__line');
    var fill = line ? $('i', line) : null;
    var steps = $$('.rail-step');
    if (!line || !fill || !steps.length) return;
    var update = throttleFrame(function () {
      var r = line.getBoundingClientRect();
      var vh = window.innerHeight;
      var start = vh * 0.75, end = vh * 0.3;
      var covered = start - r.top;
      var frac = Math.max(0, Math.min(1, covered / (r.height + (start - end))));
      fill.style.height = (frac * 100) + '%';
      steps.forEach(function (step) {
        var sr = step.getBoundingClientRect();
        step.classList.toggle('is-active', sr.top < vh * 0.65 && sr.bottom > vh * 0.2);
      });
    });
    on(window, 'scroll', update, { passive: true });
    on(window, 'resize', update);
    update();
  }

  /* -------------------------------------------------------------------
     Statement skew — a single line that leans with scroll velocity and
     settles back upright. Decorative only; the text itself never moves
     out of reading position enough to affect legibility.
     ------------------------------------------------------------------- */
  function initSkew() {
    if (reduceMotion.matches) return;
    var el = $('[data-skew]');
    if (!el) return;
    var lastY = window.scrollY, vel = 0;
    on(window, 'scroll', function () {
      var y = window.scrollY;
      vel = y - lastY; lastY = y;
    }, { passive: true });
    (function frame() {
      var target = Math.max(-6, Math.min(6, vel * 0.6));
      vel *= 0.85;
      el.style.transform = 'skewY(' + target.toFixed(2) + 'deg)';
      requestAnimationFrame(frame);
    })();
  }

  /* ---------------------------------------------------------------------
     Card sliders

     Progressive enhancement over a plain grid: the scrolling and snapping are
     CSS, so a swipe works with no JS at all. This only adds the arrows and
     dots, and only while the track is actually scrollable — above the
     breakpoint the CSS turns the track back into a grid and the controls hide
     themselves.
     ------------------------------------------------------------------ */
  function initSliders() {
    var sliders = $$('[data-slider]');
    if (!sliders.length) return;

    sliders.forEach(function (slider) {
      var track = slider.querySelector('.slider__track');
      if (!track) return;

      var slides = Array.prototype.filter.call(track.children, function (el) {
        return el.nodeType === 1;
      });
      if (slides.length < 2) return;

      var controls = document.createElement('div');
      controls.className = 'slider__controls';

      var prev = document.createElement('button');
      prev.type = 'button';
      prev.className = 'slider__btn';
      prev.setAttribute('aria-label', 'Previous');
      prev.innerHTML = '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 5l-7 7 7 7"/></svg>';

      var next = prev.cloneNode(true);
      next.setAttribute('aria-label', 'Next');
      next.innerHTML = '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 5l7 7-7 7"/></svg>';

      // These were tabs without tabpanels, which promises a screen reader a
      // relationship that does not exist and implies arrow-key navigation the
      // widget does not implement. They are what they look like: buttons that
      // scroll the row, with the current one marked.
      var dots = document.createElement('div');
      dots.className = 'slider__dots';
      dots.setAttribute('role', 'group');
      dots.setAttribute('aria-label', 'Choose a card');

      var dotEls = slides.map(function (slide, i) {
        var dot = document.createElement('button');
        dot.type = 'button';
        dot.className = 'slider__dot';
        dot.setAttribute('aria-label', 'Card ' + (i + 1) + ' of ' + slides.length);
        on(dot, 'click', function () {
          track.scrollTo({
            left: slide.offsetLeft - track.offsetLeft,
            behavior: reduceMotion.matches ? 'auto' : 'smooth'
          });
        });
        dots.appendChild(dot);
        return dot;
      });

      controls.appendChild(prev);
      controls.appendChild(dots);
      controls.appendChild(next);
      slider.appendChild(controls);

      function step(dir) {
        var by = slides[0].getBoundingClientRect().width + 16;
        track.scrollBy({ left: dir * by, behavior: reduceMotion.matches ? 'auto' : 'smooth' });
      }
      on(prev, 'click', function () { step(-1); });
      on(next, 'click', function () { step(1); });

      // Named so several rows on one page do not all announce the same thing.
      var heading = slider.closest('section') && slider.closest('section').querySelector('h2, h3');
      var headingText = heading ? heading.textContent.replace(/\s+/g, ' ').trim() : '';
      track.setAttribute('role', 'group');
      track.setAttribute('aria-label', (headingText && headingText.length <= 60 ? headingText + ' — cards' : 'Cards'));

      var sync = throttleFrame(function () {
        var max = track.scrollWidth - track.clientWidth;
        prev.disabled = track.scrollLeft <= 2;
        next.disabled = track.scrollLeft >= max - 2;

        // The track scrolls with overflow-x, so it has to be focusable for
        // anyone scrolling it by keyboard — but only while it really scrolls,
        // or every row leaves a dead tab stop on the desktop grid layout.
        if (track.scrollWidth > track.clientWidth + 2) {
          track.setAttribute('tabindex', '0');
        } else if (document.activeElement !== track) {
          track.removeAttribute('tabindex');
        }

        // Whichever slide sits nearest the track's left edge is the current one.
        var best = 0;
        var bestGap = Infinity;
        slides.forEach(function (slide, i) {
          var gap = Math.abs(slide.offsetLeft - track.offsetLeft - track.scrollLeft);
          if (gap < bestGap) { bestGap = gap; best = i; }
        });
        dotEls.forEach(function (dot, i) {
          dot.classList.toggle('is-active', i === best);
          if (i === best) dot.setAttribute('aria-current', 'true');
          else dot.removeAttribute('aria-current');
        });
      });

      on(track, 'scroll', sync, { passive: true });
      on(window, 'resize', sync);
      sync();

      /* Auto-advance ------------------------------------------------
         The row moves on its own without being asked to, and can still be
         moved by hand. Taking hold of it — a swipe, an arrow, a dot — only
         holds the motion while you are working with it: a row that keeps
         sliding under a finger is worse than one that never moved, but a
         row that never starts again is not what was asked for either. It
         does not run at all for anyone who has asked for reduced motion,
         and it stops while the tab is hidden or a pointer is over it. */
      var DELAY  = 5000;
      var RESUME = 12000;
      var timer  = null;
      var resumeTimer = null;
      var held   = false;

      function scrollable() {
        return track.scrollWidth > track.clientWidth + 2;
      }

      function advance() {
        if (held || !scrollable() || document.hidden) return;
        var max = track.scrollWidth - track.clientWidth;
        if (track.scrollLeft >= max - 2) {
          track.scrollTo({ left: 0, behavior: 'smooth' });
        } else {
          step(1);
        }
      }

      function play() {
        if (held || timer || reduceMotion.matches || !scrollable()) return;
        timer = window.setInterval(advance, DELAY);
      }
      function pause() {
        if (timer) { window.clearInterval(timer); timer = null; }
      }

      // Someone is moving the row themselves: hold the motion, then let it
      // pick up again once they have stopped.
      function hold() {
        held = true;
        pause();
        if (resumeTimer) window.clearTimeout(resumeTimer);
        resumeTimer = window.setTimeout(function () {
          held = false;
          play();
        }, RESUME);
      }

      on(slider, 'pointerenter', pause);
      on(slider, 'pointerleave', play);
      on(slider, 'focusin', pause);
      on(slider, 'focusout', play);
      on(document, 'visibilitychange', function () {
        if (document.hidden) { pause(); } else { play(); }
      });

      // Any deliberate move — a swipe, an arrow, a dot — hands control over.
      // A wheel event fires on the track just as often from someone merely
      // scrolling the page past it, though: only a gesture that is actually
      // more sideways than vertical is someone trying to move the row.
      on(track, 'pointerdown', hold);
      on(track, 'wheel', function (e) {
        if (Math.abs(e.deltaX) > Math.abs(e.deltaY)) hold();
      }, { passive: true });
      on(prev, 'click', hold);
      on(next, 'click', hold);
      dotEls.forEach(function (dot) { on(dot, 'click', hold); });
      on(reduceMotion, 'change', function () {
        if (reduceMotion.matches) { pause(); } else { play(); }
      });

      play();
    });
  }

  /* -------------------------------------------------------------------
     A finished request message
     The page comes back with the message and a link to open it, because a
     form cannot redirect to another origin under our own form-action policy.
     Following the link here spares anyone with JavaScript the extra click.
     ------------------------------------------------------------------- */
  function initAutoOpen() {
    var link = $('[data-auto-open]');
    if (!link) return;
    // A short beat so the message is on screen before the app takes over.
    window.setTimeout(function () { window.location.href = link.href; }, 400);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
