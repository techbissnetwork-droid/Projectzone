<?php
declare(strict_types=1);

namespace SignalMasterAi;

/**
 * Every preset, every timeframe, measured on the same candles.
 *
 * WHY THIS EXISTS, AND WHY IT REPORTS PAYOFF RATHER THAN JUST ACCURACY.
 *
 * The question was "which preset is more accurate". Measured over 1,194
 * replayed trades on eight coins and three timeframes, this engine answered:
 *
 *     tf     n     win%    avg R   avg win  avg loss  payoff
 *     15m   296   65.2%   -0.143    +0.346   -1.060    0.33
 *     1h    447   64.7%   -0.100    +0.450   -1.107    0.41
 *     4h    451   66.1%   -0.012    +0.519   -1.046    0.50
 *
 * It wins about two trades in three AND LOSES MONEY DOING IT. The average win
 * is a third to a half of the average loss, and at a 65% win rate a system
 * needs a payoff of 0.54 just to break even. Accuracy was never the problem
 * here; the shape of the trade was. Tightening which setups get published -
 * which is what every preset does - cannot fix a payoff of 0.33, and that is
 * exactly why a stricter preset kept measuring no better than a loose one.
 *
 * So this reports win rate because it was asked for, and reports the payoff
 * ratio and the break-even payoff beside it, because a preset comparison that
 * shows only accuracy points the operator at the number that was already fine.
 *
 * Runs under LongJob: one (preset x timeframe x coin) replay per step, a few
 * steps per cron tick, resumable.
 */
class PresetTest
{
    /** How many coins to replay. More is a better sample and a longer wait. */
    public const MAX_SYMBOLS = 12;

    /**
     * One queue item per preset, timeframe and coin.
     *
     * Ordered coin-major so that an operator watching it fill in sees every
     * preset get its first coin before any preset gets its second: a run
     * stopped half way is then a fair partial comparison rather than a
     * complete picture of Selective and nothing about the other two.
     *
     * @return array<int,array{0:string,1:string,2:string}>
     */
    public static function queue(array $params): array
    {
        $presets = self::presets($params);
        $tfs = self::timeframes($params);
        $syms = self::symbols($params);
        $out = [];
        foreach ($syms as $sym) {
            foreach ($tfs as $tf) {
                foreach ($presets as $p) {
                    $out[] = [$p, $tf, $sym];
                }
            }
        }
        return $out;
    }

    /** Empty tallies, so a partial report has the right shape from step one. */
    public static function seed(array $params): array
    {
        $cells = [];
        foreach (self::presets($params) as $p) {
            foreach (self::timeframes($params) as $tf) {
                $cells[$p . '|' . $tf] = self::zero();
            }
        }
        return ['cells' => $cells, 'symbols_done' => [], 'unused' => [], 'at' => time()];
    }

    private static function zero(): array
    {
        return [
            'signals' => 0, 'n' => 0, 'wins' => 0, 'expired' => 0,
            'r_sum' => 0.0, 'win_r' => 0.0, 'win_n' => 0, 'loss_r' => 0.0, 'loss_n' => 0,
            'coins' => 0,
            // Deepest peak-to-trough run, in R, on any ONE coin.
            //
            // Deliberately per coin rather than across the whole set. A true
            // portfolio drawdown needs every trade in chronological order, and
            // keeping that list for nine cells would put the job state past
            // what a settings row holds - this codebase has already lost data
            // once to a blob that outgrew its column. The worst single coin is
            // cheap, well defined, and answers the question an operator is
            // actually asking: how ugly does this get before it comes back.
            'dd' => 0.0,
        ];
    }

    /**
     * Replay one coin, on one timeframe, under one preset.
     *
     * The preset rides on the engine instance through Backtest's override
     * argument - nothing is written to the live configuration, so a job that
     * is cancelled half way leaves the site exactly as it found it. That is
     * the same promise the A/B comparison makes, kept the same way.
     */
    public static function work(array $item, array $results, array $params): array
    {
        [$preset, $tf, $sym] = $item;
        $overrides = ['settings' => self::overridesFor($preset, $tf)];
        $r = Backtest::run($sym, $tf, null, $overrides);

        $key = $preset . '|' . $tf;
        $c = $results['cells'][$key] ?? self::zero();
        $c['signals'] += (int)($r['signals'] ?? 0);
        $c['expired'] += (int)($r['expired'] ?? 0);
        $c['coins']++;
        foreach (($r['trades'] ?? []) as $t) {
            if (($t['outcome'] ?? '') === 'expired') {
                continue;
            }
            $rr = (float)($t['r'] ?? 0);
            $c['n']++;
            $c['r_sum'] += $rr;
            if ($rr > 0) {
                $c['wins']++;
                $c['win_n']++;
                $c['win_r'] += $rr;
            } else {
                $c['loss_n']++;
                $c['loss_r'] += $rr;
            }
        }
        // Keys the engine was handed and never read. Collected per preset,
        // because "Selective was measured" is only true of the parts of
        // Selective that reached a decision - and a replay of a partly ignored
        // configuration reports exactly like one that worked.
        foreach (($r['overrides_unused'] ?? []) as $k) {
            $results['unused'][$preset][$k] = true;
        }
        // Drawdown for this coin's own run, in the order the trades happened.
        $seq = array_values(array_filter(($r['trades'] ?? []),
            static fn ($t) => ($t['outcome'] ?? '') !== 'expired'));
        usort($seq, static fn ($a, $b) => ($a['at'] ?? 0) <=> ($b['at'] ?? 0));
        $equity = 0.0;
        $peak = 0.0;
        $worst = 0.0;
        foreach ($seq as $t) {
            $equity += (float)($t['r'] ?? 0);
            $peak = max($peak, $equity);
            $worst = max($worst, $peak - $equity);
        }
        $c['dd'] = max((float)($c['dd'] ?? 0), $worst);

        $results['cells'][$key] = $c;
        $results['symbols_done'][$sym] = true;
        $results['at'] = time();
        return $results;
    }

