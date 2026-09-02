<?php
/**
 * The TECHBISS mark, defined once per page as an SVG symbol.
 *
 * Inlined rather than loaded as <img> so the wordmark beside it can follow the
 * theme through currentColor — an SVG referenced by <img> is an isolated
 * document and cannot inherit it. Defining it once and referencing it with
 * <use> keeps the gradient id unique on the page.
 *
 * Geometry matches assets/images/brand/logo-mark.svg exactly.
 */
?>
<svg width="0" height="0" aria-hidden="true" focusable="false"
     style="position:absolute;width:0;height:0;overflow:hidden" data-brand-sprite>
    <defs>
        <linearGradient id="tb-mark-gradient" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0" stop-color="#C8FF4D"/>
            <stop offset="0.55" stop-color="#77EC8B"/>
            <stop offset="1" stop-color="#26D9C9"/>
        </linearGradient>
        <clipPath id="tb-mark-clip"><rect width="100" height="100" rx="24" ry="24"/></clipPath>
    </defs>
    <symbol id="tb-mark" viewBox="0 0 100 100">
        <rect width="100" height="100" rx="24" ry="24" fill="url(#tb-mark-gradient)"/>
        <g clip-path="url(#tb-mark-clip)" fill="none" stroke="#fff">
            <circle cx="50" cy="50" r="43" stroke-opacity="0.15" stroke-width="1.6"/>
            <circle cx="50" cy="50" r="49.5" stroke-opacity="0.11" stroke-width="1.6"/>
        </g>
        <g fill="#fff">
            <rect x="22" y="26" width="56" height="14" rx="2"/>
            <rect x="43" y="38" width="14" height="36" rx="2"/>
        </g>
    </symbol>
</svg>
