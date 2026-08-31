<?php
/** @var array $project @var array $images @var array $technologies @var array $servicesUsed
 *  @var array $related @var array|null $testimonial */
$hero = media_url($project['hero_image'] ?: $project['thumbnail']);
$meta = array_filter([
    'Client'     => $project['client_name'] ?? '',
    'Industry'   => $project['industry_name'] ?? '',
    'Category'   => $project['category_name'] ?? '',
    'Delivered'  => format_date($project['project_date'] ?? null, 'F Y'),
    'Duration'   => $project['duration'] ?? '',
]);
$blocks = array_filter([
    'Overview'  => $project['overview'] ?? '',
    'Challenge' => $project['challenge'] ?? '',
    'Solution'  => $project['solution'] ?? '',
    'Results'   => $project['results'] ?? '',
], static fn ($v) => trim(strip_tags((string) $v)) !== '');
?>
<section class="case-hero" data-accent="<?= e($project['accent'] ?: 'cyan') ?>">
    <div class="page-head__bg" aria-hidden="true"><span class="glow"></span><span class="grid-pattern"></span></div>
    <div class="container">
        <nav class="breadcrumbs" aria-label="Breadcrumb">
            <a href="<?= e(url('/')) ?>">Home</a><?= icon('chevron-right') ?>
            <a href="<?= e(url('/portfolio')) ?>">Work</a><?= icon('chevron-right') ?>
            <span aria-current="page"><?= e($project['title']) ?></span>
        </nav>

        <div class="row row--tight mt-5">
            <?php if (!empty($project['category_name'])): ?>
            <span class="badge badge--accent"><?= e($project['category_name']) ?></span>
            <?php endif; ?>
            <?php if (!empty($project['industry_name'])): ?>
            <span class="badge"><?= e($project['industry_name']) ?></span>
            <?php endif; ?>
        </div>

        <h1 class="mt-4"><?= e($project['title']) ?></h1>
        <?php if (!empty($project['short_description'])): ?>
        <p class="lead mt-4" style="max-width:60ch"><?= e($project['short_description']) ?></p>
        <?php endif; ?>

        <?php
        $links = array_filter([
            ['url' => $project['project_url'], 'label' => 'Visit website',  'icon' => 'external'],
            ['url' => $project['android_url'], 'label' => 'Google Play',    'icon' => 'device'],
            ['url' => $project['ios_url'],     'label' => 'App Store',      'icon' => 'device'],
        ], static fn ($l) => trim((string) $l['url']) !== '');
        ?>
        <?php if ($links): ?>
        <div class="row mt-6">
            <?php foreach ($links as $link): ?>
            <a class="btn btn--ghost btn--sm" href="<?= e($link['url']) ?>" target="_blank" rel="noopener noreferrer nofollow" data-no-transition>
                <?= icon($link['icon']) ?><?= e($link['label']) ?>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($hero !== ''): ?>
        <div class="case-hero__image" data-reveal="scale">
            <img src="<?= e($hero) ?>" alt="<?= e($project['title']) ?>" width="1200" height="720" fetchpriority="high">
        </div>
        <?php endif; ?>
    </div>
</section>

<?php if ($meta): ?>
<section class="section section--tight section--flush-top">
    <div class="container">
        <div class="case-meta" data-reveal>
            <?php foreach ($meta as $label => $value): ?>
            <div class="case-meta__cell">
                <div class="case-meta__label"><?= e($label) ?></div>
                <div class="case-meta__value"><?= e($value) ?></div>
            </div>
            <?php endforeach; ?>
            <?php if ($technologies): ?>
            <div class="case-meta__cell">
                <div class="case-meta__label">Technologies</div>
                <div class="chip-row mt-2">
                    <?php foreach ($technologies as $tech): ?>
                    <span class="tech-chip"><?= e($tech) ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($blocks): ?>
