<?php
declare(strict_types=1);

namespace SignalMasterAi;

/**
 * The multi-timeframe bias ladder.
 *
 * The engine used to look exactly one timeframe up. That is enough to catch a
 * 5m long fired straight into a falling 1h, and useless against a 5m long
 * fired into a falling *daily* while the 1h happens to be bouncing. Traders do
 * not read one timeframe up; they read down a ladder from the highest frame
 * that matters to the one they are executing on, and they treat the higher
 * frames as having more authority - a daily downtrend outranks a 15m uptrend,
 * not the other way round.
 *
 * So: read every timeframe above the one being analysed, score each one's
 * trend from -1 (clean downtrend) to +1 (clean uptrend), and combine them with
 * weights that fall off as the frames get shorter.
 *
 * Each frame's bias is cached under its own key, so the daily read is computed
 * once and shared by every symbol view and every lower timeframe until it goes
 * stale. Analysing 1m, 3m and 5m on one pair costs one set of fetches, not
 * three, which is what makes a seven-rung ladder affordable on shared hosting.
 */
class Mtf
{
    /** Highest authority first. Weights fall off down the ladder. */
    private const LADDER = ['1d' => 5.0, '4h' => 3.5, '1h' => 2.2, '15m' => 1.4,
                            '5m' => 0.9, '3m' => 0.6, '1m' => 0.4];


    /** Bars needed before a frame is allowed an opinion (EMA200 + slope). */
    private const MIN_BARS = 205;

    /**
     * Per-bar memo of each frame's bias, keyed symbol|timeframe|bar. Bounded
     * by the number of symbols on screen times the ladder depth, and lives
     * only for the request.
     */
    private static array $memo = [];

    /**
     * Weighted bias of every timeframe above $tf.
     *
     * Returns:
     *   bias    float  -1..+1, the weighted verdict of the frames above
     *   frames  array  per timeframe: dir, strength, why
     *   weight  float  total weight of the frames that actually answered
     *   top     string the highest frame that had an opinion
     *
     * An empty frames list means nothing above this timeframe could be read -
     * a fresh install, a thin pair, a provider outage. The caller must treat
     * that as "no information", never as "no conflict".
     */
    public static function bias(MarketData $md, string $symbol, string $tf, ?int $asOfMs = null): array
    {
        $out = ['bias' => 0.0, 'frames' => [], 'weight' => 0.0, 'top' => null];
        $sec = MarketData::TF_SECONDS[$tf] ?? 3600;
        $sum = 0.0;
        $wSum = 0.0;

        foreach (self::LADDER as $htf => $weight) {
            // Strictly above the timeframe being traded. A frame cannot be
            // its own higher-timeframe confirmation.
            if ((MarketData::TF_SECONDS[$htf] ?? 0) <= $sec) {
                continue;
            }
            $f = self::frameBias($md, $symbol, $htf, $asOfMs);
            if ($f === null) {
                continue;
            }
            $out['frames'][$htf] = $f;
            $out['top'] ??= $htf;
            $sum += $f['dir'] * $f['strength'] * $weight;
            $wSum += $weight;
        }

        if ($wSum > 0) {
            $out['bias'] = round($sum / $wSum, 4);
            $out['weight'] = round($wSum, 2);
        }
        return $out;
    }

