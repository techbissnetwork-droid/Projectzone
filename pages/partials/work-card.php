<?php
/** @var array $project @var int $i */
$thumb = media_url($project['thumbnail'] ?: ($project['hero_image'] ?? ''));
$techs = $project['technologies'] ?? [];
?>
<article class="work-card" data-reveal style="--i:<?= (int) ($i ?? 0) ?>">
    <div class="work-card__media<?= $thumb === '' ? ' work-card__media--empty' : '' ?>">
        <?php if ($thumb !== ''): ?>
            <?php /* Decorative: the card's title link below says exactly this. */ ?>
            <img src="<?= e($thumb) ?>" alt="" loading="lazy" decoding="async" width="640" height="400">
        <?php else: ?>
            <?= icon('image') ?>
        <?php endif; ?>
        <?php if (!empty($project['category_name']) || !empty($project['industry_name'])): ?>
        <div class="work-card__overlay">
            <?php if (!empty($project['category_name'])): ?>
            <span class="work-card__tag"><?= e($project['category_name']) ?></span>
            <?php endif; ?>
            <?php if (!empty($project['industry_name'])): ?>
            <span class="work-card__tag"><?= e($project['industry_name']) ?></span>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <div class="work-card__body">
        <?php /* The category is already on the overlay tag above, and the client
                 name usually repeats the title a few pixels below it — printing
                 both again here said the same two things twice per card. */ ?>
        <?php if (!empty($project['client_name']) && stripos((string) $project['title'], (string) $project['client_name']) === false): ?>
        <div class="work-card__meta">
            <span><?= e($project['client_name']) ?></span>
        </div>
        <?php endif; ?>
        <h3 class="work-card__title"><a href="<?= e(url('/portfolio/' . $project['slug'])) ?>"><?= e($project['title']) ?></a></h3>
        <?php if (!empty($project['short_description'])): ?>
        <p class="work-card__text"><?= e(str_limit($project['short_description'], 130)) ?></p>
        <?php endif; ?>
        <?php if ($techs): ?>
        <div class="work-card__foot">
            <?php foreach (array_slice($techs, 0, 4) as $tech): ?>
            <span class="tech-chip"><?= e($tech) ?></span>
            <?php endforeach; ?>
            <?php if (count($techs) > 4): ?>
            <span class="tech-chip">+<?= count($techs) - 4 ?></span>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</article>
