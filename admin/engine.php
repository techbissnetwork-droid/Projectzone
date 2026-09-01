<?php
declare(strict_types=1);

/**
 * Engine lab: everything about how the engine decides, in one place.
 *
 * Tuning used to be a matter of editing a number and hoping. This page gives
 * the admin the evidence instead: the correlation caps that stop five
 * oscillators counting five times, a champion/challenger comparison that
 * answers "would this change actually help" on real history, the stop/target
 * optimiser, the learned model's coefficients, and the health of the tuner.
 */

$config = require __DIR__ . '/_admin.php';

use SignalMasterAi\Auth;
use SignalMasterAi\Backtest;
use SignalMasterAi\Database;
use SignalMasterAi\LearnedModel;
use SignalMasterAi\LevelSearch;
use SignalMasterAi\LongJob;
use SignalMasterAi\Outcomes;
use SignalMasterAi\PresetTest;
use SignalMasterAi\SignalEngine;

Auth::requireLogin();
$pdo = Database::pdo();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf();
    $act = $_POST['act'] ?? '';

    // ---- the preset accuracy test -------------------------------------
    //
    // Start / advance / cancel, rather than "run". A full comparison is about
    // twelve minutes of replaying, which no shared host will do inside one
    // request, so the work is queued and walked a few steps at a time by the
    // cron - and by the Run a step button, for an operator who would rather
    // watch it move than wait for the next tick.
    if ($act === 'preset_test') {
        $r = LongJob::start('preset_accuracy', [
            'max_symbols' => max(1, min(PresetTest::MAX_SYMBOLS, (int)($_POST['max_symbols'] ?? 6))),
        ]);
        flash($r['ok']
            ? 'Accuracy test queued: ' . (int)$r['total'] . ' replays. It advances on every cron '
              . 'run, or press "Run a step" to push it along now.'
            : $r['error'], $r['ok'] ? 'ok' : 'warn');
        header('Location: engine.php#presets');
        exit;
    }
    // Step and cancel act on WHICHEVER measurement is live - the preset test
    // or the level search - because LongJob does not care which kind it is
    // walking. Named for the job rather than for the first thing that used it.
    // Back to the panel the job belongs to. Both of these act on whichever
    // measurement is live, so a fixed redirect would land an operator running
    // the level search on the preset card, wondering where their table went.
    $jobAnchor = static function (): string {
        $j = LongJob::state();
        return ($j['kind'] ?? '') === 'level_search' ? '#levels2' : '#presets';
    };
    if ($act === 'job_step') {
        $anchor = $jobAnchor();
        // Bounded by what THIS request can survive, not by what the job wants.
        $limit = (int)ini_get('max_execution_time');
        $slice = $limit > 0 ? max(5, (int)($limit * 0.7)) : 20;
        $st = LongJob::stepLocked((float)$slice);
        if (!empty($st['locked'])) {
            flash('Cron is stepping this job right now - give it a few seconds and try again.', 'warn');
        } else {
            flash($st['done']
                ? 'Finished - ' . $st['ran'] . ' replay(s) in this step.'
                : $st['ran'] . ' replay(s) in ' . $st['seconds'] . 's; ' . $st['left'] . ' left.');
        }
        header('Location: engine.php' . $anchor);
        exit;
    }
    // ---- the stop / target / exit search --------------------------------
    if ($act === 'level_search') {
        $r = LongJob::start('level_search', [
            'tf' => in_array($_POST['tf'] ?? '', $config['market']['intervals'], true)
                ? (string)$_POST['tf'] : '1h',
            'max_symbols' => max(1, min(12, (int)($_POST['max_symbols'] ?? 4))),
            'exits' => isset($_POST['exits']) ? '1' : '0',
        ]);
        flash($r['ok']
            ? 'Search queued: ' . (int)$r['total'] . ' replays. It advances on every cron run.'
            : $r['error'], $r['ok'] ? 'ok' : 'warn');
        header('Location: engine.php#levels2');
        exit;
    }
    if ($act === 'level_apply') {
        $job = LongJob::state();
        $res = ($job && ($job['kind'] ?? '') === 'level_search') ? ($job['results'] ?? []) : [];
        $idx = (int)($_POST['idx'] ?? -1);
        $rows = LevelSearch::ranked($res);
        $pick = null;
        foreach ($rows as $row) {
            if ((int)$row['idx'] === $idx) {
                $pick = $row;
                break;
            }
        }
        if ($pick === null) {
            flash('That candidate is not in the current search result.', 'warn');
        } else {
            $before = json_encode(\SignalMasterAi\Backtest::levelsSetting());
            $pick['tf'] = (string)($res['tf'] ?? '1h');
            $changed = LevelSearch::apply($pick);
            \SignalMasterAi\Audit::log('setting.change', 'tf_levels', (string)$before,
                json_encode(\SignalMasterAi\Backtest::levelsSetting()));
            flash('Applied: ' . implode(', ', $changed) . '. Every change is in the '
                . 'change log, so putting it back is reading a list rather than remembering one.');
        }
        header('Location: engine.php#levels2');
        exit;
    }

    if ($act === 'job_cancel') {
        $anchor = $jobAnchor();
        LongJob::cancel();
        flash('Cancelled. Nothing was written to your live settings by it.');
        header('Location: engine.php' . $anchor);
        exit;
    }

    if ($act === 'scoring') {
        Database::setSetting('scoring_mode', ($_POST['scoring_mode'] ?? 'category') === 'sum' ? 'sum' : 'category');
        Database::setSetting('cat_buy_threshold', (string)max(0.1, (float)($_POST['cat_buy_threshold'] ?? 3)));
        Database::setSetting('cat_sell_threshold', (string)min(-0.1, (float)($_POST['cat_sell_threshold'] ?? -3)));
        Database::setSetting('min_aligned_categories', (string)max(1, min(9, (int)($_POST['min_aligned_categories'] ?? 2))));
        Database::setSetting('chop_gate_enabled', isset($_POST['chop_gate_enabled']) ? '1' : '0');
        Database::setSetting('chop_limit', (string)max(0.0, min(100.0, (float)($_POST['chop_limit'] ?? 61.8))));
        Database::setSetting('chop_gate_a_plus_exempt', isset($_POST['chop_gate_a_plus_exempt']) ? '1' : '0');
        Database::setSetting('htf_engine_enabled', isset($_POST['htf_engine_enabled']) ? '1' : '0');
        Database::setSetting('min_quote_volume', (string)max(0.0, (float)($_POST['min_quote_volume'] ?? 0)));
        Database::setSetting('round_trip_cost_pct', (string)max(0.0, min(5.0, (float)($_POST['round_trip_cost_pct'] ?? 0.1))));
        Database::setSetting('max_cost_r', (string)max(0.0, min(2.0, (float)($_POST['max_cost_r'] ?? 0.33))));
        Database::setSetting('target_min_reach', (string)max(0.0, min(1.0, (float)($_POST['target_min_reach'] ?? 0.35))));
        Database::setSetting('extension_gate_enabled', isset($_POST['extension_gate_enabled']) ? '1' : '0');
        Database::setSetting('extension_max_atr', (string)max(0.5, min(10.0, (float)($_POST['extension_max_atr'] ?? 2.5))));
        Database::setSetting('entry_zone_atr', (string)max(0.0, min(2.0, (float)($_POST['entry_zone_atr'] ?? 0.25))));
        Database::setSetting('record_spread', isset($_POST['record_spread']) ? '1' : '0');
        Database::setSetting('mtf_gate_enabled', isset($_POST['mtf_gate_enabled']) ? '1' : '0');
        Database::setSetting('mtf_gate_bias', (string)max(0.05, min(1.0, (float)($_POST['mtf_gate_bias'] ?? 0.35))));
        Database::setSetting('mtf_penalty', (string)max(0.0, min(1.5, (float)($_POST['mtf_penalty'] ?? 0.6))));
        Database::setSetting('mtf_gate_hard', (string)max(0.5, min(1.0, (float)($_POST['mtf_gate_hard'] ?? 0.85))));
        Database::setSetting('smc_enabled', isset($_POST['smc_enabled']) ? '1' : '0');
        Database::setSetting('smc_fresh_bars', (string)max(1, min(50, (int)($_POST['smc_fresh_bars'] ?? 6))));
        Database::setSetting('smc_near_atr', (string)max(0.05, min(3.0, (float)($_POST['smc_near_atr'] ?? 0.5))));
        Database::setSetting('regime_detect_enabled', isset($_POST['regime_detect_enabled']) ? '1' : '0');
        Database::setSetting('mtf_min_bias', (string)max(0.0, min(1.0, (float)($_POST['mtf_min_bias'] ?? 0.25))));
        Database::setSetting('mtf_reversal_min_n', (string)max(5, min(500, (int)($_POST['mtf_reversal_min_n'] ?? 25))));
        Database::setSetting('mtf_reversal_min_r', (string)max(0.0, min(2.0, (float)($_POST['mtf_reversal_min_r'] ?? 0.15))));
        flash('Scoring settings saved.');
        header('Location: engine.php');
        exit;
    }

    // The self-tuning loop saves from its own card rather than from the
    // scoring form: its controls belong next to the log that shows what it
    // has been doing, and a form that does not carry a field must never be
    // able to reset it.
    if ($act === 'adapt') {
        Database::setSetting('adapt_enabled', isset($_POST['adapt_enabled']) ? '1' : '0');
        Database::setSetting('adapt_min_live', (string)max(20, min(500, (int)($_POST['adapt_min_live'] ?? 40))));
        $adaptTf = (string)($_POST['adapt_tf'] ?? '1h');
        Database::setSetting('adapt_tf', in_array($adaptTf, $config['market']['intervals'], true) ? $adaptTf : '1h');
        flash('Self-tuning settings saved.');
        header('Location: engine.php#adapt');
        exit;
    }

    if ($act === 'categories') {
        $upd = $pdo->prepare('UPDATE ta_categories SET cap = ?, weight = ?, enabled = ? WHERE category = ?');
        foreach ((array)($_POST['cap'] ?? []) as $cat => $cap) {
            $upd->execute([
                max(0.1, min(20.0, (float)$cap)),
                max(0.0, min(3.0, (float)($_POST['cweight'][$cat] ?? 1))),
                isset($_POST['cen'][$cat]) ? 1 : 0,
                (string)$cat,
            ]);
        }
        flash('Evidence categories saved.');
        header('Location: engine.php#categories');
        exit;
    }

    if ($act === 'ab') {
        $tf = in_array($_POST['tf'] ?? '', $config['market']['intervals'], true) ? $_POST['tf'] : '1h';
        $symbols = array_column(
            $pdo->query('SELECT symbol FROM symbols WHERE enabled = 1 ORDER BY symbol LIMIT 12')->fetchAll(), 'symbol');
        $changes = [];
        foreach (['min_aligned_categories', 'chop_limit'] as $k) {
            $v = trim((string)($_POST['ch_' . $k] ?? ''));
            if ($v !== '') {
                $changes[$k] = $v;
            }
        }
        // The BUY threshold field always posts as ch_cat_buy_threshold, but
        // which setting is actually read at runtime depends on the live
        // scoring mode - SignalEngine::modeNow() consults cat_buy_threshold
        // under Category-capped and buy_threshold under Plain sum. Writing
        // the posted value to the wrong one is a no-op override: the
        // challenger run ends up identical to the champion and the
        // comparison always reports a tie. Route it to whichever key the
        // engine will actually consult, same as Presets::values() already
        // does when it writes both pairs.
        $chBuy = trim((string)($_POST['ch_cat_buy_threshold'] ?? ''));
        if ($chBuy !== '') {
            $changes[SignalEngine::scoringMode() === 'sum' ? 'buy_threshold' : 'cat_buy_threshold'] = $chBuy;
        }
        if (!$changes) {
            flash('Set at least one challenger value to compare.', 'warn');
        } else {
            $rep = Backtest::compare($symbols, $tf, $changes);
            flash(sprintf('Comparison done on %s: %s wins (expectancy %s vs %s).',
                $tf, $rep['winner'],
                $rep['challenger']['expectancy'] ?? 'n/a', $rep['champion']['expectancy'] ?? 'n/a'));
        }
        header('Location: engine.php#ab');
        exit;
    }

    // The stop/target search handler that stood here is gone with the form
    // that posted to it. Nothing posts act=levels any more - the search is
    // started from the Stop, target and exit search card, which queues it on
    // the runner instead of trying to finish twenty minutes of replaying
    // inside one web request. A second entry point to the same search is the
    // duplication this project keeps having to undo.

    if ($act === 'retrain') {
        $rep = LearnedModel::train();
        flash(!empty($rep['trained'])
            ? sprintf('Model retrained on %d signals — out-of-sample accuracy %.1f%% (base rate %.1f%%).',
                $rep['samples'], $rep['accuracy'] * 100, $rep['base_rate'] * 100)
            : sprintf('Not enough verified signals yet: %d of %d needed.', $rep['samples'] ?? 0, $rep['need'] ?? 120),
            !empty($rep['trained']) ? 'ok' : 'warn');
        header('Location: engine.php#model');
        exit;
    }
}

$cats = $pdo->query('SELECT * FROM ta_categories ORDER BY category')->fetchAll();
$catUse = [];
foreach ($pdo->query('SELECT category, COUNT(*) n FROM ta_knowledge WHERE enabled = 1 GROUP BY category') as $r) {
    $catUse[$r['category']] = (int)$r['n'];
}
$model = LearnedModel::status();
$topFeatures = LearnedModel::topFeatures(14);
$tune = json_decode(Database::setting('autotune_report', ''), true) ?: [];
$ab = json_decode(Database::setting('ab_report', ''), true) ?: [];
$lvl = json_decode(Database::setting('level_opt_report', ''), true) ?: [];
$tfLevels = json_decode(Database::setting('tf_levels', '{}'), true) ?: [];
$buckets = json_decode(Database::setting('time_buckets', ''), true) ?: [];
$calib = json_decode(Database::setting('confidence_calib', '{}'), true) ?: [];
// Real win rate per letter grade, and the evidence behind the A+ chop
// exemption - see Outcomes::buildGradeCalibration() / chopExemptionStats().
$gradeCalib = json_decode(Database::setting('grade_calib', '{}'), true) ?: [];
try {
    $chopEv = \SignalMasterAi\Outcomes::chopExemptionStats();
} catch (Throwable $e) {
    $chopEv = null;
}
$csrf = Auth::csrfToken();
$mode = SignalEngine::scoringMode();

