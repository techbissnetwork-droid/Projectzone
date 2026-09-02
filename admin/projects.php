<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/guard.php';
require_admin();

$action = (string)($_GET['action'] ?? 'list');
$id     = (int)($_GET['id'] ?? 0);

/* ── delete ─────────────────────────────────────────────── */
if ($action === 'delete' && post()) {
    Csrf::check();
    $p = Database::one('SELECT * FROM projects WHERE id = :id', ['id' => $id]);
    if ($p) {
        Database::run('DELETE FROM maintenance_logs WHERE project_id = :id', ['id' => $id]);
        Database::run('UPDATE tickets SET project_id = NULL WHERE project_id = :id', ['id' => $id]);
        Database::delete('projects', $id);
        log_activity('project.delete', 'project', $id, $p['name']);
        Flash::ok('Project “' . $p['name'] . '” was deleted.');
    }
    redirect('admin/projects.php');
}

/* ── create / edit ──────────────────────────────────────── */
if ($action === 'new' || $action === 'edit') {
    $project = $action === 'edit'
        ? Database::one('SELECT * FROM projects WHERE id = :id', ['id' => $id])
        : ['status' => 'planning', 'email_accounts' => 0];
    if ($action === 'edit' && !$project) {
        http_response_code(404);
        exit('Project not found.');
    }
    $errors = [];
    $newAccount = null;

    if (post()) {
        Csrf::check();
        $f = static fn(string $k): string => trim((string)($_POST[$k] ?? ''));
        $d = static fn(string $k): ?string => ($v = trim((string)($_POST[$k] ?? ''))) !== '' ? $v : null;

        $data = [
            'name'               => $f('name'),
            'site_url'           => $d('site_url'),
            'package'            => $d('package'),
            'status'             => in_array($f('status'), ['planning','in_progress','live','on_hold','closed'], true) ? $f('status') : 'planning',
            'owner_name'         => $d('owner_name'),
            'owner_email'        => $d('owner_email') ? mb_strtolower((string)$d('owner_email')) : null,
            'owner_phone'        => $d('owner_phone'),
            'domain_name'        => $d('domain_name'),
            'domain_registrar'   => $d('domain_registrar'),
            'domain_expires_on'  => $d('domain_expires_on'),
            'hosting_provider'   => $d('hosting_provider'),
            'hosting_plan'       => $d('hosting_plan'),
            'hosting_expires_on' => $d('hosting_expires_on'),
            'ssl_issuer'         => $d('ssl_issuer'),
            'ssl_expires_on'     => $d('ssl_expires_on'),
            'email_provider'     => $d('email_provider'),
            'email_accounts'     => (int)($_POST['email_accounts'] ?? 0),
            'started_on'         => $d('started_on'),
            'launched_on'        => $d('launched_on'),
            'monthly_fee'        => $d('monthly_fee') !== null ? (float)$d('monthly_fee') : null,
            'portfolio_id'       => (int)($_POST['portfolio_id'] ?? 0) ?: null,
            'user_id'            => (int)($_POST['user_id'] ?? 0) ?: null,
            'notes'              => $d('notes'),
            'updated_at'         => now(),
        ];

        if ($data['name'] === '') {
            $errors[] = 'Enter a project name.';
        }
        if ($data['owner_email'] !== null && !is_email($data['owner_email'])) {
            $errors[] = 'The owner email is not a valid address.';
        }
        foreach (['domain_expires_on' => 'domain', 'hosting_expires_on' => 'hosting', 'ssl_expires_on' => 'SSL'] as $k => $lbl) {
            if ($data[$k] !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$data[$k])) {
                $errors[] = 'The ' . $lbl . ' expiry date is not a valid date.';
            }
        }

        /* Optionally create the client account from the owner details. */
        $wantAccount = !empty($_POST['create_account']);
        if ($wantAccount) {
            if (!$data['owner_email']) {
                $errors[] = 'An owner email is required to create the client account.';
            } elseif (Database::one('SELECT id FROM users WHERE email = :e', ['e' => $data['owner_email']])) {
                $errors[] = 'A user with that email already exists — pick them in “Client account” instead.';
            }
        }

        if (!$errors) {
            if ($wantAccount) {
                $plain = Auth::randomPassword();
                $data['user_id'] = Database::insert('users', [
                    'name'          => $data['owner_name'] ?: $data['name'],
                    'email'         => $data['owner_email'],
                    'phone'         => $data['owner_phone'],
                    'company'       => $data['name'],
                    'password_hash' => password_hash($plain, PASSWORD_DEFAULT),
                    'role'          => 'client',
                    'status'        => 'active',
                    'must_change'   => 1,
                    'created_at'    => now(),
                ]);
                $newAccount = ['email' => $data['owner_email'], 'password' => $plain];
                log_activity('client.create', 'user', $data['user_id'], $data['owner_email']);
            }

            if ($action === 'edit') {
                Database::update('projects', $data, $id);
                log_activity('project.update', 'project', $id, $data['name']);
                Flash::ok('Project saved.');
            } else {
                $data['created_at'] = now();
                $id = Database::insert('projects', $data);
                log_activity('project.create', 'project', $id, $data['name']);
                Flash::ok('Project created.');
            }
            if ($newAccount) {
                $_SESSION['new_account'] = $newAccount;
            }
            redirect('admin/project.php?id=' . $id);
        }
        $project = array_merge($project, $data);
    }

    $clients   = Database::all("SELECT id, name, email FROM users WHERE role = 'client' ORDER BY name");
    $portfolio = Database::all('SELECT id, title FROM portfolio ORDER BY title');

    $PAGE_TITLE = $action === 'edit' ? 'Edit project' : 'New project';
    $AREA = 'admin';
    require __DIR__ . '/../partials/app_header.php';
    ?>
    <?php if ($errors): ?>
      <div class="alert err"><?php foreach ($errors as $er): ?><p><?= e($er) ?></p><?php endforeach; ?></div>
    <?php endif; ?>

    <form method="post" class="form">
      <?= Csrf::field() ?>
      <div class="split">
        <div class="stack">
          <div class="fieldset">
            <p class="legend">The site</p>
            <label class="field"><span>Project name</span>
              <input name="name" value="<?= e($project['name'] ?? '') ?>" required maxlength="180"></label>
            <div class="row two">
              <label class="field"><span>Live URL</span>
                <input name="site_url" type="url" placeholder="https://" value="<?= e($project['site_url'] ?? '') ?>"></label>
              <label class="field"><span>Package</span>
                <input name="package" placeholder="Business site + hosting" value="<?= e($project['package'] ?? '') ?>"></label>
            </div>
            <div class="row three">
              <label class="field"><span>Status</span>
                <select name="status">
                  <?php foreach (['planning','in_progress','live','on_hold','closed'] as $s): ?>
                    <option value="<?= $s ?>"<?= ($project['status'] ?? '') === $s ? ' selected' : '' ?>><?= e(label($s)) ?></option>
                  <?php endforeach; ?>
                </select></label>
              <label class="field"><span>Started</span>
                <input name="started_on" type="date" value="<?= e($project['started_on'] ?? '') ?>"></label>
              <label class="field"><span>Launched</span>
                <input name="launched_on" type="date" value="<?= e($project['launched_on'] ?? '') ?>"></label>
            </div>
          </div>

          <div class="fieldset">
            <p class="legend">Domain</p>
            <div class="row three">
              <label class="field"><span>Domain name</span>
                <input name="domain_name" placeholder="example.com" value="<?= e($project['domain_name'] ?? '') ?>"></label>
              <label class="field"><span>Registrar</span>
                <input name="domain_registrar" value="<?= e($project['domain_registrar'] ?? '') ?>"></label>
              <label class="field"><span>Expires on</span>
                <input name="domain_expires_on" type="date" value="<?= e($project['domain_expires_on'] ?? '') ?>"></label>
            </div>
          </div>

          <div class="fieldset">
            <p class="legend">Hosting</p>
            <div class="row three">
              <label class="field"><span>Provider</span>
                <input name="hosting_provider" value="<?= e($project['hosting_provider'] ?? '') ?>"></label>
              <label class="field"><span>Plan</span>
                <input name="hosting_plan" placeholder="VPS 4 GB" value="<?= e($project['hosting_plan'] ?? '') ?>"></label>
              <label class="field"><span>Expires on</span>
                <input name="hosting_expires_on" type="date" value="<?= e($project['hosting_expires_on'] ?? '') ?>"></label>
            </div>
          </div>

          <div class="fieldset">
            <p class="legend">SSL &amp; email</p>
            <div class="row two">
              <label class="field"><span>SSL issuer</span>
                <input name="ssl_issuer" placeholder="Let's Encrypt" value="<?= e($project['ssl_issuer'] ?? '') ?>"></label>
              <label class="field"><span>SSL expires on</span>
                <input name="ssl_expires_on" type="date" value="<?= e($project['ssl_expires_on'] ?? '') ?>"></label>
            </div>
            <div class="row two">
              <label class="field"><span>Email provider</span>
                <input name="email_provider" placeholder="Google Workspace" value="<?= e($project['email_provider'] ?? '') ?>"></label>
              <label class="field"><span>Mailboxes</span>
                <input name="email_accounts" type="number" min="0" value="<?= e((string)($project['email_accounts'] ?? 0)) ?>"></label>
            </div>
          </div>

          <label class="field"><span>Internal notes <small>never shown to the client</small></span>
            <textarea name="notes" rows="4"><?= e($project['notes'] ?? '') ?></textarea></label>
        </div>

        <div class="stack">
          <div class="fieldset">
            <p class="legend">Owner</p>
            <label class="field"><span>Owner name</span>
              <input name="owner_name" value="<?= e($project['owner_name'] ?? '') ?>"></label>
            <label class="field"><span>Owner email</span>
              <input name="owner_email" type="email" value="<?= e($project['owner_email'] ?? '') ?>"></label>
            <label class="field"><span>Owner phone</span>
              <input name="owner_phone" value="<?= e($project['owner_phone'] ?? '') ?>"></label>
          </div>

          <div class="fieldset">
            <p class="legend">Client account</p>
            <label class="field"><span>Linked account</span>
              <select name="user_id">
                <option value="0">— not linked —</option>
                <?php foreach ($clients as $c): ?>
                  <option value="<?= (int)$c['id'] ?>"<?= (int)($project['user_id'] ?? 0) === (int)$c['id'] ? ' selected' : '' ?>>
                    <?= e($c['name']) ?> · <?= e($c['email']) ?></option>
                <?php endforeach; ?>
              </select></label>
            <label class="field check"><input type="checkbox" name="create_account" value="1">
              <span>Create a portal login from the owner email</span></label>
            <p class="hint">A password is generated and shown once after saving. The client signs in at
              <span class="mono"><?= e(url('login.php')) ?></span> to track this project, raise tickets and request maintenance.</p>
          </div>

          <div class="fieldset">
            <p class="legend">Commercial</p>
            <label class="field"><span>Monthly fee</span>
              <input name="monthly_fee" type="number" step="0.01" min="0" value="<?= e((string)($project['monthly_fee'] ?? '')) ?>"></label>
            <label class="field"><span>Portfolio entry</span>
              <select name="portfolio_id">
                <option value="0">— none —</option>
                <?php foreach ($portfolio as $pf): ?>
                  <option value="<?= (int)$pf['id'] ?>"<?= (int)($project['portfolio_id'] ?? 0) === (int)$pf['id'] ? ' selected' : '' ?>>
                    <?= e($pf['title']) ?></option>
                <?php endforeach; ?>
              </select></label>
            <p class="hint">Link this project to a portfolio entry to show the finished work publicly.</p>
          </div>
        </div>
      </div>

      <div class="formfoot">
        <button class="btn" type="submit"><?= $action === 'edit' ? 'Save project' : 'Create project' ?></button>
        <a class="btn ghost" href="<?= $action === 'edit' ? 'project.php?id=' . $id : 'projects.php' ?>">Cancel</a>
      </div>
    </form>
    <?php
    require __DIR__ . '/../partials/app_footer.php';
    exit;
}

