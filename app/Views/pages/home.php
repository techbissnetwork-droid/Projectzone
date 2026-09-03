<?php
/** @var App\Core\View $view @var array $site @var array $featured @var array $cases */
$view->extends('layouts.app');
$view->start('content');
$services = $site['services'];
?>

<section class="hero hero--home">
  <div class="aura"></div>
  <div class="grid-lines"></div>
  <div class="container container--wide">
    <div class="hero__inner">
      <div class="split split--wide-left" style="align-items:center">
        <div>
          <span class="hero__badge" data-seq style="--seq:0">
            <span class="pulse-dot"></span>
            <span>412 platforms live across 31 countries</span>
          </span>

          <h1 class="display hero__title" data-seq style="--seq:1">
            <span data-lines>We build the platforms your business runs on.</span>
          </h1>

          <p class="lede hero__lede" data-seq style="--seq:3">
            TECHBISS designs, builds and operates digital platforms for regulated
            enterprises — then hands your team the keys, the runbooks and the
            reliability numbers to keep them running.
          </p>

          <div class="hero__actions" data-seq style="--seq:4">
            <a class="btn btn--primary btn--lg magnetic icon-shift" href="<?= e(url('/contact')) ?>">
              Start a project<?= icon('arrow-right') ?>
            </a>
            <a class="btn btn--ghost btn--lg magnetic" href="<?= e(url('/marketplace')) ?>">
              <?= icon('grid') ?>Browse the Marketplace
            </a>
          </div>

          <ul class="hero__meta" data-seq style="--seq:5">
            <li><?= icon('check-circle') ?>Fixed-price two-week discovery</li>
            <li><?= icon('check-circle') ?>Senior engineers, no bait-and-switch</li>
            <li><?= icon('check-circle') ?>SOC 2 Type II &amp; ISO 27001</li>
          </ul>
        </div>

        <div class="hero__art" data-seq style="--seq:2">
          <div data-parallax="22">
            <div class="hero__frame tilt edge-light" data-tilt="6">
              <div class="hero__frame-bar" aria-hidden="true">
                <span></span><span></span><span></span>
                <em>fleet.techbiss.com</em>
              </div>
              <div class="tilt__layer">
                <?= art_mockup('techbiss-hero-platform', 'dashboard', ['label' => 'TECHBISS platform overview']) ?>
              </div>
            </div>

            <!-- Two figures lifted out of the frame: the numbers a buyer checks first. -->
            <div class="hero__chip hero__chip--uptime">
              <span class="pulse-dot"></span>
              <span><strong>99.98%</strong><em>fleet uptime</em></span>
            </div>
            <div class="hero__chip hero__chip--speed">
              <?= icon('zap', ['size' => 15]) ?>
              <span><strong>0.9s</strong><em>LCP on 4G</em></span>
            </div>
          </div>

          <div class="stats stats--pair mt-5">
            <?php foreach (array_slice($site['stats'], 0, 2) as $stat): ?>
              <div class="stat">
                <span class="stat__value"><span data-count="<?= e($stat['value']) ?>"><?= e($stat['value']) ?></span><em><?= e($stat['suffix']) ?></em></span>
                <span class="stat__label"><?= e($stat['label']) ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<span data-actionbar-after aria-hidden="true"></span>

<section class="section section--tight section--flush-top">
  <div class="container container--wide">
    <div class="trustbar" data-reveal>
      <p class="trustbar__label"><?= e($site['trustbar']['label']) ?></p>
      <div class="trustbar__logos">
        <?php foreach ($site['trustbar']['logos'] as $logo): ?>
          <span class="trustbar__logo"><?= e($logo) ?></span>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>

