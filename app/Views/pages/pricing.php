<?php
/** @var App\Core\View $view @var array $faqs @var array $site */
$view->extends('layouts.app');
$view->start('content');
?>
<section class="hero">
  <div class="aura"></div>
  <div class="container container--wide">
    <div class="hero__inner">
      <?php $view->partial('partials.crumbs', ['crumbs' => ['Home' => '/', 'Pricing' => '/pricing']]); ?>
      <div style="max-width:56ch">
        <span class="eyebrow" data-seq style="--seq:0">Engagement models</span>
        <h1 class="h1 hero__title" data-seq style="--seq:1"><span data-lines>Three ways to work with us. All of them priced up front.</span></h1>
        <p class="lede hero__lede" data-seq style="--seq:2">
          We do not quote day rates against an open-ended scope. Discovery is
          always fixed. Delivery is fixed per increment once architecture settles.
        </p>
      </div>
    </div>
  </div>
</section>

<span data-actionbar-after aria-hidden="true"></span>

<section class="section section--flush-top">
  <div class="container container--wide">
    <div class="cols-3">
      <?php foreach ([
        ['Discovery', '$28,000', 'fixed, 2 weeks', false, 'For teams that need certainty before committing a budget.', [
          'Principal architect, lead designer, delivery lead',
          'Target architecture and decision records',
          'Threat model and controls mapping',
          'Delivery plan with a fixed build price',
          'Technical spike proving the biggest risk',
          'Everything is yours whether you continue or not',
        ], ['label' => 'Book discovery', 'path' => '/contact?topic=new-project']],
        ['Delivery pod', 'From $92,000', 'per 4-week increment', true, 'For programmes that need a team accountable to an outcome.', [
          'Cross-functional pod, named before you commit',
          'Fortnightly production-grade increments',
          'All six quality gates on every increment',
          'Design system delivered as code',
          'Observability and runbooks as deliverables',
          'Capability transfer planned from week one',
        ], ['label' => 'Talk to an architect', 'path' => '/contact?topic=new-project']],
        ['Operate', 'From $14,000', 'per month', false, 'For platforms live in production that need to stay that way.', [
          'SLO monitoring and monthly reporting',
          'Incident response with an agreed SLA',
          'Cost governance and FinOps review',
          'Quarterly roadmap and risk review',
          'Security patching and dependency management',
          'Published reliability numbers, good months and bad',
        ], ['label' => 'Discuss operations', 'path' => '/contact?topic=support']],
      ] as $i => $plan): ?>
        <article class="card card--lift spotlight edge-light" data-reveal="<?= $i * 60 ?>"
                 style="<?= $plan[3] ? 'border-color:var(--accent-line);box-shadow:var(--sh-glow)' : '' ?>">
          <div class="between">
            <h2 class="h4"><?= e($plan[0]) ?></h2>
            <?php if ($plan[3]): ?><span class="badge">Most common</span><?php endif; ?>
          </div>
          <div class="mt-4">
            <span style="font-size:var(--t-5);font-weight:680;letter-spacing:var(--ls-display)"><?= e($plan[1]) ?></span>
            <span class="small dim" style="display:block"><?= e($plan[2]) ?></span>
          </div>
          <p class="small muted mt-4"><?= e($plan[4]) ?></p>
          <ul class="feature__list mt-5" style="margin-top:var(--s-5)">
            <?php foreach ($plan[5] as $line): ?>
              <li><?= icon('check') ?><span><?= e($line) ?></span></li>
            <?php endforeach; ?>
          </ul>
          <div class="mt-6">
            <a class="btn <?= $plan[3] ? 'btn--primary' : 'btn--ghost' ?> btn--block" href="<?= e(url($plan[6]['path'])) ?>">
              <?= e($plan[6]['label']) ?>
            </a>
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
        <span class="eyebrow">Marketplace licensing</span>
        <h2 class="h2 mt-3">Or start at a few hundred dollars.</h2>
        <p class="lede mt-4">
          Marketplace products are productised builds you deploy yourself with the
          Advanced Installer. Many clients start there and grow into an engagement
          once the platform matters more.
        </p>
        <div class="cluster mt-6">
          <a class="btn btn--primary" href="<?= e(url('/marketplace')) ?>">Browse the catalogue<?= icon('arrow-right') ?></a>
          <a class="btn btn--ghost" href="<?= e(url('/marketplace/licensing')) ?>">Licensing terms</a>
        </div>
      </div>
      <div class="stack" style="--flow:.6rem" data-reveal="80">
        <?php foreach (App\Models\Product::TIERS as $key => $tier): ?>
          <div class="card" style="padding:var(--s-4)">
            <div class="between">
              <strong><?= e($tier['label']) ?> licence</strong>
              <span class="badge badge--neutral"><?= e(ucfirst($key)) ?></span>
            </div>
            <p class="small dim mt-3"><?= e($tier['blurb']) ?></p>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>

<section class="section">
  <div class="container container--tight">
    <?php $view->partial('partials.section-head', [
      'eyebrow' => 'Questions',
      'title' => 'Before you commit.',
      'center' => true,
    ]); ?>
    <div class="accordion" data-reveal>
      <?php foreach ($faqs as $i => $faq): ?>
        <details<?= $i === 0 ? ' open' : '' ?>>
          <summary><?= e($faq['q']) ?></summary>
          <div class="accordion__body"><?= e($faq['a']) ?></div>
        </details>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php $view->partial('partials.cta-band', [
  'title' => 'Get a number, not a range.',
  'body' => 'Describe what you need. Within a week we will tell you which model fits, roughly what it costs, and whether we are the right firm for it.',
]); ?>
<?php $view->stop(); ?>

<?php $view->start('after_body'); ?>
<div class="actionbar" data-actionbar>
  <a class="btn btn--ghost" href="<?= e(url('/marketplace')) ?>">Marketplace</a>
  <a class="btn btn--primary" href="<?= e(url('/contact')) ?>">Get a quote</a>
</div>
<?php $view->stop(); ?>
