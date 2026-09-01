<?php
$__page = 'projects';
$title = 'Projects';
require_once __DIR__ . '/_bootstrap.php';
require_login();

$pdo = db();

/** True if the projects table has a usable status column (adds it if it can). */
function projects_has_status(PDO $pdo): bool
{
    static $ok = null;
    if ($ok !== null) return $ok;
    if (function_exists('ensure_column')) {
        ensure_column($pdo, 'projects', 'status', "VARCHAR(20) NOT NULL DEFAULT 'live'");
    }
    try {
        $pdo->query("SELECT status FROM projects LIMIT 1");
        $ok = true;
    } catch (Throwable $e) {
        $ok = false;
    }
    return $ok;
}

/** True if the projects table has a usable price column (adds it if it can). */
function projects_has_price(PDO $pdo): bool
{
    static $ok = null;
    if ($ok !== null) return $ok;
    if (function_exists('ensure_column')) {
        ensure_column($pdo, 'projects', 'price', "VARCHAR(40) NOT NULL DEFAULT ''");
    }
    try {
        $pdo->query("SELECT price FROM projects LIMIT 1");
        $ok = true;
    } catch (Throwable $e) {
        $ok = false;
    }
    return $ok;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');
    $HAS_STATUS = projects_has_status($pdo);
    $HAS_PRICE  = projects_has_price($pdo);

    if ($action === 'save') {
        $id     = (int) ($_POST['id'] ?? 0);
        $status = project_status_key((string) ($_POST['status'] ?? 'live'));
        $price  = trim((string) ($_POST['price'] ?? ''));
        $ts     = in_array((string) ($_POST['tag_style'] ?? ''), ['', 'pink', 'violet'], true) ? (string) $_POST['tag_style'] : '';
        $active = $status === 'hidden' ? 0 : 1;   // kept in sync for compatibility

        // Build the column/value lists dynamically so missing columns never 500.
        $cols = ['title','tag','tag_style','description','url','link_label','sort_order','is_active'];
        $vals = [
            trim((string) ($_POST['title'] ?? '')),
            trim((string) ($_POST['tag'] ?? '')),
            $ts,
            trim((string) ($_POST['description'] ?? '')),
            trim((string) ($_POST['url'] ?? '#')) ?: '#',
            trim((string) ($_POST['link_label'] ?? 'Visit Site')) ?: 'Visit Site',
            (int) ($_POST['sort_order'] ?? 0),
            $active,
        ];
        if ($HAS_STATUS) { $cols[] = 'status'; $vals[] = $status; }
        if ($HAS_PRICE)  { $cols[] = 'price';  $vals[] = $price;  }

        if ($id > 0) {
            $set = implode(',', array_map(fn($c) => "$c=?", $cols));
            $pdo->prepare("UPDATE projects SET $set WHERE id=?")->execute([...$vals, $id]);
            flash('Project updated.');
        } else {
            $ph = implode(',', array_fill(0, count($cols), '?'));
            $pdo->prepare("INSERT INTO projects (" . implode(',', $cols) . ") VALUES ($ph)")->execute($vals);
            flash('Project added.');
        }
    } elseif ($action === 'setstatus') {
        $status = project_status_key((string) ($_POST['status'] ?? 'live'));
        $id = (int) ($_POST['id'] ?? 0);
        if ($HAS_STATUS) {
            $pdo->prepare("UPDATE projects SET status=?, is_active=? WHERE id=?")
                ->execute([$status, $status === 'hidden' ? 0 : 1, $id]);
        } else {
            $pdo->prepare("UPDATE projects SET is_active=? WHERE id=?")
                ->execute([$status === 'hidden' ? 0 : 1, $id]);
        }
        flash($HAS_STATUS
            ? 'Project status set to ' . (project_statuses()[$status][0] ?? $status) . '.'
            : 'Visibility updated. (Add the status column to enable Sold/Coming Soon — see notice.)');
    } elseif ($action === 'delete') {
        $pdo->prepare("DELETE FROM projects WHERE id=?")->execute([(int) ($_POST['id'] ?? 0)]);
        flash('Project deleted.');
    } elseif ($action === 'repair') {
        $done = []; $failed = [];
        // status column
        if (!$HAS_STATUS) {
            try {
                $pdo->exec("ALTER TABLE projects ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'live'");
                try { $pdo->exec("UPDATE projects SET status='hidden' WHERE is_active=0"); } catch (Throwable $e) {}
                $done[] = 'status';
            } catch (Throwable $e) {
                $m = $e->getMessage();
                if (stripos($m, 'duplicate') !== false || stripos($m, 'exists') !== false) $done[] = 'status';
                else $failed[] = 'status (' . $m . ')';
            }
        } else { $done[] = 'status'; }
        // price column
        if (!$HAS_PRICE) {
            try {
                $pdo->exec("ALTER TABLE projects ADD COLUMN price VARCHAR(40) NOT NULL DEFAULT ''");
                $done[] = 'price';
            } catch (Throwable $e) {
                $m = $e->getMessage();
                if (stripos($m, 'duplicate') !== false || stripos($m, 'exists') !== false) $done[] = 'price';
                else $failed[] = 'price (' . $m . ')';
            }
        } else { $done[] = 'price'; }

        if (!$failed) {
            flash('Database ready — Sold / For Sale / Coming Soon and prices now work. 🎉');
        } else {
            flash('Some columns could not be added automatically: ' . implode('; ', $failed) . ' — please run the SQL shown below in phpMyAdmin.');
        }
    }
    header('Location: ' . url('/admin/projects.php'));
    exit;
}

