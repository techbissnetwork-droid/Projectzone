<?php
declare(strict_types=1);

$config = require __DIR__ . '/_admin.php';

use SignalMasterAi\Auth;
use SignalMasterAi\Database;

Auth::requireLogin();
$pdo = Database::pdo();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf();
    $act = $_POST['act'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    // Granting premium used to flip the tier and nothing else, and paid_until
    // stayed at 0 - which the app reads as unlimited. Every manual upgrade was
    // therefore a lifetime one, whatever the admin had in mind, and there was
    // no way to sell a month.
    if ($act === 'grant') {
        $days = max(0, min(3650, (int)($_POST['days'] ?? 0)));
        $cur = $pdo->prepare('SELECT tier, paid_until FROM members WHERE id = ?');
        $cur->execute([$id]);
        $row = $cur->fetch();
        $cur->closeCursor();
        if ($row) {
            if ($days === 0) {
                $until = 0;                       // 0 means no expiry
            } else {
                // Extend from whatever is left rather than from today, so a
                // renewal a week early does not throw that week away.
                $from = ($row['tier'] === 'paid' && (int)$row['paid_until'] > time())
                    ? (int)$row['paid_until'] : time();
                $until = $from + $days * 86400;
            }
            $pdo->prepare('UPDATE members SET tier = ?, paid_until = ? WHERE id = ?')
                ->execute(['paid', $until, $id]);
            \SignalMasterAi\Audit::log('member.grant', 'member #' . $id,
                (string)$row['tier'],
                'paid' . ($until > 0 ? ' until ' . gmdate('Y-m-d', $until) : ', no expiry'));
            flash($days === 0
                ? 'Premium granted with no expiry.'
                : "Premium granted for $days day(s) - runs to " . gmdate('Y-m-d H:i', $until) . ' UTC.');
        }
    }
    if ($act === 'set_tier') {
        $tier = in_array($_POST['tier'] ?? '', ['free', 'paid'], true) ? $_POST['tier'] : 'free';
        // Downgrading clears the expiry too; leaving a stale timestamp behind
        // makes a later upgrade inherit a date nobody chose.
        $pdo->prepare('UPDATE members SET tier = ?, paid_until = 0 WHERE id = ?')->execute([$tier, $id]);
        // A downgrade is the same event as an expiry and must not have a
        // different outcome: the watchlist is pruned to what the new tier can
        // reach, here and now, rather than waiting for cron or for the member
        // to notice coins they can no longer open.
        $dropped = \SignalMasterAi\Membership::prune((int)$id, $tier);
        \SignalMasterAi\Audit::log('member.tier', 'member #' . $id, '', $tier);
        flash('Member tier updated.'
              . ($dropped > 0 ? ' ' . $dropped . ' watched pair(s) removed - out of reach on ' . $tier . '.' : ''));
    }
    if ($act === 'delete') {
        // Everything of theirs, not just the row - see Membership::erase().
        // One statement used to leave ten tables' worth behind, including a
        // push subscription that went on firing and an email address in the
        // send log.
        //
        // Payments are kept: a settled payment is an accounting record with a
        // life of its own, and it stores no more about the person than the id
        // of a member who no longer exists.
        $counts = \SignalMasterAi\Membership::erase($id, true);
        \SignalMasterAi\Audit::log('member.delete', 'member #' . $id, 'existed',
            'deleted with ' . \SignalMasterAi\Membership::describeErase($counts));
        flash('Member deleted, along with '
              . \SignalMasterAi\Membership::describeErase($counts)
              . '. Payment records are kept for your accounts.');
    }
    // Email verification, by hand. Someone will always write to say the code
    // never arrived; the operator who has confirmed the address another way
    // needs to be able to let them in without turning the feature off for
    // everybody, and needs to be able to ask for it again if they were wrong.
    if ($act === 'verify_member') {
        \SignalMasterAi\MemberVerify::markVerified($id);
        \SignalMasterAi\Audit::log('member.verify', 'member #' . $id, 'unverified', 'verified by admin');
        flash('Marked as verified - that member can log in now.');
    }
    if ($act === 'unverify_member') {
        \SignalMasterAi\MemberVerify::markUnverified($id);
        \SignalMasterAi\Audit::log('member.verify', 'member #' . $id, 'verified', 'code required again');
        flash('That member must verify by email before logging in again.');
    }
    if ($act === 'resend_otp') {
        [$ok, $msg] = \SignalMasterAi\MemberVerify::issue($id, false);
        \SignalMasterAi\Audit::log('member.verify', 'member #' . $id, '',
            $ok ? 'code re-sent by admin' : 'code re-send failed');
        flash($msg, $ok ? 'ok' : 'warn');
    }
    header('Location: members.php');
    exit;
}

