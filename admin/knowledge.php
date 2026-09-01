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

    if ($act === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $weight = max(0.0, min(5.0, (float)($_POST['weight'] ?? 1)));
        $desc = trim($_POST['description'] ?? '');
        $enabled = isset($_POST['enabled']) ? 1 : 0;
        $stmt = $pdo->prepare('UPDATE ta_knowledge SET weight = ?, enabled = ?, description = ? WHERE id = ?');
        $stmt->execute([$weight, $enabled, $desc, $id]);
        flash('Rule updated.');
        header('Location: knowledge.php');
        exit;
    }

    if ($act === 'autotune') {
        // Every other caller (cron.php, api.php, the backtest handler below)
        // reads the admin's own configured floor before tuning anything; this
        // one called with no argument and silently tuned on the hard-coded
        // default of 10 regardless of what was configured here.
        $minS = max(5, (int)Database::setting('autotune_min_samples', '10'));
        $n = \SignalMasterAi\Outcomes::autoTuneWeights($minS);
        flash($n > 0
            ? "Auto-tune complete: $n rule weights adjusted from the verified track record."
            : "Auto-tune ran, but no rule has enough evaluated signals yet ($minS+ aligned outcomes needed).");
        header('Location: knowledge.php');
        exit;
    }

    if ($act === 'backtest') {
        $tf = in_array($_POST['tf'] ?? '', $config['market']['intervals'], true) ? $_POST['tf'] : '1h';
        $symPick = (string)($_POST['symbol'] ?? 'ALL');
        $apply = isset($_POST['apply']);
        $minS = max(5, (int)Database::setting('autotune_min_samples', '10'));
        if ($symPick === 'ALL') {
            $symbols = array_column(
                $pdo->query('SELECT symbol FROM symbols WHERE enabled = 1 ORDER BY symbol')->fetchAll(), 'symbol');
        } else {
            $symbols = [strtoupper(preg_replace('/[^A-Z0-9]/i', '', $symPick) ?? '')];
        }
        $rep = \SignalMasterAi\Backtest::runMany($symbols, $tf, $apply, $minS);
        flash(sprintf('Backtest done: %d simulated signals across %d coin(s) on %s — win rate %s, %s.',
            $rep['signals'], $rep['symbols'], $tf,
            $rep['winrate'] !== null ? $rep['winrate'] . '%' : 'n/a',
            $apply ? $rep['weights_changed'] . ' weights tuned + ' . $rep['tf_multipliers_set'] . " $tf multipliers learned"
                   : 'calibration not applied (preview only)'));
        header('Location: knowledge.php#backtest');
        exit;
    }

    if ($act === 'save_tf_mult') {
        // Learned multipliers were visible only as a count and clearable only
        // wholesale. An admin who could see that one rule was being muted on
        // 15m had no way to correct just that cell.
        $all = json_decode(Database::setting('tf_rule_mult', '{}'), true) ?: [];
        foreach ((array)($_POST['m'] ?? []) as $tfKey => $rules) {
            if (!is_array($rules)) {
                continue;
            }
            foreach ($rules as $ruleKey => $raw) {
                $raw = trim((string)$raw);
                if ($raw === '' || !is_numeric($raw) || abs((float)$raw - 1.0) < 0.005) {
                    // Blank or 1.0 means "no adjustment" - drop the entry
                    // rather than storing a no-op the tuner has to reconsider.
                    unset($all[$tfKey][$ruleKey]);
                    continue;
                }
                $all[$tfKey][$ruleKey] = round(max(0.1, min(3.0, (float)$raw)), 2);
            }
            if (empty($all[$tfKey])) {
                unset($all[$tfKey]);
            }
        }
        Database::setSetting('tf_rule_mult', json_encode($all));
        flash('Per-timeframe multipliers saved.');
        header('Location: knowledge.php#tfmult');
        exit;
    }

    if ($act === 'reset_tf_mult') {
        Database::setSetting('tf_rule_mult', '{}');
        flash('Per-timeframe multipliers cleared - rules use their global weights everywhere again.');
        header('Location: knowledge.php');
        exit;
    }

    if ($act === 'add_custom') {
        // Build a rule from declarative clauses. The condition text is parsed
        // and normalised, never evaluated as code.
        $name = trim((string)($_POST['name'] ?? ''));
        $clauses = \SignalMasterAi\CustomRules::parse((string)($_POST['expr'] ?? ''));
        $cat = (string)($_POST['category'] ?? 'other');
        $validCats = array_column($pdo->query('SELECT category FROM ta_categories')->fetchAll(), 'category');
        if ($name === '' || !$clauses) {
            flash('Give the rule a name and at least one valid condition (for example: rsi < 25).', 'warn');
        } elseif (!in_array($cat, $validCats, true)) {
            flash('Unknown category.', 'warn');
        } else {
            $key = 'custom_' . substr(preg_replace('/[^a-z0-9]+/', '_', strtolower($name)) ?? 'rule', 0, 40)
                 . '_' . substr(bin2hex(random_bytes(3)), 0, 4);
            $weight = max(0.0, min(5.0, (float)($_POST['weight'] ?? 1.0)));
            $dir = ($_POST['direction'] ?? 'bullish') === 'bearish' ? 'bearish' : 'bullish';
            $expr = json_encode(['direction' => $dir, 'clauses' => $clauses]);
            $desc = 'Custom rule created in the admin panel. Fires '
                  . ($dir === 'bullish' ? 'a bullish' : 'a bearish') . ' vote when: '
                  . \SignalMasterAi\CustomRules::describe($clauses) . '.';
            $pdo->prepare('INSERT INTO ta_knowledge (rule_key, name, category, description, weight, weight_base, expr, enabled)
                           VALUES (?,?,?,?,?,?,?,1)')
                ->execute([$key, mb_substr($name, 0, 128), $cat, $desc, $weight, $weight, $expr]);
            flash('Custom rule added: ' . \SignalMasterAi\CustomRules::describe($clauses));
        }
        header('Location: knowledge.php#custom');
        exit;
    }

    if ($act === 'delete_custom') {
        // Only admin-authored rules can be deleted; built-ins are re-seeded.
        $pdo->prepare("DELETE FROM ta_knowledge WHERE id = ? AND expr != ''")
            ->execute([(int)($_POST['id'] ?? 0)]);
        flash('Custom rule deleted.');
        header('Location: knowledge.php#custom');
        exit;
    }

    if ($act === 'release_rule') {
        $key = (string)($_POST['rule_key'] ?? '');
        flash(\SignalMasterAi\Outcomes::releaseRule($key)
            ? "$key is back in the vote."
            : 'That rule is not benched.');
        header('Location: knowledge.php');
        exit;
    }

    if ($act === 'bulk') {
        foreach ((array)($_POST['w'] ?? []) as $id => $w) {
            $pdo->prepare('UPDATE ta_knowledge SET weight = ?, enabled = ? WHERE id = ?')->execute([
                max(0.0, min(5.0, (float)$w)),
                isset($_POST['en'][$id]) ? 1 : 0,
                (int)$id,
            ]);
        }
        flash('Knowledge base saved.');
        header('Location: knowledge.php');
        exit;
    }
}

