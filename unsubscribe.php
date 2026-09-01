<?php
declare(strict_types=1);

/**
 * One-click opt-out from alert email.
 *
 * Reached from the List-Unsubscribe header and from the footer link, both of
 * which have to work for someone who is not logged in - so the link carries a
 * signed token rather than relying on a session.
 *
 * Two behaviours, and the difference matters:
 *
 *   POST  - what Gmail and Yahoo send when the reader taps "unsubscribe" in
 *           their client (List-Unsubscribe-Post). It must act immediately and
 *           answer 200 with no page to read, because nobody is reading one.
 *   GET   - a person clicking the link in the mail. It shows a page and asks,
 *           because a link can be fetched by a scanner, a preview pane or an
 *           over-eager corporate mail filter, and any of those turning off a
 *           paying member's alerts without them knowing is worse than one
 *           extra click.
 */

$config = require __DIR__ . '/src/bootstrap.php';

use SignalMasterAi\Database;
use SignalMasterAi\MemberAuth;
use SignalMasterAi\View;

$token = (string)($_GET['t'] ?? $_POST['t'] ?? '');
$memberId = MemberAuth::verifyOptOutToken($token);
$isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';

$done = false;
$email = '';
if ($memberId > 0) {
    $stmt = Database::pdo()->prepare('SELECT email FROM members WHERE id = ?');
    $stmt->execute([$memberId]);
    $email = (string)($stmt->fetchColumn() ?: '');

    if ($isPost || ($_GET['confirm'] ?? '') === '1') {
        // Alerts off, watchlist kept. Someone who stops the email has not
        // asked to lose the pairs they chose - and deleting them would make
        // "turn it back on" mean "set it all up again".
        Database::pdo()->prepare('UPDATE member_alerts SET enabled = 0 WHERE member_id = ?')
            ->execute([$memberId]);
        \SignalMasterAi\Audit::log('member.unsubscribe', $email !== '' ? $email : ('member ' . $memberId),
            'email alerts on', 'email alerts off', 'member');
        $done = true;
    }
}

// The one-click path answers the mail client, not a human.
if ($isPost) {
    http_response_code($memberId > 0 ? 200 : 400);
    header('Content-Type: text/plain; charset=utf-8');
    echo $memberId > 0 ? 'Unsubscribed' : 'Invalid link';
    exit;
}

View::head('Email alerts', 'Manage the alert emails from this site.',
    // Reached from a one-time link in an email. Indexing it would put a
    // personal unsubscribe URL in a search result.
    ['noindex' => true]);
View::topbar('');
?>
<main class="page-wrap narrow">
  <div class="panel" style="max-width:560px;margin:32px auto;text-align:center">
    <?php if ($memberId <= 0): ?>
      <h1 style="font-size:20px">That link is not valid</h1>
      <p class="hint-p">It may have been copied incompletely from the email. You can always turn
        alerts off from your own account page.</p>
      <p style="margin-top:16px"><a class="btn-primary" href="account.php">Open your account</a></p>
    <?php elseif ($done): ?>
      <h1 style="font-size:20px">&#10003; Alert emails are off</h1>
      <p class="hint-p">No more signal emails will be sent
        <?= $email !== '' ? 'to <strong>' . sma_e($email) . '</strong>' : '' ?>.
        Your watched pairs have been kept, so turning alerts back on does not mean setting them up
        again &mdash; and browser and Telegram alerts, if you use them, are unaffected.</p>
      <p style="margin-top:16px"><a class="btn-primary" href="account.php">Change this back</a></p>
    <?php else: ?>
      <h1 style="font-size:20px">Turn off alert emails?</h1>
      <p class="hint-p">This stops the signal emails
        <?= $email !== '' ? 'to <strong>' . sma_e($email) . '</strong>' : '' ?>. Your watched pairs
        are kept and your account is not touched.</p>
      <p style="margin-top:18px">
        <a class="btn-primary" href="unsubscribe.php?t=<?= sma_e($token) ?>&amp;confirm=1">
          Yes, turn them off</a>
        <a class="btn" href="charts.php" style="margin-left:8px">No, keep them</a>
      </p>
    <?php endif; ?>
  </div>
</main>
<?php View::footer(); ?>
