<?php
/** Edits the plain text on each page — headings, paragraphs, button labels. */
require __DIR__ . '/../app/bootstrap.php';
require_installed();
auth_require();
require __DIR__ . '/../app/resources.php';
require __DIR__ . '/_layout.php';

$groups = ['global' => 'Company details', 'home' => 'Home page', 'services' => 'Services page',
           'industries' => 'Industries page', 'pricing' => 'Pricing page', 'about' => 'About page',
           'contact' => 'Contact page'];

$g = (string) ($_GET['g'] ?? 'global');
if (!isset($groups[$g])) {
    $g = 'global';
}

if (is_post()) {
    csrf_check();
    $posted = $_POST['c'] ?? [];
    if (is_array($posted)) {
        // Only keys that already exist in this group are written, so a crafted
        // post cannot create or overwrite content elsewhere.
        $allowed = array_column(all('SELECT ckey FROM content WHERE cgroup = :g', ['g' => $g]), 'ckey');
        foreach ($posted as $ckey => $value) {
            if (in_array($ckey, $allowed, true) && is_string($value)) {
                q('UPDATE content SET cvalue = :v WHERE ckey = :k', ['v' => $value, 'k' => $ckey]);
            }
        }
    }
    flash('Page text saved.');
    redirect('admin/content.php?g=' . $g);
}

$items = all('SELECT * FROM content WHERE cgroup = :g ORDER BY sort ASC, id ASC', ['g' => $g]);

admin_header($groups[$g]);
?>
<h1><?= e($groups[$g]) ?></h1>
<p class="amuted">
  Plain text only — no HTML needed. In the big statement lines, put
  <code>{curly braces}</code> around the words you want highlighted in green.
</p>

<form method="post" class="apanel apanel--wide">
  <?= csrf_field() ?>
  <?php foreach ($items as $c): ?>
    <div class="afield">
      <label for="c_<?= e($c['ckey']) ?>"><?= e($c['clabel']) ?></label>
      <?php if ($c['ctype'] === 'textarea'): ?>
        <textarea id="c_<?= e($c['ckey']) ?>" name="c[<?= e($c['ckey']) ?>]" rows="<?= substr_count((string) $c['cvalue'], "\n") > 3 ? 8 : 3 ?>"><?= e($c['cvalue']) ?></textarea>
      <?php else: ?>
        <input type="text" id="c_<?= e($c['ckey']) ?>" name="c[<?= e($c['ckey']) ?>]" value="<?= e($c['cvalue']) ?>">
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
  <div class="arow"><button type="submit">Save page text</button></div>
</form>
<?php admin_footer(); ?>
