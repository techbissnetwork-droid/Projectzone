<?php
declare(strict_types=1);

/**
 * The API this site exposes, and what it is doing right now.
 *
 * Everything the front end does goes through api.php, which means the site
 * already has a working JSON API - and nowhere that says so. An operator
 * wanting to drive a bot, feed a spreadsheet or wire a coin's verdict into
 * something else had to read the source to find out what exists. The outbound
 * half, a member's own webhook fired on every flip, was equally undocumented
 * and equally invisible when it broke.
 *
 * Read-only on purpose: every control lives once, in Settings, and this page
 * links to it rather than offering a second copy that can disagree.
 */

$config = require __DIR__ . '/_admin.php';

use SignalMasterAi\Auth;
use SignalMasterAi\Database;
use SignalMasterAi\MarketData;

Auth::requireLogin();
$pdo = Database::pdo();

// What the site serves. Auth column is what a caller needs, not what an
// endpoint checks internally - a public coin needs nothing, a premium one
// needs the session of a member whose tier reaches it.
$endpoints = [
    ['symbols', 'GET', 'none', 'Every coin visible on the chart surface, with its tier and whether the caller may open it.'],
    ['candles&symbol=&tf=', 'GET', 'tier of the coin', 'OHLCV bars, oldest first. The same data the chart draws.'],
    ['signal&symbol=&tf=', 'GET', 'tier of the coin', 'Current verdict, score, confidence and the published entry/stop/targets.'],
    ['ticker&symbol=', 'GET', 'tier of the coin', 'Last traded price. Cached briefly; safe to poll.'],
    ['mtf&symbol=', 'GET', 'tier of the coin', 'The verdict on every timeframe at once, for an agreement view.'],
    ['context&symbol=&tf=', 'GET', 'tier of the coin', 'Regime, higher-timeframe agreement and how long the setup stays valid.'],
    // Documented as "none" until this was checked against the code: it needs a
    // paid member's 48-character API token, and answers 401 without one. A page
    // whose whole job is documenting the API had one row saying a premium feed
    // was public.
    ['feed&token=', 'GET', "a paid member's API token", 'The latest verdict per coin and timeframe across the scanner surface.'],
    ['news', 'GET', 'none', 'Headlines with their sentiment, as used on the chart.'],
    ['alerts', 'GET', 'none', 'Board data behind the scanner.'],
    ['prefs', 'GET/POST', 'member session', "The member's own alert filters, sizing and timezone."],
    ['watchlists', 'GET/POST', 'member session', 'Create, rename, activate and fill watchlists.'],
    ['alert_email', 'POST', 'member session', 'Turn email alerts on or off and set the watched pairs.'],
    ['push_subscribe / push_sync / push_unsubscribe', 'POST', 'member session', 'Browser push subscription lifecycle.'],
    ['paper', 'GET/POST', 'member session', 'Followed positions: open, close, list.'],
    ['ask', 'POST', 'member session', 'Ask a follow-up question about a setup (needs an AI key).'],
];

$rateOn = Database::setting('rate_limit_enabled', '1') === '1';
$hooksOn = Database::setting('webhooks_enabled', '1') === '1';
$aiKey = Database::setting('ai_api_key') !== '';
$hookMembers = 0;
try {
    $hookMembers = (int)$pdo->query("SELECT COUNT(*) FROM member_prefs WHERE webhook_url != ''")->fetchColumn();
} catch (\Throwable $e) {
}
// The endpoints actually in use, not the setting - left blank, the setting
// means "use the configured defaults", and reading it raw reported zero data
// sources on a site that was fetching happily.
try {
    $endpointList = MarketData::endpoints($config);
} catch (\Throwable $e) {
    $endpointList = [];
}
$siteUrl = Database::setting('site_url');
$base = $siteUrl !== '' ? rtrim($siteUrl, '/') : '(your site URL)';

admin_header('API & webhooks', 'api',
    'What this site serves to other programs, and what it sends out to them. Both directions '
    . 'obey the same coin and tier rules as the pages.');
show_flash();
?>
<div class="cards">
  <div class="card"><div class="num"><?= count($endpoints) ?></div><div class="lbl">Endpoints served</div></div>
  <div class="card"><div class="num" style="color:<?= $rateOn ? 'var(--up)' : 'var(--warn)' ?>">
    <?= $rateOn ? 'On' : 'Off' ?></div><div class="lbl">Rate limiting</div></div>
  <div class="card"><div class="num"><?= $hookMembers ?></div><div class="lbl">Members with a webhook</div></div>
  <div class="card"><div class="num"><?= count($endpointList) ?></div><div class="lbl">Market data sources</div></div>
