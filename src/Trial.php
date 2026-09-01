<?php
declare(strict_types=1);

namespace SignalMasterAi;

/**
 * The free trial a new account gets, and the three things that stop one
 * person taking it repeatedly.
 *
 * WHAT A TRIAL IS HERE.
 *
 * Premium days, granted once, on the same paid_until field a payment writes
 * to - so everything downstream already works. The tier check, the expiry
 * sweep that drops an account back to free, the account page, the coin gate:
 * none of them need to know a trial exists. A trial that invented its own
 * parallel notion of "paid until" would need every one of those taught about
 * it, and the first one nobody remembered would be a hole.
 *
 * A trial never SHORTENS anything. Somebody who already has paid time, or a
 * longer trial from a previous setting, keeps the later date.
 *
 * THE LEDGER OUTLIVES THE ACCOUNT.
 *
 * This is the whole design. If "have you had a trial" is a column on members,
 * then deleting the account answers "no" again - and members can delete their
 * own account on this site. So claims are written to their own table, keyed on
 * things that survive: the email, the address it came from, the browser it was
 * taken in. Erasing an account erases the account; the claim stays.
 *
 * NOTHING IDENTIFYING IS STORED IN IT.
 *
 * The three keys are HMACs, salted per install. The table can answer "has this
 * address had a trial" without holding a single email or IP, which matters
 * because it is a table whose whole job is to be kept for a long time.
 *
 * THE THREE SIGNALS, AND WHAT EACH IS ACTUALLY WORTH.
 *
 *   Email   The strongest, and still weak: addresses are free. Normalised
 *           first - lower-cased, +tags removed, and dots collapsed on the
 *           providers that ignore them - because a+1@gmail.com and a@gmail.com
 *           are one mailbox and treating them as two people is the cheapest
 *           bypass there is.
 *   Device  A signed cookie planted on first visit. Beaten by clearing
 *           cookies or opening a private window, which is why it is one of
 *           three rather than the answer.
 *   IP      The one that catches the lazy repeat, and the one that punishes
 *           the innocent: a family, an office, a university and most mobile
 *           carriers share an address. So it is a COUNT over a WINDOW, both
 *           set by the operator, rather than a ban - the default of one per
 *           thirty days can be raised the moment real users start hitting it.
 *
 * None of the three is a wall, and the honest thing is to say so rather than
 * imply the trial cannot be farmed. Together they make casual repetition
 * inconvenient, which is what a trial gate is for. What actually stops
 * industrial abuse is that a trial grants time, not money.
 */
class Trial
{
    /** Cookie holding the per-browser token. */
    public const COOKIE = 'sma_dev';

    public static function enabled(): bool
    {
        return Database::setting('trial_enabled', '0') === '1' && self::days() > 0;
    }

    /** How many days a new account gets. Capped so a typo cannot grant a decade. */
    public static function days(): int
    {
        return max(0, min(365, (int)Database::setting('trial_days', '3')));
    }

    /** How many claims one address may make inside the window below. */
    public static function maxPerIp(): int
    {
        return max(1, min(100, (int)Database::setting('trial_max_per_ip', '1')));
    }

    /** The window that IP count is measured over, in days. 0 = for ever. */
    public static function ipWindowDays(): int
    {
        return max(0, min(3650, (int)Database::setting('trial_ip_days', '30')));
    }

    public static function deviceCheck(): bool
    {
        return Database::setting('trial_device_check', '1') === '1';
    }

    // ---------------------------------------------------------------- keys

    /**
     * The salt every key is HMAC'd with.
     *
     * Reuses the install's encryption key when there is one, so no second
     * secret has to be managed or backed up. Without one - no OpenSSL, or an
     * unwritable data directory - it falls back to a value derived from
     * settings that are stable for the life of the install. That fallback is
     * weaker against somebody who already has the database AND knows those
     * values, and it is still enough for the job: this is a lookup key, not a
     * password.
     */
    private static function salt(): string
    {
        $k = Secrets::key();
        if (is_string($k) && $k !== '') {
            return $k;
        }
        return 'sma-trial-' . Database::setting('site_url', '') . '|'
             . Database::setting('installed_at', '');
    }

    private static function key(string $kind, string $value): string
    {
        return hash_hmac('sha256', $kind . '|' . $value, self::salt());
    }

    /**
     * One mailbox, one string.
     *
     * Gmail and the big free providers ignore dots and everything after a +,
     * so three spellings of one inbox must not read as three people. Applied
     * only to the providers that actually behave that way: collapsing dots on
     * a corporate domain would merge two different colleagues.
     */
    public static function normaliseEmail(string $email): string
    {
        $email = mb_strtolower(trim($email));
        $at = strrpos($email, '@');
        if ($at === false) {
            return $email;
        }
        $local = substr($email, 0, $at);
        $domain = substr($email, $at + 1);
        $plus = strpos($local, '+');
        if ($plus !== false) {
            $local = substr($local, 0, $plus);
        }
        $dotless = ['gmail.com', 'googlemail.com'];
        if (in_array($domain, $dotless, true)) {
            $local = str_replace('.', '', $local);
        }
        if ($domain === 'googlemail.com') {
            $domain = 'gmail.com';
        }
        return $local . '@' . $domain;
    }

