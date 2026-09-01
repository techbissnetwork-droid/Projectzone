<?php
declare(strict_types=1);

namespace SignalMasterAi;

/**
 * The three starting configurations, and what this install's own record says
 * about them.
 *
 * WHY THIS IS A CLASS AND NOT AN ARRAY IN THE SETTINGS PAGE.
 *
 * It was an array in the settings page, which was fine while the settings page
 * was the only thing that wanted it. It is not: the A/B replay wants to load a
 * preset as a challenger, and the page that offers a preset wants to say what
 * that preset has actually done here. Two more readers of a local variable
 * means copying it twice, and a copied preset drifts silently - the operator
 * presses a button whose values are no longer the values being described.
 *
 * THE CLAIM THAT WAS BEING MADE FOR THEM.
 *
 * The Selective card said, in as many words, that it "is what this install's
 * own replayed history measured as its best configuration". No shipped default
 * can know that. It was measured once, during development, on one dataset, and
 * then printed on every install as though it were a finding about the reader's
 * own account - including a brand-new install with no history at all. An
 * operator who switches on the strength of that sentence and ends up worse off
 * has been misled by the software, not by the market.
 *
 * So the presets say what they ARE - the values they write, and the reasoning
 * behind those values - and the measured part now comes from measure(), which
 * reads this install's own settled signals or admits there are not enough.
 */
class Presets
{
    /**
     * The score threshold each preset means, on BOTH scales.
     *
     * THE HEADLINE OF EVERY PRESET WAS INERT.
     *
     * The engine resolves its threshold from one of two setting pairs
     * depending on scoring_mode: cat_buy_threshold/cat_sell_threshold in
     * 'category' mode, buy_threshold/sell_threshold in 'sum'. 'category' is
     * the shipped default, and every preset wrote only the OTHER pair. So
     * "Selective - score threshold 3.0", the first line on its card and the
     * main thing it claims to do, changed nothing at all on a normal install.
     * Measured with an override: buy_threshold=99 left all 76 signals
     * standing; cat_buy_threshold=99 removed every one.
     *
     * The two scales are not the same number. The shipped defaults are 2.0
     * (sum) and 3.0 (category) and Balanced is defined as "the shipped
     * defaults", which fixes Balanced at both and sets the ratio between the
     * scales at 1.5. Selective and Active are carried across on that ratio
     * rather than invented: 3.0 -> 4.5 and 1.5 -> 2.25.
     *
     * Both pairs are written on apply, so switching scoring mode later cannot
     * silently move a preset back to a threshold nobody chose.
     *
     * [sum, category]
     */
    public const THRESHOLDS = [
        'selective' => [3.0, 4.5],
        'balanced'  => [2.0, 3.0],
        'active'    => [1.5, 2.25],
    ];

    /**
     * READ THIS BEFORE RAISING A THRESHOLD.
     *
     * The premise of these presets is that a higher score is a better trade,
     * so refusing the low ones leaves you with the good ones. Measured on this
     * codebase, over 1,357 decided trades from six coins on three timeframes,
     * replayed with the gate opened to 0.5 so the sample includes the setups a
     * threshold would normally refuse:
     *
     *     score band      n     win %    expectancy
     *     0.49 - 1.20    226    76.1%     +0.149R
     *     1.20 - 1.86    226    75.2%     +0.125R
     *     1.86 - 2.51    226    78.8%     +0.163R
     *     2.52 - 3.40    226    74.3%     +0.097R
     *     3.40 - 4.45    226    77.4%     +0.142R
     *     4.46 - 9.25    227    70.5%     +0.052R
     *
     * and asked the way a threshold asks it - everything at or above a cut:
     *
     *     >= 1.5   n=1030   +0.112R
     *     >= 2.0   n=852    +0.120R
     *     >= 3.0   n=547    +0.096R
     *     >= 4.0   n=308    +0.075R
     *     >= 5.0   n=153    +0.021R
     *
     * MONOTONIC, AND IN THE WRONG DIRECTION. Every step up the threshold makes
     * the product worse. That is the whole explanation for the thing this file
     * has never been able to explain: selective is stricter than balanced and
     * measures worse, and it is not noise, a bug, or a bad sample - it is the
     * gate working exactly as designed on a number that does not predict what
     * the gate is for.
     *
     * The mechanism is not a mystery and the codebase already half-names it in
     * the extension filter: the rule set argues hardest when a move is already
     * extended, so the highest scores cluster at the worst moment to enter. The
     * extension gate exists to counter that and these figures are measured with
     * it on, so it is not enough on its own.
     *
     * What NOT to conclude: that the thresholds should be dropped to 0.5. The
     * bands above are a replay on six coins, published calls are a different
     * population, and a site that publishes everything is a different product.
     * What it does mean is that "make it more selective" is not an improvement
     * to reach for, and that the honest lever is the shape of the trade and the
     * rules themselves, not the height of this bar.
     */

