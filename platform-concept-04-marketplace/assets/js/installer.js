/* ==========================================================================
   TECHBISS Advanced Installer — installer.html only
   A real JS state machine: Select → Detect → Configure → Import → Install →
   Verify → Launch. Each automated step (detect/import/install/verify) runs
   once, then shows its completed state on revisit. prefers-reduced-motion
   skips every staggered/counting animation and jumps straight to end states.
   ========================================================================== */
(function(){
  "use strict";
  var panels0 = document.getElementById('step-select');
  if (!panels0) return;

  var reduced = (window.TECHBISS && window.TECHBISS.reduced) ||
    (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
  var TB = window.TB_PRODUCTS;

  var STEPS = ['select','detect','configure','import','install','verify','launch'];
  var STEP_LABELS = { select:'Select', detect:'Detect', configure:'Configure', import:'Import', install:'Install', verify:'Verify', launch:'Launch' };
  var current = 0;
  var state = { path:'new', product:null, domain:'', sitename:'', env:'staging' };

  var panels = {};
  STEPS.forEach(function(s){ panels[s] = document.getElementById('step-' + s); });
  var railSteps = {};
  document.querySelectorAll('[data-rail-step]').forEach(function(el){ railSteps[el.getAttribute('data-rail-step')] = el; });
  var railFill = document.getElementById('ir-fill');
  var statusEl = document.getElementById('ir-status');

  /* ---------------- Icons ---------------- */
  function spinnerSVG(){ return '<svg class="spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><circle cx="12" cy="12" r="9" stroke-opacity=".25"/><path d="M21 12a9 9 0 0 0-9-9"/></svg>'; }
  function checkSVG(){ return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6"><path d="M4 12.5l5.5 5.5L20 7"/></svg>'; }
  function dotSVG(){ return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="8"/></svg>'; }

  /* ---------------- Navigation / rail ---------------- */
  function renderRail(){
    STEPS.forEach(function(s, i){
      var el = railSteps[s];
      el.classList.toggle('is-active', i === current);
      el.classList.toggle('is-done', i < current);
    });
    railFill.style.transform = 'scaleX(' + (current / (STEPS.length - 1)) + ')';
    statusEl.textContent = 'Step ' + (current + 1) + ' of ' + STEPS.length + ' — ' + STEP_LABELS[STEPS[current]];
  }

  function goTo(index){
    if (index < 0 || index >= STEPS.length) return;
    panels[STEPS[current]].hidden = true;
    current = index;
    panels[STEPS[current]].hidden = false;
    renderRail();
    var head = document.querySelector('.installer-panel');
    if (head) head.scrollIntoView({ behavior: reduced ? 'auto' : 'smooth', block: 'start' });
    onEnter(STEPS[current]);
  }

  document.querySelectorAll('[data-back]').forEach(function(b){ b.addEventListener('click', function(){ goTo(current - 1); }); });
  document.querySelectorAll('[data-next]').forEach(function(b){
    b.addEventListener('click', function(){
      if (b.disabled) return;
      if (STEPS[current] === 'configure' && !validateConfigure()) return;
      goTo(current + 1);
    });
  });

  function onEnter(step){
    if (step === 'detect') runDetect();
    if (step === 'import') runImport();
    if (step === 'install') runChecklist('install-checklist', INSTALL_ITEMS, 'install');
    if (step === 'verify') runChecklist('verify-checklist', VERIFY_ITEMS, 'verify');
    if (step === 'launch') renderLaunch();
  }

  /* ---------------- Step 1: Select ---------------- */
  var selectCards = document.querySelectorAll('.select-card');
  var sppWrap = document.getElementById('select-product-preview');
  var sppCard = document.getElementById('spp-card');

  function setPath(path){
    state.path = path;
    selectCards.forEach(function(c){
      var active = c.getAttribute('data-path') === path;
      c.classList.toggle('is-active', active);
      c.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
    if (path === 'marketplace' && TB) {
      var p = state.product || TB.byId('ember-table');
      state.product = p;
      if (p) {
        sppCard.innerHTML = TB.renderCard(p, { view: 'compact' });
        sppWrap.hidden = false;
      }
    } else {
      sppWrap.hidden = true;
    }
  }
  selectCards.forEach(function(c){
    c.addEventListener('click', function(){ setPath(c.getAttribute('data-path')); });
  });

  var qParams = new URLSearchParams(location.search);
  var qProduct = qParams.get('product');
  if (qProduct && TB && TB.byId(qProduct)) {
    state.product = TB.byId(qProduct);
    setPath('marketplace');
  } else {
    setPath('new');
  }

  /* ---------------- Step 2: Detect ---------------- */
  var DETECT_LINES = {
    new: ['Preparing a fresh environment…','No existing site detected — starting clean','Checking domain availability…','Recommended stack: TECHBISS Standard'],
    migrate: ['Detecting existing CMS…','Found: WordPress 6.4','SSL certificate: valid','12 pages found, 340 media files'],
    marketplace: ['Preparing template environment…','Template validated','Dependencies resolved','Ready to configure']
  };
  function scanLineHTML(text, done){
    return '<div class="scan-line' + (done ? ' is-done' : '') + '"><span class="scan-icon">' + (done ? checkSVG() : spinnerSVG()) + '</span><span class="scan-text">' + text + '</span></div>';
  }
  function runDetect(){
    var panel = panels.detect;
    var log = document.getElementById('scan-log');
    var nextBtn = panel.querySelector('[data-next]');
    var lines = DETECT_LINES[state.path] || DETECT_LINES.new;

    if (panel.dataset.done === '1') {
      log.innerHTML = lines.map(function(l){ return scanLineHTML(l, true); }).join('');
      nextBtn.disabled = false;
      return;
    }
    log.innerHTML = '';
    nextBtn.disabled = true;
    if (reduced) {
      log.innerHTML = lines.map(function(l){ return scanLineHTML(l, true); }).join('');
      panel.dataset.done = '1';
      nextBtn.disabled = false;
      return;
    }
    var i = 0;
    function addLine(){
      if (i >= lines.length) { panel.dataset.done = '1'; nextBtn.disabled = false; return; }
      var div = document.createElement('div');
      div.className = 'scan-line';
      div.innerHTML = '<span class="scan-icon">' + spinnerSVG() + '</span><span class="scan-text">' + lines[i] + '</span>';
      log.appendChild(div);
      setTimeout(function(){
        div.classList.add('is-done');
        div.querySelector('.scan-icon').innerHTML = checkSVG();
        i++;
        setTimeout(addLine, 220);
      }, 460);
    }
    addLine();
  }

  /* ---------------- Step 3: Configure ---------------- */
  var domainInput = document.getElementById('cf-domain');
  var sitenameInput = document.getElementById('cf-sitename');
  var domainField = document.getElementById('field-domain');
  var sitenameField = document.getElementById('field-sitename');
  var domainHint = document.getElementById('hint-domain');
  var sitenameHint = document.getElementById('hint-sitename');
  var DOMAIN_RE = /^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?(\.[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)+$/i;

  function validateDomain(showState){
    var val = domainInput.value.trim();
    var valid = DOMAIN_RE.test(val);
    if (showState) {
      domainField.classList.toggle('is-error', !valid);
      domainField.classList.toggle('is-success', valid);
      domainHint.textContent = valid ? 'Looks good.' : 'Enter a valid domain, e.g. yourbusiness.com';
    }
    return valid;
  }
  function validateSitename(showState){
    var val = sitenameInput.value.trim();
    var valid = val.length > 1;
    if (showState) {
      sitenameField.classList.toggle('is-error', !valid);
      sitenameField.classList.toggle('is-success', valid);
      sitenameHint.textContent = valid ? 'Looks good.' : 'Enter a site name.';
    }
    return valid;
  }
  domainInput.addEventListener('blur', function(){ validateDomain(true); });
  sitenameInput.addEventListener('blur', function(){ validateSitename(true); });
  domainInput.addEventListener('input', function(){ if (domainField.classList.contains('is-error')) validateDomain(true); });
  sitenameInput.addEventListener('input', function(){ if (sitenameField.classList.contains('is-error')) validateSitename(true); });

  function validateConfigure(){
    var d = validateDomain(true);
    var s = validateSitename(true);
    if (!d) domainInput.focus();
    else if (!s) sitenameInput.focus();
    if (d && s) { state.domain = domainInput.value.trim(); state.sitename = sitenameInput.value.trim(); }
    return d && s;
  }
  document.querySelectorAll('.seg-btn').forEach(function(btn){
    btn.addEventListener('click', function(){
      document.querySelectorAll('.seg-btn').forEach(function(b){ b.classList.remove('is-active'); b.setAttribute('aria-pressed','false'); });
      btn.classList.add('is-active');
      btn.setAttribute('aria-pressed','true');
      state.env = btn.getAttribute('data-env');
    });
  });

  /* ---------------- Step 4: Import ---------------- */
  var IMPORT_LABELS = {
    new: ['Template Files','Configuration','Starter Content'],
    migrate: ['Files','Database','Media'],
    marketplace: ['Product Files','Configuration','Sample Content']
  };
  function progressRowHTML(label, pct){
    return '<div class="progress-row"><div class="progress-row-top"><span>' + label + '</span><span class="progress-pct">' + Math.round(pct) + '%</span></div><div class="progress-bar"><div class="progress-fill" style="width:' + pct + '%"></div></div></div>';
  }
  function setProgress(row, val){
    row.querySelector('.progress-fill').style.width = val + '%';
    row.querySelector('.progress-pct').textContent = Math.round(val) + '%';
  }
  function runImport(){
    var panel = panels.import;
    var list = document.getElementById('progress-list');
    var nextBtn = panel.querySelector('[data-next]');
    var titleEl = document.getElementById('import-title');
    var subEl = document.getElementById('import-sub');
    var labels = IMPORT_LABELS[state.path] || IMPORT_LABELS.new;

    if (state.path === 'migrate') { titleEl.textContent = 'Migrating your content'; subEl.textContent = 'Copying files, database and media into your new environment.'; }
    else if (state.path === 'marketplace') { titleEl.textContent = 'Preparing ' + (state.product ? state.product.name : 'your product'); subEl.textContent = 'Copying template files and sample content into place.'; }
    else { titleEl.textContent = 'Setting things up'; subEl.textContent = 'Copying starter files and configuration into place.'; }

    if (panel.dataset.done === '1') {
      list.innerHTML = labels.map(function(l){ return progressRowHTML(l, 100); }).join('');
      nextBtn.disabled = false;
      return;
    }
    nextBtn.disabled = true;
    list.innerHTML = labels.map(function(l){ return progressRowHTML(l, 0); }).join('');
    var rows = Array.prototype.slice.call(list.children);
    if (reduced) {
      rows.forEach(function(r){ setProgress(r, 100); });
      panel.dataset.done = '1';
      nextBtn.disabled = false;
      return;
    }
    var completed = 0;
    rows.forEach(function(row, idx){
      setTimeout(function(){
        var val = 0;
        var timer = setInterval(function(){
          val += 5 + Math.random() * 7;
          if (val >= 100) {
            val = 100;
            clearInterval(timer);
            completed++;
            if (completed === rows.length) { panel.dataset.done = '1'; nextBtn.disabled = false; }
          }
          setProgress(row, val);
        }, 100);
      }, idx * 280);
    });
  }

  /* ---------------- Steps 5 & 6: checklists ---------------- */
  var INSTALL_ITEMS = ['Provisioning infrastructure','Installing core files','Configuring environment','Optimizing performance'];
  var VERIFY_ITEMS = ['SSL certificate valid','DNS propagated','Site responding','Backups configured'];

  function checklistIcon(status){
    if (status === 'done') return checkSVG();
    if (status === 'active') return spinnerSVG();
    return dotSVG();
  }
  function checklistRowHTML(label, status, passLabel){
    return '<div class="checklist-row is-' + status + '"><span class="cl-icon">' + checklistIcon(status) + '</span><span class="cl-text">' + label + '</span><span class="cl-status">' + (status === 'done' ? passLabel : status === 'active' ? 'Running…' : 'Pending') + '</span></div>';
  }
  function setRowState(row, status, passLabel){
    row.className = 'checklist-row is-' + status;
    row.querySelector('.cl-icon').innerHTML = checklistIcon(status);
    row.querySelector('.cl-status').textContent = status === 'done' ? passLabel : status === 'active' ? 'Running…' : 'Pending';
  }
  function runChecklist(containerId, items, panelKey){
    var panel = panels[panelKey];
    var list = document.getElementById(containerId);
    var nextBtn = panel.querySelector('[data-next]');
    var passLabel = panelKey === 'verify' ? 'Pass' : 'Done';

    if (panel.dataset.done === '1') {
      list.innerHTML = items.map(function(l){ return checklistRowHTML(l, 'done', passLabel); }).join('');
      nextBtn.disabled = false;
      return;
    }
    nextBtn.disabled = true;
    list.innerHTML = items.map(function(l){ return checklistRowHTML(l, 'pending', passLabel); }).join('');
    var rows = Array.prototype.slice.call(list.children);
    if (reduced) {
      rows.forEach(function(r){ setRowState(r, 'done', passLabel); });
      panel.dataset.done = '1';
      nextBtn.disabled = false;
      return;
    }
    var i = 0;
    function step(){
      if (i >= rows.length) { panel.dataset.done = '1'; nextBtn.disabled = false; return; }
      setRowState(rows[i], 'active', passLabel);
      setTimeout(function(){
        setRowState(rows[i], 'done', passLabel);
        i++;
        setTimeout(step, 170);
      }, 520);
    }
    step();
  }

  /* ---------------- Step 7: Launch ---------------- */
  function renderLaunch(){
    var domain = state.domain || (state.product ? state.product.domain : 'yourbusiness.com');
    var name = state.sitename || (state.product ? state.product.name : 'Your site');
    document.getElementById('launch-domain').textContent = domain;
    document.getElementById('launch-summary').textContent = name + ' has been deployed to ' + state.env + ' and is now reachable.';
  }

  renderRail();
})();
