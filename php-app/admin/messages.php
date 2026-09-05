<?php
/**
 * Contact form enquiries.
 *
 * api/contact.php has always written to contact_messages, but nothing ever
 * read that table — no screen, no notification, no export. "Book a call" is
 * the site's primary call to action, in the header, the footer and on every
 * page, and every lead it produced was buried where no one would see it.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/icons.php';
require_once __DIR__ . '/../includes/admin_layout.php';
require_installed('../install/');

$staff = require_staff();
require_staff_access($staff, 'messages.php');
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if (!csrf_check((string)($_POST['csrf'] ?? ''))) {
        flash('Your session expired — please try again.', 'error');
    } elseif ($action === 'toggle_handled') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('UPDATE contact_messages SET handled_at = IF(handled_at IS NULL, NOW(), NULL) WHERE id = ?')
            ->execute([$id]);
        flash('Message updated.');
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM contact_messages WHERE id = ?')->execute([(int)($_POST['id'] ?? 0)]);
        flash('Message deleted.');
    }
    header('Location: messages.php' . (!empty($_GET['show']) ? '?show=' . urlencode((string)$_GET['show']) : ''));
    exit;
}

$show = ($_GET['show'] ?? 'open') === 'all' ? 'all' : 'open';
$messages = $pdo->query(
    'SELECT * FROM contact_messages'
    . ($show === 'open' ? ' WHERE handled_at IS NULL' : '')
    . ' ORDER BY created_at DESC LIMIT 500'
)->fetchAll();
$openCount = (int)$pdo->query('SELECT COUNT(*) FROM contact_messages WHERE handled_at IS NULL')->fetchColumn();
$totalCount = (int)$pdo->query('SELECT COUNT(*) FROM contact_messages')->fetchColumn();
$token = csrf_token();
?>
<!doctype html>
<html lang="en"<?= palette_attr() . logo_motion_attr() ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=<?= ui_zoom_scale() ?>">
<meta name="robots" content="noindex, nofollow">
<title>Messages — TECHBISS Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/style.css?v=<?= @filemtime(__DIR__ . '/../assets/style.css') ?: '1' ?>">
<?= ui_zoom_style() ?>
<style>.msg-body{ white-space:pre-wrap; font-size:.9rem; color:var(--ink-soft); margin:10px 0 0; }</style>
</head>
<body>
<?= admin_header($staff, 'messages.php') ?>
<main class="admin-page">
  <?= admin_flash_html() ?>
  <div class="admin-toolbar">
    <div><h1 style="margin-bottom:4px;">Messages</h1><p class="lede" style="margin-bottom:0;">Every enquiry from the public contact form.</p></div>
    <div class="tab-labels" style="margin-bottom:0;">
      <a href="messages.php?show=open" class="<?= $show === 'open' ? 'active' : '' ?>"><?= ico('chat') ?> Needs a reply (<?= $openCount ?>)</a>
      <a href="messages.php?show=all" class="<?= $show === 'all' ? 'active' : '' ?>"><?= ico('grid') ?> All (<?= $totalCount ?>)</a>
    </div>
  </div>

  <?php foreach ($messages as $m): ?>
  <div class="card" style="margin-bottom:14px;<?= $m['handled_at'] ? 'opacity:.62;' : '' ?>">
    <div class="flex justify-between items-center" style="flex-wrap:wrap;gap:10px;">
      <div>
        <b><?= e($m['name']) ?></b>
        <?php if ($m['company']): ?><span style="color:var(--ink-faint);"> · <?= e($m['company']) ?></span><?php endif; ?>
        <?php if ($m['need']): ?><br><span class="badge"><?= e($m['need']) ?></span><?php endif; ?>
        <div style="font-size:.82rem;color:var(--ink-faint);margin-top:4px;">
          <a class="card-link" href="mailto:<?= e($m['email']) ?>?subject=<?= urlencode('Re: your enquiry') ?>"><?= ico('mail') ?> <?= e($m['email']) ?></a>
          · <?= e(time_ago($m['created_at'])) ?>
          <?php if ($m['handled_at']): ?> · <span class="badge success">Handled</span><?php endif; ?>
        </div>
      </div>
      <div class="admin-actions-cell">
        <form method="post">
          <input type="hidden" name="action" value="toggle_handled">
          <input type="hidden" name="csrf" value="<?= e($token) ?>">
          <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
          <button class="btn btn-ghost btn-sm" type="submit"><?= $m['handled_at'] ? 'Reopen' : 'Mark handled' ?></button>
        </form>
        <form method="post" onsubmit="return confirm('Delete this message permanently?');">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="csrf" value="<?= e($token) ?>">
          <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
          <button class="icon-btn danger" type="submit" aria-label="Delete"><?= ico('trash') ?></button>
        </form>
      </div>
    </div>
    <p class="msg-body"><?= e($m['message']) ?></p>
  </div>
  <?php endforeach; ?>

  <?php if (!$messages): ?>
  <div class="card" style="text-align:center;padding:36px 24px;">
    <?= blob_icon('chat', 'lg') ?>
    <h3 style="margin:14px 0 4px;"><?= $show === 'open' ? 'Nothing waiting for a reply' : 'No messages yet' ?></h3>
    <p class="lede" style="margin-bottom:0;"><?= $show === 'open' ? 'Every enquiry has been marked handled.' : 'Enquiries from the contact form will appear here.' ?></p>
  </div>
  <?php endif; ?>
</main>
<?= admin_bottomnav($staff, 'messages.php') ?>
</body>
</html>
