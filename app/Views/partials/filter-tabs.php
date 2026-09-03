<?php
/** @var array $counts @var string $active @var string $base @var string $allLabel */
$allLabel = $allLabel ?? 'All';
$total = array_sum($counts);
?>
<div class="cluster" role="group" aria-label="Filter">
  <a class="btn btn--sm <?= $active === '' ? 'btn--solid' : 'btn--ghost' ?>" href="<?= e(url($base)) ?>">
    <?= e($allLabel) ?> <span class="dim"><?= $total ?></span>
  </a>
  <?php foreach ($counts as $key => $count): ?>
    <a class="btn btn--sm <?= $active === (string) $key ? 'btn--solid' : 'btn--ghost' ?>"
       href="<?= e(url($base . '?' . http_build_query([$param ?? 'status' => $key]))) ?>">
      <?= e(ucfirst(str_replace('_', ' ', (string) $key))) ?> <span class="dim"><?= (int) $count ?></span>
    </a>
  <?php endforeach; ?>
</div>
