<?php
require_once __DIR__ . '/app/bootstrap.php';
require_installed();
require_once APP_DIR . '/partials/sections.php';

$pageTitle = setting('pricing.meta.title');
$pageDesc  = setting('pricing.meta.desc');
$activeNav = 'pricing';
$sym       = setting('site.currency', '$');
$builds    = active_packages('build');
$care      = active_packages('care');
$careBlurb = setting('pricing.care.sub');
$addons    = active_addons();

include APP_DIR . '/partials/head.php';

page_head(
    setting('pricing.hero.eyebrow'),
    setting('pricing.hero.line1'),
    setting('pricing.hero.line2'),
    setting('pricing.hero.lead'),
    [['index.php', 'Home'], [null, 'Pricing']],
    [['contact.php', 'Get a price &rarr;'], ['marketplace.php', 'Buy one ready-made']]
);

/** One row of package cards. */
function package_row(array $packages, string $sym): void
{
    ?>
  <div class="pl">
<?php foreach ($packages as $p): ?>
    <div class="pcard rv<?= $p['is_featured'] ? ' best' : '' ?>">
      <span class="k"><?= esc($p['name']) ?><?= $p['is_featured'] ? ' &mdash; most chosen' : '' ?></span>
      <div class="amt"><?= esc($p['price'] !== '' ? $sym . $p['price'] : 'Quoted') ?></div>
      <div class="per"><?= esc($p['period']) ?></div>
<?php if ($p['blurb']): ?>      <p class="who"><?= esc($p['blurb']) ?></p><?php endif; ?>
      <ul><?php foreach (lines($p['features']) as $f): ?><li><?= esc($f) ?></li><?php endforeach; ?></ul>
      <a class="pill<?= $p['is_featured'] ? '' : ' ghost' ?>" href="contact.php">Get a scope</a>
    </div>
<?php endforeach; ?>
  </div>
<?php
}
?>

<section><div class="wrap">
  <div class="sh rv"><span class="no">Prices</span>
    <h2 id="planheading"><?= esc(setting('pricing.build.heading')) ?></h2>
    <p id="planblurb"><?= esc(setting('pricing.build.sub')) ?></p>
  </div>

<?php if ($care): ?>
  <div class="switch rv" id="planswitch" role="tablist">
    <button role="tab" aria-selected="true" data-pane="build">Building your site</button>
    <button role="tab" aria-selected="false" data-pane="care">Looking after it</button>
  </div>
<?php endif; ?>

  <div class="pane" data-pane="build"><?php package_row($builds, $sym); ?></div>
<?php if ($care): ?>
  <div class="pane" data-pane="care" hidden><?php package_row($care, $sym); ?></div>
<?php endif; ?>
</div></section>

<?php if ($addons): ?>
<section><div class="wrap">
  <div class="sh rv"><span class="no">Extras</span>
    <h2><?= esc(setting('pricing.addons.heading')) ?></h2>
    <p><?= esc(setting('pricing.addons.sub')) ?></p></div>
  <div class="addons rv">
<?php foreach ($addons as $a): ?>
    <div class="addon">
      <b><?= esc($a['name']) ?></b>
      <div class="p"><?= esc($a['price'] !== '' ? $sym . $a['price'] : 'Quoted') ?></div>
      <p><?= esc($a['blurb']) ?></p>
    </div>
<?php endforeach; ?>
  </div>
</div></section>
<?php endif; ?>

<section><div class="wrap">
  <div class="sh rv"><span class="no">Side by side</span><h2>What you get<br>in each one.</h2>
    <p>Side by side, so nothing is hiding in the small print.</p></div>
  <div class="tablewrap rv">
    <table>
      <caption class="sr">Comparison of the build packages</caption>
      <thead><tr>
        <th scope="col">&nbsp;</th>
<?php foreach ($builds as $b): ?>
        <th scope="col"<?= $b['is_featured'] ? ' class="hot"' : '' ?>><?= esc($b['name']) ?></th>
<?php endforeach; ?>
      </tr></thead>
      <tbody>
<?php
$compare = [
  ['Pages included',            ['Up to 5', 'Up to 15', 'As agreed']],
  ['You can edit it yourself',  ['n', 'y', 'y']],
  ['Web address, hosting and security', ['y', 'y', 'y']],
  ['Business email set up',     ['y', 'y', 'y']],
  ['Groundwork for Google',     ['n', 'y', 'y']],
  ['Your Google listing',       ['y', 'y', 'y']],
  ['Online shop or booking',    ['n', 'n', 'y']],
  ['Joining up your other tools', ['n', 'n', 'y']],
  ['We look after it',          ['30 days', '90 days', 'Ongoing']],
];
foreach ($compare as [$label, $cells]): ?>
        <tr>
          <th scope="row"><?= $label ?></th>
<?php   foreach ($cells as $c): ?>
<?php     if ($c === 'y'): ?><td class="y">Yes</td>
<?php     elseif ($c === 'n'): ?><td class="n">&mdash;</td>
<?php     else: ?><td><?= esc($c) ?></td><?php endif; ?>
<?php   endforeach; ?>
        </tr>
<?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <p class="say small rv" style="margin-top:34px"><?= highlighted(setting('pricing.statement')) ?></p>
  <p class="formnote rv" style="margin-top:18px">Things we buy for you &mdash; your web address,
     hosting and email accounts &mdash; are charged at exactly what they cost, in your own name.
     For a small business that is usually <?= esc($sym) ?>80 to <?= esc($sym) ?>300 a year.</p>
</div></section>

<?php
faq_block('pricing', 'Questions about money.', 'Including the awkward ones.');
closing_cta(setting('pricing.cta.heading'), setting('pricing.cta.body'),
    ['contact.php', 'Get a price &rarr;'], ['services.php', 'See what we do']);
?>
<script>
/* One section, two sets of prices. */
(function () {
  var sw = document.getElementById('planswitch');
  if (!sw) return;
  var blurb   = document.getElementById('planblurb');
  var heading = document.getElementById('planheading');
  var titles  = {
    build: <?= json_encode(setting('pricing.build.heading')) ?>,
    care:  <?= json_encode(setting('pricing.care.heading')) ?>
  };
  var subs = {
    build: <?= json_encode(setting('pricing.build.sub')) ?>,
    care:  <?= json_encode($careBlurb) ?>
  };
  sw.addEventListener('click', function (e) {
    var btn = e.target.closest('button[data-pane]');
    if (!btn) return;
    var want = btn.dataset.pane;
    sw.querySelectorAll('button').forEach(function (b) {
      b.setAttribute('aria-selected', String(b === btn));
    });
    document.querySelectorAll('.pane[data-pane]').forEach(function (pane) {
      pane.hidden = pane.dataset.pane !== want;
    });
    if (heading) heading.textContent = titles[want];
    if (blurb)   blurb.textContent   = subs[want];
  });
})();
</script>
<?php
include APP_DIR . '/partials/footer.php';
