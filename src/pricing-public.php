<?php
declare(strict_types=1);

/**
 * The pricing page a signed-out visitor sees.
 *
 * Included by upgrade.php when there is no member, and it renders the whole
 * response. Kept out of upgrade.php because everything in that file after the
 * membership check assumes a member exists; a second layout threaded through
 * fifty conditionals is how both of them end up wrong.
 *
 * What it is allowed to say is decided by the same table the application
 * enforces at runtime - Gate::FEATURES and Gate::tier() - so a feature moved
 * between tiers in the admin panel changes this page in the same edit. A
 * pricing page maintained separately from the gate is a pricing page that
 * eventually lies about what you get, and this one takes money.
 *
 * @var array $config  from bootstrap
 * @var PDO   $pdo
 * @var string $siteName
 */

use SignalMasterAi\Database;
use SignalMasterAi\Gate;
use SignalMasterAi\View;

$plans = $pdo->query('SELECT * FROM plans WHERE enabled = 1 ORDER BY sort, days')->fetchAll();

// Free and premium, from the gate rather than from a list somebody typed.
// 'any' features are shown as free too: a signed-out visitor asking what the
// free tier gets should be told about the things they already have.
$freeRows = [];
$paidRows = [];
foreach (Gate::FEATURES as $key => $f) {
    $row = ['label' => (string)$f['label'], 'sell' => (string)($f['sell'] ?? $f['blurb'] ?? '')];
    if (Gate::tier($key) === 'paid') {
        $paidRows[] = $row;
    } else {
        $freeRows[] = $row;
    }
}

// The cheapest plan, for the headline. Per-day so plans of different lengths
// can be compared honestly rather than by sticker price.
$best = null;
foreach ($plans as $p) {
    $days = max(1, (int)$p['days']);
    $perDay = (float)$p['price_usd'] / $days;
    if ($best === null || $perDay < $best['perDay']) {
        $best = ['perDay' => $perDay, 'plan' => $p];
    }
}

ob_start();
?>
.pp-wrap { max-width: 1060px; margin: 0 auto; padding: 30px 16px 40px; }
.pp-head { text-align: center; max-width: 640px; margin: 0 auto 28px; }
.pp-head h1 { font-size: clamp(26px, 6vw, 38px); letter-spacing: -0.6px; margin: 0 0 10px; line-height: 1.15; }
.pp-head p { color: var(--muted); font-size: clamp(14px, 3.6vw, 16px); line-height: 1.6; margin: 0; }
/* Two across on a phone rather than four down. Four full-width cards is a
   whole screen of scrolling to compare four numbers, and a price list you have
   to scroll to compare is a price list nobody compares. 150px is the width at
   which "$199.99" still fits on one line at 375px. */
.pp-plans { display: grid; gap: 10px; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); margin-bottom: 14px; }
@media (min-width: 700px) { .pp-plans { grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; } }
.pp-plan {
  position: relative; background: var(--surface); border: 1px solid var(--border);
  border-radius: 14px; padding: 20px 16px; text-align: center;
}
.pp-plan.best { border-color: var(--accent); box-shadow: 0 0 0 1px var(--accent) inset; }
.pp-tag {
  position: absolute; top: -10px; left: 50%; transform: translateX(-50%);
  background: var(--accent); color: #fff; font-size: 10.5px; font-weight: 500;
  letter-spacing: 0.6px; text-transform: uppercase; padding: 3px 10px; border-radius: 999px;
  white-space: nowrap;
}
.pp-plan .nm { font-size: 14px; font-weight: 500; }
.pp-plan .pr { font-size: clamp(22px, 6vw, 30px); font-weight: 500; color: var(--accent); margin: 8px 0 2px; line-height: 1; }
.pp-plan .dy { font-size: 12px; color: var(--muted); }
.pp-plan .per { font-size: 11.5px; color: var(--muted); margin-top: 6px; }
.pp-cta { display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; margin: 22px 0 6px; }
.pp-btn {
  display: inline-flex; align-items: center; justify-content: center; min-height: 48px;
  padding: 0 26px; border-radius: 11px; font-size: 15px; font-weight: 500;
  text-decoration: none; border: 1px solid transparent;
}
.pp-btn.primary { background: var(--accent); color: #fff; }
.pp-btn.ghost { background: var(--surface2); border-color: var(--border); color: var(--text); }
.pp-note { text-align: center; color: var(--muted); font-size: 12.5px; margin: 0 0 30px; line-height: 1.6; }
.pp-cols { display: grid; gap: 14px; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); }
.pp-col { background: var(--surface); border: 1px solid var(--border); border-radius: 14px; padding: 20px 18px; }
.pp-col.paid { border-color: var(--accent); }
.pp-col h2 { font-size: 15px; margin: 0 0 4px; letter-spacing: 0.2px; }
.pp-col .who { color: var(--muted); font-size: 12.5px; margin: 0 0 14px; }
.pp-list { list-style: none; margin: 0; padding: 0; }
.pp-list li { display: flex; gap: 10px; padding: 9px 0; border-top: 1px solid var(--border); }
.pp-list li:first-child { border-top: 0; padding-top: 0; }
.pp-list .mark { flex: 0 0 auto; font-weight: 500; line-height: 1.5; }
.pp-col.free .mark { color: var(--up); }
.pp-col.paid .mark { color: var(--accent); }
.pp-list b { display: block; font-size: 13.5px; font-weight: 650; }
.pp-list span { display: block; color: var(--muted); font-size: 12.5px; line-height: 1.5; margin-top: 2px; }
.pp-foot { margin-top: 26px; text-align: center; }
.pp-foot a { color: var(--accent); text-decoration: none; font-size: 14px; }
.pp-risk {
  max-width: 700px; margin: 26px auto 0; text-align: center; color: var(--muted);
  font-size: 12px; line-height: 1.65;
}
<?php
View::head(
    'Pricing & premium plans',
    'Unlock every tracked coin, instant alerts and the full trade plan on each signal. '
    . 'Monthly, yearly and lifetime plans, paid in crypto. Free account available.',
    ['style' => ob_get_clean()]
);
?>
<?php View::topbar(''); ?>

