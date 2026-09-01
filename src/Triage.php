<?php
declare(strict_types=1);

namespace SignalMasterAi;

/**
 * Where the loss is coming from.
 *
 * The dashboard already reconciles a good win rate with a bad expectancy - it
 * prints the average winner against the average loser and the hit rate that
 * payoff would need. That says WHY the arithmetic comes out negative. It does
 * not say WHICH CALLS are doing it, and on a record that is 85% five-minute
 * trades the answer to those two questions is not the same.
 *
 * This splits the published, settled record along the four lines the operator
 * has a switch for, and reports each slice with an error bar rather than a
 * number. A slice is called out only when its 95% interval sits ENTIRELY below
 * zero on a sample worth reading: "this frame is losing" is a claim, and a
 * claim made from eleven trades is how an operator gets talked into switching
 * off the thing that was working.
 *
 * THE COUNTERFACTUAL IS THE POINT. Knowing 5m loses is interesting; knowing
 * the record would read +0.04R instead of -0.11R without it is the decision.
 * It is computed by subtraction rather than by a second query - the slices in
 * a dimension are disjoint and cover the record, so the remainder is exact.
 *
 * AND IT IS AN UPPER BOUND, NOT A FORECAST. The slice was chosen by looking at
 * this sample, so removing it flatters this sample by construction; the same
 * cut on next month's trades will do less. Every caller has to say so, which
 * is why the reason is written here rather than left to whoever renders it.
 */
final class Triage
{
    /**
     * Below this, a slice is not evidence.
     *
     * Thirty is where the standard error of a mean R stops being wider than
     * the effect anybody is looking for. It is deliberately higher than the
     * twenty the public breakdowns grey at: that threshold decides whether to
     * show a number quietly, this one decides whether to tell an operator to
     * turn something off.
     */
    public const MIN_N = 30;

    /**
     * How much the record has to improve for a slice to be worth naming.
     *
     * BELOW ZERO IS NOT THE TEST. On a record that is losing overall, every
     * large slice of it has an interval dipping below zero - and the first
     * version of this panel duly flagged BUY and SELL simultaneously, which is
     * not advice anybody can act on, and flagged the biggest timeframe as
     * losing when dropping it would have made the record WORSE (its -0.100R
     * was better than the -0.104R average it was being compared against).
     *
     * The test that means something is the counterfactual: does taking this
     * slice out measurably improve what is left. Two hundredths of an R per
     * trade is the floor for "measurably" - below that the operator is being
     * asked to throw away business for a difference that will not survive next
     * month's trades.
     */
    public const MIN_GAIN = 0.02;

    /** Human names for the dimensions, and where the switch for each one is. */
    public const DIMS = [
        'timeframe' => ['Timeframe', 'settings.php#market',
            'Settings &rsaquo; Market &rsaquo; Timeframes offered'],
        'session'   => ['Trading session', 'engine.php',
            'Engine lab &rsaquo; Expectancy by trading session'],
        'side'      => ['Direction', 'knowledge.php',
            'Knowledge base &rsaquo; rule weights'],
        'grade'     => ['Grade', 'settings.php#signals',
            'Settings &rsaquo; Signals &rsaquo; Publish floor'],
    ];

