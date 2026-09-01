/* ============================================================================
   iamtomley — Premium 2026 Redesign · interactions
   Depends on games-data.js (globals: GAMES, gHTML)
   ----------------------------------------------------------------------------
   All original behaviour preserved: game grid + filter + search + pagination,
   blob-iframe modal with fullscreen, project slider (keyboard/touch), animated
   stat counters, spotlight cards, custom cursor, section reveal.
   Added: theme toggle, scroll progress, staggered reveal, ripple, skeleton
   loading, toast, mobile menu, magnetic buttons, hero parallax, back-to-top.
   ========================================================================== */
(function () {
  'use strict';

  const $  = (s, c = document) => c.querySelector(s);
  const $$ = (s, c = document) => Array.from(c.querySelectorAll(s));
  const prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const canHover = window.matchMedia('(hover: hover) and (pointer: fine)').matches;

  /* ── Theme toggle ──────────────────────────────────────────────────────── */
  (function theme() {
    const root = document.documentElement;
    const saved = localStorage.getItem('theme');
    if (saved) root.setAttribute('data-theme', saved);
    const meta = $('#theme-color-meta');
    const sync = () => { if (meta) meta.content = root.getAttribute('data-theme') === 'light' ? '#f5f7fc' : '#05060d'; };
    sync();
    const btn = $('#themeToggle');
    if (btn) btn.addEventListener('click', () => {
      const next = root.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
      root.setAttribute('data-theme', next);
      localStorage.setItem('theme', next);
      sync();
    });
  })();

  /* ── Background theme picker (visitor choice, saved locally) ───────────── */
  (function bgTheme() {
    const root = document.documentElement;
    const saved = localStorage.getItem('bgtheme');
    const opts = $$('.theme-opt');
    const valid = new Set(opts.map(o => o.dataset.bg));
    if (saved && valid.has(saved)) root.setAttribute('data-bg', saved);

    const current = () => root.getAttribute('data-bg') || 'aurora';
    const markActive = () => opts.forEach(o => {
      const on = o.dataset.bg === current();
      o.classList.toggle('active', on);
      o.setAttribute('aria-checked', on ? 'true' : 'false');
    });
    markActive();

    const panel = $('#themePanel');
    const toggle = $('#bgToggle');
    if (!panel || !toggle) return;

    const setOpen = (v) => {
      panel.classList.toggle('open', v);
      toggle.setAttribute('aria-expanded', v ? 'true' : 'false');
    };
    toggle.addEventListener('click', (e) => { e.stopPropagation(); setOpen(!panel.classList.contains('open')); });
    panel.addEventListener('click', (e) => e.stopPropagation());
    document.addEventListener('click', () => setOpen(false));
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') setOpen(false); });

    opts.forEach(o => o.addEventListener('click', () => {
      root.setAttribute('data-bg', o.dataset.bg);
      localStorage.setItem('bgtheme', o.dataset.bg);
      markActive();
    }));
  })();

  /* ── Scroll progress + header state + back-to-top ──────────────────────── */
  (function scroll() {
    const bar = $('#scrollProgress');
    const header = $('#header');
    const toTop = $('#toTop');
    let ticking = false;
    const onScroll = () => {
      const st = window.scrollY;
      const h = document.documentElement.scrollHeight - window.innerHeight;
      if (bar) bar.style.width = (h > 0 ? (st / h) * 100 : 0) + '%';
      if (header) header.classList.toggle('scrolled', st > 12);
      if (toTop) toTop.classList.toggle('show', st > 600);
      ticking = false;
    };
    window.addEventListener('scroll', () => {
      if (!ticking) { requestAnimationFrame(onScroll); ticking = true; }
    }, { passive: true });
    onScroll();
    if (toTop) toTop.addEventListener('click', () =>
      window.scrollTo({ top: 0, behavior: prefersReduced ? 'auto' : 'smooth' }));
  })();

  /* ── Mobile menu ───────────────────────────────────────────────────────── */
  (function mobileMenu() {
    const menu = $('#mobileMenu');
    const open = $('#navToggle');
    const close = $('#mmClose');
    if (!menu) return;
    const setOpen = (v) => {
      menu.classList.toggle('open', v);
      document.body.style.overflow = v ? 'hidden' : '';
    };
    if (open) open.addEventListener('click', () => setOpen(true));
    if (close) close.addEventListener('click', () => setOpen(false));
    $$('a', menu).forEach(a => a.addEventListener('click', () => setOpen(false)));
    document.addEventListener('keydown', e => { if (e.key === 'Escape') setOpen(false); });
  })();

  /* ── Nav active link on scroll ─────────────────────────────────────────── */
  (function navSpy() {
    const links = $$('.nav-links a[href^="#"]');
    if (!links.length) return;
    const map = new Map(links.map(l => [l.getAttribute('href').slice(1), l]));
    const obs = new IntersectionObserver(entries => {
      entries.forEach(e => {
        const link = map.get(e.target.id);
        if (link && e.isIntersecting) {
          links.forEach(l => l.classList.remove('active'));
          link.classList.add('active');
        }
      });
    }, { rootMargin: '-45% 0px -50% 0px' });
    ['projects', 'games', 'contact'].forEach(id => { const el = $('#' + id); if (el) obs.observe(el); });
  })();

  /* ── Scroll reveal (sections + staggered groups) ───────────────────────── */
  (function reveal() {
    const targets = $$('.reveal, [data-stagger]');
    if (prefersReduced) { targets.forEach(t => t.classList.add('in')); return; }
    const obs = new IntersectionObserver(entries => {
      entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('in'); obs.unobserve(e.target); } });
    }, { threshold: 0.12 });
    targets.forEach(t => obs.observe(t));
  })();

  /* ── Animated stat counters ────────────────────────────────────────────── */
  (function counters() {
    const obs = new IntersectionObserver(entries => {
      entries.forEach(e => {
        if (!e.isIntersecting) return;
        $$('.stat-num[data-target]', e.target).forEach(el => {
          const target = parseInt(el.dataset.target, 10);
          const suffix = el.dataset.suffix || '';
          if (prefersReduced) { el.textContent = target + suffix; return; }
          const dur = 1400, t0 = performance.now();
          (function step(t) {
            const p = Math.min((t - t0) / dur, 1), ease = 1 - Math.pow(1 - p, 3);
            el.textContent = Math.round(ease * target) + suffix;
            if (p < 1) requestAnimationFrame(step);
          })(t0);
        });
        obs.unobserve(e.target);
      });
    }, { threshold: 0.35 });
    $$('.stats').forEach(s => obs.observe(s));
  })();

  /* ── Ripple on buttons ─────────────────────────────────────────────────── */
  (function ripple() {
    if (prefersReduced) return;
    $$('.btn').forEach(btn => {
      btn.addEventListener('click', e => {
        const r = btn.getBoundingClientRect();
        const size = Math.max(r.width, r.height);
        const span = document.createElement('span');
        span.className = 'ripple';
        span.style.width = span.style.height = size + 'px';
        span.style.left = (e.clientX - r.left - size / 2) + 'px';
        span.style.top = (e.clientY - r.top - size / 2) + 'px';
        btn.appendChild(span);
        setTimeout(() => span.remove(), 650);
      });
    });
  })();

  /* ── Spotlight + tilt on cards ─────────────────────────────────────────── */
  (function spotlight() {
    if (!canHover) return;
    $$('.card').forEach(card => {
      card.addEventListener('mousemove', e => {
        const r = card.getBoundingClientRect();
        card.style.setProperty('--mx', ((e.clientX - r.left) / r.width * 100).toFixed(1) + '%');
        card.style.setProperty('--my', ((e.clientY - r.top) / r.height * 100).toFixed(1) + '%');
      });
    });
  })();

  /* ── Hero parallax ─────────────────────────────────────────────────────── */
  (function parallax() {
    const photo = $('#heroPhoto');
    if (!photo || !canHover || prefersReduced) return;
    let raf;
    window.addEventListener('mousemove', e => {
      cancelAnimationFrame(raf);
      raf = requestAnimationFrame(() => {
        const dx = (e.clientX / window.innerWidth - 0.5);
        const dy = (e.clientY / window.innerHeight - 0.5);
        photo.style.transform = `translate3d(${dx * -18}px, ${dy * -18}px, 0) rotateX(${dy * 4}deg) rotateY(${dx * -4}deg)`;
      });
    }, { passive: true });
  })();

  /* ── Custom cursor ─────────────────────────────────────────────────────── */
  (function cursor() {
    if (!canHover || prefersReduced) return;
    const dot = $('#cursorDot'), ring = $('#cursorRing');
    if (!dot || !ring) return;
    document.body.classList.add('cursor-on');
    let mx = 0, my = 0, rx = 0, ry = 0;
    document.addEventListener('mousemove', e => {
      mx = e.clientX; my = e.clientY;
      dot.style.transform = `translate(${mx}px, ${my}px)`;
    });
    (function loop() {
      rx += (mx - rx) * 0.16; ry += (my - ry) * 0.16;
      ring.style.transform = `translate(${rx}px, ${ry}px)`;
      requestAnimationFrame(loop);
    })();
    const hoverIn = () => ring.classList.add('is-hover');
    const hoverOut = () => ring.classList.remove('is-hover');
    document.addEventListener('mouseover', e => { if (e.target.closest('a, button, input, .card, .game-card')) hoverIn(); });
    document.addEventListener('mouseout', e => { if (e.target.closest('a, button, input, .card, .game-card')) hoverOut(); });
    document.addEventListener('mouseleave', () => { dot.style.opacity = ring.style.opacity = '0'; });
    document.addEventListener('mouseenter', () => { dot.style.opacity = ring.style.opacity = '1'; });
  })();

  /* ── Toast helper ──────────────────────────────────────────────────────── */
  function toast(msg) {
    const wrap = $('#toasts');
    if (!wrap) return;
    const el = document.createElement('div');
    el.className = 'toast';
    el.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><polyline points="20 6 9 17 4 12"/></svg><span></span>`;
    el.querySelector('span').textContent = msg;
    wrap.appendChild(el);
    setTimeout(() => { el.classList.add('out'); setTimeout(() => el.remove(), 320); }, 2200);
  }

  /* Copy email */
  $$('[data-copy]').forEach(el => {
    el.addEventListener('click', e => {
      const val = el.getAttribute('data-copy');
      if (navigator.clipboard && val) {
        e.preventDefault();
        navigator.clipboard.writeText(val).then(() => toast('Email copied to clipboard'))
          .catch(() => { window.location.href = 'mailto:' + val; });
      }
    });
  });

  /* ── Project slider ────────────────────────────────────────────────────── */
  (function slider() {
    const track = $('#sliderTrack');
    if (!track) return;
    const slides = $$('.slide', track);
    const total = slides.length;
    const fill = $('#progressFill');
    const activeEl = $('#slideActive');
    const totalEl = $('#slideTotal');
    if (totalEl) totalEl.textContent = total;
    let cur = 0;
    const modal = $('#gameModal');
    function goTo(i) {
      cur = (i + total) % total;
      track.style.transform = `translateX(-${cur * 100}%)`;
      if (activeEl) activeEl.textContent = cur + 1;
      if (fill) fill.style.width = ((cur + 1) / total * 100) + '%';
    }
    const next = $('#btnNext'), prev = $('#btnPrev');
    if (next) next.addEventListener('click', () => goTo(cur + 1));
    if (prev) prev.addEventListener('click', () => goTo(cur - 1));
    let sx = 0;
    track.addEventListener('touchstart', e => { sx = e.touches[0].clientX; }, { passive: true });
    track.addEventListener('touchend', e => {
      const d = sx - e.changedTouches[0].clientX;
      if (Math.abs(d) > 50) goTo(d > 0 ? cur + 1 : cur - 1);
    });
    document.addEventListener('keydown', e => {
      if (modal && modal.classList.contains('open')) return;
      if (e.key === 'ArrowRight') goTo(cur + 1);
      if (e.key === 'ArrowLeft') goTo(cur - 1);
    });
    goTo(0);
  })();

  /* ── Games: render + filter + search + pagination ──────────────────────── */
  const games = (function gamesModule() {
    const grid = $('#gamesGrid');
    const GAMES = Array.isArray(window.GAMES) ? window.GAMES : [];
    if (!grid || !GAMES.length) return null;

    const PER_PAGE = 8;
    const CATS = window.GAME_CATS || {};
    let filtered = [...GAMES];
    let page = 1, catFilter = 'all', searchQ = '';

    // Titles, categories and cover paths are admin-entered — escape before
    // they go anywhere near innerHTML.
    const esc = (s) => String(s == null ? '' : s).replace(/[&<>"']/g,
      c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const catLabel = (k) => CATS[k] || k;
    const keyOf = (g) => String(g.row != null ? 'r' + g.row : 'b' + g.id);

    function skeletons(n) {
      grid.innerHTML = Array.from({ length: n }, () =>
        `<div class="game-card skeleton" aria-hidden="true" style="border:1px solid var(--border)"></div>`).join('');
    }

    function render() {
      const totalPages = Math.max(1, Math.ceil(filtered.length / PER_PAGE));
      page = Math.min(Math.max(page, 1), totalPages);
      const pager = $('#pager');

      if (!filtered.length) {
        grid.innerHTML = '<div class="games-empty">No games found 🎮 — try another search.</div>';
        if (pager) pager.style.display = 'none';
        return;
      }

      const items = filtered.slice((page - 1) * PER_PAGE, page * PER_PAGE);
      grid.style.opacity = '0';
      grid.innerHTML = items.map(g => `
        <button class="game-card" data-key="${esc(keyOf(g))}" aria-label="Play ${esc(g.title)}">
          <span class="game-thumb${g.cover ? ' has-cover' : ''}">
            ${g.cover
              ? `<img class="game-cover" src="${esc(g.cover)}" alt="" loading="lazy" decoding="async">`
              : `<span aria-hidden="true">${esc(g.emoji)}</span>`}
            <span class="game-play"><svg viewBox="0 0 24 24" fill="currentColor"><polygon points="5 3 19 12 5 21 5 3"/></svg></span>
          </span>
          <span class="game-info">
            <span class="game-title">${esc(g.title)}</span>
            <span class="game-badge ${esc(g.cat)}">${esc(catLabel(g.cat))}</span>
          </span>
        </button>`).join('');
      requestAnimationFrame(() => { grid.style.opacity = '1'; });

      const byKey = new Map(items.map(g => [keyOf(g), g]));
      $$('.game-card', grid).forEach(card => card.addEventListener('click', () => {
        const g = byKey.get(card.dataset.key);
        if (g) openGame(g);
      }));

      // pagination UI
      if (pager) {
        pager.style.display = totalPages <= 1 ? 'none' : 'flex';
        $('#pgCur').textContent = page;
        $('#pgTot').textContent = totalPages;
        $('#pgPrev').disabled = page === 1;
        $('#pgNext').disabled = page === totalPages;
        const dots = $('#pgDots');
        dots.innerHTML = Array.from({ length: totalPages }, (_, i) =>
          `<span class="pg-dot${i + 1 === page ? ' on' : ''}" data-p="${i + 1}" role="button" tabindex="0" aria-label="Page ${i + 1}"></span>`).join('');
        $$('.pg-dot', dots).forEach(d => {
          const go = () => { page = +d.dataset.p; render(); };
          d.addEventListener('click', go);
          d.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); go(); } });
        });
      }
    }

    function applyFilter() {
      filtered = GAMES.filter(g =>
        (catFilter === 'all' || g.cat === catFilter) &&
        g.title.toLowerCase().includes(searchQ));
      page = 1;
      render();
    }

    $$('.filter-btn').forEach(btn => btn.addEventListener('click', () => {
      $$('.filter-btn').forEach(b => { b.classList.remove('active'); b.setAttribute('aria-pressed', 'false'); });
      btn.classList.add('active'); btn.setAttribute('aria-pressed', 'true');
      catFilter = btn.dataset.cat;
      applyFilter();
    }));

    const searchEl = $('#gameSearch');
    if (searchEl) searchEl.addEventListener('input', e => { searchQ = e.target.value.toLowerCase().trim(); applyFilter(); });

    const pgPrev = $('#pgPrev'), pgNext = $('#pgNext');
    if (pgPrev) pgPrev.addEventListener('click', () => { page--; render(); });
    if (pgNext) pgNext.addEventListener('click', () => { page++; render(); });

    // initial skeleton → content
    skeletons(PER_PAGE);
    setTimeout(render, prefersReduced ? 0 : 420);
    return { render };
  })();

  /* ── Game modal (blob iframe, fullscreen) ──────────────────────────────── */
  const modal = $('#gameModal');
  const gFrame = $('#gameFrame');
  const mLoader = $('#modalLoader');
  let lastFocus = null;

  let frameWatchdog = null;

  /**
   * Open a game. Where its code lives depends on how it was added in the admin:
   *   builtin — the shipped code in games-data.js, wrapped in a blob URL
   *   html    — HTML pasted into the admin, served (sandboxed) by game.php
   *   url     — a game hosted elsewhere, loaded straight into the frame
   */
  function openGame(g) {
    if (!modal || !g) return;
    const cats = window.GAME_CATS || {};
    const src = g.src || 'builtin';

    lastFocus = document.activeElement;
    $('#modalName').textContent = g.title || 'Game';
    $('#modalCat').textContent = cats[g.cat] || g.cat || '';
    $('#modalEmoji').textContent = g.emoji || '🎮';
    if (mLoader) { mLoader.classList.remove('hide'); setLoaderMessage('Loading game…'); }
    clearTimeout(frameWatchdog);
    gFrame.src = '';
    modal.classList.add('open');
    document.body.style.overflow = 'hidden';
    const closeBtn = $('#modalClose');
    if (closeBtn) closeBtn.focus();

    // A game that lives elsewhere can be opened in its own tab as well.
    const openBtn = $('#modalOpen');
    const away = src === 'url' ? g.url : (src === 'html' ? g.play : '');
    if (openBtn) {
      openBtn.hidden = !away;
      if (away) openBtn.href = away;
    }

    gFrame.onload = () => { clearTimeout(frameWatchdog); if (mLoader) mLoader.classList.add('hide'); };

    if (src === 'url' && g.url) {
      gFrame.src = g.url;
      // Some sites refuse to be framed and fail silently — say so rather than
      // leaving the visitor watching a spinner forever.
      frameWatchdog = setTimeout(() => {
        setLoaderMessage('This game would not load here — use the ↗ button to open it in a new tab.');
      }, 7000);
      return;
    }

    if (src === 'html' && g.play) {
      gFrame.src = g.play;
      return;
    }

    // Built-in game: build the page in the browser and hand it to the frame.
    const id = +g.id;
    const extra = (window.EXTRA_GAMES && window.EXTRA_GAMES[id]) ? window.EXTRA_GAMES[id] : null;
    const html = extra || (typeof gHTML === 'function' ? gHTML(id) : '');
    if (!html) { setLoaderMessage('This game is not available.'); return; }
    gFrame.src = URL.createObjectURL(new Blob([html], { type: 'text/html' }));
  }

  function setLoaderMessage(text) {
    const label = mLoader && mLoader.querySelector('.spinner-label');
    if (label) label.textContent = text;
  }

  function closeModal() {
    if (!modal) return;
    clearTimeout(frameWatchdog);
    modal.classList.remove('open', 'fs');
    document.body.style.overflow = '';
    const openBtn = $('#modalOpen');
    if (openBtn) openBtn.hidden = true;
    setTimeout(() => {
      if (gFrame.src.startsWith('blob:')) URL.revokeObjectURL(gFrame.src);
      gFrame.src = '';
      setLoaderMessage('Loading game…');
    }, 350);
    if (lastFocus) lastFocus.focus();
  }

  if (modal) {
    $('#modalClose').addEventListener('click', closeModal);
    modal.addEventListener('click', e => { if (e.target === modal) closeModal(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && modal.classList.contains('open')) closeModal(); });

    const fsBtn = $('#modalFs');
    const expand = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"/></svg>`;
    const shrink = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 3v3a2 2 0 0 1-2 2H3m18 0h-3a2 2 0 0 1-2-2V3m0 18v-3a2 2 0 0 1 2-2h3M3 16h3a2 2 0 0 1 2 2v3"/></svg>`;
    if (fsBtn) fsBtn.addEventListener('click', () => {
      modal.classList.toggle('fs');
      fsBtn.innerHTML = modal.classList.contains('fs') ? shrink : expand;
    });
  }

  /* expose for inline safety (kept API compatible) */
  window.openGame = openGame;
})();
