<?php
/** What this client has bought from the marketplace. */
require_once __DIR__ . '/../app/bootstrap.php';
require_installed();
require_client();
require_once __DIR__ . '/_layout.php';

$me  = current_user();
$sym = setting('site.currency', '$');

/* Orders placed while signed in are linked by user_id; ones placed before the
   account existed are matched on the email address instead. */
$orders = db_all(
    'SELECT o.*, p.title AS product_title, p.slug AS product_slug
     FROM orders o LEFT JOIN products p ON p.id = o.product_id
     WHERE o.user_id = ? OR LOWER(o.buyer_email) = ?
     ORDER BY o.id DESC',
    [$me['id'], strtolower($me['email'])]
);

client_head('Orders', 'orders.php');
?>

<div class="hero-line">
  <h1>Your orders</h1>
  <p>Anything you have bought from the marketplace, and where each one stands.</p>
</div>

<div class="panel">
<?php if (!$orders): ?>
  <div class="empty"><strong>No orders yet</strong>
    <p>Premade projects you buy from us will be listed here, with their download links.</p>
    <p style="margin-top:16px"><a class="btn" href="../marketplace.php">Browse the marketplace</a></p>
  </div>
<?php else: ?>
  <div class="tablewrap"><table>
    <thead><tr>
      <th>Reference</th><th>Project</th><th>Placed</th>
      <th class="right">Amount</th><th class="right">Status</th><th class="right">Files</th>
    </tr></thead>
    <tbody>
<?php foreach ($orders as $o):
        $live = $o['download_token']
            && (!$o['download_expires'] || strtotime((string) $o['download_expires']) > time()); ?>
      <tr>
        <td><strong><?= esc($o['reference']) ?></strong></td>
        <td><?= esc($o['product_title'] ?? 'No longer listed') ?>
<?php if ($o['wants_setup']): ?>
          <span class="sub">With setup on your own domain</span>
<?php endif; ?></td>
        <td class="num"><?= esc(date_human($o['created_at'])) ?></td>
        <td class="right num"><?= esc(money($o['amount'], $sym)) ?></td>
        <td class="right"><?= status_pill($o['status']) ?></td>
        <td class="right">
<?php if ($live): ?>
          <a class="btn sm" href="../download.php?token=<?= urlencode($o['download_token']) ?>">Download</a>
<?php elseif ($o['download_token']): ?>
          <span class="pill soon">Link expired</span>
<?php else: ?>
          <span class="pill none">Not sent yet</span>
<?php endif; ?>
        </td>
      </tr>
<?php endforeach; ?>
    </tbody>
  </table></div>
<?php endif; ?>
</div>

<?php if ($orders): ?>
<p style="color:var(--mute);font-size:13.5px">
  A download link expired before you fetched the files?
  <a href="support.php?action=new&amp;category=billing" style="color:var(--acc)">Ask us</a>
  and we will issue a new one — your purchase stays on file.
</p>
<?php endif; ?>

<?php client_foot(); ?>
