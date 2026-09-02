<?php
/** Expects $pageTitle, and the page body echoed between _header and _footer. */
function admin_header(string $pageTitle): void
{
    $u = auth_user();
    $here = current_page();
    $r = $_GET['r'] ?? '';
    ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title><?= e($pageTitle) ?> — TECHBISS admin</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<link rel="icon" href="<?= e(base_url('assets/favicon.svg')) ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= e(base_url('admin/assets/admin.css')) ?>">
</head>
<body>
<header class="abar">
  <a class="abrand" href="<?= e(base_url('admin/index.php')) ?>"><span class="amark">T</span> TECHBISS admin</a>
  <div class="aright">
    <a href="<?= e(base_url('index.php')) ?>" target="_blank" rel="noopener">View site ↗</a>
    <span class="awho"><?= e($u['name'] ?? '') ?></span>
    <a class="abtn" href="<?= e(base_url('admin/logout.php')) ?>">Sign out</a>
  </div>
</header>
<div class="ashell">
  <nav class="aside">
    <p class="agroup">Overview</p>
    <a href="<?= e(base_url('admin/index.php')) ?>" class="<?= $here === 'index.php' ? 'on' : '' ?>">Dashboard</a>
    <a href="<?= e(base_url('admin/enquiries.php')) ?>" class="<?= $here === 'enquiries.php' ? 'on' : '' ?>">
      Enquiries
      <?php $new = (int) scalar("SELECT COUNT(*) FROM enquiries WHERE status = 'new'"); if ($new): ?>
        <span class="apill"><?= $new ?></span>
      <?php endif; ?>
    </a>

    <p class="agroup">Page text</p>
    <?php foreach (['global' => 'Company details', 'home' => 'Home page', 'services' => 'Services page',
                    'industries' => 'Industries page', 'pricing' => 'Pricing page', 'about' => 'About page',
                    'contact' => 'Contact page'] as $g => $label): ?>
      <a href="<?= e(base_url('admin/content.php?g=' . $g)) ?>" class="<?= ($here === 'content.php' && ($_GET['g'] ?? '') === $g) ? 'on' : '' ?>"><?= e($label) ?></a>
    <?php endforeach; ?>

    <p class="agroup">Content</p>
    <?php foreach (resources() as $key => $res): ?>
      <a href="<?= e(base_url('admin/resource.php?r=' . $key)) ?>" class="<?= ($here === 'resource.php' && $r === $key) ? 'on' : '' ?>"><?= e($res['label']) ?></a>
    <?php endforeach; ?>

    <p class="agroup">Account</p>
    <a href="<?= e(base_url('admin/users.php')) ?>" class="<?= $here === 'users.php' ? 'on' : '' ?>">Admin users</a>
  </nav>
  <main class="amain">
    <?php if ($f = flash()): ?>
      <div class="aflash aflash--<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
    <?php endif; ?>
    <?php
}

function admin_footer(): void
{
    ?>
  </main>
</div>
</body>
</html>
    <?php
}
