<?php
declare(strict_types=1);

namespace SignalMasterAi;

/**
 * Which features each tier gets, in one table.
 *
 * The gates already existed and were scattered: the backtester read
 * member_backtest_tier, the AI panel read ai_ask_tier, watch-all read
 * bulk_watch_tier, and the scanner and the portfolio read nothing at all.
 * Each was correct on its own and there was nowhere to see them together -
 * so "what does a free account actually get" was a question that could only
 * be answered by reading five files, and the answer was never written down
 * anywhere a customer could see it either.
 *
 * ONE TABLE, AND EVERYTHING READS IT. The admin gets a single screen that
 * IS the answer to that question. Premium::benefits() walks the same table,
 * so the upgrade page and the prompt describe whatever the operator actually
 * locked rather than a list somebody maintains by hand - lock the scanner
 * and the sales copy grows a line about the scanner, on its own.
 *
 * EXISTING KEYS ARE KEPT. A gate that already had a setting keeps that
 * setting name, so an operator who configured the backtester or the AI panel
 * before this existed finds their choice intact and in the new screen. New
 * gates get new keys; nothing is renamed and nothing is silently re-defaulted.
 */
class Gate
{
    /**
     * tier: 'any' (including signed-out), 'free' (any registered member),
     *       'paid' (premium only).
     *
     * `sell` is the line that appears on the upgrade page when the feature is
     * set to premium. It is phrased as what the reader gets, not as what they
     * are missing - a benefit list written as a list of denials reads as a
     * complaint about the customer.
     */
    /**
     * Measured expectancy per timeframe on the reference install, after costs.
     *
     * Here rather than in the admin page because THREE controls show it - the
     * master timeframe list, the scan's picker, and the weekly
     * re-calibration's - and it was written out separately in two of them.
     * Two copies of a number is two numbers as soon as one is updated, and
     * the whole point of printing it beside a checkbox is that the operator
     * trusts it enough to choose against it.
     */
    public const TF_EXPECTANCY = [
        '3m' => '-0.133R', '5m' => '-0.162R', '15m' => '-0.092R', '1h' => '+0.080R',
    ];