    /** The address this request came from, using the site's own proxy rule. */
    public static function clientIp(): string
    {
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
        if (Database::setting('trust_proxy', '0') === '1') {
            $fwd = trim(explode(',', (string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''))[0]);
            if ($fwd !== '' && filter_var($fwd, FILTER_VALIDATE_IP)) {
                $ip = $fwd;
            }
        }
        return $ip;
    }

    /**
     * This browser's token, planted if it is not there yet.
     *
     * Set on the way past rather than at claim time, so the value recorded
     * against a trial is one the browser has been carrying - a token minted in
     * the same request would be unique every time and would never match
     * anything, which is a check that looks like it works and never fires.
     */
    public static function deviceToken(): string
    {
        $cur = (string)($_COOKIE[self::COOKIE] ?? '');
        if (preg_match('/^[a-f0-9]{32}$/', $cur)) {
            return $cur;
        }
        $new = bin2hex(random_bytes(16));
        if (!headers_sent()) {
            setcookie(self::COOKIE, $new, Request::cookieParams('/', time() + 400 * 86400));
        }
        $_COOKIE[self::COOKIE] = $new;
        return $new;
    }

    // ------------------------------------------------------------ decisions

    /**
     * May this person start a trial? Returns [allowed, reason-if-not].
     *
     * The reason is for the log and the admin panel, not for the visitor: a
     * page that says "your IP has already had a trial" is a page that teaches
     * somebody to change their IP.
     *
     * @return array{0:bool,1:string}
     */
    public static function eligible(string $email, ?int $memberId = null): array
    {
        if (!self::enabled()) {
            return [false, 'trials are switched off'];
        }
        $pdo = Database::pdo();
        $emailKey = self::key('email', self::normaliseEmail($email));
        try {
            $st = $pdo->prepare('SELECT COUNT(*) FROM trials WHERE email_key = ?');
            $st->execute([$emailKey]);
            $n = (int)$st->fetchColumn();
            $st->closeCursor();
            if ($n > 0) {
                return [false, 'this address has already had a trial'];
            }
            if ($memberId !== null && $memberId > 0) {
                $st = $pdo->prepare('SELECT COUNT(*) FROM trials WHERE member_id = ?');
                $st->execute([$memberId]);
                $n = (int)$st->fetchColumn();
                $st->closeCursor();
                if ($n > 0) {
                    return [false, 'this account has already had a trial'];
                }
            }
            if (self::deviceCheck()) {
                $dev = self::deviceToken();
                $st = $pdo->prepare('SELECT COUNT(*) FROM trials WHERE device_key = ?');
                $st->execute([self::key('device', $dev)]);
                $n = (int)$st->fetchColumn();
                $st->closeCursor();
                if ($n > 0) {
                    return [false, 'this browser has already had a trial'];
                }
            }
            $ip = self::clientIp();
            if ($ip !== '') {
                $win = self::ipWindowDays();
                $sql = 'SELECT COUNT(*) FROM trials WHERE ip_key = ?'
                     . ($win > 0 ? ' AND started_at >= ?' : '');
                $args = [self::key('ip', $ip)];
                if ($win > 0) {
                    $args[] = time() - $win * 86400;
                }
                $st = $pdo->prepare($sql);
                $st->execute($args);
                $n = (int)$st->fetchColumn();
                $st->closeCursor();
                if ($n >= self::maxPerIp()) {
                    return [false, 'this address has had ' . $n . ' trial(s) already'];
                }
            }
        } catch (\Throwable $e) {
            // A broken ledger must not hand out unlimited trials.
            return [false, 'trial ledger unavailable'];
        }
        return [true, ''];
    }

