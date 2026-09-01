<?php
declare(strict_types=1);

namespace SignalMasterAi;

/**
 * How much this setup is actually worth, broken into the things that make a
 * setup good and then calibrated against what has really happened.
 *
 * The number used to be |score| rescaled: divide by the total available weight,
 * multiply by a constant chosen to make the output look like a percentage, and
 * print it with a "%" after it. That is not a probability. It cannot be wrong,
 * because it never claimed anything falsifiable - and a reader seeing "82%"
 * reasonably assumes four out of five of these work out.
 *
 * Two things are needed to fix that, and they are different problems.
 *
 * MULTI-DIMENSION. A single sum cannot distinguish setups that fail in
 * different ways. Six rules screaming momentum into a daily downtrend and six
 * rules agreeing across momentum, trend, structure and volume produce the same
 * score and are not the same trade. So quality is scored on six independent
 * axes and combined so that one bad axis hurts: a setup is only as strong as
 * the dimension it is weakest on, which is how a trader actually reads one.
 *
 * CALIBRATION. Quality still has to be mapped onto reality. The map is built
 * from this install's own verified outcomes - bucket past signals by the same
 * quality measure, count what fraction won, and report the Wilson lower bound
 * so a thin bucket cannot boast. Until enough outcomes exist there is no map,
 * and the honest thing is to say so rather than dress the raw score up as a
 * probability. That is what confidence_basis is for, and it is surfaced.
 */
class Confidence
{
    /**
     * Axis weights. Evidence and breadth dominate because they are what the
     * engine measures most directly; cost is small but never zero, because a
     * setup risking 0.4% on a pair quoting 0.15% spread is a worse trade than
     * the identical setup somewhere cheap.
     */
    private const WEIGHTS = [
        'evidence'  => 1.0,
        'breadth'   => 0.9,
        'alignment' => 0.8,
        'structure' => 0.5,
        'regime'    => 0.6,
        'cost'      => 0.4,
    ];

    /** Below this on any axis, the whole setup is dragged down hard. */
    private const FLOOR = 0.05;

    /**
     * Score the six dimensions, 0..1 each.
     *
     * $ctx carries what the engine already knows: the reasons list, category
     * breakdown, the multi-timeframe bias, the SMC read, ADX and choppiness,
     * the plan, and the execution conditions.
     */
    public static function dimensions(array $ctx): array
    {
        $side = (string)($ctx['signal'] ?? 'NEUTRAL');
        $dir = $side === 'BUY' ? 1 : ($side === 'SELL' ? -1 : 0);
        // A NEUTRAL verdict still has a leaning, and scoring it against a
        // direction of zero makes every axis meaningless - "is this rule for
        // or against nothing" has no answer, and evidence and breadth both
        // collapse to zero regardless of what the chart is doing. Score the
        // side the evidence is actually on, so the number reads as "how good
        // would this be if it triggered".
        if ($dir === 0) {
            $score = (float)($ctx['score'] ?? 0);
            $dir = $score >= 0 ? 1 : -1;
        }

        // --- Evidence: how one-sided the rules that spoke actually were ----
        // Not the raw score. Nine rules to one and nine to eight can sum to
        // the same number, and they are not the same evidence.
        $wFor = 0.0;
        $wAgainst = 0.0;
        foreach ((array)($ctx['reasons'] ?? []) as $rs) {
            if (!is_array($rs) || empty($rs['side'])) {
                continue;
            }
            $w = abs((float)($rs['weight'] ?? 0));
            if ($w <= 0) {
                continue;   // zero-weight entries are notes, not votes
            }
            $isFor = ($rs['side'] === 'bullish') === ($dir > 0);
            if ($isFor) {
                $wFor += $w;
            } else {
                $wAgainst += $w;
            }
        }
        $spoke = $wFor + $wAgainst;
        $evidence = $spoke > 0 ? $wFor / $spoke : 0.0;
        // Rescale: 50% agreement is a coin flip and should score zero, not a
        // half. Everything below even money is worthless either way.
        $evidence = max(0.0, ($evidence - 0.5) * 2);

        // --- Breadth: how many independent categories agreed ---------------
        $agreeing = (int)($dir > 0 ? ($ctx['bull_categories'] ?? 0) : ($ctx['bear_categories'] ?? 0));
        $opposing = (int)($dir > 0 ? ($ctx['bear_categories'] ?? 0) : ($ctx['bull_categories'] ?? 0));
        // Four agreeing categories is the practical ceiling; more is rare and
        // no more informative. Opposing categories subtract.
        $breadth = max(0.0, min(1.0, ($agreeing - $opposing * 0.5) / 4));

        // --- Alignment: the higher-timeframe ladder ------------------------
        // No ladder is not the same as a neutral ladder. With nothing to read,
        // this axis has no opinion and sits at the neutral 0.5 rather than
        // punishing a setup for the server's missing history.
        $bias = $ctx['mtf_bias'] ?? null;
        $alignment = 0.5;
        if ($bias !== null && !empty($ctx['mtf_frames'])) {
            // -1 fully against, +1 fully with, mapped to 0..1.
            $alignment = max(0.0, min(1.0, ((float)$bias * $dir + 1) / 2));
        }

        // --- Structure: where price is standing ----------------------------
        $structure = self::structureScore($ctx['smc'] ?? null, $dir);

        // --- Regime: is this the kind of market the setup needs? -----------
        $regime = self::regimeScore($ctx, $dir);

        // --- Cost: what the market charges to take this trade --------------
        // Spread and fees measured against the distance to the stop. A setup
        // whose costs eat a tenth of its risk is materially worse than the
        // same setup where they eat a hundredth.
        $cost = 1.0;
        $entry = (float)($ctx['levels']['entry'] ?? 0);
        $stop  = (float)($ctx['levels']['stop_loss'] ?? 0);
        if ($entry > 0 && $stop > 0 && $entry !== $stop) {
            $riskPct = abs($entry - $stop) / $entry * 100;
            $spread = (float)($ctx['spread_pct'] ?? 0);
            $fees = max(0.0, (float)($ctx['round_trip_cost_pct'] ?? 0.1));
            if ($riskPct > 0) {
                $drag = ($spread + $fees) / $riskPct;   // fraction of risk lost to costs
                // A third of the risk going to costs scores zero. That cutoff
                // is a judgement rather than a measurement, but the direction
                // is not: on a 0.6% stop, a 0.11% round trip really is a fifth
                // of everything being risked, and the old confidence number
                // could not see that at all.
                $cost = max(0.0, 1.0 - min(1.0, $drag * 3));
            }
        }

        return [
            'evidence'  => round($evidence, 4),
            'breadth'   => round($breadth, 4),
            'alignment' => round($alignment, 4),
            'structure' => round($structure, 4),
            'regime'    => round($regime, 4),
            'cost'      => round($cost, 4),
        ];
    }