/* ── list ───────────────────────────────────────────────── */
$status = (string)($_GET['status'] ?? '');
$q      = trim((string)($_GET['q'] ?? ''));
$where  = [];
$params = [];
if (in_array($status, ['planning','in_progress','live','on_hold','closed'], true)) {
    $where[] = 'p.status = :st';
    $params['st'] = $status;
}
if ($q !== '') {
    $where[] = '(p.name LIKE :q OR p.domain_name LIKE :q OR p.owner_email LIKE :q)';
    $params['q'] = '%' . $q . '%';
}
$sql = 'SELECT p.*, u.name AS client_name FROM projects p LEFT JOIN users u ON u.id = p.user_id';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY CASE p.status WHEN \'live\' THEN 0 WHEN \'in_progress\' THEN 1 ELSE 2 END, p.name';
$projects = Database::all($sql, $params);

$PAGE_TITLE = 'Projects';
$AREA = 'admin';
$PAGE_ACTIONS = '<a class="btn sm" href="projects.php?action=new">New project</a>';
require __DIR__ . '/../partials/app_header.php';
?>
<form class="filters" method="get">
  <?php foreach (['' => 'All', 'live' => 'Live', 'in_progress' => 'In progress', 'planning' => 'Planning', 'on_hold' => 'On hold', 'closed' => 'Closed'] as $k => $v): ?>
    <a href="?status=<?= e($k) ?><?= $q !== '' ? '&q=' . urlencode($q) : '' ?>" class="<?= $status === $k ? 'on' : '' ?>"><?= e($v) ?></a>
  <?php endforeach; ?>
  <span class="searchbar">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search name, domain or email">
    <?php if ($status !== ''): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
    <button class="btn ghost sm" type="submit">Search</button>
  </span>
