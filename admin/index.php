<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/guard.php';
require_admin();

$warnDays = (int)Settings::get('expiry_warn_days', '45');
$horizon  = date('Y-m-d', strtotime('+' . $warnDays . ' days'));

$stats = [
    'projects' => (int)Database::value("SELECT COUNT(*) FROM projects WHERE status <> 'closed'", [], 0),
    'clients'  => (int)Database::value("SELECT COUNT(*) FROM users WHERE role = 'client' AND status = 'active'", [], 0),
    'tickets'  => (int)Database::value("SELECT COUNT(*) FROM tickets WHERE status IN ('open','in_progress')", [], 0),
    'orders'   => (int)Database::value("SELECT COUNT(*) FROM orders WHERE status = 'pending'", [], 0),
];
$revenue = (float)Database::value("SELECT COALESCE(SUM(amount),0) FROM orders WHERE status IN ('paid','delivered')", [], 0);
$newEnq  = (int)Database::value("SELECT COUNT(*) FROM enquiries WHERE status = 'new'", [], 0);

/* Everything renewable, in one list, soonest first. */
$renewals = [];
foreach ([['domain', 'domain_expires_on', 'domain_name'],
          ['hosting', 'hosting_expires_on', 'hosting_provider'],
          ['ssl', 'ssl_expires_on', 'ssl_issuer']] as [$kind, $col, $detail]) {
    $rows = Database::all(
        "SELECT id, name, $col AS expires_on, $detail AS detail FROM projects
         WHERE $col IS NOT NULL AND $col <= :h AND status <> 'closed' ORDER BY $col ASC LIMIT 25",
        ['h' => $horizon]
    );
    foreach ($rows as $r) {
        $renewals[] = $r + ['kind' => $kind];
    }
}
usort($renewals, static fn($a, $b) => strcmp((string)$a['expires_on'], (string)$b['expires_on']));
$renewals = array_slice($renewals, 0, 12);

$recentTickets = Database::all(
    "SELECT t.*, u.name AS client_name FROM tickets t
     LEFT JOIN users u ON u.id = t.user_id
     WHERE t.status <> 'closed' ORDER BY t.updated_at DESC LIMIT 6"
);
$recentOrders = Database::all(
    "SELECT o.*, p.title AS product_title FROM orders o
     LEFT JOIN products p ON p.id = o.product_id
     ORDER BY o.created_at DESC LIMIT 6"
);

$PAGE_TITLE = 'Dashboard';
$AREA = 'admin';
$PAGE_ACTIONS = '<a class="btn sm" href="projects.php?action=new">New project</a>';
require __DIR__ . '/../partials/app_header.php';
?>
<div class="grid g4">
  <div class="stat"><span>Active projects</span><b><?= $stats['projects'] ?></b><small><a class="linkish" href="projects.php">Manage</a></small></div>
  <div class="stat"><span>Clients</span><b><?= $stats['clients'] ?></b><small><a class="linkish" href="clients.php">Directory</a></small></div>
  <div class="stat <?= $stats['tickets'] ? 'hot' : '' ?>"><span>Open tickets</span><b><?= $stats['tickets'] ?></b><small><a class="linkish" href="tickets.php">Support queue</a></small></div>
  <div class="stat <?= $stats['orders'] ? 'hot' : '' ?>"><span>Orders awaiting</span><b><?= $stats['orders'] ?></b><small><a class="linkish" href="orders.php">Review</a></small></div>
</div>

<div class="split">
  <section class="card">
    <div class="card__head">
      <h2>Renewals in the next <?= $warnDays ?> days</h2>
      <a class="btn ghost sm" href="projects.php">All projects</a>
    </div>
    <?php if (!$renewals): ?>
      <div class="empty"><b>Nothing expiring</b><p>No domain, hosting or SSL renewal falls inside the window.</p></div>
    <?php else: ?>
      <div class="card__body tight"><div class="tablewrap"><table class="data">
        <thead><tr><th>Project</th><th>What</th><th>Expires</th><th class="right">Status</th></tr></thead>
        <tbody>
        <?php foreach ($renewals as $r):
            $state = expiry_state($r['expires_on']); ?>
          <tr>
            <td><a class="linkish t-main" href="project.php?id=<?= (int)$r['id'] ?>"><?= e($r['name']) ?></a>
                <?php if ($r['detail']): ?><span class="t-sub"><?= e($r['detail']) ?></span><?php endif; ?></td>
            <td><span class="badge muted"><?= e(strtoupper($r['kind'])) ?></span></td>
            <td class="nowrap"><?= e(fdate($r['expires_on'])) ?></td>
            <td class="right"><span class="badge <?= $state === 'expired' || $state === 'danger' ? 'danger' : 'warn' ?>"><?= e(expiry_label($r['expires_on'])) ?></span></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div></div>
    <?php endif; ?>
  </section>

  <div class="stack">
    <div class="stat"><span>Marketplace revenue</span><b><?= e(money($revenue)) ?></b><small>Paid and delivered orders</small></div>
    <div class="stat"><span>New enquiries</span><b><?= $newEnq ?></b><small><a class="linkish" href="enquiries.php">Open inbox</a></small></div>
    <section class="card">
      <div class="card__head"><h2>Latest orders</h2></div>
      <?php if (!$recentOrders): ?>
        <div class="empty"><p>No orders yet.</p></div>
      <?php else: ?>
        <div class="card__body tight"><div class="tablewrap"><table class="data"><tbody>
          <?php foreach ($recentOrders as $o): ?>
            <tr>
              <td><a class="linkish t-main" href="orders.php?action=view&id=<?= (int)$o['id'] ?>"><?= e($o['reference']) ?></a>
                  <span class="t-sub"><?= e($o['product_title'] ?? 'Product removed') ?></span></td>
              <td class="right"><span class="badge <?= e(status_tone($o['status'])) ?>"><?= e(label($o['status'])) ?></span>
                  <span class="t-sub"><?= e(money($o['amount'], $o['currency'] . ' ')) ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody></table></div></div>
      <?php endif; ?>
    </section>
  </div>
</div>

<section class="card">
  <div class="card__head"><h2>Support queue</h2><a class="btn ghost sm" href="tickets.php">All tickets</a></div>
  <?php if (!$recentTickets): ?>
    <div class="empty"><b>Inbox zero</b><p>No open tickets right now.</p></div>
  <?php else: ?>
    <div class="card__body tight"><div class="tablewrap"><table class="data">
      <thead><tr><th>Ticket</th><th>Client</th><th>Category</th><th>Status</th><th class="right">Updated</th></tr></thead>
      <tbody>
      <?php foreach ($recentTickets as $t): ?>
        <tr>
          <td><a class="linkish t-main" href="ticket.php?id=<?= (int)$t['id'] ?>"><?= e($t['subject']) ?></a>
              <span class="t-sub mono"><?= e($t['reference']) ?></span></td>
          <td><?= e($t['client_name'] ?? '—') ?></td>
          <td><span class="badge muted"><?= e(label($t['category'])) ?></span></td>
          <td><span class="badge <?= e(status_tone($t['status'])) ?>"><?= e(label($t['status'])) ?></span></td>
          <td class="right nowrap"><?= e(ftime($t['updated_at'])) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div></div>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/../partials/app_footer.php'; ?>
