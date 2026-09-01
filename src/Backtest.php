<?php
declare(strict_types=1);

namespace SignalMasterAi;

/**
 * Historical backtester: replays the candle history already stored on this
 * server through the signal engine (dry mode - pure chart rules, nothing
 * stored) and settles every simulated signal with the same conservative
 * walker the live verifier uses (SL-first, TP1, break-even, TP2, time stop).
 *
 * Purpose: bootstrap the self-learner. Live outcomes take weeks to
 * accumulate; a backtest produces hundreds of verified outcomes per rule in
 * minutes, so weights and per-timeframe multipliers can be calibrated on
 * day one. Educational statistics only - past performance is not a promise.
 */
class Backtest
{
    private const WARMUP = 260;   // bars before the first simulated signal (EMA200 + Ichimoku displacement)

    /**
     * Backtest one pair/timeframe. Returns
     * ['signals','confirmed','invalid','expired','winrate','avg_r','stats'].
     * $stats: [rule_key => ['fired','wins','r_sum']] for votes aligned with
     * each simulated verdict.
     */
    public static function run(string $symbol, string $tf, ?MarketData $md = null, array $overrides = []): array
    {
        if ($md !== null) {
            try {
                $md->candles($symbol, $tf);   // top up the stored history first
            } catch (\Throwable $e) {
                // offline - backtest whatever history the DB already has
            }
        }
        // The MOST RECENT 1000 bars, not the first 1000 ever stored.
        //
        // Retention keeps 1500 bars per series, so as soon as an install has
        // been collecting for a while, an oldest-first cap replays a window
        // that ends 500 bars before the present and never advances past it -
        // three weeks behind on 1h, over a year on 1d. Everything downstream
        // is fitted on that window: the level optimiser, the self-tuner's
        // validation, and the "how would this have behaved" answer members
        // are shown. Newest-first, then reversed, so the replay still runs
        // oldest to newest.
        $stmt = Database::pdo()->prepare(
            'SELECT open_time, open, high, low, close, volume FROM candles
             WHERE symbol = ? AND tf = ? ORDER BY open_time DESC LIMIT 1000'
        );
        $stmt->execute([$symbol, $tf]);
        $candles = array_map(static fn($r) => [
            't' => (int)$r['open_time'],
            'o' => (float)$r['open'],
            'h' => (float)$r['high'],
            'l' => (float)$r['low'],
            'c' => (float)$r['close'],
            'v' => (float)$r['volume'],
        ], $stmt->fetchAll());
        $candles = array_reverse($candles);

        $n = count($candles);
        // How much history there was, and whether it was enough to simulate
        // anything at all. Without these, "no result" had one shape and two
        // completely different meanings - a server with nothing stored, and a
        // coin the engine simply never found a setup on - and the page could
        // only guess which, so it always blamed the history.
        // ONE SHAPE, BOTH WAYS OUT.
        //
        // This branch was missing keys the full return promises - wins above
        // all - and every caller that sums a run across symbols reads them
        // unconditionally. A coin with too little history to simulate made
        // runMany() and compare() warn on "Undefined array key wins" and put
        // those warnings in front of cron's JSON body, which is a parse error
        // for anything reading it over HTTP. The arithmetic survived only
        // because null coerces to 0; the shape did not. A function with two
        // exits owes both of them the same keys.
        $empty = ['signals' => 0, 'confirmed' => 0, 'invalid' => 0, 'expired' => 0,
                  'wins' => 0, 'winrate' => null, 'avg_r' => null, 'bars' => $n,
                  'enough_history' => false, 'stats' => [], 'trades' => [],
                  'excursion' => [], 'overrides_unused' => []];
        if ($n < self::WARMUP + 30) {
            return $empty;
        }

        $tfSec = MarketData::TF_SECONDS[$tf] ?? 3600;
        $timeStopBars = max(1, (int)($overrides['time_stop_bars'] ?? Database::setting('time_stop_bars', '24')));
        // THE REPLAY'S OWN COOLDOWN, WHICH IGNORED THE CONFIGURATION UNDER TEST.
        //
        // This gate lives here rather than in the engine, and it read the LIVE
        // setting - so a replay of Selective (6 bars) and one of Active (2)
        // both applied whatever the operator happened to be running. The
        // cooldown is one of the few settings that visibly separates the
        // presets, and it was the same in all three measurements.
        //
        // time_stop_bars on the line above already honours an override; this
        // one simply never learned to.
        $cooldownBars = max(0, min(100, (int)($overrides['settings']['cooldown_bars']
            ?? $overrides['cooldown_bars']
            ?? Database::setting('cooldown_bars', '4'))));

        // The dry engine now gets a MarketData source.
        //
        // Without one, `mtf_confluence` and `btc_regime` - the higher-timeframe
        // check and the Bitcoin regime filter, two of the highest-weighted
        // rules in the base - could never fire in a backtest. They were never
        // calibrated, and every backtest measured a DIFFERENT engine from the
        // one running live. Each simulated bar pins the engine's clock via
        // asOf(), so those lookups read history as it stood at that moment
        // instead of seeing the future.
        $engine = (new SignalEngine())->dry();
        if ($md !== null && Database::setting('backtest_use_mtf', '1') === '1') {
            $engine->withMarketData($md);
        }
        // Candidate stop/target multipliers travel with the engine instance
        // rather than through the settings table, so a grid search can never
        // leak a half-tested combination into the live configuration.
        if (isset($overrides['levels']) && is_array($overrides['levels'])) {
            $engine->withLevels($overrides['levels']);
        }
        if (isset($overrides['settings']) && is_array($overrides['settings'])) {
            $engine->withSettingOverrides($overrides['settings']);
        }
        // THE EXIT POLICY TRAVELS SEPARATELY, BECAUSE IT IS NOT THE ENGINE'S.
        //
        // The engine decides whether to publish; the settler decides what the
        // trade turned out to be worth, and tp1_partial_pct belongs to the
        // second. Handed to the engine it was silently dropped - so a replay of
        // Selective, whose entire point is taking the whole position at target
        // 1, settled under whatever scale-out the operator was running and
        // reported the answer as Selective's.
        $exitPolicy = null;
        $ovs = $overrides['settings'] ?? [];
        if (isset($ovs['tp1_partial_pct']) || isset($ovs['tp2_partial_pct'])) {
            $exitPolicy = [
                'tp1' => isset($ovs['tp1_partial_pct']) ? (float)$ovs['tp1_partial_pct'] : null,
                'tp2' => isset($ovs['tp2_partial_pct']) ? (float)$ovs['tp2_partial_pct'] : null,
            ];
        }

        // Today's live spread for this symbol, used as the best available
        // stand-in for what it was at each simulated moment in the replay -
        // no historical order-book series exists to ask instead. Better than
        // the alternative every trade on every symbol used before this: one
        // flat fee-only number, identical whether the pair was BTCUSDT or an
        // illiquid microcap. One request per backtest run, not per bar - see
        // Outcomes::walk()'s spreadPct doc for why this matters to tuning.
        $spreadPct = null;
        if ($md !== null) {
            try {
                $spreadPct = $md->spreadPct($symbol);
            } catch (\Throwable $e) {
                // no live quote available - fall back to fees alone, as before
            }
        }

        $prev = null;
        $lastStopDir = null;
        $lastStopAt = 0;
        $stats = [];
        $trades = [];
        $signals = 0;
        $confirmed = 0;   // reached a target label
        $moneyWins = 0;   // ended in profit - the win rate reported below
        $invalid = 0;
        $expired = 0;
        $rSum = 0.0;
        $rCount = 0;
        $exc = [
            'win'  => ['n' => 0, 'mae' => 0.0, 'mfe' => 0.0, 'bars' => 0],
            'loss' => ['n' => 0, 'mae' => 0.0, 'mfe' => 0.0, 'bars' => 0],
            'flat' => ['n' => 0, 'mae' => 0.0, 'mfe' => 0.0, 'bars' => 0],
        ];

        for ($i = self::WARMUP; $i < $n - 1; $i++) {
            $engine->setPrevVerdict($prev);
            // Freeze "now" at this bar's close: candle $i has just closed, so
            // this is the earliest instant its own signal genuinely fires -
            // any higher-timeframe or BTC lookup the engine makes is served
            // as of that instant.
            //
            // This used to sit one millisecond BEFORE the close. closedOnly()
            // pops the slice's last candle when `candle_close_time >
            // nowMs()` - and candle $i's close time IS this value, so with
            // the millisecond shift that read `X > X - 1`, true on every
            // single iteration. The last candle in the slice handed to
            // analyse() below - candle $i, the very bar this iteration is
            // about - was therefore popped as "still forming" every single
            // time: every simulated decision and outcome walk in every
            // backtest was silently evaluated one candle stale, which feeds
            // straight into nightly weight tuning and every Adapt proposal.
            $engine->asOf((int)$candles[$i]['t'] + $tfSec * 1000);
            try {
                $r = $engine->analyse($symbol, $tf, array_slice($candles, 0, $i + 1));
            } catch (\Throwable $e) {
                continue;
            }
            $verdict = $r['signal'];
            $flipped = $verdict !== $prev && in_array($verdict, ['BUY', 'SELL'], true);
            $prev = $verdict;
            if (!$flipped || !is_array($r['levels'] ?? null)) {
                continue;
            }
            // Signal fires at the close of candle $i.
            $createdAt = (int)($candles[$i]['t'] / 1000) + $tfSec;
            // Cooldown emulation: same-direction re-entry right after a stop-out
            // is skipped, exactly like the live engine.
            if ($cooldownBars > 0 && $lastStopDir === $verdict
                && $createdAt - $lastStopAt <= $cooldownBars * $tfSec) {
                continue;
            }
            $future = [];
            for ($j = $i + 1; $j < $n; $j++) {
                $future[] = ['high' => $candles[$j]['h'], 'low' => $candles[$j]['l'],
                             'close' => $candles[$j]['c'], 'open_time' => $candles[$j]['t']];
            }
            // Honour the deadline this simulated signal published, exactly as
            // the live verifier does - otherwise the backtest measures a
            // different trade from the one the engine describes.
            $bars = max(1, (int)($r['levels']['expires_bars'] ?? $timeStopBars));
            // The exit policy under test, not the one the operator is running.
            // Without this a replay of Selective - whose whole point is taking
            // the entire position at target 1 - settled under the live 50/30
            // scale-out and reported the result as Selective's.
            $res = Outcomes::walk($verdict === 'BUY', $r['levels'], $createdAt, $tfSec, $bars,
                $future, null, $exitPolicy, $spreadPct);
            if ($res === null) {
                continue;   // ran off the end of history unresolved
            }
            [$outcome, , $closedAt, $rMult] = $res;
            $ex = $res[4] ?? [];
            // Excursions summarised over the run. A strategy whose winners
            // routinely sit at -0.9R first is one stop-width away from being a
            // losing strategy, and a win rate cannot say that. Split by
            // outcome because the interesting numbers are how much heat the
            // winners took and how close the losers came to working.
            // Bucketed on money, so "the heat a winner took" is the heat
            // taken by a trade that actually paid.
            $bucket = $rMult > 0 ? 'win' : ($outcome === 'expired' ? 'flat' : 'loss');
            if (isset($ex['mae_r'], $ex['mfe_r'])) {
                $exc[$bucket]['n']++;
                $exc[$bucket]['mae'] += (float)$ex['mae_r'];
                $exc[$bucket]['mfe'] += (float)$ex['mfe_r'];
                $exc[$bucket]['bars'] += (int)($ex['bars_held'] ?? 0);
            }
            // Per-trade record, so a caller can ask questions the summary
            // cannot answer - long versus short being the first of them. Cheap
            // to build and ignored by every caller that does not want it.
            $trades[] = [
                'side' => $verdict, 'outcome' => $outcome, 'r' => $rMult,
                // THE SCORE THE CALL WAS MADE ON.
                //
                // Every threshold in the product is a line drawn on this
                // number, and the replay recorded the rules that voted, the
                // levels, the ATR and the outcome - everything except the one
                // figure the decision was actually taken on. So "does a higher
                // score make more money" could not be asked of a replay at
                // all, which is the question a threshold exists to answer.
                'score' => (float)($r['score'] ?? 0),
                'at' => $createdAt, 'entry' => (float)($r['levels']['entry'] ?? 0),
                'stop' => (float)($r['levels']['stop_loss'] ?? 0),
                'tp1' => (float)($r['levels']['tp1'] ?? 0),
                'tp3' => (float)($r['levels']['tp3'] ?? 0),
                'atr' => isset($r['indicators']['atr']) ? (float)$r['indicators']['atr'] : null,
                // Which rules voted WITH this call, so a caller can ask how a
                // rule performed on the long side against the short side -
                // the pooled per-rule figure cannot see an asymmetry.
                'rules' => array_values(array_filter(array_map(
                    static fn (array $rs) => ($rs['side'] ?? '') === ($verdict === 'BUY' ? 'bullish' : 'bearish')
                        ? (string)($rs['key'] ?? '') : '',
                    $r['reasons'] ?? []
                ))),
            ];
            $signals++;
            // Reached a target label, and separately: actually made money. The
            // two part company on any plan that leaves a runner past target 1,
            // and the win rate below is the money one - see Outcomes::isWin.
            if ($rMult > 0) {
                $moneyWins++;
            }
            if ($outcome === 'confirmed') {
                $confirmed++;
            } elseif ($outcome === 'invalid') {
                $invalid++;
                $lastStopDir = $verdict;
                $lastStopAt = (int)$closedAt;
            } else {
                $expired++;
            }
            if ($outcome !== 'expired') {
                $rSum += $rMult;
                $rCount++;
            }
            // Credit every rule vote aligned with the simulated verdict.
            $dirSide = $verdict === 'BUY' ? 'bullish' : 'bearish';
            foreach ($r['reasons'] as $rs) {
                if (($rs['side'] ?? '') !== $dirSide || empty($rs['key'])) {
                    continue;
                }
                $k = (string)$rs['key'];
                $stats[$k] ??= ['fired' => 0, 'wins' => 0, 'r_sum' => 0.0];
                $stats[$k]['fired']++;
                $stats[$k]['r_sum'] += $outcome === 'expired' ? 0.0 : $rMult;
                if ($rMult > 0) {
                    $stats[$k]['wins']++;
                }
            }
        }

        $decided = $confirmed + $invalid;
        return [
            'bars'      => $n,
            'enough_history' => true,
            'signals'   => $signals,
            'confirmed' => $confirmed,
            'invalid'   => $invalid,
            'expired'   => $expired,
            'wins'      => $moneyWins,
            // Keys handed to the engine that it never consulted. A replay of a
            // configuration that was partly ignored is a replay of a
            // configuration nobody is running, and without this it reports the
            // same way as one that worked.
            'overrides_unused' => $engine->unusedOverrides(),
            'winrate'   => $decided > 0 ? round($moneyWins / $decided * 100, 1) : null,
            'avg_r'     => $rCount > 0 ? round($rSum / $rCount, 2) : null,
            'excursion' => array_map(static fn (array $e): array => [
                'n'        => $e['n'],
                'avg_mae'  => $e['n'] > 0 ? round($e['mae'] / $e['n'], 3) : null,
                'avg_mfe'  => $e['n'] > 0 ? round($e['mfe'] / $e['n'], 3) : null,
                'avg_bars' => $e['n'] > 0 ? round($e['bars'] / $e['n'], 1) : null,
            ], $exc),
            'stats'     => $stats,
            'trades'    => $trades,
        ];
    }