    /**
     * One timeframe's trend, as direction and conviction.
     *
     * Four independent reads, because any one of them is noise on its own:
     * where price sits against the long EMA, whether the medium EMA is
     * rising, whether the EMAs are stacked, and whether structure is making
     * higher highs and higher lows. Conviction is the share of them that
     * agree, so a frame that is genuinely undecided contributes almost
     * nothing rather than casting a full vote on a coin flip.
     */
    private static function frameBias(MarketData $md, string $symbol, string $tf, ?int $asOfMs): ?array
    {
        $tfMs = (MarketData::TF_SECONDS[$tf] ?? 3600) * 1000;
        $ref = $asOfMs ?? (int)(microtime(true) * 1000);

        // Because only closed bars are read, a frame's bias is provably
        // constant for the whole of the bar currently forming - so it is
        // memoised per bar rather than per call. Live that saves a fetch per
        // symbol view; in a backtest it is the difference between reading the
        // daily series once per simulated day and once per simulated bar.
        $bucket = intdiv($ref, $tfMs);
        $memo = $symbol . '|' . $tf . '|' . $bucket;
        if (array_key_exists($memo, self::$memo)) {
            return self::$memo[$memo];
        }

        // Cached across requests too, so the daily read is shared by every
        // symbol view and every lower timeframe until the bar closes.
        $ttl = (int)max(60, min(3600, intdiv(MarketData::TF_SECONDS[$tf] ?? 3600, 4)));
        $key = 'mtfbias:' . $symbol . ':' . $tf . ':' . $bucket;
        $hit = $asOfMs === null ? Cache::get($key) : null;
        if (is_array($hit)) {
            return self::$memo[$memo] = $hit;
        }

        try {
            $candles = $asOfMs !== null
                ? $md->candlesAsOf($symbol, $tf, $asOfMs)
                : $md->candles($symbol, $tf);
        } catch (\Throwable $e) {
            // Provider down or pair unlisted on this frame. Memoised too: a
            // frame that cannot be read must not be re-attempted on every
            // single call for the rest of the bar.
            return self::$memo[$memo] = null;
        }

        // Closed bars only, exactly as every rule in the engine reads them. A
        // forming daily candle's "close" is just the current price, so a
        // trend read that includes it flickers all day and would put the
        // ladder at odds with the rules it is meant to arbitrate.
        $last = end($candles);
        if (is_array($last) && isset($last['t']) && (float)$last['t'] + $tfMs > $ref) {
            array_pop($candles);
        }

        $c = array_column($candles, 'c');
        $h = array_column($candles, 'h');
        $l = array_column($candles, 'l');
        $n = count($c);
        if ($n < self::MIN_BARS) {
            return self::$memo[$memo] = null;
        }
        $i = $n - 1;

        $ema50 = Indicators::ema($c, 50);
        $ema200 = Indicators::ema($c, 200);
        if ($ema50[$i] === null || $ema200[$i] === null || $ema50[$i - 5] === null) {
            return self::$memo[$memo] = null;
        }

        $votes = [];
        $votes['above_long'] = $c[$i] >= $ema200[$i] ? 1 : -1;
        $votes['slope'] = $ema50[$i] > $ema50[$i - 5] ? 1 : ($ema50[$i] < $ema50[$i - 5] ? -1 : 0);
        $votes['stack'] = $ema50[$i] > $ema200[$i] ? 1 : -1;
        $votes['structure'] = self::structure($h, $l);

        $net = array_sum($votes);
        $cast = count(array_filter($votes, static fn (int $v): bool => $v !== 0));
        if ($cast === 0) {
            return self::$memo[$memo] = null;
        }
        $dir = $net > 0 ? 1 : ($net < 0 ? -1 : 0);
        $strength = round(abs($net) / $cast, 3);

        $names = ['above_long' => 'price vs 200-EMA', 'slope' => '50-EMA slope',
                  'stack' => 'EMA stacking', 'structure' => 'swing structure'];
        $agree = [];
        foreach ($votes as $k => $v) {
            if ($v !== 0 && $v === $dir) {
                $agree[] = $names[$k];
            }
        }

        $f = [
            'dir' => $dir,
            'strength' => $strength,
            'why' => $agree ? implode(', ', $agree) : 'no clear read',
        ];
        if ($asOfMs === null) {
            Cache::set($key, $f, $ttl);
        }
        return self::$memo[$memo] = $f;
    }

