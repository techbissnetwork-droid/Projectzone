<?php /** @var array $flash */ ?>
<?php if (!empty($flash['status'])): ?>
  <div class="alert alert--ok mt-4" role="status">
    <?= icon('check-circle') ?><div><?= e($flash['status']) ?></div>
  </div>
<?php endif; ?>
<?php if (!empty($flash['error'])): ?>
  <div class="alert alert--bad mt-4" role="alert">
    <?= icon('alert') ?><div><?= e($flash['error']) ?></div>
  </div>
<?php endif; ?>
