<?php /** @var array $crumbs label => path */ ?>
<nav aria-label="Breadcrumb">
  <ol class="crumbs">
    <?php $last = array_key_last($crumbs); ?>
    <?php foreach ($crumbs as $label => $path): ?>
      <li>
        <?php if ($label === $last): ?>
          <span aria-current="page"><?= e($label) ?></span>
        <?php else: ?>
          <a href="<?= e(url($path)) ?>"><?= e($label) ?></a>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ol>
</nav>
