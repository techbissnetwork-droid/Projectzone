<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/icons.php';
require_once __DIR__ . '/../includes/admin_layout.php';
require_installed('../install/');

$staff = require_staff();
$pdo = db();

$TEXT_FIELDS = [
    'hero_headline_main', 'hero_headline_accent', 'hero_subheadline', 'site_tagline',
    'contact_email', 'contact_phone', 'seo_title', 'meta_description',
];
$THEME_OPTIONS = ['auto' => 'Automatic (matches visitor device)', 'light' => 'Light', 'dark' => 'Dark'];
$PALETTE_OPTIONS = [
    '' => 'Bloom (coral & amber — default)',
    'fresh' => 'Fresh (teal & lime)',
    'dusk' => 'Dusk (violet & sky)',
    'ember' => 'Ember (rose & gold)',
    'sunrise' => 'Sunrise (orange & pink)',
    'lagoon' => 'Lagoon (cyan & blue)',
    'orchid' => 'Orchid (purple & indigo)',
    'citrus' => 'Citrus (green & emerald)',
    'slate' => 'Slate Bloom (indigo & mauve)',
    'midnight' => 'Midnight Bloom (indigo & magenta)',
    'noir' => 'Noir (gold on near-black — best in dark mode)',
];

function branding_upload(string $field, array $allowedExts, string $destBasename, string $settingKey): ?string
{
    if (empty($_FILES[$field]['name']) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        return 'Upload failed — please try that file again.';
    }
    if ($_FILES[$field]['size'] > 2 * 1024 * 1024) {
        return 'That file is over 2MB — please use a smaller image.';
    }
    $ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExts, true)) {
        return 'Unsupported file type — allowed: ' . implode(', ', $allowedExts) . '.';
    }
    if (!@getimagesize($_FILES[$field]['tmp_name'])) {
        return 'That file does not look like a valid image.';
    }
    $uploadDir = __DIR__ . '/../assets/uploads';
    if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0755, true)) {
        return 'Could not create assets/uploads/ — check folder permissions.';
    }
    $filename = $destBasename . '.' . $ext;
    if (!move_uploaded_file($_FILES[$field]['tmp_name'], $uploadDir . '/' . $filename)) {
        return 'Could not save the uploaded file — check that assets/uploads/ is writable.';
    }
    foreach (glob($uploadDir . '/' . $destBasename . '.*') ?: [] as $old) {
        if (basename($old) !== $filename) {
            @unlink($old);
        }
    }
    $stmt = db()->prepare('INSERT INTO settings (id, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)');
    $stmt->execute([$settingKey, 'assets/uploads/' . $filename]);
    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check((string)($_POST['csrf'] ?? ''))) {
        flash('Your session expired — please try again.', 'error');
    } else {
        $errors = [];

        $stmt = $pdo->prepare('INSERT INTO settings (id, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)');
        foreach ($TEXT_FIELDS as $key) {
            $stmt->execute([$key, trim((string)($_POST[$key] ?? ''))]);
        }
        $theme = array_key_exists($_POST['default_theme'] ?? '', $THEME_OPTIONS) ? $_POST['default_theme'] : 'auto';
        $stmt->execute(['default_theme', $theme]);
        $palette = array_key_exists($_POST['color_palette'] ?? '', $PALETTE_OPTIONS) ? $_POST['color_palette'] : '';
        $stmt->execute(['color_palette', $palette]);

        if ($err = branding_upload('logo', ['png', 'jpg', 'jpeg', 'webp'], 'logo', 'logo_path')) {
            $errors[] = $err;
        }
        if ($err = branding_upload('favicon', ['png'], 'favicon', 'favicon_path')) {
            $errors[] = $err;
        }
        if ($err = branding_upload('social_image', ['png', 'jpg', 'jpeg'], 'social', 'social_image_path')) {
            $errors[] = $err;
        }

        if ($errors) {
            flash(implode(' ', $errors), 'error');
        } else {
            flash('Settings updated — live on the public site now.');
        }
    }
    header('Location: settings.php');
    exit;
}

