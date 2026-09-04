/* ==========================================================================
   TECHBISS — Marketplace grid: search, industry filter, sort.
   Reads window.TECHBISS_PRODUCTS (assets/js/products-data.js, loaded first).
   ========================================================================== */
(function () {
  "use strict";
  var PRODUCTS = window.TECHBISS_PRODUCTS || [];
  var INDUSTRIES = ['Restaurant', 'Hotel', 'Retail', 'E-commerce', 'School', 'Hospital', 'Clinic', 'Real Estate', 'Construction', 'Agency', 'Freelancer', 'Startup', 'Corporate', 'Travel', 'Finance', 'Service Business'];

  var grid = document.querySelector('[data-product-grid]');
  var filterRow = document.querySelector('[data-filter-row]');
  var searchInput = document.querySelector('[data-search-input]');
  var sortGroup = document.querySelector('[data-sort-group]');
  var metaEl = document.querySelector('[data-market-meta]');
  var emptyEl = document.querySelector('[data-product-empty]');
  if (!grid) return;

  var params = new URLSearchParams(location.search);
  var state = {
    industry: INDUSTRIES.indexOf(params.get('industry')) > -1 ? params.get('industry') : 'All',
    sort: ['popular', 'newest', 'price'].indexOf(params.get('sort')) > -1 ? params.get('sort') : 'popular',
    query: ''
  };

  function buildFilterRow() {
    var all = ['All'].concat(INDUSTRIES);
    filterRow.innerHTML = all.map(function (name) {
      return '<button type="button" class="filter-btn' + (name === state.industry ? ' is-active' : '') + '" data-filter="' + name + '">' + name + '</button>';
    }).join('');
    filterRow.querySelectorAll('.filter-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        state.industry = btn.getAttribute('data-filter');
        syncUrl();
        render();
      });
    });
  }

  function buildSortGroup() {
    var opts = [['popular', 'Popular'], ['newest', 'Newest'], ['price', 'Price']];
    sortGroup.querySelectorAll('.sort-btn').forEach(function (b) { b.remove(); });
    opts.forEach(function (opt) {
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'sort-btn' + (state.sort === opt[0] ? ' is-active' : '');
      btn.setAttribute('data-sort', opt[0]);
      btn.textContent = opt[1];
      btn.addEventListener('click', function () {
        state.sort = opt[0];
        syncUrl();
        render();
      });
      sortGroup.appendChild(btn);
    });
  }

  function syncUrl() {
    var p = new URLSearchParams();
    if (state.industry !== 'All') p.set('industry', state.industry);
    if (state.sort !== 'popular') p.set('sort', state.sort);
    var qs = p.toString();
    history.replaceState(null, '', location.pathname + (qs ? '?' + qs : ''));
  }

  function previewFrame(p) {
    return '' +
      '<div class="preview-frame" data-layout="' + p.layout + '">' +
        '<div class="preview-chrome"><span></span><span></span><span></span></div>' +
        '<div class="preview-body">' +
          '<div class="preview-block preview-block--hero"></div>' +
          '<div class="preview-lines"><span></span><span></span><span></span></div>' +
        '</div>' +
      '</div>';
  }

  function cardHtml(p) {
    return '' +
      '<a class="product-card" href="marketplace-product.html?product=' + p.slug + '" data-reveal>' +
        previewFrame(p) +
        '<div class="product-card-top"><span class="product-name">' + p.name + '</span><span class="product-price">$' + p.price + '</span></div>' +
        '<div class="product-industry">' + p.industry + '</div>' +
      '</a>';
  }

  function render() {
    var q = state.query.trim().toLowerCase();
    var list = PRODUCTS.filter(function (p) {
      if (state.industry !== 'All' && p.industry !== state.industry) return false;
      if (q && (p.name + ' ' + p.industry + ' ' + p.tagline).toLowerCase().indexOf(q) === -1) return false;
      return true;
    });
    list.sort(function (a, b) {
      if (state.sort === 'popular') return b.popularity - a.popularity;
      if (state.sort === 'newest') return new Date(b.dateAdded) - new Date(a.dateAdded);
      if (state.sort === 'price') return a.price - b.price;
      return 0;
    });

    grid.innerHTML = list.map(cardHtml).join('');
    if (emptyEl) emptyEl.classList.toggle('is-visible', list.length === 0);
    if (metaEl) {
      var industryText = state.industry === 'All' ? 'every industry' : state.industry;
      metaEl.textContent = list.length + (list.length === 1 ? ' theme' : ' themes') + ' — ' + industryText;
    }

    filterRow.querySelectorAll('.filter-btn').forEach(function (b) {
      b.classList.toggle('is-active', b.getAttribute('data-filter') === state.industry);
    });
    sortGroup.querySelectorAll('.sort-btn').forEach(function (b) {
      b.classList.toggle('is-active', b.getAttribute('data-sort') === state.sort);
    });

    // Newly injected [data-reveal] cards: reveal immediately (already-mounted
    // content re-filtering shouldn't replay a scroll-in animation each time).
    grid.querySelectorAll('[data-reveal]').forEach(function (el) { el.classList.add('reveal-in'); });
  }

  if (searchInput) {
    searchInput.addEventListener('input', function () {
      state.query = searchInput.value;
      render();
    });
  }

  buildFilterRow();
  buildSortGroup();
  render();
})();
