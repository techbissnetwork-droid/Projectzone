<?php
declare(strict_types=1);

namespace SignalMasterAi;

/**
 * Smart-money concepts, as features rather than signals.
 *
 * The distinction matters more than the indicators do. Read as signals, SMC
 * produces a trade on every chart: there is always an order block somewhere
 * behind price, always an unfilled gap, always a swing high with liquidity
 * resting above it. Sold that way it is astrology with a vocabulary.
 *
 * Read as features, the same structures answer a narrower and far more useful
 * question - not "should I trade" but "where am I standing". Price sitting in
 * a discount, inside a bullish order block it has not yet mitigated, just
 * after sweeping the lows, is a materially different place to be long from
 * price in a premium with nothing beneath it. None of that is a reason to buy
 * on its own. All of it changes what a reason to buy is worth.
 *
 * So this class detects and measures; it never decides. Everything it returns
 * is a measurement with a distance and an age attached, and the engine votes
 * on it only where the context lines up - all of it inside the structure
 * category, whose correlation cap means SMC can shade a verdict but can never
 * produce one by itself.
 *
 * Terms, since the field names them badly:
 *   order block   the last opposing candle before the move that broke
 *                 structure - where the orders that caused the break sat
 *   FVG           a three-candle gap price skipped through, leaving an
 *                 imbalance it often returns to fill
 *   liquidity     the stops resting beyond an obvious high or low
 *   sweep         price taking that liquidity and immediately rejecting
 *   BOS           a close beyond the last swing in the direction of trend
 *   CHOCH         the first such break AGAINST the trend - the character
 *                 change that precedes most reversals
 *   breaker       an order block price broke through, now acting as the
 *                 opposite kind of level
 *   premium /     where price sits in the current dealing range: above the
 *   discount      midpoint is expensive to buy, below it is cheap
 */
class Smc
{
    /** How far back structures stay interesting, in bars. */
    private const LOOKBACK = 120;

    /** Equal highs/lows must agree within this fraction of ATR. */
    private const EQ_TOLERANCE_ATR = 0.15;

    /**
     * A sweep shallower than this took nobody's stops - it is a wick, and
     * treating every wick past a pivot as a liquidity raid made the feature
     * fire on more than half of all charts, which is another way of saying it
     * carried no information.
     */
    private const SWEEP_MIN_ATR = 0.15;

    /**
     * Gaps older than this stop being decision points. Price sitting inside a
     * gap it left eighty bars ago says almost nothing; sitting in one from
     * ten bars ago is the market re-testing an imbalance it just created.
     */
    private const FVG_MAX_AGE = 30;

    /**
     * The same idea for the structures that had no ceiling at all.
     *
     * Order blocks, breakers and equal-highs shelves were reported at any age
     * whatsoever, while BOS, CHoCH, sweeps and gaps were all bounded. A block
     * from three hundred bars ago is not a level the market is reacting to,
     * and quoting its age in the reason text does not stop it casting a vote
     * at full weight. Admin-settable, because how long a level stays live is a
     * judgement about the instrument rather than a constant.
     */
    public const STRUCTURE_MAX_AGE_DEFAULT = 60;

    private static function structureMaxAge(): int
    {
        return max(5, min(500, (int)Database::setting(
            'smc_structure_max_age', (string)self::STRUCTURE_MAX_AGE_DEFAULT)));
    }

