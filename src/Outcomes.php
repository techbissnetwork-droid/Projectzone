<?php
declare(strict_types=1);

namespace SignalMasterAi;

/**
 * Signal outcome verification: every stored BUY/SELL signal with a trade plan
 * is checked against the price action that followed it.
 *
 *   confirmed - TP1 was reached before the stop loss
 *   invalid   - the stop loss was hit first
 *   expired   - the time stop passed with neither level touched; the setup
 *               simply went nowhere, so it counts as no-trade and is EXCLUDED
 *               from winrates and rule tuning (an untriggered setup is not a
 *               loss - counting it as one would punish rules unfairly)
 *
 * Conservative rule: when a single candle spans both the stop and TP1, the
 * stop is assumed to have been hit first. Runs from cron and piggybacked on
 * site traffic; results power the public per-coin track record.
 */
class Outcomes
{
    /**
     * WHAT COUNTS AS A WIN. ONE ANSWER, FOR THE WHOLE SITE.
     *
     * There were two, and they disagreed.
     *
     * The public track record counts a win as a trade that MADE MONEY -
     * outcome_r > 0. Everything else here counted a win as outcome =
     * 'confirmed', which means "price reached a target label", and those are
     * not the same trade. A position that takes half off at the first target
     * and then gives it all back on the stop is labelled confirmed and nets
     * about zero, or less. It is a win on one screen and a loss on the other,
     * from the same row of the same table.
     *
     * That is not a rounding difference, it is a bias with a direction: the
     * label can only flatter a plan that leaves something running past the
     * first target. Compare a preset that scales out (tp1_partial_pct 50)
     * against one that closes the whole position at target 1 (100) and the
     * scale-out preset collects wins the other one cannot have, on the label
     * measure only. An operator reading those two numbers side by side is
     * being told the wrong preset is better.
     *
     * It also fed the engine. Displayed confidence is calibrated against past
     * win rate, so a bucket full of touch-it-and-give-it-back trades was
     * reported to members as reliable.
     *
     * The money definition is the one that survives the comparison, so it is
     * the one that lives here and every counter uses it.
     */
    public static function isWin(array $row): bool
    {
        return (float)($row['outcome_r'] ?? 0) > 0;
    }

    /** The same test in SQL, for the counters that aggregate in the database. */
    public const WIN_SQL = 'outcome_r > 0';


    /** Evaluate open signals. Returns [confirmed, invalid] counters. */
    public static function evaluate(int $limit = 300): array
    {
        $pdo = Database::pdo();
        $open = $pdo->prepare(
            "SELECT id, symbol, tf, `signal`, indicators, created_at, spread_pct FROM signals
             WHERE outcome = '' AND `signal` != 'NEUTRAL'
             ORDER BY created_at ASC LIMIT " . (int)$limit
        );
        $open->execute();
        $candleStmt = $pdo->prepare(
            'SELECT open_time, high, low, close FROM candles
             WHERE symbol = ? AND tf = ? AND open_time > ? ORDER BY open_time ASC'
        );
        $close = $pdo->prepare(
            'UPDATE signals SET outcome = ?, outcome_note = ?, closed_at = ?, outcome_r = ? WHERE id = ?'
        );
        // Separate statement so a database that predates the telemetry columns
        // (upgrade half-applied, column dropped by hand) still settles signals
        // rather than throwing on every one of them.
        $closeFull = $pdo->prepare(
            'UPDATE signals SET outcome = ?, outcome_note = ?, closed_at = ?, outcome_r = ?,
                    mae_r = ?, mfe_r = ?, mae_sec = ?, mfe_sec = ?, bars_held = ?
             WHERE id = ?'
        );

        $confirmed = 0;
        $invalid = 0;
        $timeStopBars = max(1, (int)Database::setting('time_stop_bars', '24'));

        foreach ($open->fetchAll() as $sig) {
            $inds = json_decode((string)$sig['indicators'], true);
            $levels = is_array($inds) ? ($inds['levels'] ?? null) : null;
            if (!is_array($levels) || !isset($levels['stop_loss'], $levels['tp1'])) {
                // Nothing verifiable (levels disabled at signal time).
                $close->execute(['none', '', time(), 0, $sig['id']]);
                continue;
            }
            $candleStmt->execute([$sig['symbol'], $sig['tf'], ((int)$sig['created_at']) * 1000]);
            // Settle against the deadline the signal actually published, not
            // the current global default. The regime read gives a grinding
            // trend longer than a whipsaw, and a signal card promising "4 days"
            // that the verifier closed after two would be measuring a trade
            // nobody was told to take. Falls back to the global for signals
            // stored before the deadline was published with them.
            $bars = max(1, (int)($levels['expires_bars'] ?? $timeStopBars));
            $result = self::walk(
                $sig['signal'] === 'BUY',
                $levels,
                (int)$sig['created_at'],
                MarketData::TF_SECONDS[$sig['tf']] ?? 3600,
                $bars,
                $candleStmt->fetchAll(),
                self::subCandles($sig['symbol'], $sig['tf'], ((int)$sig['created_at']) * 1000),
                null,
                // This signal's own spread at the moment it was published, not
                // a flat per-install guess - see walk()'s spreadPct doc.
                isset($sig['spread_pct']) && $sig['spread_pct'] !== null ? (float)$sig['spread_pct'] : null
            );
            if ($result !== null) {
                $ex = $result[4] ?? [];
                try {
                    $closeFull->execute([
                        $result[0], $result[1], $result[2], $result[3],
                        $ex['mae_r'] ?? null, $ex['mfe_r'] ?? null,
                        $ex['mae_sec'] ?? null, $ex['mfe_sec'] ?? null, $ex['bars_held'] ?? null,
                        $sig['id'],
                    ]);
                } catch (\Throwable $e) {
                    $close->execute([$result[0], $result[1], $result[2], $result[3], $sig['id']]);
                }
                if ($result[0] === 'confirmed') {
                    $confirmed++;
                } else {
                    $invalid++;
                }
            }
        }
        return [$confirmed, $invalid];
    }

    /**
     * Settle the setups the engine turned down.
     *
     * Same walk(), same levels, same fees, same time stop as a published
     * signal - which is the whole point: a shadow is only worth anything if
     * its R means the same thing as a real trade's R. Anything else is
     * comparing a measurement to a guess.
     *
     * Returns [confirmed, invalid].
     */
    public static function evaluateShadows(int $limit = 300): array
    {
        if (Database::setting('shadow_enabled', '1') !== '1') {
            return [0, 0];
        }
        $pdo = Database::pdo();
        try {
            $open = $pdo->prepare(
                "SELECT id, symbol, tf, `signal`, indicators, created_at FROM shadow_signals
                  WHERE outcome = '' ORDER BY created_at ASC LIMIT " . (int)$limit
            );
            $open->execute();
            $rows = $open->fetchAll();
        } catch (\Throwable $e) {
            return [0, 0];      // table not migrated yet
        }
        $candleStmt = $pdo->prepare(
            'SELECT open_time, high, low, close FROM candles
             WHERE symbol = ? AND tf = ? AND open_time > ? ORDER BY open_time ASC'
        );
        $close = $pdo->prepare(
            'UPDATE shadow_signals SET outcome = ?, outcome_note = ?, closed_at = ?, outcome_r = ?
              WHERE id = ?'
        );
        $timeStopBars = max(1, (int)Database::setting('time_stop_bars', '24'));
        $confirmed = 0;
        $invalid = 0;
        foreach ($rows as $sig) {
            $inds = json_decode((string)$sig['indicators'], true);
            $levels = is_array($inds) ? ($inds['levels'] ?? null) : null;
            if (!is_array($levels) || !isset($levels['stop_loss'], $levels['tp1'])) {
                $close->execute(['none', '', time(), 0, $sig['id']]);
                continue;
            }
            $candleStmt->execute([$sig['symbol'], $sig['tf'], ((int)$sig['created_at']) * 1000]);
            $bars = max(1, (int)($levels['expires_bars'] ?? $timeStopBars));
            $result = self::walk(
                $sig['signal'] === 'BUY',
                $levels,
                (int)$sig['created_at'],
                MarketData::TF_SECONDS[$sig['tf']] ?? 3600,
                $bars,
                $candleStmt->fetchAll(),
                self::subCandles($sig['symbol'], $sig['tf'], ((int)$sig['created_at']) * 1000)
            );
            if ($result !== null) {
                $close->execute([$result[0], $result[1], $result[2], $result[3], $sig['id']]);
                if ($result[0] === 'confirmed') {
                    $confirmed++;
                } elseif ($result[0] === 'invalid') {
                    $invalid++;
                }
            }
        }
        return [$confirmed, $invalid];
    }

