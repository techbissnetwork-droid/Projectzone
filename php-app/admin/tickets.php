<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/icons.php';
require_once __DIR__ . '/../includes/admin_layout.php';
require_installed('../install/');

$staff = require_staff();
require_staff_access($staff, 'tickets.php');
$pdo = db();

$PRIORITIES = ['Low', 'Normal', 'High'];
$STATUSES = ['Open', 'In progress', 'Closed'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if (!csrf_check((string)($_POST['csrf'] ?? ''))) {
        flash('Your session expired — please try again.', 'error');
    } elseif ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $businessId = (int)($_POST['business_id'] ?? 0);
        $title = trim((string)($_POST['title'] ?? ''));
        $priority = in_array($_POST['priority'] ?? '', $PRIORITIES, true) ? $_POST['priority'] : 'Normal';
        $status = in_array($_POST['status'] ?? '', $STATUSES, true) ? $_POST['status'] : 'Open';
        $assigneeId = (int)($_POST['assignee_staff_id'] ?? 0) ?: null;
        if ($title === '' || $businessId <= 0) {
            flash('Pick a business and enter a title.', 'error');
        } elseif ($id > 0) {
            $stmt = $pdo->prepare('UPDATE tickets SET business_id=?, title=?, priority=?, status=?, assignee_staff_id=? WHERE id=?');
            $stmt->execute([$businessId, $title, $priority, $status, $assigneeId, $id]);
            flash('Ticket updated.');
        } else {
            $stmt = $pdo->prepare('INSERT INTO tickets (business_id, title, priority, status, assignee_staff_id) VALUES (?,?,?,?,?)');
            $stmt->execute([$businessId, $title, $priority, $status, $assigneeId]);
            flash('Ticket created.');
        }
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM tickets WHERE id=?')->execute([(int)($_POST['id'] ?? 0)]);
        flash('Ticket removed.');
    }
    header('Location: tickets.php');
    exit;
}

$editing = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM tickets WHERE id=?');
    $stmt->execute([(int)$_GET['edit']]);
    $editing = $stmt->fetch() ?: null;
}

$businesses = $pdo->query('SELECT id, name FROM businesses ORDER BY name')->fetchAll();
$staffList = $pdo->query('SELECT id, name FROM staff ORDER BY name')->fetchAll();
$tickets = $pdo->query(
    "SELECT t.*, b.name AS business_name, s.name AS assignee_name, s.initials AS assignee_initials
     FROM tickets t
     JOIN businesses b ON b.id = t.business_id
     LEFT JOIN staff s ON s.id = t.assignee_staff_id
     ORDER BY FIELD(t.status,'Open','In progress','Closed'), FIELD(t.priority,'High','Normal','Low'), t.created_at DESC"
)->fetchAll();
$priTone = ['High' => 'danger', 'Normal' => 'warning', 'Low' => 'success'];
$statusTone = ['Open' => 'danger', 'In progress' => 'warning', 'Closed' => 'success'];
$token = csrf_token();
?>
<!doctype html>
<html lang="en"<?= palette_attr() . logo_motion_attr() ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Tickets — TECHBISS Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/style.css?v=<?= @filemtime(__DIR__ . '/../assets/style.css') ?: '1' ?>">
</head>
<body>
<?= admin_header($staff, 'tickets.php') ?>
<main class="admin-page">
  <?= admin_flash_html() ?>
  <div class="admin-toolbar">
    <div><h1 style="margin-bottom:4px;">Support tickets</h1><p class="lede" style="margin-bottom:0;">Every open and closed ticket, across every client.</p></div>
  </div>

  <?php if (!$businesses): ?>
    <p class="badge warning" style="margin-bottom:20px;">Add a business first — tickets belong to a business.</p>
  <?php else: ?>
  <div class="card admin-form-card">
    <div class="card-head"><?= blob_icon($editing ? 'edit' : 'plus', 'sm', true) ?><h3><?= $editing ? 'Edit ticket' : 'New ticket' ?></h3></div>
    <form method="post">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="csrf" value="<?= e($token) ?>">
      <input type="hidden" name="id" value="<?= e((string)($editing['id'] ?? 0)) ?>">
      <div class="field"><label>Title</label><input name="title" required value="<?= e($editing['title'] ?? '') ?>"></div>
      <div class="grid grid-4" style="gap:16px;">
        <div class="field"><label>Business</label><select name="business_id">
          <?php foreach ($businesses as $b): ?><option value="<?= (int)$b['id'] ?>" <?= (int)($editing['business_id'] ?? 0) === (int)$b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option><?php endforeach; ?>
        </select></div>
        <div class="field"><label>Priority</label><select name="priority">
          <?php foreach ($PRIORITIES as $p): ?><option value="<?= e($p) ?>" <?= ($editing['priority'] ?? '') === $p ? 'selected' : '' ?>><?= e($p) ?></option><?php endforeach; ?>
        </select></div>
        <div class="field"><label>Status</label><select name="status">
          <?php foreach ($STATUSES as $s): ?><option value="<?= e($s) ?>" <?= ($editing['status'] ?? '') === $s ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?>
        </select></div>
        <div class="field"><label>Assignee</label><select name="assignee_staff_id">
          <option value="">Unassigned</option>
          <?php foreach ($staffList as $s): ?><option value="<?= (int)$s['id'] ?>" <?= (int)($editing['assignee_staff_id'] ?? 0) === (int)$s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; ?>
        </select></div>
      </div>
      <div class="flex gap-12">
        <button class="btn btn-primary" type="submit"><?= $editing ? 'Save changes' : 'Create ticket' ?></button>
        <?php if ($editing): ?><a href="tickets.php" class="btn btn-ghost">Cancel</a><?php endif; ?>
      </div>
    </form>
  </div>
  <?php endif; ?>

  <div class="card">
    <div class="table-wrap"><table><thead><tr><th>Business</th><th>Title</th><th>Priority</th><th>Status</th><th>Assignee</th><th></th></tr></thead><tbody>
      <?php foreach ($tickets as $t): ?>
      <tr>
        <td style="font-weight:600;"><?= e($t['business_name']) ?></td>
        <td><?= e($t['title']) ?></td>
        <td><span class="badge <?= $priTone[$t['priority']] ?? '' ?>"><?= e($t['priority']) ?></span></td>
        <td><span class="badge <?= $statusTone[$t['status']] ?? '' ?>"><?= e($t['status']) ?></span></td>
        <td style="color:var(--ink-faint);"><?= e($t['assignee_name'] ?? 'Unassigned') ?></td>
        <td class="admin-actions-cell">
          <a class="icon-btn" href="tickets.php?edit=<?= (int)$t['id'] ?>" aria-label="Edit"><?= ico('edit') ?></a>
          <form method="post" onsubmit="return confirm('Delete this ticket?');">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="csrf" value="<?= e($token) ?>">
            <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
            <button class="icon-btn danger" type="submit" aria-label="Delete"><?= ico('trash') ?></button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$tickets): ?><tr><td colspan="6" style="color:var(--ink-faint);">No tickets yet.</td></tr><?php endif; ?>
    </tbody></table></div>
  </div>
</main>
<?= admin_bottomnav($staff, 'tickets.php') ?>
</body>
</html>