</div>

<?php admin_panel('Outbound: what this site serves',
    'Every action the front end performs goes through one file, so this site already publishes a '
    . 'JSON API whether or not that was the intention. Authentication is the ordinary member '
    . 'session cookie - there are no separate API keys.'); ?>
  <p class="hint">Call it as <code><?= sma_e($base) ?>/api.php?action=&hellip;</code>. Responses are
    JSON with an <code>ok</code> field; a failure carries <code>error</code> and an HTTP status to
    match. Anything a logged-out visitor cannot see through the site is not reachable here either.</p>
  <p class="hint"><strong>Coin visibility applies.</strong> A coin switched off under
    <a href="symbols.php">Coins</a>, or hidden from the Chart surface, is refused here exactly as it
    is on the page &mdash; the API is not a way round the master switch.</p>

  <div class="tbl-scroll">
  <table class="grid">
    <tr class="head"><th>Action</th><th>Method</th><th>Needs</th><th>Returns</th></tr>
    <?php foreach ($endpoints as [$a, $m, $auth, $what]): ?>
    <tr>
      <td><code><?= sma_e($a) ?></code></td>
      <td class="muted"><?= sma_e($m) ?></td>
      <td class="muted"><?= sma_e($auth) ?></td>
      <td><?= sma_e($what) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  </div>

  <p class="hint" style="margin-top:12px">Rate limiting is
    <strong><?= $rateOn ? 'enabled' : 'disabled' ?></strong>.
    <?= $rateOn
      ? 'One analysis is a full pass of the rule set and an anonymous visitor can ask for one, so this should stay on.'
      : 'A single script can currently pin the CPU and burn the exchange quota.' ?>
    Change it under <a href="settings.php#security">Settings &rsaquo; Security</a>.</p>
<?php admin_panel_end(); ?>

<?php admin_panel('Outbound: member webhooks',
    'The other direction, per member: a URL they own, and one POST for every flip on a pair they '
    . 'watch. One request per event, never batched, and signed so the receiver can tell it came '
    . 'from here.'); ?>
  <p class="hint">The body carries <code>symbol</code>, <code>tf</code>, <code>signal</code>,
    <code>score</code>, <code>price</code>, <code>grade</code>, <code>confidence</code> and the
    <code>levels</code> object.</p>
  <p class="hint">Webhooks are <strong><?= $hooksOn ? 'enabled' : 'disabled' ?></strong> site-wide
    (<a href="settings.php#members">Settings</a>), and
    <strong><?= $hookMembers ?></strong> member<?= $hookMembers === 1 ? ' has' : 's have' ?> one
    configured. A member's endpoint that starts failing is retried and then backed off &mdash; it can
    never stall the alert sweep &mdash; and the failures show up in the
    <a href="errors.php">error log</a>.</p>
<?php admin_panel_end(); ?>

<?php admin_panel('Inbound: what this site calls',
    'Two outward dependencies. Both fail soft - which is exactly why they need somewhere to be '
    . 'visible, or a broken one looks like nothing at all.'); ?>
  <div class="tbl-scroll">
  <table class="grid">
    <tr class="head"><th>Service</th><th>State</th><th>Where</th></tr>
    <tr>
      <td><strong>Market data</strong></td>
      <td><?= count($endpointList) ?> endpoint<?= count($endpointList) === 1 ? '' : 's' ?>
        <?php if ($endpointList): ?>
          <div class="muted small"><?= sma_e(implode(', ', array_map(
              static fn($u) => MarketData::provider($u) . ' (' . parse_url($u, PHP_URL_HOST) . ')',
              array_slice($endpointList, 0, 4)))) ?></div>
        <?php endif; ?></td>
      <td><a href="settings.php#market">Settings &rsaquo; Market data</a></td>
    </tr>
    <tr>
      <td><strong>AI explanations</strong></td>
      <td><?= $aiKey ? 'Key saved' : 'No key — the site runs without it' ?></td>
      <td><a href="settings.php#signals">Settings &rsaquo; Signals</a></td>
    </tr>
  </table>
  </div>
  <p class="hint" style="margin-top:10px">A refused fetch or a rejected key does not take a page
    down &mdash; it is caught and written to the <a href="errors.php">error log</a> with the setting
    that fixes it.</p>
<?php admin_panel_end(); ?>
<?php admin_footer(); ?>