    /**
     * Walk candles after a signal and settle it, mirroring the published exit
     * rules. Phase 1: stop loss vs TP1 (stop checked first - conservative).
     * After TP1 the stop moves to entry (break-even) and the walk continues
     * for TP2 until twice the time stop. Returns
     * [outcome, note, closedAt, rMultiple, excursion] or null while still open.
     * R multiple = profit measured in units of the initial risk (-1 = stop).
     *
     * The fifth element is the excursion record: how far the trade went the
     * wrong way before it settled (MAE), how far it went the right way (MFE),
     * how long each took, and how many bars it stayed open. Two winners with
     * the same +2R are not the same trade if one never went 0.1R offside and
     * the other sat at -0.9R for six hours first, and the difference is
     * exactly what tells a robust setup from a lucky one.
     *
     * Shared by the live verifier and the backtester.
     */
    /**
     * @param array{tp1:?float,tp2:?float}|null $exitPolicy shares taken at the
     *        first and second target, 0-100. Null reads the live settings.
     *
     *        THE ONE SETTING A REPLAY COULD NOT VARY.
     *
     *        tp1_partial_pct is the difference between Selective (the whole
     *        position out at target 1) and Balanced (half out, half running) -
     *        and it was read straight from the live settings table in here, so
     *        a replay of Selective settled its trades under whatever the
     *        operator happened to be running. The preset comparison reported
     *        an exit policy it had never tested, and could not say so, because
     *        a setting that never reaches the code looks exactly like one that
     *        made no difference.
     * @param ?float $spreadPct top-of-book spread at the time of the trade, as
     *        a percentage of price (same unit as round_trip_cost_pct). Null
     *        when unknown - a historical replay with no recorded spread for
     *        that moment - and the cost falls back to fees alone, exactly as
     *        it always has.
     *
     *        A FLAT FEE WAS THE WHOLE COST MODEL, AND SPREAD IS NOT A FEE.
     *
     *        round_trip_cost_pct is one number for every symbol - a stand-in
     *        for the exchange's own trading fee, which really is close to flat
     *        across pairs. Spread is not: a top-of-book quote on a deep pair
     *        can be a hundredth of a percent, and on a thin one it can be
     *        several tenths - and until this parameter existed the walk never
     *        looked at it at all, live or backtested, so every settled R on
     *        every symbol carried the SAME cost regardless of how expensive
     *        that symbol actually was to trade. That number feeds outcome_r,
     *        which feeds isWin(), which feeds every win rate, every
     *        calibration bucket and every tuning decision on the site - so a
     *        thin-liquidity coin was having its real cost understated
     *        everywhere at once. The live verifier passes each signal's own
     *        recorded spread_pct; the backtester passes today's live spread
     *        for the symbol as the best available stand-in for what it was.
     */
    public static function walk(bool $isBuy, array $levels, int $createdAt, int $tfSec, int $timeStopBars, array $candles, ?array $subCandles = null, ?array $exitPolicy = null, ?float $spreadPct = null): ?array
    {
        $entry = (float)($levels['entry'] ?? 0);
        $sl = (float)$levels['stop_loss'];
        $tp1 = (float)$levels['tp1'];
        $tp2 = (float)($levels['tp2'] ?? 0);
        $tp3 = (float)($levels['tp3'] ?? 0);
        $risk = $entry > 0 ? abs($entry - $sl) : 0.0;
        // Trading costs, expressed in R. Published results were gross: no fees,
        // no slippage, so every reported win rate and average R flattered the
        // engine by a fixed amount per trade. The cost is a round-trip
        // percentage of notional converted into units of initial risk - fees
        // (one number for every symbol) plus this trade's own spread (not one
        // number for every symbol, see the spreadPct doc above) when it is known.
        $costR = 0.0;
        if ($risk > 0 && $entry > 0) {
            $costPct = max(0.0, (float)Database::setting('round_trip_cost_pct', '0.1')) / 100;
            if ($spreadPct !== null && $spreadPct > 0) {
                $costPct += $spreadPct / 100;
            }
            $costR = round($entry * $costPct / $risk, 4);
        }
        // One position-management plan, applied to every exit.
        //
        // This used to book whichever of two contradictory plans happened to
        // pay more. A trade that tagged TP1 and came back to entry was settled
        // at the FULL TP1 profit - as if the whole position had been sold
        // there - while a trade that carried on to TP2 was settled at the full
        // TP2 profit, as if nothing had been sold at all. Both branches
        // credited full size, so a retrace after TP1 was recorded as a win of
        // the same size as an exit taken at the target, and 44% of the settled
        // record on this install was sitting on that branch.
        //
        // The published exit rule says to take part off at TP1 and let the
        // rest run risk-free, so that is what is measured now: a fixed share
        // banked at TP1, the remainder settled wherever it actually ends up.
        // The share is a setting because it is a trading decision, not a
        // constant - 0 holds everything for TP2, 100 takes it all at TP1, and
        // either is coherent as long as the plan being measured is the plan
        // being published.
        $partial = max(0.0, min(1.0, ($exitPolicy['tp1'] ?? null) !== null
            ? (float)$exitPolicy['tp1'] / 100
            : (float)Database::setting('tp1_partial_pct', '50') / 100));
        // The second leg, and the reason TP3 was never reached in any number of
        // signals: the walk closed the whole remainder at TP2 and stopped. TP3
        // was published to members as a target and then never simulated, so
        // "TP3 reached" was not a rare outcome - it was an outcome this
        // function could not produce. Nothing was looking.
        //
        // Whatever is left after both partials runs to TP3. Set this so the two
        // shares add up to 100 and there is no runner, and the walk behaves
        // exactly as it did before - which is what an install that liked the
        // old plan should do.
        $partial2 = max(0.0, min(1.0 - $partial, ($exitPolicy['tp2'] ?? null) !== null
            ? (float)$exitPolicy['tp2'] / 100
            : (float)Database::setting('tp2_partial_pct', '30') / 100));
        $runner = max(0.0, 1.0 - $partial - $partial2);
        $g1 = $risk > 0 ? abs($tp1 - $entry) / $risk : 0.0;
        $g2 = $risk > 0 && $tp2 > 0 ? abs($tp2 - $entry) / $risk : $g1;
        $g3 = $risk > 0 && $tp3 > 0 ? abs($tp3 - $entry) / $risk : $g2;
        // A third target that is not further away than the second is not a
        // third target; treat it as absent rather than as a runner that can
        // never gain anything.
        $hasRunner = $runner > 0.001 && $tp3 > 0 && $g3 > $g2 + 0.001;
        $banked = $partial * $g1;                       // locked in at TP1
        $banked2 = $banked + $partial2 * $g2;           // locked in at TP2
        $r1 = round($g1 - $costR, 2);                   // whole position off at TP1
        $rBreakEven = round($banked - $costR, 2);       // remainder scratched at entry
        // With no runner the plan is the old one: everything left goes at TP2.
        $r2 = $hasRunner
            ? round($banked2 + $runner * $g2 - $costR, 2)
            : round($banked + (1 - $partial) * $g2 - $costR, 2);
        $r3 = round($banked2 + $runner * $g3 - $costR, 2);
        // The runner giving back to TP1 after TP2 was reached. It is a stop
        // that trails, not one that returns to entry - a position already twice
        // in profit should not be measured as if it could scratch.
        $r3Stopped = round($banked2 + $runner * $g1 - $costR, 2);
        $rStop = round(-1.0 - $costR, 2);
        // The remainder marked to the price where the walk actually ended,
        // instead of assuming it was sold back at TP1. Bounded by the two
        // levels that would have closed it themselves: below entry the
        // break-even stop had already taken it out, above TP2 the target had.
        $lastClose = null;
        $markOut = static function (?float $close) use ($banked, $partial, $isBuy, $entry, $risk, $g2, $costR): float {
            if ($close === null || $risk <= 0) {
                return round($banked - $costR, 2);      // no price to mark to: treat it as scratched
            }
            $runner = ($isBuy ? $close - $entry : $entry - $close) / $risk;
            return round($banked + (1 - $partial) * max(0.0, min($g2, $runner)) - $costR, 2);
        };
        // The runner marked to where the walk ended, bounded by the levels that
        // would themselves have closed it: below TP1 the trailing stop had it,
        // above TP3 the target had.
        $markOut3 = static function (?float $close) use ($banked2, $runner, $isBuy, $entry, $risk, $g1, $g3, $costR): float {
            if ($close === null || $risk <= 0) {
                return round($banked2 + $runner * $g1 - $costR, 2);
            }
            $run = ($isBuy ? $close - $entry : $entry - $close) / $risk;
            return round($banked2 + $runner * max($g1, min($g3, $run)) - $costR, 2);
        };
        $deadline = $createdAt + $timeStopBars * $tfSec;
        $deadline2 = $createdAt + 2 * $timeStopBars * $tfSec;
        $deadline3 = $createdAt + 3 * $timeStopBars * $tfSec;
        $phase2 = false;   // after TP1: stop at break-even, hunting TP2
        $phase3 = false;   // after TP2: stop trailing at TP1, hunting TP3

        // Excursion tracking. Recorded in R so it is comparable across pairs
        // and timeframes; null when there is no risk distance to divide by,
        // because a zero would read as "never went offside" and that is a
        // different claim from "cannot say".
        $maeR = null;
        $mfeR = null;
        $maeSec = null;
        $mfeSec = null;
        $bars = 0;
        $note = static function (float $hi, float $lo, int $at) use (&$maeR, &$mfeR, &$maeSec, &$mfeSec, $isBuy, $entry, $risk, $createdAt): void {
            if ($risk <= 0 || $entry <= 0) {
                return;
            }
            $adverse    = $isBuy ? ($lo - $entry) / $risk : ($entry - $hi) / $risk;
            $favourable = $isBuy ? ($hi - $entry) / $risk : ($entry - $lo) / $risk;
            $sec = max(0, $at - $createdAt);
            if ($maeR === null || $adverse < $maeR) {
                $maeR = $adverse;
                $maeSec = $sec;
            }
            if ($mfeR === null || $favourable > $mfeR) {
                $mfeR = $favourable;
                $mfeSec = $sec;
            }
        };
        // Every exit path returns through here so the excursion record can
        // never go missing from one branch and not another.
        $settle = static function (string $outcome, string $why, int $at, float $r) use (&$maeR, &$mfeR, &$maeSec, &$mfeSec, &$bars): array {
            return [$outcome, $why, $at, $r, [
                'mae_r'     => $maeR === null ? null : round($maeR, 3),
                'mfe_r'     => $mfeR === null ? null : round($mfeR, 3),
                'mae_sec'   => $maeSec,
                'mfe_sec'   => $mfeSec,
                'bars_held' => $bars,
            ]];
        };

        foreach ($candles as $c) {
            $hi = (float)$c['high'];
            $lo = (float)$c['low'];
            $at = (int)((int)$c['open_time'] / 1000);
            $bars++;
            $note($hi, $lo, $at);
            if (isset($c['close'])) {
                $lastClose = (float)$c['close'];
            }
            if (!$phase2) {
                $hitStop = ($isBuy && $lo <= $sl) || (!$isBuy && $hi >= $sl);
                $hitTp1  = ($isBuy && $hi >= $tp1) || (!$isBuy && $lo <= $tp1);
                // Ambiguous bar: this candle touched BOTH the stop and the
                // target, so its high/low alone cannot say which came first.
                // The conservative default assumes the stop - correct, but
                // systematically pessimistic. When finer candles covering this
                // bar are available, replay them and settle it for real.
                if ($hitStop && $hitTp1 && $subCandles !== null) {
                    $order = self::resolveAmbiguous($isBuy, $sl, $tp1, $at, $tfSec, $subCandles);
                    if ($order === 'tp') {
                        if ($tp2 <= 0) {
                            return $settle('confirmed', 'TP1 reached', $at, $r1);
                        }
                        $phase2 = true;
                        continue;
                    }
                    if ($order === 'sl') {
                        return $settle('invalid', 'Stop loss hit', $at, $rStop);
                    }
                }
                // Conservative: check the stop before the target.
                if ($hitStop) {
                    return $settle('invalid', 'Stop loss hit', $at, $rStop);
                }
                if ($hitTp1) {
                    if ($tp2 <= 0) {
                        return $settle('confirmed', 'TP1 reached', $at, $r1);
                    }
                    $phase2 = true;   // stop moves to entry from the NEXT candle
                    continue;
                }
                if ($at > $deadline) {
                    return $settle('expired', 'Time stop - setup went nowhere', $at, 0.0);
                }
            } elseif (!$phase3) {
                // Conservative again: break-even touch counts before TP2. The
                // part taken off at TP1 is kept; the remainder goes out at
                // entry, which is what a break-even stop means.
                if (($isBuy && $lo <= $entry) || (!$isBuy && $hi >= $entry)) {
                    return $settle('confirmed', 'TP1 reached, stopped at break-even', $at, $rBreakEven);
                }
                if (($isBuy && $hi >= $tp2) || (!$isBuy && $lo <= $tp2)) {
                    if (!$hasRunner) {
                        return $settle('confirmed', 'TP2 reached', $at, $r2);
                    }
                    $phase3 = true;   // stop trails to TP1 from the NEXT candle
                    continue;
                }
                if ($at > $deadline2) {
                    // Out of time holding a runner: it is closed at the market,
                    // so it is measured at the market rather than at a target
                    // it never reached.
                    return $settle('confirmed', 'TP1 reached, remainder closed at the time stop',
                        $at, $markOut($lastClose));
                }
            } else {
                // The third leg. Same conservatism as the other two: the stop
                // is checked before the target, so a candle that covers both is
                // read the way that flatters the record least.
                if (($isBuy && $lo <= $tp1) || (!$isBuy && $hi >= $tp1)) {
                    return $settle('confirmed', 'TP2 reached, remainder stopped at TP1', $at, $r3Stopped);
                }
                if (($isBuy && $hi >= $tp3) || (!$isBuy && $lo <= $tp3)) {
                    return $settle('confirmed', 'TP3 reached', $at, $r3);
                }
                if ($at > $deadline3) {
                    return $settle('confirmed', 'TP2 reached, remainder closed at the time stop',
                        $at, $markOut3($lastClose));
                }
            }
        }
        // Candles exhausted: settle on wall-clock once the window has passed.
        if ($phase3 && time() > $deadline3 + $tfSec) {
            return $settle('confirmed', 'TP2 reached, remainder closed at the time stop',
                time(), $markOut3($lastClose));
        }
        if ($phase2 && time() > $deadline2 + $tfSec) {
            return $settle('confirmed', 'TP1 reached, remainder closed at the time stop',
                time(), $markOut($lastClose));
        }
        if (!$phase2 && time() > $deadline + $tfSec) {
            return $settle('expired', 'Time stop - setup went nowhere', time(), 0.0);
        }
        return null;
    }