    /**
     * Every structure in one pass.
     *
     * $swings is the tiered swing set the engine already computed - passed in
     * rather than recomputed, so SMC and the rest of the rule base always
     * agree about where the pivots are.
     *
     * Returns a flat, JSON-safe map. Distances are in ATR and ages in bars so
     * every field is comparable across pairs and timeframes; nulls mean "not
     * present", never zero.
     */
    public static function analyse(
        array $opens, array $highs, array $lows, array $closes,
        array $swings, ?float $atr
    ): array {
        $n = count($closes);
        $i = $n - 1;
        $out = [
            'bos' => null, 'choch' => null, 'sweep' => null,
            'order_block' => null, 'fvg' => null, 'breaker' => null,
            'eq_highs' => null, 'eq_lows' => null,
            'range' => null, 'zone' => null, 'zone_pct' => null,
            'structure_dir' => 0,
        ];
        if ($n < 30 || $atr === null || $atr <= 0) {
            return $out;
        }

        $sh = array_values(array_filter($swings['highs'] ?? [], static fn ($x) => $x >= $n - self::LOOKBACK));
        $sl = array_values(array_filter($swings['lows'] ?? [], static fn ($x) => $x >= $n - self::LOOKBACK));
        $price = (float)$closes[$i];

        // ---- Dealing range, premium and discount ---------------------------
        // The range worth pricing against is the last confirmed swing low to
        // the last confirmed swing high. Anything else - session highs, a
        // round number - is a level someone chose rather than one the market
        // printed.
        if ($sh && $sl) {
            $rHigh = (float)$highs[end($sh)];
            $rLow  = (float)$lows[end($sl)];
            // Price that has run beyond either pivot has expanded the range,
            // not left it: whatever it has printed since the later of the two
            // swings IS the new boundary. Without this a market at new highs
            // reads as 118% of its range, which is not a number.
            for ($k = max((int)end($sh), (int)end($sl)); $k <= $i; $k++) {
                $rHigh = max($rHigh, (float)$highs[$k]);
                $rLow  = min($rLow, (float)$lows[$k]);
            }
            if ($rHigh > $rLow && ($rHigh - $rLow) >= 0.5 * $atr) {
                $pct = ($price - $rLow) / ($rHigh - $rLow);
                $out['range'] = ['high' => round($rHigh, 8), 'low' => round($rLow, 8)];
                $out['zone_pct'] = round($pct, 4);
                $out['zone'] = $pct >= 0.7 ? 'premium' : ($pct <= 0.3 ? 'discount' : 'equilibrium');
            }
        }

        // ---- Break of structure and change of character --------------------
        $breaks = self::breaks($highs, $lows, $closes, $sh, $sl, $n);
        $out['bos'] = $breaks['bos'];
        $out['choch'] = $breaks['choch'];
        $out['structure_dir'] = $breaks['dir'];

        // ---- Liquidity sweep -----------------------------------------------
        $out['sweep'] = self::sweep($highs, $lows, $closes, $sh, $sl, $atr, $i);

        // ---- Equal highs and lows ------------------------------------------
        // Two swings at the same price are where stops pile up, and price has
        // a habit of going to collect them. Recorded as a magnet with a
        // direction, not as a reason to trade.
        $out['eq_highs'] = self::equalLevels($highs, $sh, $atr, $price, true, $i);
        $out['eq_lows']  = self::equalLevels($lows, $sl, $atr, $price, false, $i);

        // ---- Order blocks and breakers -------------------------------------
        $ob = self::orderBlock($opens, $highs, $lows, $closes, $sh, $sl, $atr, $i);
        $out['order_block'] = $ob['block'];
        $out['breaker'] = $ob['breaker'];

        // ---- Fair value gaps -----------------------------------------------
        $out['fvg'] = self::nearestFvg($highs, $lows, $atr, $price, $i);

        return $out;
    }