    /**
     * Does the smart-money picture support this direction?
     *
     * Neutral (0.5) when nothing is in range, because "no structures nearby"
     * is genuinely no information - it is not evidence against.
     */
    private static function structureScore(?array $smc, int $dir): float
    {
        if (!$smc || $dir === 0) {
            return 0.5;
        }
        $votes = [];

        // Buying at a discount and selling at a premium is right; the reverse
        // is paying up for the same idea.
        $zone = (string)($smc['zone'] ?? '');
        if ($zone === 'discount') {
            $votes[] = $dir > 0 ? 1.0 : 0.0;
        } elseif ($zone === 'premium') {
            $votes[] = $dir > 0 ? 0.0 : 1.0;
        } elseif ($zone === 'equilibrium') {
            $votes[] = 0.5;
        }

        // Structures only count where price is actually near them.
        foreach (['order_block' => 0.6, 'breaker' => 0.6, 'fvg' => 0.6] as $k => $near) {
            $s = $smc[$k] ?? null;
            if (is_array($s) && (float)($s['distance'] ?? 99) <= $near) {
                $votes[] = ((int)$s['dir'] === $dir) ? 1.0 : 0.0;
            }
        }
        // A recent sweep or character change is a direction, not a location.
        foreach (['sweep' => 3, 'choch' => 6] as $k => $maxAge) {
            $s = $smc[$k] ?? null;
            if (is_array($s) && (int)($s['age'] ?? 99) <= $maxAge) {
                $votes[] = ((int)$s['dir'] === $dir) ? 1.0 : 0.0;
            }
        }

        return $votes ? array_sum($votes) / count($votes) : 0.5;
    }

    /**
     * Is this the kind of market the setup needs?
     *
     * Trend-following evidence wants a trending market and mean-reverting
     * evidence wants a range. The engine already halves rule weights for the
     * wrong regime; this records the mismatch as a quality cost too, because a
     * setup fighting its own regime is worth less even after the discount.
     */
    private static function regimeScore(array $ctx, int $dir): float
    {
        $adx = $ctx['adx'] ?? null;
        $chop = $ctx['chop'] ?? null;
        if ($adx === null && $chop === null) {
            return 0.5;
        }
        // Trending: ADX above 25 and choppiness below 50.
        $trending = 0.5;
        if ($adx !== null) {
            $trending = max(0.0, min(1.0, ((float)$adx - 15) / 20));
        }
        if ($chop !== null) {
            $fromChop = max(0.0, min(1.0, (61.8 - (float)$chop) / 25));
            $trending = ($trending + $fromChop) / 2;
        }

        // Which kind of evidence is carrying this setup?
        $trendCats = 0.0;
        $meanCats = 0.0;
        foreach ((array)($ctx['categories'] ?? []) as $cat => $c) {
            if (!is_array($c)) {
                continue;
            }
            $v = abs((float)($c['capped'] ?? 0));
            if (in_array($cat, ['trend', 'structure'], true)) {
                $trendCats += $v;
            } elseif (in_array($cat, ['momentum', 'volatility'], true)) {
                $meanCats += $v;
            }
        }
        $total = $trendCats + $meanCats;
        if ($total <= 0) {
            return $trending;
        }
        // A trend-led setup scores the regime's trendiness; a momentum-led one
        // scores its opposite; a mixed setup lands in between.
        $trendShare = $trendCats / $total;
        return max(0.0, min(1.0, $trendShare * $trending + (1 - $trendShare) * (1 - $trending)));
    }