    /**
     * Settle an ambiguous bar by replaying finer candles inside it.
     *
     * A 4h bar that spans both the stop and the target is settled as a loss by
     * the conservative rule. That is the right default, but if 15m candles for
     * the same window are on disk the true sequence is knowable, and assuming
     * a loss when the target actually came first understates the engine.
     * Returns 'sl', 'tp' or null when the finer data cannot decide.
     */
    private static function resolveAmbiguous(bool $isBuy, float $sl, float $tp1, int $barStart, int $tfSec, array $subCandles): ?string
    {
        foreach ($subCandles as $s) {
            $at = (int)((int)$s['open_time'] / 1000);
            if ($at < $barStart || $at >= $barStart + $tfSec) {
                continue;
            }
            $hi = (float)$s['high'];
            $lo = (float)$s['low'];
            $hitStop = ($isBuy && $lo <= $sl) || (!$isBuy && $hi >= $sl);
            $hitTp   = ($isBuy && $hi >= $tp1) || (!$isBuy && $lo <= $tp1);
            if ($hitStop && $hitTp) {
                return 'sl';    // still ambiguous at this resolution - stay conservative
            }
            if ($hitStop) {
                return 'sl';
            }
            if ($hitTp) {
                return 'tp';
            }
        }
        return null;
    }

    /** Finer candles covering a window, for ambiguous-bar resolution. */
    private static function subCandles(string $symbol, string $tf, int $fromMs): ?array
    {
        $finer = ['2h' => '15m', '6h' => '1h', '8h' => '1h', '12h' => '1h', '3d' => '4h',
                  '4h' => '15m', '1h' => '5m', '1d' => '1h', '1w' => '4h', '30m' => '5m',
                  '15m' => '1m', '5m' => '1m', '3m' => '1m'][$tf] ?? null;
        if ($finer === null || Database::setting('subcandle_resolution', '1') !== '1') {
            return null;
        }
        $stmt = Database::pdo()->prepare(
            'SELECT open_time, high, low FROM candles
             WHERE symbol = ? AND tf = ? AND open_time > ? ORDER BY open_time ASC LIMIT 4000'
        );
        $stmt->execute([$symbol, $finer, $fromMs]);
        $rows = $stmt->fetchAll();
        return $rows ?: null;
    }

    /**
     * Per-rule performance from evaluated signals.
     *
     * Votes ALIGNED with the verdict are credited as before. Votes that fired
     * AGAINST the verdict are now tracked too: previously a rule that reliably
     * argued against winning trades was never penalised, because the tuner
     * only ever looked at agreement. A rule with an inverted edge is
     * information - it just needs its sign flipped, or its weight cut.
     *
     * Returns [rule_key => ['fired','wins','r_sum','against_fired','against_wins']].
     */
    /**
     * How far back the learners look.
     *
     * A ROW COUNT, not a period - the newest N settled trades, whatever span
     * that turns out to be. That is a real trap as a site grows: 500 trades
     * is three months on a fifty-coin watchlist and about two days on a
     * five-hundred-coin one, so the same number quietly becomes a different
     * decision. Nothing warns you, because nothing about the setting changes.
     *
     * So there is an optional day cap alongside it, off by default. With both
     * set, the narrower wins: at most N trades, and none older than D days.
     * The admin card prints how many days the current window actually covers,
     * which is the number an operator can act on.
     *
     * @return array{0:int,1:int} [rows, days] - days 0 means no time limit
     */
    public static function learnWindow(): array
    {
        return [
            max(50, min(100000, (int)Database::setting('learn_window_rows', '500'))),
            max(0, min(3650, (int)Database::setting('learn_window_days', '0'))),
        ];
    }

    /**
     * @param int $lastN 0 = use the configured window
     */
    public static function ruleStats(int $lastN = 0, string $tf = '', string $symbol = ''): array
    {
        [$defRows, $days] = self::learnWindow();
        $lastN = $lastN > 0 ? $lastN : $defRows;
        $sql = "SELECT `signal`, outcome, reasons, outcome_r FROM signals
                WHERE outcome IN ('confirmed', 'invalid') AND `signal` != 'NEUTRAL'";
        $args = [];
        if ($tf !== '') {
            $sql .= ' AND tf = ?';
            $args[] = $tf;
        }
        if ($symbol !== '') {
            $sql .= ' AND symbol = ?';
            $args[] = $symbol;
        }
        if ($days > 0) {
            // closed_at, not created_at: a trade belongs to the period it was
            // DECIDED in. A 1d setup opened four months ago and closed last
            // week is evidence about last week.
            $sql .= ' AND closed_at >= ?';
            $args[] = time() - $days * 86400;
        }
        $stmt = Database::pdo()->prepare($sql . ' ORDER BY created_at DESC LIMIT ' . (int)$lastN);
        $stmt->execute($args);
        $stats = [];
        foreach ($stmt->fetchAll() as $row) {
            $dirSide = $row['signal'] === 'BUY' ? 'bullish' : 'bearish';
            $won = self::isWin($row);
            $r = (float)($row['outcome_r'] ?? 0);
            foreach ((array)json_decode((string)$row['reasons'], true) as $rs) {
                if (!is_array($rs) || empty($rs['key']) || empty($rs['side'])) {
                    continue;
                }
                $k = (string)$rs['key'];
                $stats[$k] ??= ['fired' => 0, 'wins' => 0, 'r_sum' => 0.0,
                                'against_fired' => 0, 'against_wins' => 0];
                if ($rs['side'] === $dirSide) {
                    $stats[$k]['fired']++;
                    $stats[$k]['r_sum'] += $r;
                    if ($won) {
                        $stats[$k]['wins']++;
                    }
                } else {
                    $stats[$k]['against_fired']++;
                    if ($won) {
                        $stats[$k]['against_wins']++;
                    }
                }
            }
        }
        return $stats;
    }

    /**
     * Wilson score lower bound for a binomial proportion.
     *
     * A bucket with 5 wins from 8 samples has a raw win rate of 62.5% and a
     * confidence interval roughly 30 points wide. Showing "62% measured" from
     * that is not honest, and the calibration table accepted buckets at n >= 8.
     * The lower bound answers "what win rate can we actually stand behind",
     * and converges on the raw rate as evidence accumulates.
     */
    public static function wilsonLower(int $wins, int $n, float $z = 1.96): float
    {
        if ($n <= 0) {
            return 0.0;
        }
        $p = $wins / $n;
        $z2 = $z * $z;
        $denom = 1 + $z2 / $n;
        $centre = $p + $z2 / (2 * $n);
        $margin = $z * sqrt(($p * (1 - $p) + $z2 / (4 * $n)) / $n);
        return max(0.0, min(1.0, ($centre - $margin) / $denom));
    }

    /**
     * Weight multiplier for a rule's measured performance. Prefers expectancy
     * (average R per aligned signal - profit-shaped accuracy); falls back to
     * the hit-rate formula for legacy rows stored before R tracking existed.
     */
    private static function perfMultiplier(array $s): float
    {
        // Every caller filters on a minimum sample count before getting here,
        // so this cannot currently be reached with nothing - and one new
        // caller that forgets would throw DivisionByZeroError on PHP 8 and
        // take the whole nightly tuner down with it. A rule with no evidence
        // gets the multiplier that means "no opinion".
        $fired = (int)($s['fired'] ?? 0);
        if ($fired < 1) {
            return 1.0;
        }
        $winrate = $s['wins'] / $fired;
        if (abs((float)($s['r_sum'] ?? 0)) > 0.001) {
            $avgR = $s['r_sum'] / $fired;
            $mult = max(0.75, min(1.25, 1 + 0.35 * $avgR));
        } else {
            $mult = 0.7 + 0.6 * $winrate;
        }
        // Counter-evidence: a rule that keeps arguing against trades that go
        // on to win is not neutral, it is actively misleading. The old tuner
        // never saw this because it only looked at aligned votes.
        $against = (int)($s['against_fired'] ?? 0);
        if ($against >= 10) {
            $againstWinrate = $s['against_wins'] / $against;
            if ($againstWinrate > 0.6) {
                $mult *= 0.85;
            } elseif ($againstWinrate < 0.4) {
                $mult *= 1.05;   // it was right to object
            }
        }
        return $mult;
    }

    /**
     * Apply performance-based weight tuning from a stats map.
     *
     * Weights are computed RELATIVE TO THE BASELINE, not multiplied into the
     * previous result. The old form (`weight *= multiplier`) ran daily from
     * live outcomes and weekly from the backtest, so adjustments compounded:
     * a rule drifted toward the 0.2 or 2.5 clamp and could never come back,
     * and each run measured data that the previous run's weights had shaped.
     *
     * Clamped to [0.2, 2.5] of the baseline weight.
     */
    /**
     * Performance watchdog.
     *
     * Quarantine judges one rule at a time and drift judges one change at a
     * time. Neither notices the case where nothing in particular is broken and
     * the whole thing has simply stopped working - a market the rule set was
     * fitted on has moved on. This watches the recent record as a whole and,
     * when it turns negative, forces a recalibration now instead of waiting
     * for the weekly timer.
     *
     * Judged on expectancy over the last N verified outcomes. Win rate is the
     * obvious trigger and the wrong one: this engine ran 67% while losing
     * money on every fast timeframe, and a win-rate watchdog would have sat
     * there reporting health throughout.
     *
     * Returns ['checked' => bool, ...] for the cron report.
     */
    public static function watchdog(): array
    {
        if (Database::setting('watchdog_enabled', '1') !== '1') {
            return ['checked' => false, 'reason' => 'disabled'];
        }
        $window = max(10, min(500, (int)Database::setting('watchdog_window', '30')));
        $floor  = (float)Database::setting('watchdog_floor_r', '-0.05');
        $rows = Database::pdo()->query(
            "SELECT outcome_r FROM signals
             WHERE outcome IN ('confirmed','invalid') AND `signal` != 'NEUTRAL'
             ORDER BY closed_at DESC LIMIT " . $window
        )->fetchAll(\PDO::FETCH_COLUMN);
        $n = count($rows);
        if ($n < $window) {
            return ['checked' => false, 'reason' => "only $n of $window verified outcomes"];
        }
        $avg = array_sum(array_map('floatval', $rows)) / $n;
        $out = ['checked' => true, 'n' => $n, 'avg_r' => round($avg, 4), 'floor' => $floor,
                'recalibrated' => false];
        if ($avg >= $floor) {
            return $out;
        }
        // Below the floor: re-fit now. Recalibration is the same pass the
        // weekly timer runs, so this brings it forward rather than doing
        // anything the operator has not already agreed to.
        $out['recalibrated'] = true;
        $out['changed'] = self::autoTuneWeights(
            max(5, (int)Database::setting('autotune_min_samples', '10')));
        Database::setSetting('watchdog_last', (string)time());
        return $out;
    }

