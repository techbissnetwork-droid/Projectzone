<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/icons.php';
require_once __DIR__ . '/../includes/admin_layout.php';
require_installed('../install/');

$staff = require_staff();
require_staff_access($staff, 'businesses.php');
$pdo = db();

$STATUSES = ['Planning', 'In progress', 'Live', 'On hold'];
$statusTone = ['Planning' => '', 'In progress' => 'warning', 'Live' => 'success', 'On hold' => 'danger'];
$EXPIRY_FIELDS = [
    'domain_expires_at' => 'Domain',
    'hosting_expires_at' => 'Hosting',
    'ssl_expires_at' => 'SSL',
    'email_expires_at' => 'Email',
];

$businessId = (int)($_GET['business'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if (!csrf_check((string)($_POST['csrf'] ?? ''))) {
        flash('Your session expired — please try again.', 'error');
    } elseif ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $bizId = (int)($_POST['business_id'] ?? 0);
        $title = trim((string)($_POST['title'] ?? ''));
        $status = in_array($_POST['status'] ?? '', $STATUSES, true) ? $_POST['status'] : $STATUSES[0];
        $progress = max(0, min(100, (int)($_POST['progress_pct'] ?? 0)));
        $domain = trim((string)($_POST['domain'] ?? ''));
        $notes = trim((string)($_POST['notes'] ?? ''));
        $portfolioVisible = isset($_POST['portfolio_visible']) ? 1 : 0;
        $dates = [];
        foreach ($EXPIRY_FIELDS as $field => $label) {
            $val = trim((string)($_POST[$field] ?? ''));
            $dates[$field] = $val !== '' ? $val : null;
        }

        if ($title === '' || $bizId === 0) {
            flash('A project title is required.', 'error');
        } else {
            if ($id > 0) {
                $stmt = $pdo->prepare(
                    'UPDATE projects SET title=?, status=?, progress_pct=?, domain=?,
                     domain_expires_at=?, hosting_expires_at=?, ssl_expires_at=?, email_expires_at=?,
                     notes=?, portfolio_visible=? WHERE id=?'
                );
                $stmt->execute([
                    $title, $status, $progress, $domain,
                    $dates['domain_expires_at'], $dates['hosting_expires_at'], $dates['ssl_expires_at'], $dates['email_expires_at'],
                    $notes, $portfolioVisible, $id,
                ]);
                flash('Project updated.');
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO projects (business_id, title, status, progress_pct, domain,
                     domain_expires_at, hosting_expires_at, ssl_expires_at, email_expires_at, notes, portfolio_visible)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?)'
                );
                $stmt->execute([
                    $bizId, $title, $status, $progress, $domain,
                    $dates['domain_expires_at'], $dates['hosting_expires_at'], $dates['ssl_expires_at'], $dates['email_expires_at'],
                    $notes, $portfolioVisible,
                ]);
                flash('Project added.');
            }
            $businessId = $bizId;
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $businessId = (int)($_POST['business_id'] ?? 0);
        $pdo->prepare('DELETE FROM projects WHERE id=?')->execute([$id]);
        flash('Project removed.');
    }
    header('Location: projects.php' . ($businessId ? '?business=' . $businessId : ''));
    exit;
}

$business = null;
if ($businessId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM businesses WHERE id = ?');
    $stmt->execute([$businessId]);
    $business = $stmt->fetch() ?: null;
}
if (!$business) {
    header('Location: businesses.php');
    exit;
}

$editing = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM projects WHERE id=? AND business_id=?');
    $stmt->execute([(int)$_GET['edit'], $businessId]);
    $editing = $stmt->fetch() ?: null;
}

$projects = $pdo->prepare('SELECT * FROM projects WHERE business_id=? ORDER BY created_at DESC');
$projects->execute([$businessId]);
$projects = $projects->fetchAll();

function days_until(?string $date): ?int
{
    if (!$date) {
        return null;
    }
    return (int)floor((strtotime($date) - strtotime(date('Y-m-d'))) / 86400);
}

