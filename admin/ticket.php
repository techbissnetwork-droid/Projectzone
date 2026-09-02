<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/guard.php';
$me = require_admin();

$id = (int)($_GET['id'] ?? 0);
$t  = Database::one('SELECT t.*, u.name AS client_name, u.email AS client_email, p.name AS project_name, p.id AS pid
                     FROM tickets t LEFT JOIN users u ON u.id = t.user_id
                     LEFT JOIN projects p ON p.id = t.project_id WHERE t.id = :id', ['id' => $id]);
if (!$t) {
    http_response_code(404);
    exit('Ticket not found.');
}

if (post()) {
    Csrf::check();
    $body   = trim((string)($_POST['body'] ?? ''));
    $status = (string)($_POST['status'] ?? $t['status']);
    $prio   = (string)($_POST['priority'] ?? $t['priority']);
    $valid  = ['open','in_progress','answered','resolved','closed'];
    $update = ['updated_at' => now()];

    if (in_array($status, $valid, true))                        $update['status']   = $status;
    if (in_array($prio, ['low','normal','high','urgent'], true)) $update['priority'] = $prio;

    if ($body !== '') {
        Database::insert('ticket_messages', [
            'ticket_id'  => $id,
            'user_id'    => (int)$me['id'],
            'body'       => $body,
            'is_staff'   => 1,
            'created_at' => now(),
        ]);
        $update['last_reply_by'] = 'staff';
        if (($update['status'] ?? $t['status']) === 'open') {
            $update['status'] = 'answered';
        }
        log_activity('ticket.reply', 'ticket', $id, $t['reference']);
        Flash::ok('Reply sent.');
    } elseif (count($update) > 1) {
        Flash::ok('Ticket updated.');
    } else {
        Flash::err('Write a reply, or change the status.');
    }
    Database::update('tickets', $update, $id);
    redirect('admin/ticket.php?id=' . $id);
}

$messages = Database::all('SELECT m.*, u.name AS author, u.role FROM ticket_messages m
                           LEFT JOIN users u ON u.id = m.user_id
                           WHERE m.ticket_id = :id ORDER BY m.created_at ASC, m.id ASC', ['id' => $id]);

$PAGE_TITLE = $t['subject'];
$AREA = 'admin';
$PAGE_ACTIONS = '<a class="btn ghost sm" href="tickets.php">All tickets</a>';
require __DIR__ . '/../partials/app_header.php';
?>
<div class="split">
  <div class="stack">
    <section class="card">
      <div class="card__head">
        <h2><?= e($t['reference']) ?></h2>
        <span class="badge <?= e(status_tone($t['status'])) ?>"><?= e(label($t['status'])) ?></span>
        <span class="badge muted"><?= e(label($t['category'])) ?></span>
      </div>
      <div class="card__body">
        <div class="thread">
          <?php foreach ($messages as $m): ?>
            <article class="msg <?= $m['is_staff'] ? 'staff' : '' ?>">
              <header class="msg__head">
                <b><?= e($m['author'] ?? 'Removed user') ?></b>
                <?php if ($m['is_staff']): ?><span class="badge info">Team</span><?php endif; ?>
                <time><?= e(ftime($m['created_at'])) ?></time>
              </header>
              <div class="msg__body"><?= e($m['body']) ?></div>
            </article>
          <?php endforeach; ?>
          <?php if (!$messages): ?><p class="hint">No messages on this ticket yet.</p><?php endif; ?>
        </div>
      </div>
    </section>

    <section class="card">
      <div class="card__head"><h2>Reply</h2></div>
      <div class="card__body">
        <form method="post" class="form">
          <?= Csrf::field() ?>
          <label class="field"><span>Message <small>the client is shown this immediately</small></span>
            <textarea name="body" rows="6" placeholder="Write your reply…"></textarea></label>
          <div class="row two">
            <label class="field"><span>Set status</span>
              <select name="status">
                <?php foreach (['open','in_progress','answered','resolved','closed'] as $s): ?>
                  <option value="<?= $s ?>"<?= $t['status'] === $s ? ' selected' : '' ?>><?= e(label($s)) ?></option>
                <?php endforeach; ?>
              </select></label>
            <label class="field"><span>Priority</span>
              <select name="priority">
                <?php foreach (['low','normal','high','urgent'] as $s): ?>
                  <option value="<?= $s ?>"<?= $t['priority'] === $s ? ' selected' : '' ?>><?= e(label($s)) ?></option>
                <?php endforeach; ?>
              </select></label>
          </div>
          <div class="formfoot"><button class="btn" type="submit">Send reply</button></div>
        </form>
      </div>
    </section>
  </div>

  <div class="stack">
    <section class="card">
      <div class="card__head"><h2>Details</h2></div>
      <div class="card__body">
        <table class="data" style="margin:-8px 0"><tbody>
          <tr><th>Client</th><td class="right">
            <?= $t['user_id'] ? '<a class="linkish" href="clients.php?action=edit&id=' . (int)$t['user_id'] . '">' . e((string)$t['client_name']) . '</a>' : '—' ?></td></tr>
          <tr><th>Email</th><td class="right"><?= $t['client_email'] ? '<a class="linkish" href="mailto:' . e($t['client_email']) . '">' . e($t['client_email']) . '</a>' : '—' ?></td></tr>
          <tr><th>Project</th><td class="right">
            <?= $t['pid'] ? '<a class="linkish" href="project.php?id=' . (int)$t['pid'] . '">' . e((string)$t['project_name']) . '</a>' : '—' ?></td></tr>
          <tr><th>Opened</th><td class="right"><?= e(ftime($t['created_at'])) ?></td></tr>
          <tr><th>Last activity</th><td class="right"><?= e(ftime($t['updated_at'])) ?></td></tr>
          <tr><th>Priority</th><td class="right"><span class="badge <?= in_array($t['priority'], ['urgent','high'], true) ? 'danger' : 'muted' ?>"><?= e(label($t['priority'])) ?></span></td></tr>
        </tbody></table>
      </div>
    </section>
  </div>
</div>
<?php require __DIR__ . '/../partials/app_footer.php'; ?>