    /**
     * Rule quarantine: bench rules with persistently negative expectancy.
     *
     * Judged on average R, not win rate - the distinction this install has
     * already paid for once, when a 67% win rate was losing money on every
     * fast timeframe. A benched rule stops voting for a probation period and
     * is then released to collect fresh evidence; if it is still negative on
     * the next pass it goes straight back. `confluence_bonus` is exempt: it
     * is a scoring mechanic rather than a claim about the market, so its
     * expectancy is really everyone else's.
     *
     * Returns the keys currently benched.
     */
    public static function applyQuarantine(array $stats): array
    {
        if (Database::setting('quarantine_enabled', '1') !== '1') {
            return [];
        }
        $q = (array)json_decode(Database::setting('quarantined_rules', '[]'), true);
        // Legacy plain-list format -> keyed by the time it was benched.
        if ($q !== [] && array_is_list($q)) {
            $q = array_fill_keys($q, time());
        }
        $days = max(1, min(120, (int)Database::setting('quarantine_days', '14')));
        // Entries used to be just a timestamp, so the admin could see THAT a
        // rule was benched and never why. They now carry the reading that
        // benched it; the old shapes still read, because an upgrade must not
        // silently release every rule on probation.
        foreach ($q as $key => $meta) {
            $since = is_array($meta) ? (int)($meta['at'] ?? 0) : (int)$meta;
            if (time() - $since >= $days * 86400) {
                unset($q[$key]);       // probation over - give it another chance
                Audit::log('rule.released', (string)$key, 'quarantined', 'back in the vote',
                    Audit::ACTOR_ENGINE);
            }
        }
        $minN = max(10, (int)Database::setting('quarantine_min_samples', '30'));
        $floor = (float)Database::setting('quarantine_min_r', '-0.1');
        // ONLY THINGS THAT ACTUALLY VOTE CAN BE BENCHED.
        //
        // Not every entry in a signal's reason list is a rule. Some are
        // explanations of something else the engine did - mtf_alignment, for
        // instance, carries weight 0.0 and exists to say why a counter-trend
        // threshold was raised. Those were measured, found wanting and
        // "benched" like any other, which did nothing at all: there is no vote
        // to suppress and the gate they describe is governed by its own
        // setting. The only visible effect was the dashboard reporting six
        // rules benched when five were, and an audit entry for a decision that
        // never took effect.
        //
        // A rule is a row in the knowledge base. Anything else is an
        // explanation, and an explanation cannot be sent off.
        $votable = [];
        try {
            foreach (Database::pdo()->query('SELECT rule_key FROM ta_knowledge') as $kr) {
                $votable[(string)$kr['rule_key']] = true;
            }
        } catch (\Throwable $e) {
            $votable = [];        // no knowledge table: bench nothing rather than everything
        }
        foreach ($stats as $key => $s) {
            if (isset($q[$key]) || $key === 'confluence_bonus' || !isset($votable[$key])) {
                continue;
            }
            $n = (int)($s['fired'] ?? 0);
            $avgR = $n > 0 ? (float)($s['r_sum'] ?? 0) / $n : 0.0;
            if ($n >= $minN && $avgR < $floor) {
                $q[(string)$key] = ['at' => time(),
                    'why' => sprintf('%+.3fR over %d trades, below the %.2fR floor', $avgR, $n, $floor)];
                Audit::log('rule.quarantined', (string)$key, 'voting',
                    sprintf('benched %d days (%+.3fR over %d)', $days, $avgR, $n),
                    Audit::ACTOR_ENGINE);
            }
        }
        // Release the ones that should never have been benched, so an install
        // that ran the old pass stops reporting them. Only those: a rule with
        // a genuine knowledge row keeps its bench and its clock.
        if ($votable) {
            foreach (array_keys($q) as $qk) {
                if (!isset($votable[(string)$qk]) && (string)$qk !== 'confluence_bonus') {
                    unset($q[$qk]);
                    Audit::log('rule.released', (string)$qk, 'quarantined',
                        'never voted - it is an explanation, not a rule', Audit::ACTOR_ENGINE);
                }
            }
        }
        Database::setSetting('quarantined_rules', json_encode($q));
        return array_keys($q);
    }

    /**
     * Benched rules, with when and why - for the admin panel.
     *
     * @return array<string,array{at:int,why:string,until:int}>
     */
    public static function quarantined(): array
    {
        $q = (array)json_decode(Database::setting('quarantined_rules', '[]'), true);
        if ($q !== [] && array_is_list($q)) {
            $q = array_fill_keys($q, time());
        }
        $days = max(1, min(120, (int)Database::setting('quarantine_days', '14')));
        $out = [];
        foreach ($q as $key => $meta) {
            $at = is_array($meta) ? (int)($meta['at'] ?? 0) : (int)$meta;
            $out[(string)$key] = [
                'at' => $at,
                'why' => is_array($meta) ? (string)($meta['why'] ?? '') : '',
                'until' => $at + $days * 86400,
            ];
        }
        return $out;
    }

    /** Put a rule back in the vote by hand, before its probation is up. */
    public static function releaseRule(string $key): bool
    {
        $q = (array)json_decode(Database::setting('quarantined_rules', '[]'), true);
        if ($q !== [] && array_is_list($q)) {
            $q = array_fill_keys($q, time());
        }
        if (!array_key_exists($key, $q)) {
            return false;
        }
        unset($q[$key]);
        Database::setSetting('quarantined_rules', json_encode($q));
        Audit::log('rule.released', $key, 'quarantined', 'released by hand');
        return true;
    }

    public static function applyWeightTuning(array $stats, int $minSamples): int
    {
        $pdo = Database::pdo();
        $upd = $pdo->prepare('UPDATE ta_knowledge SET weight = ? WHERE rule_key = ? AND enabled = 1');
        $changed = 0;
        foreach ($pdo->query('SELECT rule_key, weight, weight_base FROM ta_knowledge WHERE enabled = 1') as $rule) {
            $s = $stats[$rule['rule_key']] ?? null;
            if (!$s || $s['fired'] < $minSamples) {
                continue;
            }
            $base = (float)$rule['weight_base'];
            if ($base <= 0) {
                $base = (float)$rule['weight'];   // legacy row: adopt current as baseline
                $pdo->prepare('UPDATE ta_knowledge SET weight_base = ? WHERE rule_key = ?')
                    ->execute([$base, $rule['rule_key']]);
            }
            $new = round(max(0.2, min(2.5, $base * self::perfMultiplier($s))), 2);
            if (abs($new - (float)$rule['weight']) >= 0.01) {
                $upd->execute([$new, $rule['rule_key']]);
                // The engine rewriting its own weights is the single change
                // most likely to move results and the one nobody could see.
                Audit::log('rule.weight', (string)$rule['rule_key'],
                    (string)$rule['weight'], (string)$new, Audit::ACTOR_ENGINE);
                $changed++;
            }
        }
        return $changed;
    }

    /**
     * Walk-forward guard around weight tuning.
     *
     * Fit on the older slice of outcomes, score the newer slice, and only keep
     * the new weights when out-of-sample expectancy actually improves. Without
     * this the tuner was fitting and validating on the same data, which
     * rewards noise as reliably as it rewards edge.
     *
     * Returns ['applied' => bool, 'changed' => n, 'train_r' => x, 'test_r' => y].
     */
    public static function tuneWalkForward(int $minSamples, float $trainFraction = 0.7): array
    {
        $pdo = Database::pdo();
        // The most recent 2000 verified signals, oldest first within that
        // window. Taking the FIRST 2000 ever recorded meant that once an
        // install passed 2000 outcomes - and signals are kept for a year - the
        // tuner trained and validated on a frozen slice of its own history and
        // could never learn anything from a newer trade again.
        $rows = array_reverse($pdo->query(
            "SELECT id, `signal`, outcome, reasons, outcome_r, created_at FROM signals
             WHERE outcome IN ('confirmed','invalid') AND `signal` != 'NEUTRAL'
             ORDER BY created_at DESC LIMIT 2000"
        )->fetchAll());
        $n = count($rows);
        if ($n < max(40, $minSamples * 4)) {
            // Not enough evidence to validate; fall back to the plain tuner.
            return ['applied' => true, 'changed' => self::applyWeightTuning(self::ruleStats(), $minSamples),
                    'validated' => false, 'reason' => 'insufficient samples for a hold-out'];
        }
        $split = (int)floor($n * $trainFraction);
        $train = array_slice($rows, 0, $split);
        $test  = array_slice($rows, $split);

        $before = self::snapshotWeights();
        $trainStats = self::statsFromRows($train);
        // LEARN FROM WHAT IT DECLINED, VALIDATE ON WHAT IT DID.
        //
        // The refused setups join the training half only. They are real
        // measurements - same levels, same walk, same fees - and a rule's
        // weight is supposed to mean "how this rule does when it fires", not
        // "how it does on the occasions we happened to publish". But the
        // hold-out that decides whether the new weights are kept stays on
        // published signals alone, because that is the thing the site is
        // judged on and the only outcome a member ever experienced.
        if (Database::setting('shadow_learn', '1') === '1') {
            $trainStats = self::mergeStats($trainStats, self::shadowRuleStats());
        }
        $changed = self::applyWeightTuning($trainStats, $minSamples);
        $after = self::snapshotWeights();

        // Score the hold-out under both weight sets: expectancy of the trades
        // each set would have rated most highly.
        $testBefore = self::scoreRows($test, $before);
        $testAfter  = self::scoreRows($test, $after);

        $applied = $testAfter >= $testBefore;
        if (!$applied) {
            self::restoreWeights($before);
            $changed = 0;
        }
        return [
            'applied' => $applied,
            'changed' => $changed,
            'validated' => true,
            'train_n' => count($train),
            'test_n' => count($test),
            'test_r_before' => round($testBefore, 4),
            'test_r_after' => round($testAfter, 4),
        ];
    }

    /** Rule stats computed from an explicit row set (walk-forward slices). */
    /** Add two rule-stat maps together. Same shape in, same shape out. */
    private static function mergeStats(array $a, array $b): array
    {
        foreach ($b as $key => $s) {
            $a[$key] ??= ['fired' => 0, 'wins' => 0, 'r_sum' => 0.0,
                          'against_fired' => 0, 'against_wins' => 0];
            foreach (['fired', 'wins', 'against_fired', 'against_wins'] as $k) {
                $a[$key][$k] = (int)$a[$key][$k] + (int)($s[$k] ?? 0);
            }
            $a[$key]['r_sum'] = (float)$a[$key]['r_sum'] + (float)($s['r_sum'] ?? 0);
        }
        return $a;
    }

    private static function statsFromRows(array $rows): array
    {
        $stats = [];
        foreach ($rows as $row) {
            $dirSide = $row['signal'] === 'BUY' ? 'bullish' : 'bearish';
            $won = self::isWin($row);
            $r = (float)($row['outcome_r'] ?? 0);
            foreach ((array)json_decode((string)$row['reasons'], true) as $rs) {
                if (!is_array($rs) || empty($rs['key']) || empty($rs['side'])) {
                    continue;
                }
                $k = (string)$rs['key'];
                $stats[$k] ??= ['fired' => 0, 'wins' => 0, 'r_sum' => 0.0,
                                'against_fired' => 0, 'against_wins' => 0];
                if ($rs['side'] === $dirSide) {
                    $stats[$k]['fired']++;
                    $stats[$k]['r_sum'] += $r;
                    if ($won) {
                        $stats[$k]['wins']++;
                    }
                } else {
                    $stats[$k]['against_fired']++;
                    if ($won) {
                        $stats[$k]['against_wins']++;
                    }
                }
            }
        }
        return $stats;
    }