require_once __DIR__ . '/includes/head.php';

$editId = (int) ($_GET['edit'] ?? 0);
$edit = null;
if ($editId > 0) {
    $st = $pdo->prepare("SELECT * FROM projects WHERE id=?");
    $st->execute([$editId]);
    $edit = $st->fetch() ?: null;
}
$rows = $pdo->query("SELECT * FROM projects ORDER BY sort_order, id")->fetchAll();
$blank = ['id'=>0,'title'=>'','tag'=>'','tag_style'=>'','description'=>'','url'=>'','link_label'=>'Visit Site','sort_order'=>(count($rows)+1),'status'=>'live','price'=>''];
$form = $edit ?: $blank;
$curStatus = project_status_key($form['status'] ?? 'live');
$STATUSES = project_statuses();

// Category filter (by status): counts across all rows + the filtered subset.
$counts = ['all' => count($rows)];
foreach (array_keys($STATUSES) as $sk) { $counts[$sk] = 0; }
foreach ($rows as $r) { $counts[project_status_key($r['status'] ?? 'live')]++; }
$filter = (string) ($_GET['status'] ?? 'all');
if ($filter !== 'all' && !array_key_exists($filter, $STATUSES)) { $filter = 'all'; }
$shown = $filter === 'all'
    ? $rows
    : array_values(array_filter($rows, fn($r) => project_status_key($r['status'] ?? 'live') === $filter));