// EVERY MEMBER, EVERY COLUMN, EVERY PAGE LOAD.
//
// This was SELECT * FROM members with no limit and no paging, rendered one
// row each - and on a phone the grid stacks, so each row is a block. It is
// the one list on the site that grows with the business: it is fine at
// twenty members, it is a page nobody can scroll at two thousand, and it
// loads every column of every row into memory to do it.
//
// Newest first is kept, because "who just signed up" is the question this
// screen is opened for. A search box narrows by address for the other one,
// and the limit is generous enough that the operator of a small site never
// meets it.
$mSearch = trim((string)($_GET['q'] ?? ''));
$mLimit = max(25, min(500, (int)($_GET['n'] ?? 100)));
$mTotal = (int)$pdo->query('SELECT COUNT(*) FROM members')->fetchColumn();
if ($mSearch !== '') {
    // LIKE with the wildcards bound, not concatenated into the SQL.
    $q = $pdo->prepare('SELECT * FROM members WHERE email LIKE ? ORDER BY created_at DESC LIMIT ' . ($mLimit + 1));
    $q->execute(['%' . $mSearch . '%']);
} else {
    $q = $pdo->query('SELECT * FROM members ORDER BY created_at DESC LIMIT ' . ($mLimit + 1));
}
$members = $q->fetchAll();
// One more than asked for is fetched purely to know whether there IS more,
// without a second COUNT over a filtered set.
$mMore = count($members) > $mLimit;
if ($mMore) { array_pop($members); }
// Stat cards describe the whole install, not the current search - a
// zero-match query must not make the site look like it has no members.
$paidCount = (int)$pdo->query("SELECT COUNT(*) FROM members WHERE tier = 'paid'")->fetchColumn();
$unverifiedCount = (int)$pdo->query('SELECT COUNT(*) FROM members WHERE verified = 0')->fetchColumn();
$csrf = Auth::csrfToken();

admin_header('Members', 'members',
    'Everyone who registered on the site, and what their account unlocks. Free members get the '
    . '"free" tier coins; a paid member gets the premium ones. Sign-up itself, and whether an email '
    . 'code is required, are under <a href="settings.php#members">Settings &rsaquo; Members</a>.');
show_flash();
?>
<div class="cards">
  <div class="card"><div class="num"><?= number_format($mTotal) ?></div><div class="lbl">Registered members</div></div>
  <div class="card"><div class="num"><?= number_format($paidCount) ?></div><div class="lbl">Paid members</div></div>
  <div class="card"><div class="num"><?= number_format($unverifiedCount) ?></div><div class="lbl">Waiting on an email code</div></div>
</div>
<?php if ($unverifiedCount > 0): ?>
  <p class="hint" style="margin-bottom:16px">A row marked <strong>UNVERIFIED</strong> registered but
    never typed the code, so it cannot log in. Usually that is a mistyped address or a code sitting in
    a spam folder &mdash; check the <a href="emails.php" style="color:var(--accent)">Email log</a> to
    see whether the code was actually accepted for delivery. Requiring codes at all is a switch in
    <a href="settings.php#members" style="color:var(--accent)">Settings &rsaquo; Members</a>.</p>
<?php endif; ?>

<?php if (!$members && $mSearch === ''): ?>
  <p class="hint">No members yet - visitors can register from the site header.</p>
