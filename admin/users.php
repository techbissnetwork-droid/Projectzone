<?php
/** Team accounts — admins and staff. Only a full admin may manage these. */
require_once __DIR__ . '/../app/bootstrap.php';
require_installed();
require_admin();
require_once __DIR__ . '/_layout.php';

$errors = [];
$me     = current_user();

if (is_post() && post('action') === 'add') {
    csrf_check();
    $name  = post('name');
    $email = strtolower(post('email'));
    $pass  = $_POST['password'] ?? '';
    $role  = post('role') === ROLE_ADMIN ? ROLE_ADMIN : ROLE_STAFF;

    if ($name === '')                 { $errors[] = 'Enter a name.'; }
    if (!valid_email($email))         { $errors[] = 'Enter a valid email address.'; }
    if ($p = password_problem($pass)) { $errors[] = 'Password: ' . $p; }
    if (db_one('SELECT id FROM users WHERE email = ?', [$email])) {
        $errors[] = 'An account already uses that email address.';
    }
    if (!$errors) {
        $newId = db_insert('users', [
            'name' => $name, 'email' => $email, 'password_hash' => hash_password($pass),
            'role' => $role, 'status' => 'active', 'created_at' => now(),
        ]);
        log_activity('Added team member: ' . $email, 'user', $newId);
        flash('Team member added. Give them that password directly — it is not emailed.');
        redirect('users.php');
    }
}

if (is_post() && post('action') === 'delete') {
    csrf_check();
    $victim = db_one('SELECT * FROM users WHERE id = ?', [post_int('id')]);
    if (!$victim || $victim['role'] === ROLE_CLIENT) {
        flash('That is not a team account.', 'bad');
    } elseif ((int) $victim['id'] === (int) $me['id']) {
        flash('You cannot delete your own account.', 'bad');
    } elseif ($victim['role'] === ROLE_ADMIN
        && db_count("SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'active'") <= 1) {
        flash('That is the only administrator. Promote someone else first.', 'bad');
    } else {
        db_delete('users', (int) $victim['id']);
        log_activity('Removed team member: ' . $victim['email'], 'user', (int) $victim['id']);
        flash('Team member removed.');
    }
    redirect('users.php');
}

$team = db_all("SELECT * FROM users WHERE role IN ('admin','staff') ORDER BY role, name");

admin_head('Team', 'users.php');
admin_page_head('Team', 'People who can sign in to this admin area. Clients are managed separately.');

foreach ($errors as $e) {
    echo '<div class="flash bad">' . esc($e) . '</div>';
}
?>

<div class="split">
  <div>
    <div class="panel">
      <header><h2>Team accounts</h2></header>
      <div class="tablewrap"><table>
        <thead><tr><th>Name</th><th>Role</th><th>Last signed in</th><th class="right">&nbsp;</th></tr></thead>
        <tbody>
<?php foreach ($team as $u): ?>
          <tr>
            <td><strong><?= esc($u['name']) ?></strong><span class="sub"><?= esc($u['email']) ?></span></td>
            <td><span class="pill <?= $u['role'] === ROLE_ADMIN ? 'acc' : '' ?>">
              <?= $u['role'] === ROLE_ADMIN ? 'Administrator' : 'Staff' ?></span></td>
            <td class="num"><?= esc($u['last_login_at'] ? datetime_human($u['last_login_at']) : 'Never') ?></td>
            <td class="right">
<?php if ((int) $u['id'] === (int) $me['id']): ?>
              <span class="pill">You</span>
<?php else: ?>
              <form method="post" data-confirm="Remove <?= esc($u['name']) ?> from the team?">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                <button class="btn danger sm" type="submit">Remove</button>
              </form>
<?php endif; ?>
            </td>
          </tr>
<?php endforeach; ?>
        </tbody>
      </table></div>
    </div>
  </div>

  <div>
    <form method="post" class="admin">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add">
      <fieldset>
        <legend>Add a team member</legend>
        <div class="f"><label for="name">Name</label>
          <input id="name" name="name" required></div>
        <div class="f"><label for="email">Email</label>
          <input id="email" name="email" type="email" required></div>
        <div class="f"><label for="password">Password</label>
          <input id="password" name="password" type="password" required>
          <span class="hint">At least 10 characters with a letter and a number. Give it to them
            yourself — it is not emailed.</span></div>
        <div class="f"><label for="role">Role</label>
          <select id="role" name="role">
            <option value="staff">Staff — everything except the team list</option>
            <option value="admin">Administrator — full access</option>
          </select></div>
        <div class="formbar"><button class="btn" type="submit">Add team member</button></div>
      </fieldset>
    </form>
  </div>
</div>

<?php admin_foot(); ?>
