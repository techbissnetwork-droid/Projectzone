<?php
/** @var App\Core\View $view @var array $ready @var array $state @var array $meta @var string|null $previous @var array $flash */
$view->extends('layouts.install');
$view->start('content');
$log = $state['log'] ?? [];
?>
<div class="install-card">
  <div class="install-card__head">
    <h1>Ready to install</h1>
    <p>Review what will happen, then run it. This is the first step that writes anything.</p>
  </div>
  <div class="install-card__body">
    <?php $view->partial('partials.flash', ['flash' => $flash]); ?>

    <div class="checklist">
      <?php foreach ($ready['items'] as $item): ?>
        <div class="checkrow checkrow--<?= $item['done'] ? 'pass' : 'fail' ?>">
          <span class="checkrow__icon"><?= icon($item['done'] ? 'check' : 'x') ?></span>
          <span>
            <strong><?= e($item['label']) ?></strong>
            <?php if (!$item['done']): ?><span>Go back and complete this step.</span><?php endif; ?>
          </span>
          <span class="checkrow__value"><?= e($item['value']) ?></span>
        </div>
      <?php endforeach; ?>
    </div>

    <h2 class="h5 mt-6">What will happen</h2>
    <div class="steps mt-3">
      <?php
        $mode = (string) ($state['mode'] ?? 'clean');
        $actions = [
          ['Create the schema', '20 tables and 24 indexes, applied idempotently — existing tables are left alone.'],
        ];
        if ($mode === 'upgrade') {
          $actions[] = ['Preserve your records', 'No seeding runs. Existing users, orders and content stay exactly as they are.'];
        } else {
          $actions[] = ['Create the owner account', 'Your email and password become the platform owner.'];
          if (!empty($state['demo_data'])) {
            $actions[] = ['Seed the catalogue', '12 marketplace products, 6 case studies, 8 insights and sample portal data.'];
          }
        }
        if ($mode === 'migrate' && !empty($state['import_file'])) {
          $actions[] = ['Import your content', 'Records imported and every absolute URL rewritten to ' . ($state['url'] ?? 'the new site URL') . '.'];
        }
        $actions[] = ['Write the configuration', 'storage/installed.php, containing your URL, database and site settings.'];
        $actions[] = ['Lock the installer', 'storage/install.lock is written, and /install stops being reachable.'];
      ?>
      <?php foreach ($actions as $i => $action): ?>
        <article class="step" style="padding:var(--s-4)">
          <span class="step__num"><?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
          <h3 class="h5" style="margin-top:.25rem"><?= e($action[0]) ?></h3>
          <p class="small"><?= e($action[1]) ?></p>
        </article>
      <?php endforeach; ?>
    </div>

    <?php if ($log !== []): ?>
      <h2 class="h5 mt-7">Last run</h2>
      <div class="install-log mt-3">
        <?php foreach ($log as $line): ?>
          <?php [$level, $message] = array_pad(explode('|', $line, 2), 2, ''); ?>
          <div>
            <span class="t <?= e($level === 'err' ? 'err' : ($level === 'warn' ? 'warn' : 'ok')) ?>">
              <?= $level === 'err' ? '✗' : ($level === 'warn' ? '!' : '✓') ?>
            </span>
            <span><?= e($message) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="install-card__foot">
    <a class="btn btn--ghost" href="<?= e(url('/install/step/' . $previous)) ?>">Back</a>
    <form method="post" action="<?= e(url('/install/run')) ?>">
      <?= csrf_field() ?>
      <button class="btn btn--primary btn--lg" type="submit" <?= $ready['blocked'] ? 'aria-disabled="true"' : '' ?>>
        <?= icon('rocket') ?>Install now
      </button>
    </form>
  </div>
</div>
<?php $view->stop(); ?>