$rules = $pdo->query('SELECT * FROM ta_knowledge ORDER BY category, name')->fetchAll();
$ruleStats = \SignalMasterAi\Outcomes::ruleStats();
$benched = \SignalMasterAi\Outcomes::quarantined();
// quarantined() just formats whatever is still on file - it has no opinion
// on whether the feature is switched on. SignalEngine::applyQuarantine()
// does, and returns immediately without excluding anything when
// quarantine_enabled is off - so a rule bailed out here can be voting
// normally again while this list still calls it benched.
$quarantineOn = Database::setting('quarantine_enabled', '1') === '1';
$ruleNames = [];
foreach ($rules as $r) {
    $ruleNames[$r['rule_key']] = $r['name'];
}
$csrf = Auth::csrfToken();
$editId = (int)($_GET['edit'] ?? 0);

admin_header('Rules & weights', 'knowledge',
    'The technical-analysis knowledge the engine runs on. Each rule casts a bullish or bearish vote '
    . 'scaled by its weight (0 = ignore, 5 = dominant); disable one to take it out of analysis '
    . 'entirely. Thresholds live in <a href="settings.php#signals">Settings</a>.');
show_flash();
?>
<?php // Rules the engine took out of the vote by itself. A count on the
      // dashboard says something happened; this says what, why, and until
      // when - and lets the decision be overruled, which is the difference
      // between an automatic system and an unaccountable one. ?>