    /** Engine values each preset writes. */
    public const DEFS = [
        'selective' => [
            'buy_threshold' => '3.0', 'sell_threshold' => '-3.0',
            'target_min_reach' => '0.5', 'extension_gate_enabled' => '1',
            'extension_max_atr' => '2.0', 'cooldown_bars' => '6',
            'chop_gate_enabled' => '1', 'tp1_partial_pct' => '100',
            'enabled_intervals' => '1h,4h', 'quarantine_enabled' => '1',
        ],
        'balanced' => [
            'buy_threshold' => '2.0', 'sell_threshold' => '-2.0',
            'target_min_reach' => '0.35', 'extension_gate_enabled' => '1',
            'extension_max_atr' => '2.5', 'cooldown_bars' => '4',
            'chop_gate_enabled' => '1', 'tp1_partial_pct' => '50',
            'enabled_intervals' => '15m,1h,4h', 'quarantine_enabled' => '1',
        ],
        'active' => [
            'buy_threshold' => '1.5', 'sell_threshold' => '-1.5',
            'target_min_reach' => '0.25', 'extension_gate_enabled' => '0',
            'extension_max_atr' => '3.0', 'cooldown_bars' => '2',
            'chop_gate_enabled' => '0', 'tp1_partial_pct' => '50',
            'enabled_intervals' => '15m,1h,4h', 'quarantine_enabled' => '1',
        ],
    ];

    /** How each one is described. [title, what it does] - no claims of proof. */
    public const BLURB = [
        'selective' => [
            'Selective &mdash; fewer, stronger calls',
            'Score threshold 3.0, tighter reach and extension gates, a longer cooldown after a '
            . 'stop-out, the whole position taken at target 1, and only 1h and 4h scanned. Fewer '
            . 'signals, and each one has cleared a higher bar. Taking the whole position at '
            . 'target 1 also means no trade here can reach a target and then hand it back.',
        ],
        'balanced' => [
            'Balanced &mdash; the shipped defaults',
            'Score threshold 2.0, half the position banked at target 1 and the rest left running, '
            . '15m as well as 1h and 4h. Where a fresh install starts.',
        ],
        'active' => [
            'Active &mdash; more calls, lower bar',
            'Score threshold 1.5, chop and extension gates off, short cooldown. Noticeably more '
            . 'signals with less filtering behind each one. Chosen for coverage, not for accuracy.',
        ],
    ];

    /** The smallest settled sample worth quoting a win rate from. */
    public const MIN_N = 20;

    /**
     * A preset's values with the threshold written under every name the engine
     * might read it by.
     *
     * This, not DEFS, is what should be applied or handed to a replay. DEFS
     * stays as the plain declaration of what the preset is; this is the same
     * thing expressed in the keys the engine actually consults.
     *
     * @return array<string,string>
     */
    public static function values(string $name): array
    {
        $vals = self::DEFS[$name] ?? [];
        if (!$vals) {
            return [];
        }
        [$sum, $cat] = self::THRESHOLDS[$name] ?? [null, null];
        if ($sum !== null) {
            $vals['buy_threshold']      = (string)$sum;
            $vals['sell_threshold']     = (string)(-$sum);
            $vals['cat_buy_threshold']  = (string)$cat;
            $vals['cat_sell_threshold'] = (string)(-$cat);
        }
        return $vals;
    }

    /** The threshold this preset would actually enforce, given the live mode. */
    public static function liveThreshold(string $name): ?float
    {
        [$sum, $cat] = self::THRESHOLDS[$name] ?? [null, null];
        if ($sum === null) {
            return null;
        }
        return SignalEngine::scoringMode() === 'category' ? $cat : $sum;
    }

