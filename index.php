<?php
require_once __DIR__ . '/app/bootstrap.php';
require_installed();
require_once APP_DIR . '/partials/sections.php';

$pageTitle = setting('home.meta.title');
$pageDesc  = setting('home.meta.desc');
$activeNav = 'home';

$work     = public_portfolio(3);
$products = active_products(3);
$quotes   = active_testimonials();
$builds   = active_packages('build');

include APP_DIR . '/partials/head.php';
?>

<section class="hero"><div class="wrap">
  <span class="badge"><i aria-hidden="true"></i><?= esc(setting('home.hero.eyebrow')) ?></span>
  <h1>
    <span class="r"><s class="chrome"><?= esc(setting('home.hero.line1')) ?></s></span>
    <span class="r"><s class="acc"><?= esc(setting('home.hero.line2')) ?></s></span>
  </h1>
  <div class="hr-b">
    <p><?= esc(setting('home.hero.lead')) ?></p>
    <div class="hr-acts">
      <a class="pill" href="services.php"><?= esc(setting('home.hero.cta1')) ?></a>
      <a class="pill ghost" href="contact.php"><?= esc(setting('home.hero.cta2')) ?></a>
    </div>
  </div>
  <div class="orbit" aria-hidden="true">
    <span class="ring"></span><span class="ring"></span><span class="ring"></span>
    <span class="sat s1"><b>Domain</b></span>
    <span class="sat s2"><b>SSL</b></span>
    <span class="sat s3"><b>Email</b></span>
    <span class="core"></span>
  </div>
</div></section>

<?php runner_strip(); ?>

<section id="stack"><div class="wrap">
  <div class="sh rv"><span class="no">What we run</span>
    <h2><?= esc(setting('home.stack.heading')) ?></h2>
    <p><?= esc(setting('home.stack.sub')) ?></p>
  </div>
  <div class="stack">
<?php
$blocks = [
  ['Build', 'Websites and apps designed for the business you actually run — fast, indexed, and yours to edit without calling anyone.',
   ['Websites, 5 to 50 pages, with a CMS you can use', 'Booking, ordering, portals, dashboards',
    'E-commerce and payments', 'Content written with you, not filled with lorem'],
   'Build / design / ship'],
  ['Set up', 'Domain, hosting, SSL and business email, configured together as one system — under your own accounts, in your own name.',
   ['Domain registered to you, never to us', 'Hosting provisioned, tuned, backed up nightly',
    'Certificates issued and auto-renewed', 'SPF, DKIM and DMARC set so mail lands'],
   'Domain / host / SSL / mail'],
  ['Be found', 'Most businesses we meet already work. They are just invisible to anyone searching for what they sell.',
   ['Technical SEO and a sitemap that gets read', 'Google Business and Maps, details kept accurate',
    'Branding — logo, colour, type, assets', 'Listings tidied across the web'],
   'SEO / maps / brand'],
  ['Keep running', 'The part most vendors skip. Launch is the start of the job, not the end of it.',
   ['Uptime monitoring, 60-second checks', 'Nightly backups with 30-day retention',
    'Renewals watched so nothing lapses on a Sunday', 'One number for support — the people who built it'],
   'Monitor / backup / support'],
];
foreach ($blocks as $i => [$title, $body, $bullets, $label]): ?>
    <article class="sc"><div class="in"><div>
      <span class="no">Block 0<?= $i + 1 ?></span><h3><?= esc($title) ?></h3>
      <p><?= esc($body) ?></p>
      <ul><?php foreach ($bullets as $b): ?><li><?= esc($b) ?></li><?php endforeach; ?></ul>
    </div><div class="art" aria-hidden="true"><div class="mesh"></div><div class="orb"></div>
      <span class="lbl"><?= esc($label) ?></span></div></div></article>
<?php endforeach; ?>
  </div>
  <div class="spacer"></div>
  <div class="rv center"><a class="pill ghost lg" href="services.php">All ten services in full &rarr;</a></div>
</div></section>

<section id="proof"><div class="wrap">
  <div class="sh rv"><span class="no">Proof</span><h2><?= esc(setting('home.proof.heading')) ?></h2></div>
  <div class="nums">
<?php for ($i = 1; $i <= 4; $i++): ?>
    <div class="nm rv"><b><?= esc(setting("home.stat{$i}.value")) ?></b>
      <span><?= esc(setting("home.stat{$i}.label")) ?></span></div>
<?php endfor; ?>
  </div>
</div></section>

