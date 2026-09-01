/* Shared interaction polish: click ripples, scroll progress bar and topbar
 * elevation. Loaded on every public page; skipped for reduced-motion users. */
(function () {
  'use strict';
  var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  // ---------------------------------------------- theme + calm mode
  // The palette used to be dark-only with no opt-out from the ambient
  // animation. Cycles dark -> light -> high contrast; calm mode is separate so
  // it composes with any of them.
  var THEMES = ['dark', 'light', 'contrast'];
  var root = document.documentElement;
  function currentTheme() {
    return root.getAttribute('data-theme') || 'dark';
  }
  function applyTheme(name) {
    if (name === 'dark') root.removeAttribute('data-theme');
    else root.setAttribute('data-theme', name);
    try { localStorage.setItem('sma_theme', name); } catch (e) {}
    var meta = document.querySelector('meta[name="theme-color"]');
    if (meta) meta.setAttribute('content', name === 'light' ? '#f6f8fb' : '#07090E');
  }
  var themeBtn = document.getElementById('themeToggle');
  if (themeBtn) {
    var label = { dark: 'Dark theme — switch to light', light: 'Light theme — switch to high contrast',
                  contrast: 'High contrast — switch to dark' };
    var glyph = { dark: '◐', light: '☀', contrast: '◑' };
    // Function EXPRESSION, not a declaration - a `function name(){}`
    // sitting directly inside an if-block is illegal in strict mode per
    // spec. V8 (Chrome, Android, Node) accepts it anyway via a legacy
    // leniency the spec does not require; JavaScriptCore (Safari, and by
    // Apple's rules every browser on iOS) rejects it as a hard SyntaxError
    // that fails the ENTIRE FILE to parse - nothing in this script has ever
    // run on an iPhone. Assigning to var sidesteps the rule everywhere.
    var syncThemeBtn = function() {
      var t = currentTheme();
      themeBtn.textContent = glyph[t] || '◐';
      themeBtn.title = label[t] || '';
      themeBtn.setAttribute('aria-label', label[t] || 'Switch colour theme');
    };
    themeBtn.addEventListener('click', function (e) {
      // Shift-click toggles calm mode instead of cycling the palette.
      if (e.shiftKey) {
        var calm = root.getAttribute('data-calm') === '1';
        if (calm) { root.removeAttribute('data-calm'); } else { root.setAttribute('data-calm', '1'); }
        try { localStorage.setItem('sma_calm', calm ? '0' : '1'); } catch (e2) {}
        return;
      }
      applyTheme(THEMES[(THEMES.indexOf(currentTheme()) + 1) % THEMES.length]);
      syncThemeBtn();
    });
    syncThemeBtn();
  }

  // ---------------------------------------------- responsive nav
  // Both index.php and charts.php carried their own copy of this; keeping two
  // copies is how they drifted apart.
  var navBtn = document.getElementById('navToggle');
  var nav = document.getElementById('topNav');
  if (navBtn && nav && !navBtn.dataset.bound) {
    navBtn.dataset.bound = '1';
    // This button used to be hidden above 720px (a JS-enforced belt-and-
    // suspenders on top of the CSS media query, in case a browser was
    // running this file against an older cached stylesheet) because desktop
    // showed every account link inline and the icon had nothing left to do.
    // It now opens the same account panel at every width - see the CSS,
    // which shows .nav-toggle everywhere and collapses .nav-account behind
    // it on desktop the same way it always collapsed behind this icon on a
    // phone - so there is no longer a width past which this button does
    // nothing and needs hiding.
    navBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      var open = nav.classList.toggle('open');
      navBtn.classList.toggle('open', open);
      navBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    document.addEventListener('click', function (e) {
      if (nav.classList.contains('open') && !nav.contains(e.target) && e.target !== navBtn) {
        nav.classList.remove('open');
        navBtn.classList.remove('open');
        navBtn.setAttribute('aria-expanded', 'false');
      }
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && nav.classList.contains('open')) {
        nav.classList.remove('open');
        navBtn.classList.remove('open');
        navBtn.setAttribute('aria-expanded', 'false');
        navBtn.focus();
      }
    });
  }

  // ---------------------------------------------- scroll progress bar
  var bar = document.createElement('div');
  bar.className = 'scroll-progress';
  bar.setAttribute('aria-hidden', 'true');
  document.body.appendChild(bar);
  var topbar = document.querySelector('.topbar');
  var ticking = false;
  function onScroll() {
    if (ticking) return;
    ticking = true;
    requestAnimationFrame(function () {
      ticking = false;
      var doc = document.documentElement;
      var max = doc.scrollHeight - doc.clientHeight;
      bar.style.transform = 'scaleX(' + (max > 0 ? Math.min(1, doc.scrollTop / max) : 0) + ')';
      if (topbar) topbar.classList.toggle('elevated', doc.scrollTop > 8);
    });
  }
  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();

  // ---------------------------------------------- click ripples
  if (!reduced) {
    document.addEventListener('pointerdown', function (e) {
      // One list for both surfaces. The admin panel used to run its own copy
      // of this whole block with its own selectors; folding those two in here
      // is what makes deleting that copy lossless.
      var el = e.target.closest(
        'button, .btn, .admin-link, .tf, .ct-btn, .cta-main, .fav-btn, .fav-chip, summary,'
        + ' .tab, .plan-cta, .sidebar nav a, .settings-tabs a'
      );
      if (!el || el.disabled) return;
      var cs = getComputedStyle(el);
      if (cs.position === 'static') el.style.position = 'relative';
      if (cs.overflow !== 'hidden') el.style.overflow = 'hidden';
      var r = el.getBoundingClientRect();
      var d = Math.max(r.width, r.height) * 2.1;
      var s = document.createElement('span');
      s.className = 'ripple';
      s.style.width = s.style.height = d + 'px';
      s.style.left = (e.clientX - r.left - d / 2) + 'px';
      s.style.top = (e.clientY - r.top - d / 2) + 'px';
      el.appendChild(s);
      s.addEventListener('animationend', function () { s.remove(); });
      setTimeout(function () { s.remove(); }, 700);
    }, { passive: true });
  }

  // ---------------------------------------------- scroll reveals (all pages)
  if (!reduced && 'IntersectionObserver' in window) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (en.isIntersecting) { en.target.classList.add('in'); io.unobserve(en.target); }
      });
    }, { threshold: 0.12 });
    document.querySelectorAll('.reveal').forEach(function (el) { io.observe(el); });
  } else {
    document.querySelectorAll('.reveal').forEach(function (el) { el.classList.add('in'); });
  }

  // ---------------------------------------------- accordion open fade
  document.querySelectorAll('details').forEach(function (d) {
    d.addEventListener('toggle', function () {
      if (d.open && !reduced) {
        d.classList.remove('acc-anim');
        void d.offsetWidth; // restart the animation on re-open
        d.classList.add('acc-anim');
      }
    });
  });

  // ---------------------------------------------- animated stat counters
  // Shared home for what used to be index.php's own inline copy of this -
  // one page had a working counter and every other stat on the site (admin
  // dashboard cards, the track-record headline number) had none, because
  // there was nowhere to reuse it from. data-n carries the target so the
  // element's real text content works with JS disabled or failed; .cnt just
  // opts an element in without a class-name collision with anything else
  // that happens to carry data-n for its own reasons.
  document.querySelectorAll('.cnt[data-n]').forEach(function (el) {
    var target = parseInt(el.getAttribute('data-n'), 10) || 0;
    if (reduced || target === 0 || el.dataset.counted) { el.textContent = target.toLocaleString(); return; }
    el.dataset.counted = '1';
    var t0 = null, dur = 1400;
    function tick(ts) {
      if (!t0) t0 = ts;
      var p = Math.min(1, (ts - t0) / dur);
      var eased = 1 - Math.pow(1 - p, 3);
      el.textContent = Math.round(target * eased).toLocaleString();
      if (p < 1) requestAnimationFrame(tick);
    }
    requestAnimationFrame(tick);
  });

  // ---------------------------------------------- presence: cursor glow + magnetic buttons
  // One delegated, rAF-throttled pointermove listener for every hover-glow
  // and magnetic-pull effect on the page (see the "ALIVE" section of
  // style.css) - each of those reads --mx/--my/--card-mx/--card-my/--pull-x/
  // --pull-y rather than wiring its own listener, which is how one nice
  // cursor effect turns into five competing scroll-jank sources. Skipped
  // entirely with no pointer to react to (touch) or when motion is reduced,
  // matching the CSS's own (hover: hover) and (pointer: fine) gate exactly -
  // an event listener the matching styles can never show is pure waste.
  var finePointer = window.matchMedia && window.matchMedia('(hover: hover) and (pointer: fine)').matches;
  if (finePointer && !reduced) {
    var heroEl = document.querySelector('.hero-split');
    var raf = 0, lastX = 0, lastY = 0;
    function paint() {
      raf = 0;
      if (heroEl) {
        var r = heroEl.getBoundingClientRect();
        if (lastX >= r.left && lastX <= r.right && lastY >= r.top && lastY <= r.bottom) {
          heroEl.style.setProperty('--mx', ((lastX - r.left) / r.width * 100) + '%');
          heroEl.style.setProperty('--my', ((lastY - r.top) / r.height * 100) + '%');
          heroEl.classList.add('warm');
        } else {
          heroEl.classList.remove('warm');
        }
      }
      var card = document.elementFromPoint(lastX, lastY);
      card = card && card.closest ? card.closest('.feat, .price, .step') : null;
      if (card) {
        var cr = card.getBoundingClientRect();
        card.style.setProperty('--card-mx', ((lastX - cr.left) / cr.width * 100) + '%');
        card.style.setProperty('--card-my', ((lastY - cr.top) / cr.height * 100) + '%');
      }
      var btn = document.elementFromPoint(lastX, lastY);
      btn = btn && btn.closest ? btn.closest('.cta-main, .btn-primary') : null;
      document.querySelectorAll('.cta-main, .btn-primary').forEach(function (b) {
        if (b === btn) {
          var br = b.getBoundingClientRect();
          // Pull toward the pointer, clamped to a few px either way - a
          // button that leans is inviting; one that chases the cursor across
          // the screen is a bug report.
          var px = Math.max(-6, Math.min(6, (lastX - (br.left + br.width / 2)) * 0.18));
          var py = Math.max(-4, Math.min(4, (lastY - (br.top + br.height / 2)) * 0.18));
          b.style.setProperty('--pull-x', px.toFixed(1) + 'px');
          b.style.setProperty('--pull-y', py.toFixed(1) + 'px');
        } else if (b.style.getPropertyValue('--pull-x')) {
          b.style.setProperty('--pull-x', '0px');
          b.style.setProperty('--pull-y', '0px');
        }
      });
    }
    window.addEventListener('pointermove', function (e) {
      lastX = e.clientX; lastY = e.clientY;
      if (!raf) raf = requestAnimationFrame(paint);
    }, { passive: true });
  }

  // ---------------------------------------------- nav-loading bar
  // The scroll-progress bar already drawn above gets a second job: on a
  // same-origin, same-tab link click it races to most-of-the-way-there
  // immediately, so the instant before a full page unload - which nothing
  // the departing page renders can otherwise animate - still reads as "in
  // progress" rather than the page simply going inert. Browsers that support
  // cross-document view transitions (see style.css's @view-transition rule)
  // crossfade on top of this; browsers that do not still get a bar that
  // moved instead of a dead click.
  document.addEventListener('click', function (e) {
    if (reduced || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
    var a = e.target && e.target.closest ? e.target.closest('a[href]') : null;
    if (!a || a.target === '_blank' || a.hasAttribute('download')) return;
    var href = a.getAttribute('href') || '';
    if (href === '' || href.charAt(0) === '#' || /^(mailto:|tel:|javascript:)/i.test(href)) return;
    try { if (new URL(a.href, location.href).origin !== location.origin) return; } catch (e2) { return; }
    bar.classList.add('navigating');
  }, { passive: true });
})();

