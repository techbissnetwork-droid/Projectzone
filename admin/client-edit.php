<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_installed();
require_staff();
require_once __DIR__ . '/_layout.php';

$id     = get_int('id');
$isNew  = get('action') === 'new' || $id === 0;
$client = $id ? db_one("SELECT * FROM users WHERE id = ? AND role = 'client'", [$id]) : null;

if ($id && !$client) {
    flash('That client account no longer exists.', 'bad');
    redirect('clients.php');
}

$errors = [];

if (is_post() && post('action') === 'delete' && $client) {
    csrf_check();
    db_run('UPDATE projects SET user_id = NULL WHERE user_id = ?', [$client['id']]);
    db_run('UPDATE tickets SET user_id = NULL WHERE user_id = ?', [$client['id']]);
    db_delete('users', (int) $client['id']);
    log_activity('Deleted client account: ' . $client['email'], 'user', (int) $client['id']);
    flash('Client account deleted. Their projects and tickets were kept.');
    redirect('clients.php');
}

if (is_post() && post('action') === 'save') {
    csrf_check();
    $data = [
        'name'    => post('name'),
        'email'   => strtolower(post('email')),
        'phone'   => post('phone'),
        'company' => post('company'),
        'status'  => post('status', 'active') === 'suspended' ? 'suspended' : 'active',
    ];
    if ($data['name'] === '')        { $errors[] = 'Enter a name.'; }
    if (!valid_email($data['email'])){ $errors[] = 'Enter a valid email address.'; }
    $clash = db_one('SELECT id FROM users WHERE email = ? AND id <> ?', [$data['email'], $id]);
    if ($clash)                      { $errors[] = 'Another account already uses that email address.'; }

    if (!$errors) {
        if ($client) {
            db_update('users', (int) $client['id'], $data);
            log_activity('Updated client: ' . $data['email'], 'user', (int) $client['id']);
            flash('Client saved.');
            redirect('client-edit.php?id=' . $client['id']);
        }
        $data['role']       = ROLE_CLIENT;
        $data['created_at'] = now();
        $newId = db_insert('users', $data);
        $user  = db_one('SELECT * FROM users WHERE id = ?', [$newId]);
        $sent  = mail_client_welcome($user, null);
        log_activity('Created client: ' . $data['email'], 'user', $newId);
        flash($sent
            ? 'Client created and told their portal is ready.'
            : 'Client created, but <strong>the welcome email could not be sent</strong>. They can '
              . 'still sign in — the portal emails a code when they enter their address.',
            $sent ? 'ok' : 'warn');
        redirect('client-edit.php?id=' . $newId);
    }
    $client = array_merge($client ?? [], $data);
}

$c = $client ?? ['id' => 0, 'name' => '', 'email' => '', 'phone' => '', 'company' => '',
                 'status' => 'active', 'last_login_at' => null, 'created_at' => null];

$projects = $c['id'] ? db_all('SELECT * FROM projects WHERE user_id = ? ORDER BY id DESC', [$c['id']]) : [];
$tickets  = $c['id'] ? db_all('SELECT * FROM tickets WHERE user_id = ? ORDER BY id DESC LIMIT 10', [$c['id']]) : [];

admin_head($isNew ? 'Add a client' : $c['name'], 'clients.php');
admin_page_head(
    $isNew ? 'Add a client' : $c['name'],
    $isNew ? 'Creates a portal login and emails them a temporary password.' : esc($c['email']),
    [],
    [['clients.php', 'Client accounts'], [null, $isNew ? 'New' : $c['name']]]
);

foreach ($errors as $e) {
    echo '<div class="flash bad">' . esc($e) . '</div>';
}
?>