?>
<?php $hasStatus = projects_has_status($pdo); $hasPrice = projects_has_price($pdo); ?>
<div class="topbar"><div><h1 class="page-title">Projects</h1><div class="page-sub">Cards in the projects slider. Set each to Live, Sold, Coming Soon or Hidden.</div></div></div>
<?php if (!$hasStatus || !$hasPrice): ?>
<div class="panel" style="border-color:rgba(255,200,60,.3)">
  <div class="alert alert-warn" style="margin-bottom:1rem"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
    Your database is missing
    <?php if (!$hasStatus): ?>the <span class="pill">status</span> column<?php endif; ?>
    <?php if (!$hasStatus && !$hasPrice): ?> and <?php endif; ?>
    <?php if (!$hasPrice): ?>the <span class="pill">price</span> column<?php endif; ?>,
    so <strong>Sold / For&nbsp;Sale / Coming&nbsp;Soon</strong> and prices won't persist yet. Click the button to add <?= (!$hasStatus && !$hasPrice) ? 'them' : 'it' ?> automatically:</div>
  <form method="post" action="<?= e(url('/admin/projects.php')) ?>" style="margin-bottom:1rem"><?= csrf_field() ?><input type="hidden" name="action" value="repair"><button class="btn btn-primary" type="submit">Upgrade the projects table now</button></form>
  <div class="small">If that fails (some hosts block <code>ALTER</code>), run <?= (!$hasStatus && !$hasPrice) ? 'these' : 'this' ?> once in <strong>phpMyAdmin → your database → SQL</strong>:<br>
  <?php if (!$hasStatus): ?><code style="display:inline-block;margin-top:.4rem">ALTER TABLE projects ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'live';</code><br><?php endif; ?>
  <?php if (!$hasPrice): ?><code style="display:inline-block;margin-top:.4rem">ALTER TABLE projects ADD COLUMN price VARCHAR(40) NOT NULL DEFAULT '';</code><?php endif; ?></div>
</div>
<?php endif; ?>

<div class="panel">
  <h2><?= $edit ? 'Edit project' : 'Add a project' ?></h2>
  <form method="post" action="<?= e(url('/admin/projects.php')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save" />
    <input type="hidden" name="id" value="<?= (int) $form['id'] ?>" />
    <div class="grid-2">
      <div class="field"><label>Title</label><input type="text" name="title" value="<?= e($form['title']) ?>" required /></div>
      <div class="field"><label>Tag label</label><input type="text" name="tag" value="<?= e($form['tag']) ?>" placeholder="Featured / Tool / Game…" /></div>
    </div>
    <div class="grid-3">
      <div class="field"><label>Tag colour</label>
        <select name="tag_style">
          <option value="" <?= $form['tag_style']===''?'selected':'' ?>>Mint (default)</option>
          <option value="pink" <?= $form['tag_style']==='pink'?'selected':'' ?>>Pink</option>
          <option value="violet" <?= $form['tag_style']==='violet'?'selected':'' ?>>Violet</option>
        </select>
      </div>
      <div class="field"><label>Status</label>
        <select name="status">
          <?php foreach ($STATUSES as $sk => $sm): ?>
            <option value="<?= e($sk) ?>" <?= $curStatus===$sk?'selected':'' ?>><?= e($sm[0]) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field"><label>Sort order</label><input type="number" name="sort_order" value="<?= (int) $form['sort_order'] ?>" /></div>
    </div>
    <div class="field"><label>Price <span class="muted">(shown when status is “For Sale”)</span></label><input type="text" name="price" value="<?= e($form['price'] ?? '') ?>" placeholder="e.g. $1,499 or Make an offer" /></div>
    <div class="field"><label>Description</label><textarea name="description"><?= e($form['description']) ?></textarea></div>
    <div class="grid-2">
      <div class="field"><label>Link URL</label><input type="text" name="url" value="<?= e($form['url']) ?>" placeholder="https://… (leave as # to hide the link)" /></div>
      <div class="field"><label>Link label</label><input type="text" name="link_label" value="<?= e($form['link_label']) ?>" /></div>
    </div>
    <div class="hint" style="margin-bottom:1rem"><strong>Live</strong> shows normally · <strong>For Sale</strong> shows the price + a Buy button (email/chat, set in Settings) · <strong>Sold</strong> adds a SOLD ribbon &amp; disables the link · <strong>Coming Soon</strong> dims it with a SOON ribbon · <strong>Hidden</strong> removes it from the site.</div>
    <div class="actions">
      <button class="btn btn-primary" type="submit"><?= $edit ? 'Update project' : 'Add project' ?></button>
      <?php if ($edit): ?><a class="btn btn-ghost" href="<?= e(url('/admin/projects.php')) ?>">Cancel</a><?php endif; ?>
    </div>
  </form>
