<?php
/** Dots and arrows for a [data-carousel]. The script wires them by attribute. */
if (!class_exists('Settings')) { http_response_code(404); exit('Not found.'); }
?>
<div class="carousel__nav">
  <div class="carousel__dots" data-dots></div>
  <button class="carousel__arrow" type="button" data-prev aria-label="Previous">
    <svg viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M10 2 4 8l6 6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
  </button>
  <button class="carousel__arrow" type="button" data-next aria-label="Next">
    <svg viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="m6 2 6 6-6 6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
  </button>
</div>
