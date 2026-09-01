<?php
/**
 * Refresh every project from its own website in one pass.
 *
 * The lookups run from the browser, one project at a time, so a slow site
 * cannot time the page out and you can watch it work. Nothing is written until
 * you press Apply — the table is a preview you can edit first, and any picture
 * fetched for a row you did not keep is deleted again.
 */
declare(strict_types=1);

$__page = 'projects';
$title = 'Refresh from the web';
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../includes/imagefetch.php';
require_login();

$pdo = db();

/** Does the projects table have this column? (Adds it if it can.) */
function detect_all_has(PDO $pdo, string $col, string $definition): bool
{
    if (function_exists('ensure_column')) {
        ensure_column($pdo, 'projects', $col, $definition);
    }
    try {
        $pdo->query("SELECT $col FROM projects LIMIT 1");
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

$HAS_IMAGE = detect_all_has($pdo, 'image', "VARCHAR(255) NOT NULL DEFAULT ''");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $apply    = array_map('intval', (array) ($_POST['apply'] ?? []));
    $titles   = (array) ($_POST['title'] ?? []);
    $descs    = (array) ($_POST['description'] ?? []);
    $images   = (array) ($_POST['image'] ?? []);
    $fetched  = (array) ($_POST['fetched'] ?? []);   // every picture this run downloaded
    $changed  = 0;

    foreach ($apply as $id) {
        if ($id <= 0) { continue; }

        $cols = [];
        $vals = [];

        $newTitle = clip((string) ($titles[$id] ?? ''), 120);
        if ($newTitle !== '') { $cols[] = 'title'; $vals[] = $newTitle; }

        $newDesc = trim((string) ($descs[$id] ?? ''));
        if ($newDesc !== '') { $cols[] = 'description'; $vals[] = $newDesc; }

        $newImage = clip((string) ($images[$id] ?? ''), 255);
        if ($HAS_IMAGE && $newImage !== '') {
            // Replacing a stored picture? Tidy the old file away.
            $prev = $pdo->prepare("SELECT image FROM projects WHERE id = ?");
            $prev->execute([$id]);
            $old = trim((string) ($prev->fetchColumn() ?: ''));
            if ($old !== '' && $old !== $newImage) { delete_stored_image($old); }
            $cols[] = 'image';
            $vals[] = $newImage;
        }

        if (!$cols) { continue; }
        $set = implode(',', array_map(static fn($c) => "$c=?", $cols));
        $pdo->prepare("UPDATE projects SET $set WHERE id=?")->execute([...$vals, $id]);
        $changed++;
    }

    // Anything downloaded for a row you did not keep is removed again, so a
    // preview you walk away from leaves nothing behind.
    $kept = [];
    foreach ($apply as $id) {
        $k = trim((string) ($images[$id] ?? ''));
        if ($k !== '') { $kept[$k] = true; }
    }
    foreach ($fetched as $path) {
        $path = trim((string) $path);
        if ($path !== '' && !isset($kept[$path])) { delete_stored_image($path); }
    }

    flash($changed > 0
        ? $changed . ' ' . ($changed === 1 ? 'project' : 'projects') . ' updated from their websites.'
        : 'Nothing was changed.');
    touch_content();
    header('Location: ' . url('/admin/projects.php'));
    exit;
}

require_once __DIR__ . '/includes/head.php';

$rows = $pdo->query("SELECT * FROM projects ORDER BY sort_order, id")->fetchAll();
// Only projects that point at a real website can be looked up.
$rows = array_values(array_filter($rows, static fn($r) => preg_match('#^https?://#i', (string) $r['url'])));
?>
<div class="topbar">
  <div>
    <h1 class="page-title">Refresh from the web</h1>
    <div class="page-sub">Read every project's own site for its name, description and logo — then choose what to keep.</div>
  </div>
  <a class="btn btn-ghost" href="<?= e(url('/admin/projects.php')) ?>">Back to projects</a>
</div>

<?php if (!$rows): ?>
<div class="panel">
  <h2>Nothing to look up</h2>
  <p class="small">No project has a full <code>https://…</code> address yet. Add one to a project and it will show up here.</p>
</div>
<?php else: ?>

<div class="panel" style="border-color:rgba(0,255,179,.28)">
  <h2>How this works</h2>
  <div class="hint" style="margin-bottom:1rem">
    Press the button and each site is read in turn — its Open Graph image, name and description.
    <strong>Nothing is saved yet.</strong> The rows below fill in as it goes, and you can edit any of
    them before applying. Tick only the projects you want to change; a picture fetched for a row you
    do not keep is deleted again.
  </div>
  <div class="actions">
    <button class="btn btn-primary" type="button" id="detectAllStart"
            data-detect-all="<?= (int) count($rows) ?>">Read all <?= (int) count($rows) ?> websites</button>
    <button class="btn btn-ghost btn-sm" type="button" id="detectAllTickAll">Tick everything found</button>
    <button class="btn btn-ghost btn-sm" type="button" id="detectAllTickNone">Untick all</button>
  </div>
  <div class="detect-note" id="detectAllProgress">Ready when you are.</div>
</div>

<form method="post" action="<?= e(url('/admin/detect-all.php')) ?>" id="detectAllForm">
  <?= csrf_field() ?>

  <?php foreach ($rows as $r):
    $id  = (int) $r['id'];
    $img = media((string) ($r['image'] ?? ''));
  ?>
  <div class="panel detect-row" data-project="<?= $id ?>" data-url="<?= e((string) $r['url']) ?>">
    <div class="row-inline" style="justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap">
      <label class="row-inline" style="gap:.6rem;cursor:pointer">
        <input type="checkbox" name="apply[]" value="<?= $id ?>" class="detect-apply" disabled />
        <span><strong><?= e($r['title']) ?></strong>
          <span class="muted" style="font-size:.75rem">— <?= e(url_origin((string) $r['url'])) ?></span></span>
      </label>
      <span class="detect-note detect-status">waiting</span>
    </div>

    <div class="grid-2" style="margin-top:1rem">
      <div class="field">
        <label>Name</label>
        <input type="text" name="title[<?= $id ?>]" value="<?= e($r['title']) ?>" class="detect-title" />
      </div>
      <div class="field">
        <label>Picture</label>
        <div class="image-picker">
          <img class="image-preview" src="<?= e($img) ?>" alt="" <?= $img === '' ? 'hidden' : '' ?> />
          <div class="image-picker-body">
            <input type="hidden" name="image[<?= $id ?>]" value="" class="detect-image" />
            <div class="hint">The one on the left is what the card shows now. Anything found replaces it only if you tick this row.</div>
          </div>
        </div>
      </div>
    </div>

    <div class="field">
      <label>Description</label>
      <textarea name="description[<?= $id ?>]" rows="3" class="detect-desc"><?= e((string) $r['description']) ?></textarea>
    </div>
  </div>
  <?php endforeach; ?>

  <div class="panel">
    <div class="actions">
      <button class="btn btn-primary" type="submit">Apply the ticked projects</button>
      <a class="btn btn-ghost" href="<?= e(url('/admin/projects.php')) ?>">Cancel</a>
    </div>
    <div class="hint" style="margin-top:.75rem">Only ticked rows are written, and only the boxes that have something in them.</div>
  </div>
</form>
<?php endif; ?>

<?php require __DIR__ . '/includes/foot.php'; ?>
