<?php
/** @var App\Core\View $view @var array $site */
$view->extends('layouts.amp');
$view->start('content');
?>
<section class="hero">
  <div class="wrap">
    <span class="eyebrow">Contact</span>
    <h1>Tell us what needs to change.</h1>
    <p class="lede">Every qualified enquiry gets a reply from an architect within one business day.</p>
  </div>
</section>

<section class="sec">
  <div class="wrap">
    <h2>Direct lines</h2>
    <div class="card">
      <h3>New business</h3>
      <p><a href="mailto:<?= e($site['brand']['sales_email']) ?>" style="color:var(--accent2)"><?= e($site['brand']['sales_email']) ?></a></p>
    </div>
    <div class="card">
      <h3>Existing clients</h3>
      <p><a href="mailto:<?= e($site['brand']['support_email']) ?>" style="color:var(--accent2)"><?= e($site['brand']['support_email']) ?></a></p>
    </div>
    <div class="card">
      <h3>Call us</h3>
      <p><a href="tel:<?= e($site['brand']['phone_href']) ?>" style="color:var(--accent2)"><?= e($site['brand']['phone']) ?></a></p>
    </div>
    <a class="btn btn-primary full" href="<?= e(url('/contact')) ?>">Open the full contact form</a>
  </div>
</section>

<section class="sec">
  <div class="wrap">
    <h2>Offices</h2>
    <?php foreach ($site['offices'] as $office): ?>
      <div class="card">
        <h3><?= e($office['city']) ?> — <?= e($office['role']) ?></h3>
        <p><?= e($office['address']) ?></p>
        <div class="meta"><?= e($office['timezone']) ?> · <?= e($office['phone']) ?></div>
      </div>
    <?php endforeach; ?>
  </div>
</section>
<?php $view->stop(); ?>
