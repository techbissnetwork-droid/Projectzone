<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/icons.php';
require_once __DIR__ . '/../includes/admin_layout.php';
require_installed('../install/');

$staff = require_staff();
require_staff_access($staff, 'users.php');
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if (!csrf_check((string)($_POST['csrf'] ?? ''))) {
        flash('Your session expired — please try again.', 'error');
    } elseif ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim((string)($_POST['name'] ?? ''));
        $email = trim(strtolower((string)($_POST['email'] ?? '')));

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('Enter a name and a valid email address.', 'error');
        } else {
            $dupe = $pdo->prepare('SELECT id FROM customers WHERE email = ? AND id != ?');
            $dupe->execute([$email, $id]);
            if ($dupe->fetch()) {
                flash('Another user already uses that email.', 'error');
            } elseif ($id > 0) {
                $pdo->prepare('UPDATE customers SET name=?, email=? WHERE id=?')->execute([$name, $email, $id]);
                flash('User updated.');
            } else {
                $pdo->prepare('INSERT INTO customers (name, email) VALUES (?,?)')->execute([$name, $email]);
                flash('User created.');
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('UPDATE businesses SET customer_id = NULL WHERE customer_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM customers WHERE id=?')->execute([$id]);
        flash('User removed. Any businesses they owned are now unassigned.');
    }
    header('Location: users.php');
    exit;
}

$editing = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM customers WHERE id=?');
    $stmt->execute([(int)$_GET['edit']]);
    $editing = $stmt->fetch() ?: null;
}

$users = $pdo->query(
    'SELECT c.*, (SELECT COUNT(*) FROM businesses b WHERE b.customer_id = c.id) AS business_count
     FROM customers c ORDER BY c.created_at DESC'
)->fetchAll();
$token = csrf_token();
?>
<!doctype html>
<html lang="en"<?= palette_attr() . logo_motion_attr() . ui_zoom_attr() ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Users — TECHBISS Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/style.css?v=<?= @filemtime(__DIR__ . '/../assets/style.css') ?: '1' ?>">
</head>
<body>
<?= admin_header($staff, 'users.php') ?>
<main class="admin-page">
  <?= admin_flash_html() ?>
  <div class="admin-toolbar">
    <div><h1 style="margin-bottom:4px;">Users</h1><p class="lede" style="margin-bottom:0;">Client login accounts — each user can own one or more businesses.</p></div>
    <?php if (!$editing): ?><button class="btn btn-primary" type="button" id="addBtn" onclick="toggleAddForm('add')"><?= ico('plus') ?> Add a user</button><?php endif; ?>
  </div>

  <div class="card admin-form-card" id="addCard"<?= $editing ? '' : ' hidden' ?>>
    <div class="card-head"><?= blob_icon($editing ? 'edit' : 'plus', 'sm', true) ?><h3><?= $editing ? 'Edit user' : 'Add a user' ?></h3></div>
    <form method="post">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="csrf" value="<?= e($token) ?>">
      <input type="hidden" name="id" value="<?= e((string)($editing['id'] ?? 0)) ?>">
      <div class="grid grid-2" style="gap:16px;">
        <div class="field"><label>Name</label><input name="name" required value="<?= e($editing['name'] ?? '') ?>"></div>
        <div class="field"><label>Email</label><input type="email" name="email" required value="<?= e($editing['email'] ?? '') ?>"></div>
      </div>
      <p style="font-size:.78rem;color:var(--ink-faint);margin:-6px 0 14px;">No password needed — they'll sign in with a one-time code emailed to this address.</p>
      <div class="flex gap-12">
        <button class="btn btn-primary" type="submit"><?= $editing ? 'Save changes' : 'Add user' ?></button>
        <?php if ($editing): ?><a href="users.php" class="btn btn-ghost">Cancel</a><?php else: ?><button type="button" class="btn btn-ghost" onclick="toggleAddForm('add')">Cancel</button><?php endif; ?>
      </div>
    </form>
  </div>

  <div class="card">
    <div class="table-wrap"><table><thead><tr><th>Name</th><th>Email</th><th>Businesses</th><th>Joined</th><th></th></tr></thead><tbody>
      <?php foreach ($users as $u): ?>
      <tr>
        <td style="font-weight:600;"><?= e($u['name']) ?></td>
        <td><?= e($u['email']) ?></td>
        <td><a class="card-link" href="businesses.php?owner=<?= (int)$u['id'] ?>"><?= (int)$u['business_count'] ?> <?= ico('arrow') ?></a></td>
        <td style="color:var(--ink-faint);"><?= e(time_ago($u['created_at'])) ?></td>
        <td class="admin-actions-cell">
          <a class="icon-btn" href="users.php?edit=<?= (int)$u['id'] ?>" aria-label="Edit"><?= ico('edit') ?></a>
          <form method="post" onsubmit="return confirm('Remove <?= e(addslashes($u['name'])) ?>? Their businesses will be unassigned, not deleted.');">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="csrf" value="<?= e($token) ?>">
            <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
            <button class="icon-btn danger" type="submit" aria-label="Delete"><?= ico('trash') ?></button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$users): ?><tr><td colspan="5" style="color:var(--ink-faint);">No users yet — add your first one above, or create one directly from a business.</td></tr><?php endif; ?>
    </tbody></table></div>
  </div>
</main>
<?= admin_bottomnav($staff, 'users.php') ?>
</body>
</html>