    /**
     * Walk the bars forward, tracking which way structure is pointing, and
     * label every close beyond a pivot.
     *
     * The direction is carried rather than inferred from the last two swings.
     * Inferring it needs a clean higher-high-and-higher-low sequence, and real
     * charts spend much of their time expanding - a higher high AND a lower
     * low - where that test returns "no trend" and both events vanish exactly
     * when structure is most informative. Carrying the state instead makes the
     * definition the standard one: a break the same way as the last break
     * continues structure, and the first break the other way changes it.
     *
     * Closes only. A wick through a level is a sweep, and reading it as a
     * break is the single most common way this gets misapplied.
     *
     * Returns the most recent of each, plus the direction structure now points.
     */
    private static function breaks(array $highs, array $lows, array $closes, array $sh, array $sl, int $n): array
    {
        $out = ['bos' => null, 'choch' => null, 'dir' => 0];
        if (!$sh || !$sl) {
            return $out;
        }
        $from = max(1, $n - self::LOOKBACK);
        $dir = 0;

        for ($b = $from; $b < $n; $b++) {
            // The pivots standing immediately before this bar are the only
            // levels that can be broken by it.
            $priorHigh = null;
            foreach ($sh as $p) {
                if ($p < $b) {
                    $priorHigh = $p;
                } else {
                    break;
                }
            }
            $priorLow = null;
            foreach ($sl as $p) {
                if ($p < $b) {
                    $priorLow = $p;
                } else {
                    break;
                }
            }

            foreach ([[true, $priorHigh], [false, $priorLow]] as [$up, $pivot]) {
                if ($pivot === null) {
                    continue;
                }
                $level = $up ? (float)$highs[$pivot] : (float)$lows[$pivot];
                $broke = $up ? ((float)$closes[$b] > $level) : ((float)$closes[$b] < $level);
                // The previous bar must still have been on the old side, or
                // this is the middle of a move rather than the break of it.
                $was = $up ? ((float)$closes[$b - 1] <= $level) : ((float)$closes[$b - 1] >= $level);
                if (!$broke || !$was) {
                    continue;
                }
                $eventDir = $up ? 1 : -1;
                $rec = ['dir' => $eventDir, 'level' => round($level, 8), 'age' => $n - 1 - $b];
                if ($dir !== 0 && $eventDir !== $dir) {
                    $out['choch'] = $rec;   // structure turned
                } else {
                    $out['bos'] = $rec;     // structure continued
                }
                $dir = $eventDir;
            }
        }
        $out['dir'] = $dir;
        return $out;
    }

    /**
     * A liquidity sweep: price traded beyond a swing and closed back inside
     * within a couple of bars. That is the market taking the stops resting
     * there and rejecting the level, which is the opposite of accepting it.
     */
    private static function sweep(array $highs, array $lows, array $closes, array $sh, array $sl, float $atr, int $i): ?array
    {
        $best = null;
        foreach ([[$sh, true], [$sl, false]] as [$pivots, $isHigh]) {
            foreach (array_reverse($pivots) as $p) {
                // Only sweeps of the last few bars are actionable context.
                for ($b = $i; $b > max($p, $i - 5); $b--) {
                    $level = $isHigh ? (float)$highs[$p] : (float)$lows[$p];
                    $pierced = $isHigh ? ((float)$highs[$b] > $level) : ((float)$lows[$b] < $level);
                    $rejected = $isHigh ? ((float)$closes[$b] < $level) : ((float)$closes[$b] > $level);
                    if (!$pierced || !$rejected) {
                        continue;
                    }
                    $depth = $isHigh
                        ? ((float)$highs[$b] - $level) / $atr
                        : ($level - (float)$lows[$b]) / $atr;
                    // Too shallow to have taken anything; too deep and it is a
                    // breakout that came back, not a stop raid.
                    if ($depth < self::SWEEP_MIN_ATR || $depth > 2.0) {
                        continue;
                    }
                    $cand = [
                        // Sweeping the highs is bearish: the buyers above got
                        // filled and there is nobody left to buy.
                        'dir'   => $isHigh ? -1 : 1,
                        'level' => round($level, 8),
                        'depth' => round($depth, 3),
                        'age'   => $i - $b,
                    ];
                    // Freshest wins; on a tie the deeper raid does. Without
                    // the tie-break, a high and a low swept on the same bar
                    // would always resolve bearish - whichever direction the
                    // loop happened to scan first.
                    if ($best === null || $cand['age'] < $best['age']
                        || ($cand['age'] === $best['age'] && $cand['depth'] > $best['depth'])) {
                        $best = $cand;
                    }
                }
            }
        }
        return $best;
    }

