<?php
/** @var array $primaryNav @var \Techbiss\Repo\SettingsRepo $settings @var string $currentPath */
$siteName = $settings->get('site_name', 'TECHBISS');
?>
<header class="site-header">
    <div class="container">
        <div class="site-header__inner">
            <a class="brand" href="<?= e(url('/')) ?>" aria-label="<?= e($siteName) ?> home">
                <span class="brand__text"><?= e($siteName) ?></span>
            </a>

            <nav class="nav" aria-label="Primary">
                <?php foreach ($primaryNav as $item):
                    if ((int) $item['is_button'] === 1) { continue; }
                    $children = $item['children'] ?? [];
                    $href     = $item['url'] !== '' ? url($item['url']) : '#';
                    $active   = $item['url'] !== '' && is_active_url($item['url'], $currentPath);
                    if (!$active) {
                        foreach ($children as $child) {
                            if (is_active_url($child['url'], $currentPath)) { $active = true; break; }
                        }
                    }
                ?>
                <div class="nav__item<?= $children ? ' nav__item--has-children' : '' ?>">
                    <a class="nav__link<?= $active ? ' is-active' : '' ?>"
                       href="<?= e($href) ?>"
                       <?= $active && $item['url'] !== '' ? 'aria-current="page"' : '' ?>
                       <?= $children ? 'aria-expanded="false" aria-haspopup="true"' : '' ?>
                       <?= $item['target'] === '_blank' ? 'target="_blank" rel="noopener noreferrer"' : '' ?>>
                        <?= e($item['label']) ?>
                        <?php if ($children): ?><?= icon('chevron-down') ?><?php endif; ?>
                    </a>
                    <?php if ($children): ?>
                    <div class="nav__dropdown" role="menu">
                        <?php foreach ($children as $child): ?>
                        <a class="nav__dropdown-link" role="menuitem" href="<?= e(url($child['url'])) ?>"
                           <?= $child['target'] === '_blank' ? 'target="_blank" rel="noopener noreferrer"' : '' ?>>
                            <span class="nav__dropdown-title"><?= e($child['label']) ?></span>
                            <?php if ($child['description'] !== ''): ?>
                            <span class="nav__dropdown-desc"><?= e($child['description']) ?></span>
                            <?php endif; ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </nav>

            <div class="header-actions">
                <?php foreach ($primaryNav as $item):
                    if ((int) $item['is_button'] !== 1) { continue; } ?>
                <a class="btn btn--primary btn--sm btn--nav btn--arrow" href="<?= e(url($item['url'])) ?>" data-magnetic="0.22">
                    <?= e($item['label']) ?><?= icon('arrow-right') ?>
                </a>
                <?php endforeach; ?>

                <button type="button" class="nav-toggle" data-nav-toggle
                        aria-expanded="false" aria-controls="mobile-nav" aria-label="Toggle navigation menu">
                    <?= icon('menu', 'icon icon--menu') ?>
                    <?= icon('x', 'icon icon--close') ?>
                </button>
            </div>
        </div>
    </div>
</header>

<nav class="mobile-nav" id="mobile-nav" aria-label="Mobile" aria-hidden="true">
    <?php foreach ($primaryNav as $item):
        if ((int) $item['is_button'] === 1) { continue; }
        $children = $item['children'] ?? [];
    ?>
        <?php if ($children): ?>
        <div class="mobile-nav__group" data-open="false">
            <a class="mobile-nav__link" href="#" aria-expanded="false">
                <?= e($item['label']) ?><?= icon('chevron-down') ?>
            </a>
            <div class="mobile-nav__sub"><div>
                <?php foreach ($children as $child): ?>
                <a class="mobile-nav__sublink" href="<?= e(url($child['url'])) ?>"><?= e($child['label']) ?></a>
                <?php endforeach; ?>
            </div></div>
        </div>
        <?php else: ?>
        <a class="mobile-nav__link" href="<?= e(url($item['url'] !== '' ? $item['url'] : '/')) ?>">
            <?= e($item['label']) ?><?= icon('chevron-right') ?>
        </a>
        <?php endif; ?>
    <?php endforeach; ?>

    <div class="mobile-nav__footer">
        <a class="btn btn--primary btn--block btn--lg" href="<?= e(url('/request')) ?>">Tell Us What You Need</a>
    </div>

    <div class="mobile-nav__meta">
        <?php if ($settings->get('contact_email') !== ''): ?>
        <a href="mailto:<?= e($settings->get('contact_email')) ?>"><?= e($settings->get('contact_email')) ?></a>
        <?php endif; ?>
        <?php if ($settings->get('contact_phone') !== ''): ?>
        <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $settings->get('contact_phone'))) ?>"><?= e($settings->get('contact_phone')) ?></a>
        <?php endif; ?>
        <?php if ($settings->get('business_hours') !== ''): ?>
        <span><?= e($settings->get('business_hours')) ?></span>
        <?php endif; ?>
        <a href="<?= e(url('/portal')) ?>"><?= icon('user') ?>Client sign in</a>
    </div>
</nav>