/* Upgrade prompt.
 *
 * The markup ships in the page with `hidden` on it and this removes it, which
 * is the same rule the rest of this codebase follows: nothing may be hidden in
 * the markup unless a script is present to unhide it. Here the reason is the
 * reverse of usual - if this script never runs, the visitor gets no overlay at
 * all rather than an overlay with no way to close it.
 */
(function () {
  var wrap = document.getElementById('upsellWrap');
  if (!wrap) return;

  function dismiss() {
    wrap.hidden = true;
    document.documentElement.style.overflow = '';
    // Fire and forget: the prompt is already gone, and a failed request must
    // not leave the reader looking at an overlay waiting for the network.
    // Two different things can be behind this overlay and they are
    // remembered separately: closing a half-price weekend must not silence
    // the standing "what Premium adds" explanation, or vice versa.
    var act = wrap.dataset.promo === '1' ? 'promo_dismiss' : 'upsell_dismiss';
    // The JSON content type is what proves this is same-origin: a forged
    // cross-site request cannot set it. The endpoint now requires it, so
    // sending it is not optional.
    try {
      fetch('api.php?action=' + act, {
        method: 'POST',
        headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
        body: '{}'
      });
    } catch (e) { /* nothing to do - it reappears next visit at worst */ }
  }

  ['upsellClose', 'upsellLater'].forEach(function (id) {
    var b = document.getElementById(id);
    if (b) b.addEventListener('click', dismiss);
  });
  // Clicking the backdrop and pressing Escape both close it. An overlay with
  // exactly one exit is the pattern people have learned to distrust.
  wrap.addEventListener('click', function (e) { if (e.target === wrap) dismiss(); });
  document.addEventListener('keydown', function (e) {
    if (!wrap.hidden && (e.key === 'Escape' || e.key === 'Esc')) dismiss();
  });
  // Following the link is not a dismissal: someone who goes to look at the
  // plans and does not buy should not have the explanation withheld for a
  // fortnight afterwards.

  // A beat before it appears, so it does not land on top of a page the reader
  // has not seen yet - an overlay that arrives with the content reads as an ad
  // the site opened, not as a message from it.
  setTimeout(function () {
    wrap.hidden = false;
    document.documentElement.style.overflow = 'hidden';
    var x = document.getElementById('upsellClose');
    if (x) x.focus();
  }, 1200);
})();

