<?php
/** @var App\Core\View $view @var array $case @var array $related */
$view->extends('layouts.app');
$view->start('content');
?>
<article class="a-<?= e($case['accent']) ?>">
  <section class="hero">
    <div class="aura"></div>
    <div class="container container--wide">
      <div class="hero__inner">
        <?php $view->partial('partials.crumbs', ['crumbs' => ['Home' => '/', 'Work' => '/work', $case['client'] => '/work/' . $case['slug']]]); ?>
        <div style="max-width:62ch">
          <div class="cluster" data-seq style="--seq:0">
            <span class="badge"><?= e($case['client']) ?></span>
            <span class="badge badge--neutral"><?= e($case['industry']) ?></span>
            <span class="badge badge--neutral"><?= e($case['service']) ?></span>
          </div>
          <h1 class="h1 hero__title" data-seq style="--seq:1"><?= e($case['title']) ?></h1>
          <p class="lede hero__lede" data-seq style="--seq:2"><?= e($case['summary']) ?></p>
          <ul class="hero__meta" data-seq style="--seq:3">
            <li><?= icon('clock') ?><?= e($case['duration']) ?></li>
            <li><?= icon('globe') ?><?= e($case['region']) ?></li>
            <li><?= icon('calendar') ?><?= e($case['year']) ?></li>
          </ul>
        </div>

        <div class="card card--flush mt-7 tilt" data-tilt="4" data-seq style="--seq:4" style="box-shadow:var(--sh-4)">
          <?= art_mockup($case['slug'], (string) $case['layout'], ['label' => $case['client'] . ' platform']) ?>
        </div>
      </div>
    </div>
  </section>

  <span data-actionbar-after aria-hidden="true"></span>

  <section class="section section--tight section--flush-top">
    <div class="container container--wide">
      <div class="stats" data-reveal>
        <?php foreach ($case['metrics'] as $metric): ?>
          <div class="stat">
            <span class="stat__value"><?= e($metric['value']) ?></span>
            <span class="stat__label" style="font-size:var(--t--2);color:var(--ink-3);font-weight:400"><?= e($metric['label']) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="section">
    <div class="container container--wide">
      <div class="split split--wide-left" style="align-items:start">
        <div class="stack" style="--flow:var(--s-7)">
          <div data-reveal>
            <span class="eyebrow">The challenge</span>
            <p class="prose mt-4" style="font-size:var(--t-1)"><?= e($case['challenge']) ?></p>
          </div>

          <div data-reveal>
            <span class="eyebrow">The approach</span>
            <div class="steps mt-4">
              <?php foreach ($case['approach'] as $i => $step): ?>
                <article class="step">
                  <span class="step__num">0<?= $i + 1 ?></span>
                  <h3 class="h4"><?= e($step['title']) ?></h3>
                  <p><?= e($step['body']) ?></p>
                </article>
              <?php endforeach; ?>
            </div>
          </div>

          <div data-reveal>
            <span class="eyebrow">The outcome</span>
            <p class="prose mt-4" style="font-size:var(--t-1)"><?= e($case['outcome']) ?></p>
          </div>
        </div>

        <aside class="stack" style="--flow:var(--s-4)">
          <div class="card" data-reveal>
            <h3 class="h5">Engagement</h3>
            <table class="spec-table mt-3">
              <tbody>
                <tr><th>Client</th><td><?= e($case['client']) ?></td></tr>
                <tr><th>Industry</th><td><?= e($case['industry']) ?></td></tr>
                <tr><th>Practice</th><td><?= e($case['service']) ?></td></tr>
                <tr><th>Region</th><td><?= e($case['region']) ?></td></tr>
                <tr><th>Duration</th><td><?= e($case['duration']) ?></td></tr>
                <tr><th>Delivered</th><td><?= e($case['year']) ?></td></tr>
              </tbody>
            </table>
          </div>

          <div class="card" data-reveal>
            <h3 class="h5">Stack</h3>
            <div class="cluster mt-3" style="gap:.35rem">
              <?php foreach ($case['stack'] as $tech): ?>
                <span class="badge badge--neutral"><?= e($tech) ?></span>
              <?php endforeach; ?>
            </div>
          </div>

          <?php if (!empty($case['quote'])): ?>
            <div class="quote" data-reveal>
              <span class="quote__mark">&ldquo;</span>
              <blockquote style="font-size:var(--t-0)"><?= e($case['quote']) ?></blockquote>
              <div class="quote__by">
                <span class="avatar"><?= e(initials((string) $case['quote_by'])) ?></span>
                <div>
                  <strong><?= e($case['quote_by']) ?></strong>
                  <span><?= e($case['quote_role']) ?></span>
                </div>
              </div>
            </div>
          <?php endif; ?>
        </aside>
      </div>
    </div>
  </section>

  <?php if ($related): ?>
    <section class="section" style="background:var(--bg-elev);border-block:1px solid var(--line)">
      <div class="container container--wide">
        <h2 class="h4" data-reveal>More work</h2>
        <div class="cols-2 mt-5">
          <?php foreach ($related as $item): ?>
            <article class="card card--flush card--lift a-<?= e($item['accent']) ?>">
              <a href="<?= e(url('/work/' . $item['slug'])) ?>" style="display:block;border-bottom:1px solid var(--line)">
                <?= art_mockup($item['slug'], (string) $item['layout'], ['label' => $item['client'] . ' case study']) ?>
              </a>
              <div style="padding:var(--s-5)">
                <span class="badge badge--neutral"><?= e($item['industry']) ?></span>
                <h3 class="h4 mt-3"><a href="<?= e(url('/work/' . $item['slug'])) ?>"><?= e($item['title']) ?></a></h3>
                <p class="small muted mt-3"><?= e($item['summary']) ?></p>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
  <?php endif; ?>
</article>

<?php $view->partial('partials.cta-band'); ?>
<?php $view->stop(); ?>

<?php $view->start('after_body'); ?>
<div class="actionbar" data-actionbar>
  <a class="btn btn--ghost" href="<?= e(url('/work')) ?>">All work</a>
  <a class="btn btn--primary" href="<?= e(url('/contact')) ?>">Start a project</a>
</div>
<?php $view->stop(); ?>
