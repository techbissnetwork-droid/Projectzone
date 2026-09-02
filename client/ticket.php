<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/guard.php';
$me = require_client();

$id = (int)($_GET['id'] ?? 0);
$t  = Database::one('SELECT t.*, p.name AS project_name FROM tickets t
                     LEFT JOIN projects p ON p.id = t.project_id WHERE t.id = :id', ['id' => $id]);
require_owner($t);

if (post()) {
    Csrf::check();
    $body = trim((string)($_POST['body'] ?? ''));
    if ($t['status'] === 'closed') {
        Flash::err('This ticket is closed. Open a new request instead.');
    } elseif ($body === '') {
        Flash::err('Write a message before sending.');
    } else {
        Database::insert('ticket_messages', [
            'ticket_id'  => $id,
            'user_id'    => (int)$me['id'],
            'body'       => $body,
            'is_staff'   => 0,
            'created_at' => now(),
        ]);
        Database::update('tickets', [
            'status'        => $t['status'] === 'resolved' ? 'open' : $t['status'],
            'last_reply_by' => 'client',
            'updated_at'    => now(),
        ], $id);
        Flash::ok('Message sent.');
    }
    redirect('client/ticket.php?id=' . $id);
}

$messages = Database::all('SELECT m.*, u.name AS author FROM ticket_messages m
                           LEFT JOIN users u ON u.id = m.user_id
                           WHERE m.ticket_id = :id ORDER BY m.created_at ASC, m.id ASC', ['id' => $id]);

$PAGE_TITLE = $t['subject'];
$AREA = 'client';
$PAGE_ACTIONS = '<a class="btn ghost sm" href="tickets.php">All requests</a>';
require __DIR__ . '/../partials/app_header.php';
?>
<div class="split">
  <div class="stack">
    <section class="card">
      <div class="card__head">
        <h2 class="mono"><?= e($t['reference']) ?></h2>
        <span class="badge <?= e(status_tone($t['status'])) ?>"><?= e(label($t['status'])) ?></span>
        <span class="badge muted"><?= e(label($t['category'])) ?></span>
      </div>
      <div class="card__body">
        <div class="thread">
          <?php foreach ($messages as $m): ?>
            <article class="msg <?= $m['is_staff'] ? 'staff' : '' ?>">
              <header class="msg__head">
                <b><?= $m['is_staff'] ? e(Settings::get('site_name', 'TECHBISS')) : e($m['author'] ?? 'You') ?></b>
                <?php if ($m['is_staff']): ?><span class="badge info">Team</span><?php endif; ?>
                <time><?= e(ftime($m['created_at'])) ?></time>
              </header>
              <div class="msg__body"><?= e($m['body']) ?></div>
            </article>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <?php if ($t['status'] !== 'closed'): ?>
      <section class="card">
        <div class="card__head"><h2>Reply</h2></div>
        <div class="card__body">
          <form method="post" class="form">
            <?= Csrf::field() ?>
            <label class="field"><span>Message</span>
              <textarea name="body" rows="5" required placeholder="Add more detail, or answer our question…"></textarea></label>
            <div class="formfoot"><button class="btn" type="submit">Send message</button></div>
          </form>
        </div>
      </section>
    <?php else: ?>
      <div class="alert warn"><p>This request is closed. <a class="linkish" href="tickets.php?action=new">Open a new one</a> if you need anything else.</p></div>
    <?php endif; ?>
  </div>

  <section class="card">
    <div class="card__head"><h2>Details</h2></div>
    <div class="card__body">
      <table class="data" style="margin:-8px 0"><tbody>
        <tr><th>Site</th><td class="right"><?= $t['project_id']
          ? '<a class="linkish" href="project.php?id=' . (int)$t['project_id'] . '">' . e((string)$t['project_name']) . '</a>' : '—' ?></td></tr>
        <tr><th>Type</th><td class="right"><?= e(label($t['category'])) ?></td></tr>
        <tr><th>Urgency</th><td class="right"><?= e(label($t['priority'])) ?></td></tr>
        <tr><th>Opened</th><td class="right"><?= e(ftime($t['created_at'])) ?></td></tr>
        <tr><th>Last update</th><td class="right"><?= e(ftime($t['updated_at'])) ?></td></tr>
      </tbody></table>
    </div>
  </section>
</div>
<?php require __DIR__ . '/../partials/app_footer.php'; ?>