/* Discount campaign: the countdown, and hiding the strip.
 *
 * The clock counts down to a server-supplied timestamp rather than to a
 * duration handed to the browser, so a tab left open overnight shows the
 * right number instead of the one it was given at load - and a clock that is
 * wrong about an offer is worse than no clock.
 */
(function () {
  function fmt(sec) {
    if (sec <= 0) return null;
    var d = Math.floor(sec / 86400), h = Math.floor(sec % 86400 / 3600),
        m = Math.floor(sec % 3600 / 60), s = sec % 60;
    if (d > 0) return d + 'd ' + h + 'h ' + m + 'm';
    if (h > 0) return h + 'h ' + m + 'm ' + s + 's';
    return m + 'm ' + s + 's';
  }

  function tick(el, ends, render, onEnd) {
    function run() {
      var left = fmt(Math.floor(ends - Date.now() / 1000));
      if (left === null) { onEnd(); return; }
      el.textContent = render(left);
      setTimeout(run, 1000);
    }
    run();
  }

  var bar = document.getElementById('promoBar');
  if (bar) {
    var x = document.getElementById('promoBarX');
    if (x) {
      x.addEventListener('click', function () {
        bar.remove();
        // The strip is a reminder, not a decision - remembered in this
        // browser only, so it comes back on the next visit. Somebody who
        // closed a banner today has not opted out of a discount.
        try { sessionStorage.setItem('sma_promo_bar', '0'); } catch (e) {}
      });
    }
    try { if (sessionStorage.getItem('sma_promo_bar') === '0') bar.remove(); } catch (e) {}
    var left = document.getElementById('promoLeft');
    if (left && bar.dataset.ends) {
      tick(left, parseInt(bar.dataset.ends, 10),
           function (t) { return 'ends in ' + t; },
           function () { if (bar.parentNode) bar.remove(); });
    }
  }

  var clock = document.getElementById('promoClock');
  if (clock && clock.dataset.ends) {
    tick(clock, parseInt(clock.dataset.ends, 10),
         function (t) { return t + ' left'; },
         function () { clock.textContent = 'This offer has ended'; });
  }
})();