    /**
     * @param  array<int,string> $intervals every interval the install supports
     * @return array{
     *   total:int, wins:int, avg_r:?float, win_rate:?float,
     *   slices:array<int,array<string,mixed>>, at:int
     * }
     */
    public static function report(array $intervals): array
    {
        $visible = TrackRecord::sql();
        $sql = "SELECT tf, created_at, `signal`, grade, outcome_r
                FROM signals
                WHERE outcome IN ('confirmed','invalid')" . $visible;

        // Streamed, not fetched. This is every settled published signal on the
        // install and the count only grows; one pass with four accumulators
        // costs the same at ten thousand rows as at ten.
        $acc = [];                       // dim => key => [n, wins, sum, sumsq]
        $n = 0; $wins = 0; $sum = 0.0; $sumsq = 0.0;
        foreach (Database::pdo()->query($sql) as $row) {
            $r = (float)$row['outcome_r'];
            $n++;
            $sum += $r;
            $sumsq += $r * $r;
            // A win is a trade that made money, not one that reached a label.
            // Same definition as TrackRecord, or this panel would disagree
            // with the tiles above it on the same page.
            if ($r > 0) {
                $wins++;
            }
            $keys = [
                'timeframe' => (string)$row['tf'],
                'session'   => Sessions::forTime((int)$row['created_at']),
                'side'      => (string)$row['signal'],
                'grade'     => ((string)$row['grade']) !== '' ? (string)$row['grade'] : 'ungraded',
            ];
            foreach ($keys as $dim => $k) {
                if ($k === '') {
                    continue;
                }
                if (!isset($acc[$dim][$k])) {
                    $acc[$dim][$k] = ['n' => 0, 'wins' => 0, 'sum' => 0.0, 'sumsq' => 0.0];
                }
                $acc[$dim][$k]['n']++;
                $acc[$dim][$k]['sum'] += $r;
                $acc[$dim][$k]['sumsq'] += $r * $r;
                if ($r > 0) {
                    $acc[$dim][$k]['wins']++;
                }
            }
        }

        $slices = [];
        foreach ($acc as $dim => $byKey) {
            // A dimension with one value has nothing to say: "all of your
            // trades are the losing slice" is the headline figure again.
            if (count($byKey) < 2) {
                continue;
            }
            foreach ($byKey as $k => $v) {
                $sn = (int)$v['n'];
                if ($sn < 1) {
                    continue;
                }
                $avg = $v['sum'] / $sn;
                $ci = self::interval($sn, $v['sum'], $v['sumsq']);
                $restN = $n - $sn;
                $slices[] = [
                    'dim'      => $dim,
                    'dim_label' => self::DIMS[$dim][0] ?? $dim,
                    'key'      => (string)$k,
                    'label'    => $dim === 'session' ? Sessions::label((string)$k) : (string)$k,
                    'n'        => $sn,
                    'win_rate' => round($v['wins'] / $sn * 100, 1),
                    'avg_r'    => round($avg, 3),
                    'total_r'  => round($v['sum'], 2),
                    'ci'       => $ci === null ? null : [round($ci[0], 3), round($ci[1], 3)],
                    // Proven losing: enough sample, and the whole interval
                    // below zero. Not "avg_r < 0", which half a coin flip
                    // satisfies.
                    'losing'   => $sn >= self::MIN_N && $ci !== null && $ci[1] < 0,
                    // What share of the whole record this slice is. A slice
                    // that IS the record cannot be advice: "switch off the
                    // thing you do" is the headline figure with a button on
                    // it, and the remainder it leaves is too small to promise
                    // anything about.
                    'share'    => $n > 0 ? round($sn / $n * 100, 1) : 0.0,
                    // Only when enough is left to be worth stating. A
                    // counterfactual over four trades is not a forecast, it is
                    // an anecdote with a plus sign.
                    'without'  => $restN >= self::MIN_N ? [
                        'n'        => $restN,
                        'avg_r'    => round(($sum - $v['sum']) / $restN, 3),
                        'win_rate' => round(($wins - $v['wins']) / $restN * 100, 1),
                    ] : null,
                    // Both set below, once the slice's own figures exist.
                    'gain'       => null,
                    'actionable' => false,
                ];
                $last = array_key_last($slices);
                $overall = $n > 0 ? $sum / $n : 0.0;
                $w = $slices[$last]['without'];
                $slices[$last]['gain'] = $w === null ? null : round($w['avg_r'] - $overall, 3);
                $slices[$last]['actionable'] =
                    $slices[$last]['losing']
                    && $w !== null
                    && $slices[$last]['share'] <= 80.0
                    && $slices[$last]['gain'] >= self::MIN_GAIN;
            }
        }

        // Ranked by what removing it would BUY, not by what it lost.
        //
        // Sorting by total R given up put the biggest slice on top by
        // construction - it has the most trades, so it carries the most of
        // everything, including the losses it is merely a large share of. The
        // operator's question is "what do I gain", and that is the gain
        // column. Slices with no counterfactual sort last rather than first.
        usort($slices, static function (array $a, array $b): int {
            $ga = $a['gain'] ?? -INF;
            $gb = $b['gain'] ?? -INF;
            return $gb <=> $ga ?: $a['total_r'] <=> $b['total_r'];
        });

        // ALL OF THEM AT ONCE, PER DIMENSION.
        //
        // The decision is rarely one slice. A record that is half five-minute
        // trades and a third fifteen-minute ones, both measurably losing, is
        // answered by dropping BOTH - and the two counterfactuals cannot be
        // read together, because each was computed with the other still in.
        // Disjoint slices subtract exactly, so the combined remainder is a
        // real figure rather than an estimate.
        $combined = [];
        foreach ($acc as $dim => $byKey) {
            $flagged = array_values(array_filter(
                $slices,
                static fn(array $x): bool => $x['dim'] === $dim && $x['losing'] && $x['actionable']
            ));
            if (count($flagged) < 2) {
                continue;
            }
            $cutN = 0; $cutW = 0; $cutSum = 0.0;
            foreach ($flagged as $f) {
                $cutN += $f['n'];
                $cutW += (int)round($f['win_rate'] / 100 * $f['n']);
                $cutSum += (float)$f['total_r'];
            }
            $restN = $n - $cutN;
            if ($restN < self::MIN_N) {
                continue;
            }
            $restAvg = ($sum - $cutSum) / $restN;
            // The combined cut has to clear the same bar as a single one, or
            // the row is two justified decisions adding up to an unjustified
            // third.
            if ($restAvg - ($n > 0 ? $sum / $n : 0.0) < self::MIN_GAIN) {
                continue;
            }
            $combined[$dim] = [
                'dim_label' => self::DIMS[$dim][0] ?? $dim,
                'keys'      => array_column($flagged, 'label'),
                'cut_n'     => $cutN,
                'n'         => $restN,
                'avg_r'     => round($restAvg, 3),
                'win_rate'  => round(($wins - $cutW) / $restN * 100, 1),
                'gain'      => round($restAvg - ($n > 0 ? $sum / $n : 0.0), 3),
            ];
        }

        return [
            'total'    => $n,
            'wins'     => $wins,
            'avg_r'    => $n > 0 ? round($sum / $n, 3) : null,
            'win_rate' => $n > 0 ? round($wins / $n * 100, 1) : null,
            'slices'   => $slices,
            'combined' => $combined,
            'at'       => time(),
        ];
    }

