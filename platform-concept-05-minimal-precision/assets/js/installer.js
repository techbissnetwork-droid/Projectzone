/* ==========================================================================
   TECHBISS — Advanced Installer: 7-step state machine
   Select → Detect → Configure → Import/Migrate → Install → Verify → Launch
   ========================================================================== */
(function () {
  "use strict";
  var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var PRODUCTS = window.TECHBISS_PRODUCTS || [];

  var STEPS = ['select', 'detect', 'configure', 'import', 'install', 'verify', 'launch'];
  var STEP_NAMES = { select: 'Select', detect: 'Detect', configure: 'Configure', import: 'Import & Migrate', install: 'Install', verify: 'Verify', launch: 'Launch' };

  var panels = {};
  STEPS.forEach(function (key) { panels[key] = document.querySelector('.step-panel[data-step="' + key + '"]'); });

  var progressCount = document.querySelector('[data-step-count]');
  var progressName = document.querySelector('[data-step-name]');
  var progressFill = document.querySelector('[data-step-fill]');
  var backBtn = document.querySelector('[data-step-back]');
  var nextBtn = document.querySelector('[data-step-next]');
  var stepNav = document.querySelector('[data-step-nav]');

  var params = new URLSearchParams(location.search);
  var preSlug = params.get('product');
  var preProduct = preSlug ? PRODUCTS.filter(function (p) { return p.slug === preSlug; })[0] : null;

  var state = {
    index: 0,
    selection: preProduct ? 'marketplace' : 'new',
    product: preProduct || null,
    domain: '',
    environment: 'Staging',
    siteName: ''
  };
  var doneSteps = {}; // steps whose mock process has resolved at least once

  /* ---------- Select ---------- */
  var selectRadios = document.querySelectorAll('input[name="install-select"]');
  selectRadios.forEach(function (r) {
    if (r.value === state.selection) r.checked = true;
    r.addEventListener('change', function () { if (r.checked) state.selection = r.value; });
  });
  var selectContext = document.querySelector('[data-select-context]');
  if (selectContext) {
    if (preProduct) {
      selectContext.hidden = false;
      selectContext.innerHTML = 'Deploying: <b>' + preProduct.name + '</b> (' + preProduct.industry + ')';
    } else {
      selectContext.hidden = true;
    }
  }

  /* ---------- Detect ---------- */
  var DETECT_LINES = {
    new: ['Checking domain availability…', 'Domain available', 'Recommended plan: Premium Hosting', 'Environment ready'],
    migrate: ['Detecting existing CMS…', 'Found: WordPress 6.x', 'SSL certificate: valid', '12 pages found'],
    marketplace: ['Loading theme package…', 'Found: PRODUCT (INDUSTRY)', 'Dependencies verified', '6 pages included']
  };
  function detectLines() {
    var lines = DETECT_LINES[state.selection] || DETECT_LINES.new;
    if (state.selection === 'marketplace' && state.product) {
      lines = lines.map(function (l) { return l.replace('PRODUCT', state.product.name).replace('INDUSTRY', state.product.industry); });
    }
    return lines;
  }
  function runDetect(done) {
    var host = document.querySelector('[data-detect-lines]');
    if (!host) return done();
    var lines = detectLines();
    host.innerHTML = lines.map(function (text) {
      return '<div class="status-line"><span class="status-line-mark" aria-hidden="true"></span><span class="status-line-text">' + text + '</span></div>';
    }).join('');
    var rows = host.querySelectorAll('.status-line');
    if (reduced) {
      rows.forEach(function (r) { r.classList.add('is-in', 'is-done'); });
      done();
      return;
    }
    rows.forEach(function (r, i) {
      setTimeout(function () { r.classList.add('is-in'); }, i * 260);
      setTimeout(function () { r.classList.add('is-done'); }, i * 260 + 380);
    });
    setTimeout(done, rows.length * 260 + 500);
  }

  /* ---------- Configure ---------- */
  var domainInput = document.querySelector('[data-domain-input]');
  var siteNameInput = document.querySelector('[data-sitename-input]');
  var envButtons = document.querySelectorAll('[data-env-btn]');
  envButtons.forEach(function (btn) {
    btn.addEventListener('click', function () {
      state.environment = btn.getAttribute('data-env-btn');
      envButtons.forEach(function (b) { b.classList.toggle('is-active', b === btn); });
    });
  });
  var domainField = document.querySelector('[data-domain-field]');
  var siteNameField = document.querySelector('[data-sitename-field]');
  var domainRe = /^([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i;

  function validateDomain(showState) {
    var v = (domainInput.value || '').trim();
    var ok = domainRe.test(v);
    if (showState) {
      domainField.classList.toggle('has-error', !ok);
      domainField.classList.toggle('has-success', ok);
      var msg = domainField.querySelector('.field-msg');
      if (msg) msg.textContent = ok ? '✓ Looks good' : (v ? 'Enter a valid domain, e.g. yourbusiness.com' : 'Domain is required');
    }
    return ok;
  }
  function validateSiteName(showState) {
    var v = (siteNameInput.value || '').trim();
    var ok = v.length >= 2;
    if (showState) {
      siteNameField.classList.toggle('has-error', !ok);
      siteNameField.classList.toggle('has-success', ok);
      var msg = siteNameField.querySelector('.field-msg');
      if (msg) msg.textContent = ok ? '✓ Looks good' : 'Enter a site name';
    }
    return ok;
  }
  if (domainInput) domainInput.addEventListener('blur', function () { validateDomain(true); });
  if (siteNameInput) siteNameInput.addEventListener('blur', function () { validateSiteName(true); });
  if (domainInput) domainInput.addEventListener('input', function () { if (domainField.classList.contains('has-error')) validateDomain(true); });
  if (siteNameInput) siteNameInput.addEventListener('input', function () { if (siteNameField.classList.contains('has-error')) validateSiteName(true); });

  /* ---------- Import / Migrate ---------- */
  var IMPORT_BARS = {
    new: [['Environment', 100], ['Dependencies', 100], ['Assets', 100]],
    migrate: [['Files', 100], ['Database', 100], ['Media', 100]],
    marketplace: [['Theme Files', 100], ['Sample Content', 100], ['Configuration', 100]]
  };
  function runImport(done) {
    var host = document.querySelector('[data-import-bars]');
    if (!host) return done();
    var bars = IMPORT_BARS[state.selection] || IMPORT_BARS.new;
    host.innerHTML = bars.map(function (b) {
      return '<div class="bar-row"><div class="bar-top"><span class="bar-label">' + b[0] + '</span><span class="bar-pct" data-pct>0%</span></div><div class="bar-track"><div class="bar-fill" data-fill></div></div></div>';
    }).join('');
    var rows = host.querySelectorAll('.bar-row');
    if (reduced) {
      rows.forEach(function (r) {
        r.querySelector('[data-fill]').style.width = '100%';
        r.querySelector('[data-pct]').textContent = '100%';
      });
      done();
      return;
    }
    var maxDur = 0;
    rows.forEach(function (r, i) {
      var target = bars[i][1];
      var delay = i * 220;
      var dur = 1000;
      maxDur = Math.max(maxDur, delay + dur);
      var fill = r.querySelector('[data-fill]');
      var pct = r.querySelector('[data-pct]');
      setTimeout(function () {
        var start = null;
        function frame(ts) {
          if (!start) start = ts;
          var p = Math.min(1, (ts - start) / dur);
          var val = Math.round(p * target);
          fill.style.width = val + '%';
          pct.textContent = val + '%';
          if (p < 1) requestAnimationFrame(frame);
        }
        requestAnimationFrame(frame);
      }, delay);
    });
    setTimeout(done, maxDur + 250);
  }

  /* ---------- Install / Verify (checklists) ---------- */
  var INSTALL_ITEMS = ['Provisioning', 'Installing', 'Configuring', 'Optimizing'];
  var VERIFY_ITEMS = ['SSL certificate valid', 'DNS propagated', 'Site responding', 'Backups configured'];
  function runChecklist(hostSel, items, done) {
    var host = document.querySelector(hostSel);
    if (!host) return done();
    host.innerHTML = items.map(function (label) {
      return '<div class="check-item"><span class="check-mark" aria-hidden="true"></span><span class="check-label">' + label + '</span></div>';
    }).join('');
    var rows = host.querySelectorAll('.check-item');
    if (reduced) {
      rows.forEach(function (r) { r.classList.add('is-checked'); });
      done();
      return;
    }
    rows.forEach(function (r, i) { setTimeout(function () { r.classList.add('is-checked'); }, i * 480 + 260); });
    setTimeout(done, rows.length * 480 + 400);
  }

  /* ---------- Launch ---------- */
  function renderLaunch() {
    var rows = document.querySelector('[data-launch-summary]');
    if (!rows) return;
    var items = [
      ['Site name', state.siteName || '—'],
      ['Domain', state.domain || '—'],
      ['Environment', state.environment],
      ['Setup type', state.selection === 'new' ? 'New Website' : state.selection === 'migrate' ? 'Migrated Website' : 'Marketplace Product']
    ];
    if (state.selection === 'marketplace' && state.product) items.push(['Theme', state.product.name]);
    rows.innerHTML = items.map(function (pair) {
      return '<div class="row"><span>' + pair[0] + '</span><span>' + pair[1] + '</span></div>';
    }).join('');
    var domainDisplay = document.querySelector('[data-launch-domain]');
    if (domainDisplay) domainDisplay.textContent = state.domain || (state.siteName ? state.siteName.toLowerCase().replace(/[^a-z0-9]+/g, '-') + '.techbiss.site' : 'your-site.techbiss.site');
  }

  /* ---------- Step machine ---------- */
  function updateProgress() {
    var key = STEPS[state.index];
    if (progressCount) progressCount.textContent = 'Step ' + (state.index + 1) + ' / ' + STEPS.length;
    if (progressName) progressName.textContent = STEP_NAMES[key];
    if (progressFill) progressFill.style.width = (((state.index + 1) / STEPS.length) * 100) + '%';
  }

  function setNextEnabled(enabled) {
    if (!nextBtn) return;
    nextBtn.disabled = !enabled;
  }

  function enterStep(index) {
    state.index = index;
    var key = STEPS[index];
    STEPS.forEach(function (k) { if (panels[k]) panels[k].hidden = (k !== key); });
    updateProgress();
    if (backBtn) backBtn.hidden = index === 0;
    if (stepNav) stepNav.hidden = key === 'launch';

    if (key === 'select') { setNextEnabled(true); return; }
    if (key === 'configure') {
      if (domainInput) domainInput.value = state.domain;
      if (siteNameInput) siteNameInput.value = state.siteName;
      envButtons.forEach(function (b) { b.classList.toggle('is-active', b.getAttribute('data-env-btn') === state.environment); });
      setNextEnabled(true);
      return;
    }
    if (key === 'launch') { renderLaunch(); return; }

    // animated steps: disable Next until resolved
    setNextEnabled(false);
    if (key === 'detect') runDetect(function () { setNextEnabled(true); });
    else if (key === 'import') runImport(function () { setNextEnabled(true); });
    else if (key === 'install') runChecklist('[data-install-list]', INSTALL_ITEMS, function () { setNextEnabled(true); });
    else if (key === 'verify') runChecklist('[data-verify-list]', VERIFY_ITEMS, function () { setNextEnabled(true); });
  }

  if (nextBtn) {
    nextBtn.addEventListener('click', function () {
      var key = STEPS[state.index];
      if (key === 'configure') {
        var domOk = validateDomain(true);
        var nameOk = validateSiteName(true);
        if (!domOk) { domainInput.focus(); return; }
        if (!nameOk) { siteNameInput.focus(); return; }
        state.domain = domainInput.value.trim();
        state.siteName = siteNameInput.value.trim();
      }
      if (state.index < STEPS.length - 1) enterStep(state.index + 1);
    });
  }
  if (backBtn) {
    backBtn.addEventListener('click', function () {
      if (state.index > 0) enterStep(state.index - 1);
    });
  }

  enterStep(0);
})();
