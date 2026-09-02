<?php
/**
 * One client project: the site details, the four renewal dates, the owner's
 * login, its maintenance history and its support tickets.
 *
 * Saving a project with "give the owner a login" ticked creates the client
 * account and emails them a temporary password.
 */
require_once __DIR__ . '/../app/bootstrap.php';
require_installed();
require_staff();
require_once __DIR__ . '/_layout.php';

$id      = get_int('id');
$isNew   = get('action') === 'new' || $id === 0;
$project = $id ? db_one('SELECT * FROM projects WHERE id = ?', [$id]) : null;

if ($id && !$project) {
    flash('That project no longer exists.', 'bad');
    redirect('projects.php');
}

$errors = [];

/* --- delete ----------------------------------------------------------- */
if (is_post() && post('action') === 'delete' && $project) {
    csrf_check();
    db_run('DELETE FROM maintenance_logs WHERE project_id = ?', [$project['id']]);
    db_run('UPDATE tickets SET project_id = NULL WHERE project_id = ?', [$project['id']]);
    db_delete('projects', (int) $project['id']);
    log_activity('Deleted project', 'project', (int) $project['id'], null);
    flash('Project deleted. The client account was left in place — remove it under Client accounts if you no longer need it.');
    redirect('projects.php');
}

/* --- add a maintenance entry ------------------------------------------ */
if (is_post() && post('action') === 'log' && $project) {
    csrf_check();
    $title = post('log_title');
    if ($title === '') {
        $errors[] = 'Give the maintenance entry a title.';
    } else {
        db_insert('maintenance_logs', [
            'project_id'        => (int) $project['id'],
            'title'             => $title,
            'kind'              => post('log_kind', 'update'),
            'body'              => post('log_body'),
            'performed_on'      => post('log_date') ?: date('Y-m-d'),
            'performed_by'      => current_user()['name'],
            'visible_to_client' => post('log_visible') === '1' ? 1 : 0,
            'created_at'        => now(),
        ]);
        log_activity('Logged maintenance: ' . $title, 'project', (int) $project['id']);
        flash('Maintenance entry added.');
        redirect('project-edit.php?id=' . $project['id'] . '#maintenance');
    }
}

