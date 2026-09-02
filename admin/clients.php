<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/guard.php';
$me = require_admin();

$action = (string)($_GET['action'] ?? 'list');
$id     = (int)($_GET['id'] ?? 0);

if ($action === 'delete' && post()) {
    Csrf::check();
    $u = Database::one('SELECT * FROM users WHERE id = :id', ['id' => $id]);
    if ($u && (int)$u['id'] !== (int)$me['id']) {
        if ($u['role'] === 'admin'
            && (int)Database::value("SELECT COUNT(*) FROM users WHERE role='admin' AND status='active'", [], 0) <= 1) {
            Flash::err('You cannot delete the last active administrator.');
            redirect('admin/clients.php');
        }
        Database::run('UPDATE projects SET user_id = NULL WHERE user_id = :id', ['id' => $id]);
        Database::run('UPDATE orders SET user_id = NULL WHERE user_id = :id', ['id' => $id]);
        foreach (Database::all('SELECT id FROM tickets WHERE user_id = :id', ['id' => $id]) as $t) {
            Database::run('DELETE FROM ticket_messages WHERE ticket_id = :t', ['t' => (int)$t['id']]);
        }
        Database::run('DELETE FROM tickets WHERE user_id = :id', ['id' => $id]);
        Database::delete('users', $id);
        log_activity('user.delete', 'user', $id, $u['email']);
        Flash::ok($u['name'] . ' was removed. Their projects and orders were kept and unlinked.');
    } else {
        Flash::err('You cannot delete your own account.');
    }
    redirect('admin/clients.php');
}

if ($action === 'new' || $action === 'edit') {
    $u = $action === 'edit'
        ? Database::one('SELECT * FROM users WHERE id = :id', ['id' => $id])
        : ['role' => 'client', 'status' => 'active'];
    if ($action === 'edit' && !$u) {
        http_response_code(404);
        exit('User not found.');
    }
    $errors = [];
    $issued = null;

    if (post()) {
        Csrf::check();
        $name  = trim((string)($_POST['name'] ?? ''));
        $email = trim(mb_strtolower((string)($_POST['email'] ?? '')));
        $role  = ($_POST['role'] ?? 'client') === 'admin' ? 'admin' : 'client';
        $stat  = ($_POST['status'] ?? 'active') === 'suspended' ? 'suspended' : 'active';
        $pass  = (string)($_POST['password'] ?? '');

        if ($name === '')       $errors[] = 'Enter a name.';
        if (!is_email($email))  $errors[] = 'Enter a valid email address.';
        $clash = Database::one('SELECT id FROM users WHERE email = :e' . ($action === 'edit' ? ' AND id <> :id' : ''),
            $action === 'edit' ? ['e' => $email, 'id' => $id] : ['e' => $email]);
        if ($clash) $errors[] = 'Another account already uses that email.';
        if ($action === 'new' && $pass !== '' && strlen($pass) < 10) $errors[] = 'Passwords must be at least 10 characters.';
        if ($action === 'edit' && $pass !== '' && strlen($pass) < 10) $errors[] = 'Passwords must be at least 10 characters.';

        /* Never let an admin lock everyone out. */
        if ($action === 'edit' && (int)$u['id'] === (int)$me['id'] && ($role !== 'admin' || $stat !== 'active')) {
            $errors[] = 'You cannot remove your own admin access or suspend yourself.';
        }

        if (!$errors) {
            $data = [
                'name'    => $name,
                'email'   => $email,
                'phone'   => trim((string)($_POST['phone'] ?? '')) ?: null,
                'company' => trim((string)($_POST['company'] ?? '')) ?: null,
                'role'    => $role,
                'status'  => $stat,
            ];
            if ($action === 'edit') {
                if ($pass !== '') {
                    $data['password_hash'] = password_hash($pass, PASSWORD_DEFAULT);
                    $data['must_change']   = 1;
                }
                Database::update('users', $data, $id);
                log_activity('user.update', 'user', $id, $email);
                Flash::ok('Account saved.');
                redirect('admin/clients.php');
            }
            $plain = $pass !== '' ? $pass : Auth::randomPassword();
            $data['password_hash'] = password_hash($plain, PASSWORD_DEFAULT);
            $data['must_change']   = 1;
            $data['created_at']    = now();
            $id = Database::insert('users', $data);
            log_activity('user.create', 'user', $id, $email);
            $_SESSION['new_account'] = ['email' => $email, 'password' => $plain];
            Flash::ok('Account created.');
            redirect('admin/clients.php');
        }
        $u = array_merge($u, $_POST);
    }

    $PAGE_TITLE = $action === 'edit' ? 'Edit account' : 'New account';
    $AREA = 'admin';
    require __DIR__ . '/../partials/app_header.php';
    ?>
    <?php if ($errors): ?>
      <div class="alert err"><?php foreach ($errors as $er): ?><p><?= e($er) ?></p><?php endforeach; ?></div>
    <?php endif; ?>
    <form method="post" class="form" style="max-width:640px">
      <?= Csrf::field() ?>
      <div class="fieldset">
        <p class="legend">Account</p>
        <div class="row two">
          <label class="field"><span>Full name</span><input name="name" value="<?= e($u['name'] ?? '') ?>" required></label>
          <label class="field"><span>Email <small>this is the login</small></span>
            <input name="email" type="email" value="<?= e($u['email'] ?? '') ?>" required></label>
        </div>
        <div class="row two">
          <label class="field"><span>Phone</span><input name="phone" value="<?= e($u['phone'] ?? '') ?>"></label>
          <label class="field"><span>Company</span><input name="company" value="<?= e($u['company'] ?? '') ?>"></label>
        </div>
        <div class="row two">
          <label class="field"><span>Role</span>
            <select name="role">
              <option value="client"<?= ($u['role'] ?? '') === 'client' ? ' selected' : '' ?>>Client</option>
              <option value="admin"<?= ($u['role'] ?? '') === 'admin' ? ' selected' : '' ?>>Administrator</option>
            </select></label>
          <label class="field"><span>Status</span>
            <select name="status">
              <option value="active"<?= ($u['status'] ?? '') === 'active' ? ' selected' : '' ?>>Active</option>
              <option value="suspended"<?= ($u['status'] ?? '') === 'suspended' ? ' selected' : '' ?>>Suspended</option>
            </select></label>
        </div>
        <label class="field"><span>Password
            <small><?= $action === 'edit' ? 'leave blank to keep the current one' : 'leave blank to generate one' ?></small></span>
          <input name="password" type="password" minlength="10" autocomplete="new-password"></label>
      </div>
      <div class="formfoot">
        <button class="btn" type="submit"><?= $action === 'edit' ? 'Save account' : 'Create account' ?></button>
        <a class="btn ghost" href="clients.php">Cancel</a>
      </div>
    </form>
    <?php
    require __DIR__ . '/../partials/app_footer.php';
    exit;
}