    /**
     * Start the trial. Returns [granted, days, reason-if-not].
     *
     * The ledger row is written FIRST and its uniqueness is what decides the
     * race: two simultaneous registrations from one browser both pass
     * eligible(), and only one of them can insert. Granting first and
     * recording after is how a trial gets handed out twice.
     *
     * @return array{0:bool,1:int,2:string}
     */
    public static function claim(int $memberId, string $email): array
    {
        if ($memberId <= 0) {
            return [false, 0, 'no member'];
        }
        [$ok, $why] = self::eligible($email, $memberId);
        if (!$ok) {
            return [false, 0, $why];
        }
        $days = self::days();
        $pdo = Database::pdo();
        $now = time();
        $ends = $now + $days * 86400;
        try {
            $pdo->prepare(
                'INSERT INTO trials (member_id, email_key, ip_key, device_key, days, started_at, ends_at)
                 VALUES (?,?,?,?,?,?,?)'
            )->execute([
                $memberId,
                self::key('email', self::normaliseEmail($email)),
                self::clientIp() !== '' ? self::key('ip', self::clientIp()) : '',
                self::deviceCheck() ? self::key('device', self::deviceToken()) : '',
                $days, $now, $ends,
            ]);
        } catch (\Throwable $e) {
            // The unique index on email_key is doing its job.
            return [false, 0, 'already claimed'];
        }

        // Never shorten. Paid time, or a longer trial from an earlier setting,
        // outranks this one.
        try {
            $st = $pdo->prepare('SELECT tier, paid_until FROM members WHERE id = ?');
            $st->execute([$memberId]);
            $m = $st->fetch();
            $st->closeCursor();
            $until = max($ends, (int)($m['paid_until'] ?? 0));
            // paid_until = 0 on a paid account means "no expiry" - a lifetime
            // grant - and must not be overwritten with a date three days out.
            if ($m && (string)$m['tier'] === 'paid' && (int)$m['paid_until'] === 0) {
                return [true, $days, ''];
            }
            $pdo->prepare("UPDATE members SET tier = 'paid', paid_until = ? WHERE id = ?")
                ->execute([$until, $memberId]);
        } catch (\Throwable $e) {
            return [false, 0, 'could not apply the trial'];
        }
        Audit::log('trial.start', 'member #' . $memberId, '', $days . ' day(s)', 'system');
        return [true, $days, ''];
    }

    /**
     * Start a trial if this install runs them and this member is owed one.
     *
     * The one hook both routes call, so "when does a trial begin" has a single
     * answer: the moment an account becomes usable. With email codes on that
     * is the code being accepted; with them off it is registration itself.
     * Wiring the two separately is how one of them ends up forgotten.
     *
     * Silent by design. A refusal is not the visitor's business - a page that
     * says "your address has already had a trial" teaches somebody to change
     * their address - and it is never worth failing a signup over.
     */
    public static function maybeStart(int $memberId): void
    {
        if ($memberId <= 0 || !self::enabled()) {
            return;
        }
        try {
            $st = Database::pdo()->prepare('SELECT email FROM members WHERE id = ?');
            $st->execute([$memberId]);
            $email = (string)($st->fetchColumn() ?: '');
            $st->closeCursor();
            if ($email !== '') {
                self::claim($memberId, $email);
            }
        } catch (\Throwable $e) {
            // A trial is a bonus. It never breaks the thing it is attached to.
        }
    }

    /**
     * What to tell this member about their trial, or null when there is
     * nothing to say.
     *
     * @return array{days:int,ends_at:int,left:int,expired:bool}|null
     */
    public static function status(int $memberId): ?array
    {
        if ($memberId <= 0) {
            return null;
        }
        try {
            $st = Database::pdo()->prepare(
                'SELECT days, ends_at FROM trials WHERE member_id = ? ORDER BY id DESC LIMIT 1');
            $st->execute([$memberId]);
            $r = $st->fetch();
            $st->closeCursor();
        } catch (\Throwable $e) {
            return null;
        }
        if (!$r) {
            return null;
        }
        $ends = (int)$r['ends_at'];
        return [
            'days'    => (int)$r['days'],
            'ends_at' => $ends,
            'left'    => max(0, (int)ceil(($ends - time()) / 86400)),
            'expired' => $ends <= time(),
        ];
    }

    /** Counts for the admin panel. */
    public static function stats(): array
    {
        try {
            $pdo = Database::pdo();
            $now = time();
            return [
                'total'   => (int)$pdo->query('SELECT COUNT(*) FROM trials')->fetchColumn(),
                'active'  => (int)$pdo->query('SELECT COUNT(*) FROM trials WHERE ends_at > ' . $now)->fetchColumn(),
                'last_7'  => (int)$pdo->query('SELECT COUNT(*) FROM trials WHERE started_at >= ' . ($now - 7 * 86400))->fetchColumn(),
                'last_30' => (int)$pdo->query('SELECT COUNT(*) FROM trials WHERE started_at >= ' . ($now - 30 * 86400))->fetchColumn(),
                // A trial that ended and was followed by a real payment is the
                // only number that says whether the trial is worth running.
                'converted' => (int)$pdo->query(
                    "SELECT COUNT(DISTINCT t.member_id) FROM trials t
                      INNER JOIN payments p ON p.member_id = t.member_id
                       AND p.status = 'paid' AND p.amount_usd > 0 AND p.created_at > t.started_at")->fetchColumn(),
            ];
        } catch (\Throwable $e) {
            return ['total' => 0, 'active' => 0, 'last_7' => 0, 'last_30' => 0, 'converted' => 0];
        }
    }
}
