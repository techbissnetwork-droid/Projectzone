/* ============================================================
   TECHBISS · interaction layer
   No dependencies. One rAF loop. GPU-friendly transforms only.
   ============================================================ */
(function () {
  'use strict';

  var doc = document, win = window;
  var reduce = win.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var fine = win.matchMedia('(hover:hover) and (pointer:fine)').matches;
  var clamp = function (v, a, b) { return v < a ? a : v > b ? b : v; };
  var lerp = function (a, b, t) { return a + (b - a) * t; };

  /* ── scroll bus ─────────────────────────────────────────── */
  var listeners = [];
  var onScroll = function (fn) { listeners.push(fn); };
  var ticking = false;
  function pump() {
    ticking = false;
    var y = win.pageYOffset, h = win.innerHeight;
    for (var i = 0; i < listeners.length; i++) listeners[i](y, h);
  }
  function request() { if (!ticking) { ticking = true; requestAnimationFrame(pump); } }
  win.addEventListener('scroll', request, { passive: true });
  win.addEventListener('resize', request, { passive: true });

  /* ── reveal on enter ────────────────────────────────────── */
  (function () {
    var items = doc.querySelectorAll('.reveal, .mask');
    if (!('IntersectionObserver' in win) || reduce) {
      for (var k = 0; k < items.length; k++) items[k].classList.add('in');
      return;
    }
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (!e.isIntersecting) return;
        e.target.classList.add('in');
        io.unobserve(e.target);
      });
    }, { rootMargin: '0px 0px -12% 0px', threshold: 0.08 });

    for (var i = 0; i < items.length; i++) {
      var el = items[i];
      if (el.hasAttribute('data-d')) el.style.setProperty('--d', el.getAttribute('data-d'));
      else {
        // auto-stagger siblings inside a common parent
        var sibs = el.parentNode ? el.parentNode.children : [];
        var n = 0, j;
        for (j = 0; j < sibs.length; j++) { if (sibs[j] === el) break; if (sibs[j].classList && (sibs[j].classList.contains('reveal') || sibs[j].classList.contains('mask'))) n++; }
        el.style.setProperty('--d', Math.min(n, 6));
      }
      io.observe(el);
    }
  })();

  /* ── navigation ─────────────────────────────────────────── */
  (function () {
    var nav = doc.getElementById('nav');
    if (!nav) return;
    var bar = nav.querySelector('.nav__progress i');
    var last = 0;

    onScroll(function (y) {
      nav.classList.toggle('is-stuck', y > 18);
      var down = y > last && y > 560;
      if (!doc.body.classList.contains('is-locked')) nav.classList.toggle('is-hidden', down);
      last = y;
      if (bar) {
        var max = doc.documentElement.scrollHeight - win.innerHeight;
        bar.style.setProperty('--p', (max > 0 ? (y / max) * 100 : 0).toFixed(2) + '%');
      }
    });

    // active section
    var links = [].slice.call(nav.querySelectorAll('[data-nav]'));
    var map = {};
    links.forEach(function (a) {
      var id = a.getAttribute('href').slice(1), s = doc.getElementById(id);
      if (s) map[id] = { link: a, el: s };
    });
    if ('IntersectionObserver' in win) {
      var so = new IntersectionObserver(function (entries) {
        entries.forEach(function (e) {
          var rec = map[e.target.id];
          if (rec) rec.link.classList.toggle('is-current', e.isIntersecting);
        });
      }, { rootMargin: '-45% 0px -50% 0px' });
      Object.keys(map).forEach(function (k) { so.observe(map[k].el); });
    }
  })();

  /* ── mobile menu ────────────────────────────────────────── */
  (function () {
    var burger = doc.getElementById('burger'), menu = doc.getElementById('menu');
    if (!burger || !menu) return;
    var links = [].slice.call(menu.querySelectorAll('a'));
    links.forEach(function (a, i) { a.style.setProperty('--i', i); });

    function open() {
      menu.hidden = false;
      requestAnimationFrame(function () { menu.classList.add('is-open'); });
      burger.setAttribute('aria-expanded', 'true');
      burger.setAttribute('aria-label', 'Close menu');
      doc.body.classList.add('is-locked');
    }
    function close() {
      menu.classList.remove('is-open');
      burger.setAttribute('aria-expanded', 'false');
      burger.setAttribute('aria-label', 'Open menu');
      doc.body.classList.remove('is-locked');
      win.setTimeout(function () { if (!menu.classList.contains('is-open')) menu.hidden = true; }, 620);
    }
    burger.addEventListener('click', function () {
      burger.getAttribute('aria-expanded') === 'true' ? close() : open();
    });
    links.forEach(function (a) { a.addEventListener('click', close); });
    doc.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !menu.hidden) close(); });
  })();

  /* ── custom cursor ──────────────────────────────────────── */
  if (fine && !reduce) (function () {
    var cur = doc.querySelector('.cursor');
    if (!cur) return;
    var dot = cur.querySelector('.cursor__dot'), ring = cur.querySelector('.cursor__ring');
    var label = cur.querySelector('.cursor__label');
    var tx = win.innerWidth / 2, ty = win.innerHeight / 2, rx = tx, ry = ty, dx = tx, dy = ty;
    var active = false;

    doc.addEventListener('mousemove', function (e) {
      tx = e.clientX; ty = e.clientY;
      if (!active) { active = true; dx = rx = tx; dy = ry = ty; cur.classList.add('is-on'); loop(); }
    }, { passive: true });
    doc.addEventListener('mouseleave', function () { cur.classList.remove('is-on'); });
    doc.addEventListener('mouseenter', function () { if (active) cur.classList.add('is-on'); });

    function loop() {
      dx = lerp(dx, tx, 0.55); dy = lerp(dy, ty, 0.55);
      rx = lerp(rx, tx, 0.16);  ry = lerp(ry, ty, 0.16);
      dot.style.transform = 'translate3d(' + dx.toFixed(2) + 'px,' + dy.toFixed(2) + 'px,0)';
      ring.style.transform = 'translate3d(' + rx.toFixed(2) + 'px,' + ry.toFixed(2) + 'px,0)';
      requestAnimationFrame(loop);
    }

    var HOT = 'a, button, .svc, .tilt, [data-cursor]';
    doc.addEventListener('mouseover', function (e) {
      var t = e.target.closest ? e.target.closest(HOT) : null;
      if (!t) return;
      var txt = t.getAttribute('data-cursor') || (t.classList.contains('case__media') ? 'View' : '');
      if (txt) { label.textContent = txt; cur.classList.add('is-label'); cur.classList.remove('is-hot'); }
      else { cur.classList.add('is-hot'); cur.classList.remove('is-label'); }
    });
    doc.addEventListener('mouseout', function (e) {
      var t = e.target.closest ? e.target.closest(HOT) : null;
      if (!t) return;
      if (e.relatedTarget && e.relatedTarget.closest && e.relatedTarget.closest(HOT)) return;
      cur.classList.remove('is-hot', 'is-label');
    });
    doc.addEventListener('mousedown', function () { ring.style.scale = '0.86'; });
    doc.addEventListener('mouseup', function () { ring.style.scale = '1'; });
  })();

  /* ── magnetic buttons + cursor-reactive lighting ────────── */
  if (fine && !reduce) (function () {
    var mags = [].slice.call(doc.querySelectorAll('.magnetic'));
    mags.forEach(function (el) {
      var raf = 0, cx = 0, cy = 0, tX = 0, tY = 0;
      function run() {
        cx = lerp(cx, tX, 0.18); cy = lerp(cy, tY, 0.18);
        el.style.transform = 'translate3d(' + cx.toFixed(2) + 'px,' + cy.toFixed(2) + 'px,0)';
        if (Math.abs(cx - tX) > 0.05 || Math.abs(cy - tY) > 0.05) raf = requestAnimationFrame(run);
        else { el.style.transform = 'translate3d(' + tX + 'px,' + tY + 'px,0)'; raf = 0; }
      }
      el.addEventListener('mousemove', function (e) {
        var r = el.getBoundingClientRect();
        var px = e.clientX - r.left, py = e.clientY - r.top;
        tX = (px / r.width - 0.5) * 16; tY = (py / r.height - 0.5) * 14;
        el.style.setProperty('--mx', (px / r.width * 100).toFixed(1) + '%');
        el.style.setProperty('--my', (py / r.height * 100).toFixed(1) + '%');
        if (!raf) raf = requestAnimationFrame(run);
      });
      el.addEventListener('mouseleave', function () { tX = 0; tY = 0; if (!raf) raf = requestAnimationFrame(run); });
    });
  })();

  /* ── 3D tilt + light position ───────────────────────────── */
  if (fine && !reduce) (function () {
    var tilts = [].slice.call(doc.querySelectorAll('.tilt'));
    tilts.forEach(function (el) {
      var max = el.classList.contains('case__media') ? 4 : 5;
      el.addEventListener('mousemove', function (e) {
        var r = el.getBoundingClientRect();
        var px = (e.clientX - r.left) / r.width, py = (e.clientY - r.top) / r.height;
        el.style.setProperty('--ry', ((px - 0.5) * max).toFixed(2) + 'deg');
        el.style.setProperty('--rx', ((0.5 - py) * max).toFixed(2) + 'deg');
        el.style.setProperty('--mx', (px * 100).toFixed(1) + '%');
        el.style.setProperty('--my', (py * 100).toFixed(1) + '%');
      });
      el.addEventListener('mouseleave', function () {
        el.style.setProperty('--rx', '0deg'); el.style.setProperty('--ry', '0deg');
      });
    });
  })();

  /* ── 03 services: expanding modules ─────────────────────── */
  (function () {
    var grid = doc.getElementById('svcGrid');
    if (!grid) return;
    var cards = [].slice.call(grid.querySelectorAll('.svc'));

    function setOpen(card, on) {
      card.classList.toggle('is-open', on);
      var btn = card.querySelector('.svc__btn');
      if (btn) btn.setAttribute('aria-expanded', on ? 'true' : 'false');
    }
    cards.forEach(function (card) {
      var btn = card.querySelector('.svc__btn');
      card.addEventListener('click', function (e) {
        if (e.target.closest && e.target.closest('a')) return;
        var willOpen = !card.classList.contains('is-pinned');
        cards.forEach(function (c) { c.classList.remove('is-pinned'); setOpen(c, false); });
        if (willOpen) { card.classList.add('is-pinned'); setOpen(card, true); }
      });
      if (fine) {
        card.addEventListener('mouseenter', function () { setOpen(card, true); });
        card.addEventListener('mouseleave', function () { if (!card.classList.contains('is-pinned')) setOpen(card, false); });
        btn.addEventListener('focus', function () { setOpen(card, true); });
        btn.addEventListener('blur', function () { if (!card.classList.contains('is-pinned')) setOpen(card, false); });
      }
    });
  })();

  /* ── 02 offline → online : scroll-driven ────────────────── */
  (function () {
    var track = doc.getElementById('shiftTrack');
    if (!track) return;
    var stage = track.querySelector('.shift__stage');
    var panels = [].slice.call(track.querySelectorAll('.panel'));
    var rail = [].slice.call(track.querySelectorAll('#shiftRail li'));
    var bar = doc.getElementById('shiftBar');
    var wordsTrack = doc.getElementById('shiftWords');
    var words = wordsTrack ? [].slice.call(wordsTrack.children) : [];
    var title = doc.getElementById('shiftTitle'), text = doc.getElementById('shiftText');
    var stageLabel = doc.getElementById('shiftStageLabel');

    /* Stage copy is rendered by PHP from the editable content blocks. */
    var COPY = [];
    var dataEl = doc.getElementById('journeyData');
    if (dataEl) {
      try {
        COPY = JSON.parse(dataEl.textContent || '[]').map(function (s) {
          return [s.title || '', s.text || '', 'Stage ' + '', s.rail || ''];
        });
      } catch (err) { COPY = []; }
    }

    var offsets = [];
    function measure() {
      if (!wordsTrack || !words.length) return;
      var w = wordsTrack.parentNode.clientWidth;
      offsets = words.map(function (s) { return s.offsetLeft + s.offsetWidth / 2 - w / 2; });
    }
    measure();
    win.addEventListener('resize', measure, { passive: true });
    if (doc.fonts && doc.fonts.ready) doc.fonts.ready.then(measure);

    var current = -1;
    onScroll(function (y, vh) {
      var r = track.getBoundingClientRect();
      var total = track.offsetHeight - vh;
      var p = clamp((-r.top) / (total || 1), 0, 1);
      var last = Math.max(0, (words.length || 1) - 1);
      var f = p * last;
      var idx = clamp(Math.round(f), 0, last);

      if (bar) bar.style.setProperty('--p', (p * 100).toFixed(2) + '%');

      if (offsets.length) {
        var a = Math.floor(f), b = Math.min(a + 1, last), t = f - a;
        wordsTrack.style.setProperty('--wx', lerp(offsets[a], offsets[b], t).toFixed(1));
      }

      if (idx === current) return;
      current = idx;
      stage.setAttribute('data-active', Math.min(idx, 3));
      panels.forEach(function (pn, i) { pn.classList.toggle('is-active', i === idx); });
      rail.forEach(function (li, i) { li.classList.toggle('is-on', i === idx); });
      words.forEach(function (s, i) { s.classList.toggle('is-on', i === idx); });
      var copy = COPY[idx];
      if (!copy) return;
      if (title) title.textContent = copy[0];
      if (text) text.textContent = copy[1];
      if (stageLabel) stageLabel.textContent = 'Stage ' + ('0' + (idx + 1)).slice(-2) + ' — ' + copy[3];
    });
  })();

  /* ── 05 process : scroll-reactive stages ────────────────── */
  (function () {
    var track = doc.getElementById('procTrack');
    if (!track) return;
    var stage = track.querySelector('.proc__stage');
    var steps = [].slice.call(track.querySelectorAll('.step'));
    var pvs = [].slice.call(track.querySelectorAll('.pv'));
    var bar = doc.getElementById('procBar'), now = doc.getElementById('procNow');
    var current = -1;

    onScroll(function (y, vh) {
      var r = track.getBoundingClientRect();
      var total = track.offsetHeight - vh;
      var p = clamp((-r.top) / (total || 1), 0, 1);
      var idx = clamp(Math.floor(p * steps.length * 0.999), 0, steps.length - 1);
      if (bar) bar.style.setProperty('--p', (p * 100).toFixed(2) + '%');
      if (idx === current) return;
      current = idx;
      stage.setAttribute('data-active', Math.min(idx, 3));
      steps.forEach(function (s, i) { s.classList.toggle('is-active', i === idx); });
      pvs.forEach(function (v, i) { v.classList.toggle('is-on', i === idx); });
      if (now) now.textContent = '0' + (idx + 1);
    });
    if (pvs[0]) pvs[0].classList.add('is-on');
  })();

  /* ── 07 business transformation ─────────────────────────── */
  (function () {
    var list = doc.getElementById('transList'), stageEl = doc.getElementById('transStage');
    if (!list || !stageEl) return;
    var tabs = [].slice.call(list.querySelectorAll('button'));
    var panels = [].slice.call(stageEl.querySelectorAll('.tpanel'));
    var timer = null, idle = true, i = 0;

    function show(n) {
      i = n;
      tabs.forEach(function (b, k) {
        var on = k === n;
        b.classList.toggle('is-active', on);
        b.setAttribute('aria-selected', on ? 'true' : 'false');
      });
      panels.forEach(function (p, k) {
        p.classList.toggle('is-active', k === n);
        if (k === n) p.removeAttribute('hidden'); else p.setAttribute('hidden', '');
      });
    }
    function stop() { idle = false; if (timer) { clearInterval(timer); timer = null; } }

    tabs.forEach(function (b, k) {
      b.addEventListener('click', function () { stop(); show(k); });
      b.addEventListener('mouseenter', function () { if (fine) { stop(); show(k); } });
      b.addEventListener('keydown', function (e) {
        var d = e.key === 'ArrowRight' || e.key === 'ArrowDown' ? 1 : (e.key === 'ArrowLeft' || e.key === 'ArrowUp' ? -1 : 0);
        if (!d) return;
        e.preventDefault(); stop();
        var n = (k + d + tabs.length) % tabs.length;
        show(n); tabs[n].focus();
      });
    });

    if (!reduce && 'IntersectionObserver' in win) {
      new IntersectionObserver(function (es) {
        es.forEach(function (e) {
          if (e.isIntersecting && idle && !timer) timer = setInterval(function () { show((i + 1) % tabs.length); }, 4600);
          else if (!e.isIntersecting && timer) { clearInterval(timer); timer = null; }
        });
      }, { threshold: 0.3 }).observe(stageEl);
    }
  })();

  /* ── numeric counters ───────────────────────────────────── */
  (function () {
    var els = [].slice.call(doc.querySelectorAll('[data-count]'));
    if (!els.length || !('IntersectionObserver' in win)) return;
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (!e.isIntersecting) return;
        io.unobserve(e.target);
        var el = e.target, to = parseFloat(el.getAttribute('data-count')) || 0;
        if (reduce || to === 0) { el.textContent = to.toLocaleString(); return; }
        var t0 = performance.now(), dur = 1400;
        (function step(t) {
          var k = clamp((t - t0) / dur, 0, 1);
          var eased = 1 - Math.pow(1 - k, 3);
          el.textContent = Math.round(to * eased).toLocaleString();
          if (k < 1) requestAnimationFrame(step);
        })(t0);
      });
    }, { threshold: 0.5 });
    els.forEach(function (el) { io.observe(el); });
  })();

  /* ── live system indicators ─────────────────────────────── */
  (function () {
    var req = doc.querySelector('[data-tick="req"]');
    var lat = doc.querySelector('[data-tick="lat"]');
    var err = doc.querySelector('[data-tick="err"]');
    var hudLat = doc.getElementById('hudLat');
    if (!req && !hudLat) return;
    var base = 1284, seen = false;

    if ('IntersectionObserver' in win && req) {
      new IntersectionObserver(function (e) { seen = e[0].isIntersecting; }, { threshold: 0 })
        .observe(req.closest('.arch__panel') || req);
    } else seen = true;

    setInterval(function () {
      if (doc.hidden) return;
      if (seen && req) {
        base = clamp(base + Math.round((Math.random() - 0.48) * 90), 940, 1720);
        req.textContent = base.toLocaleString();
        if (lat) lat.textContent = (118 + Math.round(Math.random() * 48)) + ' ms';
        if (err) err.textContent = (Math.random() < 0.75 ? '0.01%' : '0.02%');
      }
      if (hudLat) hudLat.textContent = (14 + Math.round(Math.random() * 9)) + ' ms';
    }, 2200);
  })();

  /* ── smooth in-page navigation (respects reduced motion) ── */
  doc.addEventListener('click', function (e) {
    var a = e.target.closest ? e.target.closest('a[href^="#"]') : null;
    if (!a) return;
    var id = a.getAttribute('href');
    if (id === '#' || id.length < 2) return;
    var target = doc.getElementById(id.slice(1));
    if (!target) return;
    e.preventDefault();
    target.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'start' });
    history.replaceState(null, '', id);
  });

  request();
  win.TECHBISS = { onScroll: onScroll, reduce: reduce, fine: fine, clamp: clamp, lerp: lerp };
})();
