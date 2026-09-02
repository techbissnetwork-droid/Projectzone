<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/guard.php';
require_admin();

/** Every editable setting: key => [group, label, type, hint] */
$FIELDS = [
    'site_name'        => ['brand', 'Site name', 'text', 'Shown in the header, the tab title and emails.'],
    'site_tagline'     => ['brand', 'Tagline', 'text', ''],
    'site_description' => ['brand', 'Meta description', 'textarea', 'Used by search engines and link previews. Around 150–160 characters.'],
    'accent_color'     => ['brand', 'Accent colour', 'color', 'Drives links, highlights and the ecosystem graphic.'],
    'accent_warm'      => ['brand', 'Secondary accent', 'color', 'Used sparingly for warm highlights.'],

    'hero_eyebrow'     => ['hero', 'Eyebrow', 'text', 'The small line above the headline.'],
    'hero_title_a'     => ['hero', 'Headline line 1', 'text', ''],
    'hero_title_b'     => ['hero', 'Headline line 2', 'text', ''],
    'hero_title_c'     => ['hero', 'Headline line 3', 'text', 'This line is shown in the accent gradient.'],
    'hero_lede'        => ['hero', 'Supporting paragraph', 'textarea', ''],
    'hero_cta_primary' => ['hero', 'Primary button', 'text', ''],
    'hero_cta_secondary' => ['hero', 'Secondary button', 'text', ''],
    'quote'            => ['hero', 'Statement quote', 'textarea', 'The large pull quote near the bottom of the home page.'],

    'contact_email'    => ['contact', 'Email', 'text', ''],
    'contact_phone'    => ['contact', 'Phone', 'text', ''],
    'contact_address'  => ['contact', 'Address', 'textarea', ''],
    'contact_hours'    => ['contact', 'Office hours', 'text', ''],
    'social_linkedin'  => ['contact', 'LinkedIn URL', 'text', ''],
    'social_facebook'  => ['contact', 'Facebook URL', 'text', ''],
    'social_x'         => ['contact', 'X URL', 'text', ''],
    'social_github'    => ['contact', 'GitHub URL', 'text', ''],

    'currency'         => ['commerce', 'Currency code', 'text', 'For example NPR or USD.'],
    'currency_symbol'  => ['commerce', 'Currency symbol', 'text', 'For example Rs or $.'],
    'payment_instructions' => ['commerce', 'Payment instructions', 'textarea', 'Shown to a buyer after they place an order.'],
    'show_marketplace' => ['commerce', 'Show the marketplace', 'bool', 'Hides the marketplace from the site navigation when off.'],
    'show_portfolio'   => ['commerce', 'Show the portfolio', 'bool', 'Hides the portfolio page when off.'],
    'expiry_warn_days' => ['commerce', 'Renewal warning window', 'number', 'How many days ahead a domain, hosting or SSL renewal is flagged.'],
    'footer_note'      => ['commerce', 'Footer statement', 'textarea', ''],
];
$GROUPS = ['brand' => 'Brand', 'hero' => 'Home page', 'contact' => 'Contact &amp; social', 'commerce' => 'Commerce &amp; site'];

if (post()) {
    Csrf::check();
    $save = [];
    foreach ($FIELDS as $key => [$group, $labelText, $type]) {
        if ($type === 'bool') {
            $save[$key] = !empty($_POST[$key]) ? '1' : '0';
        } elseif ($type === 'number') {
            $save[$key] = (string)max(1, min(365, (int)($_POST[$key] ?? 45)));
        } else {
            $save[$key] = trim((string)($_POST[$key] ?? ''));
        }
    }
    foreach (['accent_color', 'accent_warm'] as $c) {
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $save[$c])) {
            $save[$c] = Settings::defaults()[$c];
        }
    }
    if ($save['site_name'] === '') {
        $save['site_name'] = 'TECHBISS';
    }
    Settings::setMany($save);
    log_activity('settings.update');
    Flash::ok('Settings saved.');
    redirect('admin/settings.php');
}

$PAGE_TITLE = 'Settings';
$AREA = 'admin';
require __DIR__ . '/../partials/app_header.php';
?>
<form method="post" class="form">
  <?= Csrf::field() ?>
  <?php foreach ($GROUPS as $gk => $gLabel): ?>
    <div class="fieldset">
      <p class="legend"><?= $gLabel ?></p>
      <?php foreach ($FIELDS as $key => [$group, $labelText, $type, $hint]):
        if ($group !== $gk) continue;
        $val = Settings::get($key, '');
        ?>
        <?php if ($type === 'bool'): ?>
          <label class="field check"><input type="checkbox" name="<?= e($key) ?>" value="1"<?= Settings::bool($key) ? ' checked' : '' ?>>
            <span><?= e($labelText) ?><?php if ($hint): ?> <small class="muted">— <?= e($hint) ?></small><?php endif; ?></span></label>
        <?php elseif ($type === 'textarea'): ?>
          <label class="field"><span><?= e($labelText) ?><?php if ($hint): ?><small><?= e($hint) ?></small><?php endif; ?></span>
            <textarea name="<?= e($key) ?>" rows="3"><?= e($val) ?></textarea></label>
        <?php elseif ($type === 'color'): ?>
          <label class="field" style="max-width:260px"><span><?= e($labelText) ?><?php if ($hint): ?><small><?= e($hint) ?></small><?php endif; ?></span>
            <input type="color" name="<?= e($key) ?>" value="<?= e($val ?: '#8FB0FF') ?>" style="height:42px;padding:4px"></label>
        <?php elseif ($type === 'number'): ?>
          <label class="field" style="max-width:260px"><span><?= e($labelText) ?><?php if ($hint): ?><small><?= e($hint) ?></small><?php endif; ?></span>
            <input type="number" name="<?= e($key) ?>" min="1" max="365" value="<?= e($val) ?>"></label>
        <?php else: ?>
          <label class="field"><span><?= e($labelText) ?><?php if ($hint): ?><small><?= e($hint) ?></small><?php endif; ?></span>
            <input name="<?= e($key) ?>" value="<?= e($val) ?>"></label>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>
  <div class="formfoot">
    <button class="btn" type="submit">Save settings</button>
    <a class="btn ghost" href="<?= e(url()) ?>" target="_blank" rel="noopener">Preview the site ↗</a>
  </div>
</form>
<?php require __DIR__ . '/../partials/app_footer.php'; ?>
