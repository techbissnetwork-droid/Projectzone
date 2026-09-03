<?php
/** @var App\Core\View $view @var array $solution @var array|null $proof @var array $others */
$view->extends('layouts.app');
$view->start('content');
?>
<section class="hero a-<?= e($solution['accent']) ?>">
  <div class="aura"></div>
  <div class="container container--wide">
    <div class="hero__inner">
      <?php $view->partial('partials.crumbs', ['crumbs' => ['Home' => '/', 'Solutions' => '/solutions', $solution['name'] => '/solutions/' . $solution['slug']]]); ?>
      <div class="split split--wide-left" style="align-items:center">
        <div>
          <div class="cluster" data-seq style="--seq:0">
            <span class="feature__icon" style="margin-bottom:0"><?= icon($solution['icon']) ?></span>
            <span class="badge"><?= e($solution['category']) ?></span>
          </div>
          <h1 class="h1 hero__title" data-seq style="--seq:1"><?= e($solution['lede']) ?></h1>
          <p class="lede hero__lede" data-seq style="--seq:2"><?= e($solution['summary']) ?></p>
          <div class="hero__actions" data-seq style="--seq:3">
            <a class="btn btn--primary btn--lg magnetic icon-shift" href="<?= e(url('/contact?topic=new-project&industry=' . $solution['slug'])) ?>">Talk to a specialist<?= icon('arrow-right') ?></a>
            <?php if ($proof): ?>
              <a class="btn btn--ghost btn--lg magnetic" href="<?= e(url('/work/' . $proof['slug'])) ?>">Read the case study</a>
            <?php endif; ?>
          </div>
        </div>
        <div data-seq style="--seq:2">
          <div class="stats" style="grid-template-columns:repeat(1,minmax(0,1fr))">
            <?php foreach ($solution['metrics'] as $metric): ?>
              <div class="stat">
                <span class="stat__value"><?= e($metric['value']) ?></span>
                <span class="stat__label"><?= e($metric['label']) ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<span data-actionbar-after aria-hidden="true"></span>

<section class="section">
  <div class="container container--wide">
    <div class="split">
      <div data-reveal>
        <span class="eyebrow">What we usually find</span>
        <h2 class="h2 mt-3">The problems that bring people to us.</h2>
        <ul class="stack mt-5" style="--flow:.75rem">
          <?php foreach ($solution['challenges'] as $challenge): ?>
            <li class="card" style="padding:var(--s-4);display:flex;gap:.75rem;align-items:flex-start">
              <?= icon('x-circle', ['class' => '', 'size' => 18]) ?>
              <span class="small"><?= e($challenge) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
      <div data-reveal="80">
        <span class="eyebrow">How we approach it</span>
        <h2 class="h2 mt-3">Four moves, in this order.</h2>
        <div class="steps mt-5">
          <?php foreach ($solution['approach'] as $i => $step): ?>
            <article class="step">
              <span class="step__num">0<?= $i + 1 ?></span>
              <h3 class="h5"><?= e($step['title']) ?></h3>
              <p class="small"><?= e($step['body']) ?></p>
            </article>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="section" style="background:var(--bg-elev);border-block:1px solid var(--line)">
  <div class="container container--wide">
    <div class="split split--wide-left">
      <div data-reveal>
        <span class="eyebrow">Reference stack</span>
        <h2 class="h3 mt-3">What it is built on</h2>
        <div class="cluster mt-4" style="gap:.4rem">
          <?php foreach ($solution['stack'] as $tech): ?>
            <span class="badge badge--neutral"><?= e($tech) ?></span>
          <?php endforeach; ?>
        </div>
      </div>
      <div data-reveal="80">
        <span class="eyebrow">Compliance</span>
        <h2 class="h3 mt-3">Frameworks we deliver against</h2>
        <div class="cluster mt-4" style="gap:.4rem">
          <?php foreach ($solution['compliance'] as $framework): ?>
            <span class="badge"><?= icon('shield', ['size' => 13]) ?><?= e($framework) ?></span>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<?php if ($proof): ?>
<section class="section">
  <div class="container container--wide">
    <?php $view->partial('partials.section-head', [
      'eyebrow' => 'Proof',
      'title' => 'We have done this before.',
      'action' => ['label' => 'All case studies', 'path' => '/work'],
    ]); ?>
    <article class="card card--flush spotlight edge-light a-<?= e($proof['accent']) ?>" data-reveal>
      <div class="split" style="gap:0">
        <a href="<?= e(url('/work/' . $proof['slug'])) ?>" style="display:block">
          <?= art_mockup($proof['slug'], (string) $proof['layout'], ['label' => $proof['client'] . ' case study']) ?>
        </a>
        <div style="padding:var(--s-6);display:flex;flex-direction:column;justify-content:center">
          <span class="badge badge--neutral" style="align-self:flex-start"><?= e($proof['client']) ?></span>
          <h3 class="h3 mt-4"><a href="<?= e(url('/work/' . $proof['slug'])) ?>"><?= e($proof['title']) ?></a></h3>
          <p class="muted mt-4"><?= e($proof['summary']) ?></p>
          <div class="cluster mt-5" style="gap:var(--s-6)">
            <?php foreach (array_slice($proof['metrics'], 0, 3) as $metric): ?>
              <div>
                <div style="font-size:var(--t-3);font-weight:660;letter-spacing:-.02em"><?= e($metric['value']) ?></div>
                <div class="tiny dim"><?= e($metric['label']) ?></div>
              </div>
            <?php endforeach; ?>
          </div>
          <div class="mt-6">
            <a class="btn btn--ghost" href="<?= e(url('/work/' . $proof['slug'])) ?>">Read the full case study<?= icon('arrow-right') ?></a>
          </div>
        </div>
      </div>
    </article>
  </div>
</section>
<?php endif; ?>

<section class="section section--tight">
  <div class="container container--wide">
    <h2 class="h4" data-reveal>Other industries</h2>
    <div class="rail rail--4 mt-4">
      <?php foreach (array_slice($others, 0, 4) as $other): ?>
        <a class="card card--lift a-<?= e($other['accent']) ?>" href="<?= e(url('/solutions/' . $other['slug'])) ?>">
          <span class="feature__icon"><?= icon($other['icon']) ?></span>
          <strong style="font-size:var(--t-1)"><?= e($other['name']) ?></strong>
          <p class="small muted mt-3"><?= e($other['lede']) ?></p>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php $view->partial('partials.cta-band', [
  'title' => 'Start with a two-week discovery.',
  'body' => 'Fixed price, fixed scope. It ends with a target architecture, a delivery plan and a fixed price for the build — yours to keep whatever you decide.',
]); ?>
<?php $view->stop(); ?>

<?php $view->start('after_body'); ?>
<div class="actionbar" data-actionbar>
  <a class="btn btn--ghost" href="<?= e(url('/solutions')) ?>">All solutions</a>
  <a class="btn btn--primary" href="<?= e(url('/contact')) ?>">Talk to a specialist</a>
</div>
<?php $view->stop(); ?>