    /**
     * Cached, because the admin dashboard is refreshed far more often than a
     * trade settles.
     *
     * THE VERSION IN THE KEY IS LOAD-BEARING. Caught in testing: a cached
     * report from before the `gain` field existed was served to a renderer
     * that reads it, and every row printed +0.000 next to a correct-looking
     * table. A stale cache after a deploy is not an empty panel, it is a wrong
     * one - bump this whenever the returned shape changes.
     */
    public static function cached(array $intervals, int $ttl = 300): array
    {
        return Cache::remember('triage_v2', $ttl, static fn(): array => self::report($intervals));
    }

    /**
     * The exit arithmetic.
     *
     * The slice report answers "which calls are losing". This answers the
     * question underneath it, which on a lot of records is the only one that
     * matters: WHAT DOES A LOSS COST, AND WHAT DOES A WIN PAY.
     *
     * The install that prompted this reads, over 409 settled calls: stop hit
     * 146 times at -1.11R, TP1 reached 134 times at +0.18R, TP2 62 at +0.63R,
     * TP3 56 at +0.99R. Nothing in that is a broken slice. The engine picks
     * direction well - two calls in three end in profit - and still loses,
     * because the most common winning exit banks a fifth of what the most
     * common losing exit gives up.
     *
     * TWO NUMBERS COME OUT OF THIS AND BOTH ARE ACTIONABLE.
     *
     * The first is the cost of a stop. A 1R stop should settle at -1.00R by
     * definition; anything below that is fees, spread and the gap between the
     * stop price and the fill. At -1.11R the site is paying an eleventh of its
     * risk on every loss before the market has done anything, and that is
     * either the round-trip cost setting being optimistic or the frames being
     * too fast for the fee to disappear into the move.
     *
     * The second is the shortfall: given how often the stop is hit and what it
     * costs, this is what the average winner HAS to make, against what it
     * does. It turns "the winners are too small" into a number with a target
     * beside it, and the lever for it is the stop's own distance - every
     * published target is built as a multiple of it, so widening or tightening
     * the stop reshapes the whole ladder at once - and how much of the
     * position comes off at the first target.
     */
    public static function exits(): array
    {
        $sql = "SELECT outcome, outcome_note, outcome_r
                FROM signals
                WHERE outcome IN ('confirmed','invalid','expired')" . TrackRecord::sql();

        $rows = [];
        $n = 0;
        $lossN = 0; $lossSum = 0.0;
        $winN = 0;  $winSum = 0.0;
        foreach (Database::pdo()->query($sql) as $row) {
            $r = (float)$row['outcome_r'];
            $label = View::shortOutcome($row['outcome_note'] ?? '', (string)$row['outcome']);
            if ($label === '') {
                $label = (string)$row['outcome'];
            }
            if (!isset($rows[$label])) {
                $rows[$label] = ['n' => 0, 'sum' => 0.0];
            }
            $rows[$label]['n']++;
            $rows[$label]['sum'] += $r;
            $n++;
            // Split on the money, not on the label - a trade that reached TP1
            // and was then scratched below break-even is a loser however it is
            // filed, and counting it as a win is what makes a payoff ratio
            // look survivable when it is not.
            if ($r > 0) {
                $winN++; $winSum += $r;
            } elseif ($r < 0) {
                $lossN++; $lossSum += $r;
            }
        }
        if ($n === 0) {
            return ['n' => 0, 'rows' => [], 'stop_avg' => null, 'cost_r' => null,
                    'win_avg' => null, 'need_avg' => null, 'short' => null, 'loss_n' => 0, 'win_n' => 0];
        }

        foreach ($rows as $k => $v) {
            $rows[$k]['avg_r'] = round($v['sum'] / $v['n'], 3);
            $rows[$k]['share'] = round($v['n'] / $n * 100, 1);
        }
        uasort($rows, static fn(array $a, array $b): int => $b['n'] <=> $a['n']);

        $lossAvg = $lossN > 0 ? $lossSum / $lossN : null;
        $winAvg  = $winN > 0 ? $winSum / $winN : null;
        // What the average winner has to make for the record to come out flat,
        // given how often it loses and what a loss actually costs.
        $need = ($winN > 0 && $lossAvg !== null) ? ($lossN * abs($lossAvg)) / $winN : null;

        return [
            'n'        => $n,
            'rows'     => $rows,
            'loss_n'   => $lossN,
            'win_n'    => $winN,
            // Named stop_avg because that is what it is on almost every record:
            // the average settled loser. Not filtered to the "stop hit" label,
            // because a scratched TP1 is a loser too and the arithmetic below
            // has to count it.
            'stop_avg' => $lossAvg === null ? null : round($lossAvg, 3),
            // Above 0 = each loss costs more than the risk it was sized to.
            'cost_r'   => $lossAvg === null ? null : round(abs($lossAvg) - 1.0, 3),
            'win_avg'  => $winAvg === null ? null : round($winAvg, 3),
            'need_avg' => $need === null ? null : round($need, 3),
            'short'    => ($need === null || $winAvg === null) ? null : round($need - $winAvg, 3),
        ];
    }