<section class="section section--flush-top">
    <div class="container">
        <div class="split" style="align-items:start">
            <div>
                <?php foreach ($blocks as $label => $html): ?>
                <div class="case-block" data-reveal>
                    <h2 class="eyebrow case-block__label" style="line-height:inherit"><?= e($label) ?></h2>
                    <div class="prose"><?= $html ?></div>
                </div>
                <?php endforeach; ?>
            </div>

            <aside class="stack stack-4" style="position:sticky;top:6.5rem">
                <?php if ($servicesUsed): ?>
                <div class="card" data-reveal="right">
                    <h3 class="card__title" style="font-size:var(--fs-sm)">Services delivered</h3>
                    <div class="stack stack-2 mt-3">
                        <?php foreach ($servicesUsed as $svc): ?>
                        <a class="row row--tight" href="<?= e(url('/services/' . $svc['slug'])) ?>" style="font-size:var(--fs-sm);color:var(--text-soft)">
                            <span class="icon-plate icon-plate--sm"><?= icon((string) $svc['icon']) ?></span>
                            <span><?= e($svc['name']) ?></span>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($testimonial !== null): ?>
                <div class="card quote-card" data-reveal="right">
                    <span class="quote-card__mark"><?= icon('quote') ?></span>
                    <p class="quote-card__text" style="font-size:var(--fs-sm)">“<?= e($testimonial['quote']) ?>”</p>
                    <div class="quote-card__author">
                        <span class="avatar avatar--sm"><?= e(initials((string) $testimonial['client_name'])) ?></span>
                        <div>
                            <div class="quote-card__name"><?= e($testimonial['client_name']) ?></div>
                            <div class="quote-card__role"><?= e($testimonial['company']) ?></div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="card card--pad-lg" data-reveal="right">
                    <h3 class="card__title" style="font-size:var(--fs-sm)">Want something like this?</h3>
                    <p class="card__text">Tell us about your business and we will scope an equivalent build.</p>
                    <div class="card__footer stack stack-2">
                        <a class="btn btn--primary btn--block" href="<?= e(url('/request')) ?>">Tell Us What You Need</a>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($images): ?>
<section class="section">
    <div class="container">
        <div class="section-head" data-reveal>
            <p class="eyebrow">Gallery</p>
            <h2 class="mt-4">A closer look</h2>
        </div>
        <?php
        // Alt text has to tell the images apart: the stored text is often the same
        // line for every shot, so anything not unique to this image gets its
        // position instead.
        $altCounts = array_count_values(array_map(static fn ($im) => trim((string) $im['alt_text']), $images));
        ?>
        <div class="gallery" data-reveal-stagger>
            <?php foreach ($images as $i => $img):
                $src = media_url($img['path']);
                $alt = trim((string) $img['alt_text']);
                if ($alt === '' || ($altCounts[$alt] ?? 0) !== 1) {
                    $alt = ($alt !== '' ? $alt : $project['title'])
                        . ', image ' . ($i + 1) . ' of ' . count($images);
                } ?>
            <figure class="gallery__item" data-lightbox="<?= e($src) ?>"
                    data-lightbox-alt="<?= e($alt) ?>"
                    aria-label="Open image <?= $i + 1 ?> of <?= count($images) ?>" data-reveal style="--i:<?= $i ?>">
                <img src="<?= e($src) ?>" alt="<?= e($alt) ?>" loading="lazy" decoding="async">
                <?php if (!empty($img['caption'])): ?>
                <figcaption class="gallery__caption"><?= e($img['caption']) ?></figcaption>
                <?php endif; ?>
            </figure>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($related): ?>
<section class="section">
    <div class="container">
        <div class="row row--between mb-6" data-reveal>
            <h2>Related projects</h2>
            <a class="link hide-sm" href="<?= e(url('/portfolio')) ?>">All work<?= icon('arrow-right') ?></a>
        </div>
        <div class="slider" data-slider>
            <div class="slider__track slider__track--work" data-reveal-stagger>
            <?php foreach ($related as $i => $rel): ?>
                <?= $view->partial('partials/work-card', ['project' => $rel, 'i' => $i]) ?>
            <?php endforeach; ?>
        </div>
    </div>
    </div>
</section>
<?php endif; ?>

<?= $view->partial('partials/cta-band') ?>
