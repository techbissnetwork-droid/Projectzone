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

<section><div class="wrap">
  <div class="sh rv"><span class="no">How it works</span><h2>Four steps,<br>every time.</h2>
    <p>We do them in the same order for everyone, and we do not skip one to hit a date.</p></div>
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

<section class="tight"><div class="wrap">
  <p class="say rv">Plenty of people can build you a website. We are the ones
     <mark>still answering the phone three years later.</mark></p>
</div></section>

<?php
closing_cta(setting('about.cta.heading'), setting('about.cta.body'),
    ['contact.php', 'Talk to us &rarr;'], ['services.php', 'See what we do']);
include APP_DIR . '/partials/footer.php';
