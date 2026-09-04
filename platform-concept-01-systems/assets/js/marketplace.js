/* ==========================================================================
   TECHBISS PLATFORM — marketplace.html page script
   Renders the product grid from window.TECHBISS_PRODUCTS, then owns search,
   multi-select industry filters, sort (actually reorders the DOM), the
   ?industry= / ?sort= deep-link params, and the preview modal.
   ========================================================================== */
(function(){
  "use strict";
  var PRODUCTS = window.TECHBISS_PRODUCTS || [];
  var grid = document.getElementById('productGrid');
  var emptyMsg = document.getElementById('mpEmpty');
  var resultCount = document.getElementById('mpResultCount');
  var searchInput = document.getElementById('mpSearch');
  var sortSelect = document.getElementById('mpSort');
  var filterWrap = document.getElementById('mpFilters');
  if (!grid) return;

  var activeIndustries = []; // empty = "all"

  function cardHTML(p){
    var featureItems = p.features.slice(0, 3).map(function(f){ return '<li>' + f + '</li>'; }).join('');
    return (
      '<article class="product-card" data-id="' + p.id + '" data-industry="' + p.industry + '" data-name="' + p.name.toLowerCase() + '" ' +
        'data-features="' + p.features.join(' ').toLowerCase() + '" data-price="' + p.price + '" data-deployments="' + p.deployments + '" data-released="' + p.released + '">' +
        window.TECHBISS_PREVIEW_HTML(p) +
        '<div class="pc-body">' +
          '<div class="pc-top"><span class="tag">' + p.industry + '</span><span class="pc-rating mono">★ ' + p.rating.toFixed(1) + '</span></div>' +
          '<h3>' + p.name + '</h3>' +
          '<p class="pc-tagline">' + p.tagline + '</p>' +
          '<ul class="pc-features">' + featureItems + '</ul>' +
          '<div class="pc-foot">' +
            '<span class="pc-price mono">' + window.TECHBISS_FORMAT_PRICE(p.price) + '</span>' +
            '<span class="pc-deployments mono">' + p.deployments.toLocaleString('en-US') + ' deployments</span>' +
          '</div>' +
          '<div class="pc-actions">' +
            '<button type="button" class="btn btn-outline btn-sm" data-preview-btn="' + p.id + '">Preview</button>' +
            '<a href="marketplace-product.html?id=' + p.id + '" class="btn btn-primary btn-sm">View Details</a>' +
          '</div>' +
        '</div>' +
      '</article>'
    );
  }

  // initial render (order = data-file order = "popular"-ish default; real sort applied below)
  grid.innerHTML = PRODUCTS.map(cardHTML).join('');

  function currentCards(){ return Array.prototype.slice.call(grid.querySelectorAll('.product-card')); }

  function applyFilters(){
    var q = (searchInput.value || '').trim().toLowerCase();
    var visibleCount = 0;
    currentCards().forEach(function(card){
      var matchesIndustry = activeIndustries.length === 0 || activeIndustries.indexOf(card.getAttribute('data-industry')) !== -1;
      var haystack = card.getAttribute('data-name') + ' ' + card.getAttribute('data-features') + ' ' + card.getAttribute('data-industry').toLowerCase();
      var matchesSearch = !q || haystack.indexOf(q) !== -1;
      var show = matchesIndustry && matchesSearch;
      card.classList.toggle('is-hidden', !show);
      if (show) visibleCount++;
    });
    resultCount.textContent = visibleCount + (visibleCount === 1 ? ' PRODUCT' : ' PRODUCTS');
    emptyMsg.hidden = visibleCount !== 0;
    grid.hidden = visibleCount === 0;
  }

  function applySort(){
    var mode = sortSelect.value;
    var cards = currentCards();
    cards.sort(function(a, b){
      if (mode === 'newest') return new Date(b.getAttribute('data-released')) - new Date(a.getAttribute('data-released'));
      if (mode === 'price-asc') return Number(a.getAttribute('data-price')) - Number(b.getAttribute('data-price'));
      if (mode === 'price-desc') return Number(b.getAttribute('data-price')) - Number(a.getAttribute('data-price'));
      return Number(b.getAttribute('data-deployments')) - Number(a.getAttribute('data-deployments')); // popular (default)
    });
    var frag = document.createDocumentFragment();
    cards.forEach(function(card){ frag.appendChild(card); });
    grid.appendChild(frag);
  }

  searchInput.addEventListener('input', applyFilters);

  sortSelect.addEventListener('change', function(){ applySort(); applyFilters(); });

  filterWrap.addEventListener('click', function(e){
    var chip = e.target.closest('[data-industry-filter]');
    if (!chip) return;
    var val = chip.getAttribute('data-industry-filter');
    if (val === 'all') {
      activeIndustries = [];
    } else {
      var idx = activeIndustries.indexOf(val);
      if (idx === -1) activeIndustries.push(val); else activeIndustries.splice(idx, 1);
    }
    syncChips();
    applyFilters();
  });

  function syncChips(){
    var allChip = filterWrap.querySelector('[data-industry-filter="all"]');
    allChip.classList.toggle('is-active', activeIndustries.length === 0);
    filterWrap.querySelectorAll('[data-industry-filter]:not([data-industry-filter="all"])').forEach(function(chip){
      chip.classList.toggle('is-active', activeIndustries.indexOf(chip.getAttribute('data-industry-filter')) !== -1);
    });
  }

  document.getElementById('mpClearFilters').addEventListener('click', function(){
    activeIndustries = [];
    searchInput.value = '';
    syncChips();
    applyFilters();
  });

  // ---- ?industry= / ?sort= deep links ----
  var params = new URLSearchParams(location.search);
  var industryParam = params.get('industry');
  var sortParam = params.get('sort');
  if (industryParam && PRODUCTS.some(function(p){ return p.industry === industryParam; })) {
    activeIndustries = [industryParam];
  }
  if (sortParam && sortSelect.querySelector('option[value="' + sortParam + '"]')) {
    sortSelect.value = sortParam;
  }
  syncChips();
  applySort();
  applyFilters();

  // ---- Preview modal ----
  var modal = document.getElementById('previewModal');
  var modalTitle = document.getElementById('previewModalTitle');
  var modalBody = document.getElementById('previewModalBody');
  var lastFocused = null;

  function openModal(product){
    lastFocused = document.activeElement;
    modalTitle.textContent = product.name.toUpperCase() + ' — PREVIEW';
    modalBody.innerHTML =
      window.TECHBISS_PREVIEW_HTML(product, { large: true }) +
      '<div class="modal-info">' +
        '<div class="pc-top"><span class="tag">' + product.industry + '</span><span class="pc-rating mono">★ ' + product.rating.toFixed(1) + '</span></div>' +
        '<h3>' + product.name + '</h3>' +
        '<p>' + product.tagline + '</p>' +
        '<div class="pc-foot"><span class="pc-price mono">' + window.TECHBISS_FORMAT_PRICE(product.price) + '</span>' +
        '<a href="marketplace-product.html?id=' + product.id + '" class="btn-link">View Full Details →</a></div>' +
      '</div>';
    modal.hidden = false;
    document.body.style.overflow = 'hidden';
    var closeBtn = modal.querySelector('[data-modal-close]');
    if (closeBtn) closeBtn.focus();
  }
  function closeModal(){
    modal.hidden = true;
    document.body.style.overflow = '';
    if (lastFocused) lastFocused.focus();
  }

  grid.addEventListener('click', function(e){
    var btn = e.target.closest('[data-preview-btn]');
    if (!btn) return;
    var product = PRODUCTS.filter(function(p){ return p.id === btn.getAttribute('data-preview-btn'); })[0];
    if (product) openModal(product);
  });
  modal.addEventListener('click', function(e){
    if (e.target === modal || e.target.closest('[data-modal-close]')) closeModal();
  });
  document.addEventListener('keydown', function(e){
    if (e.key === 'Escape' && !modal.hidden) closeModal();
  });

})();