    /**
     * Average realised R of the hold-out trades a weight set would have rated
     * above the median - a proxy for "would these weights have picked better".
     */
    private static function scoreRows(array $rows, array $weights): float
    {
        $scored = [];
        foreach ($rows as $row) {
            $dirSide = $row['signal'] === 'BUY' ? 'bullish' : 'bearish';
            $s = 0.0;
            foreach ((array)json_decode((string)$row['reasons'], true) as $rs) {
                if (!is_array($rs) || empty($rs['key'])) {
                    continue;
                }
                $w = (float)($weights[$rs['key']] ?? 0);
                $s += (($rs['side'] ?? '') === $dirSide ? 1 : -1) * $w;
            }
            $scored[] = ['s' => $s, 'r' => (float)($row['outcome_r'] ?? 0)];
        }
        if (count($scored) < 4) {
            return 0.0;
        }
        usort($scored, fn($a, $b) => $b['s'] <=> $a['s']);
        $top = array_slice($scored, 0, max(2, (int)floor(count($scored) / 2)));
        return array_sum(array_column($top, 'r')) / count($top);
    }

    private static function snapshotWeights(): array
    {
        $out = [];
        foreach (Database::pdo()->query('SELECT rule_key, weight FROM ta_knowledge') as $r) {
            $out[$r['rule_key']] = (float)$r['weight'];
        }
        return $out;
    }

    private static function restoreWeights(array $weights): void
    {
        $upd = Database::pdo()->prepare('UPDATE ta_knowledge SET weight = ? WHERE rule_key = ?');
        foreach ($weights as $k => $w) {
            $upd->execute([$w, $k]);
        }
    }

    /**
     * Expectancy by hour of day and day of week (UTC).
     *
     * Crypto trades around the clock but follow-through does not: thin weekend
     * books and the hours between sessions resolve setups differently. These
     * buckets are learned the same way rule weights are and surfaced in the
     * admin so the pattern is visible rather than assumed.
     */
    public static function timeBuckets(int $lastN = 1000): array
    {
        $stmt = Database::pdo()->prepare(
            "SELECT created_at, outcome, outcome_r FROM signals
             WHERE outcome IN ('confirmed','invalid') AND `signal` != 'NEUTRAL'
             ORDER BY created_at DESC LIMIT " . (int)$lastN
        );
        $stmt->execute();
        $hour = array_fill(0, 24, ['n' => 0, 'wins' => 0, 'r' => 0.0]);
        $dow  = array_fill(0, 7, ['n' => 0, 'wins' => 0, 'r' => 0.0]);
        foreach ($stmt->fetchAll() as $row) {
            $t = (int)$row['created_at'];
            $h = (int)gmdate('G', $t);
            $d = (int)gmdate('w', $t);
            $win = self::isWin($row) ? 1 : 0;
            $r = (float)$row['outcome_r'];
            $hour[$h]['n']++; $hour[$h]['wins'] += $win; $hour[$h]['r'] += $r;
            $dow[$d]['n']++;  $dow[$d]['wins']  += $win; $dow[$d]['r']  += $r;
        }
        $fin = function (array $b): array {
            foreach ($b as $k => $v) {
                $b[$k]['winrate'] = $v['n'] > 0 ? round($v['wins'] / $v['n'] * 100, 1) : null;
                $b[$k]['avg_r']   = $v['n'] > 0 ? round($v['r'] / $v['n'], 3) : null;
            }
            return $b;
        };
        $out = ['hour' => $fin($hour), 'dow' => $fin($dow), 'at' => time()];
        Database::setSetting('time_buckets', json_encode($out));
        return $out;
    }

    /**
     * Learn per-timeframe rule multipliers (a rule can be strong on 1d and
     * noise on 15m). Stored in the tf_rule_mult setting as
     * {tf: {rule_key: mult}}, clamped to [0.6, 1.4]; the engine applies them
     * on top of the global weights. Returns the number of multipliers set.
     *
     * SHRUNK TOWARD 1.0 (no adjustment), not applied at full strength the
     * moment a rule clears minSamples. A rule sitting exactly at minSamples
     * used to get the same full-strength multiplier as one with ten times the
     * evidence - a cliff at the sample floor rather than a curve above it, the
     * one thing applySymbolMultipliers() already got right for coins. See
     * tfShrinkage().
     */
    public static function applyTfMultipliers(string $tf, array $stats, int $minSamples): int
    {
        $all = json_decode(Database::setting('tf_rule_mult', '{}'), true) ?: [];
        $set = 0;
        foreach ($stats as $key => $s) {
            $n = (int)($s['fired'] ?? 0);
            if ($n < $minSamples) {
                continue;
            }
            $raw = self::perfMultiplier($s);
            $k = self::tfShrinkage($n);
            $mult = round(max(0.6, min(1.4, 1 + $k * ($raw - 1))), 2);
            $all[$tf][$key] = $mult;
            $set++;
        }
        if ($set > 0) {
            Database::setSetting('tf_rule_mult', json_encode($all));
        }
        return $set;
    }

    /**
     * What the setups the engine refused actually did.
     *
     * The question no record made only of published signals can answer: was
     * the filter right? A chop gate that keeps standing down winners is a
     * setting to loosen; a threshold that keeps refusing +0.4R setups is set
     * too high. Both are invisible until the refusals are settled and counted.
     *
     * Grouped by what did the refusing, and it deliberately reports EXPIRED
     * separately: a setup that went nowhere is not evidence the gate was
     * wrong, it is evidence the gate cost nothing.
     *
     * @return array<string,array{n:int,wins:int,r_sum:float,expired:int,avg_r:float}>
     */
    public static function shadowStats(int $days = 0): array
    {
        $sql = "SELECT blocked_by, outcome, outcome_r FROM shadow_signals
                 WHERE outcome IN ('confirmed','invalid','expired')";
        $args = [];
        if ($days > 0) {
            $sql .= ' AND closed_at >= ?';
            $args[] = time() - $days * 86400;
        }
        try {
            $stmt = Database::pdo()->prepare($sql);
            $stmt->execute($args);
            $rows = $stmt->fetchAll();
        } catch (\Throwable $e) {
            return [];
        }
        $out = [];
        foreach ($rows as $r) {
            $k = (string)($r['blocked_by'] ?: 'gate');
            $out[$k] ??= ['n' => 0, 'wins' => 0, 'r_sum' => 0.0, 'expired' => 0, 'avg_r' => 0.0];
            if ($r['outcome'] === 'expired') {
                $out[$k]['expired']++;
                continue;
            }
            $out[$k]['n']++;
            $out[$k]['r_sum'] += (float)$r['outcome_r'];
            if (self::isWin($r)) {
                $out[$k]['wins']++;
            }
        }
        foreach ($out as $k => $v) {
            $out[$k]['avg_r'] = $v['n'] > 0 ? round($v['r_sum'] / $v['n'], 3) : 0.0;
        }
        uasort($out, fn($a, $b) => $b['n'] <=> $a['n']);
        return $out;
    }

    /**
     * Where the threshold should be, according to the setups it refused.
     *
     * The near-miss shadows carry the score they were refused at, so for the
     * first time the question "is the bar set in the right place" has data
     * behind it instead of a preference. Walk down from the threshold: at each
     * score level, what would the refused setups below it have paid?
     *
     * The answer is the LOWEST level at which the trades between it and the
     * current threshold still made money on average. Below that, taking more
     * setups starts costing - so that is the bar.
     *
     * Advisory only, and deliberately so. It is offered in the panel with the
     * evidence beside it and never applied on its own: a threshold is the
     * single most consequential number on the site, and an engine that moves
     * it while nobody is looking is one nobody can reason about.
     *
     * @return array{buy?:array,sell?:array}
     */
    public static function thresholdAdvice(int $minSamples = 20): array
    {
        try {
            $rows = Database::pdo()->query(
                "SELECT `signal`, score, outcome_r FROM shadow_signals
                  WHERE blocked_by = 'threshold' AND outcome IN ('confirmed','invalid')"
            )->fetchAll();
        } catch (\Throwable $e) {
            return [];
        }
        $out = [];
        foreach (['BUY', 'SELL'] as $side) {
            $mine = array_values(array_filter($rows, fn($r) => $r['signal'] === $side));
            if (count($mine) < $minSamples) {
                continue;
            }
            // Closest to the threshold first: those are the ones a small move
            // would capture, and the ones to judge before reaching further.
            usort($mine, fn($a, $b) => $side === 'BUY'
                ? (float)$b['score'] <=> (float)$a['score']
                : (float)$a['score'] <=> (float)$b['score']);
            // MARGINAL, NOT CUMULATIVE - and the difference is the whole
            // answer. Taking the running average from the threshold downwards
            // says "still positive" long after the extra setups have stopped
            // paying, because the good ones nearer the threshold subsidise
            // them: on a test set that only paid above 1.6 it recommended
            // 1.38. The question is not "are the refused setups profitable on
            // average" but "does the NEXT slice down still pay" - so the bar
            // stops at the last slice that did.
            $slice = max(10, (int)floor($minSamples / 2));
            $best = null;
            $taken = 0;
            $takenSum = 0.0;
            for ($start = 0; $start + $slice <= count($mine); $start += $slice) {
                $chunk = array_slice($mine, $start, $slice);
                $chunkR = array_sum(array_map(fn($r) => (float)$r['outcome_r'], $chunk));
                if ($chunkR / $slice <= 0) {
                    break;              // this far down it stops paying
                }
                $taken += $slice;
                $takenSum += $chunkR;
                $last = end($chunk);
                $best = ['score' => round((float)$last['score'], 2),
                         'n' => $taken, 'avg_r' => round($takenSum / $taken, 3)];
            }
            // THE GATE THAT REFUSED THESE SETUPS, NOT THE ONE WITH THE
            // FAMILIAR NAME.
            //
            // This compared against buy_threshold / sell_threshold, which in
            // the shipped category mode the engine NEVER READS - it reads
            // cat_buy_threshold and cat_sell_threshold. So the panel quoted a
            // number that decides nothing, and the advice it printed was
            // self-refuting on its face: an operator was told "30 setups were
            // refused between 3.37 and the current 2", which cannot both be
            // true, because a setup scoring 3.37 clears a threshold of 2 and
            // would have been published.
            //
            // It got worse at the point of acting on it. The recommendation
            // sent the operator to Settings > Verdict thresholds to change
            // buy_threshold - a control the engine ignores in this mode - so
            // following the advice exactly would have changed the number,
            // saved cleanly, and altered nothing. That is the same failure
            // that made every preset's threshold inert, in the one panel
            // whose whole job is telling the operator what to change.
            $catMode = SignalEngine::scoringMode() === 'category';
            $key = $side === 'BUY'
                ? ($catMode ? 'cat_buy_threshold' : 'buy_threshold')
                : ($catMode ? 'cat_sell_threshold' : 'sell_threshold');
            $default = $side === 'BUY' ? ($catMode ? '3.0' : '2.0') : ($catMode ? '-3.0' : '-2.0');
            $cur = (float)Database::setting($key, $default);
            // Only ever a LOOSENING. The suggestion is drawn from setups the
            // gate refused, so it can only be reached by asking for less; a
            // recommendation on the strict side of the current gate would not
            // take a single one of the trades it is quoting, and printing one
            // is how this advice came to contradict itself.
            $looser = $side === 'BUY' ? $best['score'] < $cur : $best['score'] > $cur;
            if ($best !== null && $looser && abs($best['score'] - $cur) >= 0.1) {
                $out[strtolower($side)] = $best + [
                    'current' => $cur,
                    'setting' => $key,
                    // Where the control actually lives. The category pair is
                    // on the engine page, not on the settings one.
                    'where'   => $catMode ? 'engine.php#thresholds' : 'settings.php#thresholds',
                    'where_label' => $catMode
                        ? 'Engine lab &rsaquo; Category scoring'
                        : 'Settings &rsaquo; Signals &rsaquo; Verdict thresholds',
                ];
            }
        }
        return $out;
    }

