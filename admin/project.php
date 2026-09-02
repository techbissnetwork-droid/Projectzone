<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/guard.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);
$p  = Database::one('SELECT p.*, u.name AS client_name, u.email AS client_email, u.status AS client_status
                     FROM projects p LEFT JOIN users u ON u.id = p.user_id WHERE p.id = :id', ['id' => $id]);
if (!$p) {
    http_response_code(404);
    exit('Project not found.');
}

/* Log a maintenance / upgrade entry the client can see. */
if (post() && ($_POST['do'] ?? '') === 'log') {
    Csrf::check();
    $title = trim((string)($_POST['title'] ?? ''));
    $kind  = in_array((string)($_POST['kind'] ?? ''), ['maintenance','upgrade','fix','renewal','note'], true) ? (string)$_POST['kind'] : 'maintenance';
    $on    = trim((string)($_POST['performed_on'] ?? '')) ?: date('Y-m-d');
    if ($title === '') {
        Flash::err('Give the log entry a title.');
    } else {
        Database::insert('maintenance_logs', [
            'project_id'   => $id,
            'user_id'      => Auth::id(),
            'kind'         => $kind,
            'title'        => $title,
            'body'         => trim((string)($_POST['body'] ?? '')) ?: null,
            'performed_on' => $on,
            'created_at'   => now(),
        ]);
        log_activity('project.log', 'project', $id, $title);
        Flash::ok('Logged to the project history.');
    }
    redirect('admin/project.php?id=' . $id);
}

if (post() && ($_POST['do'] ?? '') === 'delete_log') {
    Csrf::check();
    Database::run('DELETE FROM maintenance_logs WHERE id = :l AND project_id = :p',
        ['l' => (int)($_POST['log_id'] ?? 0), 'p' => $id]);
    Flash::ok('Log entry removed.');
    redirect('admin/project.php?id=' . $id);
}

$logs    = Database::all('SELECT m.*, u.name AS author FROM maintenance_logs m
                          LEFT JOIN users u ON u.id = m.user_id
                          WHERE m.project_id = :id ORDER BY m.performed_on DESC, m.id DESC', ['id' => $id]);
$tickets = Database::all('SELECT * FROM tickets WHERE project_id = :id ORDER BY updated_at DESC', ['id' => $id]);
$folio   = $p['portfolio_id'] ? Database::one('SELECT id, title, visibility FROM portfolio WHERE id = :i', ['i' => (int)$p['portfolio_id']]) : null;

$newAccount = $_SESSION['new_account'] ?? null;
unset($_SESSION['new_account']);

$PAGE_TITLE = $p['name'];
$AREA = 'admin';
$PAGE_ACTIONS = '<a class="btn ghost sm" href="projects.php">All projects</a>'
              . '<a class="btn sm" href="projects.php?action=edit&id=' . $id . '">Edit</a>';
require __DIR__ . '/../partials/app_header.php';

/** Renewal card with a bar that fills as the date approaches. */
function renewal_card(string $label, ?string $date, ?string $detail): void
{
    $state = expiry_state($date);
    $days  = days_until($date);
    $pct   = $days === null ? 0 : max(0, min(100, (int)round((1 - $days / 365) * 100)));
    if ($days !== null && $days < 0) { $pct = 100; }
    ?>
    <div class="expiry <?= e($state) ?>">
      <div class="expiry__top"><b><?= e($label) ?></b><span class="expiry__date"><?= e(fdate($date)) ?></span></div>
      <div class="expiry__bar" aria-hidden="true"><i style="--w:<?= $pct ?>%"></i></div>
      <p class="expiry__note"><?= e(expiry_label($date)) ?><?= $detail ? ' · ' . e($detail) : '' ?></p>
    </div>
    <?php
}
?>
<?php if ($newAccount): ?>
  <div class="alert ok">
    <p><b>Client account created.</b> Give these details to the owner — the password is shown once and cannot be recovered.</p>
    <p class="mono">Sign in: <?= e(url('login.php')) ?> &nbsp;·&nbsp; Email: <?= e($newAccount['email']) ?> &nbsp;·&nbsp; Password: <?= e($newAccount['password']) ?></p>
  </div>
<?php endif; ?>

<div class="grid g3">
  <?php renewal_card('Domain',  $p['domain_expires_on'],  $p['domain_name'] ?: $p['domain_registrar']); ?>
  <?php renewal_card('Hosting', $p['hosting_expires_on'], $p['hosting_provider'] ?: $p['hosting_plan']); ?>
  <?php renewal_card('SSL',     $p['ssl_expires_on'],     $p['ssl_issuer']); ?>
</div>

<div class="split">
  <div class="stack">
    <section class="card">
      <div class="card__head"><h2>Maintenance &amp; upgrade history</h2>
        <span class="badge muted"><?= count($logs) ?> <?= count($logs) === 1 ? 'entry' : 'entries' ?></span></div>
      <div class="card__body">
        <form method="post" class="form" style="margin-bottom:22px">
          <?= Csrf::field() ?><input type="hidden" name="do" value="log">
          <div class="row three">
            <label class="field"><span>Type</span>
              <select name="kind">
                <?php foreach (['maintenance' => 'Maintenance', 'upgrade' => 'Upgrade', 'fix' => 'Fix', 'renewal' => 'Renewal', 'note' => 'Note'] as $k => $v): ?>
                  <option value="<?= $k ?>"><?= e($v) ?></option>
                <?php endforeach; ?>
              </select></label>
            <label class="field" style="grid-column:span 2"><span>What was done</span>
              <input name="title" required maxlength="200" placeholder="Upgraded PHP to 8.3 and re-tested checkout"></label>
          </div>
          <label class="field"><span>Detail <small>optional — the client sees this</small></span>
            <textarea name="body" rows="3"></textarea></label>
          <div class="formfoot">
            <label class="field" style="max-width:180px"><span>Performed on</span>
              <input name="performed_on" type="date" value="<?= e(date('Y-m-d')) ?>"></label>
            <button class="btn" type="submit" style="align-self:end">Add to history</button>
          </div>
        </form>

        <?php if (!$logs): ?>
          <p class="hint">Nothing logged yet. Entries appear in the client's portal as the record of work done.</p>
        <?php else: ?>
          <ul class="tl">
            <?php foreach ($logs as $l): ?>
              <li>
                <span class="tl__dot"></span>
                <div class="tl__body">
                  <b><?= e($l['title']) ?> <span class="badge muted"><?= e(label($l['kind'])) ?></span></b>
                  <?php if ($l['body']): ?><p><?= e($l['body']) ?></p><?php endif; ?>
                  <time><?= e(fdate($l['performed_on'])) ?><?= $l['author'] ? ' · ' . e($l['author']) : '' ?></time>
                  <form method="post" style="display:inline-block;margin-left:8px" data-confirm="Remove this log entry?">
                    <?= Csrf::field() ?><input type="hidden" name="do" value="delete_log">
                    <input type="hidden" name="log_id" value="<?= (int)$l['id'] ?>">
                    <button class="linkish" type="submit" style="background:none;border:0;cursor:pointer;font-size:11px;color:var(--tx-4)">remove</button>
                  </form>
                </div>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </section>

    <section class="card">
      <div class="card__head"><h2>Tickets on this project</h2></div>
      <?php if (!$tickets): ?>
        <div class="empty"><p>No tickets raised for this project.</p></div>
      <?php else: ?>
        <div class="tablewrap"><table class="data"><tbody>
          <?php foreach ($tickets as $t): ?>
            <tr>
              <td><a class="linkish t-main" href="ticket.php?id=<?= (int)$t['id'] ?>"><?= e($t['subject']) ?></a>
                  <span class="t-sub mono"><?= e($t['reference']) ?> · <?= e(label($t['category'])) ?></span></td>
              <td class="right"><span class="badge <?= e(status_tone($t['status'])) ?>"><?= e(label($t['status'])) ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody></table></div>
      <?php endif; ?>
    </section>
  </div>

  <div class="stack">
    <section class="card">
      <div class="card__head"><h2>Overview</h2>
        <span class="badge <?= e(status_tone($p['status'])) ?>"><?= e(label($p['status'])) ?></span></div>
      <div class="card__body">
        <table class="data" style="margin:-8px 0">
          <tbody>
            <tr><th>Package</th><td class="right"><?= e($p['package'] ?: '—') ?></td></tr>
            <tr><th>Live URL</th><td class="right"><?php if ($p['site_url']): ?>
              <a class="linkish" href="<?= e($p['site_url']) ?>" target="_blank" rel="noopener noreferrer"><?= e(preg_replace('~^https?://~', '', $p['site_url'])) ?> ↗</a>
              <?php else: ?>—<?php endif; ?></td></tr>
            <tr><th>Started</th><td class="right"><?= e(fdate($p['started_on'])) ?></td></tr>
            <tr><th>Launched</th><td class="right"><?= e(fdate($p['launched_on'])) ?></td></tr>
            <tr><th>Monthly fee</th><td class="right"><?= $p['monthly_fee'] !== null ? e(money($p['monthly_fee'])) : '—' ?></td></tr>
            <tr><th>Email</th><td class="right"><?= e($p['email_provider'] ?: '—') ?><?= (int)$p['email_accounts'] ? ' · ' . (int)$p['email_accounts'] . ' boxes' : '' ?></td></tr>
          </tbody>
        </table>
      </div>
    </section>

    <section class="card">
      <div class="card__head"><h2>Owner</h2></div>
      <div class="card__body">
        <table class="data" style="margin:-8px 0"><tbody>
          <tr><th>Name</th><td class="right"><?= e($p['owner_name'] ?: '—') ?></td></tr>
          <tr><th>Email</th><td class="right"><?= $p['owner_email'] ? '<a class="linkish" href="mailto:' . e($p['owner_email']) . '">' . e($p['owner_email']) . '</a>' : '—' ?></td></tr>
          <tr><th>Phone</th><td class="right"><?= $p['owner_phone'] ? '<a class="linkish" href="tel:' . e($p['owner_phone']) . '">' . e($p['owner_phone']) . '</a>' : '—' ?></td></tr>
          <tr><th>Portal account</th><td class="right">
            <?php if ($p['client_name']): ?>
              <a class="linkish" href="clients.php?action=edit&id=<?= (int)$p['user_id'] ?>"><?= e($p['client_name']) ?></a>
              <span class="badge <?= e(status_tone((string)$p['client_status'])) ?>"><?= e(label((string)$p['client_status'])) ?></span>
            <?php else: ?><span class="muted">Not linked</span><?php endif; ?>
          </td></tr>
        </tbody></table>
      </div>
    </section>

    <section class="card">
      <div class="card__head"><h2>Portfolio</h2></div>
      <div class="card__body">
        <?php if ($folio): ?>
          <p><a class="linkish" href="portfolio.php?action=edit&id=<?= (int)$folio['id'] ?>"><?= e($folio['title']) ?></a></p>
          <p class="hint" style="margin-top:6px">
            <?= $folio['visibility'] === 'public'
                ? 'Public — visible on the site portfolio.'
                : 'Private — visible only inside the admin panel and to this client.' ?>
          </p>
        <?php else: ?>
          <p class="hint">Not linked to a portfolio entry.
            <a class="linkish" href="portfolio.php?action=new">Create one</a> and attach it from the project editor.</p>
        <?php endif; ?>
      </div>
    </section>

    <?php if ($p['notes']): ?>
      <section class="card">
        <div class="card__head"><h2>Internal notes</h2></div>
        <div class="card__body"><p class="dim" style="white-space:pre-wrap"><?= e($p['notes']) ?></p></div>
      </section>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/../partials/app_footer.php'; ?>