<div class="pp-wrap">
  <div class="pp-head">
    <h1>Premium plans</h1>
    <p>Every signal on this site is generated automatically and checked against the price action
      that follows it &mdash; wins and losses both. Premium opens the whole coin list, the levels on
      calls that are still running, and alerts the moment one fires.</p>
  </div>

  <?php if ($plans): ?>
    <div class="pp-plans">
      <?php foreach ($plans as $p): ?>
        <?php
        $days = max(1, (int)$p['days']);
        $isBest = $best !== null && (int)$best['plan']['id'] === (int)$p['id'] && count($plans) > 1;
        // days = 0 in the table means no end date. Saying "0 days" about a
        // lifetime plan is the kind of detail that makes a buyer close the tab.
        $lifetime = (int)$p['days'] === 0;
        ?>
        <div class="pp-plan<?= $isBest ? ' best' : '' ?>">
          <?php if ($isBest): ?><span class="pp-tag">Best value</span><?php endif; ?>
          <div class="nm"><?= sma_e((string)$p['name']) ?></div>
          <div class="pr">$<?= sma_e(number_format((float)$p['price_usd'], 2)) ?></div>
          <div class="dy"><?= $lifetime ? 'one payment, no end date' : (int)$p['days'] . ' days' ?></div>
          <?php if (!$lifetime): ?>
            <div class="per">$<?= sma_e(number_format((float)$p['price_usd'] / $days, 2)) ?> per day</div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <p class="pp-note">Premium plans are not on sale on this site at the moment. A free account
      still gets you everything in the first column below.</p>
  <?php endif; ?>

  <div class="pp-cta">
    <a class="pp-btn primary" href="account.php?tab=register">Create a free account</a>
    <a class="pp-btn ghost" href="account.php?tab=login">Log in</a>
  </div>
  <p class="pp-note">
    <?php // Said before the button rather than after the payment. ?>
    No card needed for the free account. Premium is paid in crypto and does not renew on its own
    &mdash; when a plan runs out the account drops back to free, and nothing is charged again unless
    you buy another one.
  </p>

  <div class="pp-cols">
    <div class="pp-col free">
      <h2>Free account</h2>
      <p class="who">Everything below, for as long as you like, without paying.</p>
      <ul class="pp-list">
        <li><span class="mark">&check;</span><div><b>Live signals on the free coin list</b>
          <span>BUY / SELL calls with confidence, score and grade, refreshed automatically.</span></div></li>
        <li><span class="mark">&check;</span><div><b>The full verified track record</b>
          <span>Wins and losses both, measured against real price action after costs.</span></div></li>
        <?php foreach ($freeRows as $r): ?>
          <li><span class="mark">&check;</span><div><b><?= $r['label'] /* trusted: from Gate::FEATURES */ ?></b>
            <?php if ($r['sell'] !== ''): ?><span><?= sma_e($r['sell']) ?></span><?php endif; ?></div></li>
        <?php endforeach; ?>
      </ul>
    </div>

    <div class="pp-col paid">
      <h2>Premium</h2>
      <p class="who">Everything in the free account, plus:</p>
      <ul class="pp-list">
        <li><span class="mark">&plus;</span><div><b>Every coin on the site</b>
          <span>The free list is a sample; premium opens the whole watchlist and every timeframe.</span></div></li>
        <?php foreach ($paidRows as $r): ?>
          <li><span class="mark">&plus;</span><div><b><?= $r['label'] /* trusted: from Gate::FEATURES */ ?></b>
            <?php if ($r['sell'] !== ''): ?><span><?= sma_e($r['sell']) ?></span><?php endif; ?></div></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>

  <p class="pp-foot">
    <a href="performance.php">Read the verified track record first &rarr;</a>
  </p>

  <p class="pp-risk">
    <?= sma_e(Database::setting('site_notice',
        'Educational use only. Not financial advice.')) ?>
    Past results are not a promise about future ones, and every signal on this site can lose.
  </p>
</div>

<?php View::footer(); ?>
