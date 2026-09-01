<?php
declare(strict_types=1);

namespace SignalMasterAi;

/**
 * Per-member configuration: strategy profile, risk settings, alert filters,
 * watchlists and timezone.
 *
 * Everything here used to be either a single site-wide admin setting or a
 * browser localStorage key. Both are wrong for different reasons: a site-wide
 * threshold forces one risk appetite on everybody, and a localStorage
 * watchlist silently disappears when a member opens the site on their phone -
 * even though the server was already storing the same pairs for push and
 * email delivery, and simply never read them back.
 */
class MemberPrefs
{
    /** Worked-example capital, used only until a member states their own. */
    public const DEFAULT_FUNDS = 10000.0;

    /**
     * Built-in strategy profiles. These map a single human choice onto the
     * engine controls that actually implement it, so a member does not have to
     * understand category caps to say "I want fewer, stronger signals".
     */
    public const PROFILES = [
        'conservative' => [
            'label' => 'Conservative',
            'blurb' => 'Fewer signals, only high-confluence setups. Wider stops, grade B and above.',
            'buy_threshold' => 4.5, 'sell_threshold' => -4.5,
            'min_categories' => 3, 'min_grade' => 'B',
        ],
        'balanced' => [
            'label' => 'Balanced',
            'blurb' => 'The default engine settings - a middle ground between frequency and quality.',
            'buy_threshold' => null, 'sell_threshold' => null,
            'min_categories' => 2, 'min_grade' => 'any',
        ],
        'aggressive' => [
            'label' => 'Aggressive',
            'blurb' => 'More signals, earlier entries. Expect more noise and more stop-outs.',
            'buy_threshold' => 2.0, 'sell_threshold' => -2.0,
            'min_categories' => 1, 'min_grade' => 'any',
        ],
    ];

    private static array $memo = [];

    /** Full preference record for a member, with defaults filled in. */
    public static function get(int $memberId): array
    {
        if ($memberId <= 0) {
            return self::defaults();
        }
        if (isset(self::$memo[$memberId])) {
            return self::$memo[$memberId];
        }
        $stmt = Database::pdo()->prepare('SELECT * FROM member_prefs WHERE member_id = ?');
        $stmt->execute([$memberId]);
        $row = $stmt->fetch();
        $stmt->closeCursor();
        $prefs = self::defaults();
        if ($row) {
            $prefs = array_merge($prefs, [
                'profile'        => (string)$row['profile'],
                'timezone'       => (string)$row['timezone'],
                'account_size'   => (float)$row['account_size'],
                'risk_pct'       => (float)$row['risk_pct'],
                'leverage'       => (float)$row['leverage'],
                'min_grade'      => (string)$row['min_grade'],
                'min_confidence' => (float)$row['min_confidence'],
                'directions'     => (string)$row['directions'],
                'quiet_from'     => (int)$row['quiet_from'],
                'quiet_to'       => (int)$row['quiet_to'],
                'max_alerts_day' => (int)$row['max_alerts_day'],
                'webhook_url'    => (string)$row['webhook_url'],
                // Absent on a database that has not taken the column yet, and
                // the default is on - see the note in Database::migrate.
                'trade_email'    => (string)($row['trade_email'] ?? '1'),
                'rule_weights'   => json_decode((string)$row['rule_weights'], true) ?: [],
                'disabled_categories' => array_values(array_filter(
                    explode(',', (string)$row['disabled_categories']))),
                'alert_types'    => array_values(array_filter(array_map(
                    'intval', explode(',', (string)($row['alert_types'] ?? ''))))),
            ]);
        }
        self::$memo[$memberId] = $prefs;
        return $prefs;
    }

    public static function defaults(): array
    {
        return [
            'profile'        => 'balanced',
            'timezone'       => '',
            'account_size'   => 0.0,
            'risk_pct'       => 1.0,
            'leverage'       => 1.0,
            'min_grade'      => 'any',
            // Which of the three signal types to be alerted about. EMPTY IS
            // ALL OF THEM, deliberately: a member who never opens this control
            // keeps hearing about everything they hear about today.
            'alert_types'    => [],
            'min_confidence' => 0.0,
            'directions'     => 'both',
            'quiet_from'     => -1,
            'quiet_to'       => -1,
            'max_alerts_day' => 0,
            'webhook_url'    => '',
            // Receipts for a member's own paper trades: opened, and closed
            // with the money. On by default - a trade of your own is not
            // marketing, it is the receipt - and one switch on the account
            // page turns both off.
            'trade_email'    => '1',
            'rule_weights'   => [],
            'disabled_categories' => [],
        ];
    }

