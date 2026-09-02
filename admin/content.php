<?php
/**
 * Every heading, paragraph and button label on the public site, grouped by
 * the page it appears on. No HTML required.
 */
require_once __DIR__ . '/../app/bootstrap.php';
require_installed();
require_staff();
require_once __DIR__ . '/_layout.php';

$groupTitles = [
    'home'        => 'Home page',
    'services'    => 'Services page',
    'industries'  => 'Industries page',
    'pricing'     => 'Pricing page',
    'about'       => 'About page',
    'portfolio'   => 'Work page',
    'marketplace' => 'Marketplace page',
    'contact'     => 'Contact page',
    'global'      => 'Site-wide',
];

$group = get('group', 'home');
if (!isset($groupTitles[$group])) {
    $group = 'home';
}

if (is_post()) {
    csrf_check();
    $saved = 0;
    foreach (db_all('SELECT * FROM settings WHERE group_name = ?', [post('group', $group)]) as $s) {
        $field = 'f_' . $s['id'];
        if (!array_key_exists($field, $_POST)) {
            continue;
        }
        $value = is_scalar($_POST[$field]) ? trim((string) $_POST[$field]) : '';
        if ($value !== (string) $s['value']) {
            db_update('settings', (int) $s['id'], ['value' => $value]);
            $saved++;
        }
    }
    log_activity('Edited page text: ' . ($groupTitles[post('group', $group)] ?? ''), 'settings');
    flash($saved ? $saved . ' change' . ($saved === 1 ? '' : 's') . ' saved.' : 'Nothing had changed.');
    redirect('content.php?group=' . urlencode(post('group', $group)));
}

$rows = db_all('SELECT * FROM settings WHERE group_name = ? ORDER BY sort, id', [$group]);

admin_head('Page text', 'content.php');
admin_page_head('Page text', 'Every word on the public site. Changes are live the moment you save.');
?>

<div class="filters">
<?php foreach ($groupTitles as $g => $label): ?>
  <a class="<?= $g === $group ? 'on' : '' ?>" href="content.php?group=<?= urlencode($g) ?>"><?= esc($label) ?></a>
<?php endforeach; ?>
</div>

<form method="post" class="admin">
  <?= csrf_field() ?>
  <input type="hidden" name="group" value="<?= esc($group) ?>">
  <fieldset>
    <legend><?= esc($groupTitles[$group]) ?></legend>
<?php if (!$rows): ?>
    <p style="color:var(--mute)">Nothing to edit in this group.</p>
<?php endif; ?>
<?php foreach ($rows as $s): ?>
    <div class="f">
      <label for="f_<?= (int) $s['id'] ?>"><?= esc($s['label']) ?></label>
<?php if ($s['field_type'] === 'textarea'): ?>
      <textarea id="f_<?= (int) $s['id'] ?>" name="f_<?= (int) $s['id'] ?>"><?= esc($s['value']) ?></textarea>
<?php else: ?>
      <input id="f_<?= (int) $s['id'] ?>" name="f_<?= (int) $s['id'] ?>" value="<?= esc($s['value']) ?>">
<?php endif; ?>
      <span class="hint"><code><?= esc($s['setting_key']) ?></code><?php
        if (str_contains($s['label'], '{braces}')) {
            echo ' — text inside {curly braces} is highlighted in green on the site.';
        } elseif (str_contains($s['label'], 'one per line')) {
            echo ' — one item per line.';
        } ?></span>
    </div>
<?php endforeach; ?>
  </fieldset>
  <div class="formbar">
    <button class="btn" type="submit">Save changes</button>
    <a class="btn ghost" href="../index.php" target="_blank" rel="noopener">View the site &nearr;</a>
  </div>
</form>

<?php admin_foot(); ?>
