<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/guard.php';
require_admin();

$LOCATIONS = [
    'header'   => 'Header menu',
    'footer_1' => 'Footer column 1',
    'footer_2' => 'Footer column 2',
    'footer_3' => 'Footer column 3',
];
$loc = (string)($_GET['loc'] ?? 'header');
if (!isset($LOCATIONS[$loc])) {
    $loc = 'header';
}

if (post()) {
    Csrf::check();
    $do = (string)($_POST['do'] ?? '');

    if ($do === 'add') {
        $labelText = trim((string)($_POST['label'] ?? ''));
        $target    = (string)($_POST['target'] ?? '');
        if ($labelText === '') {
            Flash::err('Give the link a label.');
        } else {
            $pageId = null;
            $url    = null;
            if (str_starts_with($target, 'page:')) {
                $pageId = (int)substr($target, 5);
            } else {
                $url = trim((string)($_POST['custom_url'] ?? '')) ?: $target;
                if ($url === '') {
                    Flash::err('Choose a destination or enter a URL.');
                    redirect('admin/navigation.php?loc=' . $loc);
                }
            }
            Database::insert('nav_items', [
                'location'   => $loc,
                'label'      => $labelText,
                'url'        => $url,
                'page_id'    => $pageId,
                'new_tab'    => !empty($_POST['new_tab']) ? 1 : 0,
                'sort_order' => (int)Database::value('SELECT COALESCE(MAX(sort_order),0)+10 FROM nav_items WHERE location = :l', ['l' => $loc], 10),
                'is_active'  => 1,
                'created_at' => now(),
            ]);
            Flash::ok('Link added.');
        }
    }

    if ($do === 'save') {
        foreach ((array)($_POST['label'] ?? []) as $rowId => $lbl) {
            $rowId = (int)$rowId;
            $lbl = trim((string)$lbl);
            if ($lbl === '') {
                continue;
            }
            Database::run('UPDATE nav_items SET label = :lb, sort_order = :s, is_active = :a
                           WHERE id = :i AND location = :l', [
                'lb' => $lbl,
                's'  => (int)($_POST['sort'][$rowId] ?? 0),
                'a'  => !empty($_POST['active'][$rowId]) ? 1 : 0,
                'i'  => $rowId,
                'l'  => $loc,
            ]);
        }
        Flash::ok('Menu saved.');
    }

    if ($do === 'delete') {
        Database::run('DELETE FROM nav_items WHERE id = :i AND location = :l',
            ['i' => (int)($_POST['id'] ?? 0), 'l' => $loc]);
        Flash::ok('Link removed.');
    }
    log_activity('nav.update', 'nav', null, $loc);
    redirect('admin/navigation.php?loc=' . $loc);
}

$items = Database::all('SELECT n.*, p.title AS page_title, p.slug AS page_slug
                        FROM nav_items n LEFT JOIN pages p ON p.id = n.page_id
                        WHERE n.location = :l ORDER BY n.sort_order, n.id', ['l' => $loc]);
$pages = Database::all('SELECT id, title FROM pages ORDER BY title');

$BUILTIN = [
    'services.php'    => 'Services',
    'portfolio.php'   => 'Work / portfolio',
    'marketplace.php' => 'Marketplace',
    'contact.php'     => 'Contact',
    'login.php'       => 'Client portal',
    '/'               => 'Home',
];

$PAGE_TITLE = 'Menus';
$AREA = 'admin';
require __DIR__ . '/../partials/app_header.php';
?>
<div class="filters">
  <?php foreach ($LOCATIONS as $k => $v): ?>
    <a href="?loc=<?= e($k) ?>" class="<?= $loc === $k ? 'on' : '' ?>"><?= e($v) ?></a>
  <?php endforeach; ?>
</div>

<div class="split">
  <section class="card">
    <div class="card__head"><h2><?= e($LOCATIONS[$loc]) ?></h2>
      <span class="badge muted"><?= count($items) ?> links</span></div>
    <?php if (!$items): ?>
      <div class="empty"><b>No links here</b><p>Add one from the panel on the right.</p></div>
    <?php else: ?>
      <form method="post"><?= Csrf::field() ?><input type="hidden" name="do" value="save">
        <div class="tablewrap"><table class="data">
          <thead><tr><th style="width:76px">Order</th><th>Label</th><th>Goes to</th><th>Shown</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($items as $it): ?>
            <tr>
              <td><input type="number" name="sort[<?= (int)$it['id'] ?>]" value="<?= (int)$it['sort_order'] ?>"
                         style="width:66px;padding:6px 8px;border-radius:6px;border:1px solid var(--line-2);background:rgba(255,255,255,.03);color:var(--tx)"></td>
              <td><input name="label[<?= (int)$it['id'] ?>]" value="<?= e($it['label']) ?>"
                         style="width:100%;padding:7px 10px;border-radius:6px;border:1px solid var(--line-2);background:rgba(255,255,255,.03);color:var(--tx)"></td>
              <td class="mono dim"><?= $it['page_id']
                    ? e('Page: ' . ($it['page_title'] ?? 'deleted'))
                    : e((string)$it['url']) ?><?= $it['new_tab'] ? ' ↗' : '' ?></td>
              <td><label class="field check" style="gap:6px"><input type="checkbox" name="active[<?= (int)$it['id'] ?>]" value="1"<?= $it['is_active'] ? ' checked' : '' ?>><span></span></label></td>
              <td class="right"><button class="btn danger sm" type="submit" formnovalidate
                    name="do" value="delete" onclick="this.form.querySelector('[name=id]').value=<?= (int)$it['id'] ?>;return confirm('Remove this link?')">Remove</button></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table></div>
        <input type="hidden" name="id" value="0">
        <div class="card__body"><button class="btn" type="submit">Save menu</button></div>
      </form>
    <?php endif; ?>
  </section>

  <section class="card">
    <div class="card__head"><h2>Add a link</h2></div>
    <div class="card__body">
      <form method="post" class="form"><?= Csrf::field() ?><input type="hidden" name="do" value="add">
        <label class="field"><span>Label</span><input name="label" required maxlength="120" placeholder="About us"></label>
        <label class="field"><span>Destination</span>
          <select name="target">
            <optgroup label="Built-in pages">
              <?php foreach ($BUILTIN as $u => $t): ?><option value="<?= e($u) ?>"><?= e($t) ?></option><?php endforeach; ?>
            </optgroup>
            <?php if ($pages): ?>
              <optgroup label="Your pages">
                <?php foreach ($pages as $pg): ?><option value="page:<?= (int)$pg['id'] ?>"><?= e($pg['title']) ?></option><?php endforeach; ?>
              </optgroup>
            <?php endif; ?>
            <optgroup label="Other"><option value="">Custom URL…</option></optgroup>
          </select></label>
        <label class="field"><span>Custom URL <small>only when “Custom URL” is chosen</small></span>
          <input name="custom_url" placeholder="https://example.com or /something.php"></label>
        <label class="field check"><input type="checkbox" name="new_tab" value="1"><span>Open in a new tab</span></label>
        <div class="formfoot"><button class="btn" type="submit">Add link</button></div>
      </form>
    </div>
  </section>
</div>
<?php require __DIR__ . '/../partials/app_footer.php'; ?>