    /** Persist a partial update. Unknown keys are ignored. */
    public static function save(int $memberId, array $patch): void
    {
        if ($memberId <= 0) {
            return;
        }
        $cur = self::get($memberId);
        $new = array_merge($cur, array_intersect_key($patch, $cur));

        // Validation: these values drive money decisions and alert volume.
        $new['profile'] = isset(self::PROFILES[$new['profile']]) ? $new['profile'] : 'balanced';
        $new['account_size'] = max(0.0, min(1e12, (float)$new['account_size']));
        $new['risk_pct'] = max(0.05, min(100.0, (float)$new['risk_pct']));
        // The ceiling is the operator's, read from one place - see
        // Paper::maxLeverage(). A second hardcoded 125 here is how a lowered
        // cap ends up enforced on the ticket and ignored in preferences.
        $new['leverage'] = max(1.0, min(Paper::maxLeverage(), (float)$new['leverage']));
        $new['min_grade'] = in_array($new['min_grade'], ['any', 'C', 'B', 'A', 'A+'], true) ? $new['min_grade'] : 'any';
        // Never store a grade below the site floor. The form disables the
        // control when members may not set one, and a disabled input is a
        // suggestion to a browser, not a rule to a POST - so the rule lives
        // here. Storing a looser value would be harmless today (the floor is
        // applied at dispatch either way) and would become a leak the moment
        // anybody reordered those two checks.
        if (Database::setting('member_grade_filter', '0') !== '1') {
            $new['min_grade'] = 'any';
        } else {
            $order = ['any' => -1, 'C' => 0, 'B' => 1, 'A' => 2, 'A+' => 3];
            // The real floor, never looser than what the site publishes - see
            // Publish::alertFloor(). alertFloor() returns '' for "no floor",
            // which $order does not have a key for; ?? -1 below reads that the
            // same way it reads 'any' - correctly, as no restriction.
            $floor = Publish::alertFloor();
            if (($order[$new['min_grade']] ?? -1) < ($order[$floor] ?? -1)) {
                $new['min_grade'] = $floor === '' ? 'any' : $floor;
            }
        }
        // Tiers, deduplicated, in order, and never all three stored as a
        // selection: choosing every type is the same as choosing none, and
        // storing it as a list would make a later fourth type silently
        // excluded for everybody who had ticked "all" today.
        $types = array_values(array_unique(array_filter(
            array_map('intval', (array)($new['alert_types'] ?? [])),
            static fn (int $t): bool => $t >= 1 && $t <= 3)));
        sort($types);
        $new['alert_types'] = count($types) === 3 ? [] : $types;
        // Cleared while the operator is publishing only some of the types.
        //
        // The form stops rendering the checkboxes then, and a form that does
        // not render a control is a suggestion to a browser rather than a rule
        // to a POST - the same reason the grade floor is enforced here. More
        // importantly, a stored choice left in place would go on filtering
        // from behind a control nobody can see: a member who once picked "1:1
        // only" on an unrestricted site would receive nothing at all once the
        // operator moved to 1:3, with no box on the page to explain it.
        if (!Publish::memberMayChooseType()) {
            $new['alert_types'] = [];
        }
        $new['min_confidence'] = max(0.0, min(100.0, (float)$new['min_confidence']));
        $new['directions'] = in_array($new['directions'], ['both', 'buy', 'sell'], true) ? $new['directions'] : 'both';
        $new['quiet_from'] = (int)$new['quiet_from'];
        $new['quiet_to'] = (int)$new['quiet_to'];
        if ($new['quiet_from'] < 0 || $new['quiet_from'] > 23 || $new['quiet_to'] < 0 || $new['quiet_to'] > 23) {
            $new['quiet_from'] = $new['quiet_to'] = -1;
        }
        $new['max_alerts_day'] = max(0, min(500, (int)$new['max_alerts_day']));
        $new['webhook_url'] = self::sanitiseWebhook((string)$new['webhook_url']);
        $new['trade_email'] = ((string)$new['trade_email']) === '0' ? '0' : '1';
        $new['timezone'] = in_array($new['timezone'], \DateTimeZone::listIdentifiers(), true) ? $new['timezone'] : '';

        $weights = [];
        foreach ((array)$new['rule_weights'] as $k => $v) {
            if (is_string($k) && is_numeric($v) && preg_match('/^[a-z0-9_]{2,64}$/', $k)) {
                $weights[$k] = max(0.0, min(5.0, (float)$v));
            }
        }
        $new['rule_weights'] = $weights;
        $cats = array_values(array_filter((array)$new['disabled_categories'],
            fn($c) => is_string($c) && preg_match('/^[a-z]{2,20}$/', $c)));

        $pdo = Database::pdo();
        $sql = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite'
            ? 'INSERT INTO member_prefs (member_id, profile, timezone, account_size, risk_pct, leverage,
                 min_grade, min_confidence, directions, quiet_from, quiet_to, max_alerts_day,
                 webhook_url, trade_email, rule_weights, disabled_categories, alert_types, updated_at)
               VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
               ON CONFLICT(member_id) DO UPDATE SET
                 profile=excluded.profile, timezone=excluded.timezone, account_size=excluded.account_size,
                 risk_pct=excluded.risk_pct, leverage=excluded.leverage, min_grade=excluded.min_grade,
                 min_confidence=excluded.min_confidence, directions=excluded.directions,
                 quiet_from=excluded.quiet_from, quiet_to=excluded.quiet_to,
                 max_alerts_day=excluded.max_alerts_day, webhook_url=excluded.webhook_url,
                 trade_email=excluded.trade_email,
                 rule_weights=excluded.rule_weights, disabled_categories=excluded.disabled_categories,
                 alert_types=excluded.alert_types, updated_at=excluded.updated_at'
            : 'INSERT INTO member_prefs (member_id, profile, timezone, account_size, risk_pct, leverage,
                 min_grade, min_confidence, directions, quiet_from, quiet_to, max_alerts_day,
                 webhook_url, trade_email, rule_weights, disabled_categories, alert_types, updated_at)
               VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
               ON DUPLICATE KEY UPDATE
                 profile=VALUES(profile), timezone=VALUES(timezone), account_size=VALUES(account_size),
                 risk_pct=VALUES(risk_pct), leverage=VALUES(leverage), min_grade=VALUES(min_grade),
                 min_confidence=VALUES(min_confidence), directions=VALUES(directions),
                 quiet_from=VALUES(quiet_from), quiet_to=VALUES(quiet_to),
                 max_alerts_day=VALUES(max_alerts_day), webhook_url=VALUES(webhook_url),
                 trade_email=VALUES(trade_email),
                 rule_weights=VALUES(rule_weights), disabled_categories=VALUES(disabled_categories),
                 alert_types=VALUES(alert_types), updated_at=VALUES(updated_at)';
        $pdo->prepare($sql)->execute([
            $memberId, $new['profile'], $new['timezone'], $new['account_size'], $new['risk_pct'],
            $new['leverage'], $new['min_grade'], $new['min_confidence'], $new['directions'],
            $new['quiet_from'], $new['quiet_to'], $new['max_alerts_day'], $new['webhook_url'],
            $new['trade_email'],
            json_encode($weights), implode(',', $cats), implode(',', $new['alert_types']), time(),
        ]);
        unset(self::$memo[$memberId]);
    }

    /**
     * Outbound webhooks post to a URL the member controls, so the usual
     * server-side request forgery precautions apply: HTTPS only, no
     * credentials, and no loopback or private-range destinations.
     */
    public static function sanitiseWebhook(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (!filter_var($url, FILTER_VALIDATE_URL) || strlen($url) > 400) {
            return '';
        }
        $parts = parse_url($url);
        if (($parts['scheme'] ?? '') !== 'https' || isset($parts['user'], $parts['pass'])) {
            return '';
        }
        // parse_url keeps the brackets on a literal IPv6 host, and "[::1]" is
        // not an IP address as far as filter_var is concerned - so the check
        // below fell straight through and "https://[::1]/x" SAVED. Delivery
        // refuses it (Webhooks::destinationAllowed strips them for exactly
        // this reason), so nothing was ever sent anywhere it should not be.
        // What the member got instead was a field that accepted their address
        // and then never fired, with nothing on the page to say why. Stripped
        // here too, so the two checks see the same host.
        $host = trim((string)($parts['host'] ?? ''), '[]');
        if ($host === '' || preg_match('/^(localhost|.*\.local|.*\.internal)$/i', $host)) {
            return '';
        }
        // Literal IPs must be public; hostnames are re-checked at delivery time.
        if (filter_var($host, FILTER_VALIDATE_IP)
            && !filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return '';
        }
        // AN ADDRESS WRITTEN SO THAT IT DOES NOT LOOK LIKE ONE.
        //
        // 127.0.0.1 can be spelled 2130706433, 0x7f000001, 017700000001 or
        // 127.1, and filter_var() calls none of those an IP - so the private
        // range check above skipped every one of them and the URL saved.
        //
        // Delivery was never fooled: destinationAllowed() resolves the host,
        // gethostbyname() normalises all four to 127.0.0.1, and the same
        // private-range test refuses it there. So nothing was ever sent
        // anywhere it should not be. What the member got was the failure this
        // function was already fixed for once, in the [::1] case above: a
        // field that accepts an address and then silently never fires.
        //
        // A hostname's last label cannot be all digits - that is what
        // separates a name from a dotted or packed address - so this catches
        // every numeric spelling without a DNS lookup on a settings save,
        // while example.com, hooks.slack.com and punycode all pass.
        $labels = explode('.', $host);
        if (ctype_digit((string)end($labels)) || preg_match('/^0[xX]/', $host)) {
            return '';
        }
        return $url;
    }

    /**
     * The engine profile derived from a member's preferences, ready for
     * SignalEngine::withProfile(). Returns [] for members on the default
     * profile so the shared, cacheable analysis is reused.
     */
    public static function engineProfile(int $memberId): array
    {
        // Whether members may run the engine on their own thresholds at all.
        //
        // This one genuinely changes what a member SEES, and in the loosening
        // direction: on Aggressive the buy threshold drops to 2.0 and one
        // agreeing category is enough, so a chart can read SELL where the
        // site's own engine reads NEUTRAL. Observed on this install -
        // WLDUSDT 15m, score -3.17, NEUTRAL for the site and SELL on
        // Aggressive.
        //
        // It is narrower than it sounds and the limits are worth stating,
        // because they are what makes it safe rather than what makes it fine:
        // the personalised run is suppressStore()'d, so it never enters the
        // history, the track record or any published figure - and alerts are
        // dispatched from the shared signal_state, so a personalised verdict
        // never becomes an email, a push or a Telegram message. It is the
        // chart, for the member looking at it.
        //
        // Still, an operator who has tuned their engine usually does not want
        // a member quietly running a looser one and judging the site on the
        // result. Off by default; everybody sees the site's engine.
        if (Database::setting('member_engine_profile', '0') !== '1') {
            return [];
        }
        $p = self::get($memberId);
        $preset = self::PROFILES[$p['profile']] ?? self::PROFILES['balanced'];
        $profile = [];
        if ($preset['buy_threshold'] !== null) {
            $profile['buy_threshold'] = $preset['buy_threshold'];
            $profile['sell_threshold'] = $preset['sell_threshold'];
        }
        if (($preset['min_categories'] ?? 2) !== 2) {
            $profile['min_categories'] = $preset['min_categories'];
        }
        if ($p['rule_weights']) {
            $profile['rule_weights'] = $p['rule_weights'];
        }
        if ($p['disabled_categories']) {
            $profile['disabled_categories'] = $p['disabled_categories'];
        }
        return $profile;
    }


    /**
     * Should this alert reach this member? Applies grade, confidence,
     * direction, quiet-hours and daily-cap filters.
     *
     * The admin previously had one site-wide minimum grade and members had
     * nothing at all, so the only way to receive fewer alerts was to stop
     * watching pairs.
     */
    public static function alertAllowed(int $memberId, array $signal): array
    {
        $p = self::get($memberId);
        $order = ['C' => 0, 'B' => 1, 'A' => 2, 'A+' => 3];

        if ($p['directions'] === 'buy' && ($signal['signal'] ?? '') !== 'BUY') {
            return [false, 'direction filter (BUY only)'];
        }
        if ($p['directions'] === 'sell' && ($signal['signal'] ?? '') !== 'SELL') {
            return [false, 'direction filter (SELL only)'];
        }
        // The member's grade filter, when the operator allows one.
        //
        // It has only ever been able to NARROW: the dispatchers apply the
        // site's alert_min_grade first and this second, so a member set to
        // "any" against a site floor of A still receives only A and A+. That
        // is the safe direction and it stays true whatever this setting says -
        // switching the member control off cannot let anybody past the floor,
        // it only stops them tightening it further.
        //
        // Off by default, because an operator who has decided what a good
        // signal is does not usually want that re-litigated per account, and
        // the control was mostly offering choices that changed nothing.
        if ($p['min_grade'] !== 'any'
            && Database::setting('member_grade_filter', '0') === '1') {
            $need = $order[$p['min_grade']] ?? 0;
            $have = $order[(string)($signal['grade'] ?? 'C')] ?? 0;
            if ($have < $need) {
                return [false, 'below minimum grade ' . $p['min_grade']];
            }
        }
        // The member's own choice of signal types.
        //
        // Empty is every type, so an account that has never touched this hears
        // what it always heard. A call with no type - anything stored before
        // the types existed - is NOT withheld: the member asked to be told
        // about 1:3 setups, not to be silenced about calls the site cannot
        // classify, and treating unknown as "not it" would have gone quiet on
        // a whole install's back catalogue the day this shipped.
        if ($p['alert_types'] && Publish::memberMayChooseType()) {
            $tier = (int)($signal['rr_tier'] ?? 0);
            if ($tier >= 1 && !in_array($tier, $p['alert_types'], true)) {
                return [false, 'not a signal type you asked for (1:' . $tier . ')'];
            }
        }
        if ($p['min_confidence'] > 0 && (float)($signal['confidence'] ?? 0) < $p['min_confidence']) {
            return [false, 'below minimum confidence'];
        }
        if ($p['quiet_from'] >= 0 && $p['quiet_to'] >= 0 && $p['quiet_from'] !== $p['quiet_to']) {
            $hour = (int)self::memberDate($p, 'G');
            $inQuiet = $p['quiet_from'] < $p['quiet_to']
                ? ($hour >= $p['quiet_from'] && $hour < $p['quiet_to'])
                : ($hour >= $p['quiet_from'] || $hour < $p['quiet_to']);   // window wraps midnight
            if ($inQuiet) {
                return [false, 'quiet hours'];
            }
        }
        if ($p['max_alerts_day'] > 0) {
            $key = 'alertcap:' . $memberId . ':' . self::memberDate($p, 'Y-m-d');
            $sent = (int)(Cache::get($key, 0) ?: 0);
            if ($sent >= $p['max_alerts_day']) {
                return [false, 'daily alert cap reached'];
            }
        }
        return [true, ''];
    }

    /** Record one delivered alert against the member's daily cap. */
    public static function countAlert(int $memberId): void
    {
        $p = self::get($memberId);
        if ($p['max_alerts_day'] <= 0) {
            return;
        }
        Cache::increment('alertcap:' . $memberId . ':' . self::memberDate($p, 'Y-m-d'), 172800);
    }

    /** Format "now" in the member's own timezone (falls back to UTC). */
    public static function memberDate(array $prefs, string $format, ?int $ts = null): string
    {
        $ts ??= time();
        $tz = (string)($prefs['timezone'] ?? '');
        try {
            $d = new \DateTime('@' . $ts);
            $d->setTimezone(new \DateTimeZone($tz !== '' ? $tz : 'UTC'));
            return $d->format($format);
        } catch (\Throwable $e) {
            return gmdate($format, $ts);
        }
    }
}
