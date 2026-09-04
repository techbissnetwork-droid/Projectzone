/* ==========================================================================
   TECHBISS Marketplace — marketplace.html only
   Real client-side filter (multi-select industry chips), live search,
   sort (re-orders the DOM) and a grid/list view toggle. Reads ?industry=
   and ?sort= from the URL so other pages can deep-link into a filtered view.
   ========================================================================== */
(function(){
  "use strict";
  if (!window.TB_PRODUCTS) return;
  var PRODUCTS = window.TB_PRODUCTS.all;
  var INDUSTRIES = window.TB_PRODUCTS.industries;

  var filtersWrap = document.getElementById('mp-filters');
  var searchInput = document.getElementById('mp-search');
  var sortSelect = document.getElementById('mp-sort');
  var resultsEl = document.getElementById('mp-results');
  var emptyEl = document.getElementById('mp-empty');
  var countEl = document.getElementById('mp-count');
  var countLabelEl = document.getElementById('mp-count-label');
  var filterCountEl = document.getElementById('mp-filter-count');
  var clearBtn = document.getElementById('mp-clear');
  var emptyClearBtn = document.querySelector('[data-empty-clear]');
  var viewButtons = document.querySelectorAll('[data-view]');
  if (!filtersWrap || !resultsEl) return;

  var params = new URLSearchParams(location.search);
  var state = { industries: new Set(), query: '', sort: 'popular', view: 'grid' };

  var initialIndustry = params.get('industry');
  if (initialIndustry && INDUSTRIES.indexOf(initialIndustry) !== -1) state.industries.add(initialIndustry);
  var initialSort = params.get('sort');
  if (initialSort === 'newest' || initialSort === 'price' || initialSort === 'popular') state.sort = initialSort;

  filtersWrap.innerHTML = INDUSTRIES.map(function(ind){
    return '<button type="button" class="chip" data-industry="' + ind + '">' + ind + '</button>';
  }).join('');
  var chips = Array.prototype.slice.call(filtersWrap.children);

  function syncControls(){
    chips.forEach(function(c){
      c.classList.toggle('is-active', state.industries.has(c.getAttribute('data-industry')));
    });
    if (sortSelect) sortSelect.value = state.sort;
    viewButtons.forEach(function(b){
      b.setAttribute('aria-pressed', b.getAttribute('data-view') === state.view ? 'true' : 'false');
    });
    var n = state.industries.size;
    if (n > 0) {
      filterCountEl.hidden = false;
      filterCountEl.textContent = n + (n === 1 ? ' filter active' : ' filters active');
      clearBtn.hidden = false;
    } else {
      filterCountEl.hidden = true;
      clearBtn.hidden = true;
    }
  }

  function matches(p){
    if (state.industries.size && !state.industries.has(p.industry)) return false;
    if (state.query) {
      var hay = (p.name + ' ' + p.industry + ' ' + p.tagline + ' ' + p.features.join(' ')).toLowerCase();
      if (hay.indexOf(state.query) === -1) return false;
    }
    return true;
  }
  function sortFn(a, b){
    if (state.sort === 'newest') return new Date(b.dateAdded) - new Date(a.dateAdded);
    if (state.sort === 'price') return a.price - b.price;
    return b.popularity - a.popularity;
  }

  function render(){
    syncControls();
    var list = PRODUCTS.filter(matches).sort(sortFn);
    resultsEl.className = state.view === 'list' ? 'product-list' : 'product-grid';
    if (!list.length) {
      resultsEl.innerHTML = '';
      emptyEl.hidden = false;
    } else {
      emptyEl.hidden = true;
      resultsEl.innerHTML = list.map(function(p){ return window.TB_PRODUCTS.renderCard(p, { view: state.view }); }).join('');
    }
    countEl.textContent = list.length;
    countLabelEl.textContent = list.length === 1 ? 'product' : 'products';
  }

  chips.forEach(function(c){
    c.addEventListener('click', function(){
      var ind = c.getAttribute('data-industry');
      if (state.industries.has(ind)) state.industries.delete(ind); else state.industries.add(ind);
      render();
    });
  });
  if (searchInput) searchInput.addEventListener('input', function(){
    state.query = searchInput.value.trim().toLowerCase();
    render();
  });
  if (sortSelect) sortSelect.addEventListener('change', function(){
    state.sort = sortSelect.value;
    render();
  });
  viewButtons.forEach(function(b){
    b.addEventListener('click', function(){
      state.view = b.getAttribute('data-view');
      render();
    });
  });
  function clearAll(){
    state.industries.clear();
    state.query = '';
    if (searchInput) searchInput.value = '';
    render();
  }
  clearBtn.addEventListener('click', clearAll);
  if (emptyClearBtn) emptyClearBtn.addEventListener('click', clearAll);

  render();
})();
