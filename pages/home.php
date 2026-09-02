<?php
/**
 * Homepage. Every band below is driven by the page_sections / section_items
 * tables or by a content repository — nothing on this page is hard-coded copy.
 *
 * @var array $sections @var array $services @var array $projects
 * @var array $industries @var array $testimonials @var array $stats
 */
$s = static fn (string $key, string $field, string $fallback = ''): string
    => (string) ($sections[$key][$field] ?? $fallback);

$hero = $sections['hero'] ?? [];
?>

<!-- ================= HERO ================= -->
<section class="hero">
    <div class="hero__bg" aria-hidden="true">
        <canvas data-ambient aria-hidden="true"></canvas>
    </div>

    <div class="container">
        <div class="hero__inner">
            <div class="hero__copy">
                <span class="hero__flag" data-reveal="fade">
                    <span class="hero__flag-dot"></span>
                    <span><?= e($hero['eyebrow'] ?: 'One partner, everything digital') ?></span>
                </span>

                <h1 class="display hero__title" data-reveal="kinetic">
                    <?php
                    // Emphasise the closing phrase without hard-coding the sentence, and
                    // let each word rise into place on its own rather than the whole
                    // line fading in as one block.
                    $title = $s('hero', 'heading', 'Everything your business needs — live.');
                    $words = preg_split('/\s+/', trim($title)) ?: [$title];
                    $tail  = array_splice($words, max(0, count($words) - 1));
                    foreach ($words as $i => $word):
                    ?><span class="kinetic-word" style="--i:<?= $i ?>"><span><?= e($word) ?></span></span> <?php
                    endforeach; ?><span class="kinetic-word" style="--i:<?= count($words) ?>"><span class="text-gradient"><?= e(implode(' ', $tail)) ?></span></span>
                </h1>

                <p class="lead hero__lead" data-reveal style="--reveal-delay:80ms">
                    <?= e($s('hero', 'subheading', 'Domain, hosting, the site, email, and the app you have in mind — designed, built, and kept running by one team.')) ?>
                </p>

                <div class="hero__actions" data-reveal style="--reveal-delay:150ms">
                    <a class="btn btn--primary btn--lg btn--arrow" href="<?= e(url($s('hero', 'cta_url', '/request'))) ?>" data-magnetic="0.25">
                        <?= e($s('hero', 'cta_label', 'Tell us what you need')) ?><?= icon('arrow-right') ?>
                    </a>
                    <?php if ($projects): ?>
                    <a class="btn btn--quiet btn--lg" href="<?= e(url('/portfolio')) ?>" data-magnetic="0.2">View our work</a>
                    <?php endif; ?>
                </div>

                <div class="hero__meta" data-reveal style="--reveal-delay:220ms">
                    <?php if ($stats): ?>
                        <?php foreach (array_slice($stats, 0, 3) as $stat): ?>
                        <div class="hero__meta-item">
                            <span class="hero__meta-value"><?= e($stat['prefix']) ?><span data-count="<?= e((string) $stat['value']) ?>">0</span><?= e($stat['suffix']) ?></span>
                            <span class="hero__meta-label"><?= e($stat['label']) ?></span>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="hero__meta-item">
                            <span class="hero__meta-value"><span data-count="<?= count($services) ?: 6 ?>">0</span></span>
                            <span class="hero__meta-label">Services under one partner</span>
                        </div>
                        <div class="hero__meta-item">
                            <span class="hero__meta-value"><span data-count="<?= count($industries) ?: 15 ?>">0</span></span>
                            <span class="hero__meta-label">Industries we build for</span>
                        </div>
                        <div class="hero__meta-item">
                            <span class="hero__meta-value"><span data-count="100">0</span>%</span>
                            <span class="hero__meta-label">Ownership stays with you</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ================= CAPABILITY TICKER ================= -->
<?php if ($services): ?>
<div class="marquee" aria-hidden="true">
    <div class="marquee__track">
        <?php // The list runs twice so the scroll can loop seamlessly; the second
              // pass is marked so CSS can drop it when the animation is off and
              // the track wraps, which would otherwise show every name twice.
        for ($pass = 0; $pass < 2; $pass++): ?>
            <?php foreach ($services as $svc): ?>
            <span class="marquee__item<?= $pass === 1 ? ' marquee__item--dup' : '' ?>"><?= icon((string) ($svc['icon'] ?: 'spark')) ?><?= e($svc['name']) ?></span>
            <?php endforeach; ?>
        <?php endfor; ?>
    </div>
</div>
<p class="sr-only">Services offered: <?= e(implode(', ', array_column($services, 'name'))) ?>.</p>
<?php endif; ?>

<!-- ================= SERVICES ================= -->
<?php if ($services): ?>
<section class="section" id="services">
    <div class="container">
        <div class="section-head section-head--center" data-reveal>
            <p class="eyebrow"><?= e($s('services', 'eyebrow', 'What we build')) ?></p>
            <h2 class="mt-4"><?= e($s('services', 'heading', 'Six disciplines, run as one shop.')) ?></h2>
        </div>

        <div class="bento" data-reveal-stagger>
            <?php foreach ($services as $i => $service): ?>
                <?= $view->partial('partials/service-card', ['service' => $service, 'i' => $i, 'numbered' => true]) ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ================= HOW A PROJECT GOES LIVE ================= -->
<?php if ($steps): ?>
<section class="section">
    <div class="container">
        <div class="section-head section-head--center" data-reveal>
            <p class="eyebrow">How it works</p>
            <h2 class="mt-4">Four steps, same order every time.</h2>
        </div>
        <div class="rail" style="max-width:760px;margin-inline:auto">
            <?php foreach (array_slice($steps, 0, 4) as $step): ?>
            <div class="rstep">
                <span class="rstep__no"><?= e($step['step_number'] ?: '01') ?></span>
                <div>
                    <h3><?= e($step['title']) ?></h3>
                    <p><?= e($step['description']) ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ================= CTA ================= -->
<?= $view->partial('partials/cta-band', ['section' => $sections['cta'] ?? null]) ?>
