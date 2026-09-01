<?php
/**
 * A premade project card.
 *
 * No price: the figure is agreed in conversation, so the card carries what
 * actually helps someone decide — a live demo, the setup time, and what it is.
 *
 * @var array $project @var int $i
 */
$thumb = media_url($project['thumbnail'] ?: ($project['hero_image'] ?? ''));
$techs = $project['technologies'] ?? [];
$href  = url('/premade-projects/' . $project['slug']);
$demo  = trim((string) ($project['demo_url'] ?? ''));
$days  = (int) ($project['delivery_days'] ?? 0);
$hasApk = trim((string) ($project['apk_path'] ?? '')) !== '' || trim((string) ($project['apk_external_url'] ?? '')) !== '';
$pages = (int) ($project['page_count'] ?? 0);
?>
<article class="card card--interactive card--spotlight work-card project-card" data-reveal style="--i:<?= (int) ($i ?? 0) ?>">
    <div class="work-card__media<?= $thumb === '' ? ' work-card__media--empty' : '' ?>">
        <?php if ($thumb !== ''): ?>
            <?php /* Decorative: the card's title link below says exactly this. */ ?>
            <img src="<?= e($thumb) ?>" alt="" loading="lazy" decoding="async" width="640" height="400">
        <?php else: ?>
            <?= icon('image') ?>
        <?php endif; ?>
        <?php if (!empty($project['badge']) || !empty($project['category_name'])): ?>
        <div class="work-card__overlay">
            <?php if (!empty($project['badge'])): ?>
            <span class="work-card__tag work-card__tag--accent"><?= e($project['badge']) ?></span>
            <?php endif; ?>
            <?php if (!empty($project['category_name'])): ?>
            <span class="work-card__tag"><?= e($project['category_name']) ?></span>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <div class="work-card__body">
        <h3 class="work-card__title"><a href="<?= e($href) ?>"><?= e($project['name']) ?></a></h3>
        <?php if (!empty($project['tagline'])): ?>
        <p class="work-card__text"><?= e(str_limit($project['tagline'], 96)) ?></p>
        <?php endif; ?>

        <?php if ($days > 0 || $pages > 0): ?>
        <div class="project-card__facts">
            <?php if ($pages > 0): ?>
            <span><?= icon('file') ?><?= $pages ?> page<?= $pages === 1 ? '' : 's' ?></span>
            <?php endif; ?>
            <?php if ($days > 0): ?>
            <span><?= icon('clock') ?>Live in <?= $days ?> day<?= $days === 1 ? '' : 's' ?></span>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($techs): ?>
        <div class="work-card__foot">
            <?php foreach (array_slice($techs, 0, 3) as $tech): ?>
            <span class="tech-chip"><?= e($tech) ?></span>
            <?php endforeach; ?>
            <?php if (count($techs) > 3): ?>
            <span class="tech-chip">+<?= count($techs) - 3 ?></span>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="project-card__actions">
            <a class="btn btn--primary btn--sm" href="<?= e($href) ?>">Details</a>
            <?php if ($demo !== ''): ?>
            <a class="btn btn--ghost btn--sm" href="<?= e($demo) ?>" target="_blank" rel="noopener noreferrer nofollow">
                <?= icon('external') ?>Live demo
            </a>
            <?php endif; ?>
            <?php if ($hasApk): ?>
            <a class="btn btn--ghost btn--sm" href="<?= e(url('/premade-projects/' . $project['slug'] . '/apk')) ?>">
                <?= icon('download') ?>APK
            </a>
            <?php endif; ?>
        </div>
    </div>
</article>
