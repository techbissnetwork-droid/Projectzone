<?php
$__page = 'projects';
$title = 'Projects';
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../includes/imagefetch.php';
require_login();

$pdo = db();

/** True if the projects table has a usable column (adding it first if it can). */
function projects_has_column(PDO $pdo, string $col, string $definition): bool
{
    static $known = [];
    if (isset($known[$col])) { return $known[$col]; }
    if (function_exists('ensure_column')) {
        ensure_column($pdo, 'projects', $col, $definition);
    }
    try {
        $pdo->query("SELECT $col FROM projects LIMIT 1");
        $known[$col] = true;
    } catch (Throwable $e) {
        $known[$col] = false;
    }
    return $known[$col];
}

/** True if the projects table has a usable image column. */
function projects_has_image(PDO $pdo): bool
{
    return projects_has_column($pdo, 'image', "VARCHAR(255) NOT NULL DEFAULT ''");
}

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
    $HAS_IMAGE  = projects_has_image($pdo);

    if ($action === 'save') {
        $id     = (int) ($_POST['id'] ?? 0);
        $status = project_status_key((string) ($_POST['status'] ?? 'live'));
        $price  = clip((string) ($_POST['price'] ?? ''), 40);
        $ts     = in_array((string) ($_POST['tag_style'] ?? ''), ['', 'pink', 'violet'], true) ? (string) $_POST['tag_style'] : '';
        $active = $status === 'hidden' ? 0 : 1;   // kept in sync for compatibility
        $title  = clip((string) ($_POST['title'] ?? ''), 120);
        $link   = clip((string) ($_POST['url'] ?? '#'), 255) ?: '#';

        // ── The card image ────────────────────────────────────────────────
        // Three ways to get one, in order: a file you picked, a path you typed
        // (or that Detect filled in), or — if you left it blank and gave a real
        // link — the site's own logo, fetched automatically.
        $image = clip((string) ($_POST['image'] ?? ''), 255);
        $description = trim((string) ($_POST['description'] ?? ''));
        $notes = [];
        if ($HAS_IMAGE) {
            $prefix = slugify($title, 24) ?: 'project';

            if (!empty($_FILES['image_file']['name'])) {
                $up = store_uploaded_image($_FILES['image_file'], $prefix);
                if ($up['ok']) {
                    $image = $up['path'];
                } elseif ($up['error'] !== '') {
                    $notes[] = $up['error'];
                }
            }
            if ($image === '' && preg_match('#^https?://#i', $link)) {
                $found = detect_site_info($link, $prefix);
                if ($found['path'] !== '') {
                    $image = $found['path'];
                    $notes[] = 'Picked up the ' . strtolower($found['kind']) . ' from ' . parse_url($link, PHP_URL_HOST) . '.';
                }
                // A blank description gets the site's own summary; anything
                // you typed is left exactly as you wrote it.
                if ($description === '' && $found['description'] !== '') {
                    $description = $found['description'];
                    $notes[] = 'Filled the description in from the site.';
                }
            }

            // Replacing a stored image? Tidy the old file away.
            if ($id > 0) {
                $prev = $pdo->prepare("SELECT image FROM projects WHERE id = ?");
                $prev->execute([$id]);
                $old = trim((string) ($prev->fetchColumn() ?: ''));
                if ($old !== '' && $old !== $image) {
                    delete_stored_image($old);
                }
            }
        }

        // Build the column/value lists dynamically so missing columns never 500.
        $cols = ['title','tag','tag_style','description','url','link_label','sort_order','is_active'];
        $vals = [
            $title,
            clip((string) ($_POST['tag'] ?? ''), 60),
            $ts,
            $description,
            $link,
            clip((string) ($_POST['link_label'] ?? 'Visit Site'), 40) ?: 'Visit Site',
            (int) ($_POST['sort_order'] ?? 0),
            $active,
        ];
        if ($HAS_STATUS) { $cols[] = 'status'; $vals[] = $status; }
        if ($HAS_PRICE)  { $cols[] = 'price';  $vals[] = $price;  }
        if ($HAS_IMAGE)  { $cols[] = 'image';  $vals[] = $image;  }

        if ($id > 0) {
            $set = implode(',', array_map(fn($c) => "$c=?", $cols));
            $pdo->prepare("UPDATE projects SET $set WHERE id=?")->execute([...$vals, $id]);
            array_unshift($notes, 'Project updated.');
        } else {
            $ph = implode(',', array_fill(0, count($cols), '?'));
            $pdo->prepare("INSERT INTO projects (" . implode(',', $cols) . ") VALUES ($ph)")->execute($vals);
            array_unshift($notes, 'Project added.');
        }
        flash(implode(' ', $notes));
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
        $id = (int) ($_POST['id'] ?? 0);
        if ($HAS_IMAGE) {
            $prev = $pdo->prepare("SELECT image FROM projects WHERE id = ?");
            $prev->execute([$id]);
            delete_stored_image((string) ($prev->fetchColumn() ?: ''));
        }
        $pdo->prepare("DELETE FROM projects WHERE id=?")->execute([$id]);
        flash('Project deleted.');
    } elseif ($action === 'repair') {
        // Add whatever the projects table is missing, one column at a time, so
        // an install that predates any of these features catches up.
        $wanted = [
            'status' => ["VARCHAR(20) NOT NULL DEFAULT 'live'", $HAS_STATUS],
            'price'  => ["VARCHAR(40) NOT NULL DEFAULT ''",     $HAS_PRICE],
            'image'  => ["VARCHAR(255) NOT NULL DEFAULT ''",    $HAS_IMAGE],
        ];
        $failed = [];
        foreach ($wanted as $col => [$definition, $present]) {
            if ($present) { continue; }
            try {
                $pdo->exec("ALTER TABLE projects ADD COLUMN $col $definition");
                if ($col === 'status') {
                    // Anything previously hidden keeps that meaning.
                    try { $pdo->exec("UPDATE projects SET status='hidden' WHERE is_active=0"); } catch (Throwable $e) {}
                }
            } catch (Throwable $e) {
                $m = $e->getMessage();
                // "already exists" means someone beat us to it — that is a win.
                if (stripos($m, 'duplicate') === false && stripos($m, 'exists') === false) {
                    $failed[] = $col . ' (' . $m . ')';
                }
            }
        }

        flash($failed
            ? 'Some columns could not be added automatically: ' . implode('; ', $failed) . ' — please run the SQL shown below in phpMyAdmin.'
            : 'Database ready — statuses, prices and card images now work. 🎉');
    }
    touch_content();
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
$blank = ['id'=>0,'title'=>'','tag'=>'','tag_style'=>'','description'=>'','url'=>'','link_label'=>'Visit Site','sort_order'=>(count($rows)+1),'status'=>'live','price'=>'','image'=>''];
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
<?php
  $missing = [];
  if (!projects_has_status($pdo)) { $missing['status'] = "VARCHAR(20) NOT NULL DEFAULT 'live'"; }
  if (!projects_has_price($pdo))  { $missing['price']  = "VARCHAR(40) NOT NULL DEFAULT ''"; }
  if (!projects_has_image($pdo))  { $missing['image']  = "VARCHAR(255) NOT NULL DEFAULT ''"; }
  $hasImage = !isset($missing['image']);
