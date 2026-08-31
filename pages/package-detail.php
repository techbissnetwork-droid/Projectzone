<?php
/** @var array $package @var array $packages @var array $steps @var array $faqs */
$p        = $package['pricing'];
$features = $package['features'] ?? [];
$addons   = $package['addons'] ?? [];
$showSave = setting_bool('show_prepaid_savings', true);
$others   = array_values(array_filter($packages, static fn ($x) => (int) $x['id'] !== (int) $package['id']));
?>
<?= $view->partial('partials/page-head', [
    'eyebrow' => $package['tagline'] ?: 'Package',
    'heading' => $package['name'] . ' Package',
    'lead'    => (string) $package['short_description'],
]) ?>

<section class="section section--flush-top">
    <div class="container">
        <div class="checkout" data-accent="<?= e($package['accent'] ?: 'cyan') ?>">
            <div>
                <?php if (!empty($package['description'])): ?>
                <div class="prose" data-reveal><?= $package['description'] ?></div>
                <?php endif; ?>

                <?php if ($features): ?>
                <h2 class="mt-8" style="font-size:var(--fs-h3)" data-reveal>Everything included</h2>
                <div class="mt-5" data-reveal>
                    <?php foreach ($features as $f): ?>
                    <div class="feature-row<?= (int) $f['is_included'] === 0 ? ' feature-row--excluded' : '' ?>">
                        <span class="feature-row__check"><?= icon((int) $f['is_included'] === 1 ? 'check' : 'x') ?></span>
                        <div>
                            <div class="feature-row__title"><?= e($f['title']) ?></div>
                            <?php if (!empty($f['description'])): ?>
                            <div class="feature-row__text"><?= e($f['description']) ?></div>
                            <?php endif; ?>
                        </div>
                        <?php if ((int) $f['is_highlight'] === 1): ?>
                        <span class="badge badge--accent ml-auto nowrap hide-sm">Key</span>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if ($addons): ?>
                <h2 class="mt-8" style="font-size:var(--fs-h3)" data-reveal>Optional add-ons</h2>
                <p class="lead mt-3" data-reveal>Select any of these when you request the package.</p>
                <div class="grid grid-2 mt-5" data-reveal>
                    <?php foreach ($addons as $addon): ?>
                    <div class="card" style="padding:1rem 1.15rem">
                        <div class="row row--between" style="align-items:flex-start">
                            <span style="font-size:var(--fs-sm);font-weight:550"><?= e($addon['name']) ?></span>
                            <span class="badge nowrap"><?= e(money($addon['price'])) ?></span>
                        </div>
                        <p class="card__text mt-2" style="font-size:var(--fs-xs)"><?= e($addon['description']) ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if ($steps): ?>
                <h2 class="mt-8" style="font-size:var(--fs-h3)" data-reveal>How delivery works</h2>
                <div class="process mt-5">
                    <?php foreach ($steps as $step): ?>
                    <div class="process__step" data-reveal>
                        <div class="process__num"><?= e($step['step_number']) ?></div>
                        <div class="process__body">
                            <h3 class="process__title" style="font-size:var(--fs-h4)"><?= e($step['title']) ?></h3>
                            <p class="process__text"><?= e($step['description']) ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <aside class="checkout__aside">
                <div class="card card--pad-lg<?= (int) $package['is_featured'] === 1 ? ' package-card--featured' : '' ?>" data-reveal="right">
                    <?php if (!empty($package['badge'])): ?>
                    <span class="badge badge--solid mb-4" style="align-self:flex-start"><?= e($package['badge']) ?></span>
                    <?php endif; ?>

                    <div class="price" style="padding-top:0">
                        <?php if ($p['is_custom']): ?>
                            <div class="price__custom">Custom quote</div>
                            <p class="price__note">Priced from your requirements after a short scoping conversation.</p>
                        <?php else: ?>
                            <div class="price__row">
                                <span class="price__amount"><?= e(money($p['payable'])) ?></span>
                                <?php if ($p['has_discount'] && $showSave): ?>
                                <span class="price__regular"><?= e(money($p['regular'])) ?></span>
                                <?php endif; ?>
                            </div>
                            <span class="price__period"><?= e($package['billing_period'] === 'one-time' ? 'One-time setup' : ucfirst((string) $package['billing_period'])) ?>
                                · <?= (int) $package['duration_months'] ?> months included</span>
                            <?php if ($p['has_discount'] && $showSave): ?>
                            <span class="price__save"><?= icon('check-circle') ?>Save <?= e(money($p['saving'])) ?> (<?= (int) $p['percent'] ?>%) by prepaying</span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($package['best_for'])): ?>
                    <div class="notice mt-4"><?= icon('target') ?><span><strong style="color:var(--text)">Best for:</strong> <?= e($package['best_for']) ?></span></div>
                    <?php endif; ?>

                    <div class="card__footer stack stack-2">
                        <?php if ($p['is_custom'] || !setting_bool('checkout_enabled', true)): ?>
                        <a class="btn btn--primary btn--lg btn--block btn--arrow" href="<?= e(url('/quote?package=' . $package['slug'])) ?>">
                            <?= e($package['cta_label'] ?: 'Request a Quote') ?><?= icon('arrow-right') ?>
                        </a>
                        <?php else: ?>
                        <a class="btn btn--primary btn--lg btn--block btn--arrow" href="<?= e(url('/checkout/' . $package['slug'])) ?>" data-magnetic="0.2">
                            <?= e($package['cta_label'] ?: 'Get Started') ?><?= icon('arrow-right') ?>
                        </a>
                        <?php endif; ?>
                        <a class="btn btn--ghost btn--block" href="<?= e(url('/contact')) ?>">Ask a question first</a>
                    </div>

                    <p class="hint mt-4"><?= icon('shield') ?> No payment is taken here. We confirm the scope, then invoice.</p>
                </div>

                <?php if ($others): ?>
                <div class="card mt-4" data-reveal="right">
                    <h3 class="card__title" style="font-size:var(--fs-sm)">Other packages</h3>
                    <div class="stack stack-2 mt-3">
                        <?php foreach ($others as $other): ?>
                        <a class="row row--between" href="<?= e(url('/packages/' . $other['slug'])) ?>"
                           style="padding:.6rem .75rem;border-radius:var(--r-sm);border:1px solid var(--border)">
                            <span style="font-size:var(--fs-sm);font-weight:500"><?= e($other['name']) ?></span>
                            <span class="hint tabular">
                                <?= $other['pricing']['is_custom'] ? 'On request' : e(money($other['pricing']['payable'])) ?>
                            </span>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </aside>
        </div>
    </div>
</section>

<?php if ($faqs): ?>
<section class="section">
    <div class="container container--narrow">
        <div class="section-head section-head--center" data-reveal>
            <p class="eyebrow eyebrow--plain">Questions</p>
            <h2 class="mt-4">About pricing and payment</h2>
        </div>
        <?= $view->partial('partials/faq-accordion', ['faqs' => $faqs, 'groupId' => 'pkgd']) ?>
    </div>
</section>
<?php endif; ?>

<?= $view->partial('partials/cta-band', [
    'eyebrow'      => $package['name'] . ' package',
    'heading'      => 'Ready to start?',
    'lead'         => 'Send your details and requirements. We will confirm everything in writing before any money changes hands.',
    'primaryLabel' => $p['is_custom'] ? 'Request a Quote' : 'Request ' . $package['name'],
    'primaryUrl'   => $p['is_custom'] ? '/quote?package=' . $package['slug'] : '/checkout/' . $package['slug'],
]) ?>
