<?php
/**
 * Admin chrome. Call admin_head($title) and admin_foot() around a page body.
 */

function admin_counts(): array
{
    static $c = null;
    if ($c !== null) {
        return $c;
    }
    return $c = [
        'enquiries' => db_count("SELECT COUNT(*) FROM enquiries WHERE status = 'new'"),
        'orders'    => db_count("SELECT COUNT(*) FROM orders WHERE status = 'new'"),
        'tickets'   => db_count("SELECT COUNT(*) FROM tickets WHERE status IN ('open','answered')"),
    ];
}

function admin_nav(): array
{
    $c = admin_counts();
    return [
        'Overview' => [
            ['index.php',     'Dashboard',  null],
            ['enquiries.php', 'Enquiries',  $c['enquiries']],
            ['orders.php',    'Orders',     $c['orders']],
            ['tickets.php',   'Support',    $c['tickets']],
        ],
        'Clients' => [
            ['projects.php',    'Projects',    null],
            ['clients.php',     'Client accounts', null],
            ['maintenance.php', 'Maintenance log', null],
        ],
        'Website' => [
            ['content.php',                    'Page text',    null],
            ['resource.php?type=services',     'Services',     null],
            ['resource.php?type=industries',   'Industries',   null],
            ['resource.php?type=packages',     'Pricing',      null],
            ['resource.php?type=addons',       'Add-ons',      null],
            ['resource.php?type=faqs',         'FAQs',         null],
            ['resource.php?type=testimonials', 'Testimonials', null],
        ],
        'Catalogue' => [
            ['resource.php?type=portfolio', 'Portfolio',   null],
            ['resource.php?type=products',  'Marketplace', null],
        ],
        'System' => [
            ['settings.php', 'Settings', null],
            ['users.php',    'Team',     null],
            ['activity.php', 'Activity', null],
        ],
    ];
}

function admin_head(string $title, string $current = ''): void
{
    $user = current_user();
    $nav  = admin_nav();
    ?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= esc($title) ?> — TECHBISS admin</title>
<meta name="robots" content="noindex,nofollow">
<link rel="icon" href="../assets/favicon.svg" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=Manrope:wght@400;500;600&family=Azeret+Mono:wght@400;500&display=swap">
<link rel="stylesheet" href="assets/admin.css">
</head>
<body>
<div class="shell">
  <aside class="side">
    <a class="brand" href="index.php"><i aria-hidden="true"></i>TECHBISS</a>
<?php foreach ($nav as $group => $items): ?>
    <h6><?= esc($group) ?></h6>
<?php   foreach ($items as [$href, $label, $tally]):
          $on = $current !== '' && str_starts_with($href, $current); ?>
    <a class="nav<?= $on ? ' on' : '' ?>" href="<?= esc($href) ?>"><?= esc($label) ?>
<?php     if ($tally) { echo '<span class="tally">' . (int) $tally . '</span>'; } ?></a>
<?php   endforeach; ?>
<?php endforeach; ?>
    <div class="foot">
      <div class="who"><?= esc($user['name'] ?? '') ?> &middot; <?= esc($user['role'] ?? '') ?></div>
      <a href="../index.php" target="_blank" rel="noopener">View the site &nearr;</a>
      <a href="account.php">Your account</a>
      <a href="logout.php">Sign out</a>
    </div>
  </aside>
  <div class="main">
<?php foreach (flash_take() as $f): ?>
    <div class="flash <?= esc($f['type']) ?>"><?= $f['message'] ?></div>
<?php endforeach; ?>
<?php
}

function admin_foot(): void
{
    ?>
  </div>
</div>
<script>
/* Confirm anything destructive. */
document.querySelectorAll('form[data-confirm]').forEach(function (f) {
  f.addEventListener('submit', function (e) {
    if (!confirm(f.dataset.confirm)) { e.preventDefault(); }
  });
});
/* Keep a slug in step with a title until the slug is edited by hand. */
(function () {
  var title = document.querySelector('[data-slug-source]');
  var slug  = document.querySelector('[data-slug-target]');
  if (!title || !slug || slug.value !== '') return;
  var touched = false;
  slug.addEventListener('input', function () { touched = true; });
  title.addEventListener('input', function () {
    if (touched) return;
    slug.value = title.value.toLowerCase().replace(/[^a-z0-9]+/g, '-')
                     .replace(/^-+|-+$/g, '');
  });
})();
</script>
</body>
</html>
<?php
}

/** The page title bar. */
function admin_page_head(string $title, string $blurb = '', array $actions = [], array $crumbs = []): void
{
    ?>
    <div class="ph">
      <div>
<?php if ($crumbs): ?>
        <span class="crumb">
<?php   $last = array_key_last($crumbs);
        foreach ($crumbs as $i => [$href, $label]):
          if ($href && $i !== $last): ?><a href="<?= esc($href) ?>"><?= esc($label) ?></a> / <?php
          else: ?><?= esc($label) ?><?php endif;
        endforeach; ?>
        </span>
<?php endif; ?>
        <h1><?= esc($title) ?></h1>
<?php if ($blurb): ?>        <p><?= esc($blurb) ?></p><?php endif; ?>
      </div>
<?php if ($actions): ?>
      <div class="acts">
<?php   foreach ($actions as [$href, $label, $class]): ?>
        <a class="btn <?= esc($class) ?>" href="<?= esc($href) ?>"><?= $label ?></a>
<?php   endforeach; ?>
      </div>
<?php endif; ?>
    </div>
<?php
}
