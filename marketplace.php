<?php
require_once __DIR__ . '/app/bootstrap.php';
require_installed();
require_once APP_DIR . '/partials/sections.php';

$pageTitle = setting('market.meta.title');
$pageDesc  = setting('market.meta.desc');
$activeNav = 'marketplace';
$sym       = setting('site.currency', '$');

$items      = active_products();
$categories = [];
foreach ($items as $it) {
    if ($it['category'] !== '') {
        $categories[$it['category']] = true;
    }
}
$filter = get('category');
if ($filter !== '' && isset($categories[$filter])) {
    $items = array_values(array_filter($items, fn($i) => $i['category'] === $filter));
} else {
    $filter = '';
}

include APP_DIR . '/partials/head.php';

page_head(
    setting('market.hero.eyebrow'),
    setting('market.hero.line1'),
    setting('market.hero.line2'),
    setting('market.hero.lead'),
    [['index.php', 'Home'], [null, 'Marketplace']],
    [['#listings', 'See what is ready &rarr;'], ['contact.php', 'Ask for something else']]
);
?>

<section id="listings"><div class="wrap">
<?php if (!$items && $filter === ''): ?>
  <div class="empty rv">
    <h2>Nothing listed yet.</h2>
    <p><?= esc(setting('market.empty')) ?></p>
    <a class="pill" href="contact.php">Tell us what you need &rarr;</a>
  </div>
<?php else: ?>
<?php if ($categories): ?>
  <div class="filters rv">
    <a class="chip<?= $filter === '' ? ' on' : '' ?>" href="marketplace.php">Everything</a>
<?php foreach (array_keys($categories) as $c): ?>
    <a class="chip<?= $filter === $c ? ' on' : '' ?>" href="marketplace.php?category=<?= urlencode($c) ?>"><?= esc($c) ?></a>
<?php endforeach; ?>
  </div>
<?php endif; ?>
  <div class="grid-3">
<?php foreach ($items as $p) { product_card($p); } ?>
  </div>
<?php if (!$items): ?>
  <p class="formnote rv">Nothing in that category yet.
     <a href="marketplace.php" style="color:var(--acc)">Show everything</a>.</p>
<?php endif; ?>
<?php endif; ?>
</div></section>

<section><div class="wrap">
  <div class="sh rv"><span class="no">How buying works</span>
    <h2>Already built.<br>Yours this week.</h2>
    <p>Nothing is taken from your card on this website. You send the order, we confirm it and send
       you payment details, then you get the files &mdash; or we set the whole thing up for you.</p></div>
  <div class="pr">
    <div class="ps"><h4>Pick one</h4>
      <p>Each one shows what is included and what it costs. Ask us for a walkthrough if you want
         to see it working first.</p></div>
    <div class="ps"><h4>Send the order</h4>
      <p>A short form. No card details and no account to create. We come back within one working
         day with how to pay and a date.</p></div>
    <div class="ps"><h4>We can set it up</h4>
      <p>For <?= esc($sym . setting('market.setup.price', '450')) ?> we put it on your own web
         address, sort the hosting, security and email, and put your own words in.</p></div>
    <div class="ps"><h4>You get the keys</h4>
      <p>All the files, every login, and a note of how it is set up. In your name, not ours.</p></div>
  </div>
</div></section>

<section class="tight"><div class="wrap">
  <div class="notebox rv">
    <h3>What we do if you ask us to set it up</h3>
    <p><?= esc(setting('market.setup.blurb')) ?></p>
    <p class="formnote">Completely optional. Buy the files on their own and install it yourself if
       you would rather &mdash; you get the same files and instructions either way.</p>
  </div>
</div></section>

<?php
closing_cta('Nothing here quite right?',
    'Tell us what you are after. If we have something close we will say so, and if not we will price up building it for you.',
    ['contact.php', 'Ask us &rarr;'], ['pricing.php', 'See our prices']);
include APP_DIR . '/partials/footer.php';
