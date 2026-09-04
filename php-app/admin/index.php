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
$canBusinesses = staff_can($staff, 'businesses.php');
$canProducts = staff_can($staff, 'products.php');
$canTickets = staff_can($staff, 'tickets.php');
$canStaff = staff_can($staff, 'staff.php');

$businessCount = $canBusinesses ? (int)$pdo->query('SELECT COUNT(*) c FROM businesses')->fetch()['c'] : 0;
$mrrCents = $canBusinesses ? (int)$pdo->query('SELECT COALESCE(SUM(mrr_cents),0) s FROM businesses')->fetch()['s'] : 0;
$productCount = $canProducts ? (int)$pdo->query('SELECT COUNT(*) c FROM products')->fetch()['c'] : 0;
$openTickets = $canTickets ? (int)$pdo->query("SELECT COUNT(*) c FROM tickets WHERE status != 'Closed'")->fetch()['c'] : 0;

$accounts = $canBusinesses ? $pdo->query('SELECT * FROM businesses ORDER BY last_activity_at DESC LIMIT 8')->fetchAll() : [];

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

$queue = $canTickets ? $pdo->query(
    "SELECT t.id, t.title, t.priority, b.name AS business_name, s.initials AS assignee_initials, s.name AS assignee_name
     FROM tickets t
     JOIN businesses b ON b.id = t.business_id
     LEFT JOIN staff s ON s.id = t.assignee_staff_id
     WHERE t.status != 'Closed'
     ORDER BY FIELD(t.priority,'High','Normal','Low'), t.created_at DESC
     LIMIT 8"
)->fetchAll() : [];

$staffList = $canStaff ? $pdo->query(
    "SELECT s.id, s.name, s.role, s.initials,
            (SELECT COUNT(*) FROM tickets t WHERE t.assignee_staff_id = s.id AND t.status != 'Closed') AS open_count
     FROM staff s ORDER BY s.id"
)->fetchAll() : [];