    /**
     * Backtest many pairs on one timeframe, optionally applying calibration
     * (global weight tuning + per-timeframe multipliers) from the combined
     * stats. Stores a report in the backtest_report setting for the admin UI.
     */
    public static function runMany(array $symbols, string $tf, bool $apply, int $minSamples): array
    {
        @set_time_limit(600);
        $md = null;
        try {
            $md = new MarketData(require SMA_ROOT . '/config.php');
        } catch (\Throwable $e) {
        }

        $combined = [];
        $perSymbol = [];
        $tot = ['signals' => 0, 'confirmed' => 0, 'wins' => 0, 'invalid' => 0, 'expired' => 0];
        // Excursions are re-summed from the per-symbol counts rather than
        // averaged across symbols: a pair that produced 3 signals must not
        // weigh as much as one that produced 300.
        $exc = [];
        foreach (['win', 'loss', 'flat'] as $b) {
            $exc[$b] = ['n' => 0, 'mae' => 0.0, 'mfe' => 0.0, 'bars' => 0.0];
        }
        foreach ($symbols as $sym) {
            $r = self::run($sym, $tf, $md);
            $perSymbol[$sym] = ['signals' => $r['signals'], 'winrate' => $r['winrate'], 'avg_r' => $r['avg_r']];
            foreach (['signals', 'confirmed', 'wins', 'invalid', 'expired'] as $k) {
                $tot[$k] += $r[$k];
            }
            foreach ((array)($r['excursion'] ?? []) as $b => $e) {
                if (!isset($exc[$b]) || (int)$e['n'] === 0) {
                    continue;
                }
                $exc[$b]['n'] += (int)$e['n'];
                $exc[$b]['mae'] += (float)$e['avg_mae'] * (int)$e['n'];
                $exc[$b]['mfe'] += (float)$e['avg_mfe'] * (int)$e['n'];
                $exc[$b]['bars'] += (float)$e['avg_bars'] * (int)$e['n'];
            }
            foreach ($r['stats'] as $key => $s) {
                $combined[$key] ??= ['fired' => 0, 'wins' => 0, 'r_sum' => 0.0];
                $combined[$key]['fired'] += $s['fired'];
                $combined[$key]['wins'] += $s['wins'];
                $combined[$key]['r_sum'] += $s['r_sum'];
            }
        }
        $decided = $tot['confirmed'] + $tot['invalid'];

        $weightsChanged = 0;
        $multsSet = 0;
        if ($apply) {
            $weightsChanged = Outcomes::applyWeightTuning($combined, $minSamples);
            $multsSet = Outcomes::applyTfMultipliers($tf, $combined, $minSamples);
        }

        $report = [
            'at' => time(),
            'tf' => $tf,
            'symbols' => count($symbols),
            'signals' => $tot['signals'],
            'confirmed' => $tot['confirmed'],
            'invalid' => $tot['invalid'],
            'expired' => $tot['expired'],
            'winrate' => $decided > 0 ? round($tot['wins'] / $decided * 100, 1) : null,
            'excursion' => array_map(static fn (array $e): array => [
                'n'        => $e['n'],
                'avg_mae'  => $e['n'] > 0 ? round($e['mae'] / $e['n'], 3) : null,
                'avg_mfe'  => $e['n'] > 0 ? round($e['mfe'] / $e['n'], 3) : null,
                'avg_bars' => $e['n'] > 0 ? round($e['bars'] / $e['n'], 1) : null,
            ], $exc),
            'applied' => $apply,
            'weights_changed' => $weightsChanged,
            'tf_multipliers_set' => $multsSet,
            'rules' => $combined,
            'per_symbol' => $perSymbol,
        ];
        Database::setSetting('backtest_report', json_encode($report));
        return $report;
    }

