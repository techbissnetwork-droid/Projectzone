/* ==========================================================================
   TECHBISS — Marketplace product detail: reads ?product=<slug> and renders
   from window.TECHBISS_PRODUCTS. Defaults to "ember-table" when absent/unknown.
   ========================================================================== */
(function () {
  "use strict";
  var PRODUCTS = window.TECHBISS_PRODUCTS || [];
  if (!PRODUCTS.length) return;

  var params = new URLSearchParams(location.search);
  var slug = params.get('product');
  var product = PRODUCTS.filter(function (p) { return p.slug === slug; })[0] || PRODUCTS.filter(function (p) { return p.slug === 'ember-table'; })[0] || PRODUCTS[0];

  function previewFrameHtml(layout, large) {
    return '' +
      '<div class="preview-frame' + (large ? ' preview-frame-lg' : '') + '" data-layout="' + layout + '">' +
        '<div class="preview-chrome"><span></span><span></span><span></span></div>' +
        '<div class="preview-body">' +
          '<div class="preview-block preview-block--hero"></div>' +
          '<div class="preview-lines"><span></span><span></span><span></span></div>' +
        '</div>' +
      '</div>';
  }

  function layoutSeq(base) {
    var seq = [];
    for (var i = 0; i < 3; i++) seq.push(((base - 1 + i) % 4) + 1);
    return seq;
  }

  function setText(sel, text) { var el = document.querySelector(sel); if (el) el.textContent = text; }
  function setHref(sel, href) { var el = document.querySelector(sel); if (el) el.setAttribute('href', href); }

  document.title = product.name + ' — TECHBISS Marketplace';
  var metaDesc = document.querySelector('meta[name="description"]');
  if (metaDesc) metaDesc.setAttribute('content', product.tagline);

  setText('[data-crumb-industry]', product.industry);
  setHref('[data-crumb-industry]', 'marketplace.html?industry=' + encodeURIComponent(product.industry));
  setText('[data-crumb-name]', product.name);

  setText('[data-p-industry]', product.industry);
  setText('[data-p-name]', product.name);
  setText('[data-p-tagline]', product.tagline);
  setText('[data-p-description]', product.description);

  // Preview / screens carousel
  var previewHost = document.querySelector('[data-p-preview]');
  var dotsHost = document.querySelector('[data-p-dots]');
  var layouts = layoutSeq(product.layout);
  var screenNames = product.screens || ['Home', 'Details', 'Checkout'];
  var activeScreen = 0;

  function renderPreview() {
    if (previewHost) previewHost.innerHTML = previewFrameHtml(layouts[activeScreen], true);
  }
  function renderDots() {
    if (!dotsHost) return;
    dotsHost.innerHTML = screenNames.map(function (name, i) {
      return '<button type="button" class="' + (i === activeScreen ? 'is-active' : '') + '" data-screen="' + i + '">' + name + '</button>';
    }).join('');
    dotsHost.querySelectorAll('button').forEach(function (btn) {
      btn.addEventListener('click', function () {
        activeScreen = parseInt(btn.getAttribute('data-screen'), 10);
        renderPreview();
        renderDots();
      });
    });
  }
  renderPreview();
  renderDots();

  // Features / tech lists
  var featuresHost = document.querySelector('[data-p-features]');
  if (featuresHost) featuresHost.innerHTML = product.features.map(function (f) { return '<li>' + f + '</li>'; }).join('');
  var techHost = document.querySelector('[data-p-tech]');
  if (techHost) techHost.innerHTML = product.tech.map(function (t) { return '<li>' + t + '</li>'; }).join('');

  // Pricing
  setText('[data-p-price]', String(product.price));

  var configureBtn = document.querySelector('[data-configure-btn]');
  var deployBtn = document.querySelector('[data-deploy-btn]');
  var purchaseBtn = document.querySelector('[data-purchase-btn]');
  var purchaseNote = document.querySelector('[data-purchase-note]');
  var installerHref = 'installer.html?product=' + encodeURIComponent(product.slug);
  if (configureBtn) configureBtn.setAttribute('href', installerHref);
  if (deployBtn) {
    deployBtn.setAttribute('href', installerHref);
    deployBtn.addEventListener('click', function (e) {
      if (deployBtn.hasAttribute('aria-disabled')) e.preventDefault();
    });
  }

  var purchased = false;
  if (purchaseBtn) {
    purchaseBtn.addEventListener('click', function (e) {
      e.preventDefault();
      if (purchased || purchaseBtn.classList.contains('is-loading')) return;
      purchaseBtn.classList.add('is-loading');
      var label = purchaseBtn.querySelector('.btn-text');
      if (label) label.textContent = 'Processing…';
      var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      setTimeout(function () {
        purchased = true;
        purchaseBtn.classList.remove('is-loading');
        purchaseBtn.setAttribute('aria-disabled', 'true');
        if (label) label.textContent = 'Purchased';
        if (deployBtn) { deployBtn.removeAttribute('aria-disabled'); deployBtn.classList.remove('btn-ghost'); deployBtn.classList.add('btn-outline'); }
        if (purchaseNote) purchaseNote.textContent = 'Purchased — you can now configure and deploy ' + product.name + '.';
      }, reduced ? 0 : 900);
    });
  }

  // Related products: same industry first, then by popularity, excluding self
  var related = PRODUCTS.filter(function (p) { return p.slug !== product.slug; })
    .sort(function (a, b) {
      var aSame = a.industry === product.industry ? 1 : 0;
      var bSame = b.industry === product.industry ? 1 : 0;
      if (aSame !== bSame) return bSame - aSame;
      return b.popularity - a.popularity;
    }).slice(0, 4);

  var relatedHost = document.querySelector('[data-related-row]');
  if (relatedHost) {
    relatedHost.innerHTML = related.map(function (p) {
      return '' +
        '<a class="related-card" href="marketplace-product.html?product=' + p.slug + '">' +
          previewFrameHtml(p.layout, false) +
          '<div class="product-card-top"><span class="product-name">' + p.name + '</span><span class="product-price">$' + p.price + '</span></div>' +
          '<div class="product-industry">' + p.industry + '</div>' +
        '</a>';
    }).join('');
  }
})();
