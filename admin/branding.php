<?php
/**
 * Logo, favicon, sharing image and the words Google shows.
 * Everything here is a setting, so nothing needs a developer.
 */
require_once __DIR__ . '/../app/bootstrap.php';
require_installed();
require_staff();
require_once __DIR__ . '/_layout.php';

/* Field names cannot contain a dot: PHP rewrites "." to "_" in $_POST. */
function bfield(string $key): string
{
    return 'b_' . str_replace('.', '__', $key);
}

$groups = [
    'Logo and icons' => [
        ['brand.logo',        'Logo',            'image',
            'Shown in the header and footer instead of the word mark. A wide PNG or SVG with a '
            . 'transparent background works best. Leave empty to keep the text logo.'],
        ['brand.logo_height', 'Logo height',     'text',
            'How tall the logo sits in the header, in pixels. 24 to 40 suits most logos.'],
        ['brand.favicon',     'Favicon',         'image',
            'The tiny icon in the browser tab. A square image, at least 180 by 180.'],
        ['brand.social_image','Sharing image',   'image',
            'What people see when your link is pasted into WhatsApp, Facebook or X. '
            . '1200 by 630 is the size everything expects.'],
    ],
    'What Google shows' => [
        ['seo.title_suffix',        'Added after every page title', 'text',
            'For example "— TECHBISS". Leave empty if your titles already say it.'],
        ['seo.default_description', 'Fallback description',         'textarea',
            'Used on any page without its own. Aim for about 150 characters — Google cuts it off '
            . 'after that.'],
        ['seo.twitter',             'X / Twitter handle',           'text', 'Without the @.'],
        ['seo.google_verification', 'Search Console code',          'text',
            'Google gives you this when you add the site to Search Console. Paste the code only, '
            . 'not the whole tag.'],
        ['seo.noindex',             'Hide from Google',             'text',
            'Set to 1 while you are still building. Set it back to 0 before you go live, or you '
            . 'will never appear in search.'],
    ],
    'Your address' => [
        ['biz.street',   'Street',           'text', ''],
        ['biz.city',     'Town or city',     'text', ''],
        ['biz.region',   'County or state',  'text', ''],
        ['biz.postcode', 'Postcode or ZIP',  'text', ''],
        ['biz.country',  'Country',          'text', ''],
    ],
];

if (is_post()) {
    csrf_check();
    $saved = 0;

    foreach ($groups as $fields) {
        foreach ($fields as [$key, $label, $type]) {
            $field = bfield($key);

            if ($type === 'image') {
                $err = null;
                $new = handle_upload($field, $err);
                if ($err) {
                    flash(esc($label) . ': ' . esc($err), 'bad');
                } elseif ($new) {
                    delete_upload(setting($key, ''));
                    setting_set($key, $new);
                    $saved++;
                } elseif (isset($_POST['remove_' . $field])) {
                    delete_upload(setting($key, ''));
                    setting_set($key, '');
                    $saved++;
                }
                continue;
            }

            if (array_key_exists($field, $_POST)) {
                $value = trim((string) $_POST[$field]);
                if ($value !== setting($key, '')) {
                    setting_set($key, $value);
                    $saved++;
                }
            }
        }
    }

    log_activity('Updated branding and SEO', 'settings');
    flash($saved ? $saved . ' change' . ($saved === 1 ? '' : 's') . ' saved.' : 'Nothing had changed.');
    redirect('branding.php');
}

admin_head('Branding & SEO', 'branding.php');
admin_page_head('Branding and SEO',
    'Your logo, the icon in the browser tab, the picture people see when they share your link, '
    . 'and the words Google puts in the search results.');
?>

<form method="post" class="admin" enctype="multipart/form-data">
  <?= csrf_field() ?>
<?php foreach ($groups as $group => $fields): ?>
  <fieldset>
    <legend><?= esc($group) ?></legend>
<?php foreach ($fields as [$key, $label, $type, $hint]):
        $field = bfield($key);
        $value = setting($key, ''); ?>
    <div class="f">
      <label for="<?= esc($field) ?>"><?= esc($label) ?></label>
<?php   if ($type === 'image'): ?>
<?php     if ($value): ?>
      <img class="thumb" src="../<?= esc($value) ?>" alt=""
           style="background:#fff;object-fit:contain;padding:6px">
      <label class="check" style="margin:8px 0">
        <input type="checkbox" name="remove_<?= esc($field) ?>" value="1">
        <span>Remove this image</span></label>
<?php     endif; ?>
      <input id="<?= esc($field) ?>" name="<?= esc($field) ?>" type="file"
             accept="image/png,image/jpeg,image/svg+xml,image/webp,image/gif">
<?php   elseif ($type === 'textarea'): ?>
      <textarea id="<?= esc($field) ?>" name="<?= esc($field) ?>"><?= esc($value) ?></textarea>
<?php   else: ?>
      <input id="<?= esc($field) ?>" name="<?= esc($field) ?>" value="<?= esc($value) ?>">
<?php   endif; ?>
<?php   if ($hint): ?><span class="hint"><?= esc($hint) ?></span><?php endif; ?>
    </div>
<?php endforeach; ?>
  </fieldset>
<?php endforeach; ?>
  <div class="formbar">
    <button class="btn" type="submit">Save</button>
    <a class="btn ghost" href="../index.php" target="_blank" rel="noopener">See the site &nearr;</a>
  </div>
</form>

<fieldset style="margin-top:20px">
  <legend>How your link will look when shared</legend>
  <p style="color:var(--mute);font-size:13.5px;margin-bottom:16px">
    Roughly what WhatsApp, Facebook and X will show. They cache it, so give them a day to catch
    up after a change.</p>
  <div style="max-width:460px;border:1px solid var(--line);border-radius:12px;overflow:hidden;
              background:var(--panel2)">
<?php $social = setting('brand.social_image', ''); ?>
<?php if ($social): ?>
    <img src="../<?= esc($social) ?>" alt="" style="width:100%;aspect-ratio:1200/630;object-fit:cover;display:block">
<?php else: ?>
    <div style="aspect-ratio:1200/630;display:grid;place-items:center;background:var(--raise);
                color:var(--dim);font-size:13px;text-align:center;padding:20px">
      No sharing image yet &mdash; your link will show as plain text</div>
<?php endif; ?>
    <div style="padding:12px 14px">
      <div style="font-family:var(--mono);font-size:10px;letter-spacing:.12em;text-transform:uppercase;
                  color:var(--dim)"><?= esc(parse_url(base_url(), PHP_URL_HOST) ?: 'yourdomain.com') ?></div>
      <div style="font-weight:600;margin-top:4px"><?= esc(seo_title(setting('home.meta.title'))) ?></div>
      <div style="color:var(--mute);font-size:13.5px;margin-top:3px">
        <?= esc(excerpt(seo_description(setting('home.meta.desc')), 24)) ?></div>
    </div>
  </div>
</fieldset>

<?php admin_foot(); ?>