<section class="section" id="services">
  <div class="container container--wide">
    <?php $view->partial('partials.section-head', [
      'eyebrow' => 'What we do',
      'title' => 'Six practices. One accountable outcome.',
      'body' => 'Each practice ships independently and integrates by design. Most engagements combine two or three, led by a single principal who stays with the account.',
      'action' => ['label' => 'All services', 'path' => '/services'],
    ]); ?>

    <div class="rail rail--3">
      <?php foreach ($services as $i => $service): ?>
        <article class="card card--lift feature spotlight edge-light edge-light a-<?= e($service['accent']) ?>" data-reveal="<?= $i * 60 ?>">
          <span class="feature__icon"><?= icon($service['icon']) ?></span>
          <h3><?= e($service['name']) ?></h3>
          <p><?= e($service['lede']) ?></p>
          <ul class="feature__list">
            <?php foreach (array_slice($service['outcomes'], 0, 3) as $outcome): ?>
              <li><?= icon('check') ?><span><?= e($outcome) ?></span></li>
            <?php endforeach; ?>
          </ul>
          <div class="feature__foot">
            <span><?= e($service['starting_at']) ?></span>
            <a class="btn btn--sm btn--quiet" href="<?= e(url('/services#' . $service['slug'])) ?>">Details<?= icon('arrow-right') ?></a>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
    <p class="rail-hint"><?= icon('arrow-right') ?>Swipe for more practices</p>
  </div>
</section>

<section class="section" style="background:var(--bg-elev);border-block:1px solid var(--line)">
  <div class="container container--wide">
    <div class="split split--wide-right">
      <div data-reveal>
        <span class="eyebrow">The Marketplace</span>
        <h2 class="h2 mt-3">Deploy-ready platforms, in your hands the same day.</h2>
        <p class="lede mt-4">
          Production-grade websites, themes and templates built to the same standard
          as our engagements. Preview live, buy a licence, then deploy with the
          Advanced Installer — URL detection, existing-site detection, migration
          and configuration in one guided flow.
        </p>
        <div class="cluster mt-6">
          <a class="btn btn--primary" href="<?= e(url('/marketplace')) ?>">Browse the catalogue<?= icon('arrow-right') ?></a>
          <a class="btn btn--ghost" href="<?= e(url('/marketplace/installer')) ?>"><?= icon('rocket') ?>See the installer</a>
        </div>

        <div class="cols-2 mt-7" style="gap:var(--s-4)">
          <?php foreach ([
            ['zap', 'Automatic URL detection', 'Sub-directories, proxies and HTTPS resolved for you.'],
            ['search', 'Existing-site detection', 'Finds WordPress, Drupal or a prior install before touching anything.'],
            ['refresh', 'Migration and import', 'Brings content across and rewrites old URLs to new ones.'],
            ['shield', 'Clean, locked install', 'Schema, config and a lock file — nothing left exposed.'],
          ] as $point): ?>
            <div class="stack" style="--flow:.4rem">
              <span class="feature__icon" style="width:34px;height:34px;margin-bottom:.35rem"><?= icon($point[0], ['size' => 16]) ?></span>
              <strong style="font-size:var(--t-0)"><?= e($point[1]) ?></strong>
              <span class="small dim"><?= e($point[2]) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="mk-grid" style="grid-template-columns:1fr" data-reveal="80">
        <?php foreach (array_slice($featured, 0, 2) as $item): ?>
          <?php $view->partial('partials.product-card', ['item' => $item]); ?>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>

<section class="section">
  <div class="container container--wide">
    <?php $view->partial('partials.section-head', [
      'eyebrow' => 'Selected work',
      'title' => 'Outcomes we can put a number against.',
      'body' => 'Every case study carries the measurement that justifies it — including the constraints and the parts that were hard.',
      'action' => ['label' => 'All case studies', 'path' => '/work'],
    ]); ?>

    <div class="rail rail--3">
      <?php foreach ($cases as $i => $case): ?>
        <article class="card card--flush card--lift spotlight edge-light edge-light a-<?= e($case['accent']) ?>" data-reveal="<?= $i * 60 ?>">
          <a href="<?= e(url('/work/' . $case['slug'])) ?>" class="ratio-16-10" style="display:block;overflow:hidden;border-bottom:1px solid var(--line)">
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
    <p class="rail-hint"><?= icon('arrow-right') ?>Swipe for more work</p>
  </div>
</section>

