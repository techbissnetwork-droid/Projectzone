<?php
declare(strict_types=1);

namespace SignalMasterAi;

/**
 * The last look at a signal before anybody is told about it.
 *
 * WHAT THIS IS NOT.
 *
 * It is not another opinion about the market. The engine has sixteen of those
 * already - the score threshold, the confluence requirement, the chop filter,
 * the cooldown, target reachability, the cost floor, the higher-timeframe
 * conflict check, the score band record. Adding a seventeenth here would be
 * the duplication this project keeps having to undo.
 *
 * This checks the OTHER kind of thing: whether the decision was made on sound
 * data, and whether the plan it produced is internally coherent. Those are
 * questions with right answers, and nothing was asking them.
 *
 *   - The candles could be stale. Nothing compared the last bar's age to the
 *     timeframe before publishing a call priced off it.
 *   - The candles could have holes. MarketData::gapReport() has existed the
 *     whole time and IS CALLED FROM NOWHERE - a gap detector that was written
 *     and never wired up, so an indicator computed across a missing session
 *     published like any other.
 *   - The plan could be incoherent - a stop on the wrong side of the entry,
 *     targets out of order, zero risk. A MEMBER'S manual plan is checked for
 *     exactly this in api.php before it can be opened; the ENGINE'S own plan
 *     was never checked at all, on the reasoning that it builds its own levels
 *     so they must be right. That reasoning holds until an ATR of zero, a
 *     flat series or an arithmetic slip makes it false, and then the site
 *     publishes a trade that closes instantly at a loss.
 *
 * A failure is either a BLOCK - the call is not published and becomes a shadow
 * like any other refusal, so "what refusing cost you" can report on it - or a
 * WARN, recorded against the signal and shown, but published. Data quality
 * warns; plan incoherence blocks, because there is no reading of a stop on the
 * wrong side that is worth sending to a paying member.
 */
class Preflight
{
    public const BLOCK = 'block';
    public const WARN  = 'warn';

    /**
     * @param  array<int,array<string,mixed>> $candles the series the call was made on
     * @return array<int,array{key:string,level:string,message:string}>
     */
    public static function check(
        string $side,
        ?array $levels,
        array $candles,
        string $tf,
        bool $liveClock = true
    ): array {
        $out = [];
        if ($side !== 'BUY' && $side !== 'SELL') {
            return $out;
        }

        // ---- the plan holds together ---------------------------------------
        if (is_array($levels)) {
            foreach (self::planFaults($side, $levels) as $f) {
                $out[] = $f;
            }
        }

        // ---- how much of the reward is already gone -------------------------
        //
        // THE ONE PRE-FLIGHT CHECK THAT IS ABOUT MONEY.
        //
        // A call published after price has already run most of the way to its
        // first target is a bad trade dressed as a good one: the reward left
        // is a fraction of what the plan advertises, while the stop is still
        // the full distance away. That is precisely the shape this engine
        // suffers from - it wins two trades in three and still loses money,
        // because the average win is a third of the average loss - and a late
        // entry makes it worse one signal at a time.
        //
        // Not a market opinion; the plan's own numbers say it. The gates above
        // decide whether the setup is worth taking, and this asks whether
        // there is anything left of it.
        if (is_array($levels)) {
            $pfEntry = (float)($levels['entry'] ?? 0);
            $pfStop = (float)($levels['stop_loss'] ?? 0);
            $pfTp1 = (float)($levels['tp1'] ?? 0);
            $ref = $candles ? (float)($candles[count($candles) - 1]['c'] ?? 0) : 0.0;
            $maxGone = max(0.0, min(100.0, (float)Database::setting('preflight_max_travelled_pct', '0')));
            if ($maxGone > 0 && $pfEntry > 0 && $pfTp1 > 0 && $ref > 0 && $pfStop > 0) {
                $span = abs($pfTp1 - $pfEntry);
                $gone = $side === 'BUY' ? ($ref - $pfEntry) : ($pfEntry - $ref);
                $pct = $span > 0 ? $gone / $span * 100 : 0.0;
                if ($pct > $maxGone) {
                    $out[] = [
                        'key' => 'late_entry',
                        'level' => self::BLOCK,
                        'message' => sprintf(
                            'Price has already covered %.0f%% of the distance to the first target '
                            . '(%s of the %s move from the entry), so most of the reward is gone '
                            . 'while the whole stop is still at risk.',
                            $pct, self::num(abs($gone)), self::num($span)),
                    ];
                }
            }
        }

        // ---- the plan pays for its own risk ---------------------------------
        //
        // A MINIMUM REWARD FOR THE RISK BEING TAKEN.
        //
        // Every gate before this asks whether the setup is likely to work.
        // This asks the other half of the question, which nothing was asking:
        // whether it is worth taking if it does. A call risking a full stop to
        // make half of one is a losing trade at any win rate below two in
        // three, and this engine's own record - two wins in three, still
        // losing money, because the average win is a third of the average loss
        // - is exactly what that looks like when nobody checks.
        //
        // Measured on the PUBLISHED numbers, entry to the second target, which
        // is the pair the plan advertises as its reward and the same pair the
        // 'rr' field on the signal shows. Not the net-of-cost figure: the
        // operator sets this in the ratio they think in, and the cost floor is
        // already its own separate gate.
        //
        // A block, not a warning. There is no reading of "risk one to make a
        // half" that is worth sending to a paying member, and it becomes a
        // shadow like every other refusal, so the track record can still say
        // what refusing cost.
        if (is_array($levels)) {
            $minRr = max(1, min(3, (int)Database::setting('min_rr', '1')));
            $tier  = (int)($levels['rr_tier'] ?? 0);
            if ($minRr > 1 && $tier > 0 && $tier < $minRr) {
                $out[] = [
                    'key' => 'thin_rr',
                    'level' => self::BLOCK,
                    'message' => sprintf(
                        'This is a 1 : %d setup - on this pair\u{2019}s own record price reaches %dR '
                        . 'inside the time stop often enough to plan for, and 1 : %d it does not - '
                        . 'and this site is set to publish 1 : %d and better only.',
                        $tier, $tier, $minRr, $minRr),
                ];
            }
        }

        // ---- the data it was decided on ------------------------------------
        //
        // Skipped when the clock is not live. The backtester pins "now" to the
        // simulated bar, so a freshness check there would call every replayed
        // signal stale and the backtest would return nothing at all - which is
        // how a guard like this quietly destroys the tool that measures it.
        if ($liveClock && $candles) {
            $last = $candles[count($candles) - 1];
            $step = MarketData::TF_SECONDS[$tf] ?? 3600;
            $ageBars = $step > 0
                ? (time() - (int)round(((int)($last['t'] ?? 0)) / 1000)) / $step
                : 0.0;
            $maxBars = max(1.0, (float)Database::setting('preflight_max_stale_bars', '3'));
            if ($ageBars > $maxBars) {
                $out[] = [
                    'key' => 'data_stale',
                    'level' => self::WARN,
                    'message' => sprintf(
                        'The newest candle is %.1f bars old on a %s chart - the feed is behind, so '
                        . 'this call was priced off data that has stopped arriving.',
                        $ageBars, $tf),
                ];
            }
        }

        if (count($candles) > 2) {
            $gaps = MarketData::gapReport($candles, $tf);
            $missing = (int)($gaps['missing'] ?? 0);
            $pct = count($candles) > 0 ? $missing / count($candles) * 100 : 0.0;
            $maxPct = max(0.0, (float)Database::setting('preflight_max_gap_pct', '2'));
            if ($maxPct > 0 && $pct > $maxPct) {
                $out[] = [
                    'key' => 'data_gaps',
                    'level' => self::WARN,
                    'message' => sprintf(
                        '%d candle(s) are missing from the %d used here (%.1f%%, in %d run(s)). '
                        . 'Every indicator in this call was computed across those holes.',
                        $missing, count($candles), $pct, (int)($gaps['gaps'] ?? 0)),
                ];
            }
        }

        return $out;
    }

