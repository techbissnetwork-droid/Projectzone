/* ==========================================================================
   TECHBISS — Platform Concept 03 : Kinetic Systems Map — behaviour layer
   Everything below is defensive: every module checks the DOM actually
   contains what it needs before wiring anything up, so this single file
   can be shared, unmodified, across all six pages. GSAP / ScrollTrigger /
   Lenis are optional progressive enhancement — nothing here depends on the
   CDN succeeding (this sandbox blocks outbound CDN calls by design).
   ========================================================================== */
(function(){
  "use strict";

  var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var isCoarse = window.matchMedia && window.matchMedia('(hover: none), (pointer: coarse)').matches;
  var hasGSAP = typeof window.gsap !== 'undefined';
  var hasLenis = typeof window.Lenis !== 'undefined';

  /* -------------------- mark first paint done, THEN allow theme transitions
     so navigating between pages / first load never shows a colour-swap
     animation — only live toggles (after this point) transition. -------------------- */
  function markThemeReady(){
    requestAnimationFrame(function(){
      requestAnimationFrame(function(){
        document.documentElement.classList.add('theme-ready');
      });
    });
  }
  markThemeReady();

  /* -------------------- smooth scroll + scroll-trigger plumbing -------------------- */
  if (hasGSAP && window.ScrollTrigger) gsap.registerPlugin(ScrollTrigger);
  var lenis = null;
  if (hasLenis && !reduced && !isCoarse) {
    lenis = new Lenis({ duration: 1.0, smoothWheel: true, wheelMultiplier: 1 });
    document.documentElement.classList.add('has-lenis');
    (function raf(time){ lenis.raf(time); requestAnimationFrame(raf); })();
    if (hasGSAP) lenis.on('scroll', ScrollTrigger.update);
  }

  /* ============================================================
     THEME — three-way (dark / light / system), persisted
     ============================================================ */
  var THEME_KEY = 'techbiss-theme';
  function getStored(){ try { return localStorage.getItem(THEME_KEY); } catch(e){ return null; } }
  function setStored(v){ try { localStorage.setItem(THEME_KEY, v); } catch(e){} }
  function getPreference(){ var v = getStored(); return (v === 'dark' || v === 'light' || v === 'system') ? v : 'system'; }
  function systemPrefersDark(){ return !!(window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches); }
  function resolve(pref){ return (pref === 'dark' || pref === 'light') ? pref : (systemPrefersDark() ? 'dark' : 'light'); }

  function applyTheme(pref){
    document.documentElement.setAttribute('data-theme', resolve(pref));
    document.querySelectorAll('[data-theme-option]').forEach(function(btn){
      var mine = btn.getAttribute('data-theme-option') === pref;
      btn.setAttribute('aria-pressed', mine ? 'true' : 'false');
    });
  }

  var mql = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;
  function onSystemChange(){ if (getPreference() === 'system') applyTheme('system'); }
  if (mql) {
    if (mql.addEventListener) mql.addEventListener('change', onSystemChange);
    else if (mql.addListener) mql.addListener(onSystemChange);
  }

  document.querySelectorAll('[data-theme-option]').forEach(function(btn){
    btn.addEventListener('click', function(){
      var val = btn.getAttribute('data-theme-option');
      setStored(val);
      applyTheme(val);
    });
  });
  applyTheme(getPreference());

  /* ============================================================
     HEADER — scroll shadow, mega/menus not used here (flat nav)
     ============================================================ */
  var header = document.querySelector('[data-header]');
  function onScroll(){ if (header) header.classList.toggle('is-scrolled', window.scrollY > 18); }
  onScroll();
  window.addEventListener('scroll', onScroll, { passive: true });

  /* ============================================================
     MOBILE NAV — overlay is a DOM sibling of <header>, see CSS note
     ============================================================ */
  var navToggle = document.querySelector('[data-nav-toggle]');
  var mobileNav = document.querySelector('[data-mobile-nav]');
  if (navToggle && mobileNav) {
    var closeMobileNav = function(){
      mobileNav.classList.remove('is-open');
      navToggle.setAttribute('aria-expanded', 'false');
      document.body.style.overflow = '';
    };
    var openMobileNav = function(){
      mobileNav.classList.add('is-open');
      navToggle.setAttribute('aria-expanded', 'true');
      document.body.style.overflow = 'hidden';
    };
    navToggle.addEventListener('click', function(){
      if (mobileNav.classList.contains('is-open')) closeMobileNav(); else openMobileNav();
    });
    mobileNav.querySelectorAll('a').forEach(function(a){ a.addEventListener('click', closeMobileNav); });
    document.addEventListener('keydown', function(e){
      if (e.key === 'Escape' && mobileNav.classList.contains('is-open')) closeMobileNav();
    });
  }

  /* ============================================================
     SCROLL REVEAL — fades in [data-reveal], draws connector lines,
     stamps stagger delays for [data-stagger] groups
     ============================================================ */
  var revealEls = document.querySelectorAll('[data-reveal]');
  function activateExtras(el){
    if (el.querySelectorAll) {
      el.querySelectorAll('.connector-line').forEach(function(l){ l.classList.add('is-drawn'); });
    }
    if (el.classList && el.classList.contains('connector-line')) el.classList.add('is-drawn');
  }
  document.querySelectorAll('[data-stagger]').forEach(function(group){
    Array.prototype.forEach.call(group.children, function(child, i){
      if (child.hasAttribute('data-reveal')) child.setAttribute('data-reveal-delay', i * 90);
    });
  });
  if (reduced || !('IntersectionObserver' in window)) {
    revealEls.forEach(function(el){ el.classList.add('reveal-in'); activateExtras(el); });
  } else {
    var io = new IntersectionObserver(function(entries){
      entries.forEach(function(entry){
        if (entry.isIntersecting) {
          var delay = entry.target.getAttribute('data-reveal-delay');
          entry.target.style.transitionDelay = delay ? (delay + 'ms') : '0ms';
          entry.target.classList.add('reveal-in');
          activateExtras(entry.target);
          io.unobserve(entry.target);
        }
      });
    }, { threshold: 0.18, rootMargin: '0px 0px -8% 0px' });
    revealEls.forEach(function(el){ io.observe(el); });
    setTimeout(function(){
      revealEls.forEach(function(el){ if (!el.classList.contains('reveal-in')) { el.classList.add('reveal-in'); activateExtras(el); } });
    }, 2500);
  }

  /* ============================================================
     ANIMATED COUNTERS  [data-count] (+data-prefix / data-suffix)
     ============================================================ */
  var counters = document.querySelectorAll('[data-count]');
  if (counters.length && 'IntersectionObserver' in window) {
    var counterIO = new IntersectionObserver(function(entries){
      entries.forEach(function(entry){
        if (!entry.isIntersecting) return;
        var el = entry.target;
        var raw = el.getAttribute('data-count');
        var target = parseFloat(raw);
        var suffix = el.getAttribute('data-suffix') || '';
        var prefix = el.getAttribute('data-prefix') || '';
        var decimals = (raw.split('.')[1] || '').length;
        if (reduced) {
          el.textContent = prefix + target.toFixed(decimals) + suffix;
        } else {
          var start = performance.now(), dur = 1200;
          (function tick(now){
            var p = Math.min(1, (now - start) / dur);
            var eased = 1 - Math.pow(1 - p, 3);
            el.textContent = prefix + (target * eased).toFixed(decimals) + suffix;
            if (p < 1) requestAnimationFrame(tick);
          })(start);
        }
        counterIO.unobserve(el);
      });
    }, { threshold: 0.4 });
    counters.forEach(function(el){ counterIO.observe(el); });
  } else {
    counters.forEach(function(el){
      var raw = el.getAttribute('data-count');
      el.textContent = (el.getAttribute('data-prefix')||'') + parseFloat(raw) + (el.getAttribute('data-suffix')||'');
    });
  }

  /* ============================================================
     HUB CONNECTION LINES — service ecosystem grid: hovering / focusing
     a card draws a line from the card to the central hub indicator
     ============================================================ */
  (function initHub(){
    var grid = document.querySelector('[data-hub-grid]');
    if (!grid) return;
    var hub = grid.querySelector('[data-hub-center]');
    var svg = grid.querySelector('[data-hub-svg]');
    var line = svg && svg.querySelector('[data-hub-line]');
    var cards = grid.querySelectorAll('[data-hub-card]');
    if (!hub || !svg || !line || !cards.length) return;

    function point(el){
      var r = el.getBoundingClientRect();
      var s = svg.getBoundingClientRect();
      return { x: r.left + r.width / 2 - s.left, y: r.top + r.height / 2 - s.top };
    }
    function connect(card){
      var a = point(card), b = point(hub);
      line.setAttribute('x1', a.x); line.setAttribute('y1', a.y);
      line.setAttribute('x2', b.x); line.setAttribute('y2', b.y);
      line.style.opacity = '1';
      card.classList.add('is-connected');
    }
    function disconnect(card){
      line.style.opacity = '0';
      card.classList.remove('is-connected');
    }
    cards.forEach(function(card){
      card.addEventListener('mouseenter', function(){ connect(card); });
      card.addEventListener('mouseleave', function(){ disconnect(card); });
      card.addEventListener('focusin', function(){ connect(card); });
      card.addEventListener('focusout', function(){ disconnect(card); });
    });
    window.addEventListener('resize', function(){ line.style.opacity = '0'; });
  })();

  /* ============================================================
     PROCESS TIMELINE — scroll-reactive node highlight
     (works with plain IntersectionObserver; GSAP scrub layered on
     top only when the CDN actually loaded)
     ============================================================ */
  (function initTimeline(){
    var rail = document.querySelector('[data-process-rail]');
    if (!rail) return;
    var steps = Array.prototype.slice.call(rail.querySelectorAll('[data-process-step]'));
    var fill = rail.querySelector('[data-process-fill]');
    if (!steps.length) return;

    function setCurrent(idx){
      steps.forEach(function(s, i){
        s.classList.toggle('is-current', i === idx);
        s.classList.toggle('is-past', i < idx);
      });
      var p = steps.length <= 1 ? 1 : idx / (steps.length - 1);
      if (fill) fill.style.setProperty('--progress', p);
    }

    if (reduced || !('IntersectionObserver' in window)) {
      setCurrent(steps.length - 1);
      return;
    }
    var seen = 0;
    var stepIO = new IntersectionObserver(function(entries){
      entries.forEach(function(entry){
        var idx = steps.indexOf(entry.target);
        if (entry.isIntersecting && idx > seen) seen = idx;
        if (entry.isIntersecting) setCurrent(idx);
      });
    }, { threshold: 0.6, rootMargin: '0px 0px -20% 0px' });
    steps.forEach(function(s){ stepIO.observe(s); });
    setCurrent(0);
  })();

  /* ============================================================
     TRANSFORMATION STAGES — 4-stage offline→growing diagram:
     each stage panel gets .is-active as it's revealed, driving its
     node-graph's connected/live state via CSS
     ============================================================ */
  (function initStages(){
    var stages = document.querySelectorAll('[data-stage-panel]');
    if (!stages.length) return;
    if (reduced || !('IntersectionObserver' in window)) {
      stages.forEach(function(s){ s.classList.add('is-active'); });
      return;
    }
    var stageIO = new IntersectionObserver(function(entries){
      entries.forEach(function(entry){
        if (entry.isIntersecting) { entry.target.classList.add('is-active'); stageIO.unobserve(entry.target); }
      });
    }, { threshold: 0.35 });
    stages.forEach(function(s){ stageIO.observe(s); });
  })();

  /* ============================================================
     MARKETPLACE — client-side filter / search / sort
     ============================================================ */
  (function initMarketplace(){
    var grid = document.querySelector('[data-product-grid]');
    if (!grid) return;
    var cards = Array.prototype.slice.call(grid.querySelectorAll('[data-product]'));
    var filterBar = document.querySelector('[data-industry-filters]');
    var searchInput = document.querySelector('[data-product-search]');
    var sortSelect = document.querySelector('[data-product-sort]');
    var countEl = document.querySelector('[data-result-count]');
    var emptyState = document.querySelector('[data-empty-state]');
    var activeIndustry = 'All';

    try {
      var params = new URLSearchParams(window.location.search);
      var qIndustry = params.get('industry');
      if (qIndustry) activeIndustry = qIndustry;
      var qSearch = params.get('search');
      if (qSearch && searchInput) searchInput.value = qSearch;
    } catch(e){}

    function applyFilters(){
      var term = ((searchInput && searchInput.value) || '').trim().toLowerCase();
      var visible = 0;
      cards.forEach(function(card){
        var industry = card.getAttribute('data-industry') || '';
        var name = (card.getAttribute('data-name') || '').toLowerCase();
        var tags = (card.getAttribute('data-tags') || '').toLowerCase();
        var matchIndustry = activeIndustry === 'All' || industry === activeIndustry;
        var matchSearch = !term || name.indexOf(term) > -1 || industry.toLowerCase().indexOf(term) > -1 || tags.indexOf(term) > -1;
        var show = matchIndustry && matchSearch;
        card.hidden = !show;
        if (show) visible++;
      });
      if (countEl) countEl.textContent = String(visible);
      if (emptyState) emptyState.hidden = visible !== 0;
    }

    function setActiveIndustry(val){
      activeIndustry = val;
      if (filterBar) {
        filterBar.querySelectorAll('[data-industry-btn]').forEach(function(btn){
          var mine = btn.getAttribute('data-industry-btn') === val;
          btn.setAttribute('aria-pressed', mine ? 'true' : 'false');
        });
      }
      applyFilters();
    }

    if (filterBar) {
      filterBar.querySelectorAll('[data-industry-btn]').forEach(function(btn){
        btn.addEventListener('click', function(){ setActiveIndustry(btn.getAttribute('data-industry-btn')); });
      });
    }
    if (searchInput) searchInput.addEventListener('input', applyFilters);

    function applySort(){
      if (!sortSelect) return;
      var val = sortSelect.value;
      var sorted = cards.slice().sort(function(a, b){
        if (val === 'price-asc') return parseFloat(a.getAttribute('data-price')) - parseFloat(b.getAttribute('data-price'));
        if (val === 'price-desc') return parseFloat(b.getAttribute('data-price')) - parseFloat(a.getAttribute('data-price'));
        if (val === 'newest') return parseInt(b.getAttribute('data-newest'), 10) - parseInt(a.getAttribute('data-newest'), 10);
        return parseInt(b.getAttribute('data-popularity'), 10) - parseInt(a.getAttribute('data-popularity'), 10);
      });
      sorted.forEach(function(card){ grid.appendChild(card); });
    }
    if (sortSelect) sortSelect.addEventListener('change', applySort);

    setActiveIndustry(activeIndustry);
    applySort();
  })();

  /* ============================================================
     MARKETPLACE PRODUCT PAGE — gallery tabs + purchase/configure/deploy
     ============================================================ */
  (function initProductPage(){
    var frame = document.querySelector('[data-gallery-frame]');
    if (frame) {
      var thumbs = document.querySelectorAll('[data-gallery-thumb]');
      thumbs.forEach(function(thumb){
        thumb.addEventListener('click', function(){
          thumbs.forEach(function(t){ t.setAttribute('aria-selected', 'false'); });
          thumb.setAttribute('aria-selected', 'true');
          if (frame) frame.setAttribute('data-active-view', thumb.getAttribute('data-gallery-thumb'));
        });
      });
    }

    document.querySelectorAll('[data-tabs]').forEach(function(tabGroup){
      var buttons = tabGroup.querySelectorAll('[data-tab-btn]');
      var panels = tabGroup.querySelectorAll('[data-tab-panel]');
      buttons.forEach(function(btn){
        btn.addEventListener('click', function(){
          var id = btn.getAttribute('data-tab-btn');
          buttons.forEach(function(b){ b.setAttribute('aria-selected', b === btn ? 'true' : 'false'); });
          panels.forEach(function(p){ p.hidden = p.getAttribute('data-tab-panel') !== id; });
        });
      });
    });

    var buyFlow = document.querySelector('[data-buy-flow]');
    if (buyFlow) {
      var purchaseBtn = buyFlow.querySelector('[data-buy-purchase]');
      var configureBtn = buyFlow.querySelector('[data-buy-configure]');
      var deployBtn = buyFlow.querySelector('[data-buy-deploy]');
      var statusEl = buyFlow.querySelector('[data-buy-status]');
      var deployPanel = buyFlow.querySelector('[data-deploy-result]');

      function setStatus(text, tone){
        if (!statusEl) return;
        statusEl.textContent = text;
        statusEl.className = 'status-pill' + (tone ? ' is-' + tone : '');
      }
      if (purchaseBtn) purchaseBtn.addEventListener('click', function(){
        purchaseBtn.disabled = true;
        purchaseBtn.innerHTML = '<svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M4 10.5l4 4 8-9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg> Purchased';
        if (configureBtn) configureBtn.disabled = false;
        setStatus('Purchased — ready to configure', 'accent');
      });
      if (configureBtn) configureBtn.addEventListener('click', function(){
        configureBtn.disabled = true;
        configureBtn.innerHTML = '<svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M4 10.5l4 4 8-9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg> Configured';
        if (deployBtn) deployBtn.disabled = false;
        setStatus('Configured — ready to deploy', 'accent');
      });
      if (deployBtn) deployBtn.addEventListener('click', function(){
        deployBtn.disabled = true;
        var label = deployBtn.querySelector('[data-btn-label]');
        if (label) label.textContent = 'Deploying…';
        deployBtn.insertAdjacentHTML('afterbegin', '<span class="spinner" data-spinner></span>');
        setStatus('Deploying to production…', 'warn');
        var finish = function(){
          if (label) label.textContent = 'Deployed';
          var spinner = deployBtn.querySelector('[data-spinner]');
          if (spinner) spinner.remove();
          setStatus('Live', 'live');
          if (deployPanel) deployPanel.hidden = false;
        };
        if (reduced) finish(); else setTimeout(finish, 1400);
      });
    }
  })();

  /* ============================================================
     GENERIC SEGMENTED CONTROL — [data-segmented] > [data-segmented-option]
     ============================================================ */
  document.querySelectorAll('[data-segmented]').forEach(function(group){
    var opts = group.querySelectorAll('[data-segmented-option]');
    opts.forEach(function(opt){
      opt.addEventListener('click', function(){
        opts.forEach(function(o){ o.setAttribute('aria-selected', 'false'); });
        opt.setAttribute('aria-selected', 'true');
        var hidden = group.parentElement && group.parentElement.querySelector('input[type="hidden"]');
        if (hidden) hidden.value = opt.getAttribute('data-segmented-option');
      });
    });
  });

  /* ============================================================
     INSTALLER — Select → Detect → Configure → Import → Install →
     Verify → Launch state machine
     ============================================================ */
  (function initInstaller(){
    var root = document.querySelector('[data-installer]');
    if (!root) return;
    var order = ['select', 'detect', 'configure', 'import', 'install', 'verify', 'launch'];
    var panels = root.querySelectorAll('[data-step-panel]');
    var railNodes = root.querySelectorAll('[data-rail-node]');
    var railFill = root.querySelector('[data-rail-fill]');
    var nextBtn = root.querySelector('[data-installer-next]');
    var backBtn = root.querySelector('[data-installer-back]');
    var current = 0;
    var maxReached = 0;
    var ran = {};

    function modeChoice(){
      var picked = root.querySelector('input[name="install-mode"]:checked');
      return picked ? picked.value : null;
    }

    function render(){
      panels.forEach(function(p){ p.hidden = p.getAttribute('data-step-panel') !== order[current]; });
      railNodes.forEach(function(n, i){
        n.classList.toggle('is-current', i === current);
        n.classList.toggle('is-done', i < current);
        n.classList.toggle('is-reachable', i <= maxReached);
      });
      if (railFill) railFill.style.width = (order.length <= 1 ? 100 : (current / (order.length - 1)) * 100) + '%';
      if (backBtn) backBtn.hidden = current === 0;
      if (nextBtn) {
        if (current === order.length - 1) { nextBtn.hidden = true; }
        else { nextBtn.hidden = false; nextBtn.textContent = current === order.length - 2 ? 'Launch site →' : 'Continue →'; }
      }
      updateNextEnabled();
      enterStep(order[current]);
    }

    function updateNextEnabled(){
      if (!nextBtn) return;
      var step = order[current];
      if (step === 'select') nextBtn.disabled = !modeChoice();
      else nextBtn.disabled = false;
    }

    root.querySelectorAll('input[name="install-mode"]').forEach(function(radio){
      radio.addEventListener('change', function(){
        updateNextEnabled();
        var summary = root.querySelector('[data-mode-summary]');
        if (summary) {
          var map = {
            'new': 'Provisioning a brand-new TECHBISS site from a starter template.',
            'migrate': 'Migrating an existing website — content, media and database included.',
            'marketplace': 'Deploying a purchased marketplace product to your domain.'
          };
          summary.textContent = map[radio.value] || '';
        }
      });
    });

    function typeLine(list, text, cls, delay){
      return new Promise(function(resolve){
        var run = function(){
          var li = document.createElement('li');
          li.className = 'scan-line' + (cls ? ' ' + cls : '');
          li.innerHTML = text;
          list.appendChild(li);
          resolve();
        };
        if (reduced) run(); else setTimeout(run, delay);
      });
    }

    function runDetect(){
      if (ran.detect) return;
      ran.detect = true;
      var list = root.querySelector('[data-scan-log]');
      var badge = root.querySelector('[data-detect-status]');
      if (!list) return;
      list.innerHTML = '';
      var mode = modeChoice();
      var lines = mode === 'migrate' ? [
        'Connecting to source host…',
        'Detecting existing CMS…',
        '<strong>Found:</strong> WordPress 6.4, PHP 8.1',
        'Scanning content…',
        '<strong>12 pages</strong>, <strong>34 media files</strong> found',
        'Checking SSL certificate…',
        '<strong>SSL certificate:</strong> valid, expires in 214 days',
        'Detection complete.'
      ] : mode === 'marketplace' ? [
        'Reading product manifest…',
        '<strong>Product:</strong> Ember & Table — Restaurant theme',
        'Verifying included systems…',
        '<strong>Found:</strong> Website, Payments, Booking, Email',
        'Checking licence…',
        '<strong>Licence:</strong> valid, single-site',
        'Detection complete.'
      ] : [
        'Preparing a clean environment…',
        'No existing CMS detected — starting fresh.',
        'Selecting recommended stack…',
        '<strong>Stack:</strong> TECHBISS Core, CDN edge cache',
        'Reserving resources…',
        'Detection complete.'
      ];
      var p = Promise.resolve();
      lines.forEach(function(text, i){
        p = p.then(function(){ return typeLine(list, text, i === lines.length - 1 ? 'is-final' : '', 420); });
      });
      p.then(function(){ if (badge) { badge.textContent = 'Ready'; badge.className = 'status-pill is-live'; } });
    }

    function runImport(){
      if (ran.import) return;
      ran.import = true;
      var mode = modeChoice();
      var wrap = root.querySelector('[data-import-bars]');
      var skip = root.querySelector('[data-import-skip]');
      if (!wrap) return;
      if (mode !== 'migrate') {
        wrap.hidden = true;
        if (skip) {
          skip.hidden = false;
          skip.textContent = mode === 'marketplace' ? 'No migration needed — product files will be copied directly during install.' : 'No migration needed — a fresh starter template will be installed.';
        }
        return;
      }
      wrap.hidden = false;
      if (skip) skip.hidden = true;
      var bars = wrap.querySelectorAll('[data-import-bar]');
      bars.forEach(function(bar, idx){
        var fill = bar.querySelector('.progress-fill');
        var pct = bar.querySelector('[data-import-pct]');
        var target = 100;
        if (reduced) {
          if (fill) fill.style.width = target + '%';
          if (pct) pct.textContent = target + '%';
          return;
        }
        var startDelay = idx * 350;
        setTimeout(function(){
          var val = 0;
          var speed = 2 + idx;
          var timer = setInterval(function(){
            val = Math.min(target, val + speed + Math.random() * 4);
            if (fill) fill.style.width = val + '%';
            if (pct) pct.textContent = Math.round(val) + '%';
            if (val >= target) clearInterval(timer);
          }, 60);
        }, startDelay);
      });
    }

    function runChecklist(selector, delayEach){
      var list = root.querySelector(selector);
      if (!list) return;
      var items = list.querySelectorAll('.checklist-item');
      items.forEach(function(item){ item.classList.remove('is-active', 'is-done'); });
      if (reduced) {
        items.forEach(function(item){ item.classList.add('is-done'); });
        return;
      }
      var i = 0;
      function step(){
        if (i > 0) items[i - 1].classList.remove('is-active');
        if (i > 0) items[i - 1].classList.add('is-done');
        if (i >= items.length) return;
        items[i].classList.add('is-active');
        i++;
        setTimeout(step, delayEach);
      }
      step();
    }

    function runInstall(){
      if (ran.install) return;
      ran.install = true;
      runChecklist('[data-install-checklist]', 750);
    }
    function runVerify(){
      if (ran.verify) return;
      ran.verify = true;
      runChecklist('[data-verify-checklist]', 650);
    }
    function runLaunch(){
      var domainOut = root.querySelector('[data-launch-domain]');
      if (domainOut) {
        var domainField = root.querySelector('[name="install-domain"]');
        var siteField = root.querySelector('[name="install-sitename"]');
        var val = (domainField && domainField.value.trim()) || (siteField && siteField.value.trim().toLowerCase().replace(/[^a-z0-9]+/g, '-')) || 'yourbusiness';
        domainOut.textContent = val.indexOf('.') > -1 ? val : val + '.techbiss.app';
      }
    }

    function enterStep(step){
      if (step === 'detect') runDetect();
      if (step === 'import') runImport();
      if (step === 'install') runInstall();
      if (step === 'verify') runVerify();
      if (step === 'launch') runLaunch();
    }

    function validateConfigure(){
      var domain = root.querySelector('[name="install-domain"]');
      var siteName = root.querySelector('[name="install-sitename"]');
      var ok = true;
      [domain, siteName].forEach(function(input){
        if (!input) return;
        var field = input.closest('.field');
        var valid = input.value.trim().length > 1;
        if (input === domain) valid = /^[a-z0-9-]+\.[a-z]{2,}$/i.test(input.value.trim());
        if (field) field.classList.toggle('is-error', !valid);
        if (field) field.classList.toggle('is-success', valid);
        if (!valid) ok = false;
      });
      return ok;
    }

    if (nextBtn) nextBtn.addEventListener('click', function(){
      var step = order[current];
      if (step === 'select' && !modeChoice()) return;
      if (step === 'configure' && !validateConfigure()) return;
      if (current < order.length - 1) {
        current++;
        if (current > maxReached) maxReached = current;
        render();
        root.scrollIntoView({ behavior: reduced ? 'auto' : 'smooth', block: 'start' });
      }
    });
    if (backBtn) backBtn.addEventListener('click', function(){
      if (current > 0) { current--; render(); root.scrollIntoView({ behavior: reduced ? 'auto' : 'smooth', block: 'start' }); }
    });
    railNodes.forEach(function(node, i){
      node.addEventListener('click', function(){
        if (i <= maxReached) { current = i; render(); }
      });
    });

    render();
  })();

  /* ============================================================
     DASHBOARD — sidebar tab switching, no reload
     ============================================================ */
  (function initDashboard(){
    var nav = document.querySelector('[data-dash-nav]');
    if (!nav) return;
    var links = nav.querySelectorAll('[data-dash-link]');
    var panels = document.querySelectorAll('[data-dash-panel]');

    function activate(id){
      links.forEach(function(l){ l.classList.toggle('is-active', l.getAttribute('data-dash-link') === id); });
      panels.forEach(function(p){ p.hidden = p.getAttribute('data-dash-panel') !== id; });
      var panel = document.querySelector('[data-dash-panel="' + id + '"]');
      if (panel) panel.querySelectorAll('[data-reveal]').forEach(function(el){ el.classList.add('reveal-in'); });
      var title = document.querySelector('[data-dash-title]');
      var activeLink = nav.querySelector('[data-dash-link="' + id + '"]');
      if (title && activeLink) title.textContent = activeLink.textContent.trim();
    }
    links.forEach(function(l){
      l.addEventListener('click', function(e){
        e.preventDefault();
        activate(l.getAttribute('data-dash-link'));
        try { history.replaceState(null, '', '#' + l.getAttribute('data-dash-link')); } catch(err){}
        var mobileDashNav = document.querySelector('[data-dash-mobile-open]');
        if (mobileDashNav) mobileDashNav.classList.remove('is-open');
      });
    });
    var initial = (window.location.hash || '').replace('#', '');
    if (!initial || !document.querySelector('[data-dash-panel="' + initial + '"]')) initial = 'overview';
    activate(initial);

    var mobileToggle = document.querySelector('[data-dash-sidebar-toggle]');
    var sidebar = document.querySelector('[data-dash-sidebar]');
    if (mobileToggle && sidebar) {
      mobileToggle.addEventListener('click', function(){
        var open = sidebar.classList.toggle('is-open');
        mobileToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      });
    }
  })();

  /* ============================================================
     LOGIN — role selector, validation, loading state, forgot-password
     ============================================================ */
  (function initLogin(){
    var form = document.querySelector('[data-login-form]');
    if (!form) return;

    var roleGroup = document.querySelector('[data-role-select]');
    if (roleGroup) {
      var roleBtns = roleGroup.querySelectorAll('[data-role-option]');
      roleBtns.forEach(function(btn){
        btn.addEventListener('click', function(){
          roleBtns.forEach(function(b){ b.setAttribute('aria-selected', b === btn ? 'true' : 'false'); });
          var role = btn.getAttribute('data-role-option');
          var out = document.querySelector('[data-role-hint]');
          var map = {
            client: 'Client access — your projects, websites and billing.',
            staff: 'Staff access — assigned client projects and tickets.',
            admin: 'Admin access — full platform and account controls.'
          };
          if (out) out.textContent = map[role] || '';
          var hidden = form.querySelector('[name="login-role"]');
          if (hidden) hidden.value = role;
        });
      });
    }

    var pwToggle = document.querySelector('[data-password-toggle]');
    var pwInput = document.querySelector('[name="login-password"]');
    if (pwToggle && pwInput) {
      pwToggle.addEventListener('click', function(){
        var show = pwInput.type === 'password';
        pwInput.type = show ? 'text' : 'password';
        pwToggle.textContent = show ? 'Hide' : 'Show';
        pwToggle.setAttribute('aria-pressed', show ? 'true' : 'false');
      });
    }

    var forgotLink = document.querySelector('[data-forgot-link]');
    var forgotPanel = document.querySelector('[data-forgot-panel]');
    if (forgotLink && forgotPanel) {
      forgotLink.addEventListener('click', function(e){
        e.preventDefault();
        forgotPanel.hidden = !forgotPanel.hidden;
        if (!forgotPanel.hidden) forgotPanel.scrollIntoView({ behavior: reduced ? 'auto' : 'smooth', block: 'nearest' });
      });
    }

    function validateField(input, testFn){
      var field = input.closest('.field');
      var valid = testFn(input.value);
      if (field) {
        field.classList.toggle('is-error', !valid);
        field.classList.toggle('is-success', valid);
      }
      return valid;
    }

    form.addEventListener('submit', function(e){
      e.preventDefault();
      var email = form.querySelector('[name="login-email"]');
      var password = form.querySelector('[name="login-password"]');
      var emailOk = email ? validateField(email, function(v){ return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v.trim()); }) : true;
      var passOk = password ? validateField(password, function(v){ return v.length >= 6; }) : true;
      if (!emailOk || !passOk) return;

      var submitBtn = form.querySelector('[data-login-submit]');
      var formError = form.querySelector('[data-form-error]');
      if (formError) formError.hidden = true;
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.classList.add('is-loading');
        var label = submitBtn.querySelector('[data-btn-label]');
        if (label) label.textContent = 'Signing in…';
        submitBtn.insertAdjacentHTML('beforeend', '<span class="spinner" data-spinner></span>');
      }
      var finish = function(){
        var successPanel = document.querySelector('[data-login-success]');
        if (successPanel) {
          form.hidden = true;
          successPanel.hidden = false;
        }
        setTimeout(function(){ window.location.href = 'dashboard.html'; }, reduced ? 200 : 900);
      };
      setTimeout(finish, reduced ? 50 : 1100);
    });
  })();

})();
