<?php
/**
 * The transformation visual: offline business → TECHBISS → digital business.
 * Built from CSS transforms and one inline SVG so it costs nothing to load
 * and degrades to a static diagram under reduced-motion.
 *
 * Parameterized so services.php / how-it-works.php can reuse the same
 * orbiting-node set-piece with their own node set and labels instead of a
 * hand-copied variant — every parameter below defaults to exactly what the
 * homepage always passed, so calling this partial with no arguments still
 * renders the original homepage diagram unchanged.
 *
 * @var array|null  $nodes      [['icon'=>.., 'label'=>.., 'a'=>'Ndeg'], ...]
 * @var string|null $ariaLabel  full description read to assistive tech
 * @var string|null $coreLabel  short word/brand in the centre hub
 * @var string|null $coreSub    smaller sub-line under $coreLabel
 * @var string|null $fromLabel  @var string|null $fromValue  left endpoint tag
 * @var string|null $toLabel    @var string|null $toValue    right endpoint tag
 * @var string      $wrapClass  class on the outer wrapper (layout only)
 */
$nodes = $nodes ?? [
    ['icon' => 'globe',   'label' => 'Domain',    'a' => '0deg'],
    ['icon' => 'server',  'label' => 'Hosting',   'a' => '45deg'],
    ['icon' => 'window',  'label' => 'Website',   'a' => '90deg'],
    ['icon' => 'mail',    'label' => 'Email',     'a' => '135deg'],
    ['icon' => 'device',  'label' => 'Mobile',    'a' => '180deg'],
    ['icon' => 'chart',   'label' => 'Analytics', 'a' => '225deg'],
    ['icon' => 'spark',   'label' => 'AI',        'a' => '270deg'],
    ['icon' => 'search',  'label' => 'SEO',       'a' => '315deg'],
];
// Kept as the exact original hardcoded sentence rather than generated from
// $nodes, so the homepage's default call (no arguments) reads to assistive
// tech exactly as it always has. Any other caller passing its own $nodes
// should pass its own $ariaLabel too, describing what it actually shows.
$ariaLabel = $ariaLabel ?? 'A diagram showing an offline business passing through TECHBISS — domain, hosting, website, business email, mobile, analytics, AI and SEO — and emerging as a premium digital business.';
$coreLabel = $coreLabel ?? 'TECHBISS';
$coreSub   = $coreSub   ?? 'TRANSFORM';
$fromLabel = $fromLabel ?? 'Today';
$fromValue = $fromValue ?? 'Offline business';
$toLabel   = $toLabel   ?? 'After';
$toValue   = $toValue   ?? 'Premium digital brand';
$wrapClass = $wrapClass ?? 'hero__visual';
?>
<div class="<?= e($wrapClass) ?>" data-reveal="scale">
    <div class="transform-viz" role="img" aria-label="<?= e($ariaLabel) ?>">

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
                <div class="transform-viz__core-label"><?= e($coreLabel) ?></div>
                <div class="transform-viz__core-sub"><?= e($coreSub) ?></div>
            </div>
        </div>

        <div class="transform-viz__endpoint transform-viz__endpoint--from">
            <span class="transform-viz__endpoint-label"><?= e($fromLabel) ?></span>
            <span class="transform-viz__endpoint-value"><?= e($fromValue) ?></span>
        </div>
        <div class="transform-viz__endpoint transform-viz__endpoint--to">
            <span class="transform-viz__endpoint-label"><?= e($toLabel) ?></span>
            <span class="transform-viz__endpoint-value"><?= e($toValue) ?></span>
        </div>
    </div>
</div>