<?php if ($work): ?>
<section id="work"><div class="wrap">
  <div class="sh rv"><span class="no">Work</span>
    <h2><?= esc(setting('home.portfolio.heading')) ?></h2>
    <p><?= esc(setting('home.portfolio.sub')) ?></p>
  </div>
  <div class="grid-3">
<?php foreach ($work as $p) { portfolio_card($p); } ?>
  </div>
  <div class="spacer"></div>
  <div class="rv center"><a class="pill ghost lg" href="portfolio.php">See all our work &rarr;</a></div>
</div></section>
<?php endif; ?>

<section id="process"><div class="wrap">
  <div class="sh rv"><span class="no">Process</span>
    <h2><?= esc(setting('home.process.heading')) ?></h2>
    <p><?= esc(setting('home.process.sub')) ?></p></div>
  <div class="pr">
    <div class="ps"><h4>Conversation</h4>
      <p>You describe what the business does and what is missing. We say what is worth doing
         first &mdash; and what is not.</p></div>
    <div class="ps"><h4>Written scope</h4>
      <p>Everything listed on one page, one fixed number. Third-party costs passed through at
         cost, in your name.</p></div>
    <div class="ps"><h4>Build &amp; set up</h4>
      <p>Domain, hosting, SSL, email and the site configured together, under your own accounts,
         documented.</p></div>
    <div class="ps"><h4>Live &amp; watched</h4>
      <p>Monitoring, backups, renewals and support from the same people who built the thing.</p></div>
  </div>
</div></section>

<?php if ($products): ?>
<section id="market"><div class="wrap">
  <div class="sh rv"><span class="no">Marketplace</span>
    <h2><?= esc(setting('home.market.heading')) ?></h2>
    <p><?= esc(setting('home.market.sub')) ?></p>
  </div>
  <div class="grid-3">
<?php foreach ($products as $p) { product_card($p); } ?>
  </div>
  <div class="spacer"></div>
  <div class="rv center"><a class="pill ghost lg" href="marketplace.php">Browse the marketplace &rarr;</a></div>
</div></section>
<?php endif; ?>

<?php if ($quotes): ?>
<section id="voices"><div class="wrap">
  <div class="sh rv"><span class="no">Voices</span><h2><?= esc(setting('home.quotes.heading')) ?></h2></div>
  <div class="qbox rv" id="qbox">
<?php foreach ($quotes as $i => $q): ?>
    <div class="qs<?= $i === 0 ? ' on' : '' ?>">
      <blockquote><?= esc($q['quote']) ?></blockquote>
      <div class="m"><span><?= esc($q['author']) ?><?= $q['role'] ? ' &middot; ' . esc($q['role']) : '' ?></span>
        <span><?= sprintf('%02d / %02d', $i + 1, count($quotes)) ?></span></div>
    </div>
<?php endforeach; ?>
  </div>
<?php if (count($quotes) > 1): ?>
  <div class="qctl">
    <button id="qprev" type="button" aria-label="Previous quote">&larr;</button>
    <button id="qnext" type="button" aria-label="Next quote">&rarr;</button>
  </div>
<?php endif; ?>
</div></section>
<?php endif; ?>

<?php if ($builds): ?>
<section id="pricing"><div class="wrap">
  <div class="sh rv"><span class="no">Pricing</span>
    <h2>Prices you can see<br>before you call.</h2>
    <p><?= esc(setting('pricing.hero.lead')) ?></p></div>
  <div class="pl">
<?php $sym = setting('site.currency', '$');
      foreach ($builds as $b): ?>
    <div class="pcard rv<?= $b['is_featured'] ? ' best' : '' ?>">
      <span class="k"><?= esc($b['name']) ?><?= $b['is_featured'] ? ' &mdash; most chosen' : '' ?></span>
      <div class="amt"><?= esc($b['price'] !== '' ? $sym . $b['price'] : 'Quoted') ?></div>
      <div class="per"><?= esc($b['period']) ?></div>
<?php if ($b['blurb']): ?>      <p class="who"><?= esc($b['blurb']) ?></p><?php endif; ?>
      <ul><?php foreach (lines($b['features']) as $f): ?><li><?= esc($f) ?></li><?php endforeach; ?></ul>
      <a class="pill<?= $b['is_featured'] ? '' : ' ghost' ?>" href="pricing.php">See what is included</a>
    </div>
<?php endforeach; ?>
  </div>
</div></section>
<?php endif; ?>

<?php
closing_cta(setting('home.cta.heading'), setting('home.cta.body'),
    ['contact.php', 'Start now &rarr;'], ['pricing.php', 'See pricing']);
include APP_DIR . '/partials/footer.php';