$role  = (string)($_GET['role'] ?? '');
$where = in_array($role, ['client', 'admin'], true) ? ' WHERE u.role = :r' : '';
$users = Database::all(
    'SELECT u.*, (SELECT COUNT(*) FROM projects p WHERE p.user_id = u.id) AS project_count,
            (SELECT COUNT(*) FROM tickets t WHERE t.user_id = u.id AND t.status <> \'closed\') AS open_tickets
     FROM users u' . $where . ' ORDER BY u.role, u.name',
    $where ? ['r' => $role] : []
);
$issued = $_SESSION['new_account'] ?? null;
unset($_SESSION['new_account']);

$PAGE_TITLE = 'Clients';
$AREA = 'admin';
$PAGE_ACTIONS = '<a class="btn sm" href="clients.php?action=new">New account</a>';
require __DIR__ . '/../partials/app_header.php';
?>
<?php if ($issued): ?>
  <div class="alert ok">
    <p><b>Account created.</b> Pass these on — the password is shown once.</p>
    <p class="mono">Sign in: <?= e(url('login.php')) ?> · Email: <?= e($issued['email']) ?> · Password: <?= e($issued['password']) ?></p>
  </div>
<?php endif; ?>

<div class="filters">
  <?php foreach (['' => 'Everyone', 'client' => 'Clients', 'admin' => 'Administrators'] as $k => $v): ?>
    <a href="?role=<?= e($k) ?>" class="<?= $role === $k ? 'on' : '' ?>"><?= e($v) ?></a>
  <?php endforeach; ?>
</div>

<section class="card">
  <?php if (!$users): ?>
    <div class="empty"><b>No accounts</b><p>Create one, or let a project create it from the owner's email.</p></div>
  <?php else: ?>
    <div class="tablewrap"><table class="data">
      <thead><tr><th>Name</th><th>Email</th><th class="right">Sites</th><th class="right">Open tickets</th><th>Role</th><th>Status</th><th>Last seen</th><th class="right">Actions</th></tr></thead>
      <tbody>
      <?php foreach ($users as $u): ?>
        <tr>
          <td><span class="t-main"><?= e($u['name']) ?></span>
              <?php if ($u['company']): ?><span class="t-sub"><?= e($u['company']) ?></span><?php endif; ?></td>
          <td><a class="linkish" href="mailto:<?= e($u['email']) ?>"><?= e($u['email']) ?></a>
              <?php if ($u['phone']): ?><span class="t-sub"><?= e($u['phone']) ?></span><?php endif; ?></td>
          <td class="num"><?= (int)$u['project_count'] ?></td>
          <td class="num"><?= (int)$u['open_tickets'] ?: '—' ?></td>
          <td><span class="badge <?= $u['role'] === 'admin' ? 'info' : 'muted' ?>"><?= e(label($u['role'])) ?></span></td>
          <td><span class="badge <?= e(status_tone($u['status'])) ?>"><?= e(label($u['status'])) ?></span></td>
          <td class="nowrap muted"><?= $u['last_login_at'] ? e(fdate($u['last_login_at'], 'j M y')) : 'Never' ?></td>
          <td><div class="acts">
            <a class="btn ghost sm" href="clients.php?action=edit&id=<?= (int)$u['id'] ?>">Edit</a>
            <?php if ((int)$u['id'] !== (int)$me['id']): ?>
              <form method="post" action="clients.php?action=delete&id=<?= (int)$u['id'] ?>"
                    data-confirm="Delete <?= e($u['name']) ?>? Their tickets are deleted; projects and orders are kept but unlinked.">
                <?= Csrf::field() ?><button class="btn danger sm" type="submit">Delete</button>
              </form>
            <?php endif; ?>
          </div></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/../partials/app_footer.php'; ?>
