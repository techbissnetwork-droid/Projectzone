<?php
declare(strict_types=1);

namespace SignalMasterAi;

/**
 * What Premium actually buys, read from the site's own settings.
 *
 * The upgrade page asked for money and never said what for: it opened on
 * "1 - Choose your plan". Every reason to pay was scattered across the
 * features that enforce it - the coin tiers, the watchlist caps, the
 * backtest gate - and none of it was ever assembled into a sentence anyone
 * could read before deciding.
 *
 * THE LIST IS DERIVED, NOT WRITTEN. A hand-written feature list is a promise
 * maintained by memory: switch the backtester on for everyone and the page
 * keeps selling it, turn premium coins off and the page keeps counting them.
 * On a payment page that is not a stale string, it is a false claim about
 * what money buys. So every line here is computed from the setting that
 * actually gates the thing, and a benefit that is no longer premium does not
 * appear at all.
 *
 * It follows that this list can come back SHORT, or empty. That is the
 * correct behaviour and the operator should see it: if nothing on the site
 * is gated, there is nothing to sell, and a page that invents four bullet
 * points to fill the space is the failure this class exists to prevent.
 */
class Premium
{
    /**
     * Every advantage a paid account currently has over a free one.
     *
     * @return list<array{icon:string,title:string,body:string}>
     */
    public static function benefits(): array
    {
        static $memo = null;
        if ($memo !== null) {
            return $memo;
        }
        $out = [];
        $pdo = Database::pdo();

        // Premium coins. Counted rather than claimed, and skipped entirely
        // when the operator sells none - "access to 0 premium coins" is worse
        // than saying nothing.
        $paidCoins = (int)$pdo->query("SELECT COUNT(*) FROM symbols WHERE enabled = 1 AND tier = 'paid'")
                              ->fetchColumn();
        if ($paidCoins > 0) {
            $out[] = [
                'icon'  => '',
                'title' => $paidCoins . ' premium ' . ($paidCoins === 1 ? 'coin' : 'coins'),
                'body'  => 'Every signal on ' . ($paidCoins === 1 ? 'this coin' : 'these coins') . ' comes with '
                         . 'its full trade plan - entry, stop loss and all three targets. Free accounts '
                         . 'see the call and the result, but the levels stay locked.',
            ];
        }

        // Watchlist size. Only worth a line when the two numbers differ - if
        // free and premium get the same allowance it is not a benefit, and
        // saying so would be inviting the reader to check and catch us.
        $free = Watchlists::cap('free');
        $paid = Watchlists::cap('paid');
        if ($paid > $free) {
            $out[] = [
                'icon'  => '',
                'title' => self::capWord($paid) . ' watched pairs',
                'body'  => 'A free account can watch ' . number_format($free) . '. Every watched pair is '
                         . 'analysed in the background whether or not you have the site open, so an alert '
                         . 'reaches you while the tab is closed.',
            ];
        }

        // Everything the gate table currently locks to premium.
        //
        // Walked rather than listed, so locking a feature grows the sales copy
        // on its own and unlocking one removes it. The four entries this
        // replaced were hand-written and had already drifted: the scanner and
        // the portfolio had no gate at all and so were never mentioned, while
        // a feature switched off entirely was still being sold.
        foreach (Gate::FEATURES as $key => $f) {
            if (!Gate::isPremium($key) || !self::featureLive($key)) {
                continue;
            }
            $out[] = [
                'icon'  => (string)$f['icon'],
                'title' => strip_tags((string)$f['label']),
                'body'  => (string)$f['sell'],
            ];
        }

        // The tiered numbers, appended after the features - see Limits.
        // Walked, not written, for the same reason as everything above it: an
        // operator who raises the free allowance must not leave a paragraph
        // behind claiming premium is bigger.
        //
        // 'watch' is skipped here: it is the same Limits::of('watch', ...)
        // setting as the dedicated, better-worded entry above, and printing
        // both told the reader the same fact twice under two different
        // titles.
        foreach (Limits::benefits() as $lb) {
            if ($lb['title'] === Limits::LIMITS['watch']['label']) {
                continue;
            }
            $out[] = $lb;
        }

        return $memo = $out;
    }

