<?php
declare(strict_types=1);

namespace SignalMasterAi;

/**
 * Public member accounts (visitors who register on the site).
 * Tiers: guest (not logged in) < free (registered) < paid (premium).
 * Coin access: a symbol's tier declares the minimum member tier required.
 */
class MemberAuth
{
    /** Per-request memo for current(); cleared by login/logout. */
    private static ?array $currentMemo = null;

    /**
     * A fixed, valid bcrypt hash of a password nobody has typed - checked
     * when login() finds no matching row, so "no such account" costs exactly
     * what "wrong password" costs. Without this, password_verify() only ran
     * when a row existed, so a login attempt against a real email paid a
     * full bcrypt round-trip (tens of milliseconds) while one against a
     * nonexistent email returned in a fraction of that - a timing side
     * channel an attacker can use to enumerate registered emails one probe
     * at a time, quietly undoing the neutral-wording enumeration defenses
     * this same class builds elsewhere (see NEUTRAL_SIGNUP).
     */
    private const DUMMY_HASH = '$2y$12$ydhJOyyxC63udEOptGMSW.1/HRkdB1FnB4ttt8.7I25SQI5h30.Ru';

    /** Set by login() when the only thing wrong was an unverified address. */
    private static int $pendingVerifyId = 0;

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_name('sma_member');
            session_set_cookie_params(Request::sessionCookieParams());
            session_start();
        }
        self::gateUnverified();
    }

    /**
     * Stop holding the session file, keeping what has already been read.
     *
     * PHP's session handler locks the session for the WHOLE request, and every
     * request on this site opens one. The dashboard polls api.php every few
     * seconds; each poll can run the engine and fetch candles, and on a host
     * where an exchange is slow or blocked that is a request holding the lock
     * for seconds at a time. Anything else the same member does then waits for
     * it - not slowly, but stopped dead at session_start() before a single
     * line of the page runs.
     *
     * That is the "stuck on Preparing checkout" nobody could find: checkout
     * never reached the gateway, so the gateway was never at fault and nothing
     * was ever written to the error log. Measured before this fix: a page
     * requested one second into an eight-second poll took seven seconds to
     * start. A poll every eight seconds that takes longer than eight seconds
     * never lets go at all.
     *
     * Call this once a request has finished WRITING to $_SESSION. Reads carry
     * on working - the array stays in memory; only later writes stop being
     * saved, which is why login, logout and CSRF minting must come first.
     */
    public static function releaseSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }

    /**
     * Take the session back, for the rare case that has to write after all.
     *
     * Releasing early is only safe while nothing writes afterwards, and the
     * one path that does is a failed checkout: it falls through to rendering
     * the page again, and rendering mints a CSRF token. A token that is handed
     * to the form but never saved makes the NEXT attempt fail on "Session
     * expired" - turning one gateway error into two, the second of them
     * inexplicable. Cheap: the lock is held only for the write.
     */
    public static function resumeSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_name('sma_member');
            session_set_cookie_params(Request::sessionCookieParams());
            session_start();
        }
    }

    /**
     * An unverified account holds a session but goes nowhere.
     *
     * The login door was locked and the window was not. Refusing to log in an
     * unverified member is only half the rule, because a session can exist
     * without a login having just happened: the account was verified and an
     * operator sent it back for re-verification, the member was already logged
     * in when the setting was switched on, or they simply never closed the tab
     * after registering. In each case they carried on using the site.
     *
     * The gate lives in start() because that is the one call every page with a
     * member session already makes - a rule enforced in eleven places is a
     * rule that will be missing from the twelfth.
     *
     * Two things must stay reachable or the lock has no key: the page that
     * does the verifying, and the one that stops the emails.
     */
    private static function gateUnverified(): void
    {
        if (!isset($_SESSION['member_id'])) {
            return;
        }
        $m = self::current();
        if (!$m || !MemberVerify::pending($m)) {
            return;
        }
        // The verify form reads this, so it is set before anything redirects.
        $_SESSION['verify_member'] = (int)$m['id'];

        $script = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
        if ($script === 'account.php' || $script === 'unsubscribe.php') {
            return;
        }
        if ($script === 'api.php') {
            // An endpoint answers; it does not redirect a fetch() into an
            // HTML page the caller cannot use.
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => false,
                'error' => 'Verify your email address to continue.',
                'verify_required' => true,
                'verify_url' => 'account.php?tab=verify',
            ]);
            exit;
        }
        if (headers_sent()) {
            return;             // nothing safe left to do; the page renders
        }
        header('Location: account.php?tab=verify');
        exit;
    }

    /**
     * Create an account.
     *
     * Returns [ok, message, memberId, needsCode]. The last two are what the
     * page needs to decide where to send someone: straight in, or to the
     * "type the code we mailed you" step. An account that needs a code is
     * created but NOT logged in - see MemberVerify for why the row exists
     * before the code is proved.
     */
    public static function register(string $email, string $password): array
    {
        self::$registerDecoy = false;
        $email = mb_strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [false, 'Please enter a valid email address.', 0, false];
        }
        if (strlen($password) < 8) {
            return [false, 'Password must be at least 8 characters.', 0, false];
        }
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT id, verified FROM members WHERE email = ?');
        $stmt->execute([$email]);
        $needsCode = MemberVerify::enabled();
        $existing = $stmt->fetch();
        $stmt->closeCursor();
        if ($existing) {
            // An address left sitting unverified is not "taken" in any way the
            // person in front of us cares about - most often it is the same
            // person, back after losing the tab or never getting the mail.
            // Telling them to log in with a password they may have mistyped
            // is a dead end, so re-issue the code instead. The password is not
            // touched: if it really is someone else, they get an email they
            // can ignore and no access to anything.
            if ((int)($existing['verified'] ?? 1) === 0) {
                // Same cost as the other two branches here, for the same
                // reason - see the comment below on the verified-decoy path.
                password_hash($password, PASSWORD_DEFAULT);
                return [false, self::NEUTRAL_SIGNUP, (int)$existing['id'], true];
            }
            // The address belongs to somebody, and saying so out loud is an
            // oracle: five tries per ten minutes per address is enough to walk
            // a list and learn who holds an account on a crypto trading site.
            // The reset form was built carefully to avoid exactly this - "if
            // that email has an account" - and then the registration form
            // answered the same question through the front door.
            //
            // So the reply is the one a new address gets, and the page shows
            // the same code screen. Nothing is created and no id is handed
            // back; the caller marks the session as a decoy, and the person
            // who really owns the address is told separately that someone
            // tried to sign up with it.
            //
            // Only possible while codes are on. With verification disabled a
            // new registration logs straight in, so there is no neutral answer
            // left to give and the honest message is the better one.
            if ($needsCode) {
                // Same wording as a real signup, and now the same COST too:
                // the real path below always pays a bcrypt round-trip
                // (password_hash()), so skipping it here made the decoy
                // reply come back measurably faster than a genuine one -
                // a timing signal answering the exact question the identical
                // wording was written to hide.
                password_hash($password, PASSWORD_DEFAULT);
                self::$registerDecoy = true;
                return [false, self::NEUTRAL_SIGNUP, 0, true];
            }
            return [false, 'An account with this email already exists - log in instead.', 0, false];
        }
        $pdo->prepare('INSERT INTO members (email, password_hash, tier, created_at, verified)
                       VALUES (?, ?, ?, ?, ?)')
            ->execute([$email, password_hash($password, PASSWORD_DEFAULT), 'free', time(),
                       $needsCode ? 0 : 1]);
        $id = (int)$pdo->lastInsertId();
        if ($needsCode) {
            return [true, self::NEUTRAL_SIGNUP, $id, true];
        }
        // With email codes off the account is created verified and usable, so
        // this IS the moment a trial starts. With them on, markVerified() is -
        // one hook each, both calling the same place.
        Trial::maybeStart($id);
        self::login($email, $password);
        return [true, 'Account created - welcome!', $id, false];
    }

    /**
     * Log in, unless the address still owes a verification code.
     *
     * The gate lives here rather than in the page so that every caller gets
     * it - a session must not exist for an unverified account, whichever door
     * it came through. A blocked attempt returns false like a wrong password
     * does, but leaves the member id in pendingVerifyId() so the page can
     * offer the code form instead of "invalid email or password", which would
     * be both unhelpful and untrue.
     */
    public static function login(string $email, string $password): bool
    {
        self::$pendingVerifyId = 0;
        $stmt = Database::pdo()->prepare('SELECT * FROM members WHERE email = ?');
        $stmt->execute([mb_strtolower(trim($email))]);
        $m = $stmt->fetch();
        $stmt->closeCursor();
        // Always verify against SOMETHING - a real hash when the row exists,
        // the fixed dummy one when it doesn't - so both paths cost the same.
        // See DUMMY_HASH for why.
        $verified = password_verify($password, $m['password_hash'] ?? self::DUMMY_HASH);
        if ($m && $verified) {
            if (MemberVerify::pending($m)) {
                self::$pendingVerifyId = (int)$m['id'];
                return false;
            }
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_regenerate_id(true);
            }
            $_SESSION['member_id'] = (int)$m['id'];
            $_SESSION['member_email'] = $m['email'];
            $_SESSION['member_pw'] = (int)($m['pw_changed'] ?? 0);
            self::$currentMemo = null;
            return true;
        }
        return false;
    }

    /**
     * Member id of the last login attempt that failed only because the
     * address is unverified (0 = the last failure was a real one).
     */
    /**
     * The one thing registration says, whether or not the address is already
     * ours. Any wording difference here is the enumeration hole reopening.
     */
    public const NEUTRAL_SIGNUP = 'Almost there - check your email for the code.';

    /** Was the last register() call answered with a decoy? */
    private static bool $registerDecoy = false;

    /**
     * True when the address given to register() already belongs to a verified
     * account and the caller was handed the neutral answer instead. The page
     * uses it to show the code screen without arming it.
     */
    public static function registerWasDecoy(): bool
    {
        return self::$registerDecoy;
    }

    public static function pendingVerifyId(): int
    {
        return self::$pendingVerifyId;
    }

    /** Start a session for an id whose password was already proved. */
    public static function loginById(int $memberId): bool
    {
        $stmt = Database::pdo()->prepare('SELECT id, email, pw_changed FROM members WHERE id = ?');
        $stmt->execute([$memberId]);
        $m = $stmt->fetch();
        $stmt->closeCursor();
        if (!$m) {
            return false;
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $_SESSION['member_id'] = (int)$m['id'];
        $_SESSION['member_email'] = $m['email'];
        $_SESSION['member_pw'] = (int)$m['pw_changed'];
        self::$currentMemo = null;
        return true;
    }

    public static function logout(): void
    {
        unset($_SESSION['member_id'], $_SESSION['member_email'], $_SESSION['member_pw']);
        self::$currentMemo = null;
        // Rotate the session id so the old cookie value is dead after logout.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    /**
     * Session-only member id (0 = guest). Cheap: no database round trip, so
     * hot paths like the rate limiter can identify a caller for free.
     */
    public static function currentId(): int
    {
        return (int)($_SESSION['member_id'] ?? 0);
    }

    public static function current(): ?array
    {
        if (!isset($_SESSION['member_id'])) {
            return null;
        }
        // One lookup per request: current() is called from the header, the
        // tier gate and every API action on the same page load.
        if (self::$currentMemo !== null && (int)self::$currentMemo['id'] === (int)$_SESSION['member_id']) {
            return self::$currentMemo;
        }
        // `verified` belongs in this list: the session gate asks the memoised
        // row whether this member still owes a code, and a column that is not
        // selected reads as absent, which MemberVerify::pending() treats as
        // "fine" - so the gate silently passed everyone.
        //
        // `upsell_dismissed` is here for the same reason and was caught the
        // same way: Premium::promptFor() reads it with `?? 0`, so a column
        // missing from this list means "never dismissed" and the prompt came
        // back on the next page load however many times it was closed. A
        // fixed column list is a list somebody has to remember to update -
        // twice now - so anything read off this row belongs in it.
        // Every column, not a list somebody has to remember to extend.
        //
        // The list was already the cause of two bugs - `verified` missing let
        // the sign-up gate pass everyone, and `upsell_dismissed` missing made
        // a dismissed prompt come straight back - and naming the new columns
        // introduced a third and worse one: an install that receives this file
        // without the migration that adds them has no such column, PDO throws,
        // and EVERY PAGE FATALS FOR EVERY LOGGED-IN MEMBER. A partial upload
        // is an ordinary deployment accident, and the whole member side of the
        // site going dark is not an acceptable consequence of one.
        //
        // `SELECT *` cannot be wrong about a column, costs nothing on a single
        // row keyed by primary key, and degrades correctly on an install
        // mid-upgrade: a column that is not there yet reads as absent, which
        // is what every consumer's `?? default` already expects.
        $stmt = Database::pdo()->prepare('SELECT * FROM members WHERE id = ?');
        $stmt->execute([(int)$_SESSION['member_id']]);
        $m = $stmt->fetch();
        $stmt->closeCursor();
        if (!$m) {
            return null;
        }
        // A password change ends every session except the one that made it.
        // Without this, "someone is in my account, I changed my password" is
        // not an answer: the intruder's cookie still opens a session that was
        // never told the password moved.
        //
        // A session with no stamp is one that existed before this shipped. It
        // adopts the current value rather than being thrown out, so an upgrade
        // logs nobody out and the next password change covers everyone.
        if (!isset($_SESSION['member_pw'])) {
            $_SESSION['member_pw'] = (int)$m['pw_changed'];
        } elseif ((int)$_SESSION['member_pw'] !== (int)$m['pw_changed']) {
            self::logout();
            return null;
        }
        // Plan expiry: paid_until = 0 means unlimited (manually granted).
        if ($m['tier'] === 'paid' && (int)$m['paid_until'] > 0 && (int)$m['paid_until'] < time()) {
            Database::pdo()->prepare("UPDATE members SET tier = 'free' WHERE id = ?")->execute([$m['id']]);
            $m['tier'] = 'free';
        }
        self::$currentMemo = $m;
        return $m;
    }

    /** Forget the memoised member row (after a login, logout or tier change). */
    public static function forget(): void
    {
        self::$currentMemo = null;
    }

    /** Viewer tier: 'guest', 'free' or 'paid'. */
    public static function tier(): string
    {
        $m = self::current();
        return $m ? $m['tier'] : 'guest';
    }

    /** Can the current viewer access a symbol of the given tier? */
    public static function canAccess(string $symbolTier, ?string $viewerTier = null): bool
    {
        $rank = ['public' => 0, 'free' => 1, 'paid' => 2];
        $viewerRank = ['guest' => 0, 'free' => 1, 'paid' => 2];
        $viewerTier ??= self::tier();
        return ($viewerRank[$viewerTier] ?? 0) >= ($rank[$symbolTier] ?? 0);
    }

    /**
     * Does the viewer clear a "who may use this feature" gate?
     *
     * Distinct from canAccess(), which gates a COIN by its own tier. This
     * gates a FEATURE by the tier an operator set for it: 'any' lets guests
     * in, 'free' needs an account, 'paid' needs a subscription.
     *
     * The same three-line expression was written out five times - in
     * backtest.php, twice in api.php and twice in charts.php - which is four
     * chances for one of them to say something slightly different about who
     * gets in. A permission check is the last thing that should be copied.
     */
    public static function meetsTier(string $need, ?string $viewerTier = null): bool
    {
        $viewerTier ??= self::tier();
        return $need === 'any'
            || ($need === 'free' && in_array($viewerTier, ['free', 'paid'], true))
            || ($need === 'paid' && $viewerTier === 'paid');
    }

    /**
     * A signed opt-out token for one member.
     *
     * The link in an alert email has to work for someone who is not logged in
     * and has forgotten they ever had an account - that is the whole point of
     * an unsubscribe. So it carries proof instead of a session: an HMAC over
     * the member id, keyed by a secret only this install holds. Nothing to
     * store, nothing to expire, and not guessable from another member's link.
     *
     * Deliberately no expiry. An unsubscribe link in a year-old email should
     * still work; the alternative is a member who cannot get out and marks the
     * next one as spam instead.
     */
    public static function optOutToken(int $memberId): string
    {
        return $memberId . '.' . substr(
            hash_hmac('sha256', 'optout:' . $memberId, self::optOutSecret()), 0, 32);
    }

    /** The member id a token proves, or 0 if it proves nothing. */
    public static function verifyOptOutToken(string $token): int
    {
        [$id, $sig] = array_pad(explode('.', $token, 2), 2, '');
        $id = (int)$id;
        if ($id <= 0 || $sig === '') {
            return 0;
        }
        return hash_equals(substr(hash_hmac('sha256', 'optout:' . $id, self::optOutSecret()), 0, 32), $sig)
            ? $id : 0;
    }

    /**
     * The install's own signing key, made once and kept.
     *
     * Not derived from anything guessable and not shared with the cron token:
     * a key that also appears in a URL someone pastes into a hosting panel is
     * a key that will end up in a support ticket.
     */
    private static function optOutSecret(): string
    {
        $k = Database::setting('optout_secret');
        if ($k === '') {
            $k = bin2hex(random_bytes(32));
            Database::setSetting('optout_secret', $k);
        }
        return $k;
    }

    /** The absolute one-click opt-out URL for this member, or '' if unknown. */
    public static function optOutUrl(int $memberId): string
    {
        $base = rtrim(Database::setting('site_url'), '/');
        if ($base === '' || $memberId <= 0) {
            return '';
        }
        return $base . '/unsubscribe.php?t=' . rawurlencode(self::optOutToken($memberId));
    }

    public static function csrfToken(): string
    {
        if (empty($_SESSION['mcsrf'])) {
            $_SESSION['mcsrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['mcsrf'];
    }

    public static function verifyCsrf(): bool
    {
        $token = $_POST['csrf'] ?? '';
        return is_string($token) && $token !== '' && hash_equals($_SESSION['mcsrf'] ?? '', $token);
    }
}
