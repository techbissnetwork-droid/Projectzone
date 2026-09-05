<?php
require_once __DIR__ . '/../includes/db.php';
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

$totalUsers = $canUsers ? (int)$pdo->query('SELECT COUNT(*) c FROM customers')->fetch()['c'] : 0;
$businessCount = $canBusinesses ? (int)$pdo->query('SELECT COUNT(*) c FROM businesses')->fetch()['c'] : 0;
$mrrCents = $canBusinesses ? (int)$pdo->query("SELECT COALESCE(SUM(price_cents),0) s FROM businesses WHERE price_type='recurring'")->fetch()['s'] : 0;
$productCount = $canProducts ? (int)$pdo->query('SELECT COUNT(*) c FROM products')->fetch()['c'] : 0;
$totalTickets = $canTickets ? (int)$pdo->query('SELECT COUNT(*) c FROM tickets')->fetch()['c'] : 0;
$openTickets = $canTickets ? (int)$pdo->query("SELECT COUNT(*) c FROM tickets WHERE status != 'Closed'")->fetch()['c'] : 0;

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
<link rel="stylesheet" href="../assets/style.css?v=<?= @filemtime(__DIR__ . '/../assets/style.css') ?: '1' ?>">
</head>
<body>
<?= admin_header($staff, 'index.php') ?>
<main class="admin-page">
  <?= admin_flash_html() ?>
  <span class="badge warning" style="margin-bottom:14px;"><?= ico('shield') ?> Internal — staff access only</span>
  <h1 style="max-width:22ch;margin-bottom:6px;">Every client, every ticket, one screen.</h1>
  <p class="lede" style="margin-bottom:28px;">All figures below are live from the database.</p>

  <?php if (!$canUsers && !$canBusinesses && !$canProducts && !$canTickets && !$canStaff): ?>
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
        <?php if ($waDigits): ?><a class="icon-btn" href="https://wa.me/<?= e($waDigits) ?>" target="_blank" rel="noopener" aria-label="WhatsApp"><?= ico('users') ?></a><?php endif; ?>
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
