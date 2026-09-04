<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/icons.php';
require_once __DIR__ . '/../includes/admin_layout.php';
require_installed('../install/');

$staff = require_staff();
$pdo = db();

$FIELDS = [
    'hero_headline_main' => 'Homepage headline (plain part)',
    'hero_headline_accent' => 'Homepage headline (highlighted part)',
    'hero_subheadline' => 'Homepage subheading',
    'site_tagline' => 'Footer tagline',
    'contact_email' => 'Contact email',
    'contact_phone' => 'Contact phone',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check((string)($_POST['csrf'] ?? ''))) {
        flash('Your session expired — please try again.', 'error');
    } else {
        $stmt = $pdo->prepare('INSERT INTO settings (id, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)');
        foreach ($FIELDS as $key => $label) {
            $stmt->execute([$key, trim((string)($_POST[$key] ?? ''))]);
        }
        flash('Site settings updated — live on the public site now.');
    }
    header('Location: settings.php');
    exit;
}

// Bypass the request-lifetime cache so the form shows what was just saved.
$current = $pdo->query('SELECT id, value FROM settings')->fetchAll(PDO::FETCH_KEY_PAIR);
$token = csrf_token();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Settings — TECHBISS Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/style.css?v=<?= @filemtime(__DIR__ . '/../assets/style.css') ?: '1' ?>">
</head>
<body>
<?= admin_header($staff, 'settings.php') ?>
<main class="admin-page">
  <?= admin_flash_html() ?>
  <div class="admin-toolbar">
    <div><h1 style="margin-bottom:4px;">Site settings</h1><p class="lede" style="margin-bottom:0;">Edit the public site's headline, tagline and contact details — changes apply immediately, no redeploy needed.</p></div>
  </div>

  <div class="card">
    <form method="post">
      <input type="hidden" name="csrf" value="<?= e($token) ?>">
      <div class="field"><label>Homepage headline</label>
        <div class="grid grid-2" style="gap:16px;">
          <input name="hero_headline_main" value="<?= e($current['hero_headline_main'] ?? '') ?>" placeholder="Plain part">
          <input name="hero_headline_accent" value="<?= e($current['hero_headline_accent'] ?? '') ?>" placeholder="Highlighted part">
        </div>
        <p style="font-size:.78rem;color:var(--ink-faint);margin-top:6px;">Shown as: "<?= e($current['hero_headline_main'] ?? '') ?> <b><?= e($current['hero_headline_accent'] ?? '') ?></b>"</p>
      </div>
      <div class="field"><label>Homepage subheading</label><textarea name="hero_subheadline"><?= e($current['hero_subheadline'] ?? '') ?></textarea></div>
      <div class="field"><label>Footer tagline</label><textarea name="site_tagline"><?= e($current['site_tagline'] ?? '') ?></textarea></div>
      <div class="grid grid-2" style="gap:16px;">
        <div class="field"><label>Contact email</label><input type="email" name="contact_email" value="<?= e($current['contact_email'] ?? '') ?>"></div>
        <div class="field"><label>Contact phone</label><input name="contact_phone" value="<?= e($current['contact_phone'] ?? '') ?>"></div>
      </div>
      <button class="btn btn-primary" type="submit">Save settings</button>
    </form>
  </div>
</main>
<?= admin_bottomnav('settings.php') ?>
</body>
</html>