    public const FEATURES = [
        'scanner' => [
            'setting' => 'gate_scanner',
            'default' => 'paid',
            'label'   => 'Market scanner',
            'icon'    => '',
            // Used to be its own page (scanner.php); the "Live setups" sheet
            // on charts.php took over everything it did and the page was
            // removed, so this is the one place the feature lives now.
            'where'   => 'charts.php',
            'blurb'   => 'The whole watchlist ranked in one board. Without it a member opens coins one at a time to find a setup.',
            'sell'    => 'Every coin on the watchlist ranked in one board, so a setup finds you instead of you opening charts one at a time.',
        ],
        'portfolio' => [
            'setting' => 'gate_portfolio',
            'default' => 'free',
            'label'   => 'Paper portfolio &amp; journal',
            'icon'    => '',
            'where'   => 'portfolio.php',
            'blurb'   => 'Follow signals on paper and keep a journal of the results.',
            'sell'    => 'Follow the calls on paper and keep a journal, so you know what your own results would have been.',
        ],
        'backtest' => [
            // Pre-existing key, kept.
            'setting' => 'member_backtest_tier',
            'default' => 'paid',
            'label'   => 'Backtesting',
            'icon'    => '',
            'where'   => 'backtest.php',
            'blurb'   => 'Run the engine over history on any coin and timeframe.',
            'sell'    => 'Run the engine over historical data on any coin and timeframe and see what it would have done, instead of taking our word for the record.',
        ],
        'mtf' => [
            'setting' => 'gate_mtf',
            'default' => 'paid',
            'label'   => 'Multi-timeframe panel',
            'icon'    => '',
            'where'   => 'charts.php',
            'blurb'   => 'The same coin read on every timeframe at once, next to the chart.',
            'sell'    => 'The same coin read on every timeframe at once - the check that stops you taking a 15m long into a falling daily.',
        ],
        'perf_coins' => [
            // Each coin's own verified win rate - used to be a standalone
            // table on the track record page, then the win-rate column and
            // filter on the scanner page, now the same on the "Live setups"
            // sheet (charts.php): a win-rate sort and column on the setups
            // board, plus a "By coin" tab ranking every tracked coin by its
            // own record (see TrackRecord::topCoins() and the api.php
            // 'board' action's view=bycoin branch). Moved rather than
            // duplicated each time: a sheet next to the chart a member can
            // actually act on beats a table that could only be read.
            //
            // The track record page itself stays open to everyone: the
            // headline win rate, the average R, the equity curve, how
            // signals ended. Those prove the engine works, which is the
            // reason a stranger keeps reading.
            //
            // WHICH COINS is still the part worth paying for. "TSTUSDT 100%
            // over 5, NEARUSDT 43% over 7" is not evidence that the site
            // works - it is a shortlist of what to trade and what to avoid,
            // derived from verified outcomes nobody else has. Given away, a
            // visitor has the useful half of a premium account without an
            // account.
            'setting' => 'perf_coins_tier',
            'default' => 'paid',
            'label'   => 'Per-coin win rate',
            'icon'    => '',
            'where'   => 'charts.php',
            'blurb'   => 'Which coins actually win, ranked by verified outcome - not just the site-wide average. Filter and sort the live setups by it, or browse every coin\'s record on its own.',
            'sell'    => 'Each coin\'s own verified win rate, right on the live setups sheet - sort by it or browse the full board, so you follow the pairs with a record instead of the ones in the headlines.',
        ],
        'live_levels' => [
            // The entry, stop and targets of a call that is STILL RUNNING.
            //
            // The distinction is the trade's state, not the coin's. A settled
            // plan is history: publishing it costs nothing, and it is what
            // makes the record checkable rather than merely asserted, so it is
            // open to everyone. A live plan is the product - it is the thing a
            // member would act on in the next hour - and giving it away is
            // giving away the reason to subscribe.
            //
            // Same call, two different things, decided by whether it has
            // finished.
            'setting' => 'live_levels_tier',
            'default' => 'paid',
            'label'   => 'Levels on live calls',
            'icon'    => '',
            'where'   => 'performance.php',
            'blurb'   => 'Entry, stop and targets on calls that are still running. Settled calls show theirs to everyone.',
            'sell'    => 'The entry, stop and targets on calls that are still running - the ones you could still act on, not just the ones already finished.',
        ],
        'perf_trades' => [
            // Every verified trade, one row each.
            //
            // A tier BELOW the per-coin table on purpose, because the two are
            // different kinds of thing and the ladder has to be honest. The
            // headline figures and the curve prove the engine works and stay
            // open to everyone. This log is the AUDIT of those figures - what
            // lets a sceptic check that nothing was added or removed after the
            // fact - so it goes to anyone who registers rather than only to
            // buyers; a record nobody can check is worth less than one they
            // can.
            //
            // It is gated at all because it is the raw material of the table
            // above it: coin, timeframe and R on every settled trade is the
            // per-coin ranking with the arithmetic left to the reader. Open to
            // guests, that lock would be decorative.
            'setting' => 'perf_trades_tier',
            'default' => 'any',
            'label'   => 'Every verified trade',
            'icon'    => '',
            'where'   => 'performance.php',
            'blurb'   => 'The full settled-trade log behind the headline figures, one row per call.',
            'sell'    => 'The full trade log behind the headline figures - every settled call with its entry, stop, targets and result, so the record can be checked rather than believed.',
        ],
        'ai_ask' => [
            // Pre-existing key, kept.
            'setting' => 'ai_ask_tier',
            'default' => 'paid',
            'label'   => 'Ask about a signal',
            'icon'    => '',
            'where'   => 'charts.php',
            'blurb'   => 'Follow-up questions answered against that signal\'s own data.',
            'sell'    => 'Follow-up questions on a specific setup - why this entry, what invalidates it - answered against that signal\'s own data.',
        ],
        'bulk_watch' => [
            // Pre-existing key, kept.
            'setting' => 'bulk_watch_tier',
            'default' => 'paid',
            'label'   => 'Watch every coin in one tap',
            'icon'    => '',
            'where'   => 'charts.php',
            'blurb'   => 'One tap to watch every accessible coin on the chosen timeframes.',
            'sell'    => 'One tap to watch every coin you can access, rather than adding them one at a time.',
        ],
        'top_watch' => [
            // Pre-existing key, kept.
            'setting' => 'top_watch_tier',
            'default' => 'paid',
            'label'   => 'Watch the best-performing coins',
            'icon'    => '',
            'where'   => 'charts.php',
            'blurb'   => 'One tap to follow the coins with the best verified record.',
            'sell'    => 'One tap to follow the coins with the best verified win rate, or the best actual return - ranked from the track record you can read on this site.',
        ],
        'telegram' => [
            'setting' => 'gate_telegram',
            'default' => 'paid',
            'label'   => 'Telegram alerts',
            'icon'    => '',
            'where'   => 'account.php',
            'blurb'   => 'Signals delivered to Telegram as well as email and browser push.',
            'sell'    => 'Signals pushed to Telegram the moment they fire, as well as by email and browser notification.',
        ],
        'api_feed' => [
            'setting' => 'gate_api_feed',
            'default' => 'paid',
            'label'   => 'Signal API feed',
            'icon'    => '',
            'where'   => 'api.php?action=feed',
            'blurb'   => 'A token-authenticated JSON feed for a member\'s own bot.',
            'sell'    => 'A JSON feed of every signal, with your own token - point a bot at it.',
        ],
    ];