    /**
     * Two or more swings at effectively the same price. Equal highs are a pool
     * of buy stops above the market and equal lows a pool of sell stops below,
     * and price is drawn to both because the orders there are not optional.
     */
    private static function equalLevels(array $series, array $pivots, float $atr, float $price, bool $isHigh, int $iNow = 0): ?array
    {
        $c = count($pivots);
        if ($c < 2) {
            return null;
        }
        $tol = self::EQ_TOLERANCE_ATR * $atr;
        // Newest pair first: an old shelf has usually already been taken.
        for ($a = $c - 1; $a >= 1; $a--) {
            for ($b = $a - 1; $b >= max(0, $a - 4); $b--) {
                $pa = (float)$series[$pivots[$a]];
                $pb = (float)$series[$pivots[$b]];
                if (abs($pa - $pb) > $tol) {
                    continue;
                }
                // Old shelves have usually been taken already, and one that
                // has not is not the magnet a fresh one is.
                if ($iNow - $pivots[$a] > self::structureMaxAge()) {
                    continue;
                }
                $level = ($pa + $pb) / 2;
                // Already taken out - the liquidity is gone, so is the magnet.
                if ($isHigh ? $price > $level : $price < $level) {
                    continue;
                }
                // How many pivots actually sit on this shelf, not just the two
                // that matched. Three highs at one price is a bigger pool of
                // stops than two, and reporting a flat 2 threw that away.
                $count = 0;
                $newest = $pivots[$a];
                foreach ($pivots as $p) {
                    if (abs((float)$series[$p] - $level) <= $tol) {
                        $count++;
                        $newest = max($newest, $p);
                    }
                }
                return [
                    'level'    => round($level, 8),
                    'count'    => $count,
                    'distance' => round(abs($price - $level) / $atr, 3),
                    // Bars since the shelf was last touched. A flat zero here
                    // claimed every shelf was printed on the current bar.
                    'age'      => count($series) - 1 - $newest,
                ];
            }
        }
        return null;
    }

    /**
     * The order block behind the most recent structural break, and whether
     * price has come back to it.
     *
     * The block is the last opposing candle before the impulse that broke the
     * level - the bar the move originated from. Price returning to it is
     * "mitigation", and it is the only moment an order block is worth
     * anything; an unvisited block three hundred bars back is scenery.
     *
     * A block price closed clean through is a breaker: the level failed, and
     * failed levels tend to hold from the other side.
     */
    private static function orderBlock(array $opens, array $highs, array $lows, array $closes, array $sh, array $sl, float $atr, int $i): array
    {
        $out = ['block' => null, 'breaker' => null];
        $n = count($closes);
        $price = (float)$closes[$i];

        // Find the most recent displacement: a close beyond the prior swing.
        foreach ([[$sh, true], [$sl, false]] as [$pivots, $up]) {
            if (!$pivots) {
                continue;
            }
            for ($b = $n - 1; $b >= max(1, $n - self::LOOKBACK); $b--) {
                $prior = null;
                foreach ($pivots as $p) {
                    if ($p < $b) {
                        $prior = $p;
                    }
                }
                if ($prior === null) {
                    continue;
                }
                $level = $up ? (float)$highs[$prior] : (float)$lows[$prior];
                if ($up ? (float)$closes[$b] <= $level : (float)$closes[$b] >= $level) {
                    continue;
                }
                // THE BAR BEFORE MUST STILL HAVE BEEN ON THE OLD SIDE.
                //
                // breaks() has had this guard from the start; this copy of the
                // same idea never got it. Without it every bar of a trend
                // qualifies as a displacement, because in a trend every close
                // is beyond the prior swing - and since this loop walks
                // backwards from the newest bar and stops at the first match,
                // the "order block" it found was always a handful of bars old.
                // A freshness figure that is always fresh is not a
                // measurement, and it was feeding a vote and a chart label
                // through an entire trend.
                if ($up ? (float)$closes[$b - 1] > $level : (float)$closes[$b - 1] < $level) {
                    continue;
                }

                // Walk back from the bar BEFORE the break for the last candle
                // against the direction of the move. That bar is the block.
                // Starting at the break itself would let the breaking bar be
                // its own order block, which is not a thing.
                $obIdx = null;
                for ($k = $b - 1; $k > max(0, $b - 12); $k--) {
                    $bullBar = (float)$closes[$k] > (float)$opens[$k];
                    if ($up ? !$bullBar : $bullBar) {
                        $obIdx = $k;
                        break;
                    }
                }
                if ($obIdx === null) {
                    continue;
                }

                $top = (float)max($opens[$obIdx], $closes[$obIdx], $highs[$obIdx]);
                $bot = (float)min($opens[$obIdx], $closes[$obIdx], $lows[$obIdx]);
                $dir = $up ? 1 : -1;

                // Broken through from the far side: it is a breaker now, and
                // it argues the other way.
                $broken = $up ? ($price < $bot) : ($price > $top);
                $rec = [
                    'dir'      => $dir,
                    'top'      => round($top, 8),
                    'bottom'   => round($bot, 8),
                    'age'      => $i - $obIdx,
                    // 0 while price is inside the block; otherwise how far
                    // away it is, in ATR.
                    'distance' => round($price >= $bot && $price <= $top ? 0.0
                        : min(abs($price - $top), abs($price - $bot)) / $atr, 3),
                    'mitigated' => $price >= $bot && $price <= $top,
                ];
                // Freshest wins. Taking whichever direction happened to be
                // scanned first would have made a bullish block outrank a
                // bearish one printed forty bars later, every time.
                if ($rec['age'] > self::structureMaxAge()) {
                    break;   // too old to be a level anybody is trading off
                }
                if ($broken) {
                    $rec['dir'] = -$dir;
                    if ($out['breaker'] === null || $rec['age'] < $out['breaker']['age']) {
                        $out['breaker'] = $rec;
                    }
                } elseif ($out['block'] === null || $rec['age'] < $out['block']['age']) {
                    $out['block'] = $rec;
                }
                break;
            }
        }
        return $out;
    }

