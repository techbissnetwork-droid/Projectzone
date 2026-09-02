<?php
declare(strict_types=1);

namespace SignalMasterAi;

/**
 * How much of each thing a tier gets.
 *
 * Gate answers WHETHER: the scanner is premium, the backtester is premium.
 * That is a blunt instrument on its own - a feature is either the whole
 * product or nothing, and the only way to make a free account worth having
 * was to give a feature away entirely. So the site had one tiered NUMBER in
 * it, the watchlist cap, and everything else was the same for a stranger and
 * a paying member: the same scanner depth, the same chart history, the same
 * number of backtests, the same paper portfolio.
 *
 * A limit is the softer answer, and usually the better one. A free account
 * that can watch 5 pairs, run 3 backtests a day and see the top 20 rows of
 * the board is a real account doing real work - it just runs out, visibly,
 * at the point where somebody using the product seriously would notice. That
 * is a reason to pay. A locked door is not.
 *
 * SAME SHAPE AS Gate ON PURPOSE. One table, every reader goes through it, the
 * admin gets one screen that IS the answer to "what does each tier get", and
 * Premium::benefits() walks it so the upgrade page describes whatever the
 * operator actually set rather than a list maintained by hand.
 *
 * 0 MEANS UNLIMITED, everywhere, and it is spelled "Unlimited" wherever a
 * number is shown. Every default below is the value that shipped before this
 * class existed, for every tier, so an upgrade changes nothing until the
 * operator decides it should - a limit that silently appeared on a live site
 * would be taking something away from people who already had it.
 */
class Limits
{
    /**
     * key => [
     *   label, unit, blurb, sell,
     *   'keys' => [tier => setting name],
     *   'def'  => [tier => default],
     * ]
     *
     * The setting names for watched pairs are the ones that already existed;
     * nothing is renamed, so an operator who set a watchlist cap finds it here
     * rather than reset.
     */
    public const LIMITS = [
        'watch' => [
            'label' => 'Watched pairs',
            'unit'  => 'pairs',
            'blurb' => 'Coin-and-timeframe pairs a member can have analysed in the background for alerts.',
            'sell'  => 'Watch %s pairs instead of %s, each one analysed in the background whether or not you have the site open.',
            'keys'  => ['guest' => 'watch_max_guest', 'free' => 'watch_max_free', 'paid' => 'watch_max_paid'],
            'def'   => ['guest' => 12, 'free' => 12, 'paid' => 0],
            // Same ceiling bulk_watch_max has always had - these three
            // settings were also (accidentally) defined a second time in the
            // settings sanitizer with a 100000 cap that shadowed this one,
            // so the form's own max="..." attribute has to read it from
            // here rather than repeat a hard-coded number a future edit can
            // drift out of step with again.
            'max'   => 20000,
        ],
        'scanner_rows' => [
            'label' => 'Scanner rows',
            'unit'  => 'rows',
            'blurb' => 'How far down the ranked board a viewer can see. The board is sorted best first, so a smaller number is the top of the list rather than a random slice.',
            'sell'  => 'The whole board rather than its first %2$s rows - %1$s ranked setups instead of the top of the list.',
            'keys'  => ['guest' => 'rows_max_guest', 'free' => 'rows_max_free', 'paid' => 'rows_max_paid'],
            'def'   => ['guest' => 0, 'free' => 0, 'paid' => 0],
        ],
        'backtests' => [
            'label' => 'Backtests per day',
            'unit'  => 'runs',
            'blurb' => 'Member backtester runs. Each one replays the engine over stored history and is the most expensive thing a member can ask this server for.',
            'sell'  => '%s backtests a day rather than %s.',
            'keys'  => ['guest' => 'bt_max_guest', 'free' => 'bt_max_free', 'paid' => 'bt_max_paid'],
            // NOT UNLIMITED BY DEFAULT FOR PEOPLE WHO HAVE NOT PAID.
            //
            // 0 means unlimited here, and this shipped as 0 on every tier -
            // for the feature whose own blurb calls it the most expensive
            // thing a member can ask this server for. A burst limiter caps the
            // RATE at two a minute, which is not a cost ceiling: it is 2,880 a
            // day, per signed-out address, for free, on somebody else's CPU.
            //
            // Paid stays unlimited: they are paying, and a cap the operator
            // did not choose is not a good surprise for a customer. Set any of
            // these to 0 in the panel to restore unlimited.
            'def'   => ['guest' => 1, 'free' => 3, 'paid' => 0],
        ],
        'ai_asks' => [
            'label' => 'AI questions per day',
            'unit'  => 'questions',
            'blurb' => 'Follow-up questions about a setup. Each one reaches the upstream model and costs the operator real money.',
            'sell'  => '%s questions a day about your setups rather than %s.',
            'keys'  => ['guest' => 'ask_max_guest', 'free' => 'ask_max_free', 'paid' => 'ask_max_paid'],
            // Same reasoning, and this one is billed by a third party rather
            // than merely expensive: every question reaches the upstream model
            // on the operator's account. Which tiers may ask at all is
            // ai_ask_tier's job; this is how many, once they may.
            'def'   => ['guest' => 3, 'free' => 5, 'paid' => 0],
        ],
        'positions' => [
            'label' => 'Open followed positions',
            'unit'  => 'positions',
            'blurb' => 'How many paper positions a member can have running at once.',
            'sell'  => 'Follow %s positions at once instead of %s.',
            'keys'  => ['guest' => 'pos_max_guest', 'free' => 'pos_max_free', 'paid' => 'pos_max_paid'],
            'def'   => ['guest' => 0, 'free' => 0, 'paid' => 0],
        ],
    ];