/* --- save the project -------------------------------------------------- */
if (is_post() && post('action') === 'save') {
    csrf_check();

    $data = [
        'name'               => post('name'),
        'status'             => post('status', 'building'),
        'owner_name'         => post('owner_name'),
        'owner_email'        => strtolower(post('owner_email')),
        'owner_phone'        => post('owner_phone'),
        'company'            => post('company'),
        'domain'             => strtolower(post('domain')),
        'domain_registrar'   => post('domain_registrar'),
        'hosting_provider'   => post('hosting_provider'),
        'hosting_plan'       => post('hosting_plan'),
        'server_ip'          => post('server_ip'),
        'ssl_provider'       => post('ssl_provider'),
        'email_provider'     => post('email_provider'),
        'email_accounts'     => post_int('email_accounts'),
        'care_plan'          => post('care_plan'),
        'notes'              => post('notes'),
        'portfolio_id'       => post_int('portfolio_id') ?: null,
        'updated_at'         => now(),
    ];

    foreach (['domain_expires_on', 'hosting_expires_on', 'ssl_expires_on',
              'email_expires_on', 'care_renews_on', 'launched_on'] as $d) {
        $v = post($d);
        if ($v === '') {
            $data[$d] = null;
        } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
            $errors[] = ucfirst(str_replace('_', ' ', $d)) . ' is not a valid date.';
        } else {
            $data[$d] = $v;
        }
    }

    if ($data['name'] === '') {
        $errors[] = 'Give the project a name.';
    }
    if ($data['owner_email'] !== '' && !valid_email($data['owner_email'])) {
        $errors[] = 'The owner email address is not valid.';
    }

    $wantsLogin = post('create_login') === '1';
    if ($wantsLogin && !valid_email($data['owner_email'])) {
        $errors[] = 'To create a login you need a valid owner email address.';
    }

    if (!$errors) {
        if ($project) {
            db_update('projects', (int) $project['id'], $data);
            $projectId = (int) $project['id'];
            log_activity('Updated project: ' . $data['name'], 'project', $projectId);
            flash('Project saved.');
        } else {
            $data['reference']  = reference('PRJ');
            $data['created_at'] = now();
            $projectId = db_insert('projects', $data);
            log_activity('Added project: ' . $data['name'], 'project', $projectId);
            flash('Project created.');
        }

        /* --- the client login ---------------------------------------- */
        if ($wantsLogin) {
            $existing = db_one('SELECT * FROM users WHERE email = ?', [$data['owner_email']]);
            if ($existing) {
                if ($existing['role'] === ROLE_CLIENT) {
                    db_run('UPDATE projects SET user_id = ? WHERE id = ?', [$existing['id'], $projectId]);
                    flash('That email already had a client account, so this project was linked to it. No new password was sent.', 'warn');
                } else {
                    flash('That email belongs to a team account, so no client login was created for it.', 'warn');
                }
            } else {
                $temp = temp_password();
                $userId = db_insert('users', [
                    'name'                 => $data['owner_name'] ?: $data['name'],
                    'email'                => $data['owner_email'],
                    'password_hash'        => hash_password($temp),
                    'role'                 => ROLE_CLIENT,
                    'phone'                => $data['owner_phone'],
                    'company'              => $data['company'],
                    'status'               => 'active',
                    'must_change_password' => 1,
                    'created_at'           => now(),
                ]);
                db_run('UPDATE projects SET user_id = ? WHERE id = ?', [$userId, $projectId]);
                $user = db_one('SELECT * FROM users WHERE id = ?', [$userId]);
                $sent = mail_client_welcome($user, $temp, db_one('SELECT * FROM projects WHERE id = ?', [$projectId]));
                log_activity('Created client login for ' . $data['owner_email'], 'user', $userId);
                flash($sent
                    ? 'Client login created and emailed to ' . esc($data['owner_email']) . '.'
                    : 'Client login created. <strong>The email could not be sent</strong>, so give them '
                      . 'this password yourself: <code style="font-family:var(--mono)">' . esc($temp)
                      . '</code>', $sent ? 'ok' : 'warn');
            }
        }

        redirect('project-edit.php?id=' . $projectId);
    }

    $project = array_merge($project ?? [], $data);
}

/* --- defaults for a new project ---------------------------------------- */
$p = $project ?? [
    'id' => 0, 'name' => '', 'status' => 'building', 'owner_name' => '', 'owner_email' => '',
    'owner_phone' => '', 'company' => '', 'domain' => '', 'domain_registrar' => '',
    'domain_expires_on' => null, 'hosting_provider' => '', 'hosting_plan' => '',
    'hosting_expires_on' => null, 'server_ip' => '', 'ssl_provider' => '', 'ssl_expires_on' => null,
    'email_provider' => '', 'email_accounts' => 0, 'email_expires_on' => null, 'care_plan' => '',
    'care_renews_on' => null, 'launched_on' => null, 'notes' => '', 'user_id' => null,
    'portfolio_id' => null, 'reference' => '',
];

$client   = !empty($p['user_id']) ? db_one('SELECT * FROM users WHERE id = ?', [$p['user_id']]) : null;
$logs     = $p['id'] ? db_all('SELECT * FROM maintenance_logs WHERE project_id = ? ORDER BY performed_on DESC, id DESC', [$p['id']]) : [];
$tickets  = $p['id'] ? db_all('SELECT * FROM tickets WHERE project_id = ? ORDER BY id DESC', [$p['id']]) : [];
$portfolios = db_all('SELECT id, title FROM portfolio ORDER BY title');

