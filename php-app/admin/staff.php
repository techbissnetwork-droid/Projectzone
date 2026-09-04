<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/icons.php';
require_once __DIR__ . '/../includes/admin_layout.php';
require_installed('../install/');

$staff = require_staff();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if (!csrf_check((string)($_POST['csrf'] ?? ''))) {
        flash('Your session expired — please try again.', 'error');
    } elseif ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim((string)($_POST['name'] ?? ''));
        $email = trim(strtolower((string)($_POST['email'] ?? '')));
        $role = trim((string)($_POST['role'] ?? '')) ?: 'Staff';
        $password = (string)($_POST['password'] ?? '');
        $initials = strtoupper(substr($name, 0, 1) . substr(strrchr($name, ' ') ?: '', 1, 1)) ?: 'ST';

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('Enter a name and a valid email address.', 'error');
        } elseif ($id === 0 && strlen($password) < 8) {
            flash('New staff accounts need a password of at least 8 characters.', 'error');
        } elseif ($password !== '' && strlen($password) < 8) {
            flash('Password must be at least 8 characters (leave blank to keep the current one).', 'error');
        } else {
            $dupe = $pdo->prepare('SELECT id FROM staff WHERE email = ? AND id != ?');
            $dupe->execute([$email, $id]);
            if ($dupe->fetch()) {
                flash('Another staff account already uses that email.', 'error');
            } elseif ($id > 0) {
                if ($password !== '') {
                    $stmt = $pdo->prepare('UPDATE staff SET name=?, email=?, role=?, initials=?, password_hash=? WHERE id=?');
                    $stmt->execute([$name, $email, $role, $initials, password_hash($password, PASSWORD_DEFAULT), $id]);
                } else {
                    $stmt = $pdo->prepare('UPDATE staff SET name=?, email=?, role=?, initials=? WHERE id=?');
                    $stmt->execute([$name, $email, $role, $initials, $id]);
                }
                flash('Staff account updated.');
            } else {
                $stmt = $pdo->prepare('INSERT INTO staff (name, email, role, initials, password_hash) VALUES (?,?,?,?,?)');
                $stmt->execute([$name, $email, $role, $initials, password_hash($password, PASSWORD_DEFAULT)]);
                flash('Staff account created.');
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $total = (int)$pdo->query('SELECT COUNT(*) FROM staff')->fetchColumn();
        if ($total <= 1) {
            flash('Can\'t delete the last remaining staff account.', 'error');
        } else {
            $pdo->prepare('UPDATE tickets SET assignee_staff_id = NULL WHERE assignee_staff_id = ?')->execute([$id]);
            $pdo->prepare('DELETE FROM staff WHERE id=?')->execute([$id]);
            flash('Staff account removed.');
        }
    }
    header('Location: staff.php');
    exit;
}

$editing = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM staff WHERE id=?');
    $stmt->execute([(int)$_GET['edit']]);
    $editing = $stmt->fetch() ?: null;
}

$staffList = $pdo->query(
    "SELECT s.*, (SELECT COUNT(*) FROM tickets t WHERE t.assignee_staff_id = s.id AND t.status != 'Closed') AS open_count
     FROM staff s ORDER BY s.id"
)->fetchAll();
$token = csrf_token();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Staff — TECHBISS Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/style.css?v=<?= @filemtime(__DIR__ . '/../assets/style.css') ?: '1' ?>">
</head>
<body>
<?= admin_header($staff, 'staff.php') ?>
<main class="admin-page">
  <?= admin_flash_html() ?>
  <div class="admin-toolbar">
    <div><h1 style="margin-bottom:4px;">Staff</h1><p class="lede" style="margin-bottom:0;">Who can sign in to this admin panel.</p></div>
  </div>

  <div class="card admin-form-card">
    <div class="card-head"><?= blob_icon($editing ? 'edit' : 'plus', 'sm', true) ?><h3><?= $editing ? 'Edit staff account' : 'Add a staff account' ?></h3></div>
    <form method="post">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="csrf" value="<?= e($token) ?>">
      <input type="hidden" name="id" value="<?= e((string)($editing['id'] ?? 0)) ?>">
      <div class="grid grid-2" style="gap:16px;">
        <div class="field"><label>Name</label><input name="name" required value="<?= e($editing['name'] ?? '') ?>"></div>
        <div class="field"><label>Email</label><input type="email" name="email" required value="<?= e($editing['email'] ?? '') ?>"></div>
      </div>
      <div class="grid grid-2" style="gap:16px;">
        <div class="field"><label>Role / title</label><input name="role" value="<?= e($editing['role'] ?? '') ?>" placeholder="e.g. Head of Design"></div>
        <div class="field"><label>Password <?= $editing ? '(leave blank to keep current)' : '' ?></label><input type="password" name="password" minlength="8" <?= $editing ? '' : 'required' ?>></div>
      </div>
      <div class="flex gap-12">
        <button class="btn btn-primary" type="submit"><?= $editing ? 'Save changes' : 'Add staff account' ?></button>
        <?php if ($editing): ?><a href="staff.php" class="btn btn-ghost">Cancel</a><?php endif; ?>
      </div>
    </form>
  </div>

  <div class="card">
    <div class="table-wrap"><table><thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Open tickets</th><th></th></tr></thead><tbody>
      <?php foreach ($staffList as $s): ?>
      <tr>
        <td style="font-weight:600;"><?= e($s['name']) ?></td>
        <td><?= e($s['email']) ?></td>
        <td><?= e($s['role']) ?></td>
        <td><?= (int)$s['open_count'] ?></td>
        <td class="admin-actions-cell">
          <a class="icon-btn" href="staff.php?edit=<?= (int)$s['id'] ?>" aria-label="Edit"><?= ico('edit') ?></a>
          <form method="post" onsubmit="return confirm('Remove <?= e(addslashes($s['name'])) ?>? Their open tickets will become unassigned.');">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="csrf" value="<?= e($token) ?>">
            <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
            <button class="icon-btn danger" type="submit" aria-label="Delete"><?= ico('trash') ?></button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody></table></div>
  </div>
</main>
<?= admin_bottomnav('staff.php') ?>
</body>
</html>
