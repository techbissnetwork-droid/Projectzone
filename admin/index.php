<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_installed();
require_staff();
require_once __DIR__ . '/_layout.php';

$counts = admin_counts();
$due    = renewals_due(45);
$stats  = [
    'projects'  => db_count('SELECT COUNT(*) FROM projects'),
    'live'      => db_count("SELECT COUNT(*) FROM projects WHERE status = 'active'"),
    'clients'   => db_count("SELECT COUNT(*) FROM users WHERE role = 'client'"),
    'portfolio' => db_count("SELECT COUNT(*) FROM portfolio WHERE visibility = 'public'"),
    'products'  => db_count('SELECT COUNT(*) FROM products WHERE is_active = 1'),
    'unsent'    => db_count('SELECT COUNT(*) FROM enquiries WHERE mail_sent = 0'),
];

$recentEnquiries = db_all('SELECT * FROM enquiries ORDER BY id DESC LIMIT 6');
$recentOrders    = db_all('SELECT o.*, p.title AS product_title FROM orders o
                           LEFT JOIN products p ON p.id = o.product_id
                           ORDER BY o.id DESC LIMIT 6');
$openTickets     = db_all("SELECT t.*, u.name AS client_name FROM tickets t
                           LEFT JOIN users u ON u.id = t.user_id
                           WHERE t.status IN ('open','answered')
                           ORDER BY t.updated_at DESC, t.id DESC LIMIT 6");

admin_head('Dashboard', 'index.php');
admin_page_head('Dashboard', 'Everything that wants attention today.');
?>

<div class="tiles">
  <div class="tile <?= $counts['enquiries'] ? 'hot' : '' ?>">
    <h6>New enquiries</h6><b><?= (int) $counts['enquiries'] ?></b>
    <small><a href="enquiries.php">Open the inbox</a></small></div>
  <div class="tile <?= $counts['orders'] ? 'hot' : '' ?>">
    <h6>New orders</h6><b><?= (int) $counts['orders'] ?></b>
    <small><a href="orders.php">Marketplace orders</a></small></div>
  <div class="tile <?= $counts['tickets'] ? 'hot' : '' ?>">
    <h6>Open support</h6><b><?= (int) $counts['tickets'] ?></b>
    <small><a href="tickets.php">Client requests</a></small></div>
  <div class="tile <?= $due ? 'alarm' : '' ?>">
    <h6>Renewals due</h6><b><?= count($due) ?></b>
    <small>Within 45 days</small></div>
  <div class="tile">
    <h6>Live projects</h6><b><?= (int) $stats['live'] ?></b>
    <small><?= (int) $stats['projects'] ?> in total &middot; <?= (int) $stats['clients'] ?> clients</small></div>
</div>

<?php if ($stats['unsent']): ?>
<div class="flash warn">
  <strong><?= (int) $stats['unsent'] ?></strong> enquir<?= $stats['unsent'] === 1 ? 'y' : 'ies' ?>
  could not be emailed out, but <strong>nothing is lost</strong> — they are all saved here.
  Check the "send from" address in <a href="settings.php" style="color:inherit;text-decoration:underline">Settings</a>
  is a mailbox on your own domain.
</div>
<?php endif; ?>

<div class="split">
  <div>
    <div class="panel">
      <header><h2>Renewals coming up</h2>
        <div class="acts"><a class="btn ghost sm" href="projects.php">All projects</a></div></header>
<?php if (!$due): ?>
      <div class="empty"><strong>Nothing due</strong>
        <p>No domain, hosting, SSL or email renewal falls inside the next 45 days.</p></div>
<?php else: ?>
      <div class="tablewrap"><table>
        <thead><tr><th>Project</th><th>What</th><th>Provider</th><th>Date</th><th class="right">Due</th></tr></thead>
        <tbody>
<?php foreach ($due as $d): ?>
          <tr>
            <td><a class="rowlink" href="project-edit.php?id=<?= (int) $d['project']['id'] ?>">
              <?= esc($d['project']['name']) ?></a>
              <span class="sub"><?= esc($d['project']['domain'] ?: 'No domain set') ?></span></td>
            <td><?= esc($d['label']) ?></td>
            <td><?= esc($d['provider'] ?: '—') ?></td>
            <td class="num"><?= esc(date_human($d['date'])) ?></td>
            <td class="right"><span class="pill <?= esc($d['state']) ?>"><?= esc($d['human']) ?></span></td>
          </tr>
<?php endforeach; ?>
        </tbody>
      </table></div>
<?php endif; ?>
    </div>

    <div class="panel">
      <header><h2>Latest enquiries</h2>
        <div class="acts"><a class="btn ghost sm" href="enquiries.php">See all</a></div></header>
<?php if (!$recentEnquiries): ?>
      <div class="empty"><strong>No enquiries yet</strong>
        <p>Messages from the contact form land here.</p></div>
<?php else: ?>
      <div class="tablewrap"><table>
        <thead><tr><th>From</th><th>Wants</th><th>Received</th><th class="right">Status</th></tr></thead>
        <tbody>
<?php foreach ($recentEnquiries as $e): ?>
          <tr>
            <td><a class="rowlink" href="enquiry.php?id=<?= (int) $e['id'] ?>"><?= esc($e['name']) ?></a>
              <span class="sub"><?= esc($e['email']) ?></span></td>
            <td><?= esc($e['service'] ?: '—') ?></td>
            <td class="num"><?= esc(date_human($e['created_at'])) ?></td>
            <td class="right"><?= status_pill($e['status']) ?></td>
          </tr>
<?php endforeach; ?>
        </tbody>
      </table></div>
<?php endif; ?>
    </div>

    <div class="panel">
      <header><h2>Latest orders</h2>
        <div class="acts"><a class="btn ghost sm" href="orders.php">See all</a></div></header>
<?php if (!$recentOrders): ?>
      <div class="empty"><strong>No orders yet</strong>
        <p>Marketplace orders arrive here. Nothing is charged online — you confirm and invoice.</p></div>
<?php else: ?>
      <div class="tablewrap"><table>
        <thead><tr><th>Reference</th><th>Project</th><th>Buyer</th><th class="right">Amount</th><th class="right">Status</th></tr></thead>
        <tbody>
<?php foreach ($recentOrders as $o): ?>
          <tr>
            <td><a class="rowlink" href="order.php?id=<?= (int) $o['id'] ?>"><?= esc($o['reference']) ?></a></td>
            <td><?= esc($o['product_title'] ?? '—') ?></td>
            <td><?= esc($o['buyer_name']) ?><span class="sub"><?= esc($o['buyer_email']) ?></span></td>
            <td class="right num"><?= esc(money($o['amount'], setting('site.currency', '$'))) ?></td>
            <td class="right"><?= status_pill($o['status']) ?></td>
          </tr>
<?php endforeach; ?>
        </tbody>
      </table></div>
<?php endif; ?>
    </div>
  </div>

  <div>
    <div class="panel">
      <header><h2>Open support</h2></header>
<?php if (!$openTickets): ?>
      <div class="empty"><strong>All quiet</strong><p>No open client requests.</p></div>
<?php else: ?>
      <div class="pad" style="display:grid;gap:10px">
<?php foreach ($openTickets as $t): ?>
        <a class="msg" href="ticket.php?id=<?= (int) $t['id'] ?>" style="display:block">
          <div class="who"><b><?= esc($t['subject']) ?></b></div>
          <div class="who" style="margin:0">
            <?= esc($t['client_name'] ?? 'Unknown') ?> &middot; <?= esc($t['reference']) ?>
            &middot; <?= status_pill($t['status']) ?>
          </div>
        </a>
<?php endforeach; ?>
      </div>
<?php endif; ?>
    </div>

    <div class="panel">
      <header><h2>Quick actions</h2></header>
      <div class="pad" style="display:grid;gap:8px">
        <a class="btn" href="project-edit.php?action=new">Add a client project</a>
        <a class="btn ghost" href="resource.php?type=portfolio&amp;action=new">Add completed work</a>
        <a class="btn ghost" href="resource.php?type=products&amp;action=new">List a premade project</a>
        <a class="btn ghost" href="content.php">Edit page text</a>
      </div>
    </div>

    <div class="panel">
      <header><h2>On the site</h2></header>
      <div class="pad">
        <div class="kv"><span>Public portfolio</span><strong><?= (int) $stats['portfolio'] ?> projects</strong></div>
        <div class="kv"><span>Marketplace listings</span><strong><?= (int) $stats['products'] ?> live</strong></div>
        <div class="kv"><span>Client accounts</span><strong><?= (int) $stats['clients'] ?></strong></div>
      </div>
    </div>
  </div>
</div>

<?php admin_foot(); ?>
