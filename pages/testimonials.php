<?php /** @var array $testimonials @var array $projects */ ?>
<?= $view->partial('partials/page-head', [
    'eyebrow' => 'Testimonials',
    'heading' => 'What clients say about working with us.',
    'lead'    => 'Every testimonial here came from a real client. We do not write them ourselves.',
    'center'  => true,
]) ?>

<section class="section section--flush-top">
    <div class="container">
        <?php if (!$testimonials): ?>
            <div class="empty-state">
                <span class="empty-state__icon"><?= icon('quote') ?></span>
                <h3>No testimonials published yet</h3>
                <p>
                    We only publish feedback clients have actually given us and agreed to share. Rather than fill this page with
                    invented quotes, we have left it empty until there is something genuine to put here.
                </p>
                <div class="row row--center mt-4">
                    <a class="btn btn--primary" href="<?= e(url('/portfolio')) ?>">See the work instead</a>
                    <a class="btn btn--ghost" href="<?= e(url('/contact')) ?>">Ask for references</a>
                </div>
            </div>
        <?php else: ?>
            <div class="grid grid-3" data-reveal-stagger>
                <?php foreach ($testimonials as $i => $t): ?>
                <article class="card quote-card" style="--i:<?= $i ?>" data-reveal>
                    <span class="quote-card__mark"><?= icon('quote') ?></span>
                    <?php if ((int) $t['rating'] > 0): ?>
                    <div class="rating" aria-label="<?= (int) $t['rating'] ?> out of 5">
                        <?php for ($s = 1; $s <= 5; $s++): ?><?= icon('star', 'icon' . ($s <= (int) $t['rating'] ? '' : ' icon--off')) ?><?php endfor; ?>
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
                    <?php if (!empty($t['project_slug'])): ?>
                    <div class="card__footer">
                        <a class="link" href="<?= e(url('/portfolio/' . $t['project_slug'])) ?>">
                            Read the case study<?= icon('arrow-right') ?>
                        </a>
                    </div>
                    <?php endif; ?>
                </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php if ($projects): ?>
<section class="section">
    <div class="container">
        <div class="row row--between mb-6" data-reveal>
            <h2>The work behind the words</h2>
            <a class="link hide-sm" href="<?= e(url('/portfolio')) ?>">All projects<?= icon('arrow-right') ?></a>
        </div>
        <div class="work-grid" data-reveal-stagger>
            <?php foreach ($projects as $i => $project): ?>
                <?= $view->partial('partials/work-card', ['project' => $project, 'i' => $i]) ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?= $view->partial('partials/cta-band') ?>
