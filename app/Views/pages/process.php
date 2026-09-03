<?php
/** @var App\Core\View $view @var array $steps @var array $site */
$view->extends('layouts.app');
$view->start('content');
?>
<section class="hero">
  <div class="aura"></div>
  <div class="grid-lines"></div>
  <div class="container container--wide">
    <div class="hero__inner">
      <?php $view->partial('partials.crumbs', ['crumbs' => ['Home' => '/', 'Process' => '/process']]); ?>
      <div style="max-width:58ch">
        <span class="eyebrow" data-seq style="--seq:0">How we work</span>
        <h1 class="h1 hero__title" data-seq style="--seq:1"><span data-lines>Six phases, published deliverables, no surprises.</span></h1>
        <p class="lede hero__lede" data-seq style="--seq:2">
          The same process runs on every engagement, whether it is an eight-week
          storefront or a two-year core replacement. What changes is the depth,
          not the shape.
        </p>
      </div>

      <div class="stats mt-7" data-seq style="--seq:3">
        <?php foreach ([
          ['2', 'wks', 'Discovery', 'Fixed price, fixed scope'],
          ['2', 'wks', 'Increment cadence', 'Every one deployable'],
          ['18', 'wks', 'Median to launch', 'Discovery to production'],
          ['4', '%', 'Estimate variance', 'Across our last 20 engagements'],
        ] as $stat): ?>
          <div class="stat">
            <span class="stat__value"><?= e($stat[0]) ?><em><?= e($stat[1]) ?></em></span>
            <span class="stat__label"><?= e($stat[2]) ?></span>
            <span class="stat__detail"><?= e($stat[3]) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>

<span data-actionbar-after aria-hidden="true"></span>

<section class="section section--flush-top">
  <div class="container container--wide">
    <div class="stack" style="--flow:var(--s-4)">
      <?php foreach ($steps as $i => $step): ?>
        <article class="card spotlight edge-light" data-reveal>
          <div class="split split--wide-left" style="gap:var(--s-6);align-items:start">
            <div>
              <div class="between">
                <span class="step__num" style="font-size:var(--t-2);color:var(--accent-2)"><?= e($step['phase']) ?></span>
                <span class="badge badge--neutral"><?= e($step['duration']) ?></span>
              </div>
              <h2 class="h3 mt-3"><?= e($step['name']) ?></h2>
              <p class="mt-2" style="color:var(--accent-2);font-weight:560"><?= e($step['promise']) ?></p>
              <p class="muted mt-4 measure"><?= e($step['body']) ?></p>
            </div>
            <div>
              <h3 class="small" style="text-transform:uppercase;letter-spacing:var(--ls-eyebrow);color:var(--ink-3)">Deliverables</h3>
              <ul class="step__dl mt-3">
                <?php foreach ($step['deliverables'] as $deliverable): ?>
                  <li><span><?= e($deliverable) ?></span></li>
                <?php endforeach; ?>
              </ul>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section" style="background:var(--bg-elev);border-block:1px solid var(--line)">
  <div class="container container--wide">
    <div class="split">
      <div data-reveal>
        <span class="eyebrow">Quality gates</span>
        <h2 class="h2 mt-3">What has to be true before anything ships.</h2>
        <p class="lede mt-4">
          Every increment passes the same gates as launch. That is why the go-live
          is the least eventful day of the programme.
        </p>
      </div>
      <div class="stack" style="--flow:.6rem" data-reveal="80">
        <?php foreach ([
          ['Automated tests green', 'Unit, integration and contract tests, no skipped suites.'],
          ['Performance budget met', 'LCP, INP and CLS measured on a throttled mobile profile in CI.'],
          ['Accessibility verified', 'Automated checks in the pipeline, manual audit each sprint.'],
          ['Security review passed', 'Threat model updated, dependencies scanned, secrets attested.'],
          ['Observability in place', 'Dashboards, alerts and SLOs exist before the feature does.'],
          ['Rollback rehearsed', 'A tested path back, not a theoretical one.'],
        ] as $gate): ?>
          <div class="card" style="padding:var(--s-4);display:flex;gap:.8rem;align-items:flex-start">
            <?= icon('check-circle', ['size' => 20]) ?>
            <div>
              <strong style="font-size:var(--t-0)"><?= e($gate[0]) ?></strong>
              <p class="small dim mt-3"><?= e($gate[1]) ?></p>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>

<section class="section">
  <div class="container container--tight">
    <?php $view->partial('partials.section-head', [
      'eyebrow' => 'Common questions',
      'title' => 'What clients ask before signing.',
      'center' => true,
    ]); ?>
    <div class="accordion" data-reveal>
      <?php foreach ($site['faqs'] as $i => $faq): ?>
        <details<?= $i === 0 ? ' open' : '' ?>>
          <summary><?= e($faq['q']) ?></summary>
          <div class="accordion__body"><?= e($faq['a']) ?></div>
        </details>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php $view->partial('partials.cta-band', [
  'title' => 'Start with phase one.',
  'body' => 'Two weeks, a fixed price, and an architecture plus delivery plan at the end of it. Whether you continue with us is a decision you make with the evidence in hand.',
]); ?>
<?php $view->stop(); ?>

<?php $view->start('after_body'); ?>
<div class="actionbar" data-actionbar>
  <a class="btn btn--ghost" href="<?= e(url('/pricing')) ?>">Pricing</a>
  <a class="btn btn--primary" href="<?= e(url('/contact')) ?>">Book discovery</a>
</div>
<?php $view->stop(); ?>
