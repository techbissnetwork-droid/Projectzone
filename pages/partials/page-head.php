<?php
/**
 * Interior page header with breadcrumbs. Always centered, matching the
 * homepage hero's badge/eyebrow/kinetic-heading treatment.
 * @var string $eyebrow @var string $heading @var string $lead
 * @var \Techbiss\Core\Seo $seo
 */
$eyebrow = $eyebrow ?? '';
$lead    = $lead ?? '';
$crumbs  = $seo->getBreadcrumbs();
?>
<section class="page-head">
    <div class="page-head__bg" aria-hidden="true">
        <span class="glow" data-parallax="0.08"></span>
        <span class="grid-pattern"></span>
        <span class="page-head__ring"></span>
    </div>
    <div class="container">
        <?php if (count($crumbs) > 1): ?>
        <nav class="breadcrumbs" aria-label="Breadcrumb">
            <?php foreach ($crumbs as $i => $crumb): ?>
                <?php if ($i > 0): ?><?= icon('chevron-right') ?><?php endif; ?>
                <?php if ($i === count($crumbs) - 1): ?>
                    <span aria-current="page"><?= e($crumb['label']) ?></span>
                <?php else: ?>
                    <a href="<?= e(url($crumb['url'])) ?>"><?= e($crumb['label']) ?></a>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>
        <?php endif; ?>

        <?php if ($eyebrow !== ''): ?>
        <p class="eyebrow mt-5"><?= e($eyebrow) ?></p>
        <?php endif; ?>

        <h1 data-reveal="kinetic">
            <?php $words = preg_split('/\s+/', trim($heading)) ?: [$heading]; ?>
            <?php foreach ($words as $i => $word): ?><span class="kinetic-word" style="--i:<?= $i ?>"><span><?= e($word) ?></span></span> <?php endforeach; ?>
        </h1>

        <?php if ($lead !== ''): ?>
        <p class="lead"><?= e($lead) ?></p>
        <?php endif; ?>

        <?php if (!empty($actions)): ?>
        <div class="row row--center mt-6"><?= $actions ?></div>
        <?php endif; ?>
    </div>
</section>
