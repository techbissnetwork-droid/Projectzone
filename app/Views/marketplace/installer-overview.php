<?php
/** @var App\Core\View $view @var array $steps */
$view->extends('layouts.app');
$view->start('content');
$icons = ['requirements' => 'gauge', 'environment' => 'globe', 'database' => 'database', 'detection' => 'search',
          'migration' => 'refresh', 'configuration' => 'settings', 'install' => 'terminal', 'deploy' => 'rocket'];
?>
<section class="hero">
  <div class="aura"></div>
  <div class="grid-lines"></div>
  <div class="container container--wide">
    <div class="hero__inner">
      <?php $view->partial('partials.crumbs', ['crumbs' => ['Marketplace' => '/marketplace', 'Advanced Installer' => '/marketplace/installer']]); ?>
      <div class="split split--wide-left" style="align-items:center">
        <div>
          <span class="eyebrow" data-reveal>Included with every product</span>
          <h1 class="h1 hero__title" data-reveal="60">The Advanced Installer</h1>
          <p class="lede hero__lede" data-reveal="120">
            Putting a site live is the part that usually goes wrong. This is the
            tool our own engineers reach for — eight guided steps that take an
            empty server, or one already running WordPress, to a site handling
            real traffic. You will not need a terminal or a developer.
          </p>
          <div class="hero__actions" data-reveal="180">
            <a class="btn btn--primary btn--lg magnetic icon-shift" href="<?= e(url('/install')) ?>">Open the installer<?= icon('arrow-right') ?></a>
            <a class="btn btn--ghost btn--lg magnetic" href="<?= e(url('/resources/deployment-playbook')) ?>">Deployment playbook</a>
          </div>
          <ul class="hero__meta" data-reveal="220">
            <li><?= icon('check-circle') ?>Typically under 8 minutes</li>
            <li><?= icon('check-circle') ?>No shell access required</li>
            <li><?= icon('check-circle') ?>Headless CLI mode for CI</li>
          </ul>
        </div>

        <div data-reveal="140">
          <div class="card card--flush" style="box-shadow:var(--sh-4);overflow:hidden">
            <div class="install-card__head" style="border-radius:0">
              <span class="eyebrow eyebrow--plain">Step 4 of 8</span>
              <h2 class="h4 mt-3">Existing site detected</h2>
            </div>
            <div style="padding:var(--s-5)">
              <div class="detected">
                <div class="detected__row"><span>Platform</span><code>WordPress 6.7</code></div>
                <div class="detected__row"><span>Detected at</span><code>/var/www/html</code></div>
                <div class="detected__row"><span>Database</span><code>412 tables, wp_ prefix</code></div>
                <div class="detected__row"><span>Confidence</span><code>high</code></div>
              </div>
              <div class="checklist mt-4">
                <div class="checkrow checkrow--pass">
                  <span class="checkrow__icon"><?= icon('check') ?></span>
                  <span><strong>Migration available</strong><span>Content and URL rewriting supported</span></span>
                  <span class="checkrow__value">recommended</span>
                </div>
                <div class="checkrow checkrow--warn">
                  <span class="checkrow__icon"><?= icon('alert') ?></span>
                  <span><strong>Clean install</strong><span>Existing content will not be imported</span></span>
                  <span class="checkrow__value">available</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<span data-actionbar-after aria-hidden="true"></span>

<section class="section">
  <div class="container container--wide">
    <?php $view->partial('partials.section-head', [
      'eyebrow' => 'The flow',
      'title' => 'Eight steps, each one reversible.',
      'body' => 'Nothing is written until the install step runs. You can go back, change a decision and re-run the checks at any point.',
    ]); ?>
    <div class="steps steps--2">
      <?php $n = 1; foreach ($steps as $key => $step): ?>
        <article class="step spotlight edge-light" data-reveal="<?= ($n - 1) * 40 ?>">
          <span class="step__meta"><?= icon($icons[$key] ?? 'check') ?></span>
          <span class="step__num"><?= str_pad((string) $n, 2, '0', STR_PAD_LEFT) ?></span>
          <h3 class="h4"><?= e($step['label']) ?></h3>
          <p><?= e($step['blurb']) ?></p>
        </article>
      <?php $n++; endforeach; ?>
    </div>
  </div>
</section>