admin_header('Engine lab', 'engine',
    'How the engine turns rules into a verdict, and the evidence behind every setting on this page. '
    . 'Rule weights themselves live in the <a href="knowledge.php">knowledge base</a>.');
show_flash();
?>
<!-- ------------------------------------------------ scoring -->
<?php // Anchor target: the threshold advice below links straight here, because
      // in category mode this is where the number it names actually lives. ?>
<div class="form-card" id="thresholds">
  <h2 style="margin-top:0">Scoring</h2>
  <p class="hint">
    <strong>Category scoring</strong> caps how much any single kind of evidence can contribute before
    the categories are summed. Without it, RSI, Stochastic, Williams %R, CCI and MFI all fire together
    in an oversold market and contribute five times over for what is really one observation — and the
    trend family does the same by construction. Capping means agreement <em>across</em> independent
    evidence is what moves the score.
  </p>
  <form method="post" action="engine.php">
    <input type="hidden" name="csrf" value="<?= $csrf ?>">
    <input type="hidden" name="act" value="scoring">
    <details class="acc">
      <summary>Scoring mode and thresholds</summary>
      <div class="acc-body">
        <p class="hint">How the rule votes become a number, and what that number has to reach to become a call.</p>
    <div class="row2">
      <div>
        <label>Scoring mode</label>
        <select aria-label="Scoring mode" name="scoring_mode">
          <option value="category" <?= $mode === 'category' ? 'selected' : '' ?>>Category-capped (recommended)</option>
          <option value="sum" <?= $mode === 'sum' ? 'selected' : '' ?>>Plain sum (original behaviour)</option>
        </select>
      </div>
      <div>
        <label>Minimum agreeing categories</label>
        <input aria-label="Minimum agreeing categories" type="number" name="min_aligned_categories" min="1" max="9" step="1"
               value="<?= sma_e(Database::setting('min_aligned_categories', '2')) ?>">
      </div>
    </div>
    <div class="row2">
      <div><label>BUY threshold (category mode)</label>
        <input aria-label="BUY threshold (category mode)" type="number" step="0.1" name="cat_buy_threshold" value="<?= sma_e(Database::setting('cat_buy_threshold', '3.0')) ?>"></div>
      <div><label>SELL threshold (category mode)</label>
        <input aria-label="SELL threshold (category mode)" type="number" step="0.1" name="cat_sell_threshold" value="<?= sma_e(Database::setting('cat_sell_threshold', '-3.0')) ?>"></div>
    </div>
    <p class="hint">Capping changes the scale of the score, so category mode keeps its own thresholds —
      switching modes is reversible without losing the numbers you tuned for the other one.</p>

      </div>
    </details>
    <details class="acc">
      <summary>Market filters</summary>
      <div class="acc-body">
        <p class="hint">Conditions in which the engine declines to call at all - a market going nowhere, or a pair too thin for the targets to be reachable.</p>
    <div class="row2">
      <div><label class="chk"><input type="checkbox" name="chop_gate_enabled"
             <?= Database::setting('chop_gate_enabled', '1') === '1' ? 'checked' : '' ?>>
             Stand down in chop</label>
        <input type="number" step="0.1" name="chop_limit" aria-label="Choppiness index limit" value="<?= sma_e(Database::setting('chop_limit', '61.8')) ?>">
        <p class="hint">Choppiness index above this suppresses directional signals. The ADX filter only
          dampens weights, so a consolidating market could still produce a confident breakout call.</p>
        <label class="chk"><input type="checkbox" name="chop_gate_a_plus_exempt"
             <?= Database::setting('chop_gate_a_plus_exempt', '1') === '1' ? 'checked' : '' ?>>
             Let A+ setups through anyway</label>
        <p class="hint">On the reasoning that a setup strong enough to earn the top grade should
          survive a choppy read. See the <strong>A+ chop exemption</strong> evidence further down this
          page before turning it off &mdash; it compares this install's own settled A+ signals published
          in chop against the ones published outside it.</p>
      </div>
      <div><label>Minimum 24h turnover (quote)</label>
        <input aria-label="Minimum 24h turnover (quote)" type="number" step="1000" name="min_quote_volume" value="<?= sma_e(Database::setting('min_quote_volume', '250000')) ?>">
        <p class="hint">Skip pairs too thin for the targets to be reachable without moving the market
          yourself. 0 disables the gate.</p>
      </div>
    </div>
      </div>
    </details>
    <details class="acc">
      <summary>Costs, targets and entry</summary>
      <div class="acc-body">
        <p class="hint">What a round trip takes out of every result, and whether a target is close enough to be reached in the time the trade is given.</p>
    <div class="row2">
      <div><label>Round-trip trading cost (%)</label>
        <input aria-label="Round-trip trading cost (%)" type="number" step="0.01" name="round_trip_cost_pct" value="<?= sma_e(Database::setting('round_trip_cost_pct', '0.1')) ?>">
        <p class="hint">Subtracted from every measured R. Published results were gross before this,
          flattering the engine by a fixed amount per trade. <strong>Set this to your exchange's
          real round trip</strong> — everything below decides which setups are worth taking, and it
          is only as right as this number.</p>
      </div>
      <div><label>Most of the risk fees may take</label>
        <input aria-label="Most of the risk fees may take" type="number" step="0.01" min="0" max="2" name="max_cost_r" value="<?= sma_e(Database::setting('max_cost_r', '0.33')) ?>">
        <p class="hint">A stop is a distance and so is a round trip. When the stop is the tighter of
          the two, fees are worth more than everything being risked and no price move makes the
          trade pay — measured here on a 3m setup whose stop sat 0.069% away against a 0.1% round
          trip, costs at 145% of risk, settled at &minus;2.45R. Above this ceiling the setup is not
          published. 0.33 means fees may take at most a third of the risk; 0 publishes them anyway.</p>
      </div>
      <div><label>Target must be reachable in the time allowed</label>
        <input aria-label="Target must be reachable in the time allowed" type="number" step="0.05" min="0" max="1" name="target_min_reach"
               value="<?= sma_e(Database::setting('target_min_reach', '0.35')) ?>">
        <p class="hint">Every target is a multiple of ATR and every time stop is a bar count, and
          nothing checked the two were compatible. A target three ATR away on a coin that typically
          covers one and a half inside the window is not ambitious - it is a trade that expires by
          construction, then settles as "went nowhere" rather than as a target that was never
          reachable. Measured on this pair's own history, in the direction traded, over windows the
          same length as the time stop. 0.35 means price must have got there in at least a third of
          them; 0 switches the test off. Too little history to say stands the gate aside rather than
          guessing.</p>
      </div>
      <div><label class="chk"><input type="checkbox" name="extension_gate_enabled"
             <?= Database::setting('extension_gate_enabled', '1') === '1' ? 'checked' : '' ?>>
             Do not start a trade into extension</label>
        <input type="number" step="0.25" min="0.5" max="10" name="extension_max_atr"
               aria-label="Maximum extension from the 20-EMA, in ATR"
               value="<?= sma_e(Database::setting('extension_max_atr', '2.5')) ?>">
        <p class="hint">Distance from the 20-EMA, in ATR, past which no NEW entry is taken in the
          direction of the stretch. Trend rules fire hardest exactly when a move is extended, so
          without this the engine is at its most confident at the worst moment to start. Only
          initiation is blocked and only with the stretch: selling into a market that has run up is
          a mean-reversion trade the rule set is entitled to make.</p>
      </div>
      <div><label>Entry zone (ATR)</label>
        <input aria-label="Entry zone (ATR)" type="number" step="0.05" name="entry_zone_atr" value="<?= sma_e(Database::setting('entry_zone_atr', '0.25')) ?>">
        <p class="hint">Published tolerance around the entry, because a reader acts minutes after the
          signal bar closes.</p>
      </div>
    </div>
      </div>
    </details>
    <details class="acc">
      <summary>Higher-timeframe ladder</summary>
      <div class="acc-body">
        <p class="hint">What the frames above the one being analysed are allowed to do: vote, charge a counter-trend call extra, or stand it down outright.</p>
    <label class="chk"><input type="checkbox" name="htf_engine_enabled"
      <?= Database::setting('htf_engine_enabled', '1') === '1' ? 'checked' : '' ?>>
      Re-run the full rule set one timeframe up and vote with its verdict</label>

    <label class="chk"><input type="checkbox" name="mtf_gate_enabled"
      <?= Database::setting('mtf_gate_enabled', '1') === '1' ? 'checked' : '' ?>>
      Charge counter-trend calls extra score against the higher-timeframe ladder</label>
    <p class="hint">The engine reads every timeframe above the one being analysed and weights the
      higher ones more heavily. Going with that bias is free; going against it has to clear a raised
      threshold. Counter-trend setups are not banned — plenty of the rule set is mean-reverting by
      design — they just have to be worth it.</p>
    <div class="row3">
      <div><label>Bias before the penalty starts</label>
        <input aria-label="Bias before the penalty starts" type="number" step="0.05" min="0.05" max="1" name="mtf_gate_bias"
          value="<?= sma_e(Database::setting('mtf_gate_bias', '0.35')) ?>">
        <p class="hint">Below this the frames above are split enough to have no usable opinion.</p>
      </div>
      <div><label>Penalty at a unanimous ladder</label>
        <input aria-label="Penalty at a unanimous ladder" type="number" step="0.05" min="0" max="1.5" name="mtf_penalty"
          value="<?= sma_e(Database::setting('mtf_penalty', '0.6')) ?>">
        <p class="hint">0.6 means a counter-trend call must clear a threshold 60% higher when every
          frame above disagrees. Scales down to nothing at the trigger point.</p>
      </div>
      <div><label>Never fade above</label>
        <input aria-label="Never fade above" type="number" step="0.05" min="0.5" max="1" name="mtf_gate_hard"
          value="<?= sma_e(Database::setting('mtf_gate_hard', '0.85')) ?>">
        <p class="hint">A ladder this clean is stood down outright, whatever the score — unless a
          turn-calling rule has a measured positive expectancy on this install.</p>
      </div>
    </div>
    <div class="row3">
      <div><label>Bias before the ladder votes at all</label>
        <input aria-label="Bias before the ladder votes at all" type="number" step="0.05" min="0" max="1" name="mtf_min_bias"
          value="<?= sma_e(Database::setting('mtf_min_bias', '0.25')) ?>">
        <p class="hint">Separate from the penalty above: this is the ladder casting its own vote in
          the score. Below this the frames above are split, and abstaining is more honest than a
          token vote either way. The vote is weighted by how hard the ladder leans, so a 0.9 bias
          counts for far more than a 0.3 one.</p>
      </div>
      <div><label>Trades behind a counter-trend exception</label>
        <input aria-label="Trades behind a counter-trend exception" type="number" step="1" min="5" max="500" name="mtf_reversal_min_n"
          value="<?= sma_e(Database::setting('mtf_reversal_min_n', '25')) ?>">
        <p class="hint">The stand-down above has one exception: a turn-calling rule in the setup
          that has earned it. This is how many verified trades on this timeframe it needs before
          its record is allowed to speak. On a fresh install nothing qualifies and the stand-down
          simply holds.</p>
      </div>
      <div><label>Expectancy it must show (R)</label>
        <input aria-label="Expectancy it must show (R)" type="number" step="0.05" min="0" max="2" name="mtf_reversal_min_r"
          value="<?= sma_e(Database::setting('mtf_reversal_min_r', '0.15')) ?>">
        <p class="hint">And how well those trades must have done. Break-even is not enough to
          justify trading against every frame above, so the bar is a clear positive average R after
          costs — measured, not assumed from the pattern's reputation.</p>
      </div>
    </div>

      </div>
    </details>
    <details class="acc">
      <summary>Market regime</summary>
      <div class="acc-body">
        <p class="hint">Whether thresholds, stops and confirmations adapt to how the market is behaving rather than staying fixed.</p>
    <label class="chk"><input type="checkbox" name="regime_detect_enabled"
      <?= Database::setting('regime_detect_enabled', '1') === '1' ? 'checked' : '' ?>>
      Adapt thresholds, stops and confirmations to the market regime</label>
    <p class="hint">Two axes: whether price is going anywhere (ADX and choppiness) and how hard it is
      moving while it does (ATR and Bollinger bandwidth, as percentiles of the instrument's own
      recent history — a fixed threshold would sort the universe by ticker rather than by
      condition). A grinding trend gets a slightly lower bar, a tighter stop and a longer leash; a
      whipsawing range gets a much higher bar, a wider stop and pulled-in targets. Every adjustment
      scales down towards no change when the classification is borderline, so a market sitting on
      the line is not shoved into a policy it does not clearly belong to.</p>

      </div>
    </details>
    <details class="acc">
      <summary>Market structure</summary>
      <div class="acc-body">
        <p class="hint">Break of structure, change of character and order blocks - read as context that shades a verdict, never as calls on their own.</p>
    <label class="chk"><input type="checkbox" name="smc_enabled"
      <?= Database::setting('smc_enabled', '1') === '1' ? 'checked' : '' ?>>
      Read smart-money structures (order blocks, gaps, sweeps, BOS/CHOCH, premium&nbsp;/&nbsp;discount)</label>
    <p class="hint">Read as context, never as calls. There is an order block behind price on every
      chart ever drawn, so the presence of a structure is never voted on — only price standing in one
      while the rest of the picture agrees. All eight rules sit in the structure category, whose
      correlation cap (<?= sma_e((string)(Database::pdo()
        ->query("SELECT cap FROM ta_categories WHERE category = 'structure'")->fetchColumn() ?: '2.5')) ?>)
      is below the buy threshold, so they can shade a verdict and can never produce one alone.</p>
    <div class="row2">
      <div><label>A break stays fresh for (bars)</label>
        <input aria-label="A break stays fresh for (bars)" type="number" step="1" min="1" max="50" name="smc_fresh_bars"
          value="<?= sma_e(Database::setting('smc_fresh_bars', '6')) ?>">
        <p class="hint">Break of structure and change of character are only counted while recent. A
          character change twenty bars old is just the current trend.</p>
      </div>
      <div><label>"At" a block means within (ATR)</label>
        <input aria-label="&quot;At&quot; a block means within (ATR)" type="number" step="0.05" min="0.05" max="3" name="smc_near_atr"
          value="<?= sma_e(Database::setting('smc_near_atr', '0.5')) ?>">
        <p class="hint">How close price must be to an order block or breaker before it counts as
          being at it. An unvisited block is scenery.</p>
      </div>
    </div>
    <label class="chk"><input type="checkbox" name="record_spread"
      <?= Database::setting('record_spread', '1') === '1' ? 'checked' : '' ?>>
      Record the bid/ask spread with every BUY and SELL signal</label>
    <p class="hint">One cached request per symbol, so it costs a little time on each new signal. It
      is what lets the engine find out later whether wide-spread setups are worth taking; switching
      it off leaves the field empty rather than recording a misleading zero.</p>
    <p style="margin-top:14px"><button class="btn" type="submit">Save scoring settings</button></p>
      </div>
    </details>
  </form>
