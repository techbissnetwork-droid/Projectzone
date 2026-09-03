<?php /** @var array $result @var array $config */ ?>
<?php if ($result['ok']): ?>
  <div class="alert alert--ok">
    <?= icon('check-circle') ?>
    <div>
      <strong>Connected — <?= e($result['message']) ?></strong>
      <p>
        <?php if (($result['tables'] ?? 0) > 0): ?>
          This database already contains <?= (int) $result['tables'] ?> tables. The next step scans them
          and offers to upgrade or migrate rather than overwrite anything.
        <?php else: ?>
          The database is empty and ready for a clean installation.
        <?php endif; ?>
      </p>
    </div>
  </div>
<?php else: ?>
  <div class="alert alert--bad">
    <?= icon('x-circle') ?>
    <div>
      <strong>Could not connect</strong>
      <p><?= e($result['message']) ?></p>
    </div>
  </div>
<?php endif; ?>
