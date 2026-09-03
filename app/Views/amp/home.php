<?php
/** @var App\Core\View $view @var array $site @var array $featured @var array $cases */
$view->extends('layouts.amp');
$view->start('content');
?>
<section class="hero">
  <div class="wrap">
    <span class="eyebrow">Digital transformation, engineered</span>
    <h1>We build the platforms your business runs on.</h1>
    <p class="lede">TECHBISS designs, builds and operates digital platforms for regulated enterprises — then hands your team the keys, the runbooks and the reliability numbers.</p>
    <a class="btn btn-primary" href="<?= e(url('/contact')) ?>">Start a project</a>
    <a class="btn" href="<?= e(url('/marketplace')) ?>">Marketplace</a>
    <div class="stats">
      <?php foreach ($site['stats'] as $stat): ?>
        <div class="stat"><b><?= e($stat['value'] . $stat['suffix']) ?></b><span><?= e($stat['label']) ?></span></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="sec">
  <div class="wrap">
    <h2>Six practices, one accountable outcome</h2>
    <?php foreach ($site['services'] as $service): ?>
      <div class="card">
        <h3><?= e($service['name']) ?></h3>
        <p><?= e($service['lede']) ?></p>
        <div class="meta"><?= e($service['starting_at']) ?> · <?= e($service['duration']) ?></div>
      </div>
    <?php endforeach; ?>
    <a class="btn full" href="<?= e(url('/services')) ?>">All services</a>
  </div>
</section>

<section class="sec">
  <div class="wrap">
    <h2>From the Marketplace</h2>
    <?php foreach ($featured as $item): ?>
      <div class="card">
        <h3><a href="<?= e(url('/marketplace/' . $item['slug'])) ?>"><?= e($item['name']) ?></a></h3>
        <p><?= e($item['tagline']) ?></p>
        <div class="meta"><span class="price"><?= e(money((float) $item['price'])) ?></span> · <?= (int) $item['lighthouse'] ?> Lighthouse · <?= number_format((int) $item['sales_count']) ?> deployments</div>
      </div>
    <?php endforeach; ?>
    <a class="btn full" href="<?= e(url('/marketplace')) ?>">Browse the catalogue</a>
  </div>
</section>

<section class="sec">
  <div class="wrap">
    <h2>Selected work</h2>
    <?php foreach ($cases as $case): ?>
      <div class="card">
        <span class="tag"><?= e($case['industry']) ?></span>
        <h3><a href="<?= e(url('/work/' . $case['slug'])) ?>"><?= e($case['title']) ?></a></h3>
        <p><?= e($case['summary']) ?></p>
        <div class="meta">
          <?php foreach (array_slice($case['metrics'], 0, 3) as $metric): ?>
            <?= e($metric['value']) ?> <?= e($metric['label']) ?> ·
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
    <a class="btn full" href="<?= e(url('/work')) ?>">All case studies</a>
  </div>
</section>

<section class="sec">
  <div class="wrap">
    <h2>Ready to start?</h2>
    <p class="lede">A two-week fixed-price discovery ends with an architecture, a plan and a fixed price. If you walk away, you keep all of it.</p>
    <a class="btn btn-primary full" href="<?= e(url('/contact')) ?>">Talk to an architect</a>
  </div>
</section>
<?php $view->stop(); ?>
