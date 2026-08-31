<?php
/**
 * The transformation visual: offline business → TECHBISS → digital business.
 * Built from CSS transforms and one inline SVG so it costs nothing to load
 * and degrades to a static diagram under reduced-motion.
 */
$nodes = [
    ['icon' => 'globe',   'label' => 'Domain',    'a' => '0deg'],
    ['icon' => 'server',  'label' => 'Hosting',   'a' => '45deg'],
    ['icon' => 'window',  'label' => 'Website',   'a' => '90deg'],
    ['icon' => 'mail',    'label' => 'Email',     'a' => '135deg'],
    ['icon' => 'device',  'label' => 'Mobile',    'a' => '180deg'],
    ['icon' => 'chart',   'label' => 'Analytics', 'a' => '225deg'],
    ['icon' => 'spark',   'label' => 'AI',        'a' => '270deg'],
    ['icon' => 'search',  'label' => 'SEO',       'a' => '315deg'],
];
?>
<div class="hero__visual" data-reveal="scale">
    <div class="transform-viz" role="img"
         aria-label="A diagram showing an offline business passing through TECHBISS — domain, hosting, website, business email, mobile, analytics, AI and SEO — and emerging as a premium digital business.">

        <span class="transform-viz__ring" style="--inset:2%"></span>
        <span class="transform-viz__ring transform-viz__ring--spin" style="--inset:2%"></span>
        <span class="transform-viz__ring" style="--inset:18%"></span>
        <span class="transform-viz__ring transform-viz__ring--spin-rev" style="--inset:18%"></span>
        <span class="transform-viz__ring" style="--inset:34%"></span>

        <svg class="transform-viz__flow" viewBox="0 0 400 400" aria-hidden="true" preserveAspectRatio="none">
            <path d="M40 60 C 130 60, 150 200, 200 200"/>
            <path d="M40 60 C 130 60, 150 200, 200 200" class="flow-live"/>
            <path d="M360 340 C 270 340, 250 200, 200 200"/>
            <path d="M360 340 C 270 340, 250 200, 200 200" class="flow-live flow-live--2"/>
            <path d="M200 200 L 200 60"/>
            <path d="M200 200 L 200 60" class="flow-live flow-live--3"/>
        </svg>

        <div class="transform-viz__orbit" aria-hidden="true">
            <?php foreach ($nodes as $i => $node): ?>
            <div class="transform-viz__node<?= $i % 3 === 0 ? ' transform-viz__node--active' : '' ?>"
                 style="--a:<?= e($node['a']) ?>" title="<?= e($node['label']) ?>">
                <span><?= icon($node['icon']) ?></span>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="transform-viz__core">
            <span class="transform-viz__pulse" aria-hidden="true"></span>
            <span class="transform-viz__pulse transform-viz__pulse--2" aria-hidden="true"></span>
            <div>
                <div class="transform-viz__core-label">TECHBISS</div>
                <div class="transform-viz__core-sub">TRANSFORM</div>
            </div>
        </div>

        <div class="transform-viz__endpoint transform-viz__endpoint--from">
            <span class="transform-viz__endpoint-label">Today</span>
            <span class="transform-viz__endpoint-value">Offline business</span>
        </div>
        <div class="transform-viz__endpoint transform-viz__endpoint--to">
            <span class="transform-viz__endpoint-label">After</span>
            <span class="transform-viz__endpoint-value">Premium digital brand</span>
        </div>
    </div>
</div>