<?php if ($benched): ?>
<?php admin_panel('Benched by the engine (' . count($benched) . ')',
    ($quarantineOn
        ? 'These rules stopped paying, so the learning pass took them out of the vote entirely rather '
          . 'than just trimming their weight. Each is released when its probation ends and has to earn '
          . 'its place back on fresh evidence. Judged on expectancy, never on win rate.'
        : 'Quarantine is switched off under <a href="settings.php#signals">Settings &rsaquo; Signals</a>, '
          . 'so none of the rules below are actually excluded right now - they are voting normally. This '
          . 'is what is still on file from when it was last on; "Release now" just clears the record early.'),
    '', $quarantineOn ? 'warn' : ''); ?>
  <div class="tbl-scroll">
  <table class="grid">
    <tr class="head"><th>Rule</th><th>Why</th><th>Benched</th><th>Released</th><th></th></tr>
    <?php foreach ($benched as $key => $b): ?>
    <tr>
      <td><strong><?= sma_e($ruleNames[$key] ?? $key) ?></strong>
        <div class="hint"><code><?= sma_e($key) ?></code></div></td>
      <td><?= $b['why'] !== '' ? sma_e($b['why'])
            : '<span class="hint">benched before the reason was recorded</span>' ?></td>
      <td class="muted"><?= $b['at'] > 0 ? sma_e(gmdate('M j, H:i', $b['at'])) : '&mdash;' ?></td>
      <td class="muted"><?= $b['until'] > time()
            ? 'in ' . sma_e(\SignalMasterAi\View::span($b['until'] - time()))
            : 'at the next learning pass' ?></td>
      <td>
        <form class="inline-form" method="post" action="knowledge.php"
              onsubmit="return confirm('Put <?= sma_e($key) ?> back in the vote now? It was benched for losing money.')">
          <input type="hidden" name="csrf" value="<?= $csrf ?>">
          <input type="hidden" name="act" value="release_rule">
          <input type="hidden" name="rule_key" value="<?= sma_e($key) ?>">
          <button class="btn small gray" type="submit">Release now</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  </div>
<?php admin_panel_end(); ?>
<?php endif; ?>

<?php if ($editId): foreach ($rules as $r): if ((int)$r['id'] === $editId): ?>
<?php admin_panel('Edit: ' . $r['name'],
    'One rule. The weight is how loudly it votes; the description is what it is looking for.'); ?>
  <form method="post" action="knowledge.php">
    <input type="hidden" name="csrf" value="<?= $csrf ?>">
    <input type="hidden" name="act" value="save">
    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
    <label>Description (the "why" shown to visitors when this rule fires)</label>
    <textarea aria-label="Description (the &quot;why&quot; shown to visitors when this rule fires)" name="description"><?= sma_e($r['description']) ?></textarea>
    <div class="row2">
      <div>
        <label>Weight (0 - 5)</label>
        <input aria-label="Weight (0 - 5)" type="number" name="weight" step="0.1" min="0" max="5" value="<?= sma_e((string)$r['weight']) ?>">
      </div>
      <div>
        <label>Enabled</label>
        <input aria-label="Enabled" type="checkbox" name="enabled" <?= $r['enabled'] ? 'checked' : '' ?> style="width:auto">
      </div>
    </div>
    <p style="margin-top:14px">
      <button class="btn" type="submit">Save rule</button>
      <a class="btn gray" href="knowledge.php">Cancel</a>
    </p>
  </form>
