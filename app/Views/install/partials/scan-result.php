<?php
/** @var array $scan */
$platformNames = ['techbiss' => 'Previous TECHBISS install', 'wordpress' => 'WordPress', 'joomla' => 'Joomla',
                  'drupal' => 'Drupal', 'laravel' => 'Laravel', 'magento' => 'Magento', 'static' => 'Static HTML site'];
?>
<?php if (!$scan['found']): ?>
  <div class="alert alert--ok">
    <?= icon('check-circle') ?>
    <div>
      <strong>Nothing already installed here.</strong>
      <p>The target directory and database are clear. A clean installation is safe.</p>
    </div>
  </div>
<?php else: ?>
  <div class="alert alert--warn">
    <?= icon('search') ?>
    <div>
      <strong>Existing installation detected — <?= e($platformNames[$scan['platform']] ?? 'unknown platform') ?>.</strong>
      <p>Confidence: <?= e($scan['confidence']) ?>. Review the signals below and choose how to proceed.</p>
    </div>
  </div>

  <div class="checklist mt-4">
    <?php foreach ($scan['signals'] as $signal): ?>
      <div class="checkrow checkrow--warn">
        <span class="checkrow__icon"><?= icon('info') ?></span>
        <span>
          <strong><?= e($signal['label']) ?></strong>
          <span><?= e($signal['detail']) ?></span>
        </span>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php if (!empty($scan['database']['reachable'])): ?>
  <div class="detected mt-4">
    <div class="detected__row"><span>Database tables</span><code><?= (int) $scan['database']['tables'] ?></code></div>
    <div class="detected__row"><span>Platform tables present</span><code><?= (int) $scan['database']['platform_tables'] ?> of 6</code></div>
    <?php if (!empty($scan['database']['foreign_tables'])): ?>
      <div class="detected__row"><span>Foreign schema</span><code><?= e(implode(', ', $scan['database']['foreign_tables'])) ?></code></div>
    <?php endif; ?>
    <div class="detected__row"><span>Recommended mode</span><code><?= e($scan['recommended_mode']) ?></code></div>
  </div>
<?php elseif (!empty($scan['database']['error'])): ?>
  <div class="alert alert--bad mt-4">
    <?= icon('x-circle') ?>
    <div><strong>Database could not be scanned</strong><p><?= e($scan['database']['error']) ?></p></div>
  </div>
<?php endif; ?>
