<?php
/** Company details, email addresses and the things that are not page copy. */
require_once __DIR__ . '/../app/bootstrap.php';
require_installed();
require_staff();
require_once __DIR__ . '/_layout.php';

$keys = [
    'Company' => [
        ['site.name',          'Company name',        'text'],
        ['site.tagline',       'Footer description',  'textarea'],
        ['site.currency',      'Currency symbol',     'text'],
        ['nav.cta',            'Header button label', 'text'],
    ],
    'Contact details' => [
        ['site.email',         'Public email address',  'text'],
        ['site.support_email', 'Support email address', 'text'],
        ['site.phone',         'Phone number',          'text'],
        ['site.hours',         'Opening hours',         'text'],
        ['site.address',       'Address',               'textarea'],
    ],
    'Social links' => [
        ['social.linkedin',  'LinkedIn URL',  'text'],
        ['social.instagram', 'Instagram URL', 'text'],
        ['social.whatsapp',  'WhatsApp URL',  'text'],
    ],
    'Marketplace' => [
        ['market.setup.price', 'Setup service price', 'text'],
        ['market.setup.blurb', 'What setup includes', 'textarea'],
    ],
    'Word strip' => [
        ['marquee.words', 'Scrolling words (one per line)', 'textarea'],
    ],
];

if (is_post()) {
    csrf_check();
    foreach ($keys as $fields) {
        foreach ($fields as [$key]) {
            if (array_key_exists('s_' . $key, $_POST)) {
                setting_set($key, trim((string) $_POST['s_' . $key]));
            }
        }
    }
    log_activity('Updated settings', 'settings');
    flash('Settings saved.');
    redirect('settings.php');
}

$cfg = config();

admin_head('Settings', 'settings.php');
admin_page_head('Settings', 'Company details and the values used across the whole site.');
?>

<form method="post" class="admin">
  <?= csrf_field() ?>
<?php foreach ($keys as $group => $fields): ?>
  <fieldset>
    <legend><?= esc($group) ?></legend>
<?php foreach ($fields as [$key, $label, $type]): ?>
    <div class="f">
      <label for="s_<?= esc($key) ?>"><?= esc($label) ?></label>
<?php if ($type === 'textarea'): ?>
      <textarea id="s_<?= esc($key) ?>" name="s_<?= esc($key) ?>"><?= esc(setting($key)) ?></textarea>
<?php else: ?>
      <input id="s_<?= esc($key) ?>" name="s_<?= esc($key) ?>" value="<?= esc(setting($key)) ?>">
<?php endif; ?>
    </div>
<?php endforeach; ?>
  </fieldset>
<?php endforeach; ?>
  <div class="formbar"><button class="btn" type="submit">Save settings</button></div>
</form>

<fieldset style="margin-top:20px">
  <legend>Server configuration</legend>
  <p style="color:var(--mute);font-size:14px;margin-bottom:14px">
    These live in <code style="font-family:var(--mono)">app/config.php</code>, written when you
    installed. Edit that file to change them.</p>
  <div class="kv"><span>Database</span><strong><?= esc(($cfg['driver'] ?? '') === 'sqlite'
      ? 'SQLite (file)' : 'MySQL — ' . ($cfg['database'] ?? '')) ?></strong></div>
  <div class="kv"><span>Mail sent from</span><strong><?= esc($cfg['mail_from'] ?? 'not set') ?></strong></div>
  <div class="kv"><span>Enquiries emailed to</span><strong><?= esc($cfg['mail_to'] ?? 'not set') ?></strong></div>
  <div class="kv"><span>Site address</span><strong><?= esc($cfg['base_url'] ?: 'detected automatically') ?></strong></div>
  <div class="kv"><span>PHP</span><strong><?= esc(PHP_VERSION) ?></strong></div>
  <div class="kv"><span>Installer still present</span>
    <strong><?= is_file(APP_ROOT . '/install.php')
      ? '<span class="pill urgent">Yes — delete install.php</span>'
      : '<span class="pill ok">No</span>' ?></strong></div>
</fieldset>

<?php admin_foot(); ?>
