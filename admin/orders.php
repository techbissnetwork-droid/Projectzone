<?php
/**
 * Marketplace orders and newsletter subscribers.
 *
 * product_orders and newsletter_subscribers were both written by the public
 * API and read by nothing — staff could not see what had sold, to whom, or
 * re-issue a download link when a customer wrote in asking for one, and the
 * newsletter list existed only as rows nobody could reach.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/icons.php';
require_once __DIR__ . '/../includes/admin_layout.php';
require_installed('../install/');

$staff = require_staff();
require_staff_access($staff, 'orders.php');
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if (!csrf_check((string)($_POST['csrf'] ?? ''))) {
        flash('Your session expired — please try again.', 'error');
    } elseif ($action === 'reissue') {
        // Download links expire; this is how staff answer "my link stopped
        // working" without having to touch the database.
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare(
            'UPDATE product_orders SET download_token = ?, download_expires_at = (NOW() + INTERVAL 30 DAY), download_count = 0 WHERE id = ?'
        )->execute([bin2hex(random_bytes(24)), $id]);
        flash('A fresh 30-day download link has been issued.');
    } elseif ($action === 'unsubscribe') {
        $pdo->prepare('DELETE FROM newsletter_subscribers WHERE id = ?')->execute([(int)($_POST['id'] ?? 0)]);
        flash('Subscriber removed.');
    }
    header('Location: orders.php' . (!empty($_GET['tab']) ? '?tab=' . urlencode((string)$_GET['tab']) : ''));
    exit;
}

$tab = ($_GET['tab'] ?? 'orders') === 'newsletter' ? 'newsletter' : 'orders';

// CSV export, so the newsletter list can actually be used somewhere.
if ($tab === 'newsletter' && ($_GET['export'] ?? '') === 'csv') {
    $rows = $pdo->query('SELECT email, created_at FROM newsletter_subscribers ORDER BY created_at DESC')->fetchAll();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="newsletter-subscribers.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['email', 'subscribed_at']);
    foreach ($rows as $r) {
        fputcsv($out, [$r['email'], $r['created_at']]);
    }
    fclose($out);
    exit;
}

$orders = $pdo->query(
    'SELECT o.*, p.name AS product_name, c.name AS customer_name, c.email AS customer_email
     FROM product_orders o
     JOIN products p ON p.id = o.product_id
     JOIN customers c ON c.id = o.customer_id
     ORDER BY o.created_at DESC LIMIT 500'
)->fetchAll();
$revenueCents = (int)$pdo->query('SELECT COALESCE(SUM(price_cents),0) FROM product_orders')->fetchColumn();
$subscribers = $pdo->query('SELECT * FROM newsletter_subscribers ORDER BY created_at DESC LIMIT 500')->fetchAll();
$subCount = (int)$pdo->query('SELECT COUNT(*) FROM newsletter_subscribers')->fetchColumn();
$paymentsOn = get_setting('payments_enabled', 'off') === 'on';
$token = csrf_token();
?>
<!doctype html>
<html lang="en"<?= palette_attr() . logo_motion_attr() ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=<?= ui_zoom_scale() ?>">
<meta name="robots" content="noindex, nofollow">
<title>Orders — TECHBISS Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/style.css?v=<?= ASSET_VERSION ?>">
<?= ui_zoom_style() ?>
</head>
<body>
<?= admin_header($staff, 'orders.php') ?>
<main class="admin-page">
  <?= admin_flash_html() ?>
  <div class="admin-toolbar">
    <div><h1 style="margin-bottom:4px;">Orders &amp; subscribers</h1><p class="lede" style="margin-bottom:0;">Marketplace downloads bought from the public site, and the newsletter list.</p></div>
    <div class="tab-labels" style="margin-bottom:0;">
      <a href="orders.php?tab=orders" class="<?= $tab === 'orders' ? 'active' : '' ?>"><?= ico('star') ?> Orders (<?= count($orders) ?>)</a>
      <a href="orders.php?tab=newsletter" class="<?= $tab === 'newsletter' ? 'active' : '' ?>"><?= ico('mail') ?> Newsletter (<?= $subCount ?>)</a>
    </div>
  </div>

  <?php if ($tab === 'orders'): ?>
    <?php if ($paymentsOn): ?>
    <p class="badge danger" style="margin-bottom:20px;"><?= ico('shield') ?> Checkout is ON but no payment processor is connected — these orders were not paid for. Turn it off in Settings &gt; Email &amp; checkout.</p>
    <?php endif; ?>
    <div class="grid grid-2" style="margin-bottom:22px;">
      <div class="card tilt"><div class="stat-row"><?= blob_icon('star', 'sm', true) ?><span class="label">Orders placed</span></div><div class="stat"><div class="num"><?= number_format(count($orders)) ?></div></div></div>
      <div class="card tilt"><div class="stat-row"><?= blob_icon('chart', 'sm', true) ?><span class="label">Order value recorded</span></div><div class="stat"><div class="num">$<?= number_format($revenueCents / 100, 2) ?></div></div></div>
    </div>
    <div class="card">
      <div class="table-wrap"><table><thead><tr><th>Ref</th><th>Product</th><th>Customer</th><th>Value</th><th>Downloads</th><th>Link</th><th>Placed</th><th></th></tr></thead><tbody>
        <?php foreach ($orders as $o): ?>
        <?php $expired = $o['download_expires_at'] !== null && strtotime((string)$o['download_expires_at']) < time(); ?>
        <tr>
          <td style="font-weight:600;"><?= e($o['order_ref']) ?></td>
          <td><?= e($o['product_name']) ?></td>
          <td><a class="card-link" href="mailto:<?= e($o['customer_email']) ?>"><?= e($o['customer_name']) ?></a><br><span style="font-size:.8rem;color:var(--ink-faint);"><?= e($o['customer_email']) ?></span></td>
          <td>$<?= number_format((int)$o['price_cents'] / 100, 2) ?></td>
          <td><?= (int)$o['download_count'] ?></td>
          <td><span class="badge <?= $expired ? 'danger' : 'success' ?>"><?= $expired ? 'Expired' : 'Active' ?></span></td>
          <td style="color:var(--ink-faint);"><?= e(time_ago($o['created_at'])) ?></td>
          <td class="admin-actions-cell">
            <form method="post" onsubmit="return confirm('Issue a fresh 30-day download link? The old one stops working immediately.');">
              <input type="hidden" name="action" value="reissue">
              <input type="hidden" name="csrf" value="<?= e($token) ?>">
              <input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
              <button class="btn btn-ghost btn-sm" type="submit">Re-issue link</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$orders): ?><tr><td colspan="8" style="color:var(--ink-faint);">No orders yet.</td></tr><?php endif; ?>
      </tbody></table></div>
    </div>
  <?php else: ?>
    <div class="admin-toolbar" style="margin-bottom:16px;">
      <p class="lede" style="margin-bottom:0;"><?= number_format($subCount) ?> subscriber<?= $subCount === 1 ? '' : 's' ?>.</p>
      <?php if ($subCount): ?><a class="btn btn-ghost" href="orders.php?tab=newsletter&amp;export=csv"><?= ico('arrow') ?> Export CSV</a><?php endif; ?>
    </div>
    <div class="card">
      <div class="table-wrap"><table><thead><tr><th>Email</th><th>Subscribed</th><th></th></tr></thead><tbody>
        <?php foreach ($subscribers as $sub): ?>
        <tr>
          <td><a class="card-link" href="mailto:<?= e($sub['email']) ?>"><?= e($sub['email']) ?></a></td>
          <td style="color:var(--ink-faint);"><?= e(time_ago($sub['created_at'])) ?></td>
          <td class="admin-actions-cell">
            <form method="post" onsubmit="return confirm('Remove this subscriber?');">
              <input type="hidden" name="action" value="unsubscribe">
              <input type="hidden" name="csrf" value="<?= e($token) ?>">
              <input type="hidden" name="id" value="<?= (int)$sub['id'] ?>">
              <button class="icon-btn danger" type="submit" aria-label="Remove"><?= ico('trash') ?></button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$subscribers): ?><tr><td colspan="3" style="color:var(--ink-faint);">No subscribers yet.</td></tr><?php endif; ?>
      </tbody></table></div>
    </div>
  <?php endif; ?>
</main>
<?= admin_bottomnav($staff, 'orders.php') ?>
</body>
</html>