    /**
     * Rule statistics from the setups that were NOT taken.
     *
     * Same shape as ruleStats(), so the tuner can merge them: a rule's record
     * stops being "how it did on trades we published" and becomes "how it did
     * whenever it fired", which is the thing a weight is supposed to mean.
     * Without this the engine can only ever polish the decisions it already
     * makes - it has no way to learn that it was wrong to decline.
     */
    public static function shadowRuleStats(): array
    {
        [$rows, $days] = self::learnWindow();
        $sql = "SELECT `signal`, outcome, reasons, outcome_r FROM shadow_signals
                 WHERE outcome IN ('confirmed','invalid')";
        $args = [];
        if ($days > 0) {
            $sql .= ' AND closed_at >= ?';
            $args[] = time() - $days * 86400;
        }
        try {
            $stmt = Database::pdo()->prepare($sql . ' ORDER BY created_at DESC LIMIT ' . (int)$rows);
            $stmt->execute($args);
            $fetched = $stmt->fetchAll();
        } catch (\Throwable $e) {
            return [];
        }
        return self::statsFromRows($fetched);
    }

    /**
     * The n/(n+k) evidence curve itself, shared by every axis that shrinks a
     * thin estimate toward a broader one instead of trusting it outright.
     *
     * ONE FORMULA, so a coin's multiplier and a timeframe's multiplier are
     * shrunk by the same shape and can only differ in how cautious (k) they
     * are - not by two implementations quietly drifting apart. With k of 40:
     * three samples buy 7% of a voice, fifty buy 56%, five hundred buy 93%.
     * No threshold to cross and no cliff to fall off - influence grows
     * continuously as evidence accumulates, and zero evidence is silently
     * identical to no adjustment at all.
     */
    private static function evidenceWeight(int $n, int $k): float
    {
        $n = max(0, $n);
        $k = max(1, min(1000, $k));
        return $n / ($n + $k);
    }

    /**
     * How much a coin's own record is allowed to speak for itself.
     */
    public static function shrinkage(int $n): float
    {
        $mode = self::symLearnMode();
        if ($mode === 'global') {
            return 0.0;             // the coin never speaks for itself
        }
        if ($mode === 'coin') {
            return 1.0;             // the coin speaks entirely for itself
        }
        return self::evidenceWeight($n, (int)Database::setting('sym_learn_k', '40'));
    }

    /**
     * How much a timeframe's own per-rule record is allowed to speak for
     * itself, in a multiplier applied on top of the global rule weights.
     *
     * applyTfMultipliers() used to be a cliff: below minSamples a rule got no
     * multiplier at all, and the instant it crossed that line it got the FULL
     * measured multiplier, exactly as noisy as a sample of minSamples always
     * is. applySymbolMultipliers() right below solved the equivalent problem
     * for coins with this same curve years ago; this closes the same gap on
     * the timeframe axis. No mode switch here - timeframe multipliers have no
     * "coin" or "global" equivalent to choose between, only how cautious to be.
     */
    public static function tfShrinkage(int $n): float
    {
        return self::evidenceWeight($n, (int)Database::setting('tf_learn_k', '40'));
    }

    /**
     * Settled trades a coin needs before it is looked at AT ALL, in any mode
     * that lets coins speak. Below this there is nothing to measure and the
     * only honest answer is the shared weights, so the coin is skipped before
     * shrinkage is even consulted - which is why "coin only" is not literally
     * "whatever the coin has, however little". Named because the admin panel
     * has to tell operators the same number the tuner uses.
     */
    public const SYM_MIN_SAMPLES = 5;

    /**
     * How a coin's own record is allowed to count. One definition, because
     * three places ask and they must not disagree.
     *
     *   auto   - blended with the shared weights in proportion to evidence.
     *            The default, and the only one that cannot be wrong: with no
     *            data it IS the shared weights.
     *   coin   - the coin's own measurement, used whole. Honest when a coin
     *            has hundreds of settled trades and pure noise when it has
     *            three, which is why it is not the default.
     *   global - shared weights only; no coin has an opinion.
     *
     * Migrates the old on/off setting rather than resetting it: an operator
     * who switched per-coin learning off does not want it back on because the
     * control gained a third option.
     */
    public static function symLearnMode(): string
    {
        $mode = Database::setting('sym_learn_mode', '');
        if (in_array($mode, ['auto', 'coin', 'global'], true)) {
            return $mode;
        }
        return Database::setting('sym_learn_enabled', '1') === '1' ? 'auto' : 'global';
    }

    /**
     * Learn a per-coin multiplier for each rule, SHRUNK TOWARD THE GLOBAL ONE.
     *
     * The obvious way to do this is the wrong way. Give every coin its own
     * private weight table and each table sees a thirtieth of the evidence:
     * a rule that fired three times on one pair and won two of them measures
     * 67%, which is what a fair coin does half the time it is tossed three
     * times. The engine would promote a rule for no reason, that rule would
     * then shape which signals fire on that pair, and the mistake would seal
     * itself in - fewer firings, fewer samples, no way back. Nineteen
     * thousand such cells is not thirty times the intelligence, it is thirty
     * times the noise.
     *
     * So a coin's estimate is not used raw. It is mixed with the global
     * estimate in proportion to how much that coin has actually proved:
     *
     *     final = 1 + k * (coin_estimate - 1),  k = n / (n + K)
     *
     * At zero evidence this is exactly 1.0 - the behaviour that exists today,
     * with nothing lost. At heavy evidence it converges on the coin's own
     * measurement. Everything in between is a proportion, not a guess. This
     * is the standard answer to estimating many small things at once, and it
     * cannot do worse than not doing it: with no data it IS not doing it.
     *
     * Clamped tighter than the per-timeframe band (0.75-1.25 against
     * 0.6-1.4), because a coin's slice of the record is always the thinner
     * one, and stored only where the multiplier actually moved - a table of
     * thirty coins times sixty-five rules of 1.00 is a large way to say
     * nothing.
     *
     * @return array{coins:int,mults:int}
     */
    public static function applySymbolMultipliers(int $minSamples = self::SYM_MIN_SAMPLES): array
    {
        $mode = self::symLearnMode();
        if ($mode === 'global') {
            // Global mode means every coin uses the shared rule weights, so
            // any per-coin conclusions still on file are not merely unused -
            // they would be applied the moment somebody switched back, from a
            // market that has moved on. Cleared rather than kept.
            SymbolLearning::forgetAll();
            return ['coins' => 0, 'mults' => 0, 'dropped' => 0, 'mode' => 'global'];
        }
        $pdo = Database::pdo();
        // Only coins that have actually settled something. A scan list of
        // three hundred pairs must not become three hundred queries to learn
        // nothing from each.
        $rows = $pdo->query(
            "SELECT symbol, COUNT(*) n FROM signals
              WHERE outcome IN ('confirmed','invalid') AND `signal` != 'NEUTRAL'
              GROUP BY symbol HAVING COUNT(*) >= " . max(1, $minSamples) . "
              ORDER BY n DESC LIMIT 300"
        )->fetchAll();

        $all = [];
        $mults = 0;
        foreach ($rows as $row) {
            $sym = (string)$row['symbol'];
            $stats = self::ruleStats(0, '', $sym);
            $forSym = [];
            foreach ($stats as $key => $s) {
                $n = (int)($s['fired'] ?? 0);
                if ($n < 1) {
                    continue;
                }
                $raw = self::perfMultiplier($s);
                $k = self::shrinkage($n);
                $mult = round(max(0.75, min(1.25, 1 + $k * ($raw - 1))), 3);
                // Below a fiftieth the engine would not print it and the
                // arithmetic would not move a verdict; storing it is weight
                // without effect.
                if (abs($mult - 1.0) >= 0.02) {
                    $forSym[(string)$key] = $mult;
                    $mults++;
                }
            }
            if ($forSym) {
                // The strongest adjustments only. A coin's sixty-fifth rule at
                // x1.02 does not move a verdict, but it costs the same bytes as
                // one at x1.24 - and spending the budget on it is what turned
                // three hundred coins into thirty-three below. Sorted by how
                // far from 1.0 each one is, so what survives is what matters.
                if (count($forSym) > 20) {
                    uasort($forSym, fn($a, $b) => abs($b - 1.0) <=> abs($a - 1.0));
                    $forSym = array_slice($forSym, 0, 20, true);
                }
                $all[$sym] = $forSym;
            }
        }
        // NO BUDGET, AND NOTHING DROPPED.
        //
        // This used to be one JSON document in settings.svalue - TEXT, which
        // is 64KB on MySQL - so it carried a 48,000-byte budget enforced by
        // POPPING COINS OFF THE END until the string fit. Three hundred coins
        // times sixty-five rules is around 430KB, so a busy install spent
        // every tuning run silently un-learning its least-evidenced coins, and
        // said so only in a report nobody had a reason to open.
        //
        // One row per (coin, rule) now, in a table of its own. `dropped` stays
        // in the report and stays at zero: an operator who saw a number there
        // last month should be able to see that it is gone rather than have
        // the field vanish and wonder which release ate it.
        $store = SymbolLearning::replaceAll($all);
        Database::setSetting('sym_learn_report', json_encode([
            'mode' => $mode,
            'coins' => $store['coins'], 'mults' => $store['mults'], 'dropped' => 0,
            'considered' => count($rows), 'k' => (int)Database::setting('sym_learn_k', '40'),
            'at' => time(),
        ]));
        return ['coins' => $store['coins'], 'mults' => $store['mults'],
                'dropped' => 0, 'mode' => $mode];
    }

    /**
     * Confidence calibration: bucket verified signals by |score| and measure
     * the real win rate per bucket. The engine then shows "signals of this
     * strength historically won X%" instead of a heuristic - the most honest
     * confidence number possible. Stored in the confidence_calib setting.
     */
    public static function buildCalibration(int $lastN = 1000): array
    {
        $stmt = Database::pdo()->prepare(
            "SELECT ABS(score) s, outcome, outcome_r FROM signals
             WHERE outcome IN ('confirmed', 'invalid') AND `signal` != 'NEUTRAL'
             ORDER BY created_at DESC LIMIT " . (int)$lastN
        );
        $stmt->execute();
        $edges = [[0, 2], [2, 4], [4, 6], [6, 9], [9, 999]];
        $buckets = array_map(
            fn($e) => ['lo' => $e[0], 'hi' => $e[1], 'n' => 0, 'wins' => 0, 'r_sum' => 0.0],
            $edges
        );
        foreach ($stmt->fetchAll() as $row) {
            foreach ($buckets as $i => $b) {
                if ((float)$row['s'] >= $b['lo'] && (float)$row['s'] < $b['hi']) {
                    $buckets[$i]['n']++;
                    // Expectancy per band as well as win rate, because the
                    // question this table exists to answer is whether a higher
                    // score is a better TRADE, and a win rate on its own
                    // cannot say that - a band can win often and still lose
                    // money if its losses are the big ones.
                    $buckets[$i]['r_sum'] += (float)($row['outcome_r'] ?? 0);
                    if (self::isWin($row)) {
                        $buckets[$i]['wins']++;
                    }
                    break;
                }
            }
        }
        foreach ($buckets as $i => $b) {
            $buckets[$i]['winrate'] = $b['n'] > 0 ? round($b['wins'] / $b['n'] * 100, 1) : null;
            // What we can actually stand behind, not what the sample happened
            // to print: 5 wins from 8 is a 62.5% point estimate with a ~30
            // point interval, and the engine used to publish that as measured.
            $buckets[$i]['wilson'] = $b['n'] > 0
                ? round(self::wilsonLower($b['wins'], $b['n']) * 100, 1) : null;
            $buckets[$i]['avg_r'] = $b['n'] > 0 ? round($b['r_sum'] / $b['n'], 3) : null;
        }
        $calib = ['at' => time(), 'buckets' => $buckets];
        Database::setSetting('confidence_calib', json_encode($calib));
        return $calib;
    }

