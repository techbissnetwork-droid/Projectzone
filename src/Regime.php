<?php
declare(strict_types=1);

namespace SignalMasterAi;

/**
 * What kind of market this is, and what that should change.
 *
 * The engine already had one regime idea: read ADX, and if it is high halve
 * the mean-reverting rules, if it is low halve the trend-following ones. That
 * is a real insight and it is also one dimension of a two-dimensional problem.
 * ADX says whether price is going somewhere. It says nothing about how hard it
 * is moving while it does, and those are different questions with different
 * answers:
 *
 *   trending, quiet     a grind. The best conditions trend-following gets:
 *                       stops stay small, follow-through is reliable, and the
 *                       move takes its time.
 *   trending, volatile  a real move with real noise. Worth taking, but a stop
 *                       sized for the grind gets hit on the way to being right.
 *   ranging, quiet      compression. Most breakout attempts fail here, and the
 *                       ones that do not are worth waiting for - so demand
 *                       more, then give the trade room and time to expand.
 *   ranging, volatile   whipsaw. The worst place to have an opinion: price
 *                       moves enough to hit stops and not enough to reach
 *                       targets. Demand a great deal more, or stay out.
 *
 * Each regime carries a policy rather than a label - what the score has to
 * clear, how wide the stop should be, how many independent categories must
 * agree, how long to give the trade before calling it dead. A classification
 * that does not change behaviour is decoration.
 *
 * Volatility is measured as a percentile of the instrument's OWN recent
 * history, never as an absolute. A 4% daily range is quiet for one coin and a
 * crisis for another, and any fixed threshold would simply sort the universe
 * by ticker rather than by condition.
 */
class Regime
{
    /**
     * Per-regime policy.
     *
     *   threshold   score multiplier - above 1 demands more evidence
     *   stop        ATR multiplier on the stop distance
     *   target      ATR multiplier on the targets
     *   categories  extra independent categories required to agree
     *   time_stop   multiplier on how many bars the setup gets
     *
     * These are reasoned starting points, not measured optima. Step six's
     * tuner is what turns them into measured ones; until then they are
     * conservative in the regimes that deserve conservatism.
     */
    private const POLICY = [
        'trending_quiet' => [
            'threshold' => 0.9, 'stop' => 0.9, 'target' => 1.0, 'categories' => 0, 'time_stop' => 1.3,
            'why' => 'A grinding trend: the conditions trend-following is built for, so the bar is '
                   . 'slightly lower, the stop can be tighter and the trade is given longer to work.',
        ],
        'trending_volatile' => [
            'threshold' => 1.0, 'stop' => 1.3, 'target' => 1.2, 'categories' => 0, 'time_stop' => 1.0,
            'why' => 'A real move with real noise: worth taking, but a stop sized for calmer '
                   . 'conditions gets hit on the way to being right, so it is widened.',
        ],
        'ranging_quiet' => [
            'threshold' => 1.15, 'stop' => 0.9, 'target' => 1.2, 'categories' => 1, 'time_stop' => 1.2,
            'why' => 'Compression: most attempts to break out of it fail, so more evidence is '
                   . 'required - and the ones that do work expand, so targets are given more room.',
        ],
        'ranging_volatile' => [
            'threshold' => 1.35, 'stop' => 1.3, 'target' => 0.9, 'categories' => 1, 'time_stop' => 0.8,
            'why' => 'Whipsaw: price moves enough to hit stops and not enough to reach targets. '
                   . 'The bar is raised sharply and targets are pulled in.',
        ],
    ];

    /** Bars of history a percentile needs before it means anything. */
    private const MIN_HISTORY = 60;

