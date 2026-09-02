<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/guard.php';
$me = require_client();

$action = (string)($_GET['action'] ?? 'list');

if ($action === 'new') {
    $errors  = [];
    $myProjects = Database::all('SELECT id, name FROM projects WHERE user_id = :u ORDER BY name', ['u' => (int)$me['id']]);
    $preProject = (int)($_GET['project'] ?? 0);
    $preCat     = (string)($_GET['category'] ?? 'support');

    if (post()) {
        Csrf::check();
        $subject = trim((string)($_POST['subject'] ?? ''));
        $body    = trim((string)($_POST['body'] ?? ''));
        $cat     = in_array((string)($_POST['category'] ?? ''), ['support','maintenance','upgrade','billing','other'], true)
                 ? (string)$_POST['category'] : 'support';
        $prio    = in_array((string)($_POST['priority'] ?? ''), ['low','normal','high','urgent'], true)
                 ? (string)$_POST['priority'] : 'normal';
        $pid     = (int)($_POST['project_id'] ?? 0) ?: null;

        if ($subject === '') $errors[] = 'Give your request a short subject.';
        if ($body === '')    $errors[] = 'Describe what you need.';
        if ($pid !== null && !in_array($pid, array_map('intval', array_column($myProjects, 'id')), true)) {
            $errors[] = 'Choose one of your own sites.';
        }

        if (!$errors) {
            $tid = Database::insert('tickets', [
                'reference'     => reference('TKT'),
                'user_id'       => (int)$me['id'],
                'project_id'    => $pid,
                'subject'       => $subject,
                'category'      => $cat,
                'priority'      => $prio,
                'status'        => 'open',
                'last_reply_by' => 'client',
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
            Database::insert('ticket_messages', [
                'ticket_id'  => $tid,
                'user_id'    => (int)$me['id'],
                'body'       => $body,
                'is_staff'   => 0,
                'created_at' => now(),
            ]);
            log_activity('ticket.create', 'ticket', $tid, $subject);
            Flash::ok('Your request was sent. We reply within one business day.');
            redirect('client/ticket.php?id=' . $tid);
        }
    }

    $PAGE_TITLE = 'New request';
    $AREA = 'client';
    require __DIR__ . '/../partials/app_header.php';
    ?>
    <?php if ($errors): ?>
      <div class="alert err"><?php foreach ($errors as $er): ?><p><?= e($er) ?></p><?php endforeach; ?></div>
    <?php endif; ?>
    <form method="post" class="form" style="max-width:720px">
      <?= Csrf::field() ?>
      <div class="fieldset">
        <p class="legend">What do you need?</p>
        <div class="row two">
          <label class="field"><span>Type</span>
            <select name="category">
              <?php foreach (['support' => 'Something is broken', 'maintenance' => 'Maintenance', 'upgrade' => 'Upgrade or new feature',
                              'billing' => 'Billing or renewal', 'other' => 'Something else'] as $k => $v): ?>
                <option value="<?= $k ?>"<?= ($_POST['category'] ?? $preCat) === $k ? ' selected' : '' ?>><?= e($v) ?></option>
              <?php endforeach; ?>
            </select></label>
          <label class="field"><span>About which site?</span>
            <select name="project_id">
              <option value="0">Not about a specific site</option>
              <?php foreach ($myProjects as $mp): ?>
                <option value="<?= (int)$mp['id'] ?>"<?= (int)($_POST['project_id'] ?? $preProject) === (int)$mp['id'] ? ' selected' : '' ?>>
                  <?= e($mp['name']) ?></option>
              <?php endforeach; ?>
            </select></label>
        </div>
        <label class="field"><span>Subject</span>
          <input name="subject" required maxlength="220" value="<?= e(old('subject')) ?>"
                 placeholder="Contact form is not sending email"></label>
        <label class="field"><span>Details <small>what you expected, and what happens instead</small></span>
          <textarea name="body" rows="8" required placeholder="Describe it in your own words — screenshots and links help."><?= e(old('body')) ?></textarea></label>
        <label class="field" style="max-width:240px"><span>How urgent is it?</span>
          <select name="priority">
            <?php foreach (['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent — site is down'] as $k => $v): ?>
              <option value="<?= $k ?>"<?= ($_POST['priority'] ?? 'normal') === $k ? ' selected' : '' ?>><?= e($v) ?></option>
            <?php endforeach; ?>
          </select></label>
      </div>
      <div class="formfoot">
        <button class="btn" type="submit">Send request</button>
        <a class="btn ghost" href="tickets.php">Cancel</a>
      </div>
    </form>
    <?php
    require __DIR__ . '/../partials/app_footer.php';
    exit;
}

$show = (string)($_GET['show'] ?? 'open');
$cond = $show === 'closed' ? "AND t.status IN ('resolved','closed')" : "AND t.status NOT IN ('resolved','closed')";
$tickets = Database::all(
    "SELECT t.*, p.name AS project_name,
            (SELECT COUNT(*) FROM ticket_messages m WHERE m.ticket_id = t.id) AS replies
     FROM tickets t LEFT JOIN projects p ON p.id = t.project_id
     WHERE t.user_id = :u $cond ORDER BY t.updated_at DESC",
    ['u' => (int)$me['id']]
);

$PAGE_TITLE = 'Support';
$AREA = 'client';
$PAGE_ACTIONS = '<a class="btn sm" href="tickets.php?action=new">New request</a>';
require __DIR__ . '/../partials/app_header.php';
?>
<div class="filters">
  <a href="?show=open" class="<?= $show !== 'closed' ? 'on' : '' ?>">Open</a>
  <a href="?show=closed" class="<?= $show === 'closed' ? 'on' : '' ?>">Resolved &amp; closed</a>
</div>
<section class="card">
  <?php if (!$tickets): ?>
    <div class="empty"><b><?= $show === 'closed' ? 'Nothing closed yet' : 'No open requests' ?></b>
      <p>Ask us for maintenance, an upgrade, or help with anything on your site.</p>
      <a class="btn sm" href="tickets.php?action=new">New request</a></div>
  <?php else: ?>
    <div class="tablewrap"><table class="data">
      <thead><tr><th>Subject</th><th>Site</th><th>Type</th><th>Status</th><th class="right">Last update</th></tr></thead>
      <tbody>
      <?php foreach ($tickets as $t): ?>
        <tr>
          <td><a class="linkish t-main" href="ticket.php?id=<?= (int)$t['id'] ?>"><?= e($t['subject']) ?></a>
              <span class="t-sub mono"><?= e($t['reference']) ?> · <?= (int)$t['replies'] ?> messages
              <?= $t['last_reply_by'] === 'staff' ? ' · we replied' : '' ?></span></td>
          <td><?= $t['project_name'] ? e($t['project_name']) : '<span class="muted">—</span>' ?></td>
          <td><span class="badge muted"><?= e(label($t['category'])) ?></span></td>
          <td><span class="badge <?= e(status_tone($t['status'])) ?>"><?= e(label($t['status'])) ?></span></td>
          <td class="right nowrap muted"><?= e(ftime($t['updated_at'])) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/../partials/app_footer.php'; ?>
