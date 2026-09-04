<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/icons.php';
require_once __DIR__ . '/../includes/admin_layout.php';
require_installed('../install/');

$staff = require_staff();
require_staff_access($staff, 'marketing.php');
$pdo = db();

$canSubmit = staff_has_permission($staff, 'marketing_submit');
$canReview = staff_has_permission($staff, 'marketing_review');
$LEAD_REWARD_CENTS = 10;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if (!csrf_check((string)($_POST['csrf'] ?? ''))) {
        flash('Your session expired — please try again.', 'error');
    } elseif ($action === 'submit' && $canSubmit) {
        $name = trim((string)($_POST['business_name'] ?? ''));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $address = trim((string)($_POST['address'] ?? ''));
        $notes = trim((string)($_POST['notes'] ?? ''));
        if ($name === '' || $phone === '' || $address === '') {
            flash('Business name, phone and address are all required.', 'error');
        } else {
            $pdo->prepare('INSERT INTO marketing_leads (staff_id, business_name, phone, address, notes) VALUES (?,?,?,?,?)')
                ->execute([$staff['id'], $name, $phone, $address, $notes !== '' ? $notes : null]);
            $pdo->prepare('UPDATE staff SET marketing_earnings_cents = marketing_earnings_cents + ? WHERE id = ?')
                ->execute([$LEAD_REWARD_CENTS, $staff['id']]);
            flash('Lead submitted — $' . number_format($LEAD_REWARD_CENTS / 100, 2) . ' added to your marketing earnings.');
        }
    } elseif ($action === 'review' && $canReview) {
        $id = (int)($_POST['id'] ?? 0);
        $status = in_array($_POST['status'] ?? '', ['Approved', 'Rejected'], true) ? $_POST['status'] : null;
        if ($status) {
            $pdo->prepare('UPDATE marketing_leads SET status=?, reviewed_by_staff_id=?, reviewed_at=NOW() WHERE id=?')
                ->execute([$status, $staff['id'], $id]);
            flash('Lead marked ' . strtolower($status) . '.');
        }
    }
    header('Location: marketing.php');
    exit;
}

if ($canReview) {
    $leads = $pdo->query(
        "SELECT l.*, s.name AS staff_name, r.name AS reviewer_name
         FROM marketing_leads l
         JOIN staff s ON s.id = l.staff_id
         LEFT JOIN staff r ON r.id = l.reviewed_by_staff_id
         ORDER BY FIELD(l.status,'Pending','Approved','Rejected'), l.created_at DESC"
    )->fetchAll();
} else {
    $stmt = $pdo->prepare(
        "SELECT l.*, s.name AS staff_name, r.name AS reviewer_name
         FROM marketing_leads l
         JOIN staff s ON s.id = l.staff_id
         LEFT JOIN staff r ON r.id = l.reviewed_by_staff_id
         WHERE l.staff_id = ?
         ORDER BY l.created_at DESC"
    );
    $stmt->execute([$staff['id']]);
    $leads = $stmt->fetchAll();
}