</div>

<!-- ------------------------------------------------ categories -->
<div class="form-card" id="categories">
  <h2 style="margin-top:0">Evidence categories</h2>
  <p class="hint">Each category's net vote is clamped to its cap, then multiplied by its weight.
    A lower cap means that kind of evidence can never dominate on its own.</p>
  <form method="post" action="engine.php">
    <input type="hidden" name="csrf" value="<?= $csrf ?>">
    <input type="hidden" name="act" value="categories">
    <div class="tbl-scroll">
    <table class="grid">
      <tr><th>Category</th><th style="width:90px">Rules</th><th style="width:110px">Cap</th>
          <th style="width:110px">Weight</th><th style="width:70px">On</th></tr>
      <?php foreach ($cats as $c): ?>
      <tr>
        <td><strong><?= sma_e($c['label']) ?></strong>
            <div class="hint"><?= sma_e($c['category']) ?></div></td>
        <td><?= (int)($catUse[$c['category']] ?? 0) ?></td>
        <?php // Column heading + row label as the control's name - see the
              // same treatment on the rule grid in knowledge.php. ?>
        <td><input class="inline" type="number" step="0.1" min="0.1" max="20"
                   aria-label="Cap for <?= sma_e($c['label']) ?>"
                   name="cap[<?= sma_e($c['category']) ?>]" value="<?= sma_e((string)$c['cap']) ?>"></td>
        <td><input class="inline" type="number" step="0.05" min="0" max="3"
                   aria-label="Weight for <?= sma_e($c['label']) ?>"
                   name="cweight[<?= sma_e($c['category']) ?>]" value="<?= sma_e((string)$c['weight']) ?>"></td>
        <td style="text-align:center"><input type="checkbox" name="cen[<?= sma_e($c['category']) ?>]"
              aria-label="Enable <?= sma_e($c['label']) ?>" <?= $c['enabled'] ? 'checked' : '' ?>></td>
      </tr>
      <?php endforeach; ?>
    </table>
    </div>
    <p style="margin-top:14px"><button class="btn" type="submit">Save categories</button></p>
  </form>
</div>

<div class="form-card" id="presets">
  <h2 style="margin-top:0">Preset accuracy test</h2>
  <p class="hint">Replays <strong>every preset on the same candles</strong> &mdash; Selective,
    Balanced and Active, on each timeframe any of them scans, over your enabled coins &mdash; and
    reports what each would have done. Nothing is written to your live settings: the preset's
    values ride on the engine instance for the length of a replay, so cancelling half way leaves
    the site exactly as it was.</p>
  <p class="hint"><strong>Read the payoff column, not just the win rate.</strong> A signal engine
    can win two trades in three and still lose money, and this one does: measured over 1,194
    replayed trades while this was built, the average win was a third to a half of the average
    loss. <em>Break-even</em> is the payoff a system needs <em>at its own win rate</em> to come out
    level &mdash; at 65% accuracy that is 0.54. A preset that is more accurate and pays worse is
    the more expensive preset.</p>

  <?php
  $job = LongJob::state();
  $jobRunning = LongJob::pending();
  $res = ($job && ($job['kind'] ?? '') === 'preset_accuracy') ? ($job['results'] ?? null) : null;
  ?>

  <?php if ($jobRunning): ?>
    <?php $eta = LongJob::etaSeconds($job); ?>
    <p><strong><?= LongJob::doneCount($job) ?> of <?= (int)$job['total'] ?></strong> replays done<?php
      if ($eta !== null): ?> &mdash; about <?= $eta >= 90 ? ceil($eta / 60) . ' min' : $eta . 's' ?>
      of work left<?php endif; ?>.</p>
    <p class="hint">It advances on every cron run. There is no worker process to keep alive and
      nothing to leave open &mdash; you can close this page.</p>
    <form method="post" action="engine.php" style="display:inline">
      <input type="hidden" name="csrf" value="<?= $csrf ?>">
      <input type="hidden" name="act" value="job_step">
      <button class="btn" type="submit">Run a step now</button>
    </form>
    <form method="post" action="engine.php" style="display:inline"
          onsubmit="return confirm('Cancel the accuracy test? The partial results are lost and it starts from the beginning next time.')">
      <input type="hidden" name="csrf" value="<?= $csrf ?>">
      <input type="hidden" name="act" value="job_cancel">
      <button class="btn gray" type="submit">Cancel</button>
    </form>
  <?php else: ?>
    <form method="post" action="engine.php">
      <input type="hidden" name="csrf" value="<?= $csrf ?>">
      <input type="hidden" name="act" value="preset_test">
      <label>How many coins to replay</label>
      <select aria-label="How many coins to replay" name="max_symbols" style="max-width:280px">
        <?php foreach ([3, 6, 9, 12] as $nsym): ?>
          <option value="<?= $nsym ?>" <?= $nsym === 6 ? 'selected' : '' ?>><?= $nsym ?> coins
            &mdash; about <?= ceil($nsym * 3 * 3 * 4 / 60) ?> min of replaying</option>
        <?php endforeach; ?>
      </select>
      <p class="hint">More coins is a better sample and a longer wait. The estimate assumes about
        four seconds per replay, which is what it measured at here.</p>
      <p><button class="btn" type="submit"><?= $res ? 'Run it again' : 'Start the test' ?></button></p>
    </form>
  <?php endif; ?>

  <?php if ($res && !empty($res['cells'])): ?>
    <h2><?= $jobRunning ? 'So far' : 'Result' ?>
      &mdash; <?= count($res['symbols_done'] ?? []) ?> coin(s)</h2>
    <div class="tbl-scroll">
    <table class="grid">
      <tr><th>Preset</th><th>Timeframe</th><th>Trades</th><th>Win rate</th><th>Lower bound</th>
          <th>Expectancy</th><th>Avg win</th><th>Avg loss</th><th>Payoff</th><th>Break-even</th>
          <th>Worst run</th><th></th></tr>
      <?php foreach ($res['cells'] as $key => $cell): ?>
        <?php
        [$pk, $tf] = array_pad(explode('|', (string)$key), 2, '');
        $d = PresetTest::derive($cell);
        $scans = PresetTest::scans($pk, $tf);
        if ($d['n'] < 1) {
            continue;
        }
        ?>
        <tr<?= $scans ? '' : ' style="opacity:.55"' ?>>
          <td><strong><?= sma_e(ucfirst($pk)) ?></strong></td>
          <td><?= sma_e($tf) ?></td>
          <td><?= (int)$d['n'] ?></td>
          <td><?= sma_e((string)$d['win_rate']) ?>%</td>
          <td><?= $d['wilson'] !== null ? sma_e((string)$d['wilson']) . '%' : '&mdash;' ?></td>
          <td style="font-weight:500"><?= ($d['expectancy'] >= 0 ? '+' : '') . sma_e((string)$d['expectancy']) ?>R</td>
          <td>+<?= sma_e((string)$d['avg_win']) ?>R</td>
          <td><?= sma_e((string)$d['avg_loss']) ?>R</td>
          <td style="font-weight:500"><?= $d['payoff'] !== null ? sma_e((string)$d['payoff']) : '&mdash;' ?></td>
          <td><?= $d['need_payoff'] !== null ? sma_e((string)$d['need_payoff']) : '&mdash;' ?></td>
          <td>&minus;<?= sma_e((string)$d['max_dd']) ?>R</td>
          <td><?php if ($d['pays'] === true): ?><span style="color:var(--up)">pays</span>
              <?php elseif ($d['pays'] === false): ?><span style="color:var(--down)">loses</span>
              <?php endif; ?>
              <?= $scans ? '' : '<br><span class="hint">not scanned by this preset</span>' ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
    </div>
    <?php
    // What the engine was handed and never looked at. This is the check that
    // caught the presets shipping their score threshold under a name the
    // engine does not read in its default scoring mode - so every preset's
    // headline setting did nothing, and the report could not tell.
    $unused = $res['unused'] ?? [];
    ?>
    <?php if ($unused): ?>
      <p class="hint" style="border-left:3px solid var(--accent);padding-left:10px">
        <strong>Settings the engine was given and never read</strong>, so these rows do not
        measure them:
        <?php foreach ($unused as $pk => $keys): ?>
          <br><strong><?= sma_e(ucfirst((string)$pk)) ?></strong>:
          <code><?= sma_e(implode(', ', array_keys($keys))) ?></code>
        <?php endforeach; ?>
        <br><span class="hint">A threshold pair listed here is normal &mdash; the engine reads
        <code>cat_*</code> in category scoring and the plain names in sum scoring, and both are
        written so a later mode switch cannot move you to a threshold nobody chose.</span>
      </p>
    <?php endif; ?>
    <p class="hint"><strong>Lower bound</strong> is the 95% Wilson floor on the win rate &mdash;
      what the sample supports rather than what it printed. <strong>Worst run</strong> is the
      deepest peak-to-trough stretch, in R, on any single coin: not a portfolio drawdown, because
      keeping every trade in order for nine cells would outgrow the row this job is stored in, but
      it answers how ugly a preset gets before it recovers.</p>
    <p class="hint">Greyed rows are timeframes that preset does not scan &mdash; shown because
      &ldquo;what would including 15m have done&rdquo; is the question the row answers, and it is
      not answerable from a preset's own totals.</p>
    <?php if (!empty($job['errors'])): ?>
      <p class="hint"><strong><?= (int)$job['errors'] ?> replay(s) failed</strong> and are not in
        these totals<?= !empty($job['log']) ? ': ' . sma_e(implode('; ', array_slice($job['log'], 0, 5))) : '.' ?></p>
    <?php endif; ?>
  <?php endif; ?>
</div>