/* ---------------------------------------------------------------------------
 * Behaviour that used to live in inline on* attributes.
 *
 * The public Content-Security-Policy has no 'unsafe-inline' for script, which
 * is what stops an injected <script> from ever running - and the same rule
 * silently kills inline handlers. These three were the only ones outside the
 * admin panel, so they moved here rather than the policy being weakened for
 * them. Delegated from document, so nothing depends on load order and markup
 * rendered later still works.
 * ------------------------------------------------------------------------ */
(function () {
  // A read-only field holding something to copy: select it all on focus.
  document.addEventListener('click', function (e) {
    var el = e.target.closest ? e.target.closest('[data-select-on-click]') : null;
    if (el && typeof el.select === 'function') { el.select(); }
  });

  // A select that IS the control: choosing an option applies it.
  document.addEventListener('change', function (e) {
    var el = e.target.closest ? e.target.closest('[data-submit-on-change]') : null;
    if (el && el.form) { el.form.submit(); }
  });

  // Checkout button: one press only, say what is happening, and admit it when
  // the gateway is taking too long. The request is deliberately left running -
  // it may still succeed.
  document.addEventListener('click', function (e) {
    var b = e.target.closest ? e.target.closest('[data-pay-submit]') : null;
    if (!b) { return; }
    e.preventDefault();
    if (b.dataset.busy) { return; }
    b.dataset.busy = '1';
    b.textContent = b.dataset.busyText || 'Preparing checkout…';
    var slow = document.getElementById(b.dataset.slowNote || 'slowpay');
    setTimeout(function () { if (slow) { slow.style.display = 'block'; } }, 20000);
    if (b.form) { b.form.submit(); }
  });
})();