    /**
     * Is the feature switched on at all, separately from who may use it?
     *
     * A gate says WHO; these say WHETHER. Selling a premium feature that the
     * operator has turned off entirely is the same false claim as selling one
     * that is free - the reader pays and finds nothing there.
     *
     * Public because the admin gate table needs it for the same reason. A row
     * reading "Ask about a signal - Premium" while the AI layer is switched
     * off two cards away tells an operator their paying members get something
     * nobody gets, and the table is the one place that is supposed to answer
     * that question honestly.
     */
    public static function featureLive(string $key): bool
    {
        switch ($key) {
            case 'bulk_watch':
                return Database::setting('bulk_watch_enabled', '1') === '1';
            case 'top_watch':
                return Database::setting('top_watch_enabled', '1') === '1';
            case 'backtest':
                return Database::setting('member_backtest_enabled', '1') === '1';
            case 'ai_ask':
                return Database::setting('ai_enabled', '1') === '1';
            case 'portfolio':
                return Database::setting('paper_enabled', '1') === '1';
            case 'api_feed':
                return Database::setting('api_feed_enabled', '1') === '1';
            case 'telegram':
                return Telegram::enabled();
            case 'scanner':
                return Master::on('signals', 'scanner');
            case 'live_levels':
                return Database::setting('perf_show_trades', '1') === '1'
                       && Master::on('signals', 'track');
            case 'perf_trades':
                return Database::setting('perf_show_trades', '1') === '1'
                       && Master::on('signals', 'track');
            case 'perf_coins':
                // Nothing to gate when the breakdowns pane is off entirely:
                // the table does not exist for anyone.
                return Database::setting('perf_show_breakdowns', '1') === '1'
                       && Master::on('signals', 'track');
            case 'mtf':
                return Database::setting('mtf_strip_enabled', '1') === '1';
            default:
                return true;
        }
    }

    /** "Unlimited" reads better than the sentinel the cap uses internally. */
    private static function capWord(int $cap): string
    {
        return $cap >= 20000 ? 'Unlimited' : number_format($cap);
    }

    /**
     * Should this viewer be shown the upgrade prompt right now?
     *
     * Deliberately narrow. A prompt that appears for someone who has already
     * paid is an insult, one that appears for a guest asks them to buy before
     * they have an account, and one that appears on every visit trains people
     * to close it without reading - after which it can never work again, and
     * the cost of that is permanent. So: free members only, not before the
     * operator's delay, and not again for the operator's repeat window after
     * it is dismissed.
     *
     * Returns the reason it fired ('new' or 'aged') or '' for no.
     */
    public static function promptFor(?array $member): string
    {
        if (Database::setting('upsell_popup_enabled', '1') !== '1' || !$member) {
            return '';
        }
        if (!self::benefits()) {
            return '';      // nothing is gated - there is nothing to sell
        }
        // Paid, including a plan that has run out: an expired member is a
        // renewal conversation, and this prompt is written for a first
        // purchase. They get the normal expiry notice instead.
        if (($member['tier'] ?? 'free') !== 'free') {
            return '';
        }
        $dismissed = (int)($member['upsell_dismissed'] ?? 0);
        if ($dismissed > 0) {
            $repeat = max(0, (int)Database::setting('upsell_popup_repeat', '14')) * 86400;
            if ($repeat === 0 || time() - $dismissed < $repeat) {
                return '';
            }
        }
        $age = time() - (int)($member['created_at'] ?? time());
        // A brand-new account, once: the moment somebody registers they are
        // as interested as they will ever be, and telling them what the paid
        // tier is then is information rather than pestering. Off by default
        // for operators who would rather let people settle in first.
        if ($dismissed === 0 && Database::setting('upsell_popup_new', '1') === '1'
            && $age < 3600) {
            return 'new';
        }
        $days = max(0, (int)Database::setting('upsell_popup_days', '3'));
        return $age >= $days * 86400 ? 'aged' : '';
    }
}
