<?php
/** Client portal chrome — the public site's look, in a simpler shell. */

function client_head(string $title, string $current = ''): void
{
    $user = current_user();
    $nav  = [
        ['index.php',       'Overview'],
        ['project.php',     'My project'],
        ['support.php',     'Support'],
        ['maintenance.php', 'Maintenance'],
        ['account.php',     'Account'],
    ];
    ?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= esc($title) ?> — <?= esc(setting('site.name', 'TECHBISS')) ?></title>
<meta name="robots" content="noindex,nofollow">
<link rel="icon" href="../assets/favicon.svg" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=Manrope:wght@400;500;600&family=Azeret+Mono:wght@400;500&display=swap">
<link rel="stylesheet" href="../admin/assets/admin.css">
<link rel="stylesheet" href="assets/portal.css">
</head>
<body class="portal">
<header class="phead-bar">
  <div class="inner">
    <a class="brand" href="index.php"><i aria-hidden="true"></i><?= esc(setting('site.name', 'TECHBISS')) ?></a>
    <nav class="tabs" aria-label="Portal">
<?php foreach ($nav as [$href, $label]): ?>
      <a href="<?= $href ?>"<?= $current === $href ? ' class="on"' : '' ?>><?= esc($label) ?></a>
<?php endforeach; ?>
    </nav>
    <div class="me">
      <span><?= esc($user['name'] ?? '') ?></span>
      <a class="btn ghost sm" href="logout.php">Sign out</a>
    </div>
  </div>
</header>
<main class="portal-main">
<?php foreach (flash_take() as $f): ?>
  <div class="flash <?= esc($f['type']) ?>"><?= $f['message'] ?></div>
<?php endforeach; ?>
<?php
}

function client_foot(): void
{
    ?>
</main>
<footer class="portal-foot">
  <div class="inner">
    <span>&copy; <?= date('Y') ?> <?= esc(setting('site.name', 'TECHBISS')) ?></span>
    <span>Support: <a href="mailto:<?= esc(setting('site.support_email')) ?>"><?= esc(setting('site.support_email')) ?></a>
      &middot; <?= esc(setting('site.hours')) ?></span>
    <a href="../index.php">Back to the website</a>
  </div>
</footer>
<script>
document.querySelectorAll('form[data-confirm]').forEach(function (f) {
  f.addEventListener('submit', function (e) {
    if (!confirm(f.dataset.confirm)) { e.preventDefault(); }
  });
});
</script>
</body>
</html>
<?php
}

/** The client's project, or null. */
function my_project(): ?array
{
    $u = current_user();
    if (!$u) {
        return null;
    }
    $id = get_int('project');
    if ($id) {
        $p = db_one('SELECT * FROM projects WHERE id = ? AND user_id = ?', [$id, $u['id']]);
        if ($p) {
            return $p;
        }
    }
    return db_one('SELECT * FROM projects WHERE user_id = ? ORDER BY id DESC', [$u['id']]);
}
