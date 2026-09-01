<?php
declare(strict_types=1);

$config = require __DIR__ . '/_admin.php';

use SignalMasterAi\Auth;
use SignalMasterAi\Database;
use SignalMasterAi\Payments;

Auth::requireLogin();
$pdo = Database::pdo();
$notice = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf();
    $act = $_POST['act'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($act === 'approve') {
        Payments::approve($id);
        flash("Payment #$id approved - member upgraded.");
    }
    if ($act === 'reject') {
        Payments::setStatus($id, 'rejected');
        // SAY WHY. A rejection with no reason is a member who paid - or thinks
        // they did - being told no by a page with nothing else on it, and the
        // next thing that happens is an email to support asking what went
        // wrong. Whatever is typed here is shown to them on their order.
        $reason = mb_substr(trim((string)($_POST['reason'] ?? '')), 0, 300);
        if ($reason !== '') {
            $st = $pdo->prepare('SELECT note FROM payments WHERE id = ?');
            $st->execute([$id]);
            $old = trim((string)$st->fetchColumn());
            $st->closeCursor();
            $pdo->prepare('UPDATE payments SET note = ?, updated_at = ? WHERE id = ?')
                ->execute([trim($old . "\nRejected: " . $reason), time(), $id]);
        }
        flash("Payment #$id rejected." . ($reason !== '' ? ' The member will see your reason.' : ''));
    }
    if ($act === 'check') {
        $stmt = $pdo->prepare('SELECT * FROM payments WHERE id = ?');
        $stmt->execute([$id]);
        $p = $stmt->fetch();
    $stmt->closeCursor();
    if ($p) {
            try {
                $status = \SignalMasterAi\Gateways::refreshStatus($p);
                flash("Gateway reports payment #$id as: $status");
            } catch (Throwable $e) {
                flash('Status check failed: ' . $e->getMessage());
            }
        }
    }
    // Back to the list you were working, not to the top of everything - an
    // operator clearing the review queue should not be thrown out of it on
    // every approval.
    header('Location: payments.php?' . http_build_query([
        'f' => (string)($_POST['f'] ?? 'all'),
        'q' => (string)($_POST['q'] ?? ''),
    ]));
    exit;
}

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;

// FINDING ONE ORDER IN A YEAR OF THEM.
//
// The list was newest-first, twenty-five at a time, and nothing else - so
// "which manual payments are waiting on me" and "what happened to this
// member's order" both meant paging through everything by hand, and the
// answer moved further away with every sale. Filter by what an operator
// actually asks for, and search by the member's email.
$filters = [
    'all'      => ['All', []],
    'review'   => ['Awaiting review', ['awaiting_review']],
    'pending'  => ['Started, not finished', ['pending']],
    'paid'     => ['Paid', ['paid']],
    'problem'  => ['Failed / expired / rejected', ['failed', 'expired', 'rejected']],
];
$filter = isset($_GET['f']) && isset($filters[$_GET['f']]) ? (string)$_GET['f'] : 'all';
$q = trim((string)($_GET['q'] ?? ''));

$where = [];
$args  = [];
if ($filters[$filter][1]) {
    $where[] = 'p.status IN (' . implode(',', array_fill(0, count($filters[$filter][1]), '?')) . ')';
    $args = array_merge($args, $filters[$filter][1]);
}
if ($q !== '') {
    $where[] = 'm.email LIKE ?';
    $args[] = '%' . $q . '%';
}
$sqlWhere = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$countStmt = $pdo->prepare('SELECT COUNT(*) FROM payments p LEFT JOIN members m ON m.id = p.member_id' . $sqlWhere);
$countStmt->execute($args);
$total = (int)$countStmt->fetchColumn();
$countStmt->closeCursor();
$pages = max(1, (int)ceil($total / $perPage));
$page = min($page, $pages);

