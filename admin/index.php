<?php
require_once __DIR__ . '/../includes/db.php';

/**
 * A deploy that replaces some files but not others leaves this page calling
 * into an includes/ folder from an older release, which PHP answers with a
 * blank 500 and no clue. Say what actually happened instead. asset_version()
 * is the canary: it lives in includes/db.php and arrived with this release.
 */
if (!function_exists('asset_version')) {
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    exit('<!doctype html><html><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>Update incomplete</title></head>'
        . '<body style="font:16px/1.6 system-ui,sans-serif;max-width:640px;margin:12vh auto;padding:0 24px;color:#2c2318;">'
        . '<h1 style="font-size:1.4rem;">This update was uploaded incomplete</h1>'
        . '<p>The <code>includes/</code> folder on this server is from an older release than the rest of the files, '
        . 'so the site is calling functions that do not exist yet.</p>'
        . '<p><b>Fix:</b> upload the release again and let it overwrite <em>every</em> folder — '
        . '<code>includes/</code>, <code>assets/</code>, <code>admin/</code>, <code>api/</code> and <code>install/</code>. '
        . 'Some file managers silently skip folders that already exist, which is how this happens.</p>'
        . '<p style="color:#786a5b;font-size:.9rem;">Nothing is broken in the database — this is only about which files are on disk.</p>'
        . '</body></html>');
}
require_once __DIR__ . '/../includes/icons.php';
require_once __DIR__ . '/../includes/admin_layout.php';
require_installed('../install/');

$staff = require_staff(); // redirects to login.php if not signed in
$pdo = db();

// Every widget below is gated by the same permission its own admin page
// requires, so this dashboard can never show a staff member data they're
// not allowed to open the real section for.
$canUsers = staff_can($staff, 'users.php');
$canBusinesses = staff_can($staff, 'businesses.php');
$canProducts = staff_can($staff, 'products.php');
$canTickets = staff_can($staff, 'tickets.php');
$canStaff = staff_can($staff, 'staff.php');
$canMessages = staff_can($staff, 'messages.php');
$canOrders = staff_can($staff, 'orders.php');

$totalUsers = $canUsers ? (int)$pdo->query('SELECT COUNT(*) c FROM customers')->fetch()['c'] : 0;
$businessCount = $canBusinesses ? (int)$pdo->query('SELECT COUNT(*) c FROM businesses')->fetch()['c'] : 0;
$mrrCents = $canBusinesses ? (int)$pdo->query("SELECT COALESCE(SUM(price_cents),0) s FROM businesses WHERE price_type='recurring'")->fetch()['s'] : 0;
$productCount = $canProducts ? (int)$pdo->query('SELECT COUNT(*) c FROM products')->fetch()['c'] : 0;
$totalTickets = $canTickets ? (int)$pdo->query('SELECT COUNT(*) c FROM tickets')->fetch()['c'] : 0;
$openTickets = $canTickets ? (int)$pdo->query("SELECT COUNT(*) c FROM tickets WHERE status != 'Closed'")->fetch()['c'] : 0;
$openMessages = $canMessages ? (int)$pdo->query('SELECT COUNT(*) c FROM contact_messages WHERE handled_at IS NULL')->fetch()['c'] : 0;
$orderCount = $canOrders ? (int)$pdo->query('SELECT COUNT(*) c FROM product_orders')->fetch()['c'] : 0;

