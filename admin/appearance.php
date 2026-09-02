<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/guard.php';
require_admin();

$FONTS = [
    'Inter Tight' => 'Inter Tight — tight, editorial (default display)',
    'Inter'       => 'Inter — clean and neutral',
    'Manrope'     => 'Manrope — soft geometric',
    'Sora'        => 'Sora — technical',
    'Space Grotesk' => 'Space Grotesk — distinctive',
    'DM Sans'     => 'DM Sans — friendly',
    'Outfit'      => 'Outfit — wide and modern',
    'Plus Jakarta Sans' => 'Plus Jakarta Sans — rounded',
    'Figtree'     => 'Figtree — humanist',
    'system'      => 'System font — no download, fastest',
];

if (post()) {
    Csrf::check();
    $errors = [];

    foreach (['logo_image' => 'site', 'favicon_image' => 'site', 'og_image' => 'site'] as $field => $folder) {
        if (!empty($_POST['remove_' . $field])) {
            Upload::remove(Settings::get($field, ''));
            Settings::set($field, '');
            continue;
        }
        [$path, $err] = Upload::image($field, $folder);
        if ($err) {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . ': ' . $err;
        } elseif ($path) {
            Upload::remove(Settings::get($field, ''));
            Settings::set($field, $path);
        }
    }

    $colors = ['accent_color', 'accent_warm', 'bg_base', 'text_primary', 'text_muted'];
    $save = [];
    foreach ($colors as $c) {
        $v = trim((string)($_POST[$c] ?? ''));
        $save[$c] = preg_match('/^#[0-9a-fA-F]{6}$/', $v) ? $v : Settings::defaults()[$c];
    }
    foreach (['font_display', 'font_body'] as $f) {
        $v = (string)($_POST[$f] ?? '');
        $save[$f] = isset($FONTS[$v]) ? $v : Settings::defaults()[$f];
    }
    $save['radius_scale']    = in_array((string)($_POST['radius_scale'] ?? ''), ['sharp','normal','round'], true) ? (string)$_POST['radius_scale'] : 'normal';
    $save['custom_css']      = trim((string)($_POST['custom_css'] ?? ''));
    $save['custom_head']     = trim((string)($_POST['custom_head'] ?? ''));
    $save['custom_body_end'] = trim((string)($_POST['custom_body_end'] ?? ''));

    Settings::setMany($save);
    log_activity('appearance.update');
    foreach ($errors as $er) {
        Flash::err($er);
    }
    if (!$errors) {
        Flash::ok('Appearance saved.');
    }
    redirect('admin/appearance.php');
}

$PAGE_TITLE = 'Appearance';
$AREA = 'admin';
$PAGE_ACTIONS = '<a class="btn ghost sm" href="' . e(url()) . '" target="_blank" rel="noopener">Preview site ↗</a>';
require __DIR__ . '/../partials/app_header.php';
?>
<form method="post" class="form" enctype="multipart/form-data">
  <?= Csrf::field() ?>
  <div class="split">
    <div class="stack">
      <div class="fieldset">
        <p class="legend">Logo &amp; icons</p>
        <?php foreach ([
            ['logo_image', 'Logo', 'Replaces the hexagon mark in the header and footer. A wide PNG or SVG works best.'],
            ['favicon_image', 'Favicon', 'The small icon in the browser tab. 32×32 or an SVG.'],
            ['og_image', 'Social share image', 'Shown when a link to your site is pasted into a chat. 1200×630.'],
        ] as [$key, $labelText, $hint]):
          $cur = Settings::get($key, ''); ?>
          <div class="field">
            <span><?= e($labelText) ?> <small><?= e($hint) ?></small></span>
            <?php if ($cur): ?>
              <img class="thumb" src="<?= e(url($cur)) ?>" alt="" style="background:rgba(255,255,255,.05);object-fit:contain;padding:8px">
              <label class="field check"><input type="checkbox" name="remove_<?= e($key) ?>" value="1"><span>Remove</span></label>
            <?php endif; ?>
            <input type="file" name="<?= e($key) ?>" accept="image/*">
          </div>
        <?php endforeach; ?>
      </div>

      <div class="fieldset">
        <p class="legend">Typography</p>
        <div class="row two">
          <label class="field"><span>Headline font</span>
            <select name="font_display">
              <?php foreach ($FONTS as $k => $v): ?>
                <option value="<?= e($k) ?>"<?= Settings::get('font_display') === $k ? ' selected' : '' ?>><?= e($v) ?></option>
              <?php endforeach; ?>
            </select></label>
          <label class="field"><span>Body font</span>
            <select name="font_body">
              <?php foreach ($FONTS as $k => $v): ?>
                <option value="<?= e($k) ?>"<?= Settings::get('font_body') === $k ? ' selected' : '' ?>><?= e($v) ?></option>
              <?php endforeach; ?>
            </select></label>
        </div>
        <label class="field"><span>Corner style</span>
          <select name="radius_scale">
            <option value="sharp"<?= Settings::get('radius_scale') === 'sharp' ? ' selected' : '' ?>>Sharp — small radii</option>
            <option value="normal"<?= Settings::get('radius_scale') === 'normal' ? ' selected' : '' ?>>Normal</option>
            <option value="round"<?= Settings::get('radius_scale') === 'round' ? ' selected' : '' ?>>Round — generous radii</option>
          </select></label>
      </div>
    </div>

    <div class="stack">
      <div class="fieldset">
        <p class="legend">Colours</p>
        <?php foreach ([
            ['accent_color', 'Accent', 'Links, highlights, the ecosystem graphic.'],
            ['accent_warm', 'Secondary accent', 'Used sparingly for warm highlights.'],
            ['bg_base', 'Page background', 'The base colour behind everything.'],
            ['text_primary', 'Primary text', ''],
            ['text_muted', 'Muted text', ''],
        ] as [$key, $labelText, $hint]): ?>
          <label class="field" style="grid-template-columns:1fr auto;align-items:center">
            <span><?= e($labelText) ?><?php if ($hint): ?><small><?= e($hint) ?></small><?php endif; ?></span>
            <input type="color" name="<?= e($key) ?>" value="<?= e(Settings::get($key)) ?>"
                   style="width:64px;height:40px;padding:3px;grid-row:1;grid-column:2">
          </label>
        <?php endforeach; ?>
        <p class="hint">Contrast matters: keep the background dark and the primary text light, or invert both together.</p>
      </div>

      <div class="fieldset">
        <p class="legend">Custom code</p>
        <label class="field"><span>Custom CSS <small>added last, so it overrides everything</small></span>
          <textarea name="custom_css" rows="7" class="mono" placeholder=".hero__title { letter-spacing: -.05em; }"><?= e(Settings::get('custom_css')) ?></textarea></label>
        <label class="field"><span>Head code <small>verification tags, fonts, analytics</small></span>
          <textarea name="custom_head" rows="4" class="mono"><?= e(Settings::get('custom_head')) ?></textarea></label>
        <label class="field"><span>Before &lt;/body&gt; <small>chat widgets, trackers</small></span>
          <textarea name="custom_body_end" rows="4" class="mono"><?= e(Settings::get('custom_body_end')) ?></textarea></label>
        <p class="hint">These are inserted verbatim on every public page. Only paste code you trust — a mistake here can break the site for visitors.</p>
      </div>
    </div>
  </div>
  <div class="formfoot"><button class="btn" type="submit">Save appearance</button></div>
</form>
<?php require __DIR__ . '/../partials/app_footer.php'; ?>
