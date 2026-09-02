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
                <?php if (!empty($hero['eyebrow'])): ?>
                <span class="hero__flag" data-reveal="fade">
                    <span class="badge badge--accent">TECHBISS</span>
                    <span><?= e($hero['eyebrow']) ?></span>
                    <?= icon('arrow-right') ?>
                </span>
                <?php endif; ?>

                <h1 class="display hero__title" data-reveal="kinetic">
                    <?php
                    // Emphasise the closing phrase without hard-coding the sentence, and
                    // let each word rise into place on its own rather than the whole
                    // line fading in as one block.
                    $title = $s('hero', 'heading', 'Your Digital Business Starts Here.');
                    $words = preg_split('/\s+/', trim($title)) ?: [$title];
                    $tail  = array_splice($words, max(0, count($words) - 2));
                    foreach ($words as $i => $word):
                    ?><span class="kinetic-word" style="--i:<?= $i ?>"><span><?= e($word) ?></span></span> <?php
                    endforeach; ?><span class="kinetic-word" style="--i:<?= count($words) ?>"><span class="text-gradient"><?= e(implode(' ', $tail)) ?></span></span>
                </h1>

                <p class="lead hero__lead" data-reveal style="--reveal-delay:80ms">
                    <?= e($s('hero', 'subheading')) ?>
                </p>

                <div class="hero__actions" data-reveal style="--reveal-delay:150ms">
                    <a class="btn btn--primary btn--lg btn--arrow" href="<?= e(url($s('hero', 'cta_url', '/request'))) ?>" data-magnetic="0.25">
                        <?= e($s('hero', 'cta_label', 'Start Your Digital Journey')) ?><?= icon('arrow-right') ?>
                    </a>
                    <?php if ($projects): ?>
                    <a class="btn btn--quiet btn--lg" href="<?= e(url('/portfolio')) ?>">View Our Work</a>
                    <?php endif; ?>
                </div>

                <?php if ($stats): ?>
                <div class="hero__meta" data-reveal style="--reveal-delay:220ms">
                    <?php foreach (array_slice($stats, 0, 3) as $stat): ?>
                    <div class="hero__meta-item">
                        <span class="hero__meta-value">
                            <?= e($stat['prefix']) ?><?= e($stat['value']) ?><?= e($stat['suffix']) ?>
                        </span>
                        <span class="hero__meta-label"><?= e($stat['label']) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="hero__meta" data-reveal style="--reveal-delay:220ms">
                    <div class="hero__meta-item">
                        <span class="hero__meta-value"><?= count($services) ?: 10 ?></span>
                        <span class="hero__meta-label">Services under one partner</span>
                    </div>
                    <div class="hero__meta-item">
                        <span class="hero__meta-value"><?= count($industries) ?: 15 ?></span>
                        <span class="hero__meta-label">Industries we build for</span>
                    </div>
                    <div class="hero__meta-item">
                        <span class="hero__meta-value">100%</span>
                        <span class="hero__meta-label">Ownership stays with you</span>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="hero-chips" aria-hidden="true">
        <span class="hero-chip"><b></b>Website — kept online</span>
        <span class="hero-chip"><b></b>Backups — done nightly</span>
        <span class="hero-chip"><b></b>Checked — every day</span>
    </div>
</section>

<!-- ================= CAPABILITY MARQUEE ================= -->
<?php if ($services): ?>
<div class="container">
    <hr class="hairline">
</div>
<div class="section--tight" style="padding-block:2rem">
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
</div>
<?php endif; ?>

<!-- ================= SERVICES ================= -->
<?php if ($services): ?>
<section class="section" id="services">
    <div class="container">
        <div class="row row--between mb-6" data-reveal>
            <div class="section-head mb-0" style="margin-bottom:0">
                <p class="eyebrow"><?= e($s('services', 'eyebrow', 'What we do')) ?></p>
                <h2 class="mt-4"><?= e($s('services', 'heading', 'Everything your business needs to operate online.')) ?></h2>
                <p class="lead"><?= e($s('services', 'subheading')) ?></p>
            </div>
        </div>

        <div class="bento" data-reveal-stagger>
            <?php foreach ($services as $i => $service): ?>
                <?= $view->partial('partials/service-card', ['service' => $service, 'i' => $i]) ?>
            <?php endforeach; ?>
        </div>

        <?php if ($s('services', 'cta_label') !== ''): ?>
        <div class="row row--center mt-6" data-reveal>
            <a class="btn btn--ghost btn--arrow" href="<?= e(url($s('services', 'cta_url', '/services'))) ?>">
                <?= e($s('services', 'cta_label')) ?><?= icon('arrow-right') ?>
            </a>
        </div>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<!-- ================= HOW A PROJECT GOES LIVE ================= -->
<?php if ($steps): ?>
<section class="process-rail-sec">
    <div class="container">
        <div class="section-head" data-reveal="rise-blur">
            <p class="eyebrow">How it works</p>
            <h2 class="mt-4">How a project goes live.</h2>
            <p class="lead">The same order every time — nothing skipped to hit a date.</p>
        </div>
        <div class="process-rail">
            <div class="process-rail__line"><i></i></div>
            <div class="rail-steps">
                <?php foreach ($steps as $step): ?>
                <div class="rail-step">
                    <span class="rail-step__dot"></span>
                    <span class="rail-step__no"><?= e($step['step_number'] ?: '01') ?></span>
                    <div class="rail-step__body">
                        <h3><?= e($step['title']) ?></h3>
                        <p><?= e($step['description']) ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ================= INDUSTRIES ================= -->
<?php if ($industries): ?>
<section class="section">
    <div class="container">
        <div class="section-head" data-reveal="rise-blur">
            <p class="eyebrow"><?= e($s('industries', 'eyebrow', 'Industries')) ?></p>
            <h2 class="mt-4"><?= e($s('industries', 'heading', 'Built around how your sector actually works.')) ?></h2>
            <p class="lead"><?= e($s('industries', 'subheading')) ?></p>
        </div>

        <div class="industry-strip" data-reveal-stagger>
            <?php foreach ($industries as $i => $industry): ?>
            <a class="istrip-card" style="--i:<?= $i ?>" data-reveal href="<?= e(url('/industries/' . $industry['slug'])) ?>">
                <span class="istrip-card__no"><?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
                <h3 class="istrip-card__name"><?= e($industry['name']) ?></h3>
                <?php if (!empty($industry['tagline'])): ?>
                <p class="istrip-card__text"><?= e(str_limit($industry['tagline'], 52)) ?></p>
                <?php endif; ?>
            </a>
            <?php endforeach; ?>
        </div>

        <div class="row row--center mt-6" data-reveal>
            <a class="btn btn--ghost btn--arrow" href="<?= e(url('/industries')) ?>">
                <?= e($s('industries', 'cta_label', 'All industries')) ?><?= icon('arrow-right') ?>
            </a>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ================= STATEMENT ================= -->
<section class="statement-sec">
    <div class="container">
        <p data-skew>No sales script. <span class="accent">Tell us what you run</span> and what's missing — we'll show you the real setup, not a mockup.</p>
    </div>
</section>

<!-- ================= CTA ================= -->
<?= $view->partial('partials/cta-band', ['section' => $sections['cta'] ?? null]) ?>