/* Mark a horizontally scrollable table so the edge can say so.
 *
 * The scanner's board is wider than a phone on purpose - see the note in
 * style.css - but nothing told the reader it could be dragged: the last
 * column was cut in half at the viewport edge, which reads as a broken
 * layout rather than as an invitation. The class this adds turns on a fade;
 * it is removed once the reader reaches the end, because a fade that outlives
 * the content it is hinting at promises a column that is not there.
 *
 * Sits here rather than in app.js because .tbl-scroll is on the member pages
 * and the admin ones, and ui.js is the file both of them already load.
 */
(function () {
  // .settings-tabs (admin/settings.php's section strip) is the same
  // horizontally-scrolling-row shape as .tbl-scroll, just not a table - it
  // gets the identical fade for the identical reason.
  //
  // table.grid is here too, admin-only: below 860px the table scrolls
  // itself rather than its .tbl-scroll wrapper (see the "Wide tables scroll
  // inside themselves" note in admin.css) - so on a phone it is the table,
  // not the wrapper, whose scrollWidth actually exceeds its clientWidth.
  // Checking both costs nothing on desktop: the table's own overflow is
  // reset to visible there, and admin.css only paints the mask on it below
  // 860px, so the class can be present and simply do nothing.
  var boxes = document.querySelectorAll('.tbl-scroll, .settings-tabs, table.grid');
  if (!boxes.length) { return; }
  var sync = function (el) {
    // A couple of pixels of slack: sub-pixel widths make an exactly-fitting
    // table report one pixel of overflow and flicker a fade nobody can use.
    var more = el.scrollWidth - el.clientWidth - el.scrollLeft > 4;
    el.classList.toggle('can-scroll-x', more);
  };
  Array.prototype.forEach.call(boxes, function (el) {
    sync(el);
    el.addEventListener('scroll', function () { sync(el); }, { passive: true });
  });
  var all = function () { Array.prototype.forEach.call(boxes, sync); };
  window.addEventListener('resize', all);
  // Rows can arrive after this runs - a Load more button, a background
  // refresh - and a table that just grew is exactly when the hint is needed.
  if (window.ResizeObserver) {
    var ro = new ResizeObserver(all);
    Array.prototype.forEach.call(boxes, function (el) { ro.observe(el); });
  }
}());

/* =====================================================================
   PERFORMANCE PAGE: SIX STACKED TABLES -> ONE SWIPEABLE CAROUSEL
   =====================================================================
   By grade, by type, by timeframe, by session, how signals ended, by coin -
   six small breakdown tables, each already correct and already server-
   rendered, stacked one after another down a page a reader had to scroll
   the length of six sections to get through even though each one is a
   three-to-six-row table.

   Nothing about the tables changes and no PHP is touched: this walks the
   ALREADY-RENDERED markup at load, groups each heading with the elements
   that belong to it, and moves that group into a slide. Read-only structure
   in, rearranged structure out - if the DOM shape upstream ever changes in
   a way this does not expect, the walk simply finds fewer slides and the
   page falls back to its plain stacked self instead of breaking.
   ===================================================================== */