<div class="form-card" id="levels2">
  <h2 style="margin-top:0">Stop, target and exit search</h2>
  <p class="hint"><strong>This is the one that decides whether the site makes money.</strong>
    Measured over 1,194 replayed trades, this engine wins about 65% of the time and still loses:
    the average win is a third to a half of the average loss, and at 65% accuracy a system needs a
    payoff of 0.54 to break even. Every preset changes <em>which setups get published</em>, and no
    amount of choosing better setups repairs a payoff of 0.33. What fixes it is the shape of the
    trade &mdash; how far the stop sits (every published target is built as a multiple of that one
    distance, so this is the whole ladder at once) and how much is banked at the first target.</p>
  <p class="hint">Measured on one coin, changing nothing but the exit policy:
    <code>100% at target 1 &rarr; 65.3% win, &minus;0.03R</code> &middot;
    <code>50% &rarr; 65.3% win, &minus;0.01R</code> &middot;
    <code>0% &rarr; 36.1% win, +0.02R</code>.
    Less banked early, worse accuracy, better money. <strong>Expectancy decides here, never win
    rate</strong> &mdash; a 40% hit rate at 2R beats a 65% hit rate at 0.4, and optimising the
    wrong one is how a service ends up with a good scoreboard and a losing account.</p>

  <?php
  $ljob = LongJob::state();
  $lvRunning = LongJob::pending() && ($ljob['kind'] ?? '') === 'level_search';
  $lvRes = ($ljob && ($ljob['kind'] ?? '') === 'level_search') ? ($ljob['results'] ?? null) : null;
  ?>

  <?php if ($lvRunning): ?>
    <?php $lvEta = LongJob::etaSeconds($ljob); ?>
    <p><strong><?= LongJob::doneCount($ljob) ?> of <?= (int)$ljob['total'] ?></strong> replays
      done<?php if ($lvEta !== null): ?> &mdash; about
      <?= $lvEta >= 90 ? ceil($lvEta / 60) . ' min' : $lvEta . 's' ?> left<?php endif; ?>.
      Advances on every cron run; you can close this page.</p>
    <form method="post" action="engine.php" style="display:inline">
      <input type="hidden" name="csrf" value="<?= $csrf ?>">
      <input type="hidden" name="act" value="job_step">
      <button class="btn" type="submit">Run a step now</button>
    </form>
    <form method="post" action="engine.php" style="display:inline"
          onsubmit="return confirm('Cancel the search? The partial results are lost.')">
      <input type="hidden" name="csrf" value="<?= $csrf ?>">
      <input type="hidden" name="act" value="job_cancel">
      <button class="btn gray" type="submit">Cancel</button>
    </form>
  <?php elseif (!LongJob::pending()): ?>
    <form method="post" action="engine.php">
      <input type="hidden" name="csrf" value="<?= $csrf ?>">
      <input type="hidden" name="act" value="level_search">
      <div class="row2">
        <div><label>Timeframe</label>
          <select aria-label="Timeframe" name="tf"><?php foreach ($config['market']['intervals'] as $iv): ?>
            <option value="<?= sma_e($iv) ?>" <?= $iv === '1h' ? 'selected' : '' ?>><?= sma_e($iv) ?></option>
          <?php endforeach; ?></select>
          <p class="hint">Geometry is per timeframe: a stop that is comfortable on 4h is noise-tight
            on 15m, so they are searched separately.</p></div>
        <div><label>Coins</label>
          <select aria-label="Coins" name="max_symbols">
            <?php foreach ([2, 4, 6, 8] as $nsym): ?>
              <option value="<?= $nsym ?>" <?= $nsym === 4 ? 'selected' : '' ?>><?= $nsym ?> coins</option>
            <?php endforeach; ?>
          </select>
          <label style="margin-top:10px" class="check">
            <input type="checkbox" name="exits" value="1" checked> Search the exit policy too
          </label>
          <p class="hint">Four exit policies against <?= count(\SignalMasterAi\Backtest::levelGrid()) ?>
            geometries. With exits on that is
            <?= count(\SignalMasterAi\Backtest::levelGrid()) * count(LevelSearch::EXITS) ?>
            candidates per coin &mdash; a long background job, which is what the queue is for.</p></div>
      </div>
      <p><button class="btn" type="submit">Start the search</button></p>
    </form>
  <?php else: ?>
    <p class="hint">Another measurement is running. One at a time &mdash; two long replays racing
      each other on shared hosting is how an account gets its CPU quota pulled.</p>
  <?php endif; ?>

  <?php if ($lvRes && !empty($lvRes['cells'])): ?>
    <?php $ranked = LevelSearch::ranked($lvRes); $bestRow = LevelSearch::best($lvRes); ?>
    <h2><?= $lvRunning ? 'So far' : 'Result' ?> &mdash; <?= sma_e((string)($lvRes['tf'] ?? '')) ?>,
      best <?= min(12, count($ranked)) ?> of <?= count($ranked) ?> tried</h2>
    <?php
    // The live geometry, for comparison. A search result with nothing to
    // compare it against is a number, not a decision.
    $liveLv = \SignalMasterAi\Backtest::levelsFor((string)($lvRes['tf'] ?? '')) ?? null;
    ?>
    <?php if ($liveLv): ?>
      <p class="hint">Currently live on <?= sma_e((string)$lvRes['tf']) ?>: stop
        <?= sma_e((string)$liveLv['sl']) ?> ATR &mdash; targets publish at exactly 1&times;, 2&times;
        and 3&times; that distance, always.</p>
    <?php endif; ?>
    <p class="hint">Only the stop distance is searched below; every candidate publishes its targets
      at exactly 1R, 2R and 3R of whatever stop it is tried with, so a target column here would just
      repeat the stop column three ways.</p>
    <div class="tbl-scroll">
    <table class="grid">
      <tr><th>Stop (ATR)</th><th>Exit</th><th>Trades</th><th>Win rate</th>
          <th>Expectancy</th><th>Payoff</th><th>Break-even</th><th></th></tr>
      <?php foreach (array_slice($ranked, 0, 12) as $row): ?>
        <tr<?= $row === $bestRow ? ' style="background:rgba(127,127,127,.12)"' : '' ?>>
          <td><?= sma_e((string)$row['sl']) ?></td>
          <td><?= $row['ex1'] === null ? '&mdash;'
                : (int)$row['ex1'] . '/' . (int)$row['ex2'] ?></td>
          <td><?= (int)$row['n'] ?></td>
          <td><?= sma_e((string)$row['win_rate']) ?>%</td>
          <td style="font-weight:500"><?= ($row['expectancy'] >= 0 ? '+' : '')
                . sma_e((string)$row['expectancy']) ?>R</td>
          <td><?= $row['payoff'] !== null ? sma_e((string)$row['payoff']) : '&mdash;' ?></td>
          <td><?= $row['need_payoff'] !== null ? sma_e((string)$row['need_payoff']) : '&mdash;' ?></td>
          <td><?php if (!$row['enough']): ?>
                <span class="hint">only <?= (int)$row['n'] ?> trades</span>
              <?php elseif (!$lvRunning): ?>
                <form method="post" action="engine.php" style="display:inline"
                      onsubmit="return confirm('Apply this geometry to <?= sma_e((string)($lvRes['tf'] ?? '')) ?>? It changes the shape of every trade the site publishes from now on. It is listed in the change log.')">
                  <input type="hidden" name="csrf" value="<?= $csrf ?>">
                  <input type="hidden" name="act" value="level_apply">
                  <input type="hidden" name="idx" value="<?= (int)$row['idx'] ?>">
                  <button class="btn small" type="submit">Apply</button>
                </form>
              <?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
    </div>
    <p class="hint">Ranked by expectancy. A candidate needs
      <?= LevelSearch::MIN_DECIDED ?> decided trades before it can be applied &mdash; below that the
      grid is selecting noise, and the row says so instead of offering a button.
      <?php if ($bestRow && $bestRow['expectancy'] <= 0): ?>
        <br><strong>Nothing here is profitable.</strong> The best geometry found is still negative,
        which means the problem is not the shape of the trade alone &mdash; look at the score band
        table above, and at whether the fast timeframes are worth scanning at all.
      <?php endif; ?></p>
  <?php endif; ?>
</div>

<!-- ------------------------------------------------ A/B -->
<div class="form-card" id="ab">
  <h2 style="margin-top:0">Compare a change before you make it</h2>
  <p class="hint">Replays the same stored history under the current configuration and under your
    proposed one, then reports which produced better expectancy. Nothing is written while it runs —
    the challenger values ride on the engine instance, so an interrupted comparison cannot leave the
    live configuration half-changed.</p>
  <p class="hint">Scoring mode on this site is
    <strong><?= SignalEngine::scoringMode() === 'sum' ? 'Plain sum' : 'Category-capped' ?></strong>
    (<a href="settings.php#signals">Settings &rsaquo; Signals</a>), so the BUY threshold below is
    applied as <code><?= SignalEngine::scoringMode() === 'sum' ? 'buy_threshold' : 'cat_buy_threshold' ?></code>
    &mdash; whichever one the engine actually reads right now.</p>
  <form method="post" action="engine.php" onsubmit="this.querySelector('button').textContent='Running… this takes a minute';">
    <input type="hidden" name="csrf" value="<?= $csrf ?>">
    <input type="hidden" name="act" value="ab">
    <div class="row2">
      <div><label>Timeframe</label>
        <select aria-label="Timeframe" name="tf"><?php foreach ($config['market']['intervals'] as $iv): ?>
          <option value="<?= sma_e($iv) ?>" <?= $iv === '1h' ? 'selected' : '' ?>><?= sma_e($iv) ?></option>
        <?php endforeach; ?></select></div>
      <div><label>Challenger BUY threshold</label>
        <input aria-label="Challenger BUY threshold" type="number" step="0.1" name="ch_cat_buy_threshold" placeholder="leave blank to keep current"></div>
    </div>
    <div class="row2">
      <div><label>Challenger minimum categories</label>
        <input aria-label="Challenger minimum categories" type="number" step="1" min="1" max="9" name="ch_min_aligned_categories" placeholder="unchanged"></div>
      <div><label>Challenger chop limit</label>
        <input aria-label="Challenger chop limit" type="number" step="0.1" name="ch_chop_limit" placeholder="unchanged"></div>
    </div>
    <p style="margin-top:12px"><button class="btn" type="submit">Run comparison</button></p>
  </form>

  <?php if ($ab): ?>
    <h2>Last comparison — <?= sma_e((string)$ab['tf']) ?>, <?= date('M j H:i', (int)$ab['at']) ?></h2>
    <div class="tbl-scroll">
    <table class="grid">
      <tr><th></th><th>Signals</th><th>Win rate</th><th>Expectancy (R)</th></tr>
      <tr><td><strong>Champion (live)</strong></td>
        <td><?= (int)$ab['champion']['signals'] ?></td>
        <td><?= $ab['champion']['winrate'] !== null ? sma_e((string)$ab['champion']['winrate']) . '%' : '—' ?></td>
        <td><?= $ab['champion']['expectancy'] !== null ? sma_e((string)$ab['champion']['expectancy']) : '—' ?></td></tr>
      <tr><td><strong>Challenger</strong>
          <div class="hint"><?php foreach ((array)$ab['changes'] as $k => $v) { echo sma_e($k . '=' . $v) . ' '; } ?></div></td>
        <td><?= (int)$ab['challenger']['signals'] ?></td>
        <td><?= $ab['challenger']['winrate'] !== null ? sma_e((string)$ab['challenger']['winrate']) . '%' : '—' ?></td>
        <td><?= $ab['challenger']['expectancy'] !== null ? sma_e((string)$ab['challenger']['expectancy']) : '—' ?></td></tr>
    </table>
    </div>
    <p class="hint" style="margin-top:8px">Winner: <strong><?= sma_e((string)$ab['winner']) ?></strong>
      <?= $ab['delta'] !== null ? '(expectancy difference ' . sma_e((string)$ab['delta']) . 'R)' : '' ?>.
      Expectancy decides, not win rate — a 40% win rate at 2R beats 60% at 0.5R.</p>
  <?php endif; ?>
</div>

<!-- ------------------------------------------------ self-tuning log -->
<?php
$adaptReport = json_decode(Database::setting('adapt_report', ''), true) ?: [];
$adaptHistory = array_reverse(\SignalMasterAi\Adapt::history());
$adaptPerf = \SignalMasterAi\Adapt::performance();
?>
<div class="form-card" id="adapt">
  <h2 style="margin-top:0">Self-tuning log</h2>
  <p class="hint">The comparison above answers "is this change better?" — but somebody still has to
    have the idea. This proposes candidates from the measured record, validates each one against
    history, applies at most one clear winner, and then judges it on what actually happened rather
    than on what the backtest promised. A change that fails to deliver is put back automatically.
    Runs weekly from cron; the drift check runs daily.</p>

  <p class="hint">Current record: <strong><?= (int)$adaptPerf['n'] ?></strong> verified signals,
    <?= $adaptPerf['winrate'] !== null ? sma_e((string)$adaptPerf['winrate']) . '% win rate' : 'no win rate yet' ?>,
    expectancy <?= $adaptPerf['avg_r'] !== null ? sma_e((string)$adaptPerf['avg_r']) . 'R' : '—' ?>.
    <?php if ((int)$adaptPerf['n'] < 80): ?>
      Nothing is proposed below 80 verified signals — with less than that the record cannot tell a
      weakness from a run of bad luck.
    <?php endif; ?></p>

  <form method="post" action="engine.php">
    <input type="hidden" name="csrf" value="<?= $csrf ?>">
    <input type="hidden" name="act" value="adapt">
    <label class="chk"><input type="checkbox" name="adapt_enabled"
      <?= Database::setting('adapt_enabled', '1') === '1' ? 'checked' : '' ?>>
      Let the engine propose, validate and apply its own changes</label>
    <p class="hint">Off leaves everything above as a read-only record: nothing new is proposed, and
      no change already applied is rolled back. Settings a past run changed stay where that run put
      them — switching this off freezes the tuner, it does not undo it. Turn it off if you would
      rather make every change by hand, or while investigating a result you do not trust.</p>
    <div class="row2">
      <div><label>Verified signals before a change is judged</label>
        <input aria-label="Verified signals before a change is judged" type="number" step="1" min="20" max="500" name="adapt_min_live"
          value="<?= sma_e(Database::setting('adapt_min_live', '40')) ?>">
        <p class="hint">The drift check runs daily but stays silent until a change has this much
          live evidence behind it. Set it low and normal variance will roll back good changes; set
          it high and a bad one runs for longer before it is caught. 40 is roughly where the
          measured spread between a real edge and noise stops overlapping on this install.</p>
      </div>
      <div><label>Timeframe changes are validated on</label>
        <select aria-label="Timeframe changes are validated on" name="adapt_tf">
          <?php foreach ($config['market']['intervals'] as $iv): ?>
            <option value="<?= sma_e($iv) ?>"
              <?= Database::setting('adapt_tf', '1h') === $iv ? 'selected' : '' ?>><?= sma_e($iv) ?></option>
          <?php endforeach; ?>
        </select>
        <p class="hint">Each candidate is replayed on the first ten enabled coins at this timeframe
          before it is allowed anywhere near live settings. Pick the timeframe your
          readers actually trade — a change validated on daily bars says little about a 5m setup.</p>
      </div>
    </div>
    <p style="margin-top:14px"><button class="btn" type="submit">Save self-tuning settings</button></p>
  </form>

  <?php if (!empty($adaptReport['results'])): ?>
    <h2>Last run — <?= date('M j H:i', (int)$adaptReport['at']) ?></h2>
    <div class="tbl-scroll">
    <table class="grid">
      <tr><th>Proposal</th><th>Why</th><th>Champion</th><th>Challenger</th><th>Delta</th></tr>
      <?php foreach ($adaptReport['results'] as $r): ?>
        <tr>
          <td><code><?= sma_e((string)$r['key']) ?></code></td>
          <td class="hint"><?= sma_e((string)$r['why']) ?></td>
          <td><?= $r['champion'] !== null ? sma_e((string)$r['champion']) : '—' ?></td>
          <td><?= $r['challenger'] !== null ? sma_e((string)$r['challenger']) : '—' ?></td>
          <td style="font-weight:500;color:<?= ($r['delta'] ?? 0) > 0 ? 'var(--up)' : 'var(--down)' ?>">
            <?= $r['delta'] !== null ? sma_e((string)$r['delta']) : '—' ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
    </div>
  <?php endif; ?>

  <?php if ($adaptHistory): ?>
    <h2>Changes</h2>
    <div class="tbl-scroll">
    <table class="grid">
      <tr><th>When</th><th>Change</th><th>From &rarr; to</th><th>Was (live)</th><th>Backtest said</th><th>Actual (live)</th><th>Status</th></tr>
      <?php foreach ($adaptHistory as $h): ?>
        <?php $st = (string)($h['status'] ?? 'active'); ?>
        <tr>
          <td><?= date('M j', (int)$h['at']) ?></td>
          <td><code><?= sma_e((string)$h['key']) ?></code>
            <div class="hint"><?= sma_e((string)($h['why'] ?? '')) ?></div></td>
          <td class="hint"><?php
            foreach ((array)($h['to'] ?? []) as $k => $v) {
                echo sma_e($k . ': ' . ($h['from'][$k] ?? '?') . ' → ' . $v) . '<br>';
            } ?></td>
          <td><?= isset($h['live_before']) && $h['live_before'] !== null
                ? sma_e((string)$h['live_before']) . 'R' : '<span class="hint">—</span>' ?></td>
          <td class="hint"><?= $h['expected'] !== null ? sma_e((string)$h['expected']) . 'R' : '—' ?></td>
          <td><?= isset($h['actual']) ? sma_e((string)$h['actual']) . 'R' : '<span class="hint">measuring</span>' ?></td>
          <td style="color:<?= $st === 'rolled_back' ? 'var(--down)' : 'var(--up)' ?>">
            <?= $st === 'rolled_back' ? 'rolled back' : 'active' ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
    </div>
    <p class="hint" style="margin-top:8px">A change is judged live against live: what this install
      was actually achieving before it, versus what it has achieved since. The backtest column is
      shown for interest only and is deliberately <em>not</em> the yardstick — a backtest replays
      the same candles its own tuned weights and level distances were fitted on, which measures
      around a fifth high here. Judging a live result against that inflated figure would revert
      almost every change, including the good ones.</p>
  <?php endif; ?>