</form>

<section class="card">
  <?php if (!$projects): ?>
    <div class="empty"><b>No projects yet</b><p>Create one to start tracking a client's domain, hosting and SSL.</p>
      <a class="btn sm" href="projects.php?action=new">New project</a></div>
  <?php else: ?>
    <div class="tablewrap"><table class="data">
      <thead><tr><th>Project</th><th>Client</th><th>Domain</th><th>Hosting</th><th>SSL</th><th>Status</th><th class="right">Actions</th></tr></thead>
      <tbody>
      <?php foreach ($projects as $p): ?>
        <tr>
          <td><a class="linkish t-main" href="project.php?id=<?= (int)$p['id'] ?>"><?= e($p['name']) ?></a>
              <?php if ($p['site_url']): ?><span class="t-sub"><?= e(preg_replace('~^https?://~', '', $p['site_url'])) ?></span><?php endif; ?></td>
          <td><?php if ($p['client_name']): ?><?= e($p['client_name']) ?><?php else: ?><span class="muted">Not linked</span><?php endif; ?></td>
          <?php foreach (['domain_expires_on', 'hosting_expires_on', 'ssl_expires_on'] as $col):
              $st = expiry_state($p[$col]); ?>
            <td class="nowrap"><?php if (!$p[$col]): ?><span class="muted">—</span>
              <?php else: ?><span class="badge <?= $st === 'ok' ? 'ok' : ($st === 'warn' ? 'warn' : 'danger') ?>"><?= e(fdate($p[$col], 'j M y')) ?></span><?php endif; ?></td>
          <?php endforeach; ?>
          <td><span class="badge <?= e(status_tone($p['status'])) ?>"><?= e(label($p['status'])) ?></span></td>
          <td><div class="acts">
            <a class="btn ghost sm" href="projects.php?action=edit&id=<?= (int)$p['id'] ?>">Edit</a>
            <form method="post" action="projects.php?action=delete&id=<?= (int)$p['id'] ?>"
                  data-confirm="Delete “<?= e($p['name']) ?>”? Its maintenance history is deleted too. This cannot be undone.">
              <?= Csrf::field() ?><button class="btn danger sm" type="submit">Delete</button>
            </form>
          </div></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/../partials/app_footer.php'; ?>