<section class="section" style="background:var(--bg-elev);border-block:1px solid var(--line)">
  <div class="container">
    <?php $view->partial('partials.section-head', [
      'eyebrow' => 'How we work',
      'title' => 'A process designed to make the go-live boring.',
      'body' => 'Six phases, published deliverables, and quality gates that are the same for every client.',
      'center' => true,
    ]); ?>

    <div class="steps steps--3">
      <?php foreach (array_slice($site['process'], 0, 6) as $i => $step): ?>
        <article class="step spotlight edge-light" data-reveal="<?= $i * 50 ?>">
          <span class="step__meta"><?= e($step['duration']) ?></span>
          <span class="step__num"><?= e($step['phase']) ?></span>
          <h3><?= e($step['name']) ?></h3>
          <p class="step__promise"><?= e($step['promise']) ?></p>
          <p><?= e($step['body']) ?></p>
        </article>
      <?php endforeach; ?>
    </div>

    <div class="center mt-7" data-reveal>
      <a class="btn btn--ghost" href="<?= e(url('/process')) ?>">See the full process<?= icon('arrow-right') ?></a>
    </div>
  </div>
</section>

<section class="section">
  <div class="container container--wide">
    <div class="split">
      <div data-reveal>
        <span class="eyebrow">Why TECHBISS</span>
        <h2 class="h2 mt-3">Accountability that outlasts the invoice.</h2>
        <p class="lede mt-4">
          Most transformation programmes fail because nobody owns the result. We
          structure every engagement so one named principal does — through
          discovery, delivery and the year after launch.
        </p>
        <div class="stack mt-6" style="--flow:1rem">
          <?php foreach ($site['differentiators'] as $item): ?>
            <div class="card" style="padding:var(--s-4)">
              <div class="between" style="gap:var(--s-4)">
                <div>
                  <strong style="font-size:var(--t-1)"><?= e($item['title']) ?></strong>
                  <p class="small muted mt-3" style="max-width:46ch"><?= e($item['body']) ?></p>
                </div>
                <span class="badge"><?= e($item['metric']) ?></span>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div data-reveal="80">
        <div class="stats" style="grid-template-columns:repeat(2,minmax(0,1fr))">
          <?php foreach ($site['stats'] as $stat): ?>
            <div class="stat">
              <span class="stat__value"><span data-count="<?= e($stat['value']) ?>"><?= e($stat['value']) ?></span><em><?= e($stat['suffix']) ?></em></span>
              <span class="stat__label"><?= e($stat['label']) ?></span>
              <span class="stat__detail"><?= e($stat['detail']) ?></span>
            </div>
          <?php endforeach; ?>
        </div>

        <div class="quote mt-4">
          <span class="quote__mark">&ldquo;</span>
          <blockquote><?= e($site['testimonials'][0]['quote']) ?></blockquote>
          <span class="badge quote__metric"><?= e($site['testimonials'][0]['metric']) ?></span>
          <div class="quote__by">
            <span class="avatar"><?= e(initials($site['testimonials'][0]['name'])) ?></span>
            <div>
              <strong><?= e($site['testimonials'][0]['name']) ?></strong>
              <span><?= e($site['testimonials'][0]['role']) ?>, <?= e($site['testimonials'][0]['company']) ?></span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="section section--tight">
  <div class="container container--wide">
    <div class="marquee">
      <div class="marquee__track">
        <?php for ($pass = 0; $pass < 2; $pass++): ?>
          <?php foreach ($site['certifications'] as $cert): ?>
            <span class="trustbar__logo" style="white-space:nowrap"><?= e($cert['label']) ?> <span class="tiny dim">· <?= e($cert['detail']) ?></span></span>
          <?php endforeach; ?>
        <?php endfor; ?>
      </div>
    </div>
  </div>
</section>

<?php $view->partial('partials.cta-band', [
  'title' => 'Tell us what needs to change.',
  'body' => 'A two-week fixed-price discovery ends with an architecture, a delivery plan and a fixed price. If you walk away, you keep all of it.',
]); ?>

<?php $view->stop(); ?>

<?php $view->start('after_body'); ?>
<div class="actionbar" data-actionbar>
  <a class="btn btn--ghost" href="<?= e(url('/marketplace')) ?>">Marketplace</a>
  <a class="btn btn--primary" href="<?= e(url('/contact')) ?>">Start a project</a>
</div>
<?php $view->stop(); ?>