<?php admin_panel_end(); ?>
<?php endif; endforeach; endif; ?>

<?php admin_panel('The rule set',
    'Every rule the engine votes with. Hit rate is measured from real settled outcomes, so a rule '
    . 'with "no data yet" has simply not fired often enough to judge - not that it is broken.'); ?>
<form method="post" action="knowledge.php">
  <input type="hidden" name="csrf" value="<?= $csrf ?>">
  <input type="hidden" name="act" value="bulk">
  <div class="tbl-scroll">
  <table class="grid">
    <tr class="head"><th>Rule</th><th>Category</th><th style="width:95px">Hit rate</th><th style="width:90px">Weight</th><th style="width:70px">On</th><th style="width:60px"></th></tr>
    <?php foreach ($rules as $r): ?>
    <tr>
      <td>
        <strong><?= sma_e($r['name']) ?></strong>
        <div class="hint" style="margin-top:2px"><?= sma_e(mb_strimwidth($r['description'], 0, 110, '…')) ?></div>
      </td>
      <td><span class="badge off"><?= sma_e($r['category']) ?></span></td>
      <td>
        <?php $st = $ruleStats[$r['rule_key']] ?? null; ?>
        <?php if ($st && $st['fired'] > 0): $wr = round($st['wins'] / $st['fired'] * 100); ?>
          <span style="color:<?= $wr >= 55 ? 'var(--up)' : ($wr < 45 ? 'var(--down)' : 'var(--warn)') ?>;font-weight:700"><?= $wr ?>%</span>
          <span class="hint" style="display:inline"><?= $st['fired'] < 10 ? '(' . (int)$st['fired'] . '× — low data)' : '(' . (int)$st['fired'] . '×)' ?></span>
        <?php else: ?><span class="hint" style="display:inline">no data yet</span><?php endif; ?>
      </td>
      <?php // NAMED FOR SOMEBODY WHO CANNOT SEE THE COLUMN HEADING.
            // A sighted operator reads "Weight" at the top of the column and the
            // rule name at the left of the row, and the cell is the intersection
            // of the two. A screen reader announces neither: it walks 69 spin
            // buttons and 69 checkboxes and says "spin button, 1.0" every time.
            // The heading and the row label are what the control is called, so
            // they are what it is given. ?>
      <td><input class="inline" type="number" step="0.1" min="0" max="5" name="w[<?= (int)$r['id'] ?>]"
                 aria-label="Weight for <?= sma_e($r['name']) ?>"
                 value="<?= sma_e((string)$r['weight']) ?>"></td>
      <td style="text-align:center"><input type="checkbox" name="en[<?= (int)$r['id'] ?>]"
                 aria-label="Enable <?= sma_e($r['name']) ?>" <?= $r['enabled'] ? 'checked' : '' ?>></td>
      <td><a class="btn small gray" href="knowledge.php?edit=<?= (int)$r['id'] ?>">Edit</a></td>
    </tr>
    <?php endforeach; ?>
  </table>
  </div>
  <p style="margin-top:14px"><button class="btn" type="submit">Save all weights</button></p>
</form>

<form method="post" action="knowledge.php" style="margin-top:16px"
      onsubmit="return confirm('Adjust rule weights based on their verified track record? Changes are mild (clamped 0.2-2.5) and only apply to rules with 10+ evaluated outcomes.')">
  <input type="hidden" name="csrf" value="<?= $csrf ?>">
  <input type="hidden" name="act" value="autotune">
  <button class="btn gray" type="submit">Auto-tune weights from track record</button>
  <p class="hint" style="margin-top:6px">Each rule's hit rate is measured from real signal outcomes
    (TP1-before-stop). Auto-tune nudges winners up and losers down; you can always re-edit manually.
    <?= \SignalMasterAi\Database::setting('autotune_enabled', '1') === '1'
        ? 'This also runs <strong>automatically once a day</strong> (disable in Settings &rsaquo; Signal engine).'
        : 'Automatic daily tuning is currently <strong>off</strong> (enable in Settings &rsaquo; Signal engine).' ?></p>
