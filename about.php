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
    [['contact.php', 'Have a chat &rarr;'], ['portfolio.php', 'See our work']]
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
    <p>Six things we stick to, including the ones that cost us money.</p></div>
  <div class="grid-auto">
<?php
$rules = [
  ['Everything is in your name', '◇',
   'Your web address, hosting and email are all bought in your business name, not ours. We do the work without owning your things. If you ever leave us, nothing has to be moved or handed back.'],
  ['We do not add a markup', '◈',
   'Your web address, hosting and email accounts are charged at exactly what they cost us. We do not buy a $12 web address, sell it to you for $60, and call that a service.'],
  ['We will talk you out of work', '⛨',
   'If your site is fine and only your email or your Google listing needs fixing, we will say so and quote for that instead. Selling you a rebuild you did not need loses us the next job too.'],
  ['Going live is the start', '⟲',
   'Checks, backups, renewals and updates carry on after your site is live. Most companies disappear the day they hand over, which is exactly when things start to break.'],
  ['One number to call', '☎',
   'The person who answers is the person who built it. No queue in another country, and no explaining your setup from scratch every single time.'],
  ['A written price, agreed first', '▣',
   'Everything you are getting on one page, before any work starts. Ask for something extra and we tell you the price before we do it, never after.'],
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
  <p class="say rv">Plenty of people can build you a website. We are the ones
     <mark>still answering the phone three years later.</mark></p>
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
    ['contact.php', 'Talk to us &rarr;'], ['services.php', 'See what we do']);
include APP_DIR . '/partials/footer.php';
