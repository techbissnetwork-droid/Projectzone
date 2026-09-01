<?php
declare(strict_types=1);

namespace SignalMasterAi;

/**
 * One switch per thing the site does, and the exceptions underneath it.
 *
 * There are forty-seven separate *_enabled settings in this application and
 * not one of them is a master. Turning signals off meant finding the scan
 * switch, the chart switch, the alert switches and the cron settings, and
 * knowing that missing one leaves the feature half on - still burning cron
 * budget, still mailing people, still answering the API - while the operator
 * believes it is off.
 *
 * Two levels, and only two:
 *
 *   MASTER   off means off everywhere. Not scanned, not analysed by cron, not
 *            drawn on the chart, not listed on the scanner, not alerted, not
 *            served by the API. There is no surface that keeps working
 *            because its own switch was left on - the master is checked
 *            first and nothing below it can override it.
 *
 *   CUSTOM   with the master on, each surface may still be turned off on its
 *            own. This is the "chart but not scanner" case, which is a real
 *            thing operators want and the reason a single switch is not
 *            enough by itself.
 *
 * Both default to on, so an install that has never opened this screen behaves
 * exactly as it did before.
 *
 * Legacy keys are honoured rather than replaced. An operator who set
 * news_enabled = 0 two releases ago still has news off, and the switch they
 * remember still works; the master sits above it. This is why on() reads
 * three settings instead of one, and why the answer is an AND of all three:
 * anything that used to turn a surface off must keep turning it off.
 */
class Master
{
    /**
     * feature => [label, blurb, surfaces[]]
     * surface => [label, what it covers, legacy setting key or '']
     *
     * @var array<string,array{0:string,1:string,2:array<string,array{0:string,1:string,2:string}>}>
     */
    public const FEATURES = [
        'signals' => ['Signals', 'The engine that reads the market and produces calls.', [
            // No legacy key. fullscan_enabled was mapped here and it is not an
            // on/off switch for the scan - it chooses WHAT the scan covers,
            // every coin in the rotation or only pairs somebody watches. Wired
            // as the master's legacy switch it did both, so picking the option
            // labelled "only pairs someone watches" turned the whole scan off:
            // cron cleared its target list, watched pairs included, and stored
            // nothing at all. The setting's own description was false, and the
            // failure is silent - the worker keeps reporting healthy runs.
            //
            // This surface's own switch (master_signals_scan) is the off
            // switch, and it always was; an operator who wanted the scan off
            // has it one line above in the same card.
            'scan'    => ['Background scan', 'cron reads the market and stores verdicts', ''],
            'chart'   => ['Chart page', 'the signal card and levels on the chart', ''],
            'scanner' => ['Scanner board', 'the scanner, top setups and the home page list', ''],
            'track'   => ['Track record', 'the public page of settled calls', 'performance_page_enabled'],
            'alerts'  => ['Alerts', 'signal flips are allowed to notify anyone', ''],
            'api'     => ['JSON API', 'the token feed and the public signal endpoints', 'api_feed_enabled'],
        ]],
        'news' => ['News & events', 'Headlines and the economic calendar.', [
            'fetch'  => ['Fetching', 'cron pulls new headlines at all', ''],
            'chart'  => ['Chart page', 'the news and events panel beside the chart', ''],
            'home'   => ['Home page', 'the headline strip on the landing page', ''],
            'engine' => ['Engine input', 'sentiment is allowed to move the score', ''],
        ]],
        'alerts' => ['Alert delivery', 'How a call reaches a member once it fires.', [
            'email'    => ['Email', 'flip alerts and digests by email', ''],
            'telegram' => ['Telegram', 'the bot messages linked members', 'tg_enabled'],
            'push'     => ['Web push', 'browser notifications', ''],
            'webhook'  => ['Webhooks', "POSTs to a member's own endpoint", 'webhooks_enabled'],
        ]],
        'ai' => ['AI explanations', 'The optional plain-English write-up of a signal.', [
            'ask'     => ['Ask a question', 'the member-facing question box', ''],
            'explain' => ['Signal write-up', 'the generated explanation on a signal', ''],
        ]],
    ];