?>
<div class="topbar"><div><h1 class="page-title">Projects</h1><div class="page-sub">Cards in the projects slider — their picture, status and link.</div></div></div>
<?php if ($missing): ?>
<div class="panel" style="border-color:rgba(255,200,60,.3)">
  <div class="alert alert-warn" style="margin-bottom:1rem"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
    Your database is missing the
    <?php $n = count($missing); $i = 0; foreach (array_keys($missing) as $col): $i++; ?>
      <span class="pill"><?= e($col) ?></span><?= $i < $n - 1 ? ', ' : ($i === $n - 1 ? ' and ' : '') ?>
    <?php endforeach; ?>
    column<?= $n > 1 ? 's' : '' ?>, so
    <?= isset($missing['status']) || isset($missing['price']) ? '<strong>Sold / For&nbsp;Sale / Coming&nbsp;Soon</strong> and prices' : '' ?>
    <?= count($missing) > 1 && isset($missing['image']) && (isset($missing['status']) || isset($missing['price'])) ? ' and ' : '' ?>
    <?= isset($missing['image']) ? '<strong>card images</strong>' : '' ?>
    won't persist yet. Click the button to add <?= $n > 1 ? 'them' : 'it' ?> automatically:</div>
  <form method="post" action="<?= e(url('/admin/projects.php')) ?>" style="margin-bottom:1rem"><?= csrf_field() ?><input type="hidden" name="action" value="repair"><button class="btn btn-primary" type="submit">Upgrade the projects table now</button></form>
  <div class="small">If that fails (some hosts block <code>ALTER</code>), run <?= $n > 1 ? 'these' : 'this' ?> once in <strong>phpMyAdmin → your database → SQL</strong>:<br>
  <?php foreach ($missing as $col => $definition): ?>
    <code style="display:inline-block;margin-top:.4rem">ALTER TABLE projects ADD COLUMN <?= e($col) ?> <?= e($definition) ?>;</code><br>
  <?php endforeach; ?></div>
</div>
<?php endif; ?>