$EXPIRY_FIELDS = ['domain_expires_at' => 'Domain', 'hosting_expires_at' => 'Hosting', 'ssl_expires_at' => 'SSL', 'email_expires_at' => 'Email'];
$renewals = [];
if ($canBusinesses) {
    $renewalRows = $pdo->query(
        'SELECT p.id, p.title, p.domain_expires_at, p.hosting_expires_at, p.ssl_expires_at, p.email_expires_at,
                b.name AS business_name, b.contact_email, b.contact_phone
         FROM projects p JOIN businesses b ON b.id = p.business_id'
    )->fetchAll();
    foreach ($renewalRows as $row) {
        $due = [];
        foreach ($EXPIRY_FIELDS as $field => $label) {
            if (!$row[$field]) {
                continue;
            }
            $days = (int)floor((strtotime($row[$field]) - strtotime(date('Y-m-d'))) / 86400);
            if ($days <= 30) {
                $due[] = ['label' => $label, 'days' => $days];
            }
        }
        if ($due) {
            usort($due, fn($a, $b) => $a['days'] <=> $b['days']);
            $row['due'] = $due;
            $renewals[] = $row;
        }
    }
    usort($renewals, fn($a, $b) => $a['due'][0]['days'] <=> $b['due'][0]['days']);
    $renewals = array_slice($renewals, 0, 8);
}

