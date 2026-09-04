<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/icons.php';
require_once __DIR__ . '/../includes/admin_layout.php';
require_installed('../install/');

$staff = require_staff();
require_staff_access($staff, 'businesses.php');
$pdo = db();

$PLANS = ['Starter', 'Growth', 'App + Web', 'Scale'];
$STATUSES = ['Active', 'Trial', 'Past due'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if (!csrf_check((string)($_POST['csrf'] ?? ''))) {
        flash('Your session expired — please try again.', 'error');
    } elseif ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim((string)($_POST['name'] ?? ''));
        $sector = trim((string)($_POST['sector'] ?? ''));
        $plan = in_array($_POST['plan'] ?? '', $PLANS, true) ? $_POST['plan'] : $PLANS[0];
        $status = in_array($_POST['status'] ?? '', $STATUSES, true) ? $_POST['status'] : $STATUSES[0];
        $mrr = max(0, (float)($_POST['mrr'] ?? 0));
        $contactEmail = trim((string)($_POST['contact_email'] ?? ''));
        $contactPhone = trim((string)($_POST['contact_phone'] ?? ''));
        $loginEmail = trim(strtolower((string)($_POST['login_email'] ?? '')));
        $loginPassword = (string)($_POST['login_password'] ?? '');

        $errors = [];
        if ($name === '' || $sector === '') {
            $errors[] = 'Business name and sector are required.';
        }
        if ($contactEmail !== '' && !filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'That contact email doesn\'t look valid.';
        }
        if ($loginEmail !== '' && !filter_var($loginEmail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'That client login email doesn\'t look valid.';
        }

        $existingCustomerId = null;
        if ($id > 0) {
            $row = $pdo->prepare('SELECT customer_id FROM businesses WHERE id = ?');
            $row->execute([$id]);
            $existingCustomerId = $row->fetchColumn() ?: null;
        }
        if ($loginEmail !== '' && !$errors) {
            $dupe = $pdo->prepare('SELECT id FROM customers WHERE email = ? AND id != ?');
            $dupe->execute([$loginEmail, (int)$existingCustomerId]);
            if ($dupe->fetch()) {
                $errors[] = 'Another client login already uses that email.';
            } elseif (!$existingCustomerId && strlen($loginPassword) < 8) {
                $errors[] = 'Set a password of at least 8 characters to create this client\'s login.';
            } elseif ($loginPassword !== '' && strlen($loginPassword) < 8) {
                $errors[] = 'Password must be at least 8 characters (leave blank to keep the current one).';
            }
        }

        if ($errors) {
            flash(implode(' ', $errors), 'error');
        } else {
            $mrrCents = (int)round($mrr * 100);
            if ($id > 0) {
                $stmt = $pdo->prepare('UPDATE businesses SET name=?, sector=?, plan=?, status=?, mrr_cents=?, contact_email=?, contact_phone=? WHERE id=?');
                $stmt->execute([$name, $sector, $plan, $status, $mrrCents, $contactEmail, $contactPhone, $id]);
                flash('Business updated.');
            } else {
                $stmt = $pdo->prepare('INSERT INTO businesses (name, sector, plan, status, mrr_cents, contact_email, contact_phone, last_activity_at) VALUES (?,?,?,?,?,?,?,NOW())');
                $stmt->execute([$name, $sector, $plan, $status, $mrrCents, $contactEmail, $contactPhone]);
                $id = (int)$pdo->lastInsertId();
                flash('Business added.');
            }

            if ($loginEmail !== '') {
                if ($existingCustomerId) {
                    if ($loginPassword !== '') {
                        $pdo->prepare('UPDATE customers SET name=?, email=?, password_hash=? WHERE id=?')
                            ->execute([$name, $loginEmail, password_hash($loginPassword, PASSWORD_DEFAULT), $existingCustomerId]);
                    } else {
                        $pdo->prepare('UPDATE customers SET name=?, email=? WHERE id=?')
                            ->execute([$name, $loginEmail, $existingCustomerId]);
                    }
                } else {
                    $pdo->prepare('INSERT INTO customers (name, email, password_hash) VALUES (?,?,?)')
                        ->execute([$name, $loginEmail, password_hash($loginPassword, PASSWORD_DEFAULT)]);
                    $newCustomerId = (int)$pdo->lastInsertId();
                    $pdo->prepare('UPDATE businesses SET customer_id=? WHERE id=?')->execute([$newCustomerId, $id]);
                }
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('DELETE FROM businesses WHERE id=?')->execute([$id]);
        flash('Business removed.');
    }
    header('Location: businesses.php');
    exit;
}

$editing = null;
$editingLoginEmail = '';
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT b.*, c.email AS login_email FROM businesses b LEFT JOIN customers c ON c.id = b.customer_id WHERE b.id=?');
    $stmt->execute([(int)$_GET['edit']]);
    $editing = $stmt->fetch() ?: null;
    $editingLoginEmail = $editing['login_email'] ?? '';
}

$accounts = $pdo->query(
    'SELECT b.*, c.email AS login_email, (SELECT COUNT(*) FROM projects p WHERE p.business_id = b.id) AS project_count
     FROM businesses b LEFT JOIN customers c ON c.id = b.customer_id ORDER BY b.last_activity_at DESC'
)->fetchAll();
$statusTone = ['Active' => 'success', 'Trial' => 'warning', 'Past due' => 'danger'];
$token = csrf_token();
?>
<!doctype html>
<html lang="en"<?= palette_attr() ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Businesses — TECHBISS Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/style.css?v=<?= @filemtime(__DIR__ . '/../assets/style.css') ?: '1' ?>">
</head>
<body>
<?= admin_header($staff, 'businesses.php') ?>
<main class="admin-page">
  <?= admin_flash_html() ?>
  <div class="admin-toolbar">
    <div><h1 style="margin-bottom:4px;">Businesses</h1><p class="lede" style="margin-bottom:0;">Every client account on the platform.</p></div>
  </div>

  <div class="card admin-form-card">
    <div class="card-head"><?= blob_icon($editing ? 'edit' : 'plus', 'sm', true) ?><h3><?= $editing ? 'Edit business' : 'Add a business' ?></h3></div>
    <form method="post">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="csrf" value="<?= e($token) ?>">
      <input type="hidden" name="id" value="<?= e((string)($editing['id'] ?? 0)) ?>">
      <div class="grid grid-2" style="gap:16px;">
        <div class="field"><label>Business name</label><input name="name" required value="<?= e($editing['name'] ?? '') ?>"></div>
        <div class="field"><label>Sector</label><input name="sector" required value="<?= e($editing['sector'] ?? '') ?>" placeholder="e.g. Bakery, Fitness, Retail"></div>
      </div>
      <div class="grid grid-3" style="gap:16px;">
        <div class="field"><label>Plan</label><select name="plan">
          <?php foreach ($PLANS as $p): ?><option value="<?= e($p) ?>" <?= ($editing['plan'] ?? '') === $p ? 'selected' : '' ?>><?= e($p) ?></option><?php endforeach; ?>
        </select></div>
        <div class="field"><label>Status</label><select name="status">
          <?php foreach ($STATUSES as $s): ?><option value="<?= e($s) ?>" <?= ($editing['status'] ?? '') === $s ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?>
        </select></div>
        <div class="field"><label>MRR (USD/mo)</label><input type="number" min="0" step="1" name="mrr" value="<?= e((string)(($editing['mrr_cents'] ?? 0) / 100)) ?>"></div>
      </div>
      <div class="grid grid-2" style="gap:16px;">
        <div class="field"><label>Contact email</label><input type="email" name="contact_email" value="<?= e($editing['contact_email'] ?? '') ?>" placeholder="owner@theirbusiness.com"></div>
        <div class="field"><label>Contact phone / WhatsApp</label><input name="contact_phone" value="<?= e($editing['contact_phone'] ?? '') ?>" placeholder="+1 555 0100"></div>
      </div>
      <div class="field">
        <label>Client login <?= $editingLoginEmail !== '' ? '(update)' : '(optional — creates their dashboard access)' ?></label>
        <div class="grid grid-2" style="gap:16px;">
          <input type="email" name="login_email" value="<?= e($editingLoginEmail) ?>" placeholder="Login email">
          <input type="password" name="login_password" minlength="8" placeholder="<?= $editingLoginEmail !== '' ? 'New password (leave blank to keep)' : 'Password (min 8 characters)' ?>">
        </div>
        <p style="font-size:.78rem;color:var(--ink-faint);margin-top:6px;">Set an email + password here to give this business's owner sign-in access to their dashboard. Leave blank if they don't need one.</p>
      </div>
      <div class="flex gap-12">
        <button class="btn btn-primary" type="submit"><?= $editing ? 'Save changes' : 'Add business' ?></button>
        <?php if ($editing): ?><a href="businesses.php" class="btn btn-ghost">Cancel</a><?php endif; ?>
      </div>
    </form>
  </div>

  <div class="card">
    <div class="table-wrap"><table><thead><tr><th>Business</th><th>Sector</th><th>Plan</th><th>Login</th><th>Projects</th><th>Status</th><th>Last activity</th><th></th></tr></thead><tbody>
      <?php foreach ($accounts as $a): ?>
      <tr>
        <td style="font-weight:600;"><?= e($a['name']) ?></td>
        <td><?= e($a['sector']) ?></td>
        <td><?= e($a['plan']) ?></td>
        <td><?= $a['login_email'] ? '<span class="badge success">' . e($a['login_email']) . '</span>' : '<span class="badge">None</span>' ?></td>
        <td><a class="card-link" href="projects.php?business=<?= (int)$a['id'] ?>"><?= (int)$a['project_count'] ?> <?= ico('arrow') ?></a></td>
        <td><span class="badge <?= $statusTone[$a['status']] ?? '' ?>"><?= e($a['status']) ?></span></td>
        <td style="color:var(--ink-faint);"><?= e(time_ago($a['last_activity_at'])) ?></td>
        <td class="admin-actions-cell">
          <a class="icon-btn" href="businesses.php?edit=<?= (int)$a['id'] ?>" aria-label="Edit"><?= ico('edit') ?></a>
          <form method="post" onsubmit="return confirm('Delete <?= e(addslashes($a['name'])) ?>? This also removes its tickets and projects.');">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="csrf" value="<?= e($token) ?>">
            <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
            <button class="icon-btn danger" type="submit" aria-label="Delete"><?= ico('trash') ?></button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$accounts): ?><tr><td colspan="8" style="color:var(--ink-faint);">No businesses yet — add your first one above.</td></tr><?php endif; ?>
    </tbody></table></div>
  </div>
</main>
<?= admin_bottomnav($staff, 'businesses.php') ?>
</body>
</html>