(function () {
  var wrap = document.querySelector('.perf-wrap');
  if (!wrap) { return; }
  var heads = wrap.querySelectorAll('.perf-slide-h');
  // Two is the floor for a carousel to mean anything - one slide is just
  // the section it always was, and the tab strip would show a single tab.
  if (heads.length < 2) { return; }

  var track = document.createElement('div');
  track.className = 'perf-track';
  var tabsWrap = document.createElement('div');
  tabsWrap.className = 'perf-tabs-wrap';
  var tabs = document.createElement('div');
  tabs.className = 'perf-tabs';
  tabs.setAttribute('role', 'tablist');
  tabsWrap.appendChild(tabs);

  var firstHead = heads[0];
  var parent = firstHead.parentNode;
  var anchor = document.createElement('div');
  anchor.className = 'perf-carousel';
  parent.insertBefore(anchor, firstHead);
  anchor.appendChild(tabsWrap);
  anchor.appendChild(track);

  // The fade means "more this way" - it has to go once there is no more
  // "this way" to point at, or the last tab sits permanently half-obscured
  // behind a hint that is lying to the reader.
  function updateFade() {
    var atEnd = tabs.scrollWidth - tabs.scrollLeft - tabs.clientWidth < 4;
    tabsWrap.classList.toggle('at-end', atEnd);
  }
  tabs.addEventListener('scroll', updateFade, { passive: true });
  updateFade();

  Array.prototype.forEach.call(heads, function (h, i) {
    var slide = document.createElement('section');
    slide.className = 'perf-slide';
    slide.id = 'pslide-' + h.dataset.slide;
    slide.setAttribute('role', 'tabpanel');

    // Collect this heading and every sibling up to (not including) the
    // next perf-slide-h. appendChild MOVES a node - it does not clone it -
    // so ids, listeners and the table's own content are carried over
    // untouched, just re-parented.
    var node = h;
    // Stop at the NEXT h2, of any kind - not just one tagged perf-slide-h.
    // The sixth slide (By coin) has no seventh tagged heading after it, only
    // the trade-log's own untagged <h2>; stopping on perf-slide-h alone found
    // no boundary there and swept the entire trade log into the last slide.
    // Caught by a Node harness built to run this exact grouping logic against
    // a stub tree shaped like the real page, before it ever touched a browser.
    while (node && !(node !== h && node.tagName === 'H2')) {
      var next = node.nextElementSibling;
      slide.appendChild(node);
      node = next;
    }
    track.appendChild(slide);

    var tab = document.createElement('button');
    tab.type = 'button';
    tab.className = 'perf-tab' + (i === 0 ? ' on' : '');
    tab.setAttribute('role', 'tab');
    tab.setAttribute('aria-controls', slide.id);
    tab.textContent = h.textContent;
    tab.addEventListener('click', function () {
      slide.scrollIntoView({ behavior: 'smooth', inline: 'start', block: 'nearest' });
    });
    tabs.appendChild(tab);
  });

  // THE ACTIVE TAB, AND THE TRACK'S OWN HEIGHT, BOTH FOLLOW WHAT IS ACTUALLY
  // ON SCREEN.
  //
  // .perf-track lays its slides out in one flex row so scroll-snap can page
  // between them - and a single flex row's height is set by its TALLEST
  // child, full stop, regardless of which child is actually scrolled into
  // view. "By grade" (four rows) sat in a row exactly as tall as whichever
  // slide had the most rows, leaving the gap between "By grade"'s own
  // content and the next section below sized for a slide nobody was
  // looking at.
  //
  // Fixed by tracking which slides are ACTUALLY visible (not just the one
  // that most recently crossed the threshold - a slide leaving view matters
  // exactly as much as one entering it, so both directions update one
  // shared state map) and setting the track's height to the tallest of
  // ONLY those. At one slide per screen this is that slide's own height,
  // exactly; when two fit side by side (see the CSS at 640px+) it is the
  // taller of the visible pair, which is the one shape a shared row can
  // honestly take.
  var tabEls = tabs.querySelectorAll('.perf-tab');
  if ('IntersectionObserver' in window) {
    var visible = new Map();
    function syncHeight() {
      var max = 0;
      visible.forEach(function (inView, slide) {
        if (inView) { max = Math.max(max, slide.scrollHeight); }
      });
      if (max > 0) { track.style.height = max + 'px'; }
    }
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        visible.set(en.target, en.intersectionRatio >= 0.6);
        if (en.intersectionRatio < 0.6) { return; }
        var idx = Array.prototype.indexOf.call(track.children, en.target);
        Array.prototype.forEach.call(tabEls, function (t, i) {
          t.classList.toggle('on', i === idx);
        });
      });
      syncHeight();
    }, { root: track, threshold: [0.6] });
    Array.prototype.forEach.call(track.children, function (s) { io.observe(s); });
    // A resize (rotate, or crossing the 640px 1-up/2-up line) changes which
    // slides are wide enough to co-occupy the row, which the observer alone
    // will not repaint until the NEXT scroll. Re-measured on resize so the
    // height is never stale after the shape of the row itself changes.
    window.addEventListener('resize', function () {
      requestAnimationFrame(syncHeight);
    }, { passive: true });
  }
})();
