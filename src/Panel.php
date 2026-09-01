<?php
declare(strict_types=1);

namespace SignalMasterAi;

/**
 * The admin panel's map, by subject.
 *
 * Written without reference to how these pages were arranged before. The old
 * grouping had grown out of the order things were built - "Market & scan"
 * beside "Alerts", the money split across three places, appearance filed under
 * a settings tab nobody opened - and regrouping it a page at a time kept
 * producing the same shape with different labels.
 *
 * So: seven subjects, each named for a thing the operator owns rather than for
 * a screen. Money is one subject, not plans in one place and payments in
 * another. Delivery is one subject, whether it goes out by email, Telegram,
 * push or a webhook. Appearance and the words on the site are one subject,
 * not a settings tab. Everything that is only ever true of the server -
 * backups, cron, logs, the API - is System, and nothing else is.
 *
 * One definition, used by the header dropdowns and by the landing grid, so
 * there is exactly one arrangement of this panel and no way for the two to
 * drift.
 *
 * Each item is [label, href, one line saying what it is for].
 *
 * One entry per destination, which was not true before: thirty-two entries
 * pointed at twenty-one places. Four labels under Delivery - Email & SMTP,
 * Alert rules, Telegram & push, Broadcast - all opened the same settings tab,
 * and five under Appearance did the same. A menu that offers four choices and
 * performs one is teaching the wrong map, exactly as the dashboard grid was
 * when every entry in a box led to the first one.
 *
 * The words were worth keeping even though the entries were not, because
 * "Telegram" and "SEO" are how an operator hunts. They moved into the
 * description line, which the dropdown already shows, and the settings page
 * has a search that matches field names as well as labels.
 *
 * SETTINGS APPEARS ONCE. It used to appear seven times: six subjects each
 * carried a settings.php#section link, and System carried "All settings" on
 * top of them. That made sense while the settings page had no visible map of
 * its own - the nav WAS the map, spread across the panel. The settings page
 * now opens on a strip of its seven sections, so the nav was showing the same
 * seven destinations a second time, in a different order, under different
 * words. Two maps of one page is worse than one: they disagree the moment
 * either is edited, and an operator learns neither.
 *
 * So the nav says where things live and Settings says what is in Settings.
 * "Appearance & content" went with them - its only destination was
 * settings.php#site, so it was a label on a settings section rather than a
 * subject of its own, and the section is still there under that name.
 * Every settings.php#section URL still works; the panel simply stops being a
 * second table of contents for them.
 */
class Panel
{
    /**
     * key => [icon, title, one-line subject, items[]]
     *
     * @var array<string,array{0:string,1:string,2:string,3:array<int,array{0:string,1:string,2:string}>}>
     */
    public const CATEGORIES = [
        'coins' => ['', 'Coins & market', 'What the site watches, and where the prices come from.', [
            ['Coin list',          'symbols.php',          'Add, remove and configure every pair'],
            ['News sources',       'news.php',             'Feeds and the economic calendar'],
        ]],
        'signals' => ['', 'Signals & engine', 'How a call is decided, and what happened to the ones already made.', [
            ['Signal history',           'signals.php',          'Every call, with its outcome'],
            ['Rules & weights',          'knowledge.php',        'The rule set the engine scores against'],
            ['Engine lab',               'engine.php',           'Scoring, regime, structure and self-tuning'],
        ]],
        'members' => ['', 'Members & access', 'Who has an account, and what it lets them see.', [
            ['Member list',         'members.php',           'Accounts, tiers and verification'],
        ]],
        'money' => ['', 'Money', 'Everything that takes a payment or promises one.', [
            ['Plans & prices',   'billing.php#plans',    'What is sold, for how long, at what price'],
            ['Payment gateways', 'billing.php#gateways', 'Crypto checkout and manual methods'],
            ['Payments',         'payments.php',         'What has been paid, and what is waiting'],
            ['Coupons',          'billing.php#coupons',  'Discount codes'],
        ]],
        'delivery' => ['', 'Delivery', 'Getting a call to a member, by whatever route.', [
            ['Email log',          'emails.php',           'Every message the site tried to send'],
        ]],
        'system' => ['', 'System', 'The parts that are only ever about the server.', [
            ['Settings',          'settings.php',           'Every setting in the application, in seven sections'],
            ['API & webhooks',    'api.php',                'The JSON feed and outgoing hooks'],
            ['Delete data',       'data.php',               'Remove coins, history or learning - one place, one list'],
            ['Change log',        'audit.php',              'Who changed what, and when'],
            ['Error log',         'errors.php',             'What broke that the site kept running through'],
        ]],
    ];

    /**
     * Which category a page belongs to, so the header can mark it current.
     * Matched on the file name only - anchors are views of the same page.
     */
    public static function categoryOf(string $script): string
    {
        foreach (self::CATEGORIES as $key => [, , , $items]) {
            foreach ($items as [, $href,]) {
                if (explode('#', $href)[0] === $script) {
                    return $key;
                }
            }
        }
        return '';
    }

    /**
     * A category with its hidden items removed, or null when a master switch
     * has taken the whole subject away.
     *
     * Signals off means the signal pages are gone from the panel too - the
     * rule established with the master switches, applied here so the grid and
     * the dropdowns cannot disagree about it.
     */
    public static function visible(string $key): ?array
    {
        if (!isset(self::CATEGORIES[$key])) {
            return null;
        }
        [$icon, $title, $blurb, $items] = self::CATEGORIES[$key];
        $gate = ['signals' => 'signals', 'delivery' => 'alerts'][$key] ?? '';
        if ($gate !== '' && !Master::master($gate)) {
            return null;
        }
        if ($key === 'coins' && !Master::master('news')) {
            $items = array_values(array_filter($items, fn($i) => $i[1] !== 'news.php'));
        }
        // A subject with nothing left in it is not a subject. It matters more
        // now that the settings links have gone: these lists are short, and a
        // master switch that empties one would otherwise leave a dropdown in
        // the header that opens onto nothing.
        if (!$items) {
            return null;
        }
        return [$icon, $title, $blurb, $items];
    }

    /** Every category still on show, in order. */
    public static function all(): array
    {
        $out = [];
        foreach (array_keys(self::CATEGORIES) as $key) {
            $c = self::visible($key);
            if ($c !== null) {
                $out[$key] = $c;
            }
        }
        return $out;
    }
}