$token = csrf_token();
?>
<!doctype html>
<html lang="en"<?= palette_attr() ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Projects — TECHBISS Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/style.css?v=<?= @filemtime(__DIR__ . '/../assets/style.css') ?: '1' ?>">
<style>.expiry-chip{ display:inline-flex; align-items:center; gap:4px; padding:3px 9px; border-radius:var(--r-full); font-size:.74rem; font-weight:600; background:var(--surface-2); }
.expiry-chip.soon{ background:rgba(217,154,43,.15); color:var(--warning); }
.expiry-chip.overdue{ background:rgba(224,96,74,.15); color:var(--danger); }</style>
</head>
<body>
<?= admin_header($staff, 'businesses.php') ?>
<main class="admin-page">
  <?= admin_flash_html() ?>
  <div class="admin-toolbar">
    <div>
      <a href="businesses.php" class="card-link" style="margin-bottom:8px;"><?= ico('arrow') ?> Back to Businesses</a>
      <h1 style="margin-bottom:4px;">Projects — <?= e($business['name']) ?></h1>
      <p class="lede" style="margin-bottom:0;">What's being built or maintained for this client, and what's near renewal.</p>
    </div>
  </div>

  <div class="card admin-form-card">
    <div class="card-head"><?= blob_icon($editing ? 'edit' : 'plus', 'sm', true) ?><h3><?= $editing ? 'Edit project' : 'Add a project' ?></h3></div>
    <form method="post">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="csrf" value="<?= e($token) ?>">
      <input type="hidden" name="id" value="<?= e((string)($editing['id'] ?? 0)) ?>">
      <input type="hidden" name="business_id" value="<?= (int)$businessId ?>">
      <div class="grid grid-2" style="gap:16px;">
        <div class="field"><label>Project title</label><input name="title" required value="<?= e($editing['title'] ?? '') ?>" placeholder="e.g. Website redesign"></div>
        <div class="field"><label>Domain</label><input name="domain" value="<?= e($editing['domain'] ?? '') ?>" placeholder="example.com"></div>
      </div>
      <div class="grid grid-2" style="gap:16px;">
        <div class="field"><label>Status</label><select name="status">
          <?php foreach ($STATUSES as $s): ?><option value="<?= e($s) ?>" <?= ($editing['status'] ?? '') === $s ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?>
        </select></div>
        <div class="field"><label>Progress (%)</label><input type="number" min="0" max="100" name="progress_pct" value="<?= e((string)($editing['progress_pct'] ?? 0)) ?>"></div>
      </div>
      <div class="grid grid-2" style="gap:16px;">
        <?php foreach ($EXPIRY_FIELDS as $field => $label): ?>
        <div class="field"><label><?= e($label) ?> expires</label><input type="date" name="<?= e($field) ?>" value="<?= e($editing[$field] ?? '') ?>"></div>
        <?php endforeach; ?>
      </div>
      <div class="field"><label>Notes <small style="font-weight:400;color:var(--ink-faint);">(visible to the client on their dashboard)</small></label><textarea name="notes"><?= e($editing['notes'] ?? '') ?></textarea></div>
      <label class="flex items-center gap-8" style="font-size:.85rem;margin-bottom:14px;"><input type="checkbox" name="portfolio_visible" <?= !empty($editing['portfolio_visible']) ? 'checked' : '' ?>> Show this project in the public portfolio once it's live</label>
      <div class="flex gap-12">
        <button class="btn btn-primary" type="submit"><?= $editing ? 'Save changes' : 'Add project' ?></button>
        <?php if ($editing): ?><a href="projects.php?business=<?= (int)$businessId ?>" class="btn btn-ghost">Cancel</a><?php endif; ?>
      </div>
    </form>
  </div>

  <div class="card">
    <div class="table-wrap"><table><thead><tr><th>Project</th><th>Status</th><th>Progress</th><th>Renewals</th><th></th></tr></thead><tbody>
      <?php foreach ($projects as $p): ?>
      <tr>
        <td style="font-weight:600;"><?= e($p['title']) ?><?= $p['domain'] ? '<br><span style="font-weight:400;color:var(--ink-faint);font-size:.82rem;">' . e($p['domain']) . '</span>' : '' ?></td>
        <td><span class="badge <?= $statusTone[$p['status']] ?? '' ?>"><?= e($p['status']) ?></span></td>
        <td style="min-width:120px;"><div class="progress-track"><div class="progress-fill" style="width:<?= (int)$p['progress_pct'] ?>%;"></div></div></td>
        <td>
          <?php foreach ($EXPIRY_FIELDS as $field => $label): $d = days_until($p[$field] ?? null); if ($d === null) continue; ?>
            <span class="expiry-chip <?= $d < 0 ? 'overdue' : ($d <= 30 ? 'soon' : '') ?>"><?= e($label) ?>: <?= $d < 0 ? 'overdue' : $d . 'd' ?></span>
          <?php endforeach; ?>
        </td>
        <td class="admin-actions-cell">
          <a class="icon-btn" href="projects.php?business=<?= (int)$businessId ?>&edit=<?= (int)$p['id'] ?>" aria-label="Edit"><?= ico('edit') ?></a>
          <form method="post" onsubmit="return confirm('Remove this project?');">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="csrf" value="<?= e($token) ?>">
            <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
            <input type="hidden" name="business_id" value="<?= (int)$businessId ?>">
            <button class="icon-btn danger" type="submit" aria-label="Delete"><?= ico('trash') ?></button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$projects): ?><tr><td colspan="5" style="color:var(--ink-faint);">No projects yet — add the first one above.</td></tr><?php endif; ?>
    </tbody></table></div>
  </div>
</main>
<?= admin_bottomnav($staff, 'businesses.php') ?>
</body>
</html>