<?php else: ?>
<?php
// Everything about one member, in one place.
//
// The actions cell held six forms - grant, downgrade, re-send code, mark
// verified, require a code, delete - wrapped in conditionals, so which of
// them a row showed depended on the member. Six buttons in a table cell is a
// column nobody can read and a row that reflows differently every time.
//
// One panel now, reached from one link, grouped by what the action is for.
$mid = (int)($_GET['member'] ?? 0);
$mRow = null;
if ($mid > 0) {
    foreach ($members as $mm) {
        if ((int)$mm['id'] === $mid) { $mRow = $mm; break; }
    }
}
if ($mRow): $m = $mRow;
    $isPaid = $m['tier'] === 'paid';
    $isVerified = (int)($m['verified'] ?? 1) === 1;
?>
<div class="form-card coin-hub" id="member">
  <h2 style="margin-top:0">
    <?= sma_e($m['email']) ?>
    <span class="badge <?= $isPaid ? 'neutral' : 'on' ?>"><?= $isPaid ? '&#9733; PAID' : 'FREE' ?></span>
    <?php if (!$isVerified): ?><span class="badge neutral">UNVERIFIED</span><?php endif; ?>
    <a class="btn small gray" href="members.php" style="float:right">Close</a>
  </h2>
  <p class="hint">Registered <?= gmdate('Y-m-d H:i', (int)$m['created_at']) ?> UTC<?php
    if ($isPaid): $pu = (int)$m['paid_until'];
      echo $pu === 0 ? ' &middot; premium with no expiry'
         : ' &middot; premium until ' . gmdate('Y-m-d', $pu)
           . ($pu > time() ? ' (' . (int)ceil(($pu - time()) / 86400) . ' days left)' : ' (expired)');
    endif; ?></p>

  <details class="coin-grp" id="member-access" open>
    <summary>Access <span class="grp-state"><?= $isPaid ? 'premium' : 'free' ?></span></summary>
    <p class="hint">Give or extend premium by hand &mdash; for a payment taken outside the site, or to
      make good on a problem. Downgrading takes effect immediately.</p>
    <div class="coin-acts">
        <form class="inline-form" method="post" action="members.php" style="gap:6px">
          <input type="hidden" name="csrf" value="<?= $csrf ?>">
          <input type="hidden" name="act" value="grant">
          <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
          <select name="days" style="width:auto;padding:6px 8px">
            <option value="7">7 days</option>
            <option value="30" selected>1 month</option>
            <option value="90">3 months</option>
            <option value="180">6 months</option>
            <option value="365">1 year</option>
            <option value="0">No expiry</option>
          </select>
          <button class="btn small" type="submit">
            <?= $m['tier'] === 'paid' ? 'Extend' : 'Give premium' ?></button>
        </form>
<?php if ($isPaid): ?>
        <form class="inline-form" method="post" action="members.php">
          <input type="hidden" name="csrf" value="<?= $csrf ?>">
          <input type="hidden" name="act" value="set_tier">
          <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
          <input type="hidden" name="tier" value="free">
          <button class="btn small gray" type="submit">Downgrade</button>
        </form>
<?php endif; ?>
    </div>
  </details>

  <details class="coin-grp" id="member-verify">
    <summary>Email verification <span class="grp-state"><?= $isVerified ? 'verified' : 'waiting for a code' ?></span></summary>
    <p class="hint">An unverified account holds no session and cannot log in. Re-send the code if it
      never arrived, or mark the address good yourself once you have confirmed it another way.</p>
    <div class="coin-acts">
<?php if (!$isVerified): ?>
        <form class="inline-form" method="post" action="members.php">
          <input type="hidden" name="csrf" value="<?= $csrf ?>">
          <input type="hidden" name="act" value="resend_otp">
          <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
          <button class="btn small gray" type="submit" title="Send a fresh code to this address now">Re-send code</button>
        </form>
        <form class="inline-form" method="post" action="members.php">
          <input type="hidden" name="csrf" value="<?= $csrf ?>">
          <input type="hidden" name="act" value="verify_member">
          <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
          <button class="btn small" type="submit" title="Skip the code and let this member log in">Mark verified</button>
        </form>
<?php else: ?>
        <form class="inline-form" method="post" action="members.php"
              onsubmit="return confirm('Require this member to verify by email before they can log in again?')">
          <input type="hidden" name="csrf" value="<?= $csrf ?>">
          <input type="hidden" name="act" value="unverify_member">
          <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
          <button class="btn small gray" type="submit" title="Log them out of nothing - they simply need a code next time">Require code</button>
        </form>
