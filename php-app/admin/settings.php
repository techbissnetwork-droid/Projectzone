<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/icons.php';
require_once __DIR__ . '/../includes/admin_layout.php';
require_installed('../install/');

$staff = require_staff();
require_staff_access($staff, 'settings.php');
$pdo = db();

$TEXT_FIELDS = [
    'hero_headline_main', 'hero_headline_accent', 'hero_subheadline', 'site_tagline',
    'site_name', 'contact_email', 'contact_phone', 'whatsapp_number', 'seo_title', 'meta_description',
    'stat1_value', 'stat1_label', 'stat2_value', 'stat2_label',
    'stat3_value', 'stat3_label', 'stat4_value', 'stat4_label',
    'stat5_value', 'stat5_label',
];
$THEME_OPTIONS = ['auto' => 'Automatic (matches visitor device)', 'light' => 'Light', 'dark' => 'Dark'];
$LOGO_STYLE_OPTIONS = ['icon_text' => 'Icon + site name', 'text_only' => 'Site name only (no icon)'];
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

function branding_remove(string $settingKey, string $defaultValue, string $destBasename): void
{
    $uploadDir = __DIR__ . '/../assets/uploads';
    foreach (glob($uploadDir . '/' . $destBasename . '.*') ?: [] as $old) {
        @unlink($old);
    }
    $stmt = db()->prepare('INSERT INTO settings (id, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)');
    $stmt->execute([$settingKey, $defaultValue]);
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
        $logoStyle = array_key_exists($_POST['logo_style'] ?? '', $LOGO_STYLE_OPTIONS) ? $_POST['logo_style'] : 'icon_text';
        $stmt->execute(['logo_style', $logoStyle]);

        if (isset($_POST['remove_logo'])) {
            branding_remove('logo_path', '', 'logo');
        } elseif ($err = branding_upload('logo', ['png', 'jpg', 'jpeg', 'webp'], 'logo', 'logo_path')) {
            $errors[] = $err;
        }
        if (isset($_POST['remove_favicon'])) {
            branding_remove('favicon_path', 'assets/favicon.ico', 'favicon');
        } elseif ($err = branding_upload('favicon', ['png'], 'favicon', 'favicon_path')) {
            $errors[] = $err;
        }
        if (isset($_POST['remove_social_image'])) {
            branding_remove('social_image_path', 'assets/social-default.png', 'social');
        } elseif ($err = branding_upload('social_image', ['png', 'jpg', 'jpeg'], 'social', 'social_image_path')) {
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
<html lang="en"<?= palette_attr() ?>>
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

    <div class="settings-tabs">
      <input type="radio" name="stab" id="tab-general" class="tab-radio" checked>
      <input type="radio" name="stab" id="tab-stats" class="tab-radio">
      <input type="radio" name="stab" id="tab-seo" class="tab-radio">
      <input type="radio" name="stab" id="tab-branding" class="tab-radio">

      <div class="tab-labels">
        <label for="tab-general"><?= ico('edit') ?> General</label>
        <label for="tab-stats"><?= ico('chart') ?> Stats</label>
        <label for="tab-seo"><?= ico('star') ?> SEO &amp; Social</label>
        <label for="tab-branding"><?= ico('grid') ?> Branding</label>
      </div>

    <div class="tab-panel" id="panel-general">
    <div class="card admin-form-card">
      <div class="field"><label>Site name</label><input name="site_name" value="<?= e($current['site_name'] ?? 'TECHBISS') ?>">
        <p style="font-size:.78rem;color:var(--ink-faint);margin-top:6px;">Shown next to the logo in the header, footer and splash screen. Hidden automatically if you upload a custom logo in the Branding tab.</p>
      </div>
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
      <div class="field"><label>WhatsApp number <small style="font-weight:400;color:var(--ink-faint);">(with country code, e.g. 14155550148 — leave blank to hide the WhatsApp button)</small></label><input name="whatsapp_number" value="<?= e($current['whatsapp_number'] ?? '') ?>"></div>
    </div>
    </div>

    <div class="tab-panel" id="panel-stats">
    <div class="card admin-form-card">
      <p class="lede" style="margin-bottom:16px;">The numbers shown on the homepage and About page — edit these instead of the site's code.</p>
      <div class="grid grid-2" style="gap:16px;">
        <div class="field"><label>Stat 1 — value</label><input name="stat1_value" value="<?= e($current['stat1_value'] ?? '') ?>"></div>
        <div class="field"><label>Stat 1 — label</label><input name="stat1_label" value="<?= e($current['stat1_label'] ?? '') ?>"></div>
      </div>
      <div class="grid grid-2" style="gap:16px;">
        <div class="field"><label>Stat 2 — value</label><input name="stat2_value" value="<?= e($current['stat2_value'] ?? '') ?>"></div>
        <div class="field"><label>Stat 2 — label</label><input name="stat2_label" value="<?= e($current['stat2_label'] ?? '') ?>"></div>
      </div>
      <div class="grid grid-2" style="gap:16px;">
        <div class="field"><label>Stat 3 — value</label><input name="stat3_value" value="<?= e($current['stat3_value'] ?? '') ?>"></div>
        <div class="field"><label>Stat 3 — label</label><input name="stat3_label" value="<?= e($current['stat3_label'] ?? '') ?>"></div>
      </div>
      <div class="grid grid-2" style="gap:16px;">
        <div class="field"><label>Stat 4 — value <small style="font-weight:400;color:var(--ink-faint);">(homepage only)</small></label><input name="stat4_value" value="<?= e($current['stat4_value'] ?? '') ?>"></div>
        <div class="field"><label>Stat 4 — label</label><input name="stat4_label" value="<?= e($current['stat4_label'] ?? '') ?>"></div>
      </div>
      <div class="grid grid-2" style="gap:16px;">
        <div class="field"><label>Stat 5 — value <small style="font-weight:400;color:var(--ink-faint);">(About page only)</small></label><input name="stat5_value" value="<?= e($current['stat5_value'] ?? '') ?>"></div>
        <div class="field"><label>Stat 5 — label</label><input name="stat5_label" value="<?= e($current['stat5_label'] ?? '') ?>"></div>
      </div>
    </div>
    </div>

    <div class="tab-panel" id="panel-seo">
    <div class="card admin-form-card">
      <div class="field"><label>Page title (browser tab, search results)</label><input name="seo_title" value="<?= e($current['seo_title'] ?? '') ?>"></div>
      <div class="field"><label>Meta description (search results snippet)</label><textarea name="meta_description"><?= e($current['meta_description'] ?? '') ?></textarea></div>
      <div class="field"><label>Social share image</label>
        <div class="flex items-center gap-12" style="flex-wrap:wrap;">
          <img class="settings-preview" src="../<?= e($socialPath) ?>" alt="" style="width:100px;height:56px;">
          <input type="file" name="social_image" accept="image/png,image/jpeg" style="min-width:0;max-width:100%;">
        </div>
        <?php if (($current['social_image_path'] ?? 'assets/social-default.png') !== 'assets/social-default.png'): ?>
        <label class="flex items-center gap-8" style="font-size:.8rem;margin-top:8px;"><input type="checkbox" name="remove_social_image"> Remove custom social image (revert to default)</label>
        <?php endif; ?>
        <p style="font-size:.78rem;color:var(--ink-faint);margin-top:6px;">Shown when the site is shared on social media/messaging apps. Recommended size: 1200×630px.</p>
      </div>
    </div>
    </div>

    <div class="tab-panel" id="panel-branding">
    <div class="card admin-form-card">
      <div class="grid grid-2" style="gap:16px;">
        <div class="field"><label>Logo</label>
          <div class="flex items-center gap-12" style="flex-wrap:wrap;">
            <img class="settings-preview" src="<?= $logoPath !== '' ? '../' . e($logoPath) : '../assets/brand/logo-512.png' ?>" alt="">
            <input type="file" name="logo" accept="image/png,image/jpeg,image/webp" style="min-width:0;max-width:100%;">
          </div>
          <?php if ($logoPath !== ''): ?>
          <label class="flex items-center gap-8" style="font-size:.8rem;margin-top:8px;"><input type="checkbox" name="remove_logo"> Remove custom logo (revert to the default TECHBISS mark)</label>
          <?php endif; ?>
          <p style="font-size:.78rem;color:var(--ink-faint);margin-top:6px;">Square image, transparent PNG recommended. Uploading one here replaces the icon + name below entirely.</p>
        </div>
        <div class="field"><label>Logo style <small style="font-weight:400;color:var(--ink-faint);">(only applies while no custom logo is uploaded)</small></label>
          <select name="logo_style">
            <?php foreach ($LOGO_STYLE_OPTIONS as $val => $label): ?>
            <option value="<?= e($val) ?>" <?= ($current['logo_style'] ?? 'icon_text') === $val ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field"><label>Favicon</label>
          <div class="flex items-center gap-12" style="flex-wrap:wrap;">
            <img class="settings-preview" src="../<?= e($faviconPath) ?>" alt="">
            <input type="file" name="favicon" accept="image/png" style="min-width:0;max-width:100%;">
          </div>
          <?php if (($current['favicon_path'] ?? 'assets/favicon.ico') !== 'assets/favicon.ico'): ?>
          <label class="flex items-center gap-8" style="font-size:.8rem;margin-top:8px;"><input type="checkbox" name="remove_favicon"> Remove custom favicon (revert to default)</label>
          <?php endif; ?>
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
    </div>

    </div>

    <button class="btn btn-primary" type="submit">Save settings</button>
  </form>
</main>
<?= admin_bottomnav($staff, 'settings.php') ?>
</body>
</html>