    /** Is this feature on at all? */
    public static function master(string $feature): bool
    {
        if (!isset(self::FEATURES[$feature])) {
            return true;               // unknown feature: never invent a block
        }
        return Database::setting('master_' . $feature, '1') === '1';
    }

    /**
     * Is this feature on for this surface?
     *
     * With no surface given, answers for the feature as a whole. The master is
     * checked first and short-circuits, which is the entire contract: an
     * operator who turns the master off does not then have to be right about
     * every switch underneath it.
     */
    public static function on(string $feature, string $surface = ''): bool
    {
        if (!self::master($feature)) {
            return false;
        }
        if ($surface === '' || !isset(self::FEATURES[$feature][2][$surface])) {
            return true;
        }
        if (Database::setting('master_' . $feature . '_' . $surface, '1') !== '1') {
            return false;
        }
        // The switch this surface had before there was a master. Still binding.
        $legacy = self::FEATURES[$feature][2][$surface][2];
        return $legacy === '' || Database::setting($legacy, '1') === '1';
    }

    /**
     * Admin nav keys that belong to a feature, hidden when its master is off.
     *
     * "Off everywhere" has to include this panel, or the operator turns
     * signals off and is still looking at a Signal history page, an Engine
     * lab and a tab of thresholds for an engine that is not running. A switch
     * that leaves its own controls on screen is telling you it did not really
     * take.
     *
     * What is never hidden: the dashboard, coins, members, billing, the
     * settings page itself, the change log and the error log. Those are how
     * an operator finds their way back - including to the master switch that
     * turned the rest off.
     *
     * @var array<string,string[]>
     */
    private const ADMIN_PAGES = [
        'signals' => ['signals', 'knowledge', 'engine', 'sig_set'],
        'news'    => ['news'],
        'alerts'  => ['emails', 'alert_set'],
        'ai'      => [],
    ];

    /** Nav keys to leave out of the sidebar right now. */
    public static function hiddenAdminPages(): array
    {
        $hidden = [];
        foreach (self::ADMIN_PAGES as $feature => $keys) {
            if (!self::master($feature)) {
                $hidden = array_merge($hidden, $keys);
            }
        }
        return $hidden;
    }

    /**
     * Which feature owns an admin settings card, so the card can hide with it.
     * Returns '' for cards that belong to no feature and always show.
     */
    public static function tabFeature(string $tab): string
    {
        return ['signals' => 'signals', 'alerts' => 'alerts'][$tab] ?? '';
    }

    /** Every surface currently on, for the admin summary line. */
    public static function activeCount(string $feature): array
    {
        $surfaces = self::FEATURES[$feature][2] ?? [];
        $on = 0;
        foreach (array_keys($surfaces) as $k) {
            if (self::on($feature, $k)) {
                $on++;
            }
        }
        return [$on, count($surfaces)];
    }

    /**
     * Save one feature's switches from a posted form.
     *
     * Absent checkboxes mean off, which is why the master is read from an
     * explicit hidden field rather than from the presence of the box: a form
     * that failed to render its own controls must not read as "turn
     * everything off".
     */
    public static function save(string $feature, array $post): void
    {
        if (!isset(self::FEATURES[$feature])) {
            return;
        }
        $wasOn = self::master($feature);
        $nowOn = ($post['master_' . $feature] ?? '') === '1';
        Database::setSetting('master_' . $feature, $nowOn ? '1' : '0');
        if ($wasOn !== $nowOn) {
            // See the note in settings.php: there is no ACTOR_ADMIN constant.
            // This fataled here, after the master had been written and before
            // the per-surface switches below - so a master could be toggled
            // while none of its surfaces ever saved.
            Audit::log('master.' . $feature, 'master', $wasOn ? 'on' : 'off', $nowOn ? 'on' : 'off');
        }
        $picked = (array)($post['surface_' . $feature] ?? []);
        foreach (array_keys(self::FEATURES[$feature][2]) as $k) {
            Database::setSetting('master_' . $feature . '_' . $k,
                in_array($k, $picked, true) ? '1' : '0');
        }
    }
}
