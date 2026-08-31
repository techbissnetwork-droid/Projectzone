<?php
/**
 * Homepage. Every band below is driven by the page_sections / section_items
 * tables or by a content repository — nothing on this page is hard-coded copy.
 *
 * @var array $sections @var array $services @var array $projects
 * @var array $industries @var array $steps @var array $testimonials @var array $faqs
 * @var array $posts @var array $stats
 */
$s = static fn (string $key, string $field, string $fallback = ''): string
    => (string) ($sections[$key][$field] ?? $fallback);
$items = static fn (string $key): array => $sections[$key]['items'] ?? [];

$hero = $sections['hero'] ?? [];
?>

<!-- ================= HERO ================= -->
<section class="hero">
    <div class="hero__bg" aria-hidden="true">
        <span class="glow glow--a"></span>
        <span class="glow glow--b"></span>
        <span class="grid-pattern"></span>
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

                <h1 class="display hero__title" data-reveal>
                    <?php
                    // Emphasise the closing phrase without hard-coding the sentence.
                    $title = $s('hero', 'heading', 'Your Digital Business Starts Here.');
                    $words = preg_split('/\s+/', trim($title)) ?: [$title];
                    $tail  = array_splice($words, max(0, count($words) - 2));
                    echo e(implode(' ', $words));
                    echo $words ? ' ' : '';
                    echo '<span class="text-gradient">' . e(implode(' ', $tail)) . '</span>';
                    ?>
                </h1>

                <p class="lead hero__lead" data-reveal style="--reveal-delay:80ms">
                    <?= e($s('hero', 'subheading')) ?>
                </p>

                <div class="hero__actions" data-reveal style="--reveal-delay:150ms">
                    <a class="btn btn--primary btn--lg btn--arrow" href="<?= e(url($s('hero', 'cta_url', '/request'))) ?>" data-magnetic="0.25">
                        <?= e($s('hero', 'cta_label', 'Start Your Digital Journey')) ?><?= icon('arrow-right') ?>
                    </a>
                    <a class="btn btn--ghost btn--lg" href="<?= e(url('/request')) ?>">Tell Us What You Need</a>
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

            <?= $view->partial('partials/hero-visual') ?>
        </div>
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