    /**
     * The stop/target search used to live here, and it is gone.
     *
     * It looped the grid inline over every symbol - measured, 32 candidates
     * on six coins died at its own @set_time_limit(900) with nothing saved -
     * and its only caller, the Engine lab button, now starts LevelSearch on
     * the resumable runner instead. What survived is levelGrid() below, which
     * is the part worth keeping: the candidate set, in one place, read by the
     * search that replaced this.
     *
     * Deleted rather than left for later. An unreachable method that still
     * looks like the way to do something is how two implementations of one
     * search come to exist, and this file has already been through that.
     */
    /**
     * Champion vs challenger: replay the same history under two engine
     * configurations and compare measured expectancy.
     *
     * Tuning used to be a matter of changing a setting and hoping. This turns
     * "should we raise the threshold?" into a question with an answer, on this
     * install's own data.
     *
     * $challenger is a map of setting => value applied only for the challenger
     * run; everything is restored afterwards.
     */
    public static function compare(array $symbols, string $tf, array $challenger): array
    {
        @set_time_limit(900);
        $md = null;
        try {
            $md = new MarketData(require SMA_ROOT . '/config.php');
        } catch (\Throwable $e) {
        }

        $measure = function (array $overrides = []) use ($symbols, $tf, $md): array {
            $tot = ['signals' => 0, 'confirmed' => 0, 'wins' => 0, 'invalid' => 0, 'expired' => 0, 'r' => 0.0, 'rn' => 0];
            foreach ($symbols as $sym) {
                $r = self::run($sym, $tf, $md, $overrides);
                foreach (['signals', 'confirmed', 'wins', 'invalid', 'expired'] as $k) {
                    $tot[$k] += $r[$k];
                }
                if ($r['avg_r'] !== null) {
                    $d = $r['confirmed'] + $r['invalid'];
                    $tot['r'] += $r['avg_r'] * $d;
                    $tot['rn'] += $d;
                }
            }
            $decided = $tot['confirmed'] + $tot['invalid'];
            return [
                'signals' => $tot['signals'],
                'confirmed' => $tot['confirmed'],
                'invalid' => $tot['invalid'],
                'expired' => $tot['expired'],
                'winrate' => $decided > 0 ? round($tot['wins'] / $decided * 100, 1) : null,
                'expectancy' => $tot['rn'] > 0 ? round($tot['r'] / $tot['rn'], 4) : null,
            ];
        };

        // Both runs read the same live configuration; the challenger's changes
        // ride on the engine instance, so nothing is written and nothing needs
        // restoring afterwards.
        $champion = $measure();
        $challengerResult = $measure(['settings' => $challenger]);

        $cE = $champion['expectancy'];
        $hE = $challengerResult['expectancy'];
        $report = [
            'at' => time(), 'tf' => $tf, 'symbols' => count($symbols),
            'changes' => $challenger,
            'champion' => $champion,
            'challenger' => $challengerResult,
            'winner' => ($cE === null || $hE === null) ? 'inconclusive' : ($hE > $cE ? 'challenger' : 'champion'),
            'delta' => ($cE !== null && $hE !== null) ? round($hE - $cE, 4) : null,
        ];
        Database::setSetting('ab_report', json_encode($report));
        return $report;
    }