    /**
     * A preset's engine values, minus the ones a single-timeframe replay
     * cannot honestly test.
     *
     * enabled_intervals is dropped deliberately. The replay IS one timeframe,
     * so a preset that scans 1h and 4h and one that also scans 15m are
     * identical inside a 1h run - passing the setting through would change
     * nothing while implying it had been tested. The timeframe choice is
     * instead answered by comparing the per-timeframe rows, which is the
     * honest way round: the report shows what each timeframe did, and the
     * preset's interval list decides which of those rows it would have taken.
     */
    private static function overridesFor(string $preset, string $tf): array
    {
        $vals = Presets::values($preset);
        unset($vals['enabled_intervals']);
        return $vals;
    }

    /** Which of a preset's timeframes actually count towards its headline. */
    public static function scans(string $preset, string $tf): bool
    {
        return in_array($tf, Presets::intervals($preset), true);
    }

    /**
     * Turn the tallies into the numbers a decision gets made on.
     *
     * @return array<string,mixed>
     */
    public static function finish(array $results, array $params): array
    {
        $results['done_at'] = time();
        return $results;
    }

    /**
     * Derived figures for one tally. Kept here rather than in the template so
     * the break-even rule has exactly one definition.
     *
     * @return array<string,mixed>
     */
    public static function derive(array $c): array
    {
        $n = (int)$c['n'];
        $winRate = $n > 0 ? $c['wins'] / $n : null;
        $avgWin = $c['win_n'] > 0 ? $c['win_r'] / $c['win_n'] : null;
        $avgLoss = $c['loss_n'] > 0 ? abs($c['loss_r'] / $c['loss_n']) : null;
        $payoff = ($avgWin !== null && $avgLoss !== null && $avgLoss > 0)
            ? $avgWin / $avgLoss : null;
        // What the payoff would have to be, at THIS win rate, to break even:
        //   win% * W = (1 - win%) * L   ->   W / L = (1 - win%) / win%
        // A system can be wrong about this in both directions, which is why it
        // is shown rather than asserted: a 40% hit rate is fine at 1.5, and a
        // 65% hit rate is not fine at 0.4.
        $need = ($winRate !== null && $winRate > 0) ? (1 - $winRate) / $winRate : null;
        return [
            'n' => $n,
            // The win rate the sample actually supports, not the one it
            // happened to print. Six wins from nine is 66.7% and could easily
            // be a coin toss; the lower bound says so and converges on the
            // point estimate as evidence accumulates.
            'wilson' => $n > 0 ? round(Outcomes::wilsonLower((int)$c['wins'], $n) * 100, 1) : null,
            'max_dd' => round((float)($c['dd'] ?? 0), 2),
            'signals' => (int)$c['signals'],
            'coins' => (int)$c['coins'],
            'win_rate' => $winRate !== null ? round($winRate * 100, 1) : null,
            'expectancy' => $n > 0 ? round($c['r_sum'] / $n, 3) : null,
            'avg_win' => $avgWin !== null ? round($avgWin, 3) : null,
            'avg_loss' => $avgLoss !== null ? round(-$avgLoss, 3) : null,
            'payoff' => $payoff !== null ? round($payoff, 2) : null,
            'need_payoff' => $need !== null ? round($need, 2) : null,
            'pays' => ($payoff !== null && $need !== null) ? $payoff >= $need : null,
            'expired' => (int)$c['expired'],
        ];
    }

    /** @return array<int,string> */
    public static function presets(array $params): array
    {
        $want = $params['presets'] ?? array_keys(Presets::DEFS);
        return array_values(array_filter(
            (array)$want,
            static fn ($p) => isset(Presets::DEFS[$p])
        )) ?: array_keys(Presets::DEFS);
    }

    /** @return array<int,string> */
    public static function timeframes(array $params): array
    {
        // Every timeframe any of the presets under test would scan - so the
        // rows exist to answer "what would dropping 15m have cost me".
        $out = [];
        foreach (self::presets($params) as $p) {
            foreach (Presets::intervals($p) as $tf) {
                $out[$tf] = true;
            }
        }
        $order = ['1m', '3m', '5m', '15m', '30m', '1h', '2h', '4h', '6h', '12h', '1d'];
        $keys = array_keys($out);
        usort($keys, static fn ($a, $b) => array_search($a, $order, true) <=> array_search($b, $order, true));
        return $keys;
    }

    /** @return array<int,string> */
    public static function symbols(array $params): array
    {
        $cap = max(1, min(self::MAX_SYMBOLS, (int)($params['max_symbols'] ?? self::MAX_SYMBOLS)));
        try {
            $rows = Database::pdo()->query(
                'SELECT symbol FROM symbols WHERE enabled = 1 ORDER BY symbol'
            )->fetchAll();
        } catch (\Throwable $e) {
            return [];
        }
        $syms = array_values(array_filter(array_map(
            static fn ($r) => (string)($r['symbol'] ?? ''),
            $rows
        )));
        return array_slice($syms, 0, $cap);
    }
}
