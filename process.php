<?php
declare(strict_types=1);
require_once __DIR__ . '/app/bootstrap.php';

$steps = Content::items('process');

$PAGE_TITLE = Settings::get('process_title', 'How we work');
$META_DESC  = 'Discover, design, build, launch and grow — how ' . Settings::get('site_name') . ' runs a project from first call to ongoing maintenance.';
$CANONICAL  = url('process.php');
require __DIR__ . '/partials/public_header.php';
?>
<section class="pagehead" data-theme="deep">
  <div class="shell">
    <p class="eyebrow reveal"><?= e(Settings::get('process_eyebrow')) ?></p>
    <h1 class="pagehead__title reveal"><?= e(Settings::get('process_title')) ?></h1>
    <p class="pagehead__lede reveal">The same five stages on every project, so you always know what is happening,
      what we need from you, and what comes next.</p>
  </div>
</section>

<?php if ($steps): $secNum = 1; require __DIR__ . '/partials/section_process.php'; ?>

  <section class="listing">
    <div class="shell">
      <header class="sec__head"><h2 class="sec__title reveal">Every stage, in writing</h2>
        <p class="sec__lede reveal">The scroll above moves through them one at a time. Here they all are at once.</p></header>
      <div class="stagegrid">
        <?php foreach ($steps as $i => $s): ?>
          <article class="stagecard reveal">
            <header>
              <span class="mono"><?= sprintf('%02d', $i + 1) ?></span>
              <h3><?= e($s['label']) ?></h3>
              <?php if ($s['title']): ?><span class="badge-t mono"><?= e($s['title']) ?></span><?php endif; ?>
            </header>
            <?php if ($s['body']): ?><p><?= e($s['body']) ?></p><?php endif; ?>
            <?php $ticks = lines($s['extra']); if ($ticks): ?>
              <ul class="ticks"><?php foreach ($ticks as $t): ?><li><?= e($t) ?></li><?php endforeach; ?></ul>
            <?php endif; ?>
          </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
<?php endif; ?>

<?php $secNum = 2; require __DIR__ . '/partials/section_cta.php'; ?>
<?php require __DIR__ . '/partials/public_footer.php'; ?>