</div>

<div class="panel">
  <h2>All projects (<?= count($rows) ?>)</h2>
  <div class="filter-tabs" style="display:flex;flex-wrap:wrap;gap:.5rem;margin-bottom:1.1rem">
    <?php
      $tabs = ['all' => 'All'];
      foreach ($STATUSES as $sk => $sm) { $tabs[$sk] = $sm[0]; }
      foreach ($tabs as $tk => $tl):
        $active = $filter === $tk;
        $href = $tk === 'all' ? url('/admin/projects.php') : url('/admin/projects.php?status=' . $tk);
    ?>
    <a class="btn btn-sm <?= $active ? 'btn-primary' : 'btn-ghost' ?>" href="<?= e($href) ?>"><?= e($tl) ?> <span class="pill" style="margin-left:.15rem;padding:.05rem .4rem"><?= (int) ($counts[$tk] ?? 0) ?></span></a>
    <?php endforeach; ?>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>#</th><th>Title</th><th>Tag</th><th>Status</th><th>Price</th><th style="text-align:right">Actions</th></tr></thead>
      <tbody>
        <?php foreach ($shown as $r): $rs = project_status_key($r['status'] ?? 'live'); $rp = trim((string) ($r['price'] ?? '')); ?>
        <tr>
          <td class="muted"><?= (int) $r['sort_order'] ?></td>
          <td><strong><?= e($r['title']) ?></strong><br><span class="muted"><?= e(mb_strimwidth($r['description'], 0, 66, '…')) ?></span></td>
          <td><?php if ($r['tag']!==''): ?><span class="pill"><?= e($r['tag']) ?></span><?php else: ?><span class="muted">—</span><?php endif; ?></td>
          <td><span class="pill <?= $rs==='live'?'on':($rs==='hidden'?'off':'') ?>" style="<?= $rs==='sold'?'color:var(--amber);border-color:rgba(255,200,60,.35)':($rs==='coming_soon'?'color:var(--violet);border-color:rgba(167,139,250,.35)':($rs==='for_sale'?'color:var(--mint,#00ffb3);border-color:rgba(0,255,179,.35)':'')) ?>"><?= e($STATUSES[$rs][0]) ?></span></td>
          <td><?php if ($rp!=='' && ($rs==='for_sale'||$rs==='sold')): ?><span class="pill" style="color:var(--mint,#00ffb3);border-color:rgba(0,255,179,.3)"><?= e($rp) ?></span><?php else: ?><span class="muted">—</span><?php endif; ?></td>
          <td style="text-align:right">
            <div class="actions" style="justify-content:flex-end;flex-wrap:wrap">
              <?php
                // Quick status buttons (show the ones this project isn't already)
                $quick = ['live'=>'Live','for_sale'=>'Sale','sold'=>'Sold','hidden'=>'Hide'];
                foreach ($quick as $qk => $ql):
                  if ($qk === $rs) continue; ?>
                <form method="post" action="<?= e(url('/admin/projects.php')) ?>" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="setstatus"><input type="hidden" name="status" value="<?= e($qk) ?>"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="btn btn-ghost btn-sm" type="submit"><?= e($ql) ?></button></form>
              <?php endforeach; ?>
              <a class="btn btn-ghost btn-sm" href="<?= e(url('/admin/projects.php?edit=' . (int) $r['id'])) ?>">Edit</a>
              <form method="post" action="<?= e(url('/admin/projects.php')) ?>" style="display:inline" onsubmit="return confirm('Delete this project?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="btn btn-danger btn-sm" type="submit">Delete</button></form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$shown): ?><tr><td colspan="6" class="muted" style="text-align:center;padding:2rem"><?= $rows ? 'No projects with this status.' : 'No projects yet.' ?></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/includes/foot.php'; ?>
