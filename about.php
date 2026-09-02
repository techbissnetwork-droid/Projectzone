<?php
require_once __DIR__ . '/app/bootstrap.php';
require_installed();
require_once APP_DIR . '/partials/sections.php';

$pageTitle = setting('about.meta.title');
$pageDesc  = setting('about.meta.desc');
$activeNav = 'about';

include APP_DIR . '/partials/head.php';

page_head(
    setting('about.hero.eyebrow'),
    setting('about.hero.line1'),
    setting('about.hero.line2'),
    setting('about.hero.lead'),
    [['index.php', 'Home'], [null, 'About']],
    [['contact.php', 'Start a conversation &rarr;'], ['portfolio.php', 'See our work']]
);
?>

<section><div class="wrap">
  <div class="article">
    <div class="body">
      <h2 class="subhead big"><?= esc(setting('about.story.heading')) ?></h2>
      <?= esc_para(setting('about.story.body')) ?>
    </div>
    <aside class="side">
      <div class="factbox">
        <h4>At a glance</h4>
<?php for ($i = 1; $i <= 4; $i++): ?>
        <div class="fact"><span><?= esc(setting("home.stat{$i}.label")) ?></span>
          <strong><?= esc(setting("home.stat{$i}.value")) ?></strong></div>
<?php endfor; ?>
      </div>
      <div class="factbox">
        <h4>Reach us</h4>
        <div class="fact"><span>Email</span><strong><?= esc(setting('site.email')) ?></strong></div>
        <div class="fact"><span>Support</span><strong><?= esc(setting('site.support_email')) ?></strong></div>
        <div class="fact"><span>Hours</span><strong><?= esc(setting('site.hours')) ?></strong></div>
      </div>
    </aside>
  </div>
</div></section>

<section><div class="wrap">
  <div class="sh rv"><span class="no">How we work</span>
    <h2><?= esc(setting('about.rules.heading')) ?></h2>
    <p>Five things we hold to, including the ones that cost us work.</p></div>
  <div class="grid-auto">
<?php
$rules = [
  ['Everything in your name', '◇',
   'Domain, hosting, email and every third-party account is registered to your business, not to us. We do the admin without holding the asset. If you leave, nothing has to move.'],
  ['Third-party costs at cost', '◈',
   'Domain registration, hosting and email licences are billed to you at what they cost. We do not mark up a domain and call it a service.'],
  ['We will talk you out of work', '⛨',
   'If your site works and only needs email or search fixed, we say so and quote for the smaller job. A rebuild you did not need is a bad sale twice.'],
  ['Launch is the start', '⟲',
   'Monitoring, backups, renewals and updates continue after go-live. Most vendors disappear at handover; that is when most things actually break.'],
  ['One number for support', '☎',
   'The people who answer built the thing. No ticket queue in another timezone, no explaining your setup from scratch each time.'],
  ['Written scope, fixed price', '▣',
   'Everything listed on one page before work starts. If you ask for something outside it, we quote that first — never after the fact.'],
];
foreach ($rules as [$title, $icon, $body]): ?>
    <article class="card rv">
      <div class="ic" aria-hidden="true"><?= $icon ?></div>
      <h3><?= esc($title) ?></h3>
      <p><?= esc($body) ?></p>
    </article>
<?php endforeach; ?>
  </div>
</div></section>

<section class="tight"><div class="wrap">
  <p class="say rv">We are not short of competition. We are just the ones
     <mark>who answer the phone in year three.</mark></p>
</div></section>

<?php $work = public_portfolio(3); if ($work): ?>
<section><div class="wrap">
  <div class="sh rv"><span class="no">Work</span><h2>What that<br>looks like.</h2></div>
  <div class="grid-3">
<?php foreach ($work as $p) { portfolio_card($p); } ?>
  </div>
</div></section>
<?php endif; ?>

<?php
closing_cta(setting('about.cta.heading'), setting('about.cta.body'),
    ['contact.php', 'Talk to us &rarr;'], ['services.php', 'See the services']);
include APP_DIR . '/partials/footer.php';