    /**
     * Classify from series the engine has already computed.
     *
     * Returns name, the two axis readings that produced it, the policy, and a
     * confidence in the classification itself - because a market sitting on
     * the boundary between two regimes should not have either one's policy
     * applied at full strength.
     */
    public static function detect(
        ?float $adx, ?float $chop, array $atrSeries, array $bandwidthSeries, array $closes
    ): array {
        $out = [
            'name' => 'unknown',
            'trendiness' => null,
            'energy' => null,
            'certainty' => 0.0,
            'policy' => ['threshold' => 1.0, 'stop' => 1.0, 'target' => 1.0,
                         'categories' => 0, 'time_stop' => 1.0, 'why' => ''],
        ];
        if (count($closes) < self::MIN_HISTORY) {
            return $out;
        }

        // --- Axis 1: is price going somewhere? ------------------------------
        // ADX and choppiness measure the same thing from opposite ends, and
        // disagree often enough that averaging them beats trusting either.
        $parts = [];
        if ($adx !== null) {
            // 15 is flat, 35 is a strong trend.
            $parts[] = max(0.0, min(1.0, ($adx - 15) / 20));
        }
        if ($chop !== null) {
            // The index runs backwards: 61.8 and above is chop, 38.2 is trend.
            $parts[] = max(0.0, min(1.0, (61.8 - $chop) / 23.6));
        }
        if (!$parts) {
            return $out;
        }
        $trendiness = array_sum($parts) / count($parts);

        // --- Axis 2: how hard is it moving? ---------------------------------
        // Percentile against this instrument's own recent history. A fixed
        // ATR threshold would sort the universe by ticker, not by condition.
        $energyParts = [];
        $atrPct = Indicators::percentileRank($atrSeries, 120);
        if ($atrPct !== null) {
            $energyParts[] = $atrPct / 100;
        }
        $bwPct = Indicators::percentileRank($bandwidthSeries, 120);
        if ($bwPct !== null) {
            $energyParts[] = $bwPct / 100;
        }
        if (!$energyParts) {
            return $out;
        }
        $energy = array_sum($energyParts) / count($energyParts);

        $trending = $trendiness >= 0.5;
        $volatile = $energy >= 0.5;
        $name = ($trending ? 'trending' : 'ranging') . '_' . ($volatile ? 'volatile' : 'quiet');

        // How far from each boundary the reading sits. Kept per axis rather
        // than combined: taking the lower of the two would let one ambiguous
        // axis mute the whole policy, so a market clearly trending with
        // middling energy would stop demanding what a trend deserves purely
        // because its volatility was unremarkable.
        $trendCertainty = abs($trendiness - 0.5) * 2;
        $energyCertainty = abs($energy - 0.5) * 2;

        $out['name'] = $name;
        $out['trendiness'] = round($trendiness, 4);
        $out['energy'] = round($energy, 4);
        // Reported certainty is the weaker axis: it describes confidence in
        // the four-way LABEL, which does need both to be clear.
        $out['certainty'] = round(min($trendCertainty, $energyCertainty), 4);
        $out['policy'] = self::blend(self::POLICY[$name], $trendCertainty, $energyCertainty);
        $out['policy']['why'] = self::POLICY[$name]['why'];
        return $out;
    }

    /**
     * Scale each policy dimension towards "do nothing" by how sure the axis
     * that governs it is.
     *
     * Applying a 35% threshold increase to a market barely on the ranging side
     * of the line is not caution, it is noise amplification. But the axes
     * govern different things and should be trusted separately: how much
     * evidence to demand is a question about whether price is going anywhere,
     * while how wide the stop needs to be is a question about how hard it is
     * moving. At certainty 0 a multiplier is 1.0 and changes nothing.
     */
    private static function blend(array $policy, float $trendC, float $energyC): array
    {
        $t = max(0.0, min(1.0, $trendC));
        $e = max(0.0, min(1.0, $energyC));
        $scale = static fn (float $v, float $c): float => round(1.0 + ($v - 1.0) * $c, 4);
        return [
            // What to demand depends on whether price is going anywhere.
            'threshold'  => $scale($policy['threshold'], $t),
            // How wide to sit depends on how hard it is moving.
            'stop'       => $scale($policy['stop'], $e),
            'target'     => $scale($policy['target'], $e),
            // Confirmations are whole numbers: half an extra category is not
            // a requirement anyone can meet, so it only applies once the
            // trend read is reasonably sure.
            'categories' => $t >= 0.4 ? (int)$policy['categories'] : 0,
            // How long to wait depends on both, so it takes the weaker.
            'time_stop'  => $scale($policy['time_stop'], min($t, $e)),
        ];
    }

    /** Human name for the UI and the reasons list. */
    public static function label(string $name): string
    {
        return [
            'trending_quiet'    => 'Trending, quiet',
            'trending_volatile' => 'Trending, volatile',
            'ranging_quiet'     => 'Ranging, quiet',
            'ranging_volatile'  => 'Ranging, volatile',
        ][$name] ?? 'Unclassified';
    }
}
