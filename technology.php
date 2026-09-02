<?php
declare(strict_types=1);
require_once __DIR__ . '/app/bootstrap.php';

$nodes   = Content::items('arch');
$pillars = Content::items('pillar');

$PAGE_TITLE = 'Technology';
$META_DESC  = 'The architecture and infrastructure behind everything ' . Settings::get('site_name') . ' builds.';
$CANONICAL  = url('technology.php');
require __DIR__ . '/partials/public_header.php';
?>
<section class="pagehead" data-theme="deep">
  <div class="shell">
    <p class="eyebrow reveal"><?= e(Settings::get('arch_eyebrow')) ?></p>
    <h1 class="pagehead__title reveal"><?= nl2br(e(Settings::get('arch_title')), false) ?></h1>
    <p class="pagehead__lede reveal"><?= e(Settings::get('arch_lede')) ?></p>
  </div>
</section>

<?php if ($nodes) { $secNum = 1; require __DIR__ . '/partials/section_arch.php'; } ?>

<?php if ($pillars): ?>
  <span id="pillars"></span>
  <?php $secNum = 2; require __DIR__ . '/partials/section_pillars.php'; ?>
<?php endif; ?>

<section class="listing">
  <div class="shell">
    <header class="sec__head sec__head--split">
      <div>
        <p class="eyebrow reveal">Where it runs</p>
        <h2 class="sec__title reveal">We hold the keys,<br>so you do not have to.</h2>
      </div>
      <p class="sec__lede reveal">Domain, hosting, SSL and business email are registered, renewed and monitored by us.
        Clients see every expiry date in their portal.
        <a class="link" href="<?= e(url('services.php')) ?>">What that covers <span aria-hidden="true">→</span></a></p>
    </header>
  </div>
</section>

<?php $secNum = 3; require __DIR__ . '/partials/section_cta.php'; ?>
<?php require __DIR__ . '/partials/public_footer.php'; ?>