<?php endif; ?>
    </div>
  </details>

  <details class="coin-grp" id="member-danger">
    <summary>Delete <span class="grp-state">permanent</span></summary>
    <p class="hint">Removes the account and everything attached to it. Payment records are kept, so
      the revenue figures stay honest.</p>
    <div class="coin-acts">
        <form class="inline-form" method="post" action="members.php"
              onsubmit="return confirm('Delete member <?= sma_e($m['email']) ?>?')">
          <input type="hidden" name="csrf" value="<?= $csrf ?>">
          <input type="hidden" name="act" value="delete">
          <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
          <button class="btn small danger" type="submit">Delete</button>
        </form>
    </div>
  </details>
  <script>
  (function () {
    var open = function () {
      var h = location.hash.replace('#', '');
      if (!h) return;
      var el = document.getElementById(h);
      if (el && el.tagName === 'DETAILS') { el.open = true; el.scrollIntoView({ block: 'start' }); }
    };
    open(); window.addEventListener('hashchange', open);
  })();
  </script>
</div>
<?php endif; ?>

<form method="get" action="members.php" class="inline" style="margin:0 0 10px">
  <div class="row2">
    <div><label>Find a member by email</label>
      <input aria-label="Find a member by email" type="search" name="q" value="<?= sma_e($mSearch) ?>"
             placeholder="part of an address"></div>
    <div><label>Show at most</label>
      <select aria-label="Show at most" name="n">
        <?php foreach ([25, 50, 100, 250, 500] as $optN): ?>
          <option value="<?= $optN ?>" <?= $mLimit === $optN ? 'selected' : '' ?>><?= $optN ?></option>
        <?php endforeach; ?>
      </select></div>
  </div>
  <button class="btn small" type="submit">Search</button>
  <?php if ($mSearch !== ''): ?>
    <a class="btn small gray" href="members.php">Clear</a>
  <?php endif; ?>
</form>
<p class="hint" style="margin-top:0">
  Showing <?= count($members) ?> of <?= $mSearch !== '' ? 'the matches' : number_format($mTotal)
    . ' member' . ($mTotal === 1 ? '' : 's') ?>, newest first.
  <?= $mMore ? 'There are more - narrow the search or raise the limit above.' : '' ?></p>

<div class="tbl-scroll">
<table class="grid">
  <tr><th>Email</th><th>Tier</th><th>Paid until</th><th>Registered (UTC)</th><th style="width:220px">Actions</th></tr>
  <?php if (!$members): ?>
    <tr><td colspan="5" class="hint"><?= $mSearch !== ''
        ? 'No member matches ' . sma_e($mSearch) . '.' : 'No members yet.' ?></td></tr>
  <?php endif; ?>
  <?php foreach ($members as $m): ?>
  <tr>
    <td><?= sma_e($m['email']) ?>
      <?php if ((int)($m['verified'] ?? 1) === 0): ?>
        <span class="badge neutral" title="Registered but never typed the emailed code, so cannot log in">UNVERIFIED</span>
      <?php endif; ?></td>
    <td><span class="badge <?= $m['tier'] === 'paid' ? 'neutral' : 'on' ?>"><?= $m['tier'] === 'paid' ? '★ PAID' : 'FREE' ?></span></td>
    <td>
      <?php if ($m['tier'] === 'paid'):
              $pu = (int)$m['paid_until'];
              if ($pu === 0): ?>
                <span class="badge neutral">no expiry</span>
              <?php else: $left = $pu - time(); ?>
                <?= gmdate('Y-m-d', $pu) ?>
                <span class="hint" style="display:block"><?= $left > 0
                    ? (int)ceil($left / 86400) . ' day(s) left'
                    : 'expired ' . (int)floor(-$left / 86400) . ' day(s) ago' ?></span>
              <?php endif;
            else: ?>—<?php endif; ?>
    </td>
    <td><?= gmdate('Y-m-d H:i', (int)$m['created_at']) ?></td>
    <td>
      <a class="btn small gray" href="members.php?member=<?= (int)$m['id'] ?>#member">Manage</a>
    </td>
  </tr>
  <?php endforeach; ?>
</table>
</div>
<?php endif; ?>
<?php admin_footer(); ?>
