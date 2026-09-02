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
$addons    = active_addons();

include APP_DIR . '/partials/head.php';

page_head(
    setting('pricing.hero.eyebrow'),
    setting('pricing.hero.line1'),
    setting('pricing.hero.line2'),
    setting('pricing.hero.lead'),
    [['index.php', 'Home'], [null, 'Pricing']],
    [['contact.php', 'Get an exact number &rarr;'], ['marketplace.php', 'Buy something premade']]
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
  <div class="sh rv"><span class="no">Build</span>
    <h2><?= esc(setting('pricing.build.heading')) ?></h2>
    <p><?= esc(setting('pricing.build.sub')) ?></p></div>
  <?php package_row($builds, $sym); ?>
</div></section>

<?php if ($care): ?>
<section><div class="wrap">
  <div class="sh rv"><span class="no">Care</span>
    <h2><?= esc(setting('pricing.care.heading')) ?></h2>
    <p><?= esc(setting('pricing.care.sub')) ?></p></div>
  <?php package_row($care, $sym); ?>
</div></section>
<?php endif; ?>

<?php if ($addons): ?>
<section><div class="wrap">
  <div class="sh rv"><span class="no">Add-ons</span>
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

<?php statement(setting('pricing.statement')); ?>

<section><div class="wrap">
  <div class="sh rv"><span class="no">Side by side</span><h2>What is in<br>each package.</h2>
    <p>So nothing is hiding in a footnote.</p></div>
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
  ['Pages included',            ['Up to 5', 'Up to 15', 'As scoped']],
  ['Editable admin area',       ['n', 'y', 'y']],
  ['Domain, hosting &amp; SSL set up', ['y', 'y', 'y']],
  ['Business email configured', ['y', 'y', 'y']],
  ['SEO groundwork',            ['n', 'y', 'y']],
  ['Google Business listing',   ['y', 'y', 'y']],
  ['E-commerce or booking',     ['n', 'n', 'y']],
  ['Integrations &amp; automation',   ['n', 'n', 'y']],
  ['Aftercare',                 ['30 days', '90 days', 'Ongoing']],
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
  <p class="formnote rv" style="margin-top:18px">Third-party costs &mdash; domain registration,
     hosting, email licences &mdash; are billed to you at cost, in your own name. Typically
     <?= esc($sym) ?>80 to <?= esc($sym) ?>300 a year for a small business.</p>
</div></section>

<?php
faq_block('pricing', 'Pricing questions.', 'The awkward ones, answered up front.');
closing_cta(setting('pricing.cta.heading'), setting('pricing.cta.body'),
    ['contact.php', 'Get an exact number &rarr;'], ['services.php', 'See the services']);
include APP_DIR . '/partials/footer.php';