<div class="panel">
  <h2><?= $edit ? 'Edit project' : 'Add a project' ?></h2>
  <form method="post" enctype="multipart/form-data" action="<?= e(url('/admin/projects.php')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save" />
    <input type="hidden" name="id" value="<?= (int) $form['id'] ?>" />
    <div class="grid-2">
      <div class="field"><label>Title</label><input type="text" id="projTitle" name="title" value="<?= e($form['title']) ?>" required /></div>
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
    <div class="field"><label>Description</label><textarea name="description" id="projDesc"><?= e($form['description']) ?></textarea></div>
    <div class="grid-2">
      <div class="field"><label>Link URL</label><input type="text" id="projUrl" name="url" value="<?= e($form['url']) ?>" placeholder="https://… (leave as # to hide the link)" /></div>
      <div class="field"><label>Link label</label><input type="text" name="link_label" value="<?= e($form['link_label']) ?>" /></div>
    </div>

    <?php if ($hasImage): $curImg = media((string) ($form['image'] ?? '')); ?>
    <div class="field">
      <label>Card image</label>
      <div class="image-picker">
        <img class="image-preview" id="projPreview" data-preview-for="#projImage"
             src="<?= e($curImg) ?>" alt="" <?= $curImg === '' ? 'hidden' : '' ?> />
        <div class="image-picker-body">
          <input type="text" name="image" id="projImage" value="<?= e($form['image'] ?? '') ?>"
                 placeholder="Detect it, upload one, or paste a path" />
          <div class="actions" style="margin:.5rem 0 0">
            <button class="btn btn-ghost btn-sm" type="button"
                    data-detect="#projUrl" data-detect-target="#projImage"
                    data-detect-preview="#projPreview" data-detect-prefix="#projTitle"
                    data-detect-title="#projTitle" data-detect-desc="#projDesc"
                    data-detect-note="#projImageNote">Detect from the link</button>
            <button class="btn btn-ghost btn-sm" type="button"
                    data-clear-image="#projImage" data-clear-preview="#projPreview"
                    data-clear-note="#projImageNote">Clear</button>
          </div>
          <div class="detect-note" id="projImageNote"></div>
          <div class="hint" style="margin-top:.55rem">
            <strong>Detect</strong> reads the site in the Link URL box and fills in what it
            finds: its logo or preview image (Open Graph image first, then touch icon or
            favicon), its name, and its description. A copy of the image is saved here, so
            your page never depends on their server. Anything you have already typed is left
            alone — it only fills empty boxes. Leave the image blank when adding a project
            and the same lookup runs by itself on save. You can also upload your own image:
          </div>
          <input type="file" name="image_file" accept="image/*" style="margin-top:.5rem" />
        </div>
      </div>
    </div>
    <?php endif; ?>
    <div class="hint" style="margin-bottom:1rem"><strong>Live</strong> shows normally · <strong>For Sale</strong> shows the price + a Buy button (email/chat, set in Settings) · <strong>Not For Sale</strong> shows the card with a NOT FOR SALE badge, so visitors know it is yours to keep · <strong>Sold</strong> adds a SOLD ribbon &amp; disables the link · <strong>Coming Soon</strong> dims it with a SOON ribbon · <strong>Hidden</strong> removes it from the site.</div>
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
      <thead><tr><th>#</th><th>Image</th><th>Title</th><th>Tag</th><th>Status</th><th>Price</th><th style="text-align:right">Actions</th></tr></thead>
      <tbody>
        <?php foreach ($shown as $r): $rs = project_status_key($r['status'] ?? 'live'); $rp = trim((string) ($r['price'] ?? '')); ?>
        <tr>
          <td class="muted"><?= (int) $r['sort_order'] ?></td>
          <td><?php $ri = media((string) ($r['image'] ?? '')); ?><?php if ($ri !== ''): ?><img class="row-thumb" src="<?= e($ri) ?>" alt="" loading="lazy" /><?php else: ?><span class="muted">—</span><?php endif; ?></td>
          <td><strong><?= e($r['title']) ?></strong><br><span class="muted"><?= e(mb_strimwidth($r['description'], 0, 66, '…')) ?></span></td>
          <td><?php if ($r['tag']!==''): ?><span class="pill"><?= e($r['tag']) ?></span><?php else: ?><span class="muted">—</span><?php endif; ?></td>
          <td><span class="pill <?= $rs==='live'?'on':($rs==='hidden'?'off':'') ?>" style="<?= $rs==='sold'?'color:var(--amber);border-color:rgba(255,200,60,.35)':($rs==='coming_soon'?'color:var(--violet);border-color:rgba(167,139,250,.35)':($rs==='for_sale'?'color:var(--mint,#00ffb3);border-color:rgba(0,255,179,.35)':'')) ?>"><?= e($STATUSES[$rs][0]) ?></span></td>
          <td><?php if ($rp!=='' && ($rs==='for_sale'||$rs==='sold')): ?><span class="pill" style="color:var(--mint,#00ffb3);border-color:rgba(0,255,179,.3)"><?= e($rp) ?></span><?php else: ?><span class="muted">—</span><?php endif; ?></td>
          <td style="text-align:right">
            <div class="actions" style="justify-content:flex-end;flex-wrap:wrap">
              <?php
                // Quick status buttons (show the ones this project isn't already)
                $quick = ['live'=>'Live','for_sale'=>'Sale','not_for_sale'=>'Not for sale','sold'=>'Sold','hidden'=>'Hide'];
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
        <?php if (!$shown): ?><tr><td colspan="7" class="muted" style="text-align:center;padding:2rem"><?= $rows ? 'No projects with this status.' : 'No projects yet.' ?></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/includes/foot.php'; ?>
