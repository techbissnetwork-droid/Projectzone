<?php
/**
 * @var string $eyebrow @var string $title @var string $body
 * @var bool $center @var array|null $action
 */
$center = $center ?? false;
$action = $action ?? null;
$level = $level ?? 'h2';
?>
<div class="section-head <?= $center ? 'section-head--center' : ($action ? 'section-head--split' : '') ?>" data-reveal>
  <div class="stack" style="--flow:.75rem">
    <?php if (!empty($eyebrow)): ?><span class="eyebrow"><?= e($eyebrow) ?></span><?php endif; ?>
    <<?= $level ?> class="h2"><?= $title ?></<?= $level ?>>
    <?php if (!empty($body)): ?><p><?= e($body) ?></p><?php endif; ?>
  </div>
  <?php if ($action): ?>
    <div><a class="btn btn--ghost" href="<?= e(url($action['path'])) ?>"><?= e($action['label']) ?><?= icon('arrow-right') ?></a></div>
  <?php endif; ?>
</div>