admin_head($isNew ? 'Add a project' : $p['name'], 'project');
admin_page_head(
    $isNew ? 'Add a project' : $p['name'],
    $isNew ? 'Record everything you run for this client — and give them a login if you want them to see it.' : '',
    $isNew ? [] : [['projects.php', 'All projects', 'ghost']],
    [['projects.php', 'Projects'], [null, $isNew ? 'New' : ($p['reference'] ?: 'Project')]]
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
        <legend>The project</legend>
        <div class="grid2">
          <div class="f"><label for="name">Project name</label>
            <input id="name" name="name" required value="<?= esc($p['name']) ?>">
            <span class="hint">What you call it internally, e.g. "Jane's Bakery — website".</span></div>
          <div class="f"><label for="status">Status</label>
            <select id="status" name="status">
<?php foreach (['building' => 'Building', 'active' => 'Live', 'paused' => 'Paused', 'ended' => 'Ended'] as $v => $l): ?>
              <option value="<?= $v ?>"<?= $p['status'] === $v ? ' selected' : '' ?>><?= $l ?></option>
<?php endforeach; ?>
            </select></div>
        </div>
        <div class="grid2">
          <div class="f"><label for="launched_on">Launched on</label>
            <input id="launched_on" name="launched_on" type="date" value="<?= esc((string) $p['launched_on']) ?>"></div>
          <div class="f"><label for="portfolio_id">Linked portfolio entry</label>
            <select id="portfolio_id" name="portfolio_id">
              <option value="0">Not linked</option>
<?php foreach ($portfolios as $pf): ?>
              <option value="<?= (int) $pf['id'] ?>"<?= (int) $p['portfolio_id'] === (int) $pf['id'] ? ' selected' : '' ?>>
                <?= esc($pf['title']) ?></option>
<?php endforeach; ?>
            </select>
            <span class="hint">Optional. Links this project to a completed-work entry.</span></div>
        </div>
      </fieldset>

      <fieldset>
        <legend>Owner</legend>
        <div class="grid2">
          <div class="f"><label for="owner_name">Owner name</label>
            <input id="owner_name" name="owner_name" value="<?= esc($p['owner_name']) ?>"></div>
          <div class="f"><label for="company">Business name</label>
            <input id="company" name="company" value="<?= esc($p['company']) ?>"></div>
        </div>
        <div class="grid2">
          <div class="f"><label for="owner_email">Owner email</label>
            <input id="owner_email" name="owner_email" type="email" value="<?= esc($p['owner_email']) ?>">
            <span class="hint">This becomes their sign-in address for the client portal.</span></div>
          <div class="f"><label for="owner_phone">Owner phone</label>
            <input id="owner_phone" name="owner_phone" value="<?= esc($p['owner_phone']) ?>"></div>
        </div>
<?php if (!$client): ?>
        <div class="f">
          <label class="check">
            <input type="checkbox" name="create_login" value="1">
            <span><strong>Give the owner a login.</strong> Creates a client account for the email
              above and emails them a temporary password. They can then see this project, its
              renewal dates, raise support and maintenance requests, and message you.</span>
          </label>
        </div>
<?php endif; ?>
      </fieldset>

      <fieldset>
        <legend>Domain</legend>
        <div class="grid3">
          <div class="f"><label for="domain">Domain</label>
            <input id="domain" name="domain" value="<?= esc($p['domain']) ?>" placeholder="example.com"></div>
          <div class="f"><label for="domain_registrar">Registrar</label>
            <input id="domain_registrar" name="domain_registrar" value="<?= esc($p['domain_registrar']) ?>"></div>
          <div class="f"><label for="domain_expires_on">Renews on</label>
            <input id="domain_expires_on" name="domain_expires_on" type="date"
                   value="<?= esc((string) $p['domain_expires_on']) ?>"></div>
        </div>
      </fieldset>

      <fieldset>
        <legend>Hosting</legend>
        <div class="grid3">
          <div class="f"><label for="hosting_provider">Provider</label>
            <input id="hosting_provider" name="hosting_provider" value="<?= esc($p['hosting_provider']) ?>"></div>
          <div class="f"><label for="hosting_plan">Plan</label>
            <input id="hosting_plan" name="hosting_plan" value="<?= esc($p['hosting_plan']) ?>"></div>
          <div class="f"><label for="hosting_expires_on">Renews on</label>
            <input id="hosting_expires_on" name="hosting_expires_on" type="date"
                   value="<?= esc((string) $p['hosting_expires_on']) ?>"></div>
        </div>
        <div class="f"><label for="server_ip">Server IP</label>
          <input id="server_ip" name="server_ip" value="<?= esc($p['server_ip']) ?>"></div>
      </fieldset>

      <fieldset>
        <legend>SSL certificate</legend>
        <div class="grid2">
          <div class="f"><label for="ssl_provider">Issued by</label>
            <input id="ssl_provider" name="ssl_provider" value="<?= esc($p['ssl_provider']) ?>"
                   placeholder="Let's Encrypt"></div>
          <div class="f"><label for="ssl_expires_on">Expires on</label>
            <input id="ssl_expires_on" name="ssl_expires_on" type="date"
                   value="<?= esc((string) $p['ssl_expires_on']) ?>"></div>
        </div>
      </fieldset>

      <fieldset>
        <legend>Business email</legend>
        <div class="grid3">
          <div class="f"><label for="email_provider">Provider</label>
            <input id="email_provider" name="email_provider" value="<?= esc($p['email_provider']) ?>"></div>
          <div class="f"><label for="email_accounts">Mailboxes</label>
            <input id="email_accounts" name="email_accounts" type="number" min="0"
                   value="<?= (int) $p['email_accounts'] ?>"></div>
          <div class="f"><label for="email_expires_on">Renews on</label>
            <input id="email_expires_on" name="email_expires_on" type="date"
                   value="<?= esc((string) $p['email_expires_on']) ?>"></div>
        </div>
      </fieldset>

      <fieldset>
        <legend>Care plan and notes</legend>
        <div class="grid2">
          <div class="f"><label for="care_plan">Care plan</label>
            <input id="care_plan" name="care_plan" value="<?= esc($p['care_plan']) ?>"
                   placeholder="Essential / Standard / Managed"></div>
          <div class="f"><label for="care_renews_on">Care renews on</label>
            <input id="care_renews_on" name="care_renews_on" type="date"
                   value="<?= esc((string) $p['care_renews_on']) ?>"></div>
        </div>
        <div class="f"><label for="notes">Internal notes</label>
          <textarea id="notes" name="notes" class="tall"><?= esc((string) $p['notes']) ?></textarea>
          <span class="hint">Only the team sees this — the client never does. Do not store
            passwords here; keep those in a password manager.</span></div>
      </fieldset>

      <div class="formbar">
        <button class="btn" type="submit"><?= $isNew ? 'Create project' : 'Save changes' ?></button>
        <a class="btn ghost" href="projects.php">Back to projects</a>
      </div>
    </form>

<?php if (!$isNew): ?>
    <div class="panel" id="maintenance" style="margin-top:26px">
      <header><h2>Maintenance history</h2></header>
      <div class="pad">
        <form method="post" class="admin" style="margin-bottom:20px">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="log">
          <div class="grid3">
            <div class="f"><label for="log_title">What was done</label>
              <input id="log_title" name="log_title" placeholder="Updated PHP to 8.3"></div>
            <div class="f"><label for="log_kind">Type</label>
              <select id="log_kind" name="log_kind">
<?php foreach (['update' => 'Update', 'backup' => 'Backup', 'fix' => 'Fix',
                'upgrade' => 'Upgrade', 'renewal' => 'Renewal', 'other' => 'Other'] as $v => $l): ?>
                <option value="<?= $v ?>"><?= $l ?></option>
<?php endforeach; ?>
              </select></div>
            <div class="f"><label for="log_date">When</label>
              <input id="log_date" name="log_date" type="date" value="<?= date('Y-m-d') ?>"></div>
          </div>
          <div class="f"><label for="log_body">Detail</label>
            <textarea id="log_body" name="log_body" placeholder="Anything worth remembering later."></textarea></div>
          <div class="f"><label class="check">
            <input type="checkbox" name="log_visible" value="1" checked>
            <span>Show this to the client in their portal</span></label></div>
          <div class="formbar"><button class="btn" type="submit">Add entry</button></div>
        </form>

<?php if (!$logs): ?>
        <p style="color:var(--mute)">Nothing logged for this project yet.</p>
<?php else: ?>
        <div class="thread">
<?php foreach ($logs as $l): ?>
          <div class="msg<?= $l['visible_to_client'] ? '' : ' internal' ?>">
            <div class="who">
              <b><?= esc($l['title']) ?></b>
              <span class="pill"><?= esc(ucfirst($l['kind'])) ?></span>
              <?= esc(date_human($l['performed_on'])) ?> &middot; <?= esc($l['performed_by']) ?>
<?php if (!$l['visible_to_client']): ?>
              <span class="pill soon">Internal only</span>
<?php endif; ?>
            </div>
<?php if ($l['body']): ?>            <p><?= esc($l['body']) ?></p><?php endif; ?>
          </div>
<?php endforeach; ?>
        </div>
<?php endif; ?>
      </div>
    </div>
<?php endif; ?>
  </div>

  <div>
<?php if (!$isNew): ?>
    <div class="panel">
      <header><h2>Renewals</h2></header>
      <div class="pad">
<?php foreach (project_renewals($p) as $r): ?>
        <div class="kv">
          <span><?= esc($r['label']) ?><?php if ($r['provider']): ?>
            <span class="sub" style="display:block;color:var(--dim);font-size:12px"><?= esc($r['provider']) ?></span>
<?php endif; ?></span>
          <strong><?= esc(date_human($r['date'])) ?><br>
            <span class="pill <?= esc($r['state']) ?>"><?= esc($r['human']) ?></span></strong>
        </div>
<?php endforeach; ?>
<?php if ($p['care_plan']): ?>
        <div class="kv"><span>Care plan</span>
          <strong><?= esc($p['care_plan']) ?><br>
            <span class="pill <?= esc(expiry_state($p['care_renews_on'])[0]) ?>">
              <?= esc(expiry_state($p['care_renews_on'])[1]) ?></span></strong></div>
<?php endif; ?>
      </div>
    </div>

    <div class="panel">
      <header><h2>Client login</h2></header>
      <div class="pad">
<?php if ($client): ?>
        <div class="kv"><span>Name</span><strong><?= esc($client['name']) ?></strong></div>
        <div class="kv"><span>Email</span><strong><?= esc($client['email']) ?></strong></div>
        <div class="kv"><span>Status</span><strong><?= status_pill($client['status']) ?></strong></div>
        <div class="kv"><span>Last signed in</span>
          <strong><?= esc($client['last_login_at'] ? datetime_human($client['last_login_at']) : 'Never') ?></strong></div>
        <p style="margin-top:14px"><a class="btn ghost sm" href="client-edit.php?id=<?= (int) $client['id'] ?>">
          Manage this account</a></p>
<?php else: ?>
        <p style="color:var(--mute);font-size:14px">No login yet. Put the owner's email address in
          and tick <em>Give the owner a login</em>, then save.</p>
<?php endif; ?>
      </div>
    </div>

    <div class="panel">
      <header><h2>Support</h2>
        <div class="acts"><a class="btn ghost sm" href="tickets.php">All tickets</a></div></header>
      <div class="pad">
<?php if (!$tickets): ?>
        <p style="color:var(--mute);font-size:14px">No requests raised against this project.</p>
<?php else: ?>
<?php foreach ($tickets as $t): ?>
        <div class="kv">
          <span><a href="ticket.php?id=<?= (int) $t['id'] ?>" style="color:var(--fg)"><?= esc($t['subject']) ?></a>
            <span style="display:block;color:var(--dim);font-size:12px"><?= esc($t['reference']) ?></span></span>
          <strong><?= status_pill($t['status']) ?></strong>
        </div>
<?php endforeach; ?>
<?php endif; ?>
      </div>
    </div>

    <div class="panel">
      <header><h2>Danger</h2></header>
      <div class="pad">
        <p style="color:var(--mute);font-size:13.5px;margin-bottom:12px">
          Deleting removes the project and its maintenance history. Support tickets are kept but
          unlinked, and the client account is left alone.</p>
        <form method="post" data-confirm="Delete this project and its maintenance history? This cannot be undone.">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="delete">
          <button class="btn danger" type="submit">Delete this project</button>
        </form>
      </div>
    </div>
<?php else: ?>
    <div class="panel">
      <header><h2>What this is for</h2></header>
      <div class="pad" style="color:var(--mute);font-size:14px">
        <p style="margin-bottom:12px">A project is one site you run for one client. Record the
          domain, hosting, SSL and email details here and the dashboard warns you before anything
          expires.</p>
        <p style="margin-bottom:12px">Tick <em>Give the owner a login</em> and they get a client
          portal account: their renewal dates, their maintenance history, and a way to raise
          support or upgrade requests that come straight back to you.</p>
        <p>Never put passwords in the notes field. Keep those in a password manager.</p>
      </div>
    </div>
<?php endif; ?>
  </div>
</div>

<?php admin_foot(); ?>
