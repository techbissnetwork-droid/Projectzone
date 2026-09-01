<?php
declare(strict_types=1);

namespace SignalMasterAi;

/**
 * The search for a trade shape that actually pays.
 *
 * WHY THIS IS THE ONE THAT MATTERS.
 *
 * Measured over 1,194 replayed trades, this engine wins about 65% of the time
 * and still loses money: the average win is a third to a half of the average
 * loss. At a 65% win rate a system needs a payoff of 0.54 to break even, and
 * none of the timeframes reaches it. Every preset in the product changes WHICH
 * SETUPS GET PUBLISHED, and no amount of choosing better setups repairs a
 * payoff of 0.33 - which is why a stricter preset never measured better than a
 * loose one, and why the honest answer to "make it more accurate" is that
 * accuracy was never the problem.
 *
 * What decides the payoff is the shape of the trade: how far the stop sits,
 * how far the targets sit, and how much of the position is banked at the first
 * one. Measured on ONE COIN, changing nothing but the exit policy:
 *
 *     take 100% at target 1   ->  65.3% win, -0.03R
 *     take  50% at target 1   ->  65.3% win, -0.01R
 *     take   0% at target 1   ->  36.1% win, +0.02R
 *
 * which reads as "less banked early, worse accuracy, better money".
 *
 * A WIDER SAMPLE REVERSES IT, AND THE ORIGINAL CLAIM SHOULD NOT HAVE BEEN
 * STATED AS SETTLED. Re-run across four coins on two timeframes - 148 decided
 * trades on the selective preset, 450 on balanced - banking everything at the
 * first target was the BEST of the four policies for both:
 *
 *     balanced   100/0  +0.048R   50/30  +0.025R   25/25  +0.017R   0/0  +0.010R
 *     selective  100/0  -0.090R   50/30  -0.112R   25/25  -0.118R   0/0  -0.120R
 *
 * One coin is a sample of one, and the conclusion drawn from it here has been
 * pointing this search at the wrong dimension. What the wider run shows is
 * that the exit policy moves expectancy by a few hundredths of an R in either
 * direction and does not decide whether a configuration makes money - the
 * selective preset loses under every one of the four. The search is still
 * worth running, because the stop distance it also varies is a larger lever
 * than the exit share, but it is not the answer on its own.
 *
 * The engine lab has searched this grid on expectancy since it was written,
 * and the objective was right. The search itself was unrunnable: thirty-odd
 * candidates times a coin count times four seconds is twenty minutes inside
 * one web request, behind a set_time_limit() shared hosting ignores - measured
 * dying at exactly that limit with nothing saved. This is the same grid, read
 * from Backtest::levelGrid() so there is still one definition of it, queued on
 * LongJob so it finishes, plus the exit policy - the dimension that measured
 * largest.
 *
 * TP1/TP2 IN A CANDIDATE ARE NOT SEARCHED, only carried. Backtest::levelGrid()
 * fixes them per stop rather than varying them, because the engine builds
 * every published target as a multiple of the stop's own distance - a
 * candidate's tp1/tp2 never reached the replay it rode along on. Earlier
 * versions of this grid varied them anyway, which meant several rows below
 * a given stop always measured identically and "thirty-odd candidates" was
 * mostly the same handful of replays counted several times over.
 */
class LevelSearch
{
    /** Exit policies to try: [share at TP1, share at TP2] as percentages. */
    public const EXITS = [
        [100, 0],   // everything at the first target
        [50, 30],   // the shipped scale-out
        [25, 25],   // mostly runner
        [0, 0],     // nothing banked early; the whole position runs
    ];

    /** The smallest decided sample a candidate needs before it can win. */
    public const MIN_DECIDED = 20;

    /**
     * One item per candidate per coin.
     *
     * @return array<int,array<int,mixed>>
     */
    public static function queue(array $params): array
    {
        $tf = self::tf($params);
        $syms = self::symbols($params);
        $out = [];
        foreach (self::candidates($params) as $i => $c) {
            foreach ($syms as $sym) {
                $out[] = [$i, $sym, $tf];
            }
        }
        return $out;
    }

    /**
     * Every geometry to try.
     *
     * The grid is read from Backtest::levelGrid() rather than copied here, so
     * there is one definition of what the candidate set is.
     *
     * @return array<int,array{sl:float,tp1:float,tp2:float,ex1:int,ex2:int}>
     */
    public static function candidates(array $params): array
    {
        $withExits = ($params['exits'] ?? '1') === '1';
        $exits = $withExits ? self::EXITS : [[null, null]];
        $out = [];
        foreach (Backtest::levelGrid() as [$sl, $tp1, $tp2]) {
            foreach ($exits as [$e1, $e2]) {
                $out[] = ['sl' => $sl, 'tp1' => $tp1, 'tp2' => $tp2, 'ex1' => $e1, 'ex2' => $e2];
            }
        }
        return $out;
    }

    public static function seed(array $params): array
    {
        return ['tf' => self::tf($params), 'cells' => [], 'at' => time()];
    }

