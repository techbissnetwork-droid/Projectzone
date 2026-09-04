/* ==========================================================================
   TECHBISS — Client Dashboard: tab switching + sparklines
   ========================================================================== */
(function () {
  "use strict";

  var navButtons = document.querySelectorAll('[data-panel-btn]');
  var panels = document.querySelectorAll('[data-panel]');
  var validKeys = Array.prototype.map.call(navButtons, function (b) { return b.getAttribute('data-panel-btn'); });

  function activate(key) {
    if (validKeys.indexOf(key) === -1) key = validKeys[0];
    navButtons.forEach(function (b) { b.classList.toggle('is-active', b.getAttribute('data-panel-btn') === key); });
    panels.forEach(function (p) { p.hidden = p.getAttribute('data-panel') !== key; });
  }
  navButtons.forEach(function (btn) {
    btn.addEventListener('click', function () {
      var key = btn.getAttribute('data-panel-btn');
      activate(key);
      history.replaceState(null, '', '#' + key);
    });
  });
  activate((location.hash || '').replace('#', '') || 'overview');

  /* ---------- Sparklines ---------- */
  function sparklineSvg(values, w, h) {
    var min = Math.min.apply(null, values), max = Math.max.apply(null, values);
    var range = (max - min) || 1;
    var step = w / (values.length - 1);
    var pts = values.map(function (v, i) {
      return (i * step).toFixed(1) + ',' + (h - ((v - min) / range) * (h - 4) - 2).toFixed(1);
    }).join(' ');
    return '<svg viewBox="0 0 ' + w + ' ' + h + '" width="' + w + '" height="' + h + '" preserveAspectRatio="none" aria-hidden="true">' +
      '<polyline points="' + pts + '" fill="none" stroke="currentColor" stroke-width="1.4" vector-effect="non-scaling-stroke" stroke-linecap="round" stroke-linejoin="round"/></svg>';
  }
  document.querySelectorAll('[data-sparkline]').forEach(function (el) {
    var raw = el.getAttribute('data-sparkline');
    var values = raw.split(',').map(Number);
    el.innerHTML = sparklineSvg(values, 96, 28);
  });
})();
