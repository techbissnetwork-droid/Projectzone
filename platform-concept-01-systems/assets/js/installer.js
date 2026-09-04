/* ==========================================================================
   TECHBISS PLATFORM — installer.html page script
   A real client-side state machine driving the 7-step wizard: Select, Detect,
   Configure, Import/Migrate, Install, Verify, Launch. Every animated sequence
   (scan log, progress bars, checklists) respects prefers-reduced-motion by
   resolving to its end state immediately instead of staggering.
   ========================================================================== */
(function(){
  "use strict";
  var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  var root = document.querySelector('[data-installer]');
  if (!root) return;
  var panes = Array.prototype.slice.call(document.querySelectorAll('[data-step-pane]'));
  var markers = Array.prototype.slice.call(document.querySelectorAll('[data-step-marker]'));
  var fill = document.querySelector('[data-stepper-fill]');
  var backBtn = document.querySelector('[data-step-back]');
  var nextBtn = document.querySelector('[data-step-next]');
  var TOTAL = panes.length;

  var current = 0;
  var furthest = 0;
  var stepDone = [true, false, false, false, false, false, true];

  function render(){
    panes.forEach(function(p, i){ p.hidden = i !== current; });
    markers.forEach(function(m, i){
      m.classList.toggle('is-active', i === current);
      m.classList.toggle('is-done', i < current);
      m.classList.toggle('is-locked', i > furthest);
    });
    if (fill) fill.style.width = (TOTAL > 1 ? (current / (TOTAL - 1) * 100) : 0) + '%';
    if (backBtn) backBtn.hidden = current === 0;
    var stepperNav = document.querySelector('.stepper-nav');
    if (stepperNav) stepperNav.classList.toggle('is-final', current === TOTAL - 1);
    if (nextBtn) {
      nextBtn.hidden = current === TOTAL - 1;
      nextBtn.disabled = !stepDone[current];
      nextBtn.textContent = current === TOTAL - 2 ? 'Launch →' : 'Next →';
    }
    if (current === TOTAL - 1) populateLaunch();
  }

  function goTo(i){
    if (i < 0 || i >= TOTAL || i > furthest) return;
    current = i;
    render();
    var panelEl = document.querySelector('.installer-panel');
    if (panelEl) panelEl.scrollIntoView({ behavior: reduced ? 'auto' : 'smooth', block: 'start' });
  }

  if (backBtn) backBtn.addEventListener('click', function(){ goTo(current - 1); });
  if (nextBtn) nextBtn.addEventListener('click', function(){
    if (!stepDone[current]) return;
    if (current + 1 > furthest) furthest = current + 1;
    goTo(current + 1);
  });
  markers.forEach(function(m, i){
    m.addEventListener('click', function(){ if (i <= furthest) goTo(i); });
  });

  /* ---------------- Step 1: Select — flow + radio-card visuals ---------------- */
  /* [data-flow-for] elements (copy variants, per-flow buttons) are toggled via the
     `hidden` attribute rather than a CSS display rule, so this works uniformly for
     <p>/<h2> AND for .btn buttons without fighting .btn's own inline-flex display. */
  function syncFlowCopy(){
    var flow = root.getAttribute('data-flow');
    document.querySelectorAll('[data-flow-for]').forEach(function(el){
      el.hidden = el.getAttribute('data-flow-for') !== flow;
    });
  }
  function syncRadioCards(){
    document.querySelectorAll('.radio-card').forEach(function(card){
      var input = card.querySelector('input[type="radio"]');
      card.classList.toggle('is-checked', !!(input && input.checked));
    });
  }
  document.querySelectorAll('input[name="flow"]').forEach(function(input){
    input.addEventListener('change', function(){
      if (input.checked) root.setAttribute('data-flow', input.value);
      syncRadioCards();
      syncFlowCopy();
    });
  });
  syncRadioCards();
  syncFlowCopy();

  /* ---------------- Step 2: Detect — scan animation ---------------- */
  document.addEventListener('click', function(e){
    var btn = e.target.closest('[data-action="start-scan"]');
    if (!btn) return;
    var pane = btn.closest('[data-step-pane]');
    var bar = pane.querySelector('[data-scan-bar]');
    var flow = root.getAttribute('data-flow');
    var log = pane.querySelector('.scan-log[data-flow-for="' + flow + '"]');
    var lines = log ? Array.prototype.slice.call(log.querySelectorAll('[data-log-line]')) : [];
    btn.disabled = true;
    btn.textContent = 'Scanning…';

    function markDone(){
      stepDone[1] = true;
      btn.textContent = 'Scan Complete';
      render();
    }
    if (reduced) {
      bar.style.width = '100%';
      lines.forEach(function(l){ l.classList.add('is-shown'); });
      markDone();
      return;
    }
    var dur = 2200;
    var start = performance.now();
    (function tick(now){
      var p = Math.min(1, (now - start) / dur);
      bar.style.width = (p * 100) + '%';
      if (p < 1) requestAnimationFrame(tick);
    })(start);
    lines.forEach(function(l, i){
      setTimeout(function(){ l.classList.add('is-shown'); }, Math.round(((i + 1) / lines.length) * dur - 120));
    });
    setTimeout(markDone, dur + 150);
  });

  /* ---------------- Step 3: Configure — validation ---------------- */
  var domainInput = document.getElementById('instDomain');
  var siteNameInput = document.getElementById('instSiteName');
  var dbNameInput = document.getElementById('instDbName');
  var domainValid = false, siteNameValid = false;

  function slugify(s){ return (s.toLowerCase().trim().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '') || 'site'); }

  function setFieldState(name, ok, msg){
    var field = document.querySelector('[data-field="' + name + '"]');
    if (!field) return;
    field.classList.remove('has-error', 'has-success');
    var msgEl = field.querySelector('[data-field-msg]');
    if (ok === true) { field.classList.add('has-success'); }
    else if (ok === false) { field.classList.add('has-error'); }
    if (msgEl) {
      msgEl.classList.toggle('is-error', ok === false);
      msgEl.classList.toggle('is-success', ok === true);
      msgEl.textContent = (ok === false && msg) ? msg : msgEl.getAttribute('data-default-msg');
    }
  }

  function checkConfigureDone(){
    stepDone[2] = domainValid && siteNameValid;
    render();
  }

  if (domainInput) {
    var validateDomain = function(showError){
      var v = domainInput.value.trim();
      if (v.length === 0) { domainValid = false; setFieldState('domain', null); checkConfigureDone(); return; }
      domainValid = /^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?(\.[a-z]{2,})+$/i.test(v);
      setFieldState('domain', domainValid, showError ? 'Enter a valid domain, e.g. yourbusiness.com' : undefined);
      checkConfigureDone();
    };
    domainInput.addEventListener('input', function(){ validateDomain(false); });
    domainInput.addEventListener('blur', function(){ validateDomain(true); });
  }
  if (siteNameInput) {
    var validateSiteName = function(showError){
      var v = siteNameInput.value.trim();
      if (v.length === 0) { siteNameValid = false; setFieldState('siteName', null); dbNameInput.value = ''; checkConfigureDone(); return; }
      siteNameValid = v.length >= 2;
      setFieldState('siteName', siteNameValid, showError ? 'Site name must be at least 2 characters.' : undefined);
      dbNameInput.value = siteNameValid ? (slugify(v) + '_db') : '';
      checkConfigureDone();
    };
    siteNameInput.addEventListener('input', function(){ validateSiteName(false); });
    siteNameInput.addEventListener('blur', function(){ validateSiteName(true); });
  }

  /* ---------------- Step 4: Import/Migrate — progress bars ---------------- */
  document.addEventListener('click', function(e){
    var btn = e.target.closest('[data-action="start-import"]');
    if (!btn || btn.offsetParent === null) return;
    var pane = btn.closest('[data-step-pane]');
    var summary = pane.querySelector('[data-import-summary]');
    document.querySelectorAll('[data-flow-for="' + root.getAttribute('data-flow') + '"][data-action="start-import"]').forEach(function(b){ b.disabled = true; });
    var bars = { files: 1400, database: 2100, media: 2700 };
    var keys = Object.keys(bars);
    var doneCount = 0;
    function oneDone(){
      doneCount++;
      if (doneCount === keys.length) { summary.hidden = false; stepDone[3] = true; render(); }
    }
    keys.forEach(function(key){
      var fillEl = pane.querySelector('[data-import-bar="' + key + '"]');
      var pctEl = pane.querySelector('[data-import-pct="' + key + '"]');
      if (reduced) { fillEl.style.width = '100%'; pctEl.textContent = '100%'; oneDone(); return; }
      var dur = bars[key];
      var start = performance.now();
      (function tick(now){
        var p = Math.min(1, (now - start) / dur);
        var pct = Math.round(p * 100);
        fillEl.style.width = pct + '%';
        pctEl.textContent = pct + '%';
        if (p < 1) requestAnimationFrame(tick); else oneDone();
      })(start);
    });
  });

  /* ---------------- Shared: sequential checklist runner (Install + Verify) ---------------- */
  function runChecklist(btn, listSelector, itemAttr, stepIndex, summarySelector){
    var pane = btn.closest('[data-step-pane]');
    var items = Array.prototype.slice.call(pane.querySelectorAll('[' + itemAttr + ']'));
    var summary = pane.querySelector(summarySelector);
    btn.disabled = true;
    function setStatus(li, text){ var s = li.querySelector('.ic-status'); if (s) s.textContent = text; }
    function finishAll(){
      items.forEach(function(li){ li.classList.remove('is-active'); li.classList.add('is-done'); setStatus(li, 'DONE'); });
      if (summary) summary.hidden = false;
      stepDone[stepIndex] = true;
      render();
    }
    if (reduced) { finishAll(); return; }
    items.forEach(function(li, i){
      setTimeout(function(){
        li.classList.add('is-active');
        setStatus(li, 'RUNNING');
        setTimeout(function(){
          li.classList.remove('is-active');
          li.classList.add('is-done');
          setStatus(li, 'DONE');
          if (i === items.length - 1) { if (summary) summary.hidden = false; stepDone[stepIndex] = true; render(); }
        }, 550);
      }, i * 700);
    });
  }

  document.addEventListener('click', function(e){
    var installBtn = e.target.closest('[data-action="start-install"]');
    if (installBtn) runChecklist(installBtn, '[data-install-checklist]', 'data-install-step', 4, '[data-install-summary]');
    var verifyBtn = e.target.closest('[data-action="start-verify"]');
    if (verifyBtn) runChecklist(verifyBtn, '[data-verify-checklist]', 'data-verify-step', 5, '[data-verify-summary]');
  });

  /* ---------------- Step 7: Launch ---------------- */
  function populateLaunch(){
    var span = document.querySelector('[data-launch-sitename]');
    if (!span) return;
    var name = siteNameInput && siteNameInput.value.trim();
    span.textContent = name ? name : 'Your site';
  }

  document.addEventListener('click', function(e){
    if (e.target.closest('[data-action="restart"]')) location.reload();
  });

  render();
})();
