/* ============================================================
   TECHBISS · canvas visualisations
   Hero ecosystem · system architecture · CTA field
   ============================================================ */
(function () {
  'use strict';

  var win = window, doc = document;
  var reduce = win.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var clamp = function (v, a, b) { return v < a ? a : v > b ? b : v; };
  var lerp = function (a, b, t) { return a + (b - a) * t; };
  var TAU = Math.PI * 2;

  function fitCanvas(cv, w, h) {
    var dpr = Math.min(win.devicePixelRatio || 1, 2);
    cv.width = Math.max(1, Math.round(w * dpr));
    cv.height = Math.max(1, Math.round(h * dpr));
    var ctx = cv.getContext('2d');
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    return ctx;
  }

  /* visibility-gated animation loop */
  function loop(el, draw) {
    var running = false, raf = 0;
    function frame(t) { draw(t); raf = requestAnimationFrame(frame); }
    function start() { if (running) return; running = true; raf = requestAnimationFrame(frame); }
    function stop() { running = false; cancelAnimationFrame(raf); }
    if ('IntersectionObserver' in win) {
      new IntersectionObserver(function (e) { e[0].isIntersecting ? start() : stop(); }, { threshold: 0 }).observe(el);
    } else start();
    doc.addEventListener('visibilitychange', function () { doc.hidden ? stop() : start(); });
    return { start: start, stop: stop };
  }

  /* ══════════════════════════════════════════════════════════
     01 · HERO ECOSYSTEM
     Business → Domain → Website → App → Hosting → Email →
     Security → Payments → Growth, orbiting the TECHBISS core.
     ══════════════════════════════════════════════════════════ */
  (function () {
    var wrap = doc.getElementById('viz');
    var cv = doc.getElementById('vizCanvas');
    var host = doc.getElementById('vizNodes');
    var hudNode = doc.getElementById('hudNode');
    if (!wrap || !cv || !host) return;

    var els = [].slice.call(host.children);
    var core = null, ring = [];
    els.forEach(function (el, i) {
      el.style.setProperty('--i', i);
      var n = { el: el, name: el.querySelector('.vnode__label').textContent, x: 0, y: 0, bx: 0, by: 0, depth: 0, ph: Math.random() * TAU };
      if (el.getAttribute('data-ring') === '0') core = n; else ring.push(n);
    });
    if (!core) return;

    var W = 0, H = 0, ctx = null;
    var mx = 0, my = 0, tmx = 0, tmy = 0;      // -1 → 1
    var pulses = [], dust = [];

    function layout() {
      var r = wrap.getBoundingClientRect();
      W = r.width; H = r.height;
      if (W < 2 || H < 2) return;
      ctx = fitCanvas(cv, W, H);

      var small = W < 620;
      var cx = W * (small ? 0.5 : 0.54), cy = H * 0.5;
      core.bx = cx; core.by = cy; core.depth = 0.35;

      var rx = Math.min(W * (small ? 0.33 : 0.35), 290);
      var ry = Math.min(H * 0.41, small ? 190 : 252);
      ring.forEach(function (n, i) {
        var a = -Math.PI / 2 + 0.30 + (i / ring.length) * TAU;
        n.bx = cx + Math.cos(a) * rx;
        n.by = cy + Math.sin(a) * ry;
        n.depth = 0.55 + (Math.sin(a) + 1) * 0.35;   // lower nodes feel nearer
        n.a = a;
      });

      var count = small ? 26 : 54;
      if (dust.length !== count) {
        dust = [];
        for (var i = 0; i < count; i++) dust.push({
          x: Math.random() * W, y: Math.random() * H,
          vx: (Math.random() - 0.5) * 0.09, vy: (Math.random() - 0.5) * 0.09,
          r: Math.random() * 1.3 + 0.35, a: Math.random() * 0.5 + 0.12
        });
      }
      place(0);
      if (reduce) draw(0);
    }

    function place(t) {
      var all = [core].concat(ring);
      for (var i = 0; i < all.length; i++) {
        var n = all[i];
        var float = reduce ? 0 : Math.sin(t * 0.0006 + n.ph) * (n === core ? 2.5 : 5.5);
        var x = n.bx + mx * 20 * n.depth;
        var y = n.by + my * 16 * n.depth + float;

        /* Labels are editable, so their width is unknown until measured.
           Keep every pill inside the box rather than letting a long service
           name hang off the edge on a narrow phone. */
        var hw = (n.el.offsetWidth || 90) / 2 + 4;
        var hh = (n.el.offsetHeight || 34) / 2 + 4;
        if (W > hw * 2) { x = Math.max(hw, Math.min(W - hw, x)); }
        if (H > hh * 2) { y = Math.max(hh, Math.min(H - hh, y)); }

        n.x = x; n.y = y;
        n.el.style.setProperty('--x', x.toFixed(1) + 'px');
        n.el.style.setProperty('--y', y.toFixed(1) + 'px');
        n.el.style.setProperty('--s', (1 + (n.depth - 0.6) * 0.055).toFixed(3));
      }
    }

    // journey pulses travel the ring in order, plus core spokes
    function seedPulses() {
      pulses = [];
      for (var i = 0; i < ring.length; i++) pulses.push({ type: 'ring', i: i, t: -i * 0.11, sp: 0.0038 });
      for (var j = 0; j < ring.length; j += 2) pulses.push({ type: 'spoke', i: j, t: -Math.random(), sp: 0.0026 });
    }
    seedPulses();

    function edge(ax, ay, bx, by, alpha, width) {
      ctx.beginPath();
      ctx.moveTo(ax, ay); ctx.lineTo(bx, by);
      ctx.strokeStyle = 'rgba(143,176,255,' + alpha + ')';
      ctx.lineWidth = width;
      ctx.stroke();
    }

    var nearest = -1;
    function draw(t) {
      if (!ctx || W < 2) return;
      mx = lerp(mx, tmx, 0.06); my = lerp(my, tmy, 0.06);
      place(t);
      ctx.clearRect(0, 0, W, H);

      // ambient dust
      for (var d = 0; d < dust.length; d++) {
        var p = dust[d];
        if (!reduce) {
          p.x += p.vx + mx * 0.22; p.y += p.vy + my * 0.18;
          if (p.x < -10) p.x = W + 10; if (p.x > W + 10) p.x = -10;
          if (p.y < -10) p.y = H + 10; if (p.y > H + 10) p.y = -10;
        }
        ctx.beginPath();
        ctx.arc(p.x, p.y, p.r, 0, TAU);
        ctx.fillStyle = 'rgba(180,200,255,' + p.a * 0.5 + ')';
        ctx.fill();
      }

      // spokes core → node
      for (var i = 0; i < ring.length; i++) {
        var n = ring[i];
        var hot = i === nearest;
        edge(core.x, core.y, n.x, n.y, hot ? 0.34 : 0.13, hot ? 1.1 : 0.8);
      }
      // ring path (the journey)
      ctx.beginPath();
      for (var k = 0; k <= ring.length; k++) {
        var a = ring[k % ring.length];
        k === 0 ? ctx.moveTo(a.x, a.y) : ctx.lineTo(a.x, a.y);
      }
      ctx.strokeStyle = 'rgba(143,176,255,.10)';
      ctx.lineWidth = 1; ctx.stroke();

      // travelling pulses
      if (!reduce) {
        for (var q = 0; q < pulses.length; q++) {
          var pu = pulses[q];
          pu.t += pu.sp;
          if (pu.t > 1.25) { pu.t = -0.25; if (pu.type === 'ring') pu.i = (pu.i + 1) % ring.length; }
          if (pu.t < 0) continue;
          var tt = clamp(pu.t, 0, 1), ax, ay, bx, by;
          if (pu.type === 'ring') {
            var f = ring[pu.i], g = ring[(pu.i + 1) % ring.length];
            ax = f.x; ay = f.y; bx = g.x; by = g.y;
          } else {
            ax = core.x; ay = core.y; bx = ring[pu.i].x; by = ring[pu.i].y;
          }
          var px = lerp(ax, bx, tt), py = lerp(ay, by, tt);
          var fade = Math.sin(tt * Math.PI);
          var grd = ctx.createRadialGradient(px, py, 0, px, py, 9);
          grd.addColorStop(0, 'rgba(190,212,255,' + (0.85 * fade) + ')');
          grd.addColorStop(1, 'rgba(143,176,255,0)');
          ctx.beginPath(); ctx.arc(px, py, 9, 0, TAU); ctx.fillStyle = grd; ctx.fill();
          ctx.beginPath(); ctx.arc(px, py, 1.7, 0, TAU); ctx.fillStyle = 'rgba(226,236,255,' + fade + ')'; ctx.fill();
        }
      }

      // core halo
      var cg = ctx.createRadialGradient(core.x, core.y, 0, core.x, core.y, 130);
      cg.addColorStop(0, 'rgba(78,118,232,.20)');
      cg.addColorStop(1, 'rgba(78,118,232,0)');
      ctx.beginPath(); ctx.arc(core.x, core.y, 130, 0, TAU); ctx.fillStyle = cg; ctx.fill();
    }

    layout();
    if ('ResizeObserver' in win) new ResizeObserver(layout).observe(wrap);
    else win.addEventListener('resize', layout, { passive: true });

    if (win.matchMedia('(hover:hover) and (pointer:fine)').matches) {
      win.addEventListener('mousemove', function (e) {
        var r = wrap.getBoundingClientRect();
        tmx = clamp(((e.clientX - r.left) / r.width - 0.5) * 2, -1.6, 1.6);
        tmy = clamp(((e.clientY - r.top) / r.height - 0.5) * 2, -1.6, 1.6);
        doc.documentElement.style.setProperty('--px', tmx.toFixed(3));
        doc.documentElement.style.setProperty('--py', tmy.toFixed(3));

        // nearest node → HUD + highlight
        var best = -1, bd = 150 * 150;
        for (var i = 0; i < ring.length; i++) {
          var dx = e.clientX - (r.left + ring[i].x), dy = e.clientY - (r.top + ring[i].y);
          var dd = dx * dx + dy * dy;
          if (dd < bd) { bd = dd; best = i; }
        }
        if (best !== nearest) {
          if (nearest >= 0) ring[nearest].el.classList.remove('is-near');
          nearest = best;
          if (best >= 0) {
            ring[best].el.classList.add('is-near');
            if (hudNode) hudNode.textContent = ring[best].name.toLowerCase();
          } else if (hudNode) hudNode.textContent = 'synced';
        }
      }, { passive: true });
    }

    wrap.classList.add('js-anim');
    requestAnimationFrame(function () { wrap.classList.add('is-ready'); });
    if (reduce) { draw(0); } else { loop(wrap, draw); }
  })();

  /* ══════════════════════════════════════════════════════════
     04 · SYSTEM ARCHITECTURE
     ══════════════════════════════════════════════════════════ */
  (function () {
    var wrap = doc.getElementById('archDiagram');
    var cv = doc.getElementById('archCanvas');
    var host = doc.getElementById('archNodes');
    var section = doc.querySelector('.arch');
    if (!wrap || !cv || !host) return;

    var nodes = [].slice.call(host.children).map(function (el, i) {
      el.style.setProperty('--i', i);
      return {
        el: el,
        key: el.getAttribute('data-arch') || ('n' + i),
        layer: Math.max(0, Math.min(3, parseInt(el.getAttribute('data-layer'), 10) || 0)),
        x: 0, y: 0
      };
    });
    if (!nodes.length) return;

    var byLayer = [[], [], [], []];
    nodes.forEach(function (n) { byLayer[n.layer].push(n); });

    /**
     * Edges are derived from the layers, never from hardcoded names, so the
     * diagram keeps working whatever nodes are configured: the source feeds
     * the core, the core fans out to the services, each service drops to the
     * infrastructure node beneath it, and the infrastructure row is chained.
     */
    function buildEdges() {
      var e = [];
      var src = byLayer[0], core = byLayer[1], svc = byLayer[2], infra = byLayer[3];
      var hub = core[0] || svc[0] || src[0];

      src.forEach(function (s) { if (hub && s !== hub) e.push([s, hub]); });
      if (hub) { svc.forEach(function (s) { e.push([hub, s]); }); }
      else if (src.length) { svc.forEach(function (s) { e.push([src[0], s]); }); }

      if (infra.length) {
        svc.forEach(function (s, i) {
          var target = infra[Math.min(i, infra.length - 1)];
          if (target) { e.push([s, target]); }
        });
        for (var k = 0; k < infra.length - 1; k++) { e.push([infra[k], infra[k + 1]]); }
        if (!svc.length && hub) { e.push([hub, infra[0]]); }
      }
      return e;
    }
    var EDGES = buildEdges();

    var W = 0, H = 0, ctx = null, packets = [];

    function layout() {
      var r = wrap.getBoundingClientRect();
      W = r.width; H = r.height;
      if (W < 2 || H < 2) return;
      ctx = fitCanvas(cv, W, H);

      var narrow = W < 760;
      var padX = Math.max(narrow ? 14 : 24, W * (narrow ? 0.03 : 0.055));
      var span = W - padX * 2;

      /* Only the layers that actually have nodes take up vertical room. */
      var used = [0, 1, 2, 3].filter(function (l) { return byLayer[l].length > 0; });
      var rowY = {};
      used.forEach(function (l, i) {
        rowY[l] = H * (used.length === 1 ? 0.5 : (0.11 + (0.74 * i) / (used.length - 1)));
      });

      function place(list, y, forceRows) {
        if (!list.length) { return; }
        if (list.length === 1) { list[0].x = W / 2; list[0].y = y; return; }
        if (narrow && (forceRows || list.length > 2)) {
          /* Two columns on a phone rather than a squeezed single row. */
          var col = [padX + span * 0.26, padX + span * 0.74];
          var rows = Math.ceil(list.length / 2);
          var band = H * 0.13;
          list.forEach(function (n, i) {
            n.x = col[i % 2];
            n.y = y + (Math.floor(i / 2) - (rows - 1) / 2) * (band / Math.max(1, rows - 1) * (rows > 1 ? 1 : 0));
          });
          return;
        }
        list.forEach(function (n, i) { n.x = padX + span * ((i + 0.5) / list.length); n.y = y; });
      }

      place(byLayer[0], rowY[0] || H * 0.10);
      place(byLayer[1], rowY[1] || H * 0.34);
      place(byLayer[2], rowY[2] || H * 0.63, true);
      place(byLayer[3], rowY[3] || H * 0.88, true);

      nodes.forEach(function (n) {
        n.el.style.setProperty('--x', n.x.toFixed(1) + 'px');
        n.el.style.setProperty('--y', n.y.toFixed(1) + 'px');
      });

      if (packets.length !== EDGES.length) {
        packets = EDGES.map(function (e, i) { return { e: i, t: -(i * 0.13) % 1, sp: 0.0035 + (i % 3) * 0.0007 }; });
      }
      if (reduce) draw(0);
    }

    var hot = null;
    function isHot(e) { return hot && (e[0].key === hot || e[1].key === hot); }

    function curve(a, b) {
      var mid = (a.y + b.y) / 2;
      ctx.beginPath();
      ctx.moveTo(a.x, a.y);
      if (Math.abs(a.y - b.y) < 12) ctx.lineTo(b.x, b.y);
      else ctx.bezierCurveTo(a.x, mid, b.x, mid, b.x, b.y);
      ctx.stroke();
    }
    function pointOn(a, b, t) {
      if (Math.abs(a.y - b.y) < 12) return { x: lerp(a.x, b.x, t), y: lerp(a.y, b.y, t) };
      var mid = (a.y + b.y) / 2, u = 1 - t;
      return {
        x: u * u * u * a.x + 3 * u * u * t * a.x + 3 * u * t * t * b.x + t * t * t * b.x,
        y: u * u * u * a.y + 3 * u * u * t * mid + 3 * u * t * t * mid + t * t * t * b.y
      };
    }

    function draw(t) {
      if (!ctx || W < 2) return;
      ctx.clearRect(0, 0, W, H);

      for (var i = 0; i < EDGES.length; i++) {
        var e = EDGES[i], a = e[0], b = e[1];
        if (!a || !b) continue;
        var on = isHot(e);
        ctx.strokeStyle = on ? 'rgba(143,176,255,.55)' : 'rgba(143,176,255,.17)';
        ctx.lineWidth = on ? 1.5 : 1;
        curve(a, b);
      }

      if (!reduce) {
        for (var q = 0; q < packets.length; q++) {
          var pk = packets[q];
          pk.t += pk.sp;
          if (pk.t > 1) pk.t = -0.1;
          if (pk.t < 0) continue;
          var ed = EDGES[pk.e], na = ed[0], nb = ed[1];
          if (!na || !nb) continue;
          var p = pointOn(na, nb, pk.t);
          var fade = Math.sin(clamp(pk.t, 0, 1) * Math.PI);
          var g = ctx.createRadialGradient(p.x, p.y, 0, p.x, p.y, 8);
          g.addColorStop(0, 'rgba(200,220,255,' + (0.8 * fade) + ')');
          g.addColorStop(1, 'rgba(143,176,255,0)');
          ctx.beginPath(); ctx.arc(p.x, p.y, 8, 0, TAU); ctx.fillStyle = g; ctx.fill();
          ctx.beginPath(); ctx.arc(p.x, p.y, 1.6, 0, TAU);
          ctx.fillStyle = 'rgba(232,240,255,' + fade + ')'; ctx.fill();
        }
      }
    }

    layout();
    if ('ResizeObserver' in win) new ResizeObserver(layout).observe(wrap);
    else win.addEventListener('resize', layout, { passive: true });

    host.addEventListener('mouseover', function (e) {
      var el = e.target.closest ? e.target.closest('.anode') : null;
      if (!el) return;
      hot = el.getAttribute('data-arch');
      nodes.forEach(function (n) { n.el.classList.toggle('is-hot', n.key === hot); });
    });
    host.addEventListener('mouseleave', function () {
      hot = null; nodes.forEach(function (n) { n.el.classList.remove('is-hot'); });
    });

    if (section) {
      section.classList.add('js-anim');
      var reveal = function () { section.classList.add('is-ready'); };
      if ('IntersectionObserver' in win) {
        var io = new IntersectionObserver(function (es) {
          if (es[0].isIntersecting) { reveal(); io.disconnect(); }
        }, { threshold: 0, rootMargin: '0px 0px -5% 0px' });
        io.observe(section);
        /* Failsafe: a section taller than the viewport, a browser that never
           fires, a restored scroll position — the diagram still fills in. */
        win.setTimeout(reveal, 2500);
      } else {
        reveal();
      }
    }

    if (reduce) draw(0); else loop(wrap, draw);
  })();

  /* ══════════════════════════════════════════════════════════
     09 · CTA FIELD — slow drifting light
     ══════════════════════════════════════════════════════════ */
  (function () {
    var cv = doc.getElementById('ctaCanvas');
    if (!cv || reduce) return;
    var wrap = cv.parentNode, W = 0, H = 0, ctx = null, pts = [];

    function layout() {
      var r = wrap.getBoundingClientRect();
      W = r.width; H = r.height;
      if (W < 2 || H < 2) return;
      ctx = fitCanvas(cv, W, H);
      var n = W < 700 ? 30 : 64;
      pts = [];
      for (var i = 0; i < n; i++) pts.push({
        x: Math.random() * W, y: Math.random() * H,
        vy: -(Math.random() * 0.16 + 0.04), vx: (Math.random() - 0.5) * 0.07,
        r: Math.random() * 1.5 + 0.4, a: Math.random() * 0.45 + 0.1, ph: Math.random() * TAU
      });
    }

    function draw(t) {
      if (!ctx || W < 2) return;
      ctx.clearRect(0, 0, W, H);
      for (var i = 0; i < pts.length; i++) {
        var p = pts[i];
        p.x += p.vx; p.y += p.vy;
        if (p.y < -6) { p.y = H + 6; p.x = Math.random() * W; }
        if (p.x < -6) p.x = W + 6; if (p.x > W + 6) p.x = -6;
        var tw = 0.55 + Math.sin(t * 0.001 + p.ph) * 0.45;
        ctx.beginPath(); ctx.arc(p.x, p.y, p.r, 0, TAU);
        ctx.fillStyle = 'rgba(190,210,255,' + (p.a * tw).toFixed(3) + ')';
        ctx.fill();
      }
    }

    layout();
    if ('ResizeObserver' in win) new ResizeObserver(layout).observe(wrap);
    else win.addEventListener('resize', layout, { passive: true });
    loop(wrap, draw);
  })();
})();