    /** The tier a feature currently needs. */
    public static function tier(string $key): string
    {
        $f = self::FEATURES[$key] ?? null;
        if ($f === null) {
            // An unknown key must not silently open a gate. Locking it is the
            // safe direction: a feature nobody can reach is a bug report, a
            // feature everybody can reach is a refund.
            return 'paid';
        }
        $v = Database::setting($f['setting'], $f['default']);
        return in_array($v, ['any', 'free', 'paid'], true) ? $v : $f['default'];
    }

    /** May this viewer use it? */
    public static function allows(string $key, ?string $viewerTier = null): bool
    {
        return MemberAuth::meetsTier(self::tier($key), $viewerTier);
    }

    /** Is this feature premium-only right now? Drives the sales copy. */
    public static function isPremium(string $key): bool
    {
        return self::tier($key) === 'paid';
    }

    /**
     * The current tier of a feature, written for a person.
     *
     * Several feature cards in the admin panel used to carry their own copy of
     * the tier select, which meant two controls posting one setting name in
     * one form - and a browser posts only the last of those, so changing the
     * one you were looking at did nothing and said nothing. They show the
     * decision now and leave the making of it to the gate table. Spelt out
     * here rather than in each card, because three cards each writing their
     * own three-way match is how the wording drifts apart again.
     */
    public static function tierLabel(string $key): string
    {
        return [
            'any'  => 'Everyone, including signed-out visitors',
            'free' => 'Any registered member, free included',
            'paid' => 'Premium members only',
        ][self::tier($key)] ?? 'Premium members only';
    }

    /**
     * Stop here and show the upgrade wall.
     *
     * A wall, not a redirect to the pricing page. Somebody who followed a
     * link to the scanner wants the scanner: telling them what it is, what it
     * costs and where to get it - on the URL they asked for - respects that,
     * where a bounce to /upgrade loses the context and reads as a trap. The
     * page they wanted is still in the address bar when they come back from
     * paying.
     */
    public static function wall(string $key, string $current = ''): void
    {
        $f = self::FEATURES[$key] ?? ['label' => 'This feature', 'icon' => '', 'blurb' => ''];
        $need = self::tier($key);
        $signedIn = MemberAuth::current() !== null;
        // noindex: this is a paywall, not content. Every gated page serves
        // the same wall, so leaving them indexable offers a search engine a
        // set of near-identical pages under different addresses - and the one
        // that ranks is a page that tells the reader they cannot have what
        // they searched for. `follow` stays on, because the links out of it
        // (the plans, the chart) are worth crawling.
        View::head(strip_tags((string)$f['label']), (string)$f['blurb'], ['noindex' => true]);
        View::topbar($current);
        ?>
<div class="wrap gate-wall">
  <div class="gate-card">
    <div class="gate-ic"><?= $f['icon'] ?></div>
    <h1><?= $f['label'] ?></h1>
    <p class="gate-blurb"><?= sma_e((string)$f['blurb']) ?></p>
    <?php if ($need === 'paid'): ?>
      <p class="gate-need">This one is part of <strong>Premium</strong>.</p>
      <?php // The rest of what the same money buys, from the same derived
            // list the upgrade page uses - somebody who hit one wall should
            // not have to guess whether it is worth it for that alone. ?>
      <?php $others = array_values(array_filter(Premium::benefits(),
              static fn($b) => stripos($b['title'], strip_tags((string)$f['label'])) === false)); ?>
      <?php if ($others): ?>
        <p class="gate-also">It also includes:</p>
        <ul class="perk-list">
          <?php foreach (array_slice($others, 0, 4) as $b): ?>
            <li><span class="perk-ic"><?= $b['icon'] ?></span>
              <span><strong><?= sma_e($b['title']) ?></strong></span></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <a class="btn gate-go" href="upgrade.php">See the plans</a>
      <?php if (!$signedIn): ?>
        <p class="gate-alt">Already premium? <a href="account.php">Log in</a>.</p>
      <?php endif; ?>
    <?php else: ?>
      <p class="gate-need">This one is for registered members - a <strong>free</strong> account is enough.</p>
      <a class="btn gate-go" href="account.php?tab=register">Create a free account</a>
      <p class="gate-alt">Already have one? <a href="account.php">Log in</a>.</p>
    <?php endif; ?>
    <?php // Never a dead end. Whatever the reader cannot have, they can still
          // do the thing this site is for. ?>
    <?php // The way out points at the CHART, which is never gated.
          //
          // It read "Back to live signals" while linking to charts.php, which
          // was merely loose wording - until "Live signals" became the name of
          // the scanner. This screen is what a member sees when the scanner is
          // locked, so sending them "back to live signals" would now be
          // sending them back to the door they just bounced off. ?>
    <p class="gate-alt"><a href="charts.php">&larr; Back to the chart</a></p>
  </div>
</div>
        <?php
        View::footer();
        exit;
    }
}
