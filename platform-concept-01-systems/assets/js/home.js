/* ==========================================================================
   TECHBISS PLATFORM — index.html page script
   Everything generic (reveals, counters, accordion, theme, nav) is handled by
   main.js. This file only owns the one bespoke widget on this page: the
   industry transformation chain, where clicking a stage swaps the detail line.
   ========================================================================== */
(function(){
  "use strict";

  document.querySelectorAll('.industry-card').forEach(function(card){
    var stages = card.querySelectorAll('.chain-stage');
    var detail = card.querySelector('[data-chain-detail]');
    if (!stages.length || !detail) return;
    stages.forEach(function(stage){
      stage.addEventListener('click', function(){
        stages.forEach(function(s){ s.classList.remove('is-active'); s.setAttribute('aria-selected', 'false'); });
        stage.classList.add('is-active');
        stage.setAttribute('aria-selected', 'true');
        detail.textContent = stage.getAttribute('data-detail') || '';
      });
    });
  });

})();
