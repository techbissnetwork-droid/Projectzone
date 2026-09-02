<?php
/** The client's requests, and the form for raising a new one. */
require_once __DIR__ . '/../app/bootstrap.php';
require_installed();
require_client();
require_once __DIR__ . '/_layout.php';

$me       = current_user();
$projects = projects_for_user((int) $me['id']);
$errors   = [];

if (is_post() && post('action') === 'create') {
    csrf_check();

    $subject = post('subject');
    $body    = post('body');
    $cat     = post('category', 'support');
    if (!in_array($cat, ['support', 'maintenance', 'upgrade', 'billing', 'other'], true)) {
        $cat = 'support';
    }
    $priority  = post('priority') === 'urgent' ? 'urgent' : 'normal';
    $projectId = post_int('project_id');

    /* Only ever attach a project this client actually owns. */
    $owned = array_column($projects, 'id');
    if ($projectId && !in_array($projectId, array_map('intval', $owned), true)) {
        $projectId = 0;
    }

    if ($subject === '')          { $errors[] = 'Give the request a subject.'; }
    if (mb_strlen($body) < 5)     { $errors[] = 'Tell us a little more about what you need.'; }

    if (!$errors) {
        $ticketId = db_insert('tickets', [
            'reference'  => reference('TKT'),
            'project_id' => $projectId ?: null,
            'user_id'    => (int) $me['id'],
            'subject'    => $subject,
            'category'   => $cat,
            'priority'   => $priority,
            'status'     => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        db_insert('ticket_messages', [
            'ticket_id'   => $ticketId,
            'author_id'   => (int) $me['id'],
            'author_name' => $me['name'],
            'author_type' => 'client',
            'body'        => $body,
            'is_internal' => 0,
            'created_at'  => now(),
        ]);
        $ticket = db_one('SELECT * FROM tickets WHERE id = ?', [$ticketId]);
        mail_ticket_reply($ticket, $body, false, null);
        log_activity('Client raised ' . $ticket['reference'], 'ticket', $ticketId, $me['name']);
        flash('Request sent. We reply within one business day, and you will see the answer here.');
        redirect('ticket.php?id=' . $ticketId);
    }
}

$isNew   = get('action') === 'new' || $errors;
$tickets = db_all('SELECT * FROM tickets WHERE user_id = ? ORDER BY id DESC', [$me['id']]);

client_head($isNew ? 'Raise a request' : 'Support', 'support.php');
?>

<?php if ($isNew): ?>
<div class="hero-line">
  <h1>Raise a request</h1>
  <p>This goes straight to the people who built your site, not to a queue. We reply within one
     business day.</p>
</div>

<?php foreach ($errors as $e): ?>
<div class="flash bad"><?= esc($e) ?></div>
<?php endforeach; ?>

<div class="split">
  <div>
    <form method="post" class="admin">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create">
      <fieldset>
        <legend>What do you need</legend>
        <div class="grid2">
          <div class="f"><label for="category">Type</label>
            <select id="category" name="category">
<?php
$cats = [
    'support'     => 'Something is broken',
    'maintenance' => 'A change to the site',
    'upgrade'     => 'An upgrade or new feature',
    'billing'     => 'Billing or renewals',
    'other'       => 'Something else',
];
$preset = get('category');
foreach ($cats as $v => $l): ?>
              <option value="<?= $v ?>"<?= $preset === $v || post('category') === $v ? ' selected' : '' ?>><?= $l ?></option>
<?php endforeach; ?>
            </select></div>
          <div class="f"><label for="priority">Urgency</label>
            <select id="priority" name="priority">
              <option value="normal">Normal</option>
              <option value="urgent"<?= post('priority') === 'urgent' ? ' selected' : '' ?>>Urgent — the site is down or unusable</option>
            </select></div>
        </div>
<?php if (count($projects) > 1): ?>
        <div class="f"><label for="project_id">Which project</label>
          <select id="project_id" name="project_id">
<?php foreach ($projects as $p): ?>
            <option value="<?= (int) $p['id'] ?>"><?= esc($p['name']) ?></option>
<?php endforeach; ?>
          </select></div>
<?php elseif ($projects): ?>
        <input type="hidden" name="project_id" value="<?= (int) $projects[0]['id'] ?>">
<?php endif; ?>
        <div class="f"><label for="subject">Subject</label>
          <input id="subject" name="subject" required value="<?= esc(post('subject')) ?>"
                 placeholder="Change the opening hours on the contact page"></div>
        <div class="f"><label for="body">Detail</label>
          <textarea id="body" name="body" class="tall" required
            placeholder="What you need, and where on the site it is. Screenshots can follow by email."><?= esc(post('body')) ?></textarea></div>
        <div class="formbar">
          <button class="btn" type="submit">Send request</button>
          <a class="btn ghost" href="support.php">Cancel</a>
        </div>
      </fieldset>
    </form>
  </div>
  <div>
    <div class="panel">
      <header><h2>What to expect</h2></header>
      <div class="pad" style="color:var(--mute);font-size:14px">
        <p style="margin-bottom:12px">First reply within four business hours, always from someone
          who knows your setup.</p>
        <p style="margin-bottom:12px">Anything that takes your site down is treated as urgent
          whether or not you tick the box.</p>
        <p>If it turns out to be outside your care plan we will say so and quote it before doing
          any work &mdash; never after.</p>
      </div>
    </div>
  </div>
</div>

<?php else: ?>

<div class="hero-line">
  <h1>Support</h1>
  <p>Everything you have raised with us, and where each one stands.</p>
</div>

<p style="margin-bottom:18px"><a class="btn" href="support.php?action=new">Raise a request</a></p>

<div class="panel">
<?php if (!$tickets): ?>
  <div class="empty"><strong>Nothing raised yet</strong>
    <p>Need a change, a fix or an upgrade? Send it here and it goes straight to the team.</p>
    <p style="margin-top:16px"><a class="btn" href="support.php?action=new">Raise a request</a></p></div>
<?php else: ?>
  <div class="pad"><div class="ticketlist">
<?php foreach ($tickets as $t): ?>
    <a class="ticketrow" href="ticket.php?id=<?= (int) $t['id'] ?>">
      <div><b><?= esc($t['subject']) ?></b>
        <div class="meta"><?= esc($t['reference']) ?> &middot; <?= esc(ucfirst($t['category'])) ?>
          &middot; raised <?= esc(date_human($t['created_at'])) ?></div></div>
      <div class="right">
<?php if ($t['priority'] === 'urgent'): ?><span class="pill urgent">Urgent</span><?php endif; ?>
        <?= status_pill($t['status']) ?>
      </div>
    </a>
<?php endforeach; ?>
  </div></div>
<?php endif; ?>
</div>

<?php endif; ?>

<?php client_foot(); ?>