</form>
<?php admin_panel_end(); ?>

<?php admin_panel('Build your own rule',
    'Write one condition per line; all of them must hold for the rule to vote. Conditions are '
    . 'parsed into comparisons and never executed as code, so adding an idea does not mean '
    . 'editing PHP.', 'custom'); ?>
  <form method="post" action="knowledge.php">
    <input type="hidden" name="csrf" value="<?= $csrf ?>">
    <input type="hidden" name="act" value="add_custom">
    <div class="row2">
      <div><label>Rule name</label>
        <input aria-label="Rule name" type="text" name="name" maxlength="120" placeholder="Oversold under VWAP" required></div>
      <div><label>Category</label>
        <select aria-label="Category" name="category">
          <?php foreach ($pdo->query('SELECT category, label FROM ta_categories ORDER BY label') as $c): ?>
            <option value="<?= sma_e($c['category']) ?>"><?= sma_e($c['label']) ?></option>
          <?php endforeach; ?>
        </select></div>
    </div>
    <div class="row2">
      <div><label>Direction</label>
        <select aria-label="Direction" name="direction">
          <option value="bullish">Bullish vote</option>
          <option value="bearish">Bearish vote</option>
        </select></div>
      <div><label>Weight (0 - 5)</label>
        <input aria-label="Weight (0 - 5)" type="number" step="0.1" min="0" max="5" name="weight" value="1.0"></div>
    </div>
    <label>Conditions (one per line)</label>
    <textarea aria-label="Conditions (one per line)" name="expr" rows="4" placeholder="rsi &lt; 25&#10;price &lt; vwap&#10;adx &gt;= 20"></textarea>
    <p class="hint" style="margin-top:6px">
      <strong>Fields:</strong>
      <?php foreach (\SignalMasterAi\CustomRules::FIELDS as $f => $lbl): ?><code><?= sma_e($f) ?></code> <?php endforeach; ?><br>
      <strong>Operators:</strong> <code>&lt;</code> <code>&lt;=</code> <code>&gt;</code> <code>&gt;=</code>
      <code>crosses_above</code> <code>crosses_below</code>. The right-hand side can be a number or
      another field.
    </p>
    <p style="margin-top:12px"><button class="btn" type="submit">Add rule</button></p>
  </form>

  <?php $customRules = array_filter($rules, fn($r) => (string)($r['expr'] ?? '') !== ''); ?>
  <?php if ($customRules): ?>
    <h3 style="font-size:13px;margin:18px 0 6px">Your custom rules</h3>
    <div class="tbl-scroll">
    <table class="grid">
      <tr class="head"><th>Rule</th><th style="width:110px">Category</th><th style="width:80px">Weight</th><th style="width:80px"></th></tr>
      <?php foreach ($customRules as $r): $spec = json_decode((string)$r['expr'], true) ?: []; ?>
      <tr>
        <td><strong><?= sma_e($r['name']) ?></strong>
          <div class="hint"><?= sma_e(($spec['direction'] ?? 'bullish') === 'bearish' ? 'Bearish when: ' : 'Bullish when: ')
            . \SignalMasterAi\CustomRules::describe((array)($spec['clauses'] ?? [])) ?></div></td>
        <td><span class="badge off"><?= sma_e($r['category']) ?></span></td>
        <td><?= sma_e((string)$r['weight']) ?></td>
        <td>
          <form method="post" action="knowledge.php" onsubmit="return confirm('Delete this custom rule?')">
            <input type="hidden" name="csrf" value="<?= $csrf ?>">
            <input type="hidden" name="act" value="delete_custom">
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <button class="btn small gray" type="submit">Delete</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
    </div>
  <?php endif; ?>
