<?php
/** @var App\Core\View $view @var array $site */
$view->extends('layouts.app');
$view->start('content');
$services = $site['services'];
?>
<section class="hero">
  <div class="aura"></div>
  <div class="container container--wide">
    <div class="hero__inner">
      <?php $view->partial('partials.crumbs', ['crumbs' => ['Home' => '/', 'Services' => '/services']]); ?>
      <div class="split split--wide-left" style="align-items:end">
        <div>
          <span class="eyebrow" data-seq style="--seq:0">Services</span>
          <h1 class="h1 hero__title" data-seq style="--seq:1"><span data-lines>Six practices, staffed by the people who scoped the work.</span></h1>
        </div>
        <p class="lede" data-seq style="--seq:2">
          Every practice can run standalone, and most engagements combine two or
          three. Pricing below is indicative — a two-week discovery converts it
          into a fixed price.
        </p>
      </div>
    </div>
  </div>
</section>

<span data-actionbar-after aria-hidden="true"></span>

<section class="section section--flush-top">
  <div class="container container--wide">
    <div class="stack" style="--flow:var(--s-4)">
      <?php foreach ($services as $i => $service): ?>
        <article class="card spotlight edge-light a-<?= e($service['accent']) ?>" id="<?= e($service['slug']) ?>"
                 style="scroll-margin-top:calc(var(--header-h) + 2rem)" data-reveal>
          <div class="split split--wide-left" style="gap:var(--s-6);align-items:start">
            <div>
              <div class="cluster" style="gap:.75rem">
                <span class="feature__icon" style="margin-bottom:0"><?= icon($service['icon']) ?></span>
                <div>
                  <span class="mono dim">0<?= $i + 1 ?></span>
                  <h2 class="h3"><?= e($service['name']) ?></h2>
                </div>
              </div>
              <p class="lede mt-4"><?= e($service['lede']) ?></p>
              <p class="muted mt-4 measure"><?= e($service['body']) ?></p>

              <div class="cluster mt-6">
                <a class="btn btn--primary btn--sm" href="<?= e(url('/contact?topic=new-project&service=' . $service['slug'])) ?>">
                  Discuss this practice<?= icon('arrow-right') ?>
                </a>
                <a class="btn btn--ghost btn--sm" href="<?= e(url('/work')) ?>">See related work</a>
              </div>
            </div>

            <div class="stack" style="--flow:var(--s-4)">
              <div>
                <h3 class="small" style="text-transform:uppercase;letter-spacing:var(--ls-eyebrow);color:var(--ink-3)">Typical outcomes</h3>
                <ul class="feature__list mt-3" style="padding-top:0">
                  <?php foreach ($service['outcomes'] as $outcome): ?>
                    <li><?= icon('check') ?><span><?= e($outcome) ?></span></li>
                  <?php endforeach; ?>
                </ul>
              </div>
              <div>
                <h3 class="small" style="text-transform:uppercase;letter-spacing:var(--ls-eyebrow);color:var(--ink-3)">Capabilities</h3>
                <div class="cluster mt-3" style="gap:.35rem">
                  <?php foreach ($service['capabilities'] as $capability): ?>
                    <span class="badge badge--neutral"><?= e($capability) ?></span>
                  <?php endforeach; ?>
                </div>
              </div>
              <div class="between" style="padding-top:var(--s-4);border-top:1px solid var(--line)">
                <div>
                  <div class="tiny dim">Indicative</div>
                  <strong><?= e($service['starting_at']) ?></strong>
                </div>
                <div style="text-align:right">
                  <div class="tiny dim">Typical duration</div>
                  <strong><?= e($service['duration']) ?></strong>
                </div>
              </div>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section" style="background:var(--bg-elev);border-block:1px solid var(--line)">
  <div class="container">
    <?php $view->partial('partials.section-head', [
      'eyebrow' => 'Engagement model',
      'title' => 'Pods, not headcount.',
      'body' => 'A cross-functional pod owns discovery through operations with one accountable lead and a fixed weekly cadence. You never buy a seat you cannot name.',
      'center' => true,
    ]); ?>
    <div class="cols-3">
      <?php foreach ([
        ['users', 'Discovery pod', '2 weeks · fixed price', 'Principal architect, lead designer and delivery lead. Ends with architecture, plan and a fixed price you keep either way.'],
        ['layers', 'Delivery pod', '8–24 weeks · per increment', 'Four to eight engineers, a designer and a delivery lead. Fortnightly production increments against published quality gates.'],
        ['gauge', 'Operate pod', 'Ongoing · monthly', 'Reliability, cost governance and roadmap. SLOs reported monthly, including the months we miss them.'],
      ] as $i => $pod): ?>
        <article class="card card--lift feature spotlight edge-light" data-reveal="<?= $i * 60 ?>">
          <span class="feature__icon"><?= icon($pod[0]) ?></span>
          <h3><?= e($pod[1]) ?></h3>
          <span class="badge badge--neutral mt-3"><?= e($pod[2]) ?></span>
          <p class="mt-4"><?= e($pod[3]) ?></p>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php $view->partial('partials.cta-band', [
  'title' => 'Not sure which practice you need?',
  'body' => 'Most clients arrive with a symptom rather than a diagnosis. Describe what is going wrong and an architect will tell you which practice actually addresses it.',
  'primary' => ['label' => 'Talk to an architect', 'path' => '/contact'],
  'secondary' => ['label' => 'See the process', 'path' => '/process'],
]); ?>
<?php $view->stop(); ?>

<?php $view->start('after_body'); ?>
<div class="actionbar" data-actionbar>
  <a class="btn btn--ghost" href="<?= e(url('/process')) ?>">Our process</a>
  <a class="btn btn--primary" href="<?= e(url('/contact')) ?>">Start a project</a>
</div>
<?php $view->stop(); ?>