?>
<!doctype html>
<html lang="en"<?= palette_attr() . logo_motion_attr() ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=<?= ui_zoom_scale() ?>">
<title>Dashboard — TECHBISS Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/style.css?v=<?= asset_version() ?>">
<?= ui_zoom_style() ?>
</head>
<body>
<?= admin_header($staff, 'index.php') ?>
<main class="admin-page">
  <?= admin_flash_html() ?>

  <?php if (assets_look_stale()): ?>
  <?php $markers = asset_markers(); ?>
  <div class="card" style="border-color:var(--danger);margin-bottom:20px;">
    <div class="card-head"><?= blob_icon('shield', 'sm', true) ?><h3 style="color:var(--danger);">Your upload looks incomplete</h3></div>
    <p style="font-size:.9rem;margin-bottom:10px;">
      The stylesheet and script on this server don't come from the same release, so the site is running new PHP against old design files. That shows up as a broken-looking layout — a footer in the wrong shape, a menu covering the bottom bar — even though the code is correct.
    </p>
    <p style="font-size:.9rem;margin-bottom:10px;">
      <?php foreach ($markers as $file => $mark): ?>
      <code><?= e($file) ?></code> — <?= $mark === '' ? '<b style="color:var(--danger);">no version marker (old file)</b>' : e($mark) ?><br>
      <?php endforeach; ?>
    </p>
    <p style="font-size:.9rem;margin-bottom:0;"><b>Fix:</b> re-upload the whole <code>assets/</code> folder, overwriting what's there. Some file managers skip a folder that already exists.</p>
  </div>
  <?php endif; ?>

  <span class="badge warning" style="margin-bottom:14px;"><?= ico('shield') ?> Internal — staff access only</span>
  <h1 style="max-width:22ch;margin-bottom:6px;">Every client, every ticket, one screen.</h1>
  <p class="lede" style="margin-bottom:28px;">All figures below are live from the database.</p>

  <?php if (!$canUsers && !$canBusinesses && !$canProducts && !$canTickets && !$canStaff && !$canMessages && !$canOrders): ?>
  <div class="card" style="text-align:center;padding:36px 24px;"><?= blob_icon('shield', 'lg') ?><h3 style="margin:14px 0 4px;">Nothing to show here yet</h3><p class="lede" style="margin-bottom:0;">You don't have access to any of this dashboard's sections. Ask an admin to grant you a section from Staff &amp; permissions.</p></div>
  <?php endif; ?>

  <div class="grid grid-4" style="margin-bottom:28px;">
    <?php if ($canUsers): ?>
    <a class="card tilt" href="users.php" style="display:block;color:inherit;"><div class="stat-row"><?= blob_icon('users', 'sm', true) ?><span class="label">Total users</span></div><div class="stat"><div class="num"><?= number_format($totalUsers) ?></div></div></a>
    <?php endif; ?>
    <?php if ($canBusinesses): ?>
    <a class="card tilt" href="businesses.php" style="display:block;color:inherit;"><div class="stat-row"><?= blob_icon('box', 'sm', true) ?><span class="label">Businesses on platform</span></div><div class="stat"><div class="num"><?= number_format($businessCount) ?></div></div></a>
    <div class="card tilt"><div class="stat-row"><?= blob_icon('chart', 'sm', true) ?><span class="label">Monthly recurring revenue</span></div><div class="stat"><div class="num">$<?= number_format($mrrCents / 100, 0) ?></div></div></div>
    <?php endif; ?>
    <?php if ($canProducts): ?>
    <a class="card tilt" href="products.php" style="display:block;color:inherit;"><div class="stat-row"><?= blob_icon('star', 'sm', true) ?><span class="label">Marketplace products live</span></div><div class="stat"><div class="num"><?= number_format($productCount) ?></div></div></a>
    <?php endif; ?>
    <?php if ($canMessages): ?>
    <a class="card tilt" href="messages.php" style="display:block;color:inherit;"><div class="stat-row"><?= blob_icon('mail', 'sm', true) ?><span class="label">Enquiries needing a reply</span></div><div class="stat"><div class="num"><?= number_format($openMessages) ?></div></div></a>
    <?php endif; ?>
    <?php if ($canOrders): ?>
    <a class="card tilt" href="orders.php" style="display:block;color:inherit;"><div class="stat-row"><?= blob_icon('star', 'sm', true) ?><span class="label">Marketplace orders</span></div><div class="stat"><div class="num"><?= number_format($orderCount) ?></div></div></a>
    <?php endif; ?>
    <?php if ($canTickets): ?>
    <a class="card tilt" href="tickets.php" style="display:block;color:inherit;"><div class="stat-row"><?= blob_icon('chat', 'sm', true) ?><span class="label">Total tickets</span></div><div class="stat"><div class="num"><?= number_format($totalTickets) ?></div></div></a>
    <a class="card tilt" href="tickets.php" style="display:block;color:inherit;"><div class="stat-row"><?= blob_icon('chat', 'sm', true) ?><span class="label">Open support tickets</span></div><div class="stat"><div class="num"><?= number_format($openTickets) ?></div></div></a>
    <?php endif; ?>
  </div>

  <?php if ($canBusinesses && $renewals): ?>
  <div class="card" style="margin-bottom:22px;">
    <div class="card-head" style="justify-content:space-between;"><div class="flex items-center gap-12"><?= blob_icon('calendar', 'sm', true) ?><h3>Renewals due soon</h3></div></div>
    <?php foreach ($renewals as $r): ?>
    <?php
      $soonest = $r['due'][0];
      $waDigits = preg_replace('/\D+/', '', (string)($r['contact_phone'] ?? ''));
    ?>
    <div class="flex justify-between items-center" style="padding:12px 0;border-bottom:1px solid var(--border-soft);flex-wrap:wrap;gap:10px;">
      <div>
        <b style="font-size:.9rem;"><?= e($r['business_name']) ?></b> <span style="color:var(--ink-faint);font-size:.85rem;">— <?= e($r['title']) ?></span><br>
        <span class="badge <?= $soonest['days'] < 0 ? 'danger' : 'warning' ?>"><?= e($soonest['label']) ?> <?= $soonest['days'] < 0 ? 'overdue' : 'in ' . $soonest['days'] . 'd' ?></span>
      </div>
      <div class="flex gap-8">
        <?php if ($r['contact_email']): ?><a class="icon-btn" href="mailto:<?= e($r['contact_email']) ?>" aria-label="Email"><?= ico('mail') ?></a><?php endif; ?>
        <?php if ($waDigits): ?><a class="icon-btn" href="https://wa.me/<?= e($waDigits) ?>" target="_blank" rel="noopener" aria-label="WhatsApp"><?= ico('chat') ?></a><?php endif; ?>
        <?php if ($r['contact_phone']): ?><a class="icon-btn" href="tel:<?= e($r['contact_phone']) ?>" aria-label="Call"><?= ico('phone') ?></a><?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

</main>
<?= admin_bottomnav($staff, 'index.php') ?>
</body>
</html>