    /**
     * Real win rate per letter grade, built the same way the score and
     * quality calibrations above are: bucket verified signals by what they
     * actually claimed at creation time, measure what happened.
     *
     * GRADE WAS THE ONE SCORE COMPONENT NEVER CHECKED AGAINST OUTCOMES.
     *
     * |score| gets a calibration. The six-axis quality read gets one too.
     * Grade - the single letter the board sorts by, the first thing a member
     * reads about a setup - never had one: it is confluence-category counting
     * plus an ADX threshold (SignalEngine.php), asserted at signal time and
     * never once compared to what its own signals went on to do. This builds
     * that comparison and flags the one failure a sort key cannot survive - a
     * "better" grade with a measurably worse record than a "worse" one.
     */
    public static function buildGradeCalibration(int $lastN = 3000): array
    {
        $rows = Database::pdo()->query(
            "SELECT indicators, outcome, outcome_r FROM signals
             WHERE outcome IN ('confirmed', 'invalid') AND `signal` != 'NEUTRAL'
             ORDER BY created_at DESC LIMIT " . (int)$lastN
        )->fetchAll();

        $order = ['A+', 'A', 'B', 'C'];
        $buckets = [];
        foreach ($order as $g) {
            $buckets[$g] = ['n' => 0, 'wins' => 0, 'r_sum' => 0.0];
        }
        foreach ($rows as $row) {
            $inds = json_decode((string)$row['indicators'], true);
            $grade = is_array($inds) ? (string)($inds['grade'] ?? '') : '';
            // Same fallback the board itself uses for a row stored before
            // grading existed - see Scanner.php's $rawGrade !== '' ? $rawGrade : 'C'.
            if (!in_array($grade, $order, true)) {
                $grade = 'C';
            }
            $buckets[$grade]['n']++;
            $buckets[$grade]['r_sum'] += (float)($row['outcome_r'] ?? 0);
            if (self::isWin($row)) {
                $buckets[$grade]['wins']++;
            }
        }
        foreach ($buckets as $g => $b) {
            $buckets[$g]['winrate'] = $b['n'] > 0 ? round($b['wins'] / $b['n'] * 100, 1) : null;
            $buckets[$g]['wilson'] = $b['n'] > 0
                ? round(self::wilsonLower($b['wins'], $b['n']) * 100, 1) : null;
            $buckets[$g]['avg_r'] = $b['n'] > 0 ? round($b['r_sum'] / $b['n'], 3) : null;
        }

        // INVERSION: a grade that is supposed to be better measuring worse
        // than one that is supposed to be worse, both with enough evidence to
        // trust. Judged on the Wilson bound, not the raw rate, so two buckets
        // whose confidence intervals overlap are not flagged as contradicting
        // each other over what is really noise.
        $minN = max(15, (int)Database::setting('calib_min_samples', '30'));
        $inversions = [];
        for ($i = 0; $i < count($order) - 1; $i++) {
            for ($j = $i + 1; $j < count($order); $j++) {
                [$better, $worse] = [$order[$i], $order[$j]];
                $bb = $buckets[$better];
                $wb = $buckets[$worse];
                if ($bb['n'] >= $minN && $wb['n'] >= $minN
                    && $bb['wilson'] !== null && $wb['wilson'] !== null
                    && $bb['wilson'] < $wb['wilson']) {
                    $inversions[] = ['better' => $better, 'worse' => $worse,
                        'better_wilson' => $bb['wilson'], 'worse_wilson' => $wb['wilson']];
                }
            }
        }

        $calib = ['at' => time(), 'buckets' => $buckets, 'inversions' => $inversions, 'min_samples' => $minN];
        Database::setSetting('grade_calib', json_encode($calib));
        return $calib;
    }

    /**
     * Evidence for or against the chop gate's A+ exemption
     * (chop_gate_a_plus_exempt, read in SignalEngine.php).
     *
     * THE ONE COMPARISON THE PUBLISHED RECORD CAN ACTUALLY MAKE.
     *
     * Only A+ setups ever publish while the Choppiness Index reads above the
     * gate's limit - every other grade is stood down there, no exceptions -
     * so the published record alone can only compare A+-in-chop against
     * A+-not-in-chop. What every OTHER grade would have done in chop is not
     * nothing, though: shadow_signals already records exactly those setups
     * (blocked_by 'chop_gate'), settled the same walk a published signal gets
     * (see shadowStats()). This lines the three numbers up together: what the
     * exemption publishes, what it publishes when chop is not a factor, and
     * what it is exempting A+ from having to clear.
     */
    public static function chopExemptionStats(int $lastN = 3000): array
    {
        $chopLimit = (float)Database::setting('chop_limit', '61.8');
        $rows = Database::pdo()->query(
            "SELECT indicators, outcome, outcome_r FROM signals
             WHERE outcome IN ('confirmed', 'invalid') AND `signal` != 'NEUTRAL'
             ORDER BY created_at DESC LIMIT " . (int)$lastN
        )->fetchAll();

        $buckets = ['in_chop' => ['n' => 0, 'wins' => 0, 'r_sum' => 0.0],
                    'not_chop' => ['n' => 0, 'wins' => 0, 'r_sum' => 0.0]];
        foreach ($rows as $row) {
            $inds = json_decode((string)$row['indicators'], true);
            if (!is_array($inds) || ($inds['grade'] ?? '') !== 'A+' || !isset($inds['chop'])) {
                continue;
            }
            $key = (float)$inds['chop'] >= $chopLimit ? 'in_chop' : 'not_chop';
            $buckets[$key]['n']++;
            $buckets[$key]['r_sum'] += (float)($row['outcome_r'] ?? 0);
            if (self::isWin($row)) {
                $buckets[$key]['wins']++;
            }
        }
        foreach ($buckets as $k => $b) {
            $buckets[$k]['winrate'] = $b['n'] > 0 ? round($b['wins'] / $b['n'] * 100, 1) : null;
            $buckets[$k]['wilson'] = $b['n'] > 0
                ? round(self::wilsonLower($b['wins'], $b['n']) * 100, 1) : null;
            $buckets[$k]['avg_r'] = $b['n'] > 0 ? round($b['r_sum'] / $b['n'], 3) : null;
        }

        return [
            'at' => time(),
            'chop_limit' => $chopLimit,
            'a_plus_in_chop' => $buckets['in_chop'],
            'a_plus_not_in_chop' => $buckets['not_chop'],
            // Everyone else's chop-gated record, already measured by
            // shadowStats(). Read fresh rather than threaded in by the
            // caller, so this function is the whole answer on its own.
            'other_grades_blocked' => self::shadowStats()['chop_gate'] ?? null,
        ];
    }

    /**
     * Daily self-tuning entry point: global weights from all live outcomes,
     * per-timeframe multipliers from per-TF outcome slices, and a refreshed
     * confidence calibration.
     */
    public static function autoTuneWeights(int $minSamples = 10): int
    {
        // Walk-forward validated when there is enough history to hold out a
        // test slice; the plain tuner otherwise.
        $res = Database::setting('walk_forward_enabled', '1') === '1'
            ? self::tuneWalkForward($minSamples)
            : ['changed' => self::applyWeightTuning(self::ruleStats(), $minSamples)];
        // Bench the rules that keep losing money. Tuning only moves a weight,
        // and a rule with a real negative edge still drags at a small weight -
        // it needs to stop voting, then be given a chance to earn its place
        // back rather than being written off for good.
        $res['quarantined'] = self::applyQuarantine(self::ruleStats());
        $res['shadow'] = ['learned_from' => 0];
        if (Database::setting('shadow_learn', '1') === '1') {
            $sh = self::shadowRuleStats();
            $res['shadow']['learned_from'] = array_sum(array_column($sh, 'fired'));
        }

        foreach (['1m', '3m', '5m', '15m', '30m', '1h', '4h', '1d', '1w', '1mo'] as $tf) {
            self::applyTfMultipliers($tf, self::ruleStats(0, $tf), $minSamples);
        }
        // Per-coin, shrunk toward the global weight by how much each coin has
        // proved. Deliberately NOT gated on $minSamples: shrinkage already
        // makes a thin coin's opinion almost nothing, so a threshold on top
        // would only turn a smooth curve back into a cliff.
        $res['symbols'] = self::applySymbolMultipliers();
        // Written HERE, not before the two multiplier legs. It used to be
        // saved straight after quarantine, so everything the run did
        // afterwards was missing from its own report - the panel could show
        // the per-coin work as never having happened.
        Database::setSetting('autotune_report', json_encode($res + ['at' => time()]));
        self::buildCalibration();
        // The quality map, over the six-dimension read the engine now
        // publishes. The |score| buckets above still describe real outcomes
        // and stay as a fallback for installs whose history predates this.
        try {
            Confidence::buildCalibration();
        } catch (\Throwable $e) {
            // A missing map means confidence reports itself as an estimate,
            // which is the correct behaviour rather than a failure.
        }
        self::timeBuckets();
        try {
            LearnedModel::train();
        } catch (\Throwable $e) {
            // the learned model is an enhancement, never a hard dependency
        }
        // Grade calibration and the chop-exemption evidence: read-only
        // measurements, never applied on their own (see buildGradeCalibration
        // and chopExemptionStats docs) - surfaced in Engine lab for an
        // operator to act on, same as thresholdAdvice() above.
        try {
            self::buildGradeCalibration();
        } catch (\Throwable $e) {
            // a stale or missing grade_calib means the panel reports no
            // evidence yet, which is correct rather than a failure
        }
        return (int)($res['changed'] ?? 0);
    }

