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
$LOGO_STYLE_OPTIONS = ['icon_text' => 'Icon + site name', 'icon_only' => 'Icon only (no site name)', 'text_only' => 'Site name only (no icon)'];
$LOGO_MOTION_OPTIONS = ['on' => 'On (gentle idle tilt)', 'off' => 'Off (static)'];
$ON_OFF_OPTIONS = ['on' => 'On', 'off' => 'Off'];
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
    $action = $_POST['action'] ?? 'save';
    if (!csrf_check((string)($_POST['csrf'] ?? ''))) {
        flash('Your session expired — please try again.', 'error');
    } elseif ($action === 'test_email') {
        $to = trim((string)($_POST['test_email_to'] ?? ''));
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            flash('Enter a valid email address to send the test to.', 'error');
        } elseif (send_mail($to, 'TECHBISS SMTP test', "This is a test email from your TECHBISS admin panel.\n\nIf you received this, your SMTP settings are working correctly.")) {
            flash('Test email sent to ' . $to . ' — check its inbox (and spam folder).');
        } else {
            flash('Could not send the test email — double check your SMTP settings, or check the server error log for details.', 'error');
        }
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
        $logoMotion = array_key_exists($_POST['logo_animation'] ?? '', $LOGO_MOTION_OPTIONS) ? $_POST['logo_animation'] : 'on';
        $stmt->execute(['logo_animation', $logoMotion]);
        $stmt->execute(['splash_enabled', array_key_exists($_POST['splash_enabled'] ?? '', $ON_OFF_OPTIONS) ? $_POST['splash_enabled'] : 'on']);
        $stmt->execute(['page_transition_enabled', array_key_exists($_POST['page_transition_enabled'] ?? '', $ON_OFF_OPTIONS) ? $_POST['page_transition_enabled'] : 'on']);
        $uiZoom = max(50, min(150, (int)($_POST['ui_zoom'] ?? 100)));
        $stmt->execute(['ui_zoom', (string)$uiZoom]);

        $stmt->execute(['smtp_host', trim((string)($_POST['smtp_host'] ?? ''))]);
        $stmt->execute(['smtp_port', (string)max(1, (int)($_POST['smtp_port'] ?? 587))]);
        $stmt->execute(['smtp_user', trim((string)($_POST['smtp_user'] ?? ''))]);
        $stmt->execute(['smtp_pass', (string)($_POST['smtp_pass'] ?? '')]);
        $smtpEncryption = in_array($_POST['smtp_encryption'] ?? '', ['tls', 'ssl', 'none'], true) ? $_POST['smtp_encryption'] : 'tls';
        $stmt->execute(['smtp_encryption', $smtpEncryption]);
        $stmt->execute(['smtp_from_email', trim((string)($_POST['smtp_from_email'] ?? ''))]);
        $stmt->execute(['smtp_from_name', trim((string)($_POST['smtp_from_name'] ?? ''))]);

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
<html lang="en"<?= palette_attr() . logo_motion_attr() . ui_zoom_attr() ?>>
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
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="csrf" value="<?= e($token) ?>">

    <div class="settings-tabs">
      <input type="radio" name="stab" id="tab-general" class="tab-radio" checked>
      <input type="radio" name="stab" id="tab-stats" class="tab-radio">
      <input type="radio" name="stab" id="tab-seo" class="tab-radio">
      <input type="radio" name="stab" id="tab-branding" class="tab-radio">
      <input type="radio" name="stab" id="tab-email" class="tab-radio">

      <div class="tab-labels">
        <label for="tab-general"><?= ico('edit') ?> General</label>
        <label for="tab-stats"><?= ico('chart') ?> Stats</label>
        <label for="tab-seo"><?= ico('star') ?> SEO &amp; Social</label>
        <label for="tab-branding"><?= ico('grid') ?> Branding</label>
        <label for="tab-email"><?= ico('mail') ?> Email</label>
      </div>

    <div class="tab-panel" id="panel-general">
    <div class="card admin-form-card" style="border:1.5px solid var(--accent-1);">
      <div class="card-head"><?= blob_icon('chart', 'sm', true) ?><h3>Site zoom</h3></div>
      <p class="lede" style="margin-bottom:14px;">Shrinks or enlarges the whole site — public pages and this admin panel — for every visitor. Useful for fitting more on small screens.</p>
      <div class="flex gap-12" style="align-items:center;">
        <input type="range" min="50" max="150" step="5" name="ui_zoom" id="uiZoomRange" value="<?= (int)($current['ui_zoom'] ?? 100) ?>" style="flex:1;">
        <b id="uiZoomLabel" style="min-width:48px;text-align:right;font-size:1.1rem;"><?= (int)($current['ui_zoom'] ?? 100) ?>%</b>
      </div>
    </div>
    <script>
    (function(){
      var r = document.getElementById('uiZoomRange'), l = document.getElementById('uiZoomLabel');
      r.addEventListener('input', function(){ l.textContent = r.value + '%'; });
    })();
    </script>
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
        <div class="field"><label>Logo animation</label>
          <select name="logo_animation">
            <?php foreach ($LOGO_MOTION_OPTIONS as $val => $label): ?>
            <option value="<?= e($val) ?>" <?= ($current['logo_animation'] ?? 'on') === $val ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field"><label>Intro splash screen <small style="font-weight:400;color:var(--ink-faint);">(the one-time animation on first page load)</small></label>
          <select name="splash_enabled">
            <?php foreach ($ON_OFF_OPTIONS as $val => $label): ?>
            <option value="<?= e($val) ?>" <?= ($current['splash_enabled'] ?? 'on') === $val ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field"><label>Page transition <small style="font-weight:400;color:var(--ink-faint);">(the wipe animation between pages)</small></label>
          <select name="page_transition_enabled">
            <?php foreach ($ON_OFF_OPTIONS as $val => $label): ?>
            <option value="<?= e($val) ?>" <?= ($current['page_transition_enabled'] ?? 'on') === $val ? 'selected' : '' ?>><?= e($label) ?></option>
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

    <div class="tab-panel" id="panel-email">
    <div class="card admin-form-card">
      <p style="font-size:.85rem;color:var(--ink-faint);margin-bottom:16px;">Used to send customer login codes, magic links and email-change verifications. Leave the host blank to fall back to the server's built-in mail sending (works on many hosts, but for reliable delivery a real SMTP provider — e.g. your email host, SendGrid, Mailgun — is recommended.)</p>
      <div class="grid grid-2" style="gap:16px;">
        <div class="field"><label>SMTP host</label><input name="smtp_host" value="<?= e($current['smtp_host'] ?? '') ?>" placeholder="smtp.yourprovider.com"></div>
        <div class="field"><label>Port</label><input type="number" name="smtp_port" value="<?= e($current['smtp_port'] ?? '587') ?>"></div>
      </div>
      <div class="grid grid-2" style="gap:16px;">
        <div class="field"><label>Username</label><input name="smtp_user" value="<?= e($current['smtp_user'] ?? '') ?>" autocomplete="off"></div>
        <div class="field"><label>Password</label><input type="password" name="smtp_pass" value="<?= e($current['smtp_pass'] ?? '') ?>" autocomplete="new-password"></div>
      </div>
      <div class="grid grid-2" style="gap:16px;">
        <div class="field"><label>Encryption</label>
          <select name="smtp_encryption">
            <?php foreach (['tls' => 'STARTTLS (port 587, most common)', 'ssl' => 'SSL/TLS (port 465)', 'none' => 'None'] as $val => $label): ?>
            <option value="<?= e($val) ?>" <?= ($current['smtp_encryption'] ?? 'tls') === $val ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field"><label>From name</label><input name="smtp_from_name" value="<?= e($current['smtp_from_name'] ?? '') ?>" placeholder="<?= e($current['site_name'] ?? 'TECHBISS') ?>"></div>
      </div>
      <div class="field"><label>From email</label><input type="email" name="smtp_from_email" value="<?= e($current['smtp_from_email'] ?? '') ?>" placeholder="<?= e($current['contact_email'] ?? 'hello@techbiss.com') ?>"></div>
    </div>
    </div>

    </div>

    <button class="btn btn-primary" type="submit">Save settings</button>
  </form>

  <div class="card admin-form-card" id="testEmailCard" hidden style="margin-top:22px;">
    <div class="card-head"><?= blob_icon('mail', 'sm', true) ?><h3>Send a test email</h3></div>
    <p class="lede" style="margin-bottom:14px;">Save your SMTP settings above first, then send a test to confirm they work.</p>
    <form method="post" class="flex gap-12" style="flex-wrap:wrap;align-items:flex-end;">
      <input type="hidden" name="action" value="test_email">
      <input type="hidden" name="csrf" value="<?= e($token) ?>">
      <div class="field" style="margin-bottom:0;flex:1;min-width:220px;"><label>Send to</label><input type="email" name="test_email_to" required placeholder="you@example.com"></div>
      <button class="btn btn-ghost" type="submit">Send test</button>
    </form>
  </div>
  <script>
  (function(){
    var tab = document.getElementById('tab-email'), card = document.getElementById('testEmailCard');
    function sync(){ card.hidden = !tab.checked; }
    tab.addEventListener('change', sync);
    document.querySelectorAll('input[name="stab"]').forEach(function(r){ r.addEventListener('change', sync); });
    sync();
  })();
  </script>
</main>
<?= admin_bottomnav($staff, 'settings.php') ?>
</body>
</html>
