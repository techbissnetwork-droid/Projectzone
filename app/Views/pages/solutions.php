<?php
/** @var App\Core\View $view @var array $solutions */
$view->extends('layouts.app');
$view->start('content');
$groups = [];
foreach ($solutions as $solution) { $groups[$solution['category']][] = $solution; }
?>
<section class="hero">
  <div class="aura"></div>
  <div class="grid-lines"></div>
  <div class="container container--wide">
    <div class="hero__inner">
      <?php $view->partial('partials.crumbs', ['crumbs' => ['Home' => '/', 'Solutions' => '/solutions']]); ?>
      <div style="max-width:56ch">
        <span class="eyebrow" data-reveal>Solutions by industry</span>
        <h1 class="h1 hero__title" data-reveal="60">Reference architectures that start you at week six.</h1>
        <p class="lede hero__lede" data-reveal="120">
          Each solution carries pre-hardened infrastructure, a design system and a
          CI/CD pipeline tuned to that sector’s regulation and scale — so discovery
          ends with running software rather than a slide deck.
        </p>
      </div>
    </div>
  </div>
</section>

<span data-actionbar-after aria-hidden="true"></span>

<section class="section section--flush-top">
  <div class="container container--wide">
    <?php foreach ($groups as $category => $items): ?>
      <div class="mt-7">
        <div class="between" data-reveal>
          <h2 class="h3"><?= e($category) ?></h2>
          <span class="badge badge--neutral"><?= count($items) ?> solutions</span>
        </div>
        <hr class="divider mt-4">
        <div class="cols-3 mt-5">
          <?php foreach ($items as $i => $solution): ?>
            <article class="card card--lift spotlight a-<?= e($solution['accent']) ?>" data-reveal="<?= $i * 60 ?>">
              <span class="feature__icon"><?= icon($solution['icon']) ?></span>
              <h3 class="h4"><a href="<?= e(url('/solutions/' . $solution['slug'])) ?>"><?= e($solution['name']) ?></a></h3>
              <p class="muted small mt-3"><?= e($solution['lede']) ?></p>

              <div class="cluster mt-5" style="gap:var(--s-5)">
                <?php foreach (array_slice($solution['metrics'], 0, 2) as $metric): ?>
                  <div>
                    <div style="font-size:var(--t-2);font-weight:660;letter-spacing:-.02em"><?= e($metric['value']) ?></div>
                    <div class="tiny dim" style="max-width:16ch"><?= e($metric['label']) ?></div>
                  </div>
                <?php endforeach; ?>
              </div>

              <div class="feature__foot">
                <span><?= count($solution['compliance']) ?> compliance frameworks</span>
                <a class="btn btn--sm btn--quiet" href="<?= e(url('/solutions/' . $solution['slug'])) ?>">Explore<?= icon('arrow-right') ?></a>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<section class="section" style="background:var(--bg-elev);border-block:1px solid var(--line)">
  <div class="container">
    <?php $view->partial('partials.section-head', [
      'eyebrow' => 'What every solution includes',
      'title' => 'The same foundations, whichever sector you are in.',
      'center' => true,
    ]); ?>
    <div class="cols-4">
      <?php foreach ([
        ['server', 'Hardened infrastructure', 'Landing zone, network policy and secrets management as code.'],
        ['shield', 'Security baseline', 'Threat model, controls mapping and continuous evidence capture.'],
        ['compass', 'Design system', 'Accessible components in code, versioned and documented.'],
        ['terminal', 'Delivery pipeline', 'CI/CD with quality gates, progressive delivery and rollback.'],
      ] as $i => $item): ?>
        <article class="card feature" data-reveal="<?= $i * 50 ?>">
          <span class="feature__icon"><?= icon($item[0]) ?></span>
          <h3 class="h5"><?= e($item[1]) ?></h3>
          <p class="small mt-3"><?= e($item[2]) ?></p>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php $view->partial('partials.cta-band', [
  'title' => 'Your sector is not listed?',
  'body' => 'The four foundations apply everywhere. Tell us the regulatory and scale constraints you work under and we will map the reference architecture to them.',
]); ?>
<?php $view->stop(); ?>

<?php $view->start('after_body'); ?>
<div class="actionbar" data-actionbar>
  <a class="btn btn--ghost" href="<?= e(url('/work')) ?>">Case studies</a>
  <a class="btn btn--primary" href="<?= e(url('/contact')) ?>">Talk to an architect</a>
</div>
<?php $view->stop(); ?>
