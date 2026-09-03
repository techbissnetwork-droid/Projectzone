<?php
/** @var App\Core\View $view @var array $site */
$view->extends('layouts.amp');
$view->start('content');
?>
<section class="hero">
  <div class="wrap">
    <span class="eyebrow">Services</span>
    <h1>Six practices, staffed by the people who scoped the work.</h1>
    <p class="lede">Each practice can run standalone; most engagements combine two or three under one accountable lead.</p>
    <a class="btn btn-primary" href="<?= e(url('/contact')) ?>">Talk to an architect</a>
  </div>
</section>

<?php foreach ($site['services'] as $service): ?>
  <section class="sec">
    <div class="wrap">
      <h2><?= e($service['name']) ?></h2>
      <p class="lede" style="font-size:16px"><?= e($service['lede']) ?></p>
      <p style="color:var(--ink2);font-size:15px;margin-top:12px"><?= e($service['body']) ?></p>
      <h3 style="margin-top:20px;font-size:14px;color:var(--ink3);text-transform:uppercase;letter-spacing:.12em">Typical outcomes</h3>
      <ul style="margin-top:10px">
        <?php foreach ($service['outcomes'] as $outcome): ?>
          <li style="color:var(--ink2);font-size:14px;margin-bottom:6px">— <?= e($outcome) ?></li>
        <?php endforeach; ?>
      </ul>
      <div style="margin-top:16px">
        <?php foreach ($service['capabilities'] as $capability): ?>
          <span class="tag"><?= e($capability) ?></span>
        <?php endforeach; ?>
      </div>
      <div class="meta"><?= e($service['starting_at']) ?> · <?= e($service['duration']) ?></div>
    </div>
  </section>
<?php endforeach; ?>

<section class="sec">
  <div class="wrap">
    <h2>Start with discovery</h2>
    <p class="lede">Two weeks, fixed price, and an architecture plus delivery plan at the end.</p>
    <a class="btn btn-primary full" href="<?= e(url('/contact')) ?>">Book discovery</a>
  </div>
</section>
<?php $view->stop(); ?>