    /**
     * Faults in the plan itself. Same rules the member's own manual trade is
     * held to in api.php - one definition of "this plan makes sense", applied
     * to the engine as well as to the member.
     *
     * @return array<int,array{key:string,level:string,message:string}>
     */
    private static function planFaults(string $side, array $levels): array
    {
        $out = [];
        $entry = (float)($levels['entry'] ?? 0);
        $stop = (float)($levels['stop_loss'] ?? 0);
        if ($entry <= 0 || $stop <= 0) {
            return [[
                'key' => 'plan_missing',
                'level' => self::BLOCK,
                'message' => 'The plan has no entry or no stop, so there is no risk to measure '
                    . 'anything against.',
            ]];
        }
        $isBuy = $side === 'BUY';
        if ($isBuy ? $stop >= $entry : $stop <= $entry) {
            $out[] = [
                'key' => 'plan_stop_side',
                'level' => self::BLOCK,
                'message' => sprintf(
                    'On a %s the stop goes %s the entry, and this one is at %s against an entry '
                    . 'of %s. Published, it would close at a loss on the first tick.',
                    $isBuy ? 'long' : 'short', $isBuy ? 'below' : 'above',
                    self::num($stop), self::num($entry)),
            ];
        }
        $ladder = [];
        foreach (['tp1', 'tp2', 'tp3'] as $k) {
            $v = (float)($levels[$k] ?? 0);
            if ($v <= 0) {
                continue;
            }
            if ($isBuy ? $v <= $entry : $v >= $entry) {
                $out[] = [
                    'key' => 'plan_target_side',
                    'level' => self::BLOCK,
                    'message' => strtoupper($k) . ' is on the losing side of the entry: '
                        . self::num($v) . ' against ' . self::num($entry) . ' on a '
                        . ($isBuy ? 'long' : 'short') . '.',
                ];
                continue;
            }
            $ladder[] = $v;
        }
        if (!$ladder) {
            $out[] = [
                'key' => 'plan_no_target',
                'level' => self::BLOCK,
                'message' => 'The plan has no usable target, so there is nothing for the trade to '
                    . 'aim at and no reward to weigh the risk against.',
            ];
        } else {
            $sorted = $ladder;
            $isBuy ? sort($sorted) : rsort($sorted);
            if ($ladder !== $sorted) {
                $out[] = [
                    'key' => 'plan_target_order',
                    'level' => self::BLOCK,
                    'message' => 'The targets are out of order - TP2 is not further from the entry '
                        . 'than TP1 - so a partial exit would not mean what it says.',
                ];
            }
        }
        return $out;
    }

    /** True when anything in the list is a hard stop. */
    public static function blocked(array $faults): bool
    {
        foreach ($faults as $f) {
            if (($f['level'] ?? '') === self::BLOCK) {
                return true;
            }
        }
        return false;
    }

    /** The first blocking fault's key, for the shadow's blocked_by. */
    public static function firstBlock(array $faults): string
    {
        foreach ($faults as $f) {
            if (($f['level'] ?? '') === self::BLOCK) {
                return (string)($f['key'] ?? 'preflight');
            }
        }
        return 'preflight';
    }

    private static function num(float $v): string
    {
        return rtrim(rtrim(number_format($v, 8, '.', ','), '0'), '.');
    }
}
