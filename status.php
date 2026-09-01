<?php
declare(strict_types=1);

/**
 * Public status page.
 *
 * When the market feed stalled, the cron worker died or the news sources went
 * stale, nothing anywhere said so - the site kept serving increasingly old
 * signals as if they were current. This surfaces the health checks the admin
 * dashboard hints at, without exposing any configuration.
 */

$config = require __DIR__ . '/src/bootstrap.php';

use SignalMasterAi\Database;
use SignalMasterAi\Maintenance;
use SignalMasterAi\MemberAuth;
use SignalMasterAi\View;

MemberAuth::start();
$health = Maintenance::health();

// Data-quality flags raised by the continuity audit during candle refreshes.
$gapRows = [];
foreach (Database::pdo()->query("SELECT ckey, cvalue FROM cache WHERE ckey LIKE 'gaps:%' AND expires_at > " . time()) as $row) {
    $missing = (int)json_decode((string)$row['cvalue'], true);
    if ($missing > 0) {
        $parts = explode(':', (string)$row['ckey']);
        $gapRows[] = ['symbol' => $parts[1] ?? '?', 'tf' => $parts[2] ?? '?', 'missing' => $missing];
    }
}
usort($gapRows, fn($a, $b) => $b['missing'] <=> $a['missing']);
$gapRows = array_slice($gapRows, 0, 10);

if (($_GET['format'] ?? '') === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    // The same redaction as the page above it. This fed the raw detail, so
    // "32 coins in the rotation" was one curl away for anyone - the page was
    // careful and the machine-readable version of the page was not.
    echo json_encode(['status' => $health['status'], 'checks' => array_map(
        fn($c) => ['ok' => $c['ok'], 'detail' => (string)($c['public_detail'] ?? $c['detail'])],
        $health['checks']
    ), 'at' => $health['at']]);
    exit;
}

$statusLabel = ['operational' => 'All systems operational',
                'partial' => 'Partially degraded',
                'degraded' => 'Degraded'][$health['status']] ?? 'Unknown';

View::head('System status', 'Live health of the market data feed, background worker and alert delivery.');
View::topbar('');
?>
<main class="page-wrap narrow">
  <div class="page-head">
    <h1>System status</h1>
    <p>The engine is only as current as the data behind it. This page reports whether the pieces
       that keep signals fresh are actually running.</p>
  </div>

  <div class="status-banner <?= sma_e($health['status']) ?>">
    <span class="dot" aria-hidden="true"></span>
    <strong><?= sma_e($statusLabel) ?></strong>
    <span class="muted">checked <?= sma_e(gmdate('H:i', $health['at'])) ?> UTC</span>
  </div>

  <section class="panel">
    <ul class="check-list">
      <?php foreach ($health['checks'] as $c): ?>
        <?php // Optional infrastructure is not service status.
              //
              // "Email delivery - not configured" told every visitor that the
              // operator has not set up SMTP. It is a soft check precisely
              // because nothing a reader depends on is affected by it, so on
              // this page it is noise that reads as a fault. The admin
              // dashboard shows all of them.
              if (!empty($c['soft']) && !$c['ok']) { continue; }
              // And an operator-only check is never shown here at all, pass or
              // fail - see the note on 'admin_only' in Maintenance::health().
              if (!empty($c['admin_only'])) { continue; } ?>
        <li class="<?= $c['ok'] ? 'ok' : (!empty($c['soft']) ? 'soft' : 'bad') ?>">
          <span class="check-mark" aria-hidden="true"><?= $c['ok'] ? '✓' : (!empty($c['soft']) ? '–' : '✕') ?></span>
          <div>
            <strong><?= sma_e($c['label']) ?></strong>
            <span class="check-detail"><?= sma_e((string)($c['public_detail'] ?? $c['detail'])) ?></span>
            <?php // The operator's fix does NOT belong on a public page.
                  //
                  // This printed $c['hint'], which is written for whoever owns
                  // the site: "Add the cron job shown on the admin dashboard",
                  // "Optional: set SMTP in Settings > Email". A visitor was
                  // being handed instructions for a machine they cannot reach,
                  // about a panel they cannot open - and told, in the same
                  // breath, exactly which piece of the site is broken and how.
                  // What a reader needs here is whether the thing works and
                  // what it means for them, which is what 'public' says. ?>
            <?php if (!$c['ok'] && ($c['public'] ?? '') !== ''): ?>
              <span class="check-hint"><?= sma_e((string)$c['public']) ?></span>
            <?php endif; ?>
          </div>
          <span class="visually-hidden"><?= $c['ok'] ? 'healthy' : 'needs attention' ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>

  <?php if ($gapRows): ?>
  <section class="panel">
    <h2>Data quality</h2>
    <p class="hint-p">Gaps detected in cached candle series. Missing bars are repaired automatically
      from the exchange; a series listed here was recently incomplete, which is worth knowing before
      trusting a signal on it.</p>
    <div class="tbl-scroll">
      <table class="scan-table">
        <thead><tr><th>Pair</th><th>Timeframe</th><th>Missing bars</th></tr></thead>
        <tbody>
        <?php foreach ($gapRows as $g): ?>
          <tr><th scope="row"><?= sma_e($g['symbol']) ?></th><td><?= sma_e($g['tf']) ?></td>
              <td class="num"><?= (int)$g['missing'] ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
  <?php endif; ?>

  <p class="hint-p" style="text-align:center;margin-top:18px">
    Machine-readable: <a href="status.php?format=json">status.php?format=json</a>
  </p>
</main>
<?php View::footer(); ?>