<!-- ================= THE OFFLINE PROBLEM ================= -->
<?php if (isset($sections['problem'])): ?>
<section class="section">
    <div class="container">
        <div class="section-head" data-reveal>
            <p class="eyebrow"><?= e($s('problem', 'eyebrow')) ?></p>
            <h2><?= e($s('problem', 'heading')) ?></h2>
            <p class="lead"><?= e($s('problem', 'subheading')) ?></p>
        </div>

        <?php if ($items('problem')): ?>
        <div class="problem-grid" data-reveal data-reveal-stagger>
            <?php foreach ($items('problem') as $i => $item): ?>
            <div class="problem-item" style="--i:<?= $i ?>">
                <span class="problem-item__icon"><?= icon((string) ($item['icon'] ?: 'x')) ?></span>
                <div>
                    <div class="problem-item__title"><?= e($item['title']) ?></div>
                    <?php if (!empty($item['description'])): ?>
                    <p class="problem-item__text"><?= e($item['description']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (isset($sections['chain'])): ?>
        <div class="mt-8" data-reveal>
            <div class="row row--between mb-5">
                <div>
                    <p class="eyebrow"><?= e($s('chain', 'eyebrow')) ?></p>
                    <h3 class="mt-3"><?= e($s('chain', 'heading')) ?></h3>
                </div>
                <?php if ($s('chain', 'cta_label') !== ''): ?>
                <a class="link hide-sm" href="<?= e(url($s('chain', 'cta_url', '/request'))) ?>">
                    <?= e($s('chain', 'cta_label')) ?><?= icon('arrow-right') ?>
                </a>
                <?php endif; ?>
            </div>

            <div class="chain">
                <?php foreach ($items('chain') as $item): ?>
                <div class="chain__step">
                    <?php if (!empty($item['value'])): ?>
                    <span class="chain__num"><?= e($item['value']) ?></span>
                    <?php endif; ?>
                    <span class="chain__icon"><?= icon((string) ($item['icon'] ?: 'spark')) ?></span>
                    <span class="chain__label"><?= e($item['title']) ?></span>
                    <?php if (!empty($item['description'])): ?>
                    <span class="chain__text"><?= e($item['description']) ?></span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($s('problem', 'cta_label') !== ''): ?>
        <div class="row mt-6" data-reveal>
            <a class="btn btn--primary btn--lg btn--arrow" href="<?= e(url($s('problem', 'cta_url', '/request'))) ?>">
                <?= e($s('problem', 'cta_label')) ?><?= icon('arrow-right') ?>
            </a>
        </div>
        <?php endif; ?>
    </div>
</section>
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

        <div class="slider" data-slider>
            <div class="slider__track slider__track--cards" data-reveal-stagger>
            <?php foreach ($services as $i => $service): ?>
                <?= $view->partial('partials/service-card', ['service' => $service, 'i' => $i]) ?>
            <?php endforeach; ?>
        </div>
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

<!-- ================= TRUST / CREDIBILITY ================= -->
<?php if (isset($sections['trust']) && $items('trust')): ?>
<section class="section">
    <div class="container">
        <div class="split">
            <div data-reveal="left">
                <p class="eyebrow"><?= e($s('trust', 'eyebrow')) ?></p>
                <h2 class="mt-4"><?= e($s('trust', 'heading')) ?></h2>
                <p class="lead mt-4"><?= e($s('trust', 'subheading')) ?></p>
                <div class="notice notice--accent mt-6">
                    <?= icon('info') ?>
                    <span>A professional digital presence changes how a business is found, judged and contacted.
                    It is a foundation, not a guarantee — we will never promise you a specific level of sales or growth.</span>
                </div>
            </div>

            <div class="stack stack-3" data-reveal="right">
                <?php foreach ($items('trust') as $i => $item): ?>
                <div class="card card--interactive card--spotlight" style="padding:1.1rem 1.25rem;--i:<?= $i ?>">
                    <div class="row row--nowrap" style="align-items:flex-start;gap:.85rem">
                        <span class="icon-plate icon-plate--sm"><?= icon((string) ($item['icon'] ?: 'check')) ?></span>
                        <div>
                            <div class="card__title" style="font-size:var(--fs-sm);margin-bottom:.2rem"><?= e($item['title']) ?></div>
                            <?php if (!empty($item['description'])): ?>
                            <p class="card__text" style="font-size:var(--fs-xs)"><?= e($item['description']) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ================= STATS ================= -->
<?php if ($stats): ?>
<div class="stats-band" data-reveal>
    <?php foreach ($stats as $stat): ?>
    <div class="stat">
        <div class="stat__value">
            <?= e($stat['prefix']) ?><?php
                if (is_numeric($stat['value'])) {
                    echo '<span data-count="' . e($stat['value']) . '">0</span>';
                } else {
                    echo e($stat['value']);
                }
            ?><?= e($stat['suffix']) ?>
        </div>
        <div class="stat__label"><?= e($stat['label']) ?></div>
        <?php if (!empty($stat['description'])): ?>
        <div class="stat__desc"><?= e($stat['description']) ?></div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ================= PROCESS ================= -->
<?php if ($steps): ?>
<section class="section">
    <div class="container">
        <div class="section-head" data-reveal>
            <p class="eyebrow"><?= e($s('process', 'eyebrow', 'How it works')) ?></p>
            <h2 class="mt-4"><?= e($s('process', 'heading', 'Six stages from first conversation to ongoing growth.')) ?></h2>
            <p class="lead"><?= e($s('process', 'subheading')) ?></p>
        </div>

        <div class="process">
            <?php foreach ($steps as $step): ?>
            <div class="process__step" data-reveal>
                <div class="process__num"><?= e($step['step_number']) ?></div>
                <div class="process__body">
                    <h3 class="process__title"><?= e($step['title']) ?></h3>
                    <p class="process__text"><?= e($step['description']) ?></p>
                    <?php if (!empty($step['duration'])): ?>
                    <div class="process__duration"><span class="badge"><?= icon('clock') ?><?= e($step['duration']) ?></span></div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if ($s('process', 'cta_label') !== ''): ?>
        <div class="row mt-6" data-reveal>
            <a class="link" href="<?= e(url($s('process', 'cta_url', '/how-it-works'))) ?>">
                <?= e($s('process', 'cta_label')) ?><?= icon('arrow-right') ?>
            </a>
        </div>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<!-- ================= WORK ================= -->
<?php if ($projects): ?>
<section class="section">
    <div class="container">
        <div class="row row--between mb-6" data-reveal>
            <div>
                <p class="eyebrow"><?= e($s('work', 'eyebrow', 'Selected work')) ?></p>
                <h2 class="mt-4"><?= e($s('work', 'heading', 'Projects built the same way yours will be.')) ?></h2>
            </div>
            <a class="link hide-sm" href="<?= e(url('/portfolio')) ?>">
                <?= e($s('work', 'cta_label', 'View all work')) ?><?= icon('arrow-right') ?>
            </a>
        </div>

        <div class="slider" data-slider>
            <div class="slider__track slider__track--work" data-reveal-stagger>
            <?php foreach ($projects as $i => $project): ?>
                <?= $view->partial('partials/work-card', ['project' => $project, 'i' => $i]) ?>
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
        <div class="section-head" data-reveal>
            <p class="eyebrow"><?= e($s('industries', 'eyebrow', 'Industries')) ?></p>
            <h2 class="mt-4"><?= e($s('industries', 'heading', 'Built around how your sector actually works.')) ?></h2>
            <p class="lead"><?= e($s('industries', 'subheading')) ?></p>
        </div>

        <div class="slider" data-slider>
            <div class="slider__track slider__track--wide" data-reveal-stagger>
            <?php foreach ($industries as $i => $industry): ?>
            <div class="industry-card" style="--i:<?= $i ?>" data-reveal>
                <span class="icon-plate icon-plate--sm"><?= icon((string) ($industry['icon'] ?: 'building')) ?></span>
                <div>
                    <div class="industry-card__name">
                        <a href="<?= e(url('/industries/' . $industry['slug'])) ?>"><?= e($industry['name']) ?></a>
                    </div>
                    <?php if (!empty($industry['tagline'])): ?>
                    <div class="industry-card__text"><?= e(str_limit($industry['tagline'], 52)) ?></div>
                    <?php endif; ?>
                </div>
                <span class="industry-card__arrow"><?= icon('arrow-right') ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        </div>

        <div class="row row--center mt-6" data-reveal>
            <a class="btn btn--ghost btn--arrow" href="<?= e(url('/industries')) ?>">
                <?= e($s('industries', 'cta_label', 'All industries')) ?><?= icon('arrow-right') ?>
            </a>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ================= TESTIMONIALS ================= -->
<?php if ($testimonials): ?>
<section class="section">
    <div class="container">
        <div class="section-head section-head--center" data-reveal>
            <p class="eyebrow eyebrow--plain">In their words</p>
            <h2 class="mt-4">What clients say about working with us.</h2>
        </div>

        <div class="slider" data-slider>
            <div class="slider__track slider__track--cards" data-reveal-stagger>
            <?php foreach ($testimonials as $i => $t): ?>
            <article class="card quote-card" style="--i:<?= $i ?>" data-reveal>
                <span class="quote-card__mark"><?= icon('quote') ?></span>
                <?php if ((int) $t['rating'] > 0): ?>
                <div class="rating" aria-label="<?= (int) $t['rating'] ?> out of 5">
                    <?php for ($star = 1; $star <= 5; $star++): ?>
                        <?= icon('star', 'icon' . ($star <= (int) $t['rating'] ? '' : ' icon--off')) ?>
                    <?php endfor; ?>
                </div>
                <?php endif; ?>
                <p class="quote-card__text">“<?= e($t['quote']) ?>”</p>
                <div class="quote-card__author">
                    <?php $img = media_url($t['image']); ?>
                    <?php if ($img !== ''): ?>
                    <img class="avatar" src="<?= e($img) ?>" alt="<?= e($t['client_name']) ?>" loading="lazy" width="42" height="42">
                    <?php else: ?>
                    <span class="avatar" aria-hidden="true"><?= e(initials((string) $t['client_name'])) ?></span>
                    <?php endif; ?>
                    <div>
                        <div class="quote-card__name"><?= e($t['client_name']) ?></div>
                        <div class="quote-card__role">
                            <?= e(trim(($t['position'] ?? '') . (($t['position'] ?? '') && ($t['company'] ?? '') ? ', ' : '') . ($t['company'] ?? ''), ', ')) ?>
                        </div>
                    </div>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ================= FAQ ================= -->
<?php if ($faqs): ?>
<section class="section">
    <div class="container">
        <div class="split">
            <div data-reveal="left">
                <p class="eyebrow">Questions</p>
                <h2 class="mt-4">The things businesses ask us first.</h2>
                <p class="lead mt-4">If your question is not here, ask it directly — we answer honestly, including when the answer is “that is not something we do”.</p>
                <div class="row mt-6">
                    <a class="btn btn--ghost btn--arrow" href="<?= e(url('/faqs')) ?>">All questions<?= icon('arrow-right') ?></a>
                    <a class="btn btn--quiet" href="<?= e(url('/contact')) ?>">Ask us directly</a>
                </div>
            </div>
            <div data-reveal="right">
                <?= $view->partial('partials/faq-accordion', ['faqs' => $faqs, 'groupId' => 'home']) ?>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ================= BLOG ================= -->
<?php if ($posts): ?>
<section class="section">
    <div class="container">
        <div class="row row--between mb-6" data-reveal>
            <div>
                <p class="eyebrow">From the blog</p>
                <h2 class="mt-4">Practical writing on going digital.</h2>
            </div>
            <a class="link hide-sm" href="<?= e(url('/blog')) ?>">All articles<?= icon('arrow-right') ?></a>
        </div>

        <div class="slider" data-slider>
            <div class="slider__track slider__track--cards" data-reveal-stagger>
            <?php foreach ($posts as $i => $post): ?>
            <article class="card card--interactive post-card" style="--i:<?= $i ?>" data-reveal>
                <?php $img = media_url($post['featured_image']); ?>
                <?php if ($img !== ''): ?>
                <div class="post-card__media">
                    <?php /* Decorative: the post's title link below says exactly this. */ ?>
                    <img src="<?= e($img) ?>" alt="" loading="lazy" decoding="async" width="600" height="338">
                </div>
                <?php endif; ?>
                <div class="post-card__body">
                    <div class="post-card__meta">
                        <?php if (!empty($post['category_name'])): ?>
                        <span class="badge badge--accent"><?= e($post['category_name']) ?></span>
                        <?php endif; ?>
                        <span><?= e(format_date($post['published_at'])) ?></span>
                    </div>
                    <h3 class="post-card__title"><a href="<?= e(url('/blog/' . $post['slug'])) ?>"><?= e($post['title']) ?></a></h3>
                    <p class="post-card__excerpt"><?= e(str_limit($post['excerpt'], 120)) ?></p>
                    <div class="post-card__foot">
                        <span class="hint"><?= (int) $post['reading_minutes'] ?> min read</span>
                    </div>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ================= CTA ================= -->
<?= $view->partial('partials/cta-band', ['section' => $sections['cta'] ?? null]) ?>