</div>

<!-- ------------------------------------------------ level optimiser -->
<div class="form-card" id="levels">
  <h2 style="margin-top:0">Stop &amp; target optimiser</h2>
  <p class="hint">The stop distance behind every trade plan was a fixed constant, even though it sets
    the whole risk/reward profile and therefore every measured R - every target publishes as an exact
    multiple of it (1R, 2R, 3R), so tuning the stop tunes the whole ladder. What is in use here is
    shown below.</p>
  <?php // TWO PANELS WERE SEARCHING THE SAME THING, AND THIS ONE COULD NOT FINISH.
        //
        // The form that stood here ran the search inline on up to eight coins -
        // twenty minutes of work inside one web request. It did not merely risk
        // timing out; a six-coin run of exactly that call was measured dying at
        // "Maximum execution time of 900 seconds exceeded" with nothing saved.
        // The search lives in the panel above now, on the runner that survives
        // it, and this card keeps what it was actually good for: showing which
        // geometry each timeframe is running. ?>
  <p class="hint"><a href="#levels2" style="color:var(--accent)"><strong>&uarr; Run the search from
    Stop, target and exit search</strong></a> &mdash; same grid and same objective, queued so it
    finishes, and with the exit policy searched alongside the distances.</p>

  <?php if ($tfLevels): ?>
    <h2>Learned levels in use</h2>
    <div class="tbl-scroll">
    <table class="grid">
      <tr><th>Timeframe</th><th>Stop (ATR)</th><th>Targets</th><th>Expectancy</th></tr>
      <?php foreach ($tfLevels as $tfk => $l): ?>
        <tr><td><strong><?= sma_e((string)$tfk) ?></strong></td>
            <td><?= sma_e((string)$l['sl']) ?></td>
            <td class="hint">1R / 2R / 3R of the stop</td>
            <td><?= sma_e((string)($l['expectancy'] ?? '—')) ?></td></tr>
      <?php endforeach; ?>
    </table>
    </div>
  <?php endif; ?>
  <?php if ($lvl && !empty($lvl['results'])): ?>
    <h2>Last search — <?= sma_e((string)$lvl['tf']) ?>, <?= (int)$lvl['tested'] ?> combinations</h2>
    <div class="tbl-scroll">
    <table class="grid">
      <tr><th>Stop</th><th>Signals</th><th>Win rate</th><th>Expectancy</th></tr>
      <?php foreach (array_slice($lvl['results'], 0, 8) as $r): ?>
        <tr><td><?= sma_e((string)$r['sl']) ?></td><td><?= (int)$r['signals'] ?></td>
            <td><?= $r['winrate'] !== null ? sma_e((string)$r['winrate']) . '%' : '—' ?></td>
            <td style="font-weight:500;color:<?= ($r['expectancy'] ?? 0) > 0 ? 'var(--up)' : 'var(--down)' ?>">
              <?= $r['expectancy'] !== null ? sma_e((string)$r['expectancy']) : '—' ?></td></tr>
      <?php endforeach; ?>
    </table>
    </div>
  <?php endif; ?>
</div>

<!-- ------------------------------------------------ learned model -->
<div class="form-card" id="model">
  <h2 style="margin-top:0">Learned model</h2>
  <p class="hint">A logistic regression fitted on this install's own verified signals. Every stored
    signal already carried the rules that fired and how it resolved — a labelled dataset that only
    hand-set weights were consuming. The model reads the whole fired-rule vector rather than its sum,
    and supplies a calibrated probability as the confidence figure. It stays silent until it has
    enough samples <em>and</em> beats the base rate out of sample.</p>

  <?php if (Database::setting('learned_model_enabled', '1') !== '1'): ?>
    <p class="hint" style="color:var(--warn-text)"><strong>Switched off under
      <a href="settings.php#signals">Settings &rsaquo; Signals</a>.</strong> The stats below are the
      last trained fit, kept for reference — this model's vote is currently excluded from every
      signal, and "Retrain now" fits a model that still won't be consulted until you turn it back
      on.</p>
  <?php endif; ?>

  <?php if (!empty($model['trained'])): ?>
    <div class="tbl-scroll">
    <table class="grid">
      <tr><th>Samples</th><th>Held out</th><th>Accuracy (out of sample)</th><th>Base rate</th><th>Brier</th><th>Trained</th></tr>
      <tr>
        <td><?= (int)$model['samples'] ?></td>
        <td><?= (int)($model['test_n'] ?? 0) ?></td>
        <td style="font-weight:500;color:<?= ($model['accuracy'] ?? 0) > max((float)($model['base_rate'] ?? 0.5), 1 - (float)($model['base_rate'] ?? 0.5)) ? 'var(--up)' : 'var(--warn)' ?>">
          <?= sma_e((string)round(((float)$model['accuracy']) * 100, 1)) ?>%</td>
        <td><?= sma_e((string)round(((float)$model['base_rate']) * 100, 1)) ?>%</td>
        <td><?= sma_e((string)($model['brier'] ?? '—')) ?></td>
        <td><?= date('M j H:i', (int)($model['trained_at'] ?? time())) ?></td>
      </tr>
    </table>
    </div>
    <?php if ($topFeatures): ?>
      <h2>What it learned</h2>
      <p class="hint">Strongest coefficients. A rule whose learned sign contradicts its hand-set
        weight is worth a look — the model is saying the market disagrees with the assumption.</p>
      <div class="tbl-scroll">
      <table class="grid">
        <tr><th>Feature</th><th style="width:120px">Coefficient</th></tr>
        <?php foreach ($topFeatures as $f => $w): ?>
          <tr><td><?= sma_e((string)$f) ?></td>
              <td style="font-weight:500;color:<?= $w > 0 ? 'var(--up)' : 'var(--down)' ?>">
                <?= $w > 0 ? '+' : '' ?><?= sma_e((string)round((float)$w, 3)) ?></td></tr>
        <?php endforeach; ?>
      </table>
      </div>
    <?php endif; ?>
  <?php else: ?>
    <p class="hint">Not trained yet: <strong><?= (int)($model['samples'] ?? 0) ?></strong> verified
      signals of the <strong><?= (int)($model['need'] ?? 120) ?></strong> needed. Until then the
      engine uses calibrated confidence buckets, then the weighted-sum heuristic.</p>
  <?php endif; ?>
  <form method="post" action="engine.php" style="margin-top:12px">
    <input type="hidden" name="csrf" value="<?= $csrf ?>">
    <input type="hidden" name="act" value="retrain">
    <button class="btn gray" type="submit">Retrain now</button>
  </form>
</div>

<!-- ------------------------------------------------ shadow signals -->
<?php
// WHAT REFUSING COST, which is the one thing a record of published signals
// cannot answer. A gate that keeps standing down winners is a setting to
// loosen; one that keeps standing down losers is earning its place.
$shadowOn = Database::setting('shadow_enabled', '1') === '1';
$shadowStats = $shadowOn ? \SignalMasterAi\Outcomes::shadowStats() : [];
$shadowOpen = 0;
$shadowTotal = 0;
if ($shadowOn) {
    try {
        $shadowOpen = (int)Database::pdo()->query(
            "SELECT COUNT(*) FROM shadow_signals WHERE outcome = ''")->fetchColumn();
        $shadowTotal = (int)Database::pdo()->query('SELECT COUNT(*) FROM shadow_signals')->fetchColumn();
    } catch (Throwable $e) {
    }
}
$gateName = [
    'threshold'      => 'Score threshold (near miss)',
    'min_categories' => 'Confluence requirement',
    'chop_gate'      => 'Chop filter',
    'cooldown'       => 'Stop-out cooldown',
    'target_reach'   => 'Target reachability',
    'cost_floor'     => 'Cost floor',
    'mtf_conflict'   => 'Higher-timeframe conflict',
    'score_band'     => 'Score band record',
    // Pre-flight refusals: not a market opinion, a plan that could not be
    // traded or data that could not be trusted.
    'plan_missing'      => 'Pre-flight: no entry or stop',
    'plan_stop_side'    => 'Pre-flight: stop on the wrong side',
    'plan_target_side'  => 'Pre-flight: target on the wrong side',
    'plan_target_order' => 'Pre-flight: targets out of order',
    'plan_no_target'    => 'Pre-flight: no usable target',
    'late_entry'        => 'Pre-flight: most of the move already gone',
    'preflight'         => 'Pre-flight check',
];
?>
<div class="form-card">
  <h2 style="margin-top:0">What refusing cost you</h2>
  <?php if (!$shadowOn): ?>
    <p class="hint">Shadow signals are off. The engine can only learn from trades it published, so it
      has no way to find out whether a filter was right to stand a setup down.
      Turn them on under <a href="settings.php#selflearn">Settings &rsaquo; Signals &rsaquo; Self-learning</a>.</p>
  <?php elseif (!$shadowStats): ?>
    <p class="hint"><?= $shadowTotal ?> setup<?= $shadowTotal === 1 ? '' : 's' ?> recorded,
      <?= $shadowOpen ?> still open. Nothing has settled yet &mdash; each one is walked forward
      against real candles exactly like a published signal, so this fills in as they resolve.
      Members never see any of it.</p>
  <?php else: ?>
    <p class="hint">Setups the engine declined, settled against real price action with the plan it
      would have published. <strong>Negative average R means the filter was right</strong> &mdash; it
      saved you that much per trade. Positive means it is standing down setups that went on to work,
      and is worth loosening. Expired setups went nowhere and cost nothing either way.</p>
    <div class="tbl-scroll">
    <table class="grid stack">
      <tr class="head"><th>What refused it</th><th>Settled</th><th>Would have won</th>
          <th>Average R</th><th>Went nowhere</th><th>Verdict</th></tr>
      <?php foreach ($shadowStats as $gate => $st): ?>
        <?php
        $enough = $st['n'] >= 20;
        $good = $st['avg_r'] < 0;
        ?>
        <tr>
          <td data-label="What refused it"><?= sma_e($gateName[$gate] ?? $gate) ?></td>
          <td data-label="Settled"><?= (int)$st['n'] ?></td>
          <td data-label="Would have won"><?= $st['n'] > 0 ? round($st['wins'] / $st['n'] * 100) . '%' : '—' ?></td>
          <td data-label="Average R" style="font-weight:500;color:<?= $good ? 'var(--up)' : 'var(--down)' ?>">
            <?= $st['avg_r'] > 0 ? '+' : '' ?><?= sma_e((string)$st['avg_r']) ?></td>
          <td data-label="Went nowhere"><?= (int)$st['expired'] ?></td>
          <td data-label="Verdict"><?= !$enough
              ? '<span class="hint">too few to judge</span>'
              : ($good ? '<span class="badge on">earning its place</span>'
                       : '<span class="badge off">costing you &mdash; consider loosening</span>') ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
    </div>
    <?php $advice = \SignalMasterAi\Outcomes::thresholdAdvice(); ?>
    <?php if ($advice): ?>
      <?php foreach ($advice as $side => $a): ?>
        <div class="okmsg" style="background:var(--bg-warn);border:1px solid #D8B26A66;color:var(--warn-text);
             padding:10px 12px;border-radius:8px;font-size:13px;margin-top:12px">
          <strong>Your <?= sma_e($side) ?> gate may be stricter than it needs to be.</strong><br>
          <?= (int)$a['n'] ?> setups were refused between <?= sma_e((string)$a['score']) ?> and your
          current <strong><?= sma_e((string)$a['current']) ?></strong>
          (<code><?= sma_e((string)$a['setting']) ?></code>), and they averaged
          <strong><?= $a['avg_r'] > 0 ? '+' : '' ?><?= sma_e((string)$a['avg_r']) ?>R</strong> &mdash;
          money the site turned down. Easing the <?= sma_e($side) ?> gate to
          <strong><?= sma_e((string)$a['score']) ?></strong> would have taken them.
          <span class="hint">Nothing is changed automatically; a threshold is the most consequential
          number here. The number quoted above is the one this engine actually reads &mdash; set it
          under <a href="<?= sma_e((string)$a['where']) ?>"><?= $a['where_label'] ?></a> if you agree
          with the evidence.</span>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
    <p class="hint" style="margin-top:10px"><?= $shadowTotal ?> recorded, <?= $shadowOpen ?> still open.
      None of this is shown to members or counted in the public track record &mdash; it lives in its
      own table and feeds the tuner only.</p>
  <?php endif; ?>
</div>

