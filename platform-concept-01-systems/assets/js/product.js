/* ==========================================================================
   TECHBISS PLATFORM — marketplace-product.html page script
   Default content is fully authored, static HTML for "Ember & Table" (works
   with zero JS beyond the interactions below). When linked with ?id=<slug>
   from a different marketplace card, the identity fields (name, tagline,
   industry, rating, deployments, price, accent color, breadcrumb) are
   re-skinned from the shared product data so no "View Details" link across
   the marketplace ever points at mismatched content.
   ========================================================================== */
(function(){
  "use strict";
  var PRODUCTS = window.TECHBISS_PRODUCTS || [];
  var BASE_PRICE = 349;

  /* ---------------- ?id= re-skin ---------------- */
  var params = new URLSearchParams(location.search);
  var requestedId = params.get('id');
  if (requestedId && requestedId !== 'ember-table') {
    var p = PRODUCTS.filter(function(x){ return x.id === requestedId; })[0];
    if (p) {
      BASE_PRICE = p.price;
      document.title = p.name + ' — TECHBISS Marketplace';
      var setText = function(id, text){ var el = document.getElementById(id); if (el) el.textContent = text; };
      setText('pdName', p.name);
      setText('pdTagline', p.tagline);
      setText('pdIndustryTag', p.industry);
      setText('pdRating', '★ ' + p.rating.toFixed(1));
      setText('pdDeployments', p.deployments.toLocaleString('en-US') + ' deployments');
      setText('pdBasePrice', window.TECHBISS_FORMAT_PRICE(p.price));
      setText('pdPriceTotal', window.TECHBISS_FORMAT_PRICE(p.price));
      setText('pdBreadcrumbName', p.name);
      var bcIndustry = document.getElementById('pdBreadcrumbIndustry');
      if (bcIndustry) { bcIndustry.textContent = p.industry; bcIndustry.href = 'marketplace.html?industry=' + encodeURIComponent(p.industry); }
      document.body.style.setProperty('--card-accent', p.accent);
      document.body.style.setProperty('--card-accent-rgb', p.accentRgb);
      document.querySelectorAll('.pw-url').forEach(function(el){
        var suffix = el.textContent.replace(/^[a-z0-9-]+\.techbiss\.site/, '');
        el.textContent = p.id + '.techbiss.site' + suffix;
      });
      var siteNameField = document.getElementById('pcfgSiteName');
      if (siteNameField) siteNameField.value = p.name;
    }
  }

  /* ---------------- Gallery tabs ---------------- */
  document.querySelectorAll('.gallery-tab').forEach(function(tab){
    tab.addEventListener('click', function(){
      var target = tab.getAttribute('data-gallery-tab');
      document.querySelectorAll('.gallery-tab').forEach(function(t){
        t.classList.toggle('is-active', t === tab);
        t.setAttribute('aria-selected', t === tab ? 'true' : 'false');
      });
      document.querySelectorAll('.gallery-panel').forEach(function(panel){
        var match = panel.getAttribute('data-gallery-panel') === target;
        panel.hidden = !match;
        panel.classList.toggle('is-active', match);
      });
    });
  });

  /* ---------------- Addon total ---------------- */
  var totalEl = document.getElementById('pdPriceTotal');
  function recalcTotal(){
    var sum = BASE_PRICE;
    document.querySelectorAll('.addon-list input[type="checkbox"]:checked').forEach(function(cb){
      sum += Number(cb.getAttribute('data-addon')) || 0;
    });
    if (totalEl) totalEl.textContent = window.TECHBISS_FORMAT_PRICE(sum);
  }
  document.querySelectorAll('.addon-list input[type="checkbox"]').forEach(function(cb){
    cb.addEventListener('change', recalcTotal);
  });

  /* ---------------- Purchase -> Configure -> Deploy state machine ---------------- */
  var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var purchaseBtn = document.querySelector('[data-action="purchase"]');
  var configureBtn = document.querySelector('[data-action="configure"]');
  var deployBtn = document.querySelector('[data-action="deploy"]');
  var note = document.querySelector('[data-purchase-note]');
  var configurePanel = document.querySelector('[data-configure-panel]');
  var deployPanel = document.querySelector('[data-deploy-panel]');
  var saveConfigBtn = document.querySelector('[data-action="save-config"]');
  var configSavedMsg = document.querySelector('[data-config-saved-msg]');

  var state = { purchased: false, configured: false, deployed: false };

  if (purchaseBtn) {
    purchaseBtn.addEventListener('click', function(){
      if (state.purchased) return;
      purchaseBtn.classList.add('btn-loading');
      purchaseBtn.disabled = true;
      var finish = function(){
        state.purchased = true;
        purchaseBtn.classList.remove('btn-loading');
        purchaseBtn.textContent = '✓ Purchased';
        purchaseBtn.classList.remove('btn-primary');
        purchaseBtn.classList.add('btn-outline');
        configureBtn.disabled = false;
        note.textContent = 'Purchased — configure your deployment below.';
      };
      if (reduced) finish(); else setTimeout(finish, 900);
    });
  }

  if (configureBtn) {
    configureBtn.addEventListener('click', function(){
      if (configureBtn.disabled) return;
      configurePanel.hidden = !configurePanel.hidden;
      if (!configurePanel.hidden) configurePanel.scrollIntoView({ behavior: reduced ? 'auto' : 'smooth', block: 'nearest' });
    });
  }

  if (saveConfigBtn) {
    saveConfigBtn.addEventListener('click', function(){
      state.configured = true;
      configSavedMsg.hidden = false;
      deployBtn.disabled = false;
      note.textContent = 'Configuration saved — ready to deploy.';
    });
  }

  if (deployBtn) {
    deployBtn.addEventListener('click', function(){
      if (deployBtn.disabled) return;
      deployPanel.hidden = false;
      deployBtn.disabled = true;
      deployPanel.scrollIntoView({ behavior: reduced ? 'auto' : 'smooth', block: 'nearest' });
      var steps = Array.prototype.slice.call(deployPanel.querySelectorAll('[data-deploy-step]'));
      var doneLink = deployPanel.querySelector('[data-deploy-done]');
      function complete(step){ step.classList.add('is-done'); }
      if (reduced) {
        steps.forEach(complete);
        doneLink.hidden = false;
        note.textContent = 'Deployed — your site is live in the dashboard.';
      } else {
        steps.forEach(function(step, i){
          setTimeout(function(){
            step.classList.add('is-active');
            setTimeout(function(){
              step.classList.remove('is-active');
              complete(step);
              if (i === steps.length - 1) {
                doneLink.hidden = false;
                note.textContent = 'Deployed — your site is live in the dashboard.';
              }
            }, 650);
          }, i * 750);
        });
      }
    });
  }

  /* ---------------- Related products ---------------- */
  var relatedGrid = document.getElementById('relatedGrid');
  if (relatedGrid && PRODUCTS.length) {
    var currentId = requestedId || 'ember-table';
    var pool = PRODUCTS.filter(function(p){ return p.id !== currentId; });
    // deterministic-ish variety: skip around the list rather than always the first 4
    var picks = [pool[2], pool[5], pool[8], pool[11]].filter(Boolean);
    if (picks.length < 4) picks = pool.slice(0, 4);
    relatedGrid.innerHTML = picks.map(function(p){
      return (
        '<article class="product-card">' +
          window.TECHBISS_PREVIEW_HTML(p) +
          '<div class="pc-body">' +
            '<div class="pc-top"><span class="tag">' + p.industry + '</span><span class="pc-rating mono">★ ' + p.rating.toFixed(1) + '</span></div>' +
            '<h3>' + p.name + '</h3>' +
            '<p class="pc-tagline">' + p.tagline + '</p>' +
            '<div class="pc-foot"><span class="pc-price mono">' + window.TECHBISS_FORMAT_PRICE(p.price) + '</span></div>' +
            '<div class="pc-actions"><a href="marketplace-product.html?id=' + p.id + '" class="btn btn-primary btn-sm btn-block">View Details</a></div>' +
          '</div>' +
        '</article>'
      );
    }).join('');
  }

})();
