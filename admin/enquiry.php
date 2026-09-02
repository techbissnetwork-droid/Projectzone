<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_installed();
require_staff();
require_once __DIR__ . '/_layout.php';

$id = get_int('id');
$e  = db_one('SELECT * FROM enquiries WHERE id = ?', [$id]);
if (!$e) {
    flash('That enquiry no longer exists.', 'bad');
    redirect('enquiries.php');
}

/* Opening a new enquiry marks it read. */
if ($e['status'] === 'new') {
    db_update('enquiries', (int) $e['id'], ['status' => 'read']);
    $e['status'] = 'read';
}

if (is_post() && post('action') === 'update') {
    csrf_check();
    $new = post('status');
    if (in_array($new, ['new', 'read', 'quoted', 'won', 'lost'], true)) {
        db_update('enquiries', (int) $e['id'], ['status' => $new, 'admin_notes' => post('admin_notes')]);
        flash('Enquiry updated.');
    }
    redirect('enquiry.php?id=' . $e['id']);
}

if (is_post() && post('action') === 'delete') {
    csrf_check();
    db_delete('enquiries', (int) $e['id']);
    log_activity('Deleted enquiry from ' . $e['name'], 'enquiry', (int) $e['id']);
    flash('Enquiry deleted.');
    redirect('enquiries.php');
}

admin_head('Enquiry from ' . $e['name'], 'enquiries.php');
admin_page_head($e['name'], esc($e['email']), [], [['enquiries.php', 'Enquiries'], [null, $e['name']]]);
?>

<div class="split">
  <div>
    <div class="panel">
      <header><h2>Message</h2></header>
      <div class="pad"><p style="white-space:pre-wrap"><?= esc($e['message']) ?></p></div>
    </div>

    <form method="post" class="admin">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="update">
      <fieldset>
        <legend>Follow-up</legend>
        <div class="f"><label for="status">Status</label>
          <select id="status" name="status">
<?php foreach (['new' => 'New', 'read' => 'Read', 'quoted' => 'Quoted', 'won' => 'Won', 'lost' => 'Lost'] as $v => $l): ?>
            <option value="<?= $v ?>"<?= $e['status'] === $v ? ' selected' : '' ?>><?= $l ?></option>
<?php endforeach; ?>
          </select></div>
        <div class="f"><label for="admin_notes">Private notes</label>
          <textarea id="admin_notes" name="admin_notes"><?= esc((string) $e['admin_notes']) ?></textarea></div>
        <div class="formbar"><button class="btn" type="submit">Save</button></div>
      </fieldset>
    </form>
  </div>

  <div>
    <div class="panel">
      <header><h2>Details</h2></header>
      <div class="pad">
        <div class="kv"><span>Email</span>
          <strong><a href="mailto:<?= esc($e['email']) ?>"><?= esc($e['email']) ?></a></strong></div>
        <div class="kv"><span>Phone</span><strong><?= esc($e['phone'] ?: '—') ?></strong></div>
        <div class="kv"><span>Company</span><strong><?= esc($e['company'] ?: '—') ?></strong></div>
        <div class="kv"><span>Wants</span><strong><?= esc($e['service'] ?: '—') ?></strong></div>
        <div class="kv"><span>Budget</span><strong><?= esc($e['budget'] ?: '—') ?></strong></div>
        <div class="kv"><span>Received</span><strong><?= esc(datetime_human($e['created_at'])) ?></strong></div>
        <div class="kv"><span>Emailed out</span>
          <strong><?= $e['mail_sent'] ? '<span class="pill ok">Yes</span>'
            : '<span class="pill soon">No</span>' ?></strong></div>
      </div>
    </div>

    <div class="panel">
      <header><h2>Reply</h2></header>
      <div class="pad">
        <a class="btn" href="mailto:<?= esc($e['email']) ?>?subject=<?= rawurlencode('Re: your enquiry — ' . setting('site.name')) ?>">Email <?= esc($e['name']) ?></a>
        <p style="color:var(--mute);font-size:13.5px;margin-top:12px">Opens your own mail client.
          Replies are not tracked here — only clients with a portal login get threaded messaging.</p>
      </div>
    </div>

    <div class="panel">
      <header><h2>Danger</h2></header>
      <div class="pad">
        <form method="post" data-confirm="Delete this enquiry permanently?">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="delete">
          <button class="btn danger" type="submit">Delete this enquiry</button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php admin_foot(); ?>
