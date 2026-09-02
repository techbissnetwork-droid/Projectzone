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

if (is_post() && post('action') === 'migrate') {
    csrf_check();
    require_once APP_DIR . '/seed.php';
    try {
        $changes = schema_migrate();
        foreach (seed_missing_settings() as $key) {
            $changes[] = 'Added the "' . $key . '" setting';
        }
        schema_set_version(SCHEMA_VERSION);
        log_activity('Ran database update to version ' . SCHEMA_VERSION, 'system');
        flash($changes
            ? 'Database updated: ' . esc(implode('; ', $changes)) . '.'
            : 'Already up to date — nothing needed changing.');
    } catch (Throwable $e) {
        flash('The update failed: ' . esc($e->getMessage()) . ' No data was removed.', 'bad');
    }
    redirect('settings.php');
}

/**
 * PHP rewrites "." to "_" in $_POST keys, so a field literally named
 * "s_site.phone" would arrive as "s_site_phone" and never match. Encode the
 * dots on the way out and decode them on the way back.
 */
function field_name(string $key): string
{
    return 's_' . str_replace('.', '__', $key);
}

if (is_post() && post('action') === 'save') {
    csrf_check();
    $saved = 0;
    foreach ($keys as $fields) {
        foreach ($fields as [$key]) {
            $field = field_name($key);
            if (!array_key_exists($field, $_POST)) {
                continue;
            }
            $value = trim((string) $_POST[$field]);
            if ($value !== setting($key)) {
                setting_set($key, $value);
                $saved++;
            }
        }
    }
    log_activity('Updated settings', 'settings');
    flash($saved
        ? $saved . ' setting' . ($saved === 1 ? '' : 's') . ' saved.'
        : 'Nothing had changed.');
    redirect('settings.php');
}

$cfg = config();

admin_head('Settings', 'settings.php');
admin_page_head('Settings', 'Company details and the values used across the whole site.');
?>

<form method="post" class="admin">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="save">
<?php foreach ($keys as $group => $fields): ?>
  <fieldset>
    <legend><?= esc($group) ?></legend>
<?php foreach ($fields as [$key, $label, $type]): ?>
    <?php $field = field_name($key); ?>
    <div class="f">
      <label for="<?= esc($field) ?>"><?= esc($label) ?></label>
<?php if ($type === 'textarea'): ?>
      <textarea id="<?= esc($field) ?>" name="<?= esc($field) ?>"><?= esc(setting($key)) ?></textarea>
<?php else: ?>
      <input id="<?= esc($field) ?>" name="<?= esc($field) ?>" value="<?= esc(setting($key)) ?>">
<?php endif; ?>
    </div>
<?php endforeach; ?>
  </fieldset>
<?php endforeach; ?>
  <div class="formbar"><button class="btn" type="submit">Save settings</button></div>
</form>

<fieldset style="margin-top:20px">
  <legend>Database</legend>
<?php
$version = schema_installed_version();
$missing = schema_missing_tables();
$needs   = schema_needs_update() || $missing;
?>
  <div class="kv"><span>Schema version</span>
    <strong><?= (int) $version ?> of <?= SCHEMA_VERSION ?>
      <?= $needs ? '<span class="pill soon">Update available</span>'
                 : '<span class="pill ok">Up to date</span>' ?></strong></div>
  <div class="kv"><span>Tables</span>
    <strong><?= $missing
      ? '<span class="pill urgent">' . count($missing) . ' missing</span>'
      : count(schema_tables()) . ' present' ?></strong></div>
  <p style="color:var(--mute);font-size:13.5px;margin:14px 0">
    Run this after uploading a newer copy of the files. It adds any new tables, columns and
    settings the new version expects. <strong>It never deletes anything and never overwrites
    text you have edited.</strong></p>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="migrate">
    <button class="btn<?= $needs ? '' : ' ghost' ?>" type="submit">
      <?= $needs ? 'Run the update' : 'Check for updates' ?></button>
  </form>
</fieldset>

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
  <div class="kv"><span>Installer</span>
    <strong><?= is_file(APP_ROOT . '/install.php')
      ? '<span class="pill urgent">Still on the server — delete install.php</span>'
      : '<span class="pill ok">Removed</span>' ?></strong></div>
  <div class="kv"><span>Install lock</span>
    <strong><?= is_file(APP_DIR . '/install.lock')
      ? '<span class="pill ok">In place</span>'
      : '<span class="pill soon">Missing</span>' ?></strong></div>
</fieldset>

<?php admin_foot(); ?>
