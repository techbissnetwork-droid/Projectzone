<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/guard.php';
require_admin();

if (post()) {
    Csrf::check();
    $id = (int)($_POST['id'] ?? 0);
    $do = (string)($_POST['do'] ?? '');
    if ($do === 'delete') {
        Database::delete('enquiries', $id);
        Flash::ok('Enquiry deleted.');
    } elseif (in_array($do, ['new', 'read', 'archived'], true)) {
        Database::update('enquiries', ['status' => $do], $id);
        Flash::ok('Enquiry marked ' . $do . '.');
    }
    redirect('admin/enquiries.php');
}

$status = (string)($_GET['status'] ?? '');
$where  = in_array($status, ['new', 'read', 'archived'], true) ? ' WHERE status = :s' : '';
$rows   = Database::all('SELECT * FROM enquiries' . $where . ' ORDER BY created_at DESC',
    $where ? ['s' => $status] : []);

$PAGE_TITLE = 'Enquiries';
$AREA = 'admin';
require __DIR__ . '/../partials/app_header.php';
?>
<div class="filters">
  <?php foreach (['' => 'All', 'new' => 'New', 'read' => 'Read', 'archived' => 'Archived'] as $k => $v): ?>
    <a href="?status=<?= e($k) ?>" class="<?= $status === $k ? 'on' : '' ?>"><?= e($v) ?></a>
  <?php endforeach; ?>
</div>
<?php if (!$rows): ?>
  <section class="card"><div class="empty"><b>Inbox empty</b><p>Messages from the site contact form land here.</p></div></section>
<?php else: ?>
  <div class="stack">
    <?php foreach ($rows as $r): ?>
      <section class="card">
        <div class="card__head">
          <h2><?= e($r['name']) ?></h2>
          <span class="badge <?= $r['status'] === 'new' ? 'info' : 'muted' ?>"><?= e(label($r['status'])) ?></span>
          <?php if ($r['service']): ?><span class="badge muted"><?= e($r['service']) ?></span><?php endif; ?>
          <span class="muted mono" style="margin-left:auto"><?= e(ftime($r['created_at'])) ?></span>
        </div>
        <div class="card__body">
          <p class="msg__body" style="margin-bottom:14px"><?= e($r['message']) ?></p>
          <div class="rowline">
            <a class="btn ghost sm" href="mailto:<?= e($r['email']) ?>">Reply by email</a>
            <?php if ($r['phone']): ?><a class="btn ghost sm" href="tel:<?= e($r['phone']) ?>"><?= e($r['phone']) ?></a><?php endif; ?>
            <span class="muted mono"><?= e($r['email']) ?></span>
            <span style="margin-left:auto"></span>
            <?php foreach (['read' => 'Mark read', 'archived' => 'Archive'] as $k => $v): ?>
              <?php if ($r['status'] !== $k): ?>
                <form method="post"><?= Csrf::field() ?>
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <input type="hidden" name="do" value="<?= $k ?>">
                  <button class="btn ghost sm" type="submit"><?= $v ?></button></form>
              <?php endif; ?>
            <?php endforeach; ?>
            <form method="post" data-confirm="Delete this enquiry?"><?= Csrf::field() ?>
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <input type="hidden" name="do" value="delete">
              <button class="btn danger sm" type="submit">Delete</button></form>
          </div>
        </div>
      </section>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php require __DIR__ . '/../partials/app_footer.php'; ?>