    /** Replay one coin under one candidate geometry. */
    public static function work(array $item, array $results, array $params): array
    {
        [$idx, $sym, $tf] = $item;
        $cands = self::candidates($params);
        $c = $cands[$idx] ?? null;
        if ($c === null) {
            return $results;
        }
        $overrides = ['levels' => ['sl' => $c['sl'], 'tp1' => $c['tp1'], 'tp2' => $c['tp2']]];
        if ($c['ex1'] !== null) {
            $overrides['settings'] = [
                'tp1_partial_pct' => (string)$c['ex1'],
                'tp2_partial_pct' => (string)$c['ex2'],
            ];
        }
        $r = Backtest::run($sym, $tf, null, $overrides);

        $cell = $results['cells'][$idx] ?? [
            'sl' => $c['sl'], 'tp1' => $c['tp1'], 'tp2' => $c['tp2'],
            'ex1' => $c['ex1'], 'ex2' => $c['ex2'],
            'signals' => 0, 'n' => 0, 'wins' => 0,
            'r_sum' => 0.0, 'win_r' => 0.0, 'win_n' => 0, 'loss_r' => 0.0, 'loss_n' => 0,
        ];
        $cell['signals'] += (int)($r['signals'] ?? 0);
        foreach (($r['trades'] ?? []) as $t) {
            if (($t['outcome'] ?? '') === 'expired') {
                continue;
            }
            $rr = (float)($t['r'] ?? 0);
            $cell['n']++;
            $cell['r_sum'] += $rr;
            if ($rr > 0) {
                $cell['wins']++;
                $cell['win_n']++;
                $cell['win_r'] += $rr;
            } else {
                $cell['loss_n']++;
                $cell['loss_r'] += $rr;
            }
        }
        $results['cells'][$idx] = $cell;
        $results['at'] = time();
        return $results;
    }

    public static function finish(array $results, array $params): array
    {
        $results['done_at'] = time();
        return $results;
    }

    /**
     * Candidates worth reading, best expectancy first.
     *
     * Expectancy decides, never win rate. A 40% hit rate at 2R beats a 65% hit
     * rate at 0.4, and optimising the wrong one is precisely how this engine
     * came to have a scoreboard that looks good and an account that does not.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function ranked(array $results): array
    {
        $rows = [];
        foreach (($results['cells'] ?? []) as $idx => $c) {
            $n = (int)$c['n'];
            if ($n < 1) {
                continue;
            }
            $avgWin = $c['win_n'] > 0 ? $c['win_r'] / $c['win_n'] : null;
            $avgLoss = $c['loss_n'] > 0 ? abs($c['loss_r'] / $c['loss_n']) : null;
            $winRate = $c['wins'] / $n;
            $rows[] = [
                'idx' => $idx,
                'sl' => $c['sl'], 'tp1' => $c['tp1'], 'tp2' => $c['tp2'],
                'ex1' => $c['ex1'], 'ex2' => $c['ex2'],
                'n' => $n,
                'win_rate' => round($winRate * 100, 1),
                'expectancy' => round($c['r_sum'] / $n, 4),
                'payoff' => ($avgWin !== null && $avgLoss !== null && $avgLoss > 0)
                    ? round($avgWin / $avgLoss, 2) : null,
                'need_payoff' => $winRate > 0 ? round((1 - $winRate) / $winRate, 2) : null,
                'enough' => $n >= self::MIN_DECIDED,
            ];
        }
        usort($rows, static fn ($a, $b) => $b['expectancy'] <=> $a['expectancy']);
        return $rows;
    }

    /** The winner, or null when nothing cleared the sample floor. */
    public static function best(array $results): ?array
    {
        foreach (self::ranked($results) as $row) {
            if ($row['enough']) {
                return $row;
            }
        }
        return null;
    }

    /**
     * Write a candidate into the live configuration.
     *
     * Deliberately explicit and separate from the search: a measurement that
     * applies itself is a measurement an operator cannot check first, and this
     * one moves the shape of every trade the site publishes.
     */
    public static function apply(array $row): array
    {
        $tf = (string)($row['tf'] ?? '');
        $changed = [];
        $levels = Backtest::levelsSetting();
        $levels[$tf] = ['sl' => (float)$row['sl'], 'tp1' => (float)$row['tp1'], 'tp2' => (float)$row['tp2']];
        Database::setSetting('tf_levels', json_encode($levels));
        $changed[] = 'stop and target distances for ' . $tf;
        // APPLIED IS NOT THE SAME AS IN EFFECT.
        //
        // Learned per-timeframe levels are read only while tf_levels_enabled
        // is on. An operator who switched that off months ago would press
        // Apply here, be told it worked, and publish trades with the old
        // geometry - the exact "the setting went somewhere nothing reads"
        // failure that made every preset's threshold inert. Said out loud
        // rather than silently flipping a switch the operator chose.
        if (Database::setting('tf_levels_enabled', '1') !== '1') {
            $changed[] = 'BUT learned per-timeframe levels are switched off, so this has no '
                . 'effect until you turn them back on';
        }
        if (($row['ex1'] ?? null) !== null) {
            Database::setSetting('tp1_partial_pct', (string)(int)$row['ex1']);
            Database::setSetting('tp2_partial_pct', (string)(int)$row['ex2']);
            $changed[] = 'exit policy ' . (int)$row['ex1'] . '/' . (int)$row['ex2'];
        }
        return $changed;
    }

    public static function tf(array $params): string
    {
        $tf = (string)($params['tf'] ?? '1h');
        return $tf !== '' ? $tf : '1h';
    }

    /** @return array<int,string> */
    public static function symbols(array $params): array
    {
        $cap = max(1, min(12, (int)($params['max_symbols'] ?? 4)));
        try {
            $rows = Database::pdo()->query(
                'SELECT symbol FROM symbols WHERE enabled = 1 ORDER BY symbol'
            )->fetchAll();
        } catch (\Throwable $e) {
            return [];
        }
        return array_slice(array_values(array_filter(array_map(
            static fn ($r) => (string)($r['symbol'] ?? ''),
            $rows
        ))), 0, $cap);
    }
}