    /** Every tier, weakest first - the order the admin table is drawn in. */
    public const TIERS = ['guest' => 'Signed out', 'free' => 'Free account', 'paid' => 'Premium'];

    /**
     * The limit for one key and one tier. 0 is unlimited and stays 0.
     */
    public static function forTier(string $key, string $tier): int
    {
        $l = self::LIMITS[$key] ?? null;
        if (!$l) {
            return 0;                       // unknown key: never invent a cap
        }
        $tier = isset($l['keys'][$tier]) ? $tier : 'guest';
        $v = (int)Database::setting($l['keys'][$tier], (string)$l['def'][$tier]);
        return max(0, $v);
    }

    /** The limit for whoever is looking. */
    public static function of(string $key, ?string $tier = null): int
    {
        return self::forTier($key, $tier ?? MemberAuth::tier());
    }

    /**
     * Is this viewer inside the limit, given how much they already have?
     *
     * Written as a question about a count rather than a boolean about a tier,
     * because every caller has a count and none of them should be re-deriving
     * "0 means unlimited" for themselves.
     */
    public static function allows(string $key, int $used, ?string $tier = null): bool
    {
        $max = self::of($key, $tier);
        return $max === 0 || $used < $max;
    }

    /** "Unlimited" rather than "0", wherever a number is shown to a person. */
    public static function label(int $n): string
    {
        return $n === 0 ? 'Unlimited' : number_format($n);
    }

    /**
     * Limits where premium genuinely beats free, as sales copy.
     *
     * Walked rather than written, for the same reason Gate::FEATURES is: an
     * operator who raises the free allowance should not leave a paragraph
     * behind claiming otherwise. A limit that is equal on both tiers, or
     * unlimited on both, is not a benefit and does not appear.
     *
     * @return list<array{icon:string,title:string,body:string}>
     */
    public static function benefits(): array
    {
        $out = [];
        foreach (self::LIMITS as $key => $l) {
            $free = self::forTier($key, 'free');
            $paid = self::forTier($key, 'paid');
            // Unlimited beats any number; otherwise a bigger number wins. Equal
            // is not a benefit, and free-better-than-paid is an operator
            // mistake this must not dress up as a feature.
            $better = ($paid === 0 && $free !== 0) || ($paid !== 0 && $free !== 0 && $paid > $free);
            if (!$better || !self::live($key)) {
                continue;
            }
            $out[] = [
                'icon'  => '',
                'title' => $l['label'],
                'body'  => sprintf((string)$l['sell'], self::label($paid), self::label($free)),
            ];
        }
        return $out;
    }

    /**
     * Is the thing this limit bounds switched on at all, for any viewer?
     *
     * Selling "30 backtests a day" while the backtester is off is the same
     * false claim as selling a feature nobody has - see Premium::featureLive(),
     * which this defers to wherever a limit belongs to a gated feature.
     *
     * Deliberately not viewer-scoped: the admin overview table lists every
     * tier's number on one row, so there is no single viewer to check a tier
     * gate against - it needs only "would this ever apply to anybody,"
     * which live() below builds on for the narrower one-viewer question.
     */
    public static function featureOn(string $key): bool
    {
        return match ($key) {
            'backtests'    => Premium::featureLive('backtest'),
            'ai_asks'      => Premium::featureLive('ai_ask'),
            'positions'    => Premium::featureLive('portfolio'),
            'scanner_rows' => Premium::featureLive('scanner'),
            default        => true,
        };
    }

    /**
     * Is the thing this limit bounds reachable by ONE particular viewer?
     *
     * Off entirely is not the only way a limit is unreachable: backtest.php
     * also walls the whole feature off by TIER (member_backtest_tier), a
     * separate gate from member_backtest_enabled. A free viewer that gate
     * excludes can no more reach "3 backtests a day" than one where the
     * feature is switched off, so the tier this is being asked for - the
     * caller's viewer, defaulting to whoever is looking - has to clear that
     * gate too.
     */
    public static function live(string $key, ?string $tier = null): bool
    {
        return self::featureOn($key) && ($key !== 'backtests' || Gate::allows('backtest', $tier));
    }
}