$statusTone = ['Pending' => 'warning', 'Approved' => 'success', 'Rejected' => 'danger'];
$token = csrf_token();
?>
<!doctype html>
<html lang="en"<?= palette_attr() . logo_motion_attr() ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Marketing — TECHBISS Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/style.css?v=<?= @filemtime(__DIR__ . '/../assets/style.css') ?: '1' ?>">
</head>
<body>
<?= admin_header($staff, 'marketing.php') ?>
<main class="admin-page">
  <?= admin_flash_html() ?>
  <div class="admin-toolbar">
    <div><h1 style="margin-bottom:4px;">Marketing</h1><p class="lede" style="margin-bottom:0;">Offline businesses found in the field, ready for a follow-up call or visit.</p></div>
  </div>

  <?php if ($canSubmit): ?>
  <div class="card admin-form-card">
    <div class="card-head"><?= blob_icon('plus', 'sm', true) ?><h3>Submit a lead</h3></div>
    <p class="lede" style="margin-bottom:14px;">Every submission adds $<?= number_format($LEAD_REWARD_CENTS / 100, 2) ?> to your marketing earnings — <b>$<?= number_format(((int)$staff['marketing_earnings_cents']) / 100, 2) ?></b> so far.</p>
    <form method="post">
      <input type="hidden" name="action" value="submit">
      <input type="hidden" name="csrf" value="<?= e($token) ?>">
      <div class="grid grid-2" style="gap:16px;">
        <div class="field"><label>Business name</label><input name="business_name" required placeholder="e.g. Corner Hardware & Repair"></div>
        <div class="field"><label>Phone number</label><input name="phone" required placeholder="+1 555 0100"></div>
      </div>
      <div class="field"><label>Address</label><input name="address" required placeholder="Street, city, state"></div>
      <div class="field"><label>Notes <small style="font-weight:400;color:var(--ink-faint);">(optional)</small></label><textarea name="notes" placeholder="What do they do? Why are they a good fit?"></textarea></div>
      <button class="btn btn-primary" type="submit">Submit lead</button>
    </form>
  </div>
  <?php endif; ?>

  <div class="card">
    <div class="card-head"><?= blob_icon('pin', 'sm', true) ?><h3><?= $canReview ? 'All submitted leads' : 'Your submitted leads' ?></h3></div>
    <div class="table-wrap"><table><thead><tr><th>Business</th><th>Contact</th><?php if ($canReview): ?><th>Submitted by</th><?php endif; ?><th>Status</th><th></th></tr></thead><tbody>
      <?php foreach ($leads as $l): ?>
      <tr>
        <td style="font-weight:600;max-width:220px;"><?= e($l['business_name']) ?><?= $l['notes'] ? '<br><span style="font-weight:400;color:var(--ink-faint);font-size:.82rem;">' . e(mb_strimwidth($l['notes'], 0, 80, '…')) . '</span>' : '' ?></td>
        <td>
          <a class="card-link" style="font-size:.85rem;margin-bottom:4px;" href="tel:<?= e(preg_replace('/[^0-9+]/', '', $l['phone'])) ?>"><?= ico('phone') ?> <?= e($l['phone']) ?></a><br>
          <a class="card-link" style="font-size:.85rem;" href="https://www.google.com/maps/search/?api=1&query=<?= urlencode($l['address']) ?>" target="_blank" rel="noopener"><?= ico('pin') ?> <?= e(mb_strimwidth($l['address'], 0, 40, '…')) ?></a>
        </td>
        <?php if ($canReview): ?><td style="color:var(--ink-faint);"><?= e($l['staff_name']) ?></td><?php endif; ?>
        <td>
          <span class="badge <?= $statusTone[$l['status']] ?? '' ?>"><?= e($l['status']) ?></span>
          <?php if ($l['status'] !== 'Pending' && $l['reviewer_name']): ?><br><span style="font-size:.76rem;color:var(--ink-faint);">by <?= e($l['reviewer_name']) ?></span><?php endif; ?>
        </td>
        <td class="admin-actions-cell">
          <?php if ($canReview && $l['status'] === 'Pending'): ?>
          <form method="post" style="display:inline;">
            <input type="hidden" name="action" value="review">
            <input type="hidden" name="csrf" value="<?= e($token) ?>">
            <input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
            <input type="hidden" name="status" value="Approved">
            <button class="icon-btn" type="submit" aria-label="Approve"><?= ico('shield') ?></button>
          </form>
          <form method="post" style="display:inline;">
            <input type="hidden" name="action" value="review">
            <input type="hidden" name="csrf" value="<?= e($token) ?>">
            <input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
            <input type="hidden" name="status" value="Rejected">
            <button class="icon-btn danger" type="submit" aria-label="Reject"><?= ico('close') ?></button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$leads): ?><tr><td colspan="<?= $canReview ? 5 : 4 ?>" style="color:var(--ink-faint);">No leads yet.</td></tr><?php endif; ?>
    </tbody></table></div>
  </div>
</main>
<?= admin_bottomnav($staff, 'marketing.php') ?>
</body>
</html>
