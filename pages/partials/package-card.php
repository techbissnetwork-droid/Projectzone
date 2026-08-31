<?php
/** @var array $package @var bool $compact */
$p        = $package['pricing'];
$compact  = $compact ?? false;
$features = $package['features'] ?? [];
$showSave = setting_bool('show_prepaid_savings', true);
$showPrice = setting_bool('public_pricing', false);
$limit    = $compact ? 6 : count($features);
?>
<article class="package-card<?= (int) $package['is_featured'] === 1 ? ' package-card--featured' : '' ?>"
         data-accent="<?= e($package['accent'] ?: 'cyan') ?>" data-reveal>
    <?php if (!empty($package['badge'])): ?>
    <span class="badge badge--solid package-card__badge"><?= e($package['badge']) ?></span>
    <?php endif; ?>

    <div class="package-card__head">
        <span class="icon-plate"><?= icon((string) ($package['icon'] ?: 'layers')) ?></span>
        <div>
            <h3 class="package-card__name"><?= e($package['name']) ?></h3>
            <?php if (!empty($package['tagline'])): ?>
            <p class="package-card__tagline"><?= e($package['tagline']) ?></p>
            <?php endif; ?>
        </div>
    </div>

    <div class="price">
        <?php if (!$showPrice): ?>
            <div class="price__custom">Priced with you</div>
            <p class="price__note">
                <?= !empty($package['best_for']) ? e($package['best_for']) : 'Tell us what you need and we agree the figure directly.' ?>
            </p>
        <?php elseif ($p['is_custom']): ?>
            <div class="price__custom">Custom quote</div>
            <p class="price__note">Scoped and priced from your requirements.</p>
        <?php else: ?>
            <div class="price__row">
                <span class="price__amount"><?= e(money($p['payable'])) ?></span>
                <?php if ($p['has_discount'] && $showSave): ?>
                <span class="price__regular" aria-label="Regular price"><?= e(money($p['regular'])) ?></span>
                <?php endif; ?>
                <span class="price__period"><?= e($package['billing_period'] === 'one-time' ? 'one-time setup' : $package['billing_period']) ?></span>
            </div>
            <?php if ($p['has_discount'] && $showSave): ?>
            <span class="price__save"><?= icon('check-circle') ?>Prepaid — save <?= e(money($p['saving'])) ?> (<?= (int) $p['percent'] ?>%)</span>
            <?php endif; ?>
            <?php if (!empty($package['best_for'])): ?>
            <p class="price__note"><?= e($package['best_for']) ?></p>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <?php if ($features): ?>
    <div class="package-card__features">
        <?php foreach (array_slice($features, 0, $limit) as $f): ?>
        <div class="feature-row<?= (int) $f['is_included'] === 0 ? ' feature-row--excluded' : '' ?>">
            <span class="feature-row__check"><?= icon((int) $f['is_included'] === 1 ? 'check' : 'x') ?></span>
            <div>
                <div class="feature-row__title"><?= e($f['title']) ?></div>
                <?php if (!$compact && !empty($f['description'])): ?>
                <div class="feature-row__text"><?= e($f['description']) ?></div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if ($compact && count($features) > $limit): ?>
        <p class="hint mt-3"><?= count($features) - $limit ?> more included — see full details.</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="package-card__cta stack stack-2">
        <?php if (!setting_bool('checkout_enabled', true)): ?>
            <a class="btn <?= (int) $package['is_featured'] === 1 ? 'btn--primary' : 'btn--ghost' ?> btn--block"
               href="<?= e(url('/quote?package=' . $package['slug'])) ?>"><?= e($package['cta_label'] ?: 'Request a Quote') ?></a>
        <?php else: ?>
            <a class="btn <?= (int) $package['is_featured'] === 1 ? 'btn--primary' : 'btn--ghost' ?> btn--block"
               href="<?= e(url('/checkout/' . $package['slug'])) ?>"><?= e($package['cta_label'] ?: 'Choose what you need') ?></a>
        <?php endif; ?>
        <a class="btn btn--quiet btn--block btn--sm" href="<?= e(url('/packages/' . $package['slug'])) ?>">
            See what's included
        </a>
    </div>
</article>