    /** Track record for one pair: [evaluated, wins, winrate|null]. */
    /**
     * Why there are not more signals than there are.
     *
     * THE COMMONEST QUESTION AND THERE WAS NOWHERE TO LOOK.
     *
     * "The engine has gone quiet" has a dozen possible causes - a threshold,
     * the confluence requirement, the chop filter, a cooldown, a pre-flight
     * refusal, or simply a market where nothing qualifies - and the operator's
     * only recourse was to change a setting and wait a day to see whether it
     * helped. Every refusal is already recorded in the shadow book. This adds
     * them up.
     *
     * Counting SETUPS THE ENGINE WANTED AND DID NOT PUBLISH against what it
     * did publish, so the answer is a ratio rather than a bare number: nine
     * signals from ten candidates is a quiet market, nine from ninety is a
     * gate set too tight, and the raw count cannot tell those apart.
     *
     * @return array{published:int, refused:int, days:int, reasons:array<int,array{key:string,n:int}>}
     */
    public static function supply(int $days = 7): array
    {
        $days = max(1, min(90, $days));
        $since = time() - $days * 86400;
        $out = ['published' => 0, 'refused' => 0, 'days' => $days, 'reasons' => []];
        try {
            $pdo = Database::pdo();
            $q = $pdo->prepare(
                "SELECT COUNT(*) FROM signals WHERE created_at >= ? AND `signal` IN ('BUY','SELL')");
            $q->execute([$since]);
            $out['published'] = (int)$q->fetchColumn();
            $q->closeCursor();

            $r = $pdo->prepare(
                'SELECT blocked_by, COUNT(*) n FROM shadow_signals
                  WHERE created_at >= ? GROUP BY blocked_by ORDER BY n DESC');
            $r->execute([$since]);
            foreach ($r->fetchAll() as $row) {
                $n = (int)$row['n'];
                $out['refused'] += $n;
                $out['reasons'][] = ['key' => (string)$row['blocked_by'], 'n' => $n];
            }
            $r->closeCursor();
        } catch (\Throwable $e) {
            return $out;
        }
        return $out;
    }

    /**
     * How each grade has actually done.
     *
     * THE SITE PUTS A LETTER ON EVERY CALL AND HAS NEVER SAID WHAT IT IS WORTH.
     *
     * A grade is a promise: A+ is meant to mean something better than B, it is
     * printed beside every signal, it gates alerts through the member's
     * minimum-grade filter, and until now nothing anywhere measured whether
     * the letters rank in the order they claim. A reader could not check it
     * and neither could the operator.
     *
     * Everything is derived from the same settled record the public track
     * record uses, and a win is Outcomes::isWin - money made, not a label
     * reached - so this table and every other figure on the site agree about
     * what a win is.
     *
     * BOTH NUMBERS ARE HERE ON PURPOSE. Win rate is what a reader asks for and
     * expectancy is what decides whether the grade makes money, and this
     * engine is a live demonstration that the two can disagree: it wins about
     * three trades in four and its highest-scoring band is its least
     * profitable. A grade table showing only the win rate would rank the
     * grades in exactly the wrong order and look authoritative doing it.
     *
     * @param int|null $days  window in days, or null for the whole record
     * @return array<int,array<string,mixed>> best grade first, thin ones last
     */
    public static function byGrade(?int $days = null): array
    {
        // TrackRecord::sql() - the SAME publish/visibility/timeframe filter
        // every other number on the track record page is computed under (see
        // its own docblock). Without it this table counted every settled
        // signal regardless of a grade publish floor, a hidden coin or a
        // switched-off timeframe - so it could (and on a real install, did)
        // sum to MORE than the page's own "verified signals" headline, while
        // this function's docblock claimed the two agreed.
        $sql = "SELECT grade, outcome_r FROM signals
                 WHERE outcome IN ('confirmed', 'invalid') AND grade <> ''"
                . TrackRecord::sql();
        $args = [];
        if ($days !== null && $days > 0) {
            // closed_at, not created_at - TrackRecord::stats() windows the
            // headline this table sits beside on when a call SETTLED, not
            // when it was opened, for exactly the reason its own docblock
            // gives: a call opened five weeks ago and settled yesterday
            // belongs to the week it was decided in. A caller passing $days
            // here needs the same population the headline counts, or a "last
            // 7 days" grade table would silently describe a different set of
            // signals than the tiles above it.
            $sql .= ' AND closed_at >= ?';
            $args[] = time() - $days * 86400;
        }
        try {
            $stmt = Database::pdo()->prepare($sql);
            $stmt->execute($args);
            $rows = $stmt->fetchAll();
            $stmt->closeCursor();
        } catch (\Throwable $e) {
            return [];
        }
        $by = [];
        foreach ($rows as $r) {
            $g = (string)$r['grade'];
            $by[$g] ??= ['grade' => $g, 'n' => 0, 'wins' => 0, 'r_sum' => 0.0,
                         'win_r' => 0.0, 'win_n' => 0, 'loss_r' => 0.0, 'loss_n' => 0];
            $rr = (float)$r['outcome_r'];
            $by[$g]['n']++;
            $by[$g]['r_sum'] += $rr;
            if (self::isWin($r)) {
                $by[$g]['wins']++;
                $by[$g]['win_n']++;
                $by[$g]['win_r'] += $rr;
            } else {
                $by[$g]['loss_n']++;
                $by[$g]['loss_r'] += $rr;
            }
        }
        // The order the letters CLAIM, so a table reading top to bottom shows
        // whether the record agrees with the ranking the site advertises.
        return self::bucketStats($by, ['A+' => 0, 'A' => 1, 'B' => 2, 'C' => 3, 'D' => 4], 'grade');
    }

    /**
     * The record of each of the three signal types: 1:1, 1:2 and 1:3.
     *
     * The same question byGrade() asks, of the other thing a signal is. The
     * grade says how good the read was; the type says what the plan paid for
     * the risk, and those are independent - a coin can be read well and still
     * only have room for 1R. Both belong on the page for the same reason: an
     * operator narrowing the site to 1:3 only, and a member filtering to them,
     * are both entitled to see whether that is the type that actually makes
     * money here rather than the one that sounds best.
     *
     * The tier lives in the stored plan rather than in a column, so it is read
     * out of the indicators JSON in PHP. That is a scan, which is why this is
     * only ever called for a page and never inside the engine loop.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function byType(?int $days = null): array
    {
        // Same fix, same reason as byGrade() above: the published/visible
        // filter, not just the settled filter, or this table can disagree
        // with the headline count it is displayed right next to.
        $sql = "SELECT indicators, outcome_r FROM signals
                 WHERE outcome IN ('confirmed', 'invalid')"
                . TrackRecord::sql();
        $args = [];
        if ($days !== null && $days > 0) {
            // closed_at, not created_at - same reason as byGrade() above.
            $sql .= ' AND closed_at >= ?';
            $args[] = time() - $days * 86400;
        }
        try {
            $stmt = Database::pdo()->prepare($sql);
            $stmt->execute($args);
            $rows = $stmt->fetchAll();
            $stmt->closeCursor();
        } catch (\Throwable $e) {
            return [];
        }
        $by = [];
        foreach ($rows as $r) {
            $inds = json_decode((string)$r['indicators'], true);
            $lv = is_array($inds) ? ($inds['levels'] ?? null) : null;
            $tier = is_array($lv) ? (int)($lv['rr_tier'] ?? 0) : 0;
            // Calls stored before the types existed have no tier. They are
            // left out rather than bucketed as 1:1, which would put thousands
            // of untyped trades under a label they were never given.
            if ($tier < 1 || $tier > 3) {
                continue;
            }
            $k = '1:' . $tier;
            $by[$k] ??= ['grade' => $k, 'n' => 0, 'wins' => 0, 'r_sum' => 0.0,
                         'win_r' => 0.0, 'win_n' => 0, 'loss_r' => 0.0, 'loss_n' => 0];
            $rr = (float)$r['outcome_r'];
            $by[$k]['n']++;
            $by[$k]['r_sum'] += $rr;
            if (self::isWin($r)) {
                $by[$k]['wins']++;
                $by[$k]['win_n']++;
                $by[$k]['win_r'] += $rr;
            } else {
                $by[$k]['loss_n']++;
                $by[$k]['loss_r'] += $rr;
            }
        }
        return self::bucketStats($by, ['1:1' => 0, '1:2' => 1, '1:3' => 2], 'type');
    }

    /**
     * Win rate, expectancy, payoff and the payoff a bucket NEEDS to break even
     * at its own win rate - computed once, for every breakdown that shows them.
     *
     * Split out when the signal types arrived: two copies of this arithmetic
     * would be two chances for the grade table and the type table to disagree
     * about what a win is, and they answer to the same definition.
     *
     * @param  array<string,array<string,mixed>> $by
     * @param  array<string,int>                 $order
     * @return array<int,array<string,mixed>>
     */
    private static function bucketStats(array $by, array $order, string $label): array
    {
        $out = [];
        foreach ($by as $g => $c) {
            $n = (int)$c['n'];
            $winRate = $n > 0 ? $c['wins'] / $n : null;
            $avgWin = $c['win_n'] > 0 ? $c['win_r'] / $c['win_n'] : null;
            $avgLoss = $c['loss_n'] > 0 ? abs($c['loss_r'] / $c['loss_n']) : null;
            $payoff = ($avgWin !== null && $avgLoss !== null && $avgLoss > 0) ? $avgWin / $avgLoss : null;
            $need = ($winRate !== null && $winRate > 0) ? (1 - $winRate) / $winRate : null;
            $out[] = [
                // Kept as 'grade' as well as under its own name, so the table
                // partial that renders these does not need to know which
                // breakdown it was handed.
                'grade' => $g,
                $label => $g,
                'n' => $n,
                'wins' => (int)$c['wins'],
                'win_rate' => $winRate !== null ? round($winRate * 100, 1) : null,
                // The rate the sample supports, not the one it printed. Six
                // wins from nine is 66.7% and is also what a coin toss does.
                'wilson' => $n > 0 ? round(self::wilsonLower((int)$c['wins'], $n) * 100, 1) : null,
                'expectancy' => $n > 0 ? round($c['r_sum'] / $n, 3) : null,
                'total_r' => round($c['r_sum'], 2),
                'avg_win' => $avgWin !== null ? round($avgWin, 3) : null,
                'avg_loss' => $avgLoss !== null ? round(-$avgLoss, 3) : null,
                'payoff' => $payoff !== null ? round($payoff, 2) : null,
                'need_payoff' => $need !== null ? round($need, 2) : null,
                'pays' => ($payoff !== null && $need !== null) ? $payoff >= $need : null,
                'thin' => $n < self::GRADE_MIN_N,
                'rank' => $order[$g] ?? 9,
            ];
        }
        usort($out, static fn ($a, $b) => [$a['rank'], $a['grade']] <=> [$b['rank'], $b['grade']]);
        return $out;
    }

    /** Below this a grade's record is shown but never leaned on. */
    public const GRADE_MIN_N = 20;

    /**
     * Do the grades rank in the order they claim?
     *
     * Returns null when there is not enough to say. Deliberately judged on
     * expectancy: a ranking that holds on win rate and fails on money is not
     * a ranking that helps anybody.
     */
    public static function gradesRankTrue(?array $rows = null): ?bool
    {
        $rows = $rows ?? self::byGrade();
        $solid = array_values(array_filter($rows, static fn ($r) => !$r['thin'] && $r['expectancy'] !== null));
        if (count($solid) < 2) {
            return null;
        }
        for ($i = 1; $i < count($solid); $i++) {
            if ((float)$solid[$i]['expectancy'] > (float)$solid[$i - 1]['expectancy'] + 0.02) {
                return false;      // a lower grade beat a higher one, clearly
            }
        }
        return true;
    }

    public static function stats(string $symbol, string $tf, int $lastN = 30): array
    {
        $stmt = Database::pdo()->prepare(
            "SELECT outcome, outcome_r FROM signals
             WHERE symbol = ? AND tf = ? AND outcome IN ('confirmed', 'invalid')
             ORDER BY created_at DESC LIMIT " . (int)$lastN
        );
        $stmt->execute([$symbol, $tf]);
        $rows = $stmt->fetchAll();
        $total = count($rows);
        $wins = count(array_filter($rows, fn($r) => self::isWin($r)));
        return [
            'evaluated' => $total,
            // 'wins', not 'confirmed': these are the ones that made money, and
            // the chart used to read this key out loud as "signals confirmed".
            'wins'      => $wins,
            'winrate'   => $total > 0 ? round($wins / $total * 100, 1) : null,
        ];
    }
}