    /** Target distances the curve is measured at, in R. */
    public const TARGET_GRID = [0.2, 0.3, 0.4, 0.5, 0.75, 1.0, 1.25, 1.5, 2.0, 2.5, 3.0];

    /**
     * The "not enough to say" answer, with every key the full one has.
     *
     * A short return that omits half its keys makes the caller responsible for
     * remembering which - and the caller is a template, where a missing key is
     * a warning printed into the page.
     *
     * @return array<string,mixed>
     */
    private static function emptyCurve(int $n): array
    {
        return ['n' => $n, 'rows' => [], 'ceiling' => null, 'stopped' => 0,
                'best' => null, 'loss_avg' => null];
    }

    /**
     * What win rate each first-target distance would actually have produced.
     *
     * "Can we get the win rate to 80%" has an arithmetic answer and a real
     * answer, and they are different.
     *
     * The arithmetic answer is yes, trivially: move the first target close
     * enough to entry and almost everything reaches it. It is also useless,
     * because the payoff falls with the distance and the break-even hit rate
     * rises faster than the hit rate does. At a payoff of 0.25 - a 0.25R
     * winner against a 1R loser - 80% is exactly break-even, and every point
     * of win rate bought below that loses money faster than it wins it.
     *
     * The real answer is a CEILING, and it is the number this exists to print.
     * Every settled signal already carries mfe_r and mae_r: the furthest price
     * went in its favour and the furthest it went against, in units of the
     * risk it was sized to. A trade whose worst excursion reached the stop is
     * a loser at EVERY target distance - no target, however near, can be
     * reached by a trade that was stopped out before it got there. So the
     * highest win rate any target policy can produce on this record is the
     * share of trades that never touched their stop, and if that share is 62%
     * then 80% is not a target to aim at, it is arithmetic that does not
     * exist. Getting past it needs a wider stop or better entries, and a wider
     * stop makes every loss cost more.
     *
     * MEASURED, NOT SIMULATED. The level optimiser replays the engine over
     * stored candles to answer a nearby question, and takes minutes. This
     * reads what the calls this site actually published went on to do, and
     * takes one query. The two are worth having separately: the optimiser can
     * change the entries, this cannot, and that is exactly why this one's
     * ceiling is a ceiling.
     *
     * ORDERING IS UNKNOWN AND HANDLED CONSERVATIVELY. Excursions say how far
     * price went each way, not which happened first, so a trade that reached
     * both its target and its stop is counted as the stop - the same rule the
     * verifier applies to a candle that spans both. The curve is therefore a
     * floor on the hit rate at each distance, which is the safe direction for
     * a number somebody is about to set a target from.
     */
    public static function targetCurve(): array
    {
        $sql = "SELECT mfe_r, mae_r, outcome_r FROM signals
                WHERE outcome IN ('confirmed','invalid')
                  AND mfe_r IS NOT NULL AND mae_r IS NOT NULL" . TrackRecord::sql();
        $rows = [];
        $lossSum = 0.0; $lossN = 0;
        $stopped = 0;
        try {
            foreach (Database::pdo()->query($sql) as $r) {
                $rows[] = [(float)$r['mfe_r'], (float)$r['mae_r']];
                if ((float)$r['outcome_r'] < 0) {
                    $lossSum += (float)$r['outcome_r'];
                    $lossN++;
                }
                if ((float)$r['mae_r'] <= -1.0) {
                    $stopped++;
                }
            }
        } catch (\Throwable $e) {
            return self::emptyCurve(0);
        }
        $n = count($rows);
        if ($n < self::MIN_N) {
            // Below this the curve is a shape drawn through noise, and it
            // would be read as advice about where to put a target.
            return self::emptyCurve($n);
        }
        // What a loss actually costs, from the record rather than from the 1R
        // it was sized to - fees and slippage are the difference.
        $lossAvg = $lossN > 0 ? $lossSum / $lossN : -1.0;

        $out = [];
        $best = null;
        foreach (self::TARGET_GRID as $d) {
            $w = 0;
            foreach ($rows as [$mfe, $mae]) {
                if ($mfe >= $d && $mae > -1.0) {
                    $w++;
                }
            }
            $p = $w / $n;
            // First-order: reach the target and bank d, or take the loss. It
            // ignores the partial-at-TP1 ladder, which makes it optimistic
            // about small targets and pessimistic about large ones - stated
            // where it is rendered rather than buried here.
            $exp = $p * $d + (1 - $p) * $lossAvg;
            $row = ['tp1' => $d, 'win_rate' => round($p * 100, 1), 'exp_r' => round($exp, 3)];
            $out[] = $row;
            if ($best === null || $exp > $best['exp_r']) {
                $best = $row;
            }
        }

        return [
            'n'        => $n,
            'rows'     => $out,
            // The highest win rate any target distance could produce: the
            // share that never reached the stop at all.
            'ceiling'  => round(($n - $stopped) / $n * 100, 1),
            'stopped'  => $stopped,
            'best'     => $best,
            'loss_avg' => round($lossAvg, 3),
        ];
    }

    /**
     * 95% interval for the mean, from the running sums.
     *
     * The ordinary standard error, the same one the public track record uses
     * for its headline claim - so a slice called losing here is losing by the
     * same standard the site applies to itself. Null below two samples, where
     * a variance does not exist.
     *
     * @return array{0:float,1:float}|null
     */
    private static function interval(int $n, float $sum, float $sumsq): ?array
    {
        if ($n < 2) {
            return null;
        }
        $mean = $sum / $n;
        $var = ($sumsq - $n * $mean * $mean) / ($n - 1);
        if ($var < 0) {
            $var = 0.0;   // floating-point dust on a constant-R slice
        }
        $se = sqrt($var / $n);
        return [$mean - 1.96 * $se, $mean + 1.96 * $se];
    }
}