    /**
     * The nearest unfilled fair value gap: three bars where the first and
     * third do not overlap, leaving a band price moved through without
     * trading. Price fills most of them eventually, which makes an open gap
     * both a target and a place a move can stall.
     */
    private static function nearestFvg(array $highs, array $lows, float $atr, float $price, int $i): ?array
    {
        $best = null;
        // Bounded by the staleness limit rather than the general lookback:
        // anything older is discarded below anyway, so scanning back further
        // is just work.
        for ($k = $i - 1; $k >= max(2, $i - self::FVG_MAX_AGE); $k--) {
            $a = $k - 2;
            $c = $k;
            $bull = (float)$lows[$c] > (float)$highs[$a];
            $bear = (float)$highs[$c] < (float)$lows[$a];
            if (!$bull && !$bear) {
                continue;
            }
            $top = $bull ? (float)$lows[$c] : (float)$lows[$a];
            $bot = $bull ? (float)$highs[$a] : (float)$highs[$c];
            $size = ($top - $bot) / $atr;
            if ($size < 0.15) {
                continue;   // noise, not an imbalance
            }
            // FILLED AT ANY POINT SINCE IT FORMED, NOT MERELY RIGHT NOW.
            //
            // This asked whether the CURRENT price is through the band. A gap
            // that was fully traded through ten bars ago and then left behind
            // read as untouched, so a dead imbalance was still being offered
            // as a decision point and a target. The imbalance is gone the
            // moment price trades through it; whether price happens to be
            // there today is a different question.
            $filled = false;
            for ($m = $c + 1; $m <= $i; $m++) {
                if ($bull ? (float)$lows[$m] < $bot : (float)$highs[$m] > $top) {
                    $filled = true;
                    break;
                }
            }
            if ($filled) {
                continue;
            }
            $inside = $price >= $bot && $price <= $top;
            $cand = [
                'dir'      => $bull ? 1 : -1,
                'top'      => round($top, 8),
                'bottom'   => round($bot, 8),
                'size'     => round($size, 3),
                'age'      => $i - $c,
                'distance' => round($inside ? 0.0 : min(abs($price - $top), abs($price - $bot)) / $atr, 3),
                'inside'   => $inside,
            ];
            if ($best === null || $cand['distance'] < $best['distance']) {
                $best = $cand;
            }
        }
        return $best;
    }
}