<!-- ------------------------------------------------ per-coin learning -->
<?php
// WHAT EACH COIN HAS EARNED, and what it has not.
//
// The honest thing to show is not just the multipliers, but how much of each
// coin's own measurement is being used - because for most coins the answer is
// "almost none of it, yet", and that is the design working rather than the
// feature missing.
$symMult = \SignalMasterAi\SymbolLearning::all();
$symRep  = json_decode(Database::setting('sym_learn_report', ''), true) ?: [];
$symMode = \SignalMasterAi\Outcomes::symLearnMode();
$symOn   = $symMode !== 'global';
$symK    = max(1, (int)Database::setting('sym_learn_k', '40'));
$symMin  = \SignalMasterAi\Outcomes::SYM_MIN_SAMPLES;
$symRuleTotal = (int)Database::pdo()->query('SELECT COUNT(*) FROM ta_knowledge WHERE enabled = 1')->fetchColumn();
$symCounts = [];
foreach (Database::pdo()->query(
    "SELECT symbol, COUNT(*) n FROM signals
      WHERE outcome IN ('confirmed','invalid') AND `signal` != 'NEUTRAL'
      GROUP BY symbol ORDER BY n DESC LIMIT 25") as $r) {
    $symCounts[(string)$r['symbol']] = (int)$r['n'];
}
?>
<div class="form-card">
  <h2 style="margin-top:0">What each coin has learned</h2>
  <?php if (!$symOn): ?>
    <p class="hint">Per-coin learning is set to <strong>shared only</strong> &mdash; every coin uses
      the shared rule weights and none has an opinion of its own. Change it under
      <a href="settings.php#selflearn">Settings &rsaquo; Signals &rsaquo; Self-learning</a>.</p>
  <?php elseif (!$symCounts): ?>
    <p class="hint">No coin has settled a trade yet, so every coin is on the shared weights.
      Nothing to do: this fills itself in as signals close.</p>
  <?php else: ?>
    <?php if ($symMode === 'coin'): ?>
      <p class="hint">Mode: <strong>coin only</strong>. Once a coin has settled
        <strong><?= $symMin ?></strong> trades it uses its own measurement whole &mdash; no blending
        with the shared weights at all. Honest on a pair with a long record; on one that has just
        crossed <?= $symMin ?> it is acting on what a coin toss does half the time. Below that floor
        there is nothing to measure and the coin stays on the shared weights.</p>
    <?php else: ?>
      <p class="hint">Mode: <strong>automatic</strong>. A coin's own record is blended with the
        shared weights in proportion to what it has settled &mdash; half its own voice at
        <strong><?= $symK ?></strong> trades. A coin with a short record is deliberately almost
        identical to the shared weights, and that is the point: two wins from three trades is what
        a coin toss does half the time.</p>
    <?php endif; ?>
    <?php
    // A LIST THAT ENDED WHEN THE COINS DID.
    //
    // Every coin with a settled trade printed a card, and on a phone each one
    // is a stacked block - so an install scanning ninety coins put ninety
    // blocks between the operator and everything below this panel. The page
    // has four more cards after it, and nobody scrolled that far twice.
    //
    // Capped at a page rather than paginated on the server: the query has
    // already fetched these and they cost nothing to hold, so the button is
    // instant and there is no endpoint to authenticate. The rows are rendered
    // either way and hidden by script, never by markup - an operator with
    // JavaScript off sees the whole list rather than a truncated one with no
    // way to reveal the rest, which is the failure mode worth avoiding.
    $symPage = 10;
    $symTotal = count($symCounts);
    $symIdx = 0;
    ?>
    <div class="tbl-scroll">
    <table class="grid stack" id="coinLearn" data-page="<?= (int)$symPage ?>">
      <tr class="head"><th>Coin</th><th>Settled trades</th><th>Its own voice</th><th>Rules it has moved</th></tr>
      <?php foreach ($symCounts as $sym => $n): ?>
        <?php
        // Under the floor the tuner never looks at the coin, so "100% of its
        // own voice" would be a number the engine does not act on. Say what
        // actually happens instead.
        $below = $n < $symMin;
        $voice = $below ? 0.0 : ($symMode === 'coin' ? 1.0 : $n / ($n + $symK));
        $moved = count($symMult[$sym] ?? []);
        ?>
        <tr<?= $symIdx++ >= $symPage ? ' data-extra="1"' : '' ?>>
          <td data-label="Coin"><?= sma_e($sym) ?></td>
          <td data-label="Settled trades"><?= $n ?></td>
          <td data-label="Its own voice">
            <?php if ($below): ?>
              <span class="hint">none &mdash; needs <?= $symMin ?></span>
            <?php else: ?>
              <span style="color:<?= $voice >= 0.5 ? 'var(--up)' : 'var(--muted)' ?>">
                <?= round($voice * 100) ?>%</span>
            <?php endif; ?></td>
          <td data-label="Rules it has moved"><?= $moved > 0
              ? $moved . ' of ' . $symRuleTotal : '<span class="hint">none yet</span>' ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
    </div>
    <?php if ($symTotal > $symPage): ?>
      <div class="plan-acts" style="margin-top:10px">
        <?php // The only bare <button> on the panel: no class, so it inherited
              // the browser's default chrome and looked nothing like the other
              // twenty "show more" controls in here - and came out 21px tall,
              // under the target floor everything else clears through .btn. ?>
        <button type="button" class="btn small gray" id="coinLearnMore"
                data-total="<?= (int)$symTotal ?>" hidden>Show more coins</button>
      </div>
      <script<?= sma_nonce() ?>>
        (function () {
          var t = document.getElementById('coinLearn');
          var b = document.getElementById('coinLearnMore');
          if (!t || !b) { return; }
          var page = parseInt(t.dataset.page, 10) || 10;
          var total = parseInt(b.dataset.total, 10) || 0;
          var shown = page;
          // Hidden here rather than in the markup, so this list is complete
          // for anyone this script never reaches.
          var rows = t.querySelectorAll('tr[data-extra]');
          Array.prototype.forEach.call(rows, function (r) { r.hidden = true; });
          var label = function () {
            var left = total - shown;
            b.textContent = left > page
              ? 'Show ' + page + ' more (' + left + ' left)'
              : 'Show the last ' + left;
          };
          b.hidden = false;
          label();
          b.addEventListener('click', function () {
            var next = Array.prototype.slice.call(t.querySelectorAll('tr[data-extra]'))
              .filter(function (r) { return r.hidden; }).slice(0, page);
            next.forEach(function (r) { r.hidden = false; });
            shown += next.length;
            if (shown >= total) { b.hidden = true; } else { label(); }
          });
        }());
      </script>
    <?php endif; ?>
    <p class="hint" style="margin-top:10px">
      <?= $symTotal ?> coin<?= $symTotal === 1 ? '' : 's' ?> have settled a trade.
      <?= (int)($symRep['coins'] ?? 0) ?> coin<?= (int)($symRep['coins'] ?? 0) === 1 ? '' : 's' ?>
      currently carry their own multipliers
      (<?= (int)($symRep['mults'] ?? 0) ?> in total), from
      <?= (int)($symRep['considered'] ?? 0) ?> considered at the last run
      <?= isset($symRep['at']) ? 'on ' . date('M j H:i', (int)$symRep['at']) : '' ?>.
      Every multiplier a signal used is printed in that signal's own reasons.</p>
  <?php endif; ?>
</div>

<!-- ------------------------------------------------ tuner health -->
<div class="form-card">
  <h2 style="margin-top:0">Self-tuning health</h2>
  <?php if ($tune): ?>
    <p class="hint">
      Last run <?= isset($tune['at']) ? date('M j H:i', (int)$tune['at']) : 'unknown' ?>.
      <?php if (!empty($tune['validated'])): ?>
        Fitted on <?= (int)($tune['train_n'] ?? 0) ?> outcomes and validated on
        <?= (int)($tune['test_n'] ?? 0) ?> held out.
        Out-of-sample expectancy <?= sma_e((string)($tune['test_r_before'] ?? '—')) ?> →
        <?= sma_e((string)($tune['test_r_after'] ?? '—')) ?>.
        <strong style="color:<?= !empty($tune['applied']) ? 'var(--up)' : 'var(--warn)' ?>">
          <?= !empty($tune['applied']) ? 'Kept' : 'Rolled back' ?>
        </strong>
        (<?= (int)($tune['changed'] ?? 0) ?> weights changed).
      <?php else: ?>
        <?= sma_e((string)($tune['reason'] ?? 'ran without a hold-out')) ?> —
        <?= (int)($tune['changed'] ?? 0) ?> weights changed.
      <?php endif; ?>
    </p>
    <p class="hint">Weights are computed relative to each rule's stored baseline, not multiplied into
      their own previous output — otherwise daily and weekly tuning compound, and a rule that drifts
      into a clamp can never come back.</p>
  <?php else: ?>
    <p class="hint">The tuner has not run yet. It runs daily from cron once verified outcomes exist.</p>
  <?php endif; ?>

  <?php
  // "WHY ARE THERE NOT MORE SIGNALS" - THE COMMONEST QUESTION, ANSWERED HERE.
  //
  // It has a dozen possible causes and the operator's only recourse used to be
  // changing a setting and waiting a day. Every refusal is already recorded;
  // this adds them up and names the setting behind the biggest one.
  $supply = ['published' => 0, 'refused' => 0, 'days' => 7, 'reasons' => []];
  try { $supply = \SignalMasterAi\Outcomes::supply(7); } catch (Throwable $e) {}
  // Which dial each refusal answers to. Named rather than linked, because
  // several live on this page and the rest are one hop away under Settings.
  $gateFix = [
      'threshold'      => 'the score threshold, under Scoring above',
      'min_categories' => 'how many categories must agree, under Scoring above',
      'chop_gate'      => 'the chop filter, under Signals &rsaquo; Signal engine thresholds',
      'cooldown'       => 'the stop-out cooldown, under Signals &rsaquo; Signal engine thresholds',
      'target_reach'   => 'target reachability &mdash; the first target must be close enough to be '
                        . 'plausible before the trade is published',
      'cost_floor'     => 'the cost floor &mdash; the move must be worth more than the round trip',
      'extension_gate' => 'the extension filter &mdash; it refuses entries chasing a move that has '
                        . 'already run',
      'mtf_gate'       => 'the higher-timeframe gate &mdash; it raises the bar when the frames above disagree',
      'mtf_conflict'   => 'the higher-timeframe gate &mdash; it raises the bar when the frames above disagree',
      'score_band'     => 'the score-band record gate',
      'late_entry'     => 'the pre-flight late-entry check',
  ];
  $supTotal = $supply['published'] + $supply['refused'];
  ?>
  <h2>Why there are not more signals</h2>
  <?php if ($supTotal < 1): ?>
    <p class="hint">Nothing published and nothing refused in the last <?= (int)$supply['days'] ?>
      days. That is not a gate being strict &mdash; it means the scan is not reaching any coins.
      Check <strong>Scan coverage</strong> on the dashboard and that the background worker is
      running.</p>
  <?php else: ?>
    <p class="hint">Over the last <strong><?= (int)$supply['days'] ?> days</strong> the engine
      published <strong><?= (int)$supply['published'] ?></strong> call<?= $supply['published'] === 1 ? '' : 's' ?>
      and refused <strong><?= (int)$supply['refused'] ?></strong> setup<?= $supply['refused'] === 1 ? '' : 's' ?>
      it had otherwise wanted to take
      <?php if ($supTotal > 0): ?>
        &mdash; <strong><?= round($supply['published'] / $supTotal * 100) ?>%</strong> of candidates
        made it through.
      <?php endif; ?>
      <?php if ($supply['refused'] === 0): ?>
        Nothing is being refused, so a quiet board is the market rather than your settings.
      <?php endif; ?>
    </p>
    <?php if ($supply['reasons']): ?>
      <div class="tbl-scroll">
      <table class="grid stack">
        <tr class="head"><th>What refused it</th><th>Times</th><th>Share</th><th>The dial behind it</th></tr>
        <?php foreach (array_slice($supply['reasons'], 0, 8) as $rr): ?>
          <tr>
            <td data-label="What refused it"><?= sma_e($gateName[$rr['key']] ?? ($rr['key'] ?: 'unrecorded')) ?></td>
            <td data-label="Times"><strong><?= (int)$rr['n'] ?></strong></td>
            <td data-label="Share"><?= $supply['refused'] > 0
                ? round($rr['n'] / $supply['refused'] * 100) . '%' : '&mdash;' ?></td>
            <td data-label="The dial behind it"><?= $gateFix[$rr['key']] ?? 'see the table below' ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
      </div>
      <p class="hint"><strong>Loosen the top row first</strong> &mdash; it is the one holding back
        the most, and the table below this one says whether the setups it refused would actually
        have made money. A gate refusing a great deal while its average R is negative is doing its
        job, however quiet it makes the board.</p>
    <?php endif; ?>
  <?php endif; ?>

  <?php
  // WHAT EACH LETTER IS ACTUALLY WORTH.
  //
  // The site prints a grade on every call, sells on it, and lets a member
  // filter alerts by it - and nothing measured whether the letters rank in
  // the order they claim. See Outcomes::byGrade().
  $gradeRows = [];
  $gradeTrue = null;
  try {
      $gradeRows = \SignalMasterAi\Outcomes::byGrade();
      $gradeTrue = \SignalMasterAi\Outcomes::gradesRankTrue($gradeRows);
  } catch (Throwable $e) {
  }
  ?>
  <h2>What each grade is worth</h2>
  <?php if (!$gradeRows): ?>
    <p class="hint">No settled calls carry a grade yet. This fills itself in as signals close.</p>
  <?php else: ?>
    <p class="hint">Every settled call, grouped by the letter it was published with. A win is a
      trade that <strong>made money</strong> &mdash; not one that reached a label &mdash; so this
      agrees with the public track record. <strong>Expectancy is the column that decides</strong>:
      a grade can win often and still lose, which is why both are here. <strong>Needs</strong> is
      the payoff that grade's own win rate requires to break even, so a payoff below it is a grade
      that costs money however good the win rate looks. Under
      <?= (int)\SignalMasterAi\Outcomes::GRADE_MIN_N ?> settled calls a row is marked thin and
      should not be leaned on.</p>
    <?php if ($gradeTrue === false): ?>
      <p class="hint" style="border-left:3px solid var(--down);padding-left:10px">
        <strong>The grades do not rank in the order they claim.</strong> A lower grade is earning
        more per trade than a higher one, on samples big enough to say so. The letter is a promise
        to your members and to your own alert filters &mdash; a member who set a minimum grade of A
        is being given the worse half of your record. Worth reading beside the score bands below:
        this engine's highest scores have measured its least profitable trades.</p>
    <?php elseif ($gradeTrue === true): ?>
      <p class="hint" style="border-left:3px solid var(--up);padding-left:10px">
        The grades rank in the order they claim &mdash; each letter earns at least as much per
        trade as the one below it, on the rows thick enough to judge.</p>
    <?php endif; ?>
    <div class="tbl-scroll">
    <table class="grid stack">
      <tr class="head"><th>Grade</th><th>Settled</th><th>Win rate</th><th>Lower bound</th>
          <th>Expectancy</th><th>Total R</th><th>Avg win</th><th>Avg loss</th>
          <th>Payoff</th><th>Needs</th><th>Pays?</th></tr>
      <?php foreach ($gradeRows as $g): ?>
        <tr<?= $g['thin'] ? ' class="dim"' : '' ?>>
          <td data-label="Grade"><strong><?= sma_e((string)$g['grade']) ?></strong>
            <?= $g['thin'] ? ' <span class="hint">thin</span>' : '' ?></td>
          <td data-label="Settled"><?= (int)$g['n'] ?></td>
          <td data-label="Win rate"><?= $g['win_rate'] === null ? '&mdash;' : sma_e((string)$g['win_rate']) . '%' ?></td>
          <td data-label="Lower bound"><?= $g['wilson'] === null ? '&mdash;' : sma_e((string)$g['wilson']) . '%' ?></td>
          <td data-label="Expectancy" style="font-weight:500;color:<?= (float)$g['expectancy'] >= 0 ? 'var(--up)' : 'var(--down)' ?>">
            <?= (float)$g['expectancy'] >= 0 ? '+' : '' ?><?= sma_e((string)$g['expectancy']) ?>R</td>
          <td data-label="Total R"><?= (float)$g['total_r'] >= 0 ? '+' : '' ?><?= sma_e((string)$g['total_r']) ?>R</td>
          <td data-label="Avg win"><?= $g['avg_win'] === null ? '&mdash;' : '+' . sma_e((string)$g['avg_win']) . 'R' ?></td>
          <td data-label="Avg loss"><?= $g['avg_loss'] === null ? '&mdash;' : sma_e((string)$g['avg_loss']) . 'R' ?></td>
          <td data-label="Payoff"><?= $g['payoff'] === null ? '&mdash;' : sma_e((string)$g['payoff']) ?></td>
          <td data-label="Needs"><?= $g['need_payoff'] === null ? '&mdash;' : sma_e((string)$g['need_payoff']) ?></td>
          <td data-label="Pays?"><?= $g['pays'] === null ? '&mdash;'
              : ($g['pays'] ? '<span class="badge on">yes</span>' : '<span class="badge off">no</span>') ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
    </div>
  <?php endif; ?>

  <?php
  // WHY THE TRACK RECORD SHOWS THE NUMBER IT SHOWS.
  //
  // Reported: 50 verified signals became 13 after a change in this panel, and
  // putting the setting back left it at 13. Nothing on the site could explain
  // either half - the count is the end of a pipeline with four ways to shrink
  // and no way to see which one did it. Three are filters and reversible; the
  // fourth deletes rows and is not. See TrackRecord::census().
  $cen = [];
  try { $cen = \SignalMasterAi\TrackRecord::census(); } catch (Throwable $e) {}
  // The same record over 7, 30 and 90 days beside the lifetime figure. One
  // number cannot tell an engine that was right for six months and has been
  // wrong for three weeks from one that is fine - and those are the two cases
  // an operator most needs to tell apart.
  $winStats = [];
  foreach ([7 => 'Last 7 days', 30 => 'Last 30 days', 90 => 'Last 90 days',
            365 => 'Last 12 months', 0 => 'All time'] as $wd => $wl) {
      try { $winStats[$wl] = \SignalMasterAi\TrackRecord::stats($wd ?: null); } catch (Throwable $e) {}
  }
  ?>
  <?php if ($winStats): ?>
  <h2>Recent form</h2>
  <p class="hint">Settled calls only, counted on the day each one was DECIDED rather than the day
    it was made &mdash; otherwise the short windows would be permanently missing their slowest
    trades, which are disproportionately the losers. Same population as the published track
    record, so these reconcile with the public page.</p>
  <div class="tbl-scroll">
  <table class="grid stack">
    <tr class="head"><th>Period</th><th>Settled</th><th>Wins</th><th>Win rate</th>
        <th>Avg R</th><th>Total R</th></tr>
    <?php foreach ($winStats as $wl => $ws): ?>
      <tr>
        <td data-label="Period"><strong><?= sma_e((string)$wl) ?></strong></td>
        <td data-label="Settled"><?= (int)$ws['total'] ?></td>
        <td data-label="Wins"><?= (int)$ws['wins'] ?></td>
        <td data-label="Win rate"><?= $ws['winRate'] === null ? '&mdash;' : sma_e((string)$ws['winRate']) . '%' ?></td>
        <td data-label="Avg R" style="font-weight:500;color:<?= ($ws['avgR'] ?? 0) >= 0 ? 'var(--up)' : 'var(--down)' ?>">
          <?= $ws['avgR'] === null ? '&mdash;'
              : (($ws['avgR'] >= 0 ? '+' : '') . sma_e((string)$ws['avgR']) . 'R') ?></td>
        <td data-label="Total R"><?= isset($ws['sumR'])
              ? (($ws['sumR'] >= 0 ? '+' : '') . sma_e((string)round((float)$ws['sumR'], 2)) . 'R') : '&mdash;' ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
  </div>
  <p class="hint">A window with nothing in it is an empty run, not a bad one: a call has to reach
    its stop, a target or its time stop before it counts, and on the slower frames that takes
    longer than seven days.</p>
  <?php endif; ?>
  <?php if ($cen): ?>
  <h2>Why the track record shows <?= (int)$cen['published'] ?></h2>
  <div class="tbl-scroll">
  <table class="grid stack">
    <tr class="head"><th>Settled signals</th><th>Count</th><th>What it means</th></tr>
    <tr><td data-label="Settled"><strong>Stored and settled</strong></td>
        <td data-label="Count"><strong><?= (int)$cen['settled'] ?></strong></td>
        <td data-label="Means">Every finished call in the database. <strong>If this number itself
          dropped, rows were deleted</strong> &mdash; by the retention window below, by Delete
          signals, or by a symbol purge &mdash; and no setting brings them back.</td></tr>
    <tr><td data-label="Hidden">Hidden: coin off the track surface</td>
        <td data-label="Count"><?= (int)$cen['hidden_symbol'] ?></td>
        <td data-label="Means">Coins disabled, or hidden from the track record under
          <a href="symbols.php" style="color:var(--accent)">Coins</a>. Reversible.</td></tr>
    <tr><td data-label="Hidden">Hidden: below the publish grade floor</td>
        <td data-label="Count"><?= (int)$cen['hidden_grade'] ?></td>
        <td data-label="Means">The floor under
          <a href="settings.php#publish" style="color:var(--accent)">Signals &rsaquo; publish</a>.
          Reversible.</td></tr>
    <tr><td data-label="Hidden">Hidden: timeframe switched off</td>
        <td data-label="Count"><?= (int)$cen['hidden_tf'] ?></td>
        <td data-label="Means">Frames turned off site-wide under
          <a href="settings.php#timeframes" style="color:var(--accent)">Market &rsaquo;
          timeframes</a>. Reversible.</td></tr>
    <tr><td data-label="Published"><strong>Published on the track record</strong></td>
        <td data-label="Count"><strong><?= (int)$cen['published'] ?></strong></td>
        <td data-label="Means">What a visitor sees. The three lines above overlap &mdash; one call
          can be hidden by two of them &mdash; so they do not subtract to this; it is counted
          separately.</td></tr>
  </table>
  </div>
  <p class="hint">
    <?php if ((int)$cen['retention_days'] > 0): ?>
      Settled signals are kept <strong><?= (int)$cen['retention_days'] ?> days</strong>.
      <?php if ((int)$cen['prunable'] > 0): ?>
        <strong style="color:var(--down)">The next background run will delete
        <?= (int)$cen['prunable'] ?> of them permanently.</strong>
        Raise the window under <a href="settings.php" style="color:var(--accent)">Security &rsaquo;
        retention</a> before it runs if you want them kept.
      <?php else: ?>
        Nothing is old enough to be deleted at that setting.
      <?php endif; ?>
      <strong>Lowering that number deletes record you cannot get back</strong>, and the deletion
      happens on the next background run rather than when you save &mdash; which is how a track
      record shrinks hours after the change that caused it, and why putting the setting back does
      not restore it.
    <?php else: ?>
      Settled signals are kept forever, so nothing is being deleted by retention.
    <?php endif; ?>
    <?php if ((int)$cen['oldest_closed'] > 0): ?>
      Oldest settled call still stored: <?= sma_e(gmdate('j M Y', (int)$cen['oldest_closed'])) ?>.
    <?php endif; ?>
    Every setting change is recorded with its before and after value in
    <a href="audit.php" style="color:var(--accent)">the audit log</a>.
  </p>
  <?php endif; ?>

  <?php
  // THE SAME QUESTION, ASKED OF THE OTHER THING A SIGNAL IS.
  //
  // The grade says how good the read was. The type says what the plan paid for
  // the risk, and an operator choosing which types to publish is deciding with
  // this table or with a hunch. Untyped calls - everything stored before the
  // types existed - are left out rather than filed under a label they never
  // carried, so this fills in from here.
  $typeRows = [];
  try { $typeRows = \SignalMasterAi\Outcomes::byType(); } catch (Throwable $e) {}
  ?>
  <h2>What each signal type is worth</h2>
  <?php if (!$typeRows): ?>
    <p class="hint">No settled call carries a type yet. Types are decided when a signal is made, so
      this fills itself in as calls published from this version close.</p>
  <?php else: ?>
    <p class="hint">Every plan carries targets at one, two and three times its risk; the type is
      which of them the setup was expected to reach, chosen on expected value from how often that
      pair has covered that ground inside the deadline. A 1:1 is not a worse signal &mdash; it is a
      shorter trade with a nearer target, and it should win more often for that reason. What
      matters is <strong>Pays?</strong>: whether the payoff clears what that type's own win rate
      needs to break even. Which types this site publishes is set under
      <a href="settings.php#signals">Signals</a>.</p>
    <div class="tbl-scroll">
    <table class="grid stack">
      <tr class="head"><th>Type</th><th>Settled</th><th>Win rate</th><th>Lower bound</th>
          <th>Expectancy</th><th>Total R</th><th>Avg win</th><th>Avg loss</th>
          <th>Payoff</th><th>Needs</th><th>Pays?</th></tr>
      <?php foreach ($typeRows as $g): ?>
        <tr<?= $g['thin'] ? ' class="dim"' : '' ?>>
          <td data-label="Type"><strong><?= sma_e((string)$g['grade']) ?></strong>
            <?= $g['thin'] ? ' <span class="hint">thin</span>' : '' ?></td>
          <td data-label="Settled"><?= (int)$g['n'] ?></td>
          <td data-label="Win rate"><?= $g['win_rate'] === null ? '&mdash;' : sma_e((string)$g['win_rate']) . '%' ?></td>
          <td data-label="Lower bound"><?= $g['wilson'] === null ? '&mdash;' : sma_e((string)$g['wilson']) . '%' ?></td>
          <td data-label="Expectancy" style="font-weight:500;color:<?= (float)$g['expectancy'] >= 0 ? 'var(--up)' : 'var(--down)' ?>">
            <?= (float)$g['expectancy'] >= 0 ? '+' : '' ?><?= sma_e((string)$g['expectancy']) ?>R</td>
          <td data-label="Total R"><?= (float)$g['total_r'] >= 0 ? '+' : '' ?><?= sma_e((string)$g['total_r']) ?>R</td>
          <td data-label="Avg win"><?= $g['avg_win'] === null ? '&mdash;' : '+' . sma_e((string)$g['avg_win']) . 'R' ?></td>
          <td data-label="Avg loss"><?= $g['avg_loss'] === null ? '&mdash;' : sma_e((string)$g['avg_loss']) . 'R' ?></td>
          <td data-label="Payoff"><?= $g['payoff'] === null ? '&mdash;' : sma_e((string)$g['payoff']) ?></td>
          <td data-label="Needs"><?= $g['need_payoff'] === null ? '&mdash;' : sma_e((string)$g['need_payoff']) ?></td>
          <td data-label="Pays?"><?= $g['pays'] === null ? '&mdash;'
              : ($g['pays'] ? '<span class="badge on">yes</span>' : '<span class="badge off">no</span>') ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
    </div>
  <?php endif; ?>

  <?php if (!empty($calib['buckets'])): ?>
    <h2>Confidence calibration</h2>
    <p class="hint">Measured win rate per score band, with the Wilson lower bound — the figure the
      sample actually supports. A band needs
      <?= (int)Database::setting('calib_min_samples', '30') ?> results before it is published as
      "measured".</p>
    <div class="tbl-scroll">
    <table class="grid">
      <tr><th>Score band</th><th>Samples</th><th>Raw win rate</th><th>Lower bound (shown)</th>
          <th>Expectancy</th></tr>
      <?php foreach ($calib['buckets'] as $b): ?>
        <tr>
          <td><?= sma_e((string)$b['lo']) ?> – <?= $b['hi'] >= 999 ? '∞' : sma_e((string)$b['hi']) ?></td>
          <td><?= (int)$b['n'] ?></td>
          <td><?= $b['winrate'] !== null ? sma_e((string)$b['winrate']) . '%' : '—' ?></td>
          <td style="font-weight:500"><?= isset($b['wilson']) && $b['wilson'] !== null ? sma_e((string)$b['wilson']) . '%' : '—' ?></td>
          <td><?= isset($b['avg_r']) && $b['avg_r'] !== null
                ? (($b['avg_r'] >= 0 ? '+' : '') . sma_e((string)$b['avg_r']) . 'R') : '—' ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
    </div>

    <?php
    // DOES A HIGHER SCORE MEAN A BETTER TRADE HERE?
    //
    // The whole premise of a score threshold is that it does. If it does not,
    // raising the threshold filters towards the worse end of the record, and
    // the operator sees a "stricter" configuration perform worse and has no
    // way to know why. Said out loud rather than left for somebody to spot by
    // reading five rows of a table.
    // A lower bar than the GATE uses on purpose. This paragraph only points at
    // a shape in the table and prints the sample size beside it; the gate,
    // which actually refuses trades, still needs score_band_min_n. Hiding the
    // observation until the gate could act would keep it from the operator
    // exactly while they are deciding whether the record is worth acting on.
    $bandsWithData = array_values(array_filter($calib['buckets'],
        fn($b) => (int)$b['n'] >= 10 && ($b['avg_r'] ?? null) !== null));
    $worstTop = null;
    if (count($bandsWithData) >= 2) {
        $top = $bandsWithData[count($bandsWithData) - 1];
        $best = $bandsWithData[0];
        foreach ($bandsWithData as $b) {
            if (($b['avg_r'] ?? -99) > ($best['avg_r'] ?? -99)) {
                $best = $b;
            }
        }
        if (($top['avg_r'] ?? 0) < ($best['avg_r'] ?? 0) - 0.05) {
            $worstTop = [$top, $best];
        }
    }
    ?>
    <?php if ($worstTop): [$top, $best] = $worstTop; ?>
      <p class="hint" style="border-left:3px solid var(--accent);padding-left:10px">
        <strong>A higher score is not buying a better trade here.</strong>
        The strongest band (<?= sma_e((string)$top['lo']) ?>+) is running
        <?= ($top['avg_r'] >= 0 ? '+' : '') . sma_e((string)$top['avg_r']) ?>R over
        <?= (int)$top['n'] ?> settled signals, while
        <?= sma_e((string)$best['lo']) ?>–<?= $best['hi'] >= 999 ? '∞' : sma_e((string)$best['hi']) ?>
        is running <?= ($best['avg_r'] >= 0 ? '+' : '') . sma_e((string)$best['avg_r']) ?>R over
        <?= (int)$best['n'] ?>.
        Raising the score threshold pushes the mix towards the first of those, which is why a
        tighter preset can measure <em>worse</em> than a looser one. The
        <a href="settings.php#signals" style="color:var(--accent)">score band gate</a> can stand
        that band down instead of publishing it as your strongest call &mdash; it is off until you
        turn it on, and every refusal is recorded as a shadow so you can see whether it was right.
      </p>
    <?php endif; ?>
  <?php endif; ?>

  <?php if (!empty($gradeCalib['buckets'])): ?>
    <h2>Grade calibration</h2>
    <p class="hint">Score gets a calibration above; the letter grade board rows are sorted by never
      had one until now. This is the real win rate behind A+/A/B/C, measured the same way &mdash; with
      the Wilson lower bound, so a thin grade cannot boast. A grade needs
      <?= (int)($gradeCalib['min_samples'] ?? 30) ?> settled signals before it counts toward an
      inversion warning below.</p>
    <div class="tbl-scroll">
    <table class="grid">
      <tr><th>Grade</th><th>Settled</th><th>Raw win rate</th><th>Lower bound</th><th>Avg R</th></tr>
      <?php foreach (['A+', 'A', 'B', 'C'] as $g): $b = $gradeCalib['buckets'][$g] ?? null; if (!$b) { continue; } ?>
        <tr>
          <td><strong><?= sma_e($g) ?></strong></td>
          <td><?= (int)$b['n'] ?></td>
          <td><?= $b['winrate'] !== null ? sma_e((string)$b['winrate']) . '%' : '&mdash;' ?></td>
          <td style="font-weight:500"><?= $b['wilson'] !== null ? sma_e((string)$b['wilson']) . '%' : '&mdash;' ?></td>
          <td style="<?= $b['avg_r'] !== null ? 'color:' . ($b['avg_r'] >= 0 ? 'var(--up)' : 'var(--down)') : '' ?>">
            <?= $b['avg_r'] !== null ? (($b['avg_r'] >= 0 ? '+' : '') . sma_e((string)$b['avg_r']) . 'R') : '&mdash;' ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
    </div>
    <?php if (!empty($gradeCalib['inversions'])): ?>
      <?php foreach ($gradeCalib['inversions'] as $inv): ?>
        <p class="okmsg" style="background:var(--bg-warn);border:1px solid #D8B26A66;color:var(--warn-text);
             padding:10px 12px;border-radius:8px;font-size:13px;margin-top:12px">
          <strong><?= sma_e($inv['worse']) ?> is measuring ahead of <?= sma_e($inv['better']) ?>.</strong>
          <?= sma_e($inv['better']) ?> setups have a <?= sma_e((string)$inv['better_wilson']) ?>%
          lower-bound win rate against <?= sma_e((string)$inv['worse_wilson']) ?>% for
          <?= sma_e($inv['worse']) ?>, both on samples large enough to trust. A grade is only useful as
          a sort key while a higher one really does mean a better trade &mdash; worth a look at what
          earns <?= sma_e($inv['better']) ?> before members keep being pointed at it first.
        </p>
      <?php endforeach; ?>
    <?php else: ?>
      <p class="hint">No inversion: every grade with enough evidence is measuring at least as well as
        the grade below it.</p>
    <?php endif; ?>
  <?php endif; ?>

  <?php if ($chopEv !== null && (($chopEv['a_plus_in_chop']['n'] ?? 0) > 0 || ($chopEv['a_plus_not_in_chop']['n'] ?? 0) > 0)): ?>
    <h2>The A+ chop exemption</h2>
    <p class="hint">A+ setups are let through the chop filter by default (toggle under
      <a href="#thresholds">Scoring &rsaquo; Market filters</a>) on the reasoning that a setup strong
      enough to earn the top grade should survive a choppy read. That reasoning was never checked
      against this install's own results until now. Only A+ ever publishes in chop, so the middle
      column below is the one this exemption is responsible for; the right column is what the shadow
      book says everyone else stood down there would have done instead.</p>
    <div class="tbl-scroll">
    <table class="grid">
      <tr><th></th><th>A+ published in chop</th><th>A+ published outside chop</th>
          <th>Other grades, blocked by chop</th></tr>
      <?php
      $aIn = $chopEv['a_plus_in_chop'];
      $aOut = $chopEv['a_plus_not_in_chop'];
      $blocked = $chopEv['other_grades_blocked'];
      $blockedWinrate = $blocked && $blocked['n'] > 0 ? round($blocked['wins'] / $blocked['n'] * 100, 1) : null;
      ?>
      <tr>
        <td>Settled</td>
        <td><?= (int)$aIn['n'] ?></td>
        <td><?= (int)$aOut['n'] ?></td>
        <td><?= $blocked ? (int)$blocked['n'] : 0 ?></td>
      </tr>
      <tr>
        <td>Win rate</td>
        <td><?= $aIn['winrate'] !== null ? sma_e((string)$aIn['winrate']) . '%' : '&mdash;' ?></td>
        <td><?= $aOut['winrate'] !== null ? sma_e((string)$aOut['winrate']) . '%' : '&mdash;' ?></td>
        <td><?= $blockedWinrate !== null ? sma_e((string)$blockedWinrate) . '%' : '&mdash;' ?></td>
      </tr>
      <tr>
        <td>Avg R</td>
        <td style="<?= $aIn['avg_r'] !== null ? 'color:' . ($aIn['avg_r'] >= 0 ? 'var(--up)' : 'var(--down)') : '' ?>">
          <?= $aIn['avg_r'] !== null ? (($aIn['avg_r'] >= 0 ? '+' : '') . sma_e((string)$aIn['avg_r']) . 'R') : '&mdash;' ?></td>
        <td style="<?= $aOut['avg_r'] !== null ? 'color:' . ($aOut['avg_r'] >= 0 ? 'var(--up)' : 'var(--down)') : '' ?>">
          <?= $aOut['avg_r'] !== null ? (($aOut['avg_r'] >= 0 ? '+' : '') . sma_e((string)$aOut['avg_r']) . 'R') : '&mdash;' ?></td>
        <td style="<?= $blocked && $blocked['avg_r'] !== null ? 'color:' . ($blocked['avg_r'] >= 0 ? 'var(--up)' : 'var(--down)') : '' ?>">
          <?= $blocked && $blocked['avg_r'] !== null ? (($blocked['avg_r'] >= 0 ? '+' : '') . sma_e((string)$blocked['avg_r']) . 'R') : '&mdash;' ?></td>
      </tr>
    </table>
    </div>
    <p class="hint" style="margin-top:10px">
      <?php if ($aIn['n'] >= 20 && $aOut['n'] >= 20 && $aIn['avg_r'] !== null && $aOut['avg_r'] !== null): ?>
        <?php if ($aIn['avg_r'] < $aOut['avg_r'] - 0.1): ?>
          <strong>The exemption looks costly on this install's own record</strong> &mdash; A+ setups
          published in chop are running <?= sma_e((string)$aIn['avg_r']) ?>R against
          <?= sma_e((string)$aOut['avg_r']) ?>R outside it. Worth turning the exemption off and letting
          the chop gate apply to every grade.
        <?php else: ?>
          A+ setups published in chop are holding up against A+ setups published outside it
          (<?= sma_e((string)$aIn['avg_r']) ?>R vs <?= sma_e((string)$aOut['avg_r']) ?>R) &mdash; the
          exemption's reasoning is supported by this install's own record so far.
        <?php endif; ?>
      <?php else: ?>
        Not enough of either bucket has settled yet to judge the exemption &mdash; each needs at least
        20 before this evidence means much.
      <?php endif; ?>
    </p>
  <?php endif; ?>

  <?php if (!empty($buckets['hour'])): ?>
    <?php
    $hours = array_filter($buckets['hour'], fn($h) => ($h['n'] ?? 0) >= 5);
    uasort($hours, fn($a, $b) => ($b['avg_r'] ?? -9) <=> ($a['avg_r'] ?? -9));
    ?>
    <?php
    // The same hours, grouped the way a trader names them. The public track
    // record publishes exactly this table, from the same Sessions class, so
    // what an operator reads here is what a visitor reads there.
    $sessAgg = [];
    foreach (\SignalMasterAi\Sessions::BLOCKS as $sk => $sb) {
        $sessAgg[$sk] = ['n' => 0, 'w' => 0.0, 'r' => 0.0];
    }
    foreach ($buckets['hour'] as $hh => $hd) {
        $k = \SignalMasterAi\Sessions::forHour((int)$hh);
        $n = (int)($hd['n'] ?? 0);
        if ($n < 1) { continue; }
        $sessAgg[$k]['n'] += $n;
        $sessAgg[$k]['w'] += $n * (float)($hd['winrate'] ?? 0);
        $sessAgg[$k]['r'] += $n * (float)($hd['avg_r'] ?? 0);
    }
    ?>
    <h2>Expectancy by trading session (UTC)</h2>
    <p class="hint">The hour buckets below, grouped into the five blocks the public track record
      publishes. Non-overlapping, so the counts add up. The session filter under
      <a href="settings.php#signals">Signals</a> acts on the <em>hour</em>, not the block — this
      table is where you see whether a whole session is worth standing down.</p>
    <?php // Hours under the name rather than in a column of their own, and
          // who-is-trading on the row's tooltip rather than in the cell. Five
          // columns - or four with a sentence in the first - pushed Avg R off
          // a 375px screen, which is the one number this table exists to
          // show. ?>
    <div class="tbl-scroll">
    <table class="grid">
      <tr><th>Session</th><th>Signals</th><th>Win rate</th><th>Avg R</th></tr>
      <?php foreach ($sessAgg as $sk => $sv): $sn = (int)$sv['n']; ?>
        <tr title="<?= sma_e(\SignalMasterAi\Sessions::blurb($sk)) ?>">
            <td><strong><?= sma_e(\SignalMasterAi\Sessions::label($sk)) ?></strong>
              <br><span class="hint"><?= sma_e(\SignalMasterAi\Sessions::range($sk)) ?></span></td>
            <td><?= $sn ?></td>
            <td><?= $sn ? round($sv['w'] / $sn, 1) . '%' : '&mdash;' ?></td>
            <td style="font-weight:500<?= $sn ? ';color:' . ($sv['r'] / $sn > 0 ? 'var(--up)' : 'var(--down)') : '' ?>">
              <?= $sn ? sprintf('%+.3f', $sv['r'] / $sn) : '&mdash;' ?></td></tr>
      <?php endforeach; ?>
    </table>
    </div>

    <?php if ($hours): ?>
      <h2>Expectancy by hour (UTC)</h2>
      <p class="hint">The raw buckets behind the table above, best six first. The optional
        <strong>session filter</strong> reads these directly: with it on, an hour whose measured
        expectancy is below the floor stands its setups down. With it off — the default — nothing
        is filtered and this is shown so the pattern is visible rather than assumed.</p>
      <div class="tbl-scroll">
      <table class="grid">
        <tr><th>Hour</th><th>Signals</th><th>Win rate</th><th>Avg R</th></tr>
        <?php foreach (array_slice($hours, 0, 6, true) as $h => $d): ?>
          <tr><td><?= sprintf('%02d:00', (int)$h) ?></td><td><?= (int)$d['n'] ?></td>
              <td><?= $d['winrate'] !== null ? sma_e((string)$d['winrate']) . '%' : '—' ?></td>
              <td style="font-weight:500;color:<?= ($d['avg_r'] ?? 0) > 0 ? 'var(--up)' : 'var(--down)' ?>">
                <?= sma_e((string)($d['avg_r'] ?? '—')) ?></td></tr>
        <?php endforeach; ?>
      </table>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>
<?php admin_footer(); ?>
