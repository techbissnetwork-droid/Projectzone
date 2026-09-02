<?php
require __DIR__ . '/../app/bootstrap.php';
require_installed();
auth_require();
require __DIR__ . '/../app/resources.php';
require __DIR__ . '/_layout.php';

$statuses = ['new' => 'New', 'read' => 'Read', 'replied' => 'Replied', 'closed' => 'Closed'];
$id = (int) ($_GET['id'] ?? 0);
$en = one('SELECT * FROM enquiries WHERE id = :id', ['id' => $id]);
if (!$en) {
    http_response_code(404);
    exit('Enquiry not found.');
}

if (is_post()) {
    csrf_check();
    if (post('action') === 'delete') {
        db_delete('enquiries', $id);
        flash('Enquiry deleted.');
        redirect('admin/enquiries.php');
    }
    $s = post('status');
    db_update('enquiries', $id, [
        'status' => isset($statuses[$s]) ? $s : $en['status'],
        'notes'  => post('notes'),
    ]);
    flash('Saved.');
    redirect('admin/enquiry.php?id=' . $id);
}

/* Opening a new enquiry marks it read, so the counter reflects real attention. */
if ($en['status'] === 'new') {
    db_update('enquiries', $id, ['status' => 'read']);
    $en['status'] = 'read';
}

admin_header('Enquiry from ' . $en['name']);
?>
<p class="acrumb"><a href="<?= e(base_url('admin/enquiries.php')) ?>">← All enquiries</a></p>
<h1><?= e($en['name']) ?></h1>

<div class="asplit">
  <div>
    <div class="apanel">
      <h2>Message</h2>
      <p class="amsg"><?= nl2br(e($en['message'])) ?></p>
    </div>

    <form method="post" class="apanel">
      <?= csrf_field() ?>
      <h2>Your notes</h2>
      <textarea name="notes" rows="5" placeholder="Internal notes — never shown on the website."><?= e($en['notes']) ?></textarea>
      <label for="status">Status</label>
      <select id="status" name="status">
        <?php foreach ($statuses as $k => $label): ?>
          <option value="<?= e($k) ?>"<?= $en['status'] === $k ? ' selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
      <div class="arow">
        <button type="submit">Save</button>
        <button type="submit" name="action" value="delete" class="abtn--danger"
                onclick="return confirm('Delete this enquiry permanently?')">Delete</button>
      </div>
    </form>
  </div>

  <aside>
    <div class="apanel">
      <h2>Details</h2>
      <dl class="adl">
        <dt>Email</dt><dd><a href="mailto:<?= e($en['email']) ?>"><?= e($en['email']) ?></a></dd>
        <dt>Phone</dt><dd><?= $en['phone'] ? '<a href="tel:' . e($en['phone']) . '">' . e($en['phone']) . '</a>' : '—' ?></dd>
        <dt>Business</dt><dd><?= e($en['company'] ?: '—') ?></dd>
        <dt>Needs</dt><dd><?= e($en['service'] ?: '—') ?></dd>
        <dt>Budget</dt><dd><?= e($en['budget'] ?: '—') ?></dd>
        <dt>Received</dt><dd><?= e(date('j M Y, H:i', strtotime($en['created_at']))) ?></dd>
        <dt>Emailed out</dt><dd><?= $en['mailed'] ? 'Yes' : 'No — read it here' ?></dd>
        <dt>IP address</dt><dd><?= e($en['ip'] ?: '—') ?></dd>
      </dl>
      <p><a class="abtn" href="mailto:<?= e($en['email']) ?>?subject=<?= e(rawurlencode('Re: your enquiry')) ?>">Reply by email</a></p>
    </div>
  </aside>
</div>
<?php admin_footer(); ?>