<section class="section" id="migration" style="background:var(--bg-elev);border-block:1px solid var(--line);scroll-margin-top:calc(var(--header-h) + 2rem)">
  <div class="container container--wide">
    <div class="split split--wide-left">
      <div data-reveal>
        <span class="eyebrow">Migration and import</span>
        <h2 class="h2 mt-3">Moving an existing site without losing your rankings.</h2>
        <p class="lede mt-4">
          The migration step imports content from a JSON or CSV export and
          rewrites every absolute URL from the old origin to the new one — in
          body content, canonical tags, sitemap entries and structured data.
        </p>
        <p class="muted mt-4 measure">
          That last part is what most manual migrations get wrong. A missed
          absolute URL in an imported post body points visitors and crawlers at
          a domain you no longer control.
        </p>
        <div class="cluster mt-6">
          <a class="btn btn--ghost" href="<?= e(url('/resources/migrating-a-legacy-site-safely')) ?>">Read the migration guide<?= icon('arrow-right') ?></a>
        </div>
      </div>
      <div class="stack" style="--flow:.6rem" data-reveal="80">
        <?php foreach ([
          ['WordPress', 'wp-config.php, wp-content and wp_ table prefix'],
          ['Joomla', 'configuration.php and administrator manifests'],
          ['Drupal', 'settings.php and core Drupal class'],
          ['Laravel', 'artisan and bootstrap/app.php'],
          ['Magento', 'bin/magento and app/etc/env.php'],
          ['Static HTML', 'index.html in the document root'],
          ['Previous TECHBISS', 'installed.php config and platform schema'],
        ] as $platform): ?>
          <div class="card" style="padding:.85rem 1rem;display:flex;gap:.75rem;align-items:center">
            <?= icon('search', ['size' => 16]) ?>
            <span>
              <strong style="font-size:var(--t-0)"><?= e($platform[0]) ?></strong>
              <span class="tiny dim" style="display:block"><?= e($platform[1]) ?></span>
            </span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>

<section class="section">
  <div class="container container--wide">
    <div class="split">
      <div data-reveal>
        <span class="eyebrow">Automatic URL detection</span>
        <h2 class="h2 mt-3">It works out where it lives.</h2>
        <p class="lede mt-4">
          Sub-directory installs, reverse proxies, load balancers terminating TLS,
          CDN edges rewriting the host — the installer resolves the canonical
          URL through all of them and shows you what it found before committing.
        </p>
        <div class="detected mt-6">
          <div class="detected__row"><span>Scheme</span><code>https (X-Forwarded-Proto)</code></div>
          <div class="detected__row"><span>Host</span><code>techbiss.com</code></div>
          <div class="detected__row"><span>Base path</span><code>/app</code></div>
          <div class="detected__row"><span>Resolved URL</span><code>https://techbiss.com/app</code></div>
          <div class="detected__row"><span>Behind proxy</span><code>yes</code></div>
        </div>
      </div>
      <div data-reveal="80">
        <span class="eyebrow">Headless mode</span>
        <h2 class="h2 mt-3">Or skip the browser entirely.</h2>
        <p class="lede mt-4">
          The same engine runs from the command line, so a container image or CI
          pipeline can produce a fully installed platform with no interaction.
        </p>
        <div class="codeblock mt-5"><span class="c"># Full headless install</span>
php bin/techbiss install \
  --driver=mysql --host=db --database=techbiss \
  --username=techbiss --db-password=<span class="c">secret</span> \
  --url=https://example.com \
  --email=owner@example.com --password=<span class="c">strong-password</span> \
  --no-demo

<span class="c"># Verify afterwards</span>
php bin/techbiss health</div>
      </div>
    </div>
  </div>
</section>

<?php $view->partial('partials.cta-band', [
  'title' => 'Try the installer now.',
  'body' => 'Every product licence includes it. Walk the eight steps and see the requirement checks, the URL it resolves and the existing-site scan run against your own server.',
  'primary' => ['label' => 'Open the installer', 'path' => '/install'],
  'secondary' => ['label' => 'Browse products', 'path' => '/marketplace'],
]); ?>
<?php $view->stop(); ?>

<?php $view->start('after_body'); ?>
<div class="actionbar" data-actionbar>
  <a class="btn btn--ghost" href="<?= e(url('/marketplace')) ?>">Marketplace</a>
  <a class="btn btn--primary" href="<?= e(url('/install')) ?>">Open installer</a>
</div>
<?php $view->stop(); ?>