// Bypass the request-lifetime cache so the form shows what was just saved.
$current = $pdo->query('SELECT id, value FROM settings')->fetchAll(PDO::FETCH_KEY_PAIR);
$token = csrf_token();
$logoPath = $current['logo_path'] ?? '';
$faviconPath = $current['favicon_path'] ?? 'assets/favicon.ico';
$socialPath = $current['social_image_path'] ?? 'assets/social-default.png';
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
<style>.settings-preview{ width:56px;height:56px;border-radius:14px;object-fit:contain;background:var(--surface-2);border:1px solid var(--border-soft);padding:6px; }</style>
</head>
<body>
<?= admin_header($staff, 'settings.php') ?>
<main class="admin-page">
  <?= admin_flash_html() ?>
  <div class="admin-toolbar">
    <div><h1 style="margin-bottom:4px;">Site settings</h1><p class="lede" style="margin-bottom:0;">Edit the public site's copy, branding and SEO — changes apply immediately, no redeploy needed.</p></div>
  </div>

  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?= e($token) ?>">

    <div class="card admin-form-card">
      <div class="card-head"><?= blob_icon('edit', 'sm', true) ?><h3>Homepage &amp; footer</h3></div>
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
    </div>

    <div class="card admin-form-card">
      <div class="card-head"><?= blob_icon('star', 'sm', true) ?><h3>SEO &amp; social sharing</h3></div>
      <div class="field"><label>Page title (browser tab, search results)</label><input name="seo_title" value="<?= e($current['seo_title'] ?? '') ?>"></div>
      <div class="field"><label>Meta description (search results snippet)</label><textarea name="meta_description"><?= e($current['meta_description'] ?? '') ?></textarea></div>
      <div class="field"><label>Social share image</label>
        <div class="flex items-center gap-12">
          <img class="settings-preview" src="../<?= e($socialPath) ?>" alt="" style="width:100px;height:56px;">
          <input type="file" name="social_image" accept="image/png,image/jpeg">
        </div>
        <p style="font-size:.78rem;color:var(--ink-faint);margin-top:6px;">Shown when the site is shared on social media/messaging apps. Recommended size: 1200×630px.</p>
      </div>
    </div>

    <div class="card admin-form-card">
      <div class="card-head"><?= blob_icon('grid', 'sm', true) ?><h3>Branding &amp; appearance</h3></div>
      <div class="grid grid-2" style="gap:16px;">
        <div class="field"><label>Logo</label>
          <div class="flex items-center gap-12">
            <img class="settings-preview" src="<?= $logoPath !== '' ? '../' . e($logoPath) : '../assets/brand/logo-512.png' ?>" alt="">
            <input type="file" name="logo" accept="image/png,image/jpeg,image/webp">
          </div>
          <p style="font-size:.78rem;color:var(--ink-faint);margin-top:6px;">Square image, transparent PNG recommended.</p>
        </div>
        <div class="field"><label>Favicon</label>
          <div class="flex items-center gap-12">
            <img class="settings-preview" src="../<?= e($faviconPath) ?>" alt="">
            <input type="file" name="favicon" accept="image/png">
          </div>
          <p style="font-size:.78rem;color:var(--ink-faint);margin-top:6px;">PNG, ideally 512×512px (square).</p>
        </div>
      </div>
      <div class="grid grid-2" style="gap:16px;">
        <div class="field"><label>Brand color theme</label>
          <select name="color_palette">
            <?php foreach ($PALETTE_OPTIONS as $val => $label): ?>
            <option value="<?= e($val) ?>" <?= ($current['color_palette'] ?? '') === $val ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
          <p style="font-size:.78rem;color:var(--ink-faint);margin-top:6px;">Applies site-wide for everyone — one consistent brand look, not a per-visitor toggle.</p>
        </div>
        <div class="field"><label>Default theme for new visitors</label>
          <select name="default_theme">
            <?php foreach ($THEME_OPTIONS as $val => $label): ?>
            <option value="<?= e($val) ?>" <?= ($current['default_theme'] ?? 'auto') === $val ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
          <p style="font-size:.78rem;color:var(--ink-faint);margin-top:6px;">Only applies to first-time visitors — anyone who has already toggled light/dark themselves keeps their own choice.</p>
        </div>
      </div>
    </div>

    <button class="btn btn-primary" type="submit">Save settings</button>
  </form>
</main>
<?= admin_bottomnav('settings.php') ?>
</body>
</html>