$stmt = $pdo->prepare(
    'SELECT p.*, m.email FROM payments p LEFT JOIN members m ON m.id = p.member_id' . $sqlWhere
    . ' ORDER BY p.created_at DESC LIMIT ? OFFSET ?'
);
$i = 1;
foreach ($args as $a) {
    $stmt->bindValue($i++, $a);
}
$stmt->bindValue($i++, $perPage, PDO::PARAM_INT);
$stmt->bindValue($i, ($page - 1) * $perPage, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

// The tiles count everything, never the filtered slice - they are the site's
// totals, and a number that changes when you click a filter is not a total.
$revenue = (float)$pdo->query("SELECT COALESCE(SUM(amount_usd),0) FROM payments WHERE status = 'paid'")->fetchColumn();
$pending = (int)$pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'awaiting_review'")->fetchColumn();
$started = (int)$pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'pending'")->fetchColumn();
$allRecords = (int)$pdo->query('SELECT COUNT(*) FROM payments')->fetchColumn();
$qs = fn(array $over = []) => 'payments.php?' . http_build_query(array_merge(
    ['f' => $filter, 'q' => $q], $over));
$csrf = Auth::csrfToken();

$badge = fn(string $s) => match ($s) {
    'paid' => 'buy',
    'awaiting_review' => 'neutral',
    'rejected', 'failed', 'expired' => 'sell',
    default => 'off',
};

admin_header('Payments', 'payments',
    'Every payment the site has recorded, and the manual ones waiting for you to confirm. What is '
    . 'for sale is set under <a href="billing.php">Plans &amp; prices</a>.');
show_flash();
?>
<div class="cards">
  <div class="card"><div class="num">$<?= number_format($revenue, 2) ?></div><div class="lbl">Total revenue (paid)</div></div>
  <?php // The tiles are the filters. A count of things waiting for you that
        // you cannot click is a number telling you to go and look. ?>
  <a class="card" href="payments.php?f=review" style="text-decoration:none;color:inherit">
    <div class="num"><?= $pending ?></div><div class="lbl">Awaiting your review</div></a>
  <a class="card" href="payments.php?f=pending" style="text-decoration:none;color:inherit">
    <div class="num"><?= $started ?></div><div class="lbl">Started, not finished</div></a>
  <a class="card" href="payments.php?f=all" style="text-decoration:none;color:inherit">
    <div class="num"><?= number_format($allRecords) ?></div><div class="lbl">All payment records</div></a>
</div>

<?php admin_panel('Payment records',
    'Newest first. An automatic crypto payment confirms itself and needs nothing from you; a '
    . 'manual one sits at AWAITING REVIEW until you approve or reject it, and approving upgrades '
    . 'the member on the spot.'); ?>
<div class="settings-tabs" style="margin-bottom:12px">
  <?php foreach ($filters as $key => [$label, $_]): ?>
    <a class="<?= $filter === $key ? 'on' : '' ?>"
       href="payments.php?<?= http_build_query(['f' => $key, 'q' => $q]) ?>"><?= sma_e($label) ?></a>
  <?php endforeach; ?>
</div>
<form method="get" action="payments.php" class="inline-form" style="margin-bottom:12px;gap:6px">
  <input type="hidden" name="f" value="<?= sma_e($filter) ?>">
  <input type="text" name="q" value="<?= sma_e($q) ?>" placeholder="Search by member email"
         style="max-width:260px;flex:1 1 180px">
  <button class="btn small" type="submit">Search</button>
  <?php if ($q !== ''): ?>
    <a class="btn small gray" href="payments.php?f=<?= sma_e($filter) ?>">Clear</a>
  <?php endif; ?>
</form>

<?php if (!$rows): ?>
  <?php admin_empty(
      $filter === 'all' && $q === '' ? 'No payments yet' : 'Nothing matches that',
      $filter === 'all' && $q === ''
        ? 'Members start checkout from the Upgrade page. Automatic crypto payments confirm themselves; manual ones appear here for review.'
        : 'No payment matches this filter and search. Clear them to see every record.'); ?>
<?php else: ?>
<p class="sub" style="margin:0 0 8px"><?= number_format($total) ?>
  <?= $total === 1 ? 'record' : 'records' ?><?= $filter === 'all' && $q === '' ? '' : ' matching' ?>.</p>
<div class="tbl-scroll">
<table class="grid stack">
  <tr class="head"><th>#</th><th>Member</th><th>Plan</th><th>Method</th><th>Amount</th><th>Status</th><th>Proof</th><th>When (UTC)</th><th style="width:220px">Actions</th></tr>
  <?php foreach ($rows as $p): ?>
  <?php $hasActions = !in_array($p['status'], ['paid', 'rejected'], true); ?>
  <tr>
    <td data-label="Payment #"><?= (int)$p['id'] ?></td>
    <td data-label="Member"><?= sma_e($p['email'] ?? '(deleted)') ?></td>
    <td data-label="Plan"><?= sma_e($p['plan_name']) ?> (<?= \SignalMasterAi\Payments::planLength((int)$p['plan_days'], true) ?>)</td>
    <td data-label="Method"><?= sma_e($p['method_name']) ?>
      <?php // Three kinds now, not two. An activation key produces a real
            // payment row so it shows up in the order history like everything
            // else; labelling it MANUAL would have an operator hunting for a
            // proof image and a transfer that never existed. ?>
      <?php // The badge class carries its own size (11px). The inline 9px here
            // was shrinking a MANUAL/KEY/AUTO tag - the thing that tells an
            // operator whether to go looking for a proof image - to below what
            // the rest of the panel treats as readable, on the one page that
            // gets opened on a phone when somebody says they have paid. ?>
      <span class="badge <?= $p['kind'] === 'manual' ? 'off' : 'on' ?>"><?=
        $p['kind'] === 'manual' ? 'MANUAL' : ($p['kind'] === 'redeem' ? 'KEY' : 'AUTO') ?></span></td>
    <td data-label="Amount">$<?= number_format((float)$p['amount_usd'], 2) ?>
      <?php // WHY IS THIS LESS THAN THE PLAN COSTS? The coupon was stored on
            // the row and shown nowhere, so an operator reconciling a $10
            // payment against a $19.99 plan had no way to tell a discount from
            // an underpayment. ?>
      <?php if (($p['coupon'] ?? '') !== ''): ?>
        <span class="badge on"><?= sma_e($p['coupon']) ?></span>
      <?php endif; ?></td>
    <td data-label="Status"><span class="badge <?= $badge($p['status']) ?>"><?= sma_e(strtoupper(str_replace('_', ' ', $p['status']))) ?></span></td>
    <td data-label="Proof">
      <?php if ($p['proof_image'] !== ''): ?>
        <a class="btn small gray" href="proof.php?id=<?= (int)$p['id'] ?>" target="_blank">View</a>
      <?php else: ?>—<?php endif; ?>
    </td>
    <td data-label="When (UTC)" style="white-space:nowrap"><?= gmdate('m-d H:i', (int)$p['created_at']) ?></td>
    <td class="act-cell<?= (!$hasActions && $p['note'] === '') ? ' is-empty' : '' ?>">
      <?php if (!in_array($p['status'], ['paid', 'rejected'], true)): ?>
        <form class="inline-form" method="post" action="payments.php"
              onsubmit="return confirm('Approve payment #<?= (int)$p['id'] ?> and upgrade the member?')">
          <input type="hidden" name="csrf" value="<?= $csrf ?>">
          <input type="hidden" name="f" value="<?= sma_e($filter) ?>">
          <input type="hidden" name="q" value="<?= sma_e($q) ?>">
          <input type="hidden" name="act" value="approve">
          <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
          <button class="btn small" type="submit">Approve</button>
        </form>
        <form class="inline-form" method="post" action="payments.php"
              onsubmit="var r=prompt('Reject payment #<?= (int)$p['id'] ?>. Why? (the member sees this - leave blank to reject with no reason)');
                        if(r===null){return false;} this.reason.value=r; return true;">
          <input type="hidden" name="csrf" value="<?= $csrf ?>">
          <input type="hidden" name="f" value="<?= sma_e($filter) ?>">
          <input type="hidden" name="q" value="<?= sma_e($q) ?>">
          <input type="hidden" name="act" value="reject">
          <input type="hidden" name="reason" value="">
          <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
          <button class="btn small danger" type="submit">Reject</button>
        </form>
        <?php if ($p['kind'] !== 'manual' && $p['gateway_uuid'] !== ''): ?>
        <form class="inline-form" method="post" action="payments.php">
          <input type="hidden" name="csrf" value="<?= $csrf ?>">
          <input type="hidden" name="f" value="<?= sma_e($filter) ?>">
          <input type="hidden" name="q" value="<?= sma_e($q) ?>">
          <input type="hidden" name="act" value="check">
          <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
          <button class="btn small gray" type="submit">Check</button>
        </form>
        <?php endif; ?>
      <?php endif; ?>
      <?php if ($p['note'] !== ''): ?>
        <div class="hint" style="margin-top:4px">note: <?= sma_e($p['note']) ?></div>
      <?php endif; ?>
    </td>
  </tr>
  <?php endforeach; ?>
</table>
</div>
<?php if ($page > 1 || $page < $pages): ?>
<div style="margin-top:14px;display:flex;gap:8px">
  <?php // The filter and the search have to survive paging, or page 2 quietly
        // shows a different list than page 1. ?>
  <?php if ($page > 1): ?><a class="btn small gray" href="<?= sma_e($qs(['page' => $page - 1])) ?>">&larr; Newer</a><?php endif; ?>
  <?php if ($page < $pages): ?><a class="btn small gray" href="<?= sma_e($qs(['page' => $page + 1])) ?>">Older &rarr;</a><?php endif; ?>
</div>
<?php endif; ?>
<?php endif; ?>
<?php admin_panel_end(); ?>
<?php admin_footer(); ?>