<?php admin_panel_end(); ?>

<?php admin_panel('Backtest & calibrate',
    'Replays the candle history stored on this server through the engine - chart rules only, '
    . 'closed candles, the same stop and target walker as live - and measures what each rule '
    . 'actually did.', 'backtest'); ?>
  <p class="hint">Measures every rule's real hit rate and
    average R. Tick "apply" to calibrate global weights and learn per-timeframe multipliers from the
    result instantly - no waiting weeks for live outcomes. Past performance is educational, not a promise.</p>
  <form method="post" action="knowledge.php">
    <input type="hidden" name="csrf" value="<?= $csrf ?>">
    <input type="hidden" name="act" value="backtest">
    <div class="row2">
      <div><label>Coins</label>
        <select aria-label="Coins" name="symbol">
          <option value="ALL">All enabled coins</option>
          <?php foreach ($pdo->query('SELECT symbol, label FROM symbols WHERE enabled = 1 ORDER BY symbol') as $s): ?>
            <option value="<?= sma_e($s['symbol']) ?>"><?= sma_e($s['label']) ?> (<?= sma_e($s['symbol']) ?>)</option>
          <?php endforeach; ?>
        </select></div>
      <div><label>Timeframe</label>
        <select aria-label="Timeframe" name="tf">
          <?php foreach ($config['market']['intervals'] as $iv): ?>
            <option value="<?= sma_e($iv) ?>" <?= $iv === '1h' ? 'selected' : '' ?>><?= sma_e($iv) ?></option>
          <?php endforeach; ?>
        </select></div>
    </div>
    <label class="chk"><input type="checkbox" name="apply" checked>
      Apply calibration (tune global weights + learn per-timeframe multipliers)</label>
    <p style="margin-top:12px"><button class="btn" type="submit"
        onclick="this.textContent='Backtesting… this can take a minute';this.form.submit();this.disabled=true">▶ Run backtest</button></p>
  </form>

  <?php
  $btRep = json_decode(Database::setting('backtest_report', ''), true);
  if (is_array($btRep) && !empty($btRep['at'])):
      $btRules = $btRep['rules'] ?? [];
      uasort($btRules, fn($a, $b) => $b['fired'] <=> $a['fired']);
      $btRules = array_slice($btRules, 0, 15, true);
      $names = [];
      foreach ($rules as $r) { $names[$r['rule_key']] = $r['name']; }
  ?>
  <h3 style="font-size:13px;margin:18px 0 6px">Last backtest &mdash; <?= sma_e((string)$btRep['tf']) ?>, <?= (int)$btRep['symbols'] ?> coin(s),
      <?= date('M j H:i', (int)$btRep['at']) ?></h3>
  <p class="hint" style="margin-bottom:8px">
    <?= (int)$btRep['signals'] ?> simulated signals ·
    <?= (int)$btRep['confirmed'] ?> confirmed / <?= (int)$btRep['invalid'] ?> stopped / <?= (int)$btRep['expired'] ?> expired ·
    win rate <?= $btRep['winrate'] !== null ? sma_e((string)$btRep['winrate']) . '%' : 'n/a' ?>
    <?= !empty($btRep['applied']) ? ' · applied: ' . (int)$btRep['weights_changed'] . ' weights, ' . (int)$btRep['tf_multipliers_set'] . ' multipliers' : ' · preview only' ?>
  </p>
  <div class="tbl-scroll">
  <table class="grid">
    <tr class="head"><th>Rule (top by sample size)</th><th style="width:90px">Fired</th><th style="width:100px">Win rate</th><th style="width:90px">Avg R</th></tr>
    <?php foreach ($btRules as $key => $s): $wr = $s['fired'] > 0 ? round($s['wins'] / $s['fired'] * 100) : 0;
          $avgR = $s['fired'] > 0 ? round($s['r_sum'] / $s['fired'], 2) : 0; ?>
    <tr>
      <td><?= sma_e($names[$key] ?? $key) ?></td>
      <td><?= (int)$s['fired'] ?>×</td>
      <td style="color:<?= $wr >= 55 ? 'var(--up)' : ($wr < 45 ? 'var(--down)' : 'var(--warn)') ?>;font-weight:700"><?= $wr ?>%</td>
      <td style="color:<?= $avgR > 0 ? 'var(--up)' : ($avgR < 0 ? 'var(--down)' : 'var(--muted)') ?>;font-weight:700"><?= $avgR > 0 ? '+' : '' ?><?= $avgR ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  </div>
  <?php endif; ?>
