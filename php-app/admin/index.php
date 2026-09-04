<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/icons.php';
require_installed('../install/');

$staff = require_staff(); // redirects to login.php if not signed in
$pdo = db();

$businessCount = (int)$pdo->query('SELECT COUNT(*) c FROM businesses')->fetch()['c'];
$mrrCents = (int)$pdo->query('SELECT COALESCE(SUM(mrr_cents),0) s FROM businesses')->fetch()['s'];
$productCount = (int)$pdo->query('SELECT COUNT(*) c FROM products')->fetch()['c'];
$openTickets = (int)$pdo->query("SELECT COUNT(*) c FROM tickets WHERE status != 'Closed'")->fetch()['c'];

$accounts = $pdo->query('SELECT * FROM businesses ORDER BY last_activity_at DESC')->fetchAll();

$queue = $pdo->query(
    "SELECT t.id, t.title, t.priority, b.name AS business_name, s.initials AS assignee_initials, s.name AS assignee_name
     FROM tickets t
     JOIN businesses b ON b.id = t.business_id
     LEFT JOIN staff s ON s.id = t.assignee_staff_id
     WHERE t.status != 'Closed'
     ORDER BY FIELD(t.priority,'High','Normal','Low'), t.created_at DESC
     LIMIT 10"
)->fetchAll();

$staffList = $pdo->query(
    "SELECT s.id, s.name, s.role, s.initials,
            (SELECT COUNT(*) FROM tickets t WHERE t.assignee_staff_id = s.id AND t.status != 'Closed') AS open_count
     FROM staff s ORDER BY s.id"
)->fetchAll();

$sellers = $pdo->query('SELECT name, rating FROM products ORDER BY rating DESC, sort_order ASC LIMIT 3')->fetchAll();

$statusTone = ['Active' => 'success', 'Trial' => 'warning', 'Past due' => 'danger'];
$priTone = ['High' => 'danger', 'Normal' => 'warning', 'Low' => 'success'];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin control center — TECHBISS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/style.css">
<style>
.admin-bar{ display:flex; justify-content:space-between; align-items:center; padding:18px 0; }
.admin-bar .who{ font-size:.85rem; color:var(--ink-faint); }
</style>
</head>
<body>
<main class="container" style="padding-top:8px;padding-bottom:60px;">
  <div class="admin-bar">
    <div class="flex items-center gap-12">
      <div class="logo-mark" style="width:36px;height:36px;"><svg viewBox="0 0 24 24" fill="none"><rect x="3" y="3" width="18" height="18" rx="6" fill="var(--accent-1)"/><rect x="7.5" y="7.5" width="9" height="2.6" rx="1.3" fill="#fff2ea"/><rect x="10.7" y="7.5" width="2.6" height="9.5" rx="1.3" fill="#fff2ea"/></svg></div>
      <b style="font-family:var(--font-display);">techbiss admin</b>
    </div>
    <div class="flex items-center gap-12">
      <span class="who">Signed in as <?= e($staff['name']) ?> · <?= e($staff['role']) ?></span>
      <a href="logout.php" class="btn btn-ghost btn-sm"><?= ico('logout') ?> Log out</a>
    </div>
  </div>

  <span class="badge warning" style="margin-bottom:14px;"><?= ico('shield') ?> Internal — staff access only</span>
  <h1 style="max-width:22ch;margin-bottom:6px;">Every client, every ticket, one screen.</h1>
  <p class="lede" style="margin-bottom:28px;">All figures below are live from the database.</p>

  <div class="grid grid-4" style="margin-bottom:28px;">
    <div class="card tilt"><?= blob_icon('users', 'sm', true) ?><div class="stat" style="margin-top:12px;"><div class="num"><?= number_format($businessCount) ?></div><div class="label">Businesses on platform</div></div></div>
    <div class="card tilt"><?= blob_icon('chart', 'sm', true) ?><div class="stat" style="margin-top:12px;"><div class="num">$<?= number_format($mrrCents / 100, 0) ?></div><div class="label">Monthly recurring revenue</div></div></div>
    <div class="card tilt"><?= blob_icon('box', 'sm', true) ?><div class="stat" style="margin-top:12px;"><div class="num"><?= number_format($productCount) ?></div><div class="label">Marketplace products live</div></div></div>
    <div class="card tilt"><?= blob_icon('chat', 'sm', true) ?><div class="stat" style="margin-top:12px;"><div class="num"><?= number_format($openTickets) ?></div><div class="label">Open support tickets</div></div></div>
  </div>

  <div class="card" style="margin-bottom:22px;">
    <div class="card-head"><?= blob_icon('users', 'sm', true) ?><h3>Business accounts</h3></div>
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
    </tbody></table></div>
  </div>

  <div class="hero-grid" style="align-items:flex-start;gap:26px;">
    <div class="card">
      <div class="card-head"><?= blob_icon('chat', 'sm', true) ?><h3>Support queue — all clients</h3></div>
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
    <div style="display:flex;flex-direction:column;gap:22px;">
      <div class="card">
        <h3>Staff</h3>
        <?php foreach ($staffList as $s): ?>
        <div class="flex items-center gap-12" style="padding:12px 0;border-bottom:1px solid var(--border-soft);">
          <div class="avatar-blob" style="width:38px;height:38px;font-size:.75rem;"><?= e($s['initials']) ?></div>
          <div style="flex:1;"><b style="font-size:.9rem;"><?= e($s['name']) ?></b><p style="margin:0;font-size:.78rem;color:var(--ink-faint);"><?= e($s['role']) ?></p></div>
          <span style="font-size:.78rem;color:var(--ink-faint);"><?= (int)$s['open_count'] ?> open</span>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="card">
        <h3>Top marketplace sellers</h3>
        <?php foreach ($sellers as $p): ?>
        <div class="flex justify-between items-center" style="padding:10px 0;border-bottom:1px solid var(--border-soft);">
          <span style="font-size:.9rem;"><?= e($p['name']) ?></span>
          <span class="badge"><?= ico('star') ?> <?= number_format((float)$p['rating'], 1) ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</main>
</body>
</html>
