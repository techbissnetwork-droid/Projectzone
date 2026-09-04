/* ==========================================================================
   TECHBISS Marketplace — marketplace-product.html only
   Reads ?id= from the URL, renders one product's full detail: tabbed
   gallery, sticky pricing panel with a real Purchase → Configure → Deploy
   state machine, grouped feature list, tech-included chips and a related
   products row.
   ========================================================================== */
(function(){
  "use strict";
  if (!window.TB_PRODUCTS) return;
  var TB = window.TB_PRODUCTS;

  var params = new URLSearchParams(location.search);
  var product = TB.byId(params.get('id')) || TB.all[0];

  document.title = product.name + ' — TECHBISS Marketplace';
  var metaDesc = document.getElementById('pd-meta-desc');
  if (metaDesc) metaDesc.setAttribute('content', product.tagline);

  document.getElementById('pd-industry').textContent = product.industry;
  document.getElementById('pd-name').textContent = product.name;
  document.getElementById('pd-tagline').textContent = product.tagline;

  var badgeEl = document.getElementById('pd-badge');
  if (product.badge === 'popular') { badgeEl.textContent = 'Popular Choice'; badgeEl.hidden = false; }
  else if (product.badge === 'new') { badgeEl.textContent = 'New This Month'; badgeEl.hidden = false; }

  document.getElementById('pd-rating').innerHTML = TB.starIcon() + ' <strong>' + product.rating.toFixed(1) + '</strong> · ' + product.reviews + '+ businesses using this theme';
  document.getElementById('pd-price').textContent = TB.money(product.price);
  document.getElementById('pd-purchase-price').textContent = TB.money(product.price);
  document.getElementById('pd-trust-rating').textContent = product.rating.toFixed(1);
  document.getElementById('pd-trust-businesses').textContent = product.reviews + '+';

  /* ---------------- Gallery ---------------- */
  var SCREENS = [
    { label: 'Home', hint: 'Landing page' },
    { label: 'Browse', hint: 'Catalog / listing view' },
    { label: 'Detail', hint: 'Item / booking detail' },
    { label: 'Checkout', hint: 'Payment & confirmation' }
  ];
  function nextLayout(n){ return (n % 6) + 1; }
  var layouts = [product.layout];
  for (var i = 1; i < SCREENS.length; i++) layouts.push(nextLayout(layouts[i - 1]));

  var stage = document.getElementById('preview');
  var tabsWrap = document.getElementById('pd-tabs');
  tabsWrap.innerHTML = SCREENS.map(function(s, i){
    return '<button type="button" role="tab" class="pd-tab' + (i === 0 ? ' is-active' : '') + '" aria-selected="' + (i === 0 ? 'true' : 'false') + '" data-tab-index="' + i + '">' + s.label + '</button>';
  }).join('');
  var tabs = Array.prototype.slice.call(tabsWrap.children);

  function showScreen(i){
    stage.innerHTML = TB.previewMarkup(product, layouts[i]) + '<span class="pd-gallery-caption">' + SCREENS[i].hint + '</span>';
    var pv = stage.querySelector('.product-preview');
    if (pv) pv.classList.add('product-preview--lg');
    tabs.forEach(function(t, idx){
      var active = idx === i;
      t.classList.toggle('is-active', active);
      t.setAttribute('aria-selected', active ? 'true' : 'false');
    });
  }
  tabs.forEach(function(t){
    t.addEventListener('click', function(){ showScreen(parseInt(t.getAttribute('data-tab-index'), 10)); });
  });
  showScreen(0);

  /* ---------------- Purchase → Configure → Deploy ---------------- */
  var purchaseBtn = document.getElementById('pd-purchase');
  var configureBtn = document.getElementById('pd-configure');
  var deployBtn = document.getElementById('pd-deploy');
  var stepPurchase = document.querySelector('.pms-step[data-step="purchase"]');
  var stepConfigure = document.querySelector('.pms-step[data-step="configure"]');
  var deployHref = 'installer.html?product=' + encodeURIComponent(product.id);

  configureBtn.setAttribute('href', deployHref);
  deployBtn.setAttribute('href', deployHref);
  stepPurchase.classList.add('is-active');

  deployBtn.addEventListener('click', function(e){
    if (deployBtn.classList.contains('is-locked')) e.preventDefault();
  });

  var purchased = false;
  purchaseBtn.addEventListener('click', function(){
    if (purchased) return;
    purchaseBtn.classList.add('btn-loading');
    purchaseBtn.disabled = true;
    setTimeout(function(){
      purchased = true;
      purchaseBtn.classList.remove('btn-loading');
      purchaseBtn.textContent = 'Purchased ✓';
      purchaseBtn.classList.remove('btn-primary');
      purchaseBtn.classList.add('btn-outline');
      deployBtn.classList.remove('is-locked');
      deployBtn.removeAttribute('aria-disabled');
      stepPurchase.classList.remove('is-active');
      stepPurchase.classList.add('is-done');
      stepConfigure.classList.add('is-active');
    }, 900);
  });

  /* ---------------- Feature groups ---------------- */
  var groups = {};
  groups['Built for ' + product.industry] = product.features;
  Object.keys(TB.featureGroups).forEach(function(k){ groups[k] = TB.featureGroups[k]; });
  document.getElementById('pd-feature-groups').innerHTML = Object.keys(groups).map(function(title){
    return '' +
      '<div class="pd-feature-group">' +
        '<h3>' + title + '</h3>' +
        '<ul>' + groups[title].map(function(f){ return '<li>' + f + '</li>'; }).join('') + '</ul>' +
      '</div>';
  }).join('');

  /* ---------------- Tech included ---------------- */
  document.getElementById('pd-tech-chips').innerHTML = TB.techIncluded.map(function(t){
    return '<span class="tech-chip"><svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 8.5l3.2 3.2L13 4.8"/></svg>' + t + '</span>';
  }).join('');

  /* ---------------- Related products ---------------- */
  var related = TB.related(product.id, 4);
  document.getElementById('pd-related-grid').innerHTML = related.map(function(p){ return TB.renderCard(p, { view: 'grid' }); }).join('');
})();
