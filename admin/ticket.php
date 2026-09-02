<?php
/** One support thread, with replies and internal notes. */
require_once __DIR__ . '/../app/bootstrap.php';
require_installed();
require_staff();
require_once __DIR__ . '/_layout.php';

$id     = get_int('id');
$ticket = db_one('SELECT * FROM tickets WHERE id = ?', [$id]);
if (!$ticket) {
    flash('That request no longer exists.', 'bad');
    redirect('tickets.php');
}

$client  = $ticket['user_id'] ? db_one('SELECT * FROM users WHERE id = ?', [$ticket['user_id']]) : null;
$project = $ticket['project_id'] ? db_one('SELECT * FROM projects WHERE id = ?', [$ticket['project_id']]) : null;

if (is_post() && post('action') === 'reply') {
    csrf_check();
    $body     = post('body');
    $internal = post('internal') === '1';
    if ($body === '') {
        flash('Write something before sending.', 'bad');
    } else {
        db_insert('ticket_messages', [
            'ticket_id'   => (int) $ticket['id'],
            'author_id'   => (int) current_user()['id'],
            'author_name' => current_user()['name'],
            'author_type' => 'staff',
            'body'        => $body,
            'is_internal' => $internal ? 1 : 0,
            'created_at'  => now(),
        ]);
        $newStatus = $internal ? $ticket['status'] : 'answered';
        db_update('tickets', (int) $ticket['id'], ['status' => $newStatus, 'updated_at' => now()]);
        if (!$internal) {
            mail_ticket_reply($ticket, $body, true, $client);
        }
        log_activity('Replied to ' . $ticket['reference'], 'ticket', (int) $ticket['id']);
        flash($internal ? 'Internal note added — the client cannot see it.' : 'Reply sent.');
    }
    redirect('ticket.php?id=' . $ticket['id']);
}

if (is_post() && post('action') === 'status') {
    csrf_check();
    $new = post('status');
    if (in_array($new, ['open', 'answered', 'in_progress', 'closed'], true)) {
        db_update('tickets', (int) $ticket['id'], ['status' => $new, 'updated_at' => now()]);
        log_activity('Set ' . $ticket['reference'] . ' to ' . $new, 'ticket', (int) $ticket['id']);
        flash('Status updated.');
    }
    redirect('ticket.php?id=' . $ticket['id']);
}

$messages = db_all('SELECT * FROM ticket_messages WHERE ticket_id = ? ORDER BY id', [$ticket['id']]);

admin_head($ticket['subject'], 'tickets.php');
admin_page_head($ticket['subject'], '', [], [['tickets.php', 'Support'], [null, $ticket['reference']]]);
?>

<div class="split">
  <div>
    <div class="thread">
<?php foreach ($messages as $m): ?>
      <div class="msg<?= $m['author_type'] === 'staff' ? ' staff' : '' ?><?= $m['is_internal'] ? ' internal' : '' ?>">
        <div class="who">
          <b><?= esc($m['author_name'] ?: 'Unknown') ?></b>
          <?= esc(datetime_human($m['created_at'])) ?>
<?php if ($m['is_internal']): ?>
          <span class="pill soon">Internal note — the client cannot see this</span>
<?php endif; ?>
        </div>
        <p><?= esc($m['body']) ?></p>
      </div>
<?php endforeach; ?>
<?php if (!$messages): ?>
      <div class="empty"><strong>Nothing in this thread</strong></div>
<?php endif; ?>
    </div>

    <form method="post" class="admin" style="margin-top:20px">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="reply">
      <fieldset>
        <legend>Reply</legend>
        <div class="f">
          <label for="body">Your message</label>
          <textarea id="body" name="body" class="tall" required
            placeholder="What you did, or what you need from them."></textarea>
        </div>
        <div class="f"><label class="check">
          <input type="checkbox" name="internal" value="1">
          <span>Internal note — keep this between the team, do not email or show the client</span>
        </label></div>
        <div class="formbar"><button class="btn" type="submit">Send</button></div>
      </fieldset>
    </form>
  </div>

  <div>
    <div class="panel">
      <header><h2>Request</h2></header>
      <div class="pad">
        <div class="kv"><span>Reference</span><strong><?= esc($ticket['reference']) ?></strong></div>
        <div class="kv"><span>Type</span><strong><?= esc(ucfirst($ticket['category'])) ?></strong></div>
        <div class="kv"><span>Priority</span><strong><?= esc(ucfirst($ticket['priority'])) ?></strong></div>
        <div class="kv"><span>Raised</span><strong><?= esc(datetime_human($ticket['created_at'])) ?></strong></div>
        <div class="kv"><span>Status</span><strong><?= status_pill($ticket['status']) ?></strong></div>
      </div>
    </div>

    <div class="panel">
      <header><h2>Set status</h2></header>
      <div class="pad">
        <form method="post" class="admin">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="status">
          <div class="f">
            <select name="status">
<?php foreach (['open' => 'Open', 'in_progress' => 'In progress', 'answered' => 'Answered',
                'closed' => 'Closed'] as $v => $l): ?>
              <option value="<?= $v ?>"<?= $ticket['status'] === $v ? ' selected' : '' ?>><?= $l ?></option>
<?php endforeach; ?>
            </select>
          </div>
          <button class="btn ghost" type="submit">Update status</button>
        </form>
      </div>
    </div>

<?php if ($client): ?>
    <div class="panel">
      <header><h2>Client</h2></header>
      <div class="pad">
        <div class="kv"><span>Name</span>
          <strong><a href="client-edit.php?id=<?= (int) $client['id'] ?>"><?= esc($client['name']) ?></a></strong></div>
        <div class="kv"><span>Email</span><strong><?= esc($client['email']) ?></strong></div>
<?php if ($client['phone']): ?>
        <div class="kv"><span>Phone</span><strong><?= esc($client['phone']) ?></strong></div>
<?php endif; ?>
      </div>
    </div>
<?php endif; ?>

<?php if ($project): ?>
    <div class="panel">
      <header><h2>Project</h2></header>
      <div class="pad">
        <div class="kv"><span>Name</span>
          <strong><a href="project-edit.php?id=<?= (int) $project['id'] ?>"><?= esc($project['name']) ?></a></strong></div>
        <div class="kv"><span>Domain</span><strong><?= esc($project['domain'] ?: '—') ?></strong></div>
        <div class="kv"><span>Care plan</span><strong><?= esc($project['care_plan'] ?: 'None') ?></strong></div>
      </div>
    </div>
<?php endif; ?>
  </div>
</div>

<?php admin_foot(); ?>