    /** @return array<int,string> the timeframes a preset scans */
    public static function intervals(string $name): array
    {
        $csv = self::DEFS[$name]['enabled_intervals'] ?? '';
        return array_values(array_filter(array_map('trim', explode(',', $csv))));
    }

    /**
     * What this install's settled signals did on the timeframes a preset scans.
     *
     * HONEST ABOUT WHAT IT IS AND IS NOT MEASURING.
     *
     * This is not a replay of the preset. It cannot be: the thresholds and
     * gates decide which setups would ever have been published, and answering
     * that needs the candles back, which is what the A/B replay in the engine
     * lab is for. What this DOES answer is the part of the preset that can be
     * checked against history already on disk - the timeframes it chooses to
     * scan - and that is the setting most likely to move the headline, because
     * it decides which trades exist at all.
     *
     * A win is a trade that made money, the one definition the whole site uses
     * now. See Outcomes::isWin - counting "reached a target label" instead is
     * what let a scale-out preset look better than a take-it-all-at-target-1
     * preset without being better.
     *
     * @return array{n:int, wins:int, win_rate:?float, avg_r:?float, enough:bool}
     */
    public static function measure(string $name): array
    {
        $tfs = self::intervals($name);
        $empty = ['n' => 0, 'wins' => 0, 'win_rate' => null, 'avg_r' => null, 'enough' => false];
        if (!$tfs) {
            return $empty;
        }
        $marks = implode(',', array_fill(0, count($tfs), '?'));
        try {
            $st = Database::pdo()->prepare(
                'SELECT COUNT(*) n,
                        SUM(CASE WHEN ' . Outcomes::WIN_SQL . ' THEN 1 ELSE 0 END) w,
                        AVG(outcome_r) r
                   FROM signals
                  WHERE outcome IN (\'confirmed\', \'invalid\')
                    AND tf IN (' . $marks . ')'
            );
            $st->execute($tfs);
            $row = $st->fetch() ?: [];
        } catch (\Throwable $e) {
            return $empty;
        }
        $n = (int)($row['n'] ?? 0);
        if ($n < 1) {
            return $empty;
        }
        $w = (int)($row['w'] ?? 0);
        return [
            'n' => $n,
            'wins' => $w,
            'win_rate' => round($w / $n * 100, 1),
            'avg_r' => round((float)($row['r'] ?? 0), 3),
            'enough' => $n >= self::MIN_N,
        ];
    }

    /**
     * Every preset measured, plus which timeframes each one leaves out.
     *
     * The left-out list is the point. A preset that scans fewer timeframes is
     * not automatically more selective about QUALITY - it is more selective
     * about SPEED, and those come apart. Dropping the timeframe that happens
     * to be carrying the record lowers the headline while every gate around it
     * is being tightened, and the operator reads the result as "the strict
     * setting performs worse" when what happened is that its best source of
     * trades was switched off.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function report(): array
    {
        $out = [];
        $all = [];
        foreach (array_keys(self::DEFS) as $name) {
            foreach (self::intervals($name) as $tf) {
                $all[$tf] = true;
            }
        }
        foreach (array_keys(self::DEFS) as $name) {
            $mine = self::intervals($name);
            $out[$name] = self::measure($name) + [
                'intervals' => $mine,
                'dropped' => array_values(array_diff(array_keys($all), $mine)),
            ];
        }
        return $out;
    }

    /** Per-timeframe record, so a dropped timeframe can be judged on its own. */
    public static function byInterval(): array
    {
        try {
            $rows = Database::pdo()->query(
                'SELECT tf,
                        COUNT(*) n,
                        SUM(CASE WHEN ' . Outcomes::WIN_SQL . ' THEN 1 ELSE 0 END) w,
                        AVG(outcome_r) r
                   FROM signals
                  WHERE outcome IN (\'confirmed\', \'invalid\')
                  GROUP BY tf'
            )->fetchAll();
        } catch (\Throwable $e) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            $n = (int)$row['n'];
            if ($n < 1) {
                continue;
            }
            $out[(string)$row['tf']] = [
                'n' => $n,
                'win_rate' => round((int)$row['w'] / $n * 100, 1),
                'avg_r' => round((float)$row['r'], 3),
                'enough' => $n >= self::MIN_N,
            ];
        }
        return $out;
    }
}
