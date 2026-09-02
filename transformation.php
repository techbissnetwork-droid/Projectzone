<?php
declare(strict_types=1);
require_once __DIR__ . '/app/bootstrap.php';

$journey = Content::items('journey');
$types   = Content::items('transform');

$PAGE_TITLE = 'From offline to online';
$META_DESC  = 'How ' . Settings::get('site_name') . ' takes a business from paper records and phone orders to a complete online operation.';
$CANONICAL  = url('transformation.php');
require __DIR__ . '/partials/public_header.php';
?>
<section class="pagehead" data-theme="deep">
  <div class="shell">
    <p class="eyebrow reveal"><?= e(Settings::get('journey_eyebrow')) ?></p>
    <h1 class="pagehead__title reveal">From a business in one place<br>to one that runs <span class="grad">everywhere.</span></h1>
    <p class="pagehead__lede reveal">Most businesses do not need a website. They need the whole operation to work
      online — orders, payments, records, communication — without anyone standing at a counter to make it happen.</p>
  </div>
</section>

<?php
if ($journey) {
    $secNum = 1;
    require __DIR__ . '/partials/section_shift.php';
}
?>

<?php if ($types): ?>
  <span id="types"></span>
  <?php $secNum = 2; require __DIR__ . '/partials/section_transform.php'; ?>
<?php endif; ?>

<section class="listing" data-theme="deep">
  <div class="shell">
    <header class="sec__head sec__head--split">
      <div>
        <p class="eyebrow reveal">What happens next</p>
        <h2 class="sec__title reveal">We build it, launch it<br>and keep it running.</h2>
      </div>
      <p class="sec__lede reveal">Every project follows the same five stages, and every site we deliver stays on our
        maintenance and renewal watch afterwards.
        <a class="link" href="<?= e(url('process.php')) ?>">See how we work <span aria-hidden="true">→</span></a></p>
    </header>
  </div>
</section>

<?php $secNum = 3; require __DIR__ . '/partials/section_cta.php'; ?>
<?php require __DIR__ . '/partials/public_footer.php'; ?>
