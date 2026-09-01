<?php
$__page = 'stats';
$title = 'Stats';
require_once __DIR__ . '/_bootstrap.php';
require_login();

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'save_all') {
        $ids = $_POST['id'] ?? [];
        $st = $pdo->prepare("UPDATE stats SET value=?,label=?,target=?,suffix=?,sort_order=? WHERE id=?");
        foreach ($ids as $i => $id) {
            $st->execute([
                trim((string) ($_POST['value'][$i] ?? '')),
                trim((string) ($_POST['label'][$i] ?? '')),
                trim((string) ($_POST['target'][$i] ?? '')),
                trim((string) ($_POST['suffix'][$i] ?? '')),
                (int) ($_POST['sort_order'][$i] ?? 0),
                (int) $id,
            ]);
        }
        flash('Stats saved.');
    } elseif ($action === 'add') {
        $pdo->prepare("INSERT INTO stats (value,label,target,suffix,sort_order) VALUES (?,?,?,?,?)")
            ->execute(['0', 'New stat', '', '', (int) ($pdo->query("SELECT COALESCE(MAX(sort_order),0)+1 FROM stats")->fetchColumn())]);
        flash('Stat added.');
    } elseif ($action === 'delete') {
        $pdo->prepare("DELETE FROM stats WHERE id=?")->execute([(int) ($_POST['id'] ?? 0)]);
        flash('Stat deleted.');
    }
    header('Location: ' . url('/admin/stats.php'));
    exit;
}

require_once __DIR__ . '/includes/head.php';
$rows = stats_all();
?>
<div class="topbar"><div><h1 class="page-title">Stat widgets</h1><div class="page-sub">The animated numbers under the Projects heading.</div></div></div>

<div class="panel">
  <h2>Edit stats</h2>
  <form method="post" action="<?= e(url('/admin/stats.php')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_all" />
    <div class="table-wrap">
      <table>
        <thead><tr><th>Display value</th><th>Label</th><th>Count-to target</th><th>Suffix</th><th>Order</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><input type="hidden" name="id[]" value="<?= (int) $r['id'] ?>"><input type="text" name="value[]" value="<?= e($r['value']) ?>" style="width:110px"></td>
            <td><input type="text" name="label[]" value="<?= e($r['label']) ?>"></td>
            <td><input type="text" name="target[]" value="<?= e($r['target']) ?>" placeholder="e.g. 100" style="width:120px"></td>
            <td><input type="text" name="suffix[]" value="<?= e($r['suffix']) ?>" placeholder="%" style="width:70px"></td>
            <td><input type="number" name="sort_order[]" value="<?= (int) $r['sort_order'] ?>" style="width:70px"></td>
            <td><form method="post" action="<?= e(url('/admin/stats.php')) ?>" onsubmit="return confirm('Delete this stat?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="btn btn-danger btn-sm" type="submit">✕</button></form></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="hint" style="margin:.75rem 0 1rem">Leave “target” empty for static values (like ∞). If a target is set, the number animates up to it on scroll; add a suffix like “%”.</div>
    <div class="actions">
      <button class="btn btn-primary" type="submit">Save stats</button>
    </div>
  </form>
</div>

<form method="post" action="<?= e(url('/admin/stats.php')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="add"><button class="btn btn-ghost" type="submit">+ Add stat</button></form>

<?php require __DIR__ . '/includes/foot.php'; ?>