    /**
     * Combine the axes into one 0..1 quality.
     *
     * A weighted GEOMETRIC mean, not an arithmetic one. Arithmetic lets a
     * strong axis paper over a broken one - a setup with overwhelming momentum
     * and a daily trend pointing the other way would average out respectable.
     * Geometric will not: it is dominated by the weakest term, which is the
     * behaviour wanted, because that is the term that gets people stopped out.
     */
    public static function quality(array $dims): float
    {
        $num = 0.0;
        $den = 0.0;
        foreach (self::WEIGHTS as $k => $w) {
            $v = max(self::FLOOR, min(1.0, (float)($dims[$k] ?? 0.5)));
            $num += $w * log($v);
            $den += $w;
        }
        return $den > 0 ? exp($num / $den) : 0.0;
    }

    /**
     * The published confidence, and how it was arrived at.
     *
     * Returns [percent, basis, samples, dimensions, quality]. The basis is not
     * decoration - it is the difference between a measured win rate and an
     * educated guess, and a reader deserves to be told which one they are
     * looking at.
     */
    public static function evaluate(array $ctx): array
    {
        $dims = self::dimensions($ctx);
        $q = self::quality($dims);

        $out = [
            'confidence' => round(max(1.0, min(95.0, $q * 100)), 1),
            'basis'      => 'quality',
            'n'          => 0,
            'dimensions' => $dims,
            'quality'    => round($q, 4),
        ];

        // Calibrate against measured outcomes where there are enough of them.
        $calib = json_decode(Database::setting('quality_calib', ''), true);
        $minN = max(10, (int)Database::setting('calib_min_samples', '30'));
        if (is_array($calib['buckets'] ?? null)) {
            foreach ($calib['buckets'] as $b) {
                if ((int)($b['n'] ?? 0) < $minN
                    || $q < (float)$b['lo'] || $q >= (float)$b['hi']
                    || ($b['winrate'] ?? null) === null) {
                    continue;
                }
                $useWilson = Database::setting('confidence_conservative', '1') === '1'
                    && ($b['wilson'] ?? null) !== null;
                $out['confidence'] = round(max(1.0, min(95.0,
                    (float)($useWilson ? $b['wilson'] : $b['winrate']))), 1);
                $out['basis'] = 'measured';
                $out['n'] = (int)$b['n'];
                break;
            }
        }
        return $out;
    }

    /**
     * Build the quality -> win rate map from verified signals.
     *
     * Same shape as the older score-based calibration and for the same reason:
     * the only defensible confidence number is the fraction of comparable past
     * signals that actually worked. This one buckets on the combined quality
     * rather than on |score|, so the map is over the thing now being reported.
     */
    public static function buildCalibration(int $lastN = 1000): array
    {
        $rows = Database::pdo()->query(
            "SELECT features, outcome, outcome_r FROM signals
             WHERE outcome IN ('confirmed', 'invalid') AND `signal` != 'NEUTRAL'
               AND features IS NOT NULL
             ORDER BY created_at DESC LIMIT " . (int)$lastN
        )->fetchAll();

        $edges = [[0.0, 0.15], [0.15, 0.3], [0.3, 0.45], [0.45, 0.6], [0.6, 1.01]];
        $buckets = array_map(fn ($e) => ['lo' => $e[0], 'hi' => $e[1], 'n' => 0, 'wins' => 0], $edges);
        $used = 0;
        foreach ($rows as $row) {
            $f = json_decode((string)$row['features'], true);
            // Signals stored before the dimensions existed cannot be scored
            // retrospectively - the inputs are gone with the candles.
            if (!is_array($f) || !isset($f['conf_quality'])) {
                continue;
            }
            $q = (float)$f['conf_quality'];
            $used++;
            foreach ($buckets as $i => $b) {
                if ($q >= $b['lo'] && $q < $b['hi']) {
                    $buckets[$i]['n']++;
                    if (Outcomes::isWin($row)) {
                        $buckets[$i]['wins']++;
                    }
                    break;
                }
            }
        }
        foreach ($buckets as $i => $b) {
            $buckets[$i]['winrate'] = $b['n'] > 0 ? round($b['wins'] / $b['n'] * 100, 1) : null;
            $buckets[$i]['wilson'] = $b['n'] > 0
                ? round(Outcomes::wilsonLower($b['wins'], $b['n']) * 100, 1) : null;
        }
        $calib = ['at' => time(), 'samples' => $used, 'buckets' => $buckets];
        Database::setSetting('quality_calib', json_encode($calib));
        return $calib;
    }

    /** Human labels for the axes, shared by the UI and the explanations. */
    public static function labels(): array
    {
        return [
            'evidence'  => 'Evidence',
            'breadth'   => 'Breadth',
            'alignment' => 'Trend alignment',
            'structure' => 'Structure',
            'regime'    => 'Regime fit',
            'cost'      => 'Cost of entry',
        ];
    }
}