    /**
     * Swing structure over the recent window: higher highs and higher lows
     * against lower highs and lower lows. Compares the extremes of the last
     * two 20-bar blocks, which is the cheapest honest read of "is this making
     * progress in one direction" that does not need a full swing detector.
     */
    private static function structure(array $h, array $l): int
    {
        $n = count($h);
        if ($n < 40) {
            return 0;
        }
        $recentHigh = max(array_slice($h, -20));
        $priorHigh  = max(array_slice($h, -40, 20));
        $recentLow  = min(array_slice($l, -20));
        $priorLow   = min(array_slice($l, -40, 20));
        if ($recentHigh > $priorHigh && $recentLow > $priorLow) {
            return 1;
        }
        if ($recentHigh < $priorHigh && $recentLow < $priorLow) {
            return -1;
        }
        return 0;   // one up one down: a range, and a range has no bias
    }

    /**
     * Rules that specifically call a turn rather than a continuation. These
     * are the only ones that can ever justify trading against the ladder -
     * "MACD crossed up" inside a daily downtrend is a continuation rule
     * firing in the wrong place, not a reversal thesis.
     */
    private const REVERSAL_RULES = [
        'rsi_divergence_bull', 'rsi_divergence_bear',
        'bullish_engulfing', 'bearish_engulfing', 'hammer', 'shooting_star',
        'fib_golden_zone', 'near_support', 'near_resistance',
        'bb_lower_touch', 'bb_upper_touch',
        'psar_flip_bull', 'psar_flip_bear',
    ];

    /**
     * Has a counter-trend setup like this one actually paid on this install?
     *
     * This is the only escape hatch from the alignment gate, and it is
     * deliberately evidence-gated rather than opinion-gated: a reversal rule
     * fired is not a reason to fade a daily trend. A reversal rule fired
     * *and* measured on this install across enough closed trades to show a
     * positive expectancy is.
     *
     * With no history, nothing qualifies and the gate holds - which is the
     * correct behaviour for a fresh install, not a limitation of it.
     *
     * Returns the qualifying rule's record, or null.
     */
    public static function validatedReversal(array $reasons, string $verdict, string $tf, int $minSamples, float $minR): ?array
    {
        $side = $verdict === 'BUY' ? 'bullish' : 'bearish';
        $fired = [];
        foreach ($reasons as $rs) {
            if (is_array($rs) && ($rs['side'] ?? '') === $side
                && in_array($rs['key'] ?? '', self::REVERSAL_RULES, true)) {
                $fired[(string)$rs['key']] = (string)($rs['rule'] ?? $rs['key']);
            }
        }
        if (!$fired) {
            return null;
        }

        // Measured over this timeframe only where there is enough of it: a
        // hammer on the daily and a hammer on the 1m are not the same rule.
        $stats = Cache::remember('rulestats:' . $tf, 900,
            static fn (): array => Outcomes::ruleStats(600, $tf), 900);
        if (!is_array($stats) || !$stats) {
            return null;
        }

        $best = null;
        foreach ($fired as $key => $name) {
            $s = $stats[$key] ?? null;
            $n = (int)($s['fired'] ?? 0);
            if ($n < $minSamples) {
                continue;
            }
            $avgR = $n > 0 ? (float)$s['r_sum'] / $n : 0.0;
            if ($avgR < $minR) {
                continue;
            }
            if ($best === null || $avgR > $best['avg_r']) {
                $best = ['key' => $key, 'name' => $name, 'n' => $n,
                         'avg_r' => round($avgR, 3),
                         'winrate' => round((int)($s['wins'] ?? 0) / $n * 100, 1)];
            }
        }
        return $best;
    }

    /** Human summary of the ladder, for the signal card and the reasons list. */
    public static function describe(array $b): string
    {
        if (!$b['frames']) {
            return 'No higher timeframe available';
        }
        $parts = [];
        foreach ($b['frames'] as $tf => $f) {
            $word = $f['dir'] > 0 ? 'up' : ($f['dir'] < 0 ? 'down' : 'flat');
            $parts[] = $tf . ' ' . $word . ' ' . round($f['strength'] * 100) . '%';
        }
        return implode(' · ', $parts);
    }
}