    /**
     * Learned per-timeframe ATR multipliers, when the optimiser has produced
     * them. Consumed by the engine in place of the global defaults.
     */
    /**
     * Every stop geometry worth trying, as [sl, tp1, tp2] in ATR.
     *
     * Pulled out of optimiseLevels so the resumable search in LevelSearch can
     * walk the SAME candidates rather than keeping a second copy of the grid -
     * two lists that are meant to be identical are two lists that will not be.
     *
     * ONLY sl VARIES NOW. This used to also vary tp1 and tp2 independently -
     * up to nine pairs per stop - on the assumption that the first target's
     * distance was its own lever. It is not: the engine builds every published
     * target as an exact multiple of the stop's own distance (see
     * SignalEngine's "ratio IS the ladder" note), so a replay's outcome never
     * depends on the tp1/tp2 a candidate carried, only on its sl. Varying them
     * anyway meant running up to nine byte-identical replays per stop and
     * showing them as nine different rows - real CPU spent, and a results
     * table where distinct-looking numbers described the same measurement.
     * tp1/tp2 still travel with each candidate because levelsFor() reads them
     * (tp1 feeds the chase-guard threshold) and tf_levels stores them, so the
     * shape here matches theirs; they are fixed rather than searched.
     *
     * @return array<int,array{0:float,1:float,2:float}>
     */
    public static function levelGrid(): array
    {
        $out = [];
        foreach ([1.0, 1.5, 2.0, 2.5] as $sl) {
            $out[] = [$sl, 1.0, 2.0];
        }
        return $out;
    }

    /** The learned per-timeframe levels, as stored. */
    public static function levelsSetting(): array
    {
        return json_decode(Database::setting('tf_levels', '{}'), true) ?: [];
    }

    public static function levelsFor(string $tf): ?array
    {
        if (Database::setting('tf_levels_enabled', '1') !== '1') {
            return null;
        }
        $all = json_decode(Database::setting('tf_levels', '{}'), true) ?: [];
        $l = $all[$tf] ?? null;
        return is_array($l) && isset($l['sl'], $l['tp1'], $l['tp2']) ? $l : null;
    }
}
