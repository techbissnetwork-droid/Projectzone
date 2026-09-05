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
$TYPES = ['project_task' => 'Project task', 'new_project' => 'New project request'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if (!csrf_check((string)($_POST['csrf'] ?? ''))) {
        flash('Your session expired — please try again.', 'error');
    } elseif ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $businessId = (int)($_POST['business_id'] ?? 0);
        $title = trim((string)($_POST['title'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $type = array_key_exists($_POST['type'] ?? '', $TYPES) ? $_POST['type'] : 'project_task';
        $priority = in_array($_POST['priority'] ?? '', $PRIORITIES, true) ? $_POST['priority'] : 'Normal';
        $status = in_array($_POST['status'] ?? '', $STATUSES, true) ? $_POST['status'] : 'Open';
        $assigneeId = (int)($_POST['assignee_staff_id'] ?? 0) ?: null;

        // A project_task ticket with no project_id never reaches the
        // customer: api/dashboard.php only shows open tickets that name a
        // project. Admin had no way to set it, so every task raised here
        // was invisible to the client it was about.
        $projectId = (int)($_POST['project_id'] ?? 0) ?: null;
        if ($projectId !== null) {
            $owns = $pdo->prepare('SELECT id FROM projects WHERE id = ? AND business_id = ?');
            $owns->execute([$projectId, $businessId]);
            if (!$owns->fetch()) {
                $projectId = null;
            }
        }
        if ($type === 'project_task' && $projectId === null) {
            $projectId = null;
        }

        if ($title === '' || $businessId <= 0) {
            flash('Pick a business and enter a title.', 'error');
        } elseif ($id > 0) {
            $stmt = $pdo->prepare('UPDATE tickets SET business_id=?, project_id=?, title=?, description=?, type=?, priority=?, status=?, assignee_staff_id=? WHERE id=?');
            $stmt->execute([$businessId, $projectId, $title, $description !== '' ? $description : null, $type, $priority, $status, $assigneeId, $id]);
            touch_business_activity($businessId);
            flash('Ticket updated.');
        } else {
            $stmt = $pdo->prepare('INSERT INTO tickets (business_id, project_id, title, description, type, priority, status, assignee_staff_id) VALUES (?,?,?,?,?,?,?,?)');
            $stmt->execute([$businessId, $projectId, $title, $description !== '' ? $description : null, $type, $priority, $status, $assigneeId]);
            touch_business_activity($businessId);
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
$projectOptions = $pdo->query('SELECT id, business_id, title FROM projects ORDER BY title')->fetchAll();
$tickets = $pdo->query(
    "SELECT t.*, b.name AS business_name, s.name AS assignee_name, s.initials AS assignee_initials,
            p.title AS project_title
     FROM tickets t
     JOIN businesses b ON b.id = t.business_id
     LEFT JOIN staff s ON s.id = t.assignee_staff_id
     LEFT JOIN projects p ON p.id = t.project_id
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
<meta name="viewport" content="width=device-width, initial-scale=<?= ui_zoom_scale() ?>">
<title>Tickets — TECHBISS Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/style.css?v=<?= asset_version() ?>">
<?= ui_zoom_style() ?>
</head>
<body>
<?= admin_header($staff, 'tickets.php') ?>
<main class="admin-page">
  <?= admin_flash_html() ?>
  <div class="admin-toolbar">
    <div><h1 style="margin-bottom:4px;">Support tickets</h1><p class="lede" style="margin-bottom:0;">Every open and closed ticket, across every client.</p></div>
    <?php if (!$editing && $businesses): ?><button class="btn btn-primary" type="button" id="addBtn" onclick="toggleAddForm('add')"><?= ico('plus') ?> New ticket</button><?php endif; ?>
  </div>

  <?php if (!$businesses): ?>
    <p class="badge warning" style="margin-bottom:20px;">Add a business first — tickets belong to a business.</p>
  <?php else: ?>
  <div class="card admin-form-card" id="addCard"<?= $editing ? '' : ' hidden' ?>>
    <div class="card-head"><?= blob_icon($editing ? 'edit' : 'plus', 'sm', true) ?><h3><?= $editing ? 'Edit ticket' : 'New ticket' ?></h3></div>
    <form method="post">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="csrf" value="<?= e($token) ?>">
      <input type="hidden" name="id" value="<?= e((string)($editing['id'] ?? 0)) ?>">
      <div class="field"><label>Title</label><input name="title" required value="<?= e($editing['title'] ?? '') ?>"></div>
      <div class="field"><label>Description <small style="font-weight:400;color:var(--ink-faint);">(optional)</small></label><textarea name="description"><?= e($editing['description'] ?? '') ?></textarea></div>
      <div class="grid grid-4" style="gap:16px;">
        <div class="field"><label>Business</label><select name="business_id" required>
          <?php // No silent default: picking the alphabetically-first client by accident files the ticket against the wrong account. ?>
          <?php if (!$editing): ?><option value="">Choose a business…</option><?php endif; ?>
          <?php foreach ($businesses as $b): ?><option value="<?= (int)$b['id'] ?>" <?= (int)($editing['business_id'] ?? 0) === (int)$b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option><?php endforeach; ?>
        </select></div>
        <div class="field"><label>Type</label><select name="type">
          <?php foreach ($TYPES as $val => $label): ?><option value="<?= e($val) ?>" <?= ($editing['type'] ?? 'project_task') === $val ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
        </select></div>
        <div class="field"><label>Priority</label><select name="priority">
          <?php foreach ($PRIORITIES as $p): ?><option value="<?= e($p) ?>" <?= ($editing['priority'] ?? '') === $p ? 'selected' : '' ?>><?= e($p) ?></option><?php endforeach; ?>
        </select></div>
        <div class="field"><label>Status</label><select name="status">
          <?php foreach ($STATUSES as $s): ?><option value="<?= e($s) ?>" <?= ($editing['status'] ?? '') === $s ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?>
        </select></div>
      </div>
      <div class="field"><label>Project <small style="font-weight:400;color:var(--ink-faint);">(a project task only reaches the customer's dashboard once it names a project)</small></label>
        <select name="project_id" id="ticketProject">
          <option value="">Not tied to a project</option>
          <?php foreach ($projectOptions as $po): ?>
          <option value="<?= (int)$po['id'] ?>" data-biz="<?= (int)$po['business_id'] ?>" <?= (int)($editing['project_id'] ?? 0) === (int)$po['id'] ? 'selected' : '' ?>><?= e($po['title']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field"><label>Assignee</label><select name="assignee_staff_id">
        <option value="">Unassigned</option>
        <?php foreach ($staffList as $s): ?><option value="<?= (int)$s['id'] ?>" <?= (int)($editing['assignee_staff_id'] ?? 0) === (int)$s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; ?>
      </select></div>
      <div class="flex gap-12">
        <button class="btn btn-primary" type="submit"><?= $editing ? 'Save changes' : 'Create ticket' ?></button>
        <?php if ($editing): ?><a href="tickets.php" class="btn btn-ghost">Cancel</a><?php else: ?><button type="button" class="btn btn-ghost" onclick="toggleAddForm('add')">Cancel</button><?php endif; ?>
      </div>
    </form>
  </div>
  <?php endif; ?>

  <div class="card">
    <div class="table-wrap"><table><thead><tr><th>Business</th><th>Title</th><th>Type</th><th>Priority</th><th>Status</th><th>Assignee</th><th></th></tr></thead><tbody>
      <?php foreach ($tickets as $t): ?>
      <tr>
        <td style="font-weight:600;"><?= e($t['business_name']) ?></td>
        <td><?= e($t['title']) ?><?= $t['description'] ? '<br><span style="font-weight:400;color:var(--ink-faint);font-size:.82rem;">' . e(mb_strimwidth($t['description'], 0, 80, '…')) . '</span>' : '' ?></td>
        <td>
          <?php if (($t['type'] ?? 'project_task') === 'new_project'): ?>
            <span class="badge">New project</span>
            <?php if ($t['project_id']): ?>
              <br><a class="card-link" style="font-size:.78rem;" href="projects.php?business=<?= (int)$t['business_id'] ?>&edit=<?= (int)$t['project_id'] ?>"><?= e($t['project_title']) ?> <?= ico('arrow') ?></a>
            <?php elseif ($t['status'] !== 'Closed'): ?>
              <br><a class="card-link" style="font-size:.78rem;" href="projects.php?business=<?= (int)$t['business_id'] ?>&title=<?= urlencode($t['title']) ?>&from_ticket=<?= (int)$t['id'] ?>">Create project <?= ico('arrow') ?></a>
            <?php endif; ?>
          <?php else: ?>
            <span class="badge">Project task</span>
            <?php if ($t['project_id']): ?>
              <br><a class="card-link" style="font-size:.78rem;" href="projects.php?business=<?= (int)$t['business_id'] ?>&edit=<?= (int)$t['project_id'] ?>"><?= e($t['project_title'] ?? 'Open project') ?> <?= ico('arrow') ?></a>
            <?php endif; ?>
          <?php endif; ?>
        </td>
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
      <?php if (!$tickets): ?><tr><td colspan="7" style="color:var(--ink-faint);">No tickets yet.</td></tr><?php endif; ?>
    </tbody></table></div>
  </div>
  <script>
  /* Only offer projects that belong to the business currently selected. */
  (function(){
    var biz = document.querySelector('select[name="business_id"]');
    var proj = document.getElementById('ticketProject');
    if(!biz || !proj) return;
    var all = Array.prototype.slice.call(proj.options);
    function sync(){
      var want = biz.value;
      all.forEach(function(o){
        if(!o.value){ return; }
        var match = o.dataset.biz === want;
        o.hidden = !match;
        o.disabled = !match;
        if(!match && o.selected){ proj.value = ''; }
      });
    }
    biz.addEventListener('change', sync);
    sync();
  })();
  </script>
</main>
<?= admin_bottomnav($staff, 'tickets.php') ?>
</body>
</html>
