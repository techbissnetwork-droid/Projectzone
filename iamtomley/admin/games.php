<?php
$__page = 'games';
$title = 'Games';
require_once __DIR__ . '/_bootstrap.php';
require_login();

$pdo = db();
$cats = ['arcade', 'puzzle', 'action', 'sports', 'casual'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'save_all') {
        $ids = $_POST['id'] ?? [];
        $st = $pdo->prepare("UPDATE games SET title=?,cat=?,emoji=?,sort_order=?,is_active=? WHERE id=?");
        foreach ($ids as $i => $id) {
            $cat = (string) ($_POST['cat'][$i] ?? 'arcade');
            if (!in_array($cat, $cats, true)) { $cat = 'arcade'; }
            $st->execute([
                trim((string) ($_POST['title'][$i] ?? '')),
                $cat,
                trim((string) ($_POST['emoji'][$i] ?? '🎮')),
                (int) ($_POST['sort_order'][$i] ?? 0),
                isset($_POST['is_active'][$id]) ? 1 : 0,
                (int) $id,
            ]);
        }
        flash('Games saved.');
    }
    header('Location: ' . url('/admin/games.php'));
    exit;
}

require_once __DIR__ . '/includes/head.php';
$rows = $pdo->query("SELECT * FROM games ORDER BY sort_order, id")->fetchAll();
?>
<div class="topbar"><div><h1 class="page-title">Games</h1><div class="page-sub">Rename, recategorise, reorder, or hide the playable games.</div></div></div>

<div class="alert alert-warn" style="background:rgba(0,212,255,.08);border-color:rgba(0,212,255,.25);color:var(--cyan)">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
  The playable game code (<span class="pill">ID</span> column) is self-contained and fixed. You can fully manage how each game is listed; adding a brand-new playable game requires adding its code to <span class="pill">assets/js/games-data.js</span>.
</div>

<div class="panel">
  <h2>Manage games (<?= count($rows) ?>)</h2>
  <form method="post" action="<?= e(url('/admin/games.php')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_all" />
    <div class="table-wrap">
      <table>
        <thead><tr><th>ID</th><th>Emoji</th><th>Title</th><th>Category</th><th>Order</th><th>Live</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $i => $r): ?>
          <tr>
            <td class="muted">#<?= (int) $r['code_ref'] ?><input type="hidden" name="id[]" value="<?= (int) $r['id'] ?>"></td>
            <td><input type="text" name="emoji[]" value="<?= e($r['emoji']) ?>" style="width:64px;text-align:center"></td>
            <td><input type="text" name="title[]" value="<?= e($r['title']) ?>"></td>
            <td>
              <select name="cat[]">
                <?php foreach ($cats as $c): ?><option value="<?= $c ?>" <?= $r['cat']===$c?'selected':'' ?>><?= ucfirst($c) ?></option><?php endforeach; ?>
              </select>
            </td>
            <td><input type="number" name="sort_order[]" value="<?= (int) $r['sort_order'] ?>" style="width:70px"></td>
            <td><label class="switch"><input type="checkbox" name="is_active[<?= (int) $r['id'] ?>]" <?= $r['is_active']?'checked':'' ?>><span class="track"></span></label></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="actions" style="margin-top:1.1rem"><button class="btn btn-primary" type="submit">Save all games</button></div>
  </form>
</div>

<?php require __DIR__ . '/includes/foot.php'; ?>