$statusTone = ['Active' => 'success', 'Trial' => 'warning', 'Past due' => 'danger'];
$priTone = ['High' => 'danger', 'Normal' => 'warning', 'Low' => 'success'];
?>
<!doctype html>
<html lang="en"<?= palette_attr() . logo_motion_attr() ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
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

  <?php if (!$canBusinesses && !$canProducts && !$canTickets && !$canStaff): ?>
  <div class="card" style="text-align:center;padding:36px 24px;"><?= blob_icon('shield', 'lg') ?><h3 style="margin:14px 0 4px;">Nothing to show here yet</h3><p class="lede" style="margin-bottom:0;">You don't have access to any of this dashboard's sections. Ask an admin to grant you a section from Staff &amp; permissions.</p></div>
  <?php endif; ?>

  <div class="grid grid-4" style="margin-bottom:28px;">
    <?php if ($canBusinesses): ?>
    <a class="card tilt" href="businesses.php" style="display:block;color:inherit;"><?= blob_icon('box', 'sm', true) ?><div class="stat" style="margin-top:12px;"><div class="num"><?= number_format($businessCount) ?></div><div class="label">Businesses on platform</div></div></a>
    <div class="card tilt"><?= blob_icon('chart', 'sm', true) ?><div class="stat" style="margin-top:12px;"><div class="num">$<?= number_format($mrrCents / 100, 0) ?></div><div class="label">Monthly recurring revenue</div></div></div>
    <?php endif; ?>
    <?php if ($canProducts): ?>
    <a class="card tilt" href="products.php" style="display:block;color:inherit;"><?= blob_icon('star', 'sm', true) ?><div class="stat" style="margin-top:12px;"><div class="num"><?= number_format($productCount) ?></div><div class="label">Marketplace products live</div></div></a>
    <?php endif; ?>
    <?php if ($canTickets): ?>
    <a class="card tilt" href="tickets.php" style="display:block;color:inherit;"><?= blob_icon('chat', 'sm', true) ?><div class="stat" style="margin-top:12px;"><div class="num"><?= number_format($openTickets) ?></div><div class="label">Open support tickets</div></div></a>
    <?php endif; ?>
  </div>

  <?php if ($canBusinesses): ?>
  <div class="card" style="margin-bottom:22px;">
    <div class="card-head" style="justify-content:space-between;"><div class="flex items-center gap-12"><?= blob_icon('box', 'sm', true) ?><h3>Business accounts</h3></div><a href="businesses.php" class="card-link">Manage all <?= ico('arrow') ?></a></div>
    <div class="table-wrap"><table><thead><tr><th>Business</th><th>Plan</th><th>MRR</th><th>Status</th><th>Last activity</th></tr></thead><tbody>
      <?php foreach ($accounts as $a): ?>
      <tr>
        <td style="font-weight:600;"><?= e($a['name']) ?></td>
        <td><?= e($a['plan']) ?></td>
        <td>$<?= number_format($a['mrr_cents'] / 100, 0) ?></td>
        <td><span class="badge <?= $statusTone[$a['status']] ?? '' ?>"><?= e($a['status']) ?></span></td>
        <td style="color:var(--ink-faint);"><?= e(time_ago($a['last_activity_at'])) ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$accounts): ?><tr><td colspan="5" style="color:var(--ink-faint);">No businesses yet.</td></tr><?php endif; ?>
    </tbody></table></div>
  </div>
  <?php endif; ?>

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

  <?php if ($canTickets || $canStaff): ?>
  <div class="hero-grid" style="align-items:flex-start;gap:26px;">
    <?php if ($canTickets): ?>
    <div class="card">
      <div class="card-head" style="justify-content:space-between;"><div class="flex items-center gap-12"><?= blob_icon('chat', 'sm', true) ?><h3>Support queue</h3></div><a href="tickets.php" class="card-link">Manage all <?= ico('arrow') ?></a></div>
      <?php foreach ($queue as $q): ?>
      <div class="flex justify-between items-center" style="padding:12px 0;border-bottom:1px solid var(--border-soft);">
        <div>
          <span style="font-family:var(--font-display);font-weight:600;font-size:.85rem;color:var(--ink-faint);">#<?= 1000 + (int)$q['id'] ?> · <?= e($q['business_name']) ?></span>
          <p style="margin:2px 0 0;font-size:.9rem;"><?= e($q['title']) ?></p>
        </div>
        <div class="flex items-center gap-8">
          <span class="badge <?= $priTone[$q['priority']] ?? '' ?>"><?= e($q['priority']) ?></span>
          <span class="blob-icon sm soft" title="Assigned to <?= e($q['assignee_name'] ?? 'Unassigned') ?>" style="width:30px;height:30px;font-size:.65rem;"><?= e($q['assignee_initials'] ?? '—') ?></span>
        </div>
      </div>
      <?php endforeach; ?>
      <?php if (!$queue): ?><p style="padding:12px 0;color:var(--ink-faint);">No open tickets.</p><?php endif; ?>
    </div>
    <?php endif; ?>
    <?php if ($canStaff): ?>
    <div style="display:flex;flex-direction:column;gap:22px;">
      <div class="card">
        <div class="card-head" style="justify-content:space-between;"><h3>Staff</h3><a href="staff.php" class="card-link">Manage <?= ico('arrow') ?></a></div>
        <?php foreach ($staffList as $s): ?>
        <div class="flex items-center gap-12" style="padding:12px 0;border-bottom:1px solid var(--border-soft);">
          <div class="avatar-blob" style="width:38px;height:38px;font-size:.75rem;"><?= e($s['initials']) ?></div>
          <div style="flex:1;"><b style="font-size:.9rem;"><?= e($s['name']) ?></b><p style="margin:0;font-size:.78rem;color:var(--ink-faint);"><?= e($s['role']) ?></p></div>
          <span style="font-size:.78rem;color:var(--ink-faint);"><?= (int)$s['open_count'] ?> open</span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</main>
<?= admin_bottomnav($staff, 'index.php') ?>
</body>
</html>
