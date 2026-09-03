<?php
/** @var App\Core\View $view @var array $cases @var array $industries @var string $activeIndustry */
$view->extends('layouts.app');
$view->start('content');
$feature = $cases[0] ?? null;
$rest = array_slice($cases, 1);
?>
<section class="hero">
  <div class="aura"></div>
  <div class="container container--wide">
    <div class="hero__inner">
      <?php $view->partial('partials.crumbs', ['crumbs' => ['Home' => '/', 'Work' => '/work']]); ?>
      <div class="split split--wide-left" style="align-items:end">
        <div>
          <span class="eyebrow" data-seq style="--seq:0">Selected work</span>
          <h1 class="h1 hero__title" data-seq style="--seq:1"><span data-lines>The numbers, the constraints and the parts that were hard.</span></h1>
        </div>
        <p class="lede" data-seq style="--seq:2">
          Each case study leads with the measurement that justified the
          engagement — including the ones where the answer was uncomfortable.
        </p>
      </div>

      <div class="cluster mt-7" data-seq style="--seq:3" role="group" aria-label="Filter by industry">
        <a class="btn btn--sm <?= $activeIndustry === '' ? 'btn--solid' : 'btn--ghost' ?>" href="<?= e(url('/work')) ?>">All industries</a>
        <?php foreach ($industries as $industry): ?>
          <a class="btn btn--sm <?= $activeIndustry === $industry ? 'btn--solid' : 'btn--ghost' ?>"
             href="<?= e(url('/work?industry=' . rawurlencode($industry))) ?>"><?= e($industry) ?></a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>

<span data-actionbar-after aria-hidden="true"></span>

<?php if ($feature === null): ?>
  <section class="section section--flush-top">
    <div class="container">
      <div class="empty">
        <?= icon('folder') ?>
        <h3>No case studies in that industry yet</h3>
        <p>We publish work once a client is happy for us to name them and the results have held for a full quarter.</p>
        <a class="btn btn--ghost" href="<?= e(url('/work')) ?>">Show all work</a>
      </div>
    </div>
  </section>
<?php else: ?>
  <section class="section section--flush-top">
    <div class="container container--wide">
      <article class="card card--flush spotlight edge-light a-<?= e($feature['accent']) ?>" data-reveal>
        <div class="split split--wide-right" style="gap:0">
          <div style="padding:clamp(1.5rem,1rem + 2vw,2.75rem);display:flex;flex-direction:column;justify-content:center">
            <div class="cluster" style="gap:.4rem">
              <span class="badge">Featured</span>
              <span class="badge badge--neutral"><?= e($feature['industry']) ?></span>
              <span class="badge badge--neutral"><?= e($feature['duration']) ?></span>
            </div>
            <h2 class="h2 mt-4"><a href="<?= e(url('/work/' . $feature['slug'])) ?>"><?= e($feature['title']) ?></a></h2>
            <p class="lede mt-4"><?= e($feature['summary']) ?></p>
            <div class="stats mt-6" style="grid-template-columns:repeat(2,minmax(0,1fr))">
              <?php foreach (array_slice($feature['metrics'], 0, 4) as $metric): ?>
                <div class="stat">
                  <span class="stat__value"><?= e($metric['value']) ?></span>
                  <span class="stat__label" style="font-size:var(--t--2);color:var(--ink-3);font-weight:400"><?= e($metric['label']) ?></span>
                </div>
              <?php endforeach; ?>
            </div>
            <div class="mt-6">
              <a class="btn btn--primary" href="<?= e(url('/work/' . $feature['slug'])) ?>">Read the case study<?= icon('arrow-right') ?></a>
            </div>
          </div>
          <a href="<?= e(url('/work/' . $feature['slug'])) ?>" style="display:block;min-height:100%">
            <?= art_mockup($feature['slug'], (string) $feature['layout'], ['label' => $feature['client'] . ' case study']) ?>
          </a>
        </div>
      </article>
    </div>
  </section>

  <section class="section section--flush-top">
    <div class="container container--wide">
      <div class="cols-3">
        <?php foreach ($rest as $i => $case): ?>
          <article class="card card--flush card--lift spotlight edge-light a-<?= e($case['accent']) ?>" data-reveal="<?= $i * 60 ?>">
            <a href="<?= e(url('/work/' . $case['slug'])) ?>" style="display:block;border-bottom:1px solid var(--line)">
              <?= art_mockup($case['slug'], (string) $case['layout'], ['label' => $case['client'] . ' case study']) ?>
            </a>
            <div style="padding:var(--s-5)">
              <div class="cluster" style="gap:.4rem">
                <span class="badge badge--neutral"><?= e($case['industry']) ?></span>
                <span class="badge badge--neutral"><?= e($case['year']) ?></span>
              </div>
              <h3 class="h4 mt-4"><a href="<?= e(url('/work/' . $case['slug'])) ?>"><?= e($case['title']) ?></a></h3>
              <p class="small muted mt-3"><?= e($case['summary']) ?></p>
              <div class="cluster mt-5" style="gap:var(--s-5)">
                <?php foreach (array_slice($case['metrics'], 0, 2) as $metric): ?>
                  <div>
                    <div style="font-size:var(--t-2);font-weight:660;letter-spacing:-.02em"><?= e($metric['value']) ?></div>
                    <div class="tiny dim"><?= e($metric['label']) ?></div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
<?php endif; ?>

<?php $view->partial('partials.cta-band', [
  'title' => 'Want the version with your numbers in it?',
  'body' => 'Bring us the measurement you are accountable for. Discovery will tell you whether it is achievable, what it costs, and how long it takes.',
]); ?>
<?php $view->stop(); ?>

<?php $view->start('after_body'); ?>
<div class="actionbar" data-actionbar>
  <a class="btn btn--ghost" href="<?= e(url('/solutions')) ?>">Solutions</a>
  <a class="btn btn--primary" href="<?= e(url('/contact')) ?>">Start a project</a>
</div>
<?php $view->stop(); ?>