<?php admin_panel_end(); ?>

<?php $tfm = json_decode(Database::setting('tf_rule_mult', '{}'), true) ?: []; ?>
<?php admin_panel('Per-timeframe multipliers',
    'The same rule can be strong on the daily and noise on 15m. These are learned from verified '
    . 'outcomes and applied on top of the global weight.', 'tfmult'); ?>
  <p class="hint">Blank or 1.00 means no adjustment. Each cell is editable on its own &mdash; these
    used to be visible only as a count and clearable only in bulk, so noticing that one rule was
    being muted on one timeframe gave you no way to correct just that cell.</p>

  <?php if (!$tfm): ?>
    <p class="hint">Nothing learned yet. Run a backtest with <em>apply</em> ticked, or let the daily
      tuner accumulate verified outcomes.</p>
  <?php else: ?>
    <?php
    $tfKeys = array_keys($tfm);
    sort($tfKeys);
    // Only rules that actually carry a multiplier somewhere - the full 54 x 9
    // grid would be unreadable and almost entirely empty.
    $usedRules = [];
    foreach ($tfm as $m) {
        foreach (array_keys($m) as $rk) {
            $usedRules[$rk] = true;
        }
    }
    $names = [];
    foreach ($rules as $r) {
        $names[$r['rule_key']] = $r['name'];
    }
    ?>
    <form method="post" action="knowledge.php">
      <input type="hidden" name="csrf" value="<?= $csrf ?>">
      <input type="hidden" name="act" value="save_tf_mult">
      <div class="tbl-scroll">
        <table class="grid">
          <tr>
            <th>Rule</th>
            <?php foreach ($tfKeys as $tfK): ?><th style="width:88px"><?= sma_e($tfK) ?></th><?php endforeach; ?>
          </tr>
          <?php foreach (array_keys($usedRules) as $rk): ?>
          <tr>
            <td><strong><?= sma_e($names[$rk] ?? $rk) ?></strong>
                <div class="hint"><?= sma_e($rk) ?></div></td>
            <?php foreach ($tfKeys as $tfK): $v = $tfm[$tfK][$rk] ?? null; ?>
              <td>
                <input class="inline" type="number" step="0.05" min="0.1" max="3"
                       name="m[<?= sma_e($tfK) ?>][<?= sma_e($rk) ?>]"
                       value="<?= $v !== null ? sma_e((string)$v) : '' ?>" placeholder="1.00"
                       style="<?= $v !== null && $v < 0.9 ? 'color:var(--down)' : ($v !== null && $v > 1.1 ? 'color:var(--up)' : '') ?>">
              </td>
            <?php endforeach; ?>
          </tr>
          <?php endforeach; ?>
        </table>
      </div>
      <p style="margin-top:14px"><button class="btn" type="submit">Save multipliers</button></p>
    </form>

    <form method="post" action="knowledge.php" style="margin-top:8px"
          onsubmit="return confirm('Clear all learned per-timeframe multipliers?')">
      <input type="hidden" name="csrf" value="<?= $csrf ?>">
      <input type="hidden" name="act" value="reset_tf_mult">
      <button class="btn small gray" type="submit">Reset all multipliers</button>
    </form>
  <?php endif; ?>
<?php admin_panel_end(); ?>
<?php admin_footer(); ?>