<div class="split">
  <div>
    <form method="post" class="admin">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <fieldset>
        <legend>Account</legend>
        <div class="grid2">
          <div class="f"><label for="name">Name</label>
            <input id="name" name="name" required value="<?= esc($c['name']) ?>"></div>
          <div class="f"><label for="email">Email</label>
            <input id="email" name="email" type="email" required value="<?= esc($c['email']) ?>">
            <span class="hint">Their sign-in address, and where their code is emailed.
              Only you can change this &mdash; they cannot change it themselves.</span></div>
        </div>
        <div class="grid2">
          <div class="f"><label for="phone">Phone</label>
            <input id="phone" name="phone" value="<?= esc($c['phone']) ?>"></div>
          <div class="f"><label for="company">Company</label>
            <input id="company" name="company" value="<?= esc($c['company']) ?>"></div>
        </div>
        <div class="f"><label for="status">Status</label>
          <select id="status" name="status">
            <option value="active"<?= $c['status'] === 'active' ? ' selected' : '' ?>>Active — can sign in</option>
            <option value="suspended"<?= $c['status'] === 'suspended' ? ' selected' : '' ?>>Suspended — cannot sign in</option>
          </select></div>
      </fieldset>
      <div class="formbar">
        <button class="btn" type="submit"><?= $isNew ? 'Create client' : 'Save changes' ?></button>
        <a class="btn ghost" href="clients.php">Back to clients</a>
      </div>
    </form>

<?php if (!$isNew): ?>
    <div class="panel" style="margin-top:26px">
      <header><h2>Their projects</h2></header>
<?php if (!$projects): ?>
      <div class="empty"><strong>No projects</strong>
        <p>This account is not linked to any project yet. Open a project and set its owner email
           to <?= esc($c['email']) ?>.</p></div>
<?php else: ?>
      <div class="tablewrap"><table>
        <thead><tr><th>Project</th><th>Domain</th><th class="right">Status</th></tr></thead>
        <tbody>
<?php foreach ($projects as $p): ?>
          <tr>
            <td><a class="rowlink" href="project-edit.php?id=<?= (int) $p['id'] ?>"><?= esc($p['name']) ?></a></td>
            <td><?= esc($p['domain'] ?: '—') ?></td>
            <td class="right"><?= status_pill($p['status']) ?></td>
          </tr>
<?php endforeach; ?>
        </tbody>
      </table></div>
<?php endif; ?>
    </div>
<?php endif; ?>
  </div>

<?php if (!$isNew): ?>
  <div>
    <div class="panel">
      <header><h2>Account</h2></header>
      <div class="pad">
        <div class="kv"><span>Created</span><strong><?= esc(date_human($c['created_at'])) ?></strong></div>
        <div class="kv"><span>Last signed in</span>
          <strong><?= esc($c['last_login_at'] ? datetime_human($c['last_login_at']) : 'Never') ?></strong></div>
        <div class="kv"><span>Open tickets</span>
          <strong><?= db_count("SELECT COUNT(*) FROM tickets WHERE user_id = ? AND status IN ('open','answered')", [$c['id']]) ?></strong></div>
      </div>
    </div>

    <div class="panel">
      <header><h2>Password</h2></header>
      <div class="pad">
        <p style="color:var(--mute);font-size:13.5px;margin-bottom:12px">
          Sends them a new temporary password by email. You never see their existing one —
          passwords are stored hashed and cannot be read back.</p>
        <form method="post" data-confirm="Reset this client's password and email them a new one?">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="reset">
          <button class="btn ghost" type="submit">Reset password</button>
        </form>
      </div>
    </div>

<?php if ($tickets): ?>
    <div class="panel">
      <header><h2>Recent requests</h2></header>
      <div class="pad">
<?php foreach ($tickets as $t): ?>
        <div class="kv">
          <span><a href="ticket.php?id=<?= (int) $t['id'] ?>" style="color:var(--fg)"><?= esc($t['subject']) ?></a></span>
          <strong><?= status_pill($t['status']) ?></strong>
        </div>
<?php endforeach; ?>
      </div>
    </div>
<?php endif; ?>

    <div class="panel">
      <header><h2>Danger</h2></header>
      <div class="pad">
        <p style="color:var(--mute);font-size:13.5px;margin-bottom:12px">
          Deletes the login only. Their projects and tickets stay, just unlinked.</p>
        <form method="post" data-confirm="Delete this client account? Their projects and tickets are kept.">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="delete">
          <button class="btn danger" type="submit">Delete this account</button>
        </form>
      </div>
    </div>
  </div>
<?php endif; ?>
</div>

<?php admin_foot(); ?>
