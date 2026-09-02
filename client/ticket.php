<?php
/** One of the client's own threads. */
require_once __DIR__ . '/../app/bootstrap.php';
require_installed();
require_client();
require_once __DIR__ . '/_layout.php';

$me     = current_user();
$id     = get_int('id');
$ticket = db_one('SELECT * FROM tickets WHERE id = ? AND user_id = ?', [$id, $me['id']]);

if (!$ticket) {
    flash('That request is not on your account.', 'bad');
    redirect('support.php');
}

if (is_post() && post('action') === 'reply') {
    csrf_check();
    $body = post('body');
    if ($body === '') {
        flash('Write something before sending.', 'bad');
    } else {
        db_insert('ticket_messages', [
            'ticket_id'   => (int) $ticket['id'],
            'author_id'   => (int) $me['id'],
            'author_name' => $me['name'],
            'author_type' => 'client',
            'body'        => $body,
            'is_internal' => 0,
            'created_at'  => now(),
        ]);
        db_update('tickets', (int) $ticket['id'], ['status' => 'open', 'updated_at' => now()]);
        mail_ticket_reply($ticket, $body, false, null);
        flash('Sent. We will come back to you.');
    }
    redirect('ticket.php?id=' . $ticket['id']);
}

if (is_post() && post('action') === 'close') {
    csrf_check();
    db_update('tickets', (int) $ticket['id'], ['status' => 'closed', 'updated_at' => now()]);
    flash('Marked as resolved. Reply on it any time to reopen it.');
    redirect('ticket.php?id=' . $ticket['id']);
}

/* Internal notes are never shown to the client. */
$messages = db_all(
    'SELECT * FROM ticket_messages WHERE ticket_id = ? AND is_internal = 0 ORDER BY id',
    [$ticket['id']]
);
$project = $ticket['project_id'] ? db_one('SELECT * FROM projects WHERE id = ?', [$ticket['project_id']]) : null;

client_head($ticket['subject'], 'support.php');
?>

<div class="hero-line">
  <h1><?= esc($ticket['subject']) ?></h1>
  <p><?= esc($ticket['reference']) ?> &middot; <?= esc(ucfirst($ticket['category'])) ?>
     &middot; raised <?= esc(date_human($ticket['created_at'])) ?>
     &middot; <?= status_pill($ticket['status']) ?></p>
</div>

<div class="split">
  <div>
    <div class="thread">
<?php foreach ($messages as $m): ?>
      <div class="msg<?= $m['author_type'] === 'staff' ? ' staff' : '' ?>">
        <div class="who">
          <b><?= $m['author_type'] === 'staff'
              ? esc(setting('site.name', 'TECHBISS')) . ' — ' . esc($m['author_name'])
              : esc($m['author_name']) ?></b>
          <?= esc(datetime_human($m['created_at'])) ?>
        </div>
        <p><?= esc($m['body']) ?></p>
      </div>
<?php endforeach; ?>
    </div>

    <form method="post" class="admin" style="margin-top:20px">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="reply">
      <fieldset>
        <legend>Reply</legend>
        <div class="f">
          <label for="body">Your message</label>
          <textarea id="body" name="body" class="tall" required></textarea>
        </div>
        <div class="formbar"><button class="btn" type="submit">Send</button></div>
      </fieldset>
    </form>
  </div>

  <div>
    <div class="panel">
      <header><h2>This request</h2></header>
      <div class="pad">
        <div class="kv"><span>Reference</span><strong><?= esc($ticket['reference']) ?></strong></div>
        <div class="kv"><span>Type</span><strong><?= esc(ucfirst($ticket['category'])) ?></strong></div>
        <div class="kv"><span>Urgency</span><strong><?= esc(ucfirst($ticket['priority'])) ?></strong></div>
        <div class="kv"><span>Status</span><strong><?= status_pill($ticket['status']) ?></strong></div>
<?php if ($project): ?>
        <div class="kv"><span>Project</span><strong><?= esc($project['name']) ?></strong></div>
<?php endif; ?>
      </div>
    </div>

<?php if ($ticket['status'] !== 'closed'): ?>
    <div class="panel">
      <header><h2>All sorted?</h2></header>
      <div class="pad">
        <p style="color:var(--mute);font-size:14px;margin-bottom:12px">
          Mark it resolved when you are happy. Replying on it later reopens it.</p>
        <form method="post" data-confirm="Mark this request as resolved?">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="close">
          <button class="btn ghost" type="submit">Mark as resolved</button>
        </form>
      </div>
    </div>
<?php endif; ?>
  </div>
</div>

<?php client_foot(); ?>
