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
  ['We build it', 'A website made for the business you actually run — quick on a phone, easy to find, and simple enough that you can change the words yourself.',
   ['Websites you can edit yourself, no code', 'Booking, ordering and customer logins',
    'Online shops and card payments', 'Words written with you, not filled with waffle'],
   'Design and build'],
  ['We set it up', 'Your web address, hosting, the security padlock and business email — all sorted together, and all in your name rather than ours.',
   ['Your web address bought in your name, never ours', 'Hosting set up, made quick, copied every night',
    'The padlock in place and renewed automatically', 'Email set up so it does not land in spam'],
   'Address, hosting, email'],
  ['People find you', 'Most businesses we meet are doing fine. They are just invisible to anyone searching for what they sell.',
   ['The groundwork so Google can read your site', 'Your Google and Maps listing, with the right hours',
    'Logo, colours and fonts if you need them', 'Your details tidied up everywhere online'],
   'Google and Maps'],
  ['We keep it going', 'The bit most companies skip. Going live is the start of the job, not the end of it.',
   ['Checked every minute to make sure it is up', 'A copy saved every night, kept for a month',
    'Renewals watched, so nothing expires on a Sunday', 'One number to call — the people who built it'],
   'Looking after it'],
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
  <div class="rv center"><a class="pill ghost lg" href="services.php">See everything we do &rarr;</a></div>
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
  <div class="rv center"><a class="pill ghost lg" href="portfolio.php">See more of our work &rarr;</a></div>
</div></section>
<?php endif; ?>

<section id="process"><div class="wrap">
  <div class="sh rv"><span class="no">Process</span>
    <h2><?= esc(setting('home.process.heading')) ?></h2>
    <p><?= esc(setting('home.process.sub')) ?></p></div>
  <div class="pr">
    <div class="ps"><h4>We talk</h4>
      <p>You tell us what your business does and what is missing. We tell you what is worth
         doing first, and what is not worth paying for yet.</p></div>
    <div class="ps"><h4>You get a price</h4>
      <p>Everything you are getting, listed on one page, with one fixed price. Anything we buy
         for you is charged at cost.</p></div>
    <div class="ps"><h4>We build it</h4>
      <p>Website, web address, hosting, security and email, all set up together and all in your
         name. We write down how it works.</p></div>
    <div class="ps"><h4>We look after it</h4>
      <p>Checks, backups, renewals and support, from the same people who built it. Not a call
         centre.</p></div>
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
  <div class="rv center"><a class="pill ghost lg" href="marketplace.php">See the ready-made ones &rarr;</a></div>
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
    <h2>Prices, before<br>you call us.</h2>
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
      <a class="pill<?= $b['is_featured'] ? '' : ' ghost' ?>" href="pricing.php">What you get</a>
    </div>
<?php endforeach; ?>
  </div>
</div></section>
<?php endif; ?>

<?php
closing_cta(setting('home.cta.heading'), setting('home.cta.body'),
    ['contact.php', 'Start now &rarr;'], ['pricing.php', 'See pricing']);
include APP_DIR . '/partials/footer.php';
