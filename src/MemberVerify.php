<?php
declare(strict_types=1);

namespace SignalMasterAi;

/**
 * Email verification at registration: a one-time code, mailed to the address
 * someone typed, that they have to type back before the account works.
 *
 * Registration used to take an email address on trust. That is fine right up
 * until it is not: a typo means a member who never receives a single alert and
 * blames the site, and a deliberately wrong address means this install sending
 * signal mail to a stranger who never asked for it - which is the fastest way
 * to get a sending domain blacklisted.
 *
 * Three decisions worth stating, because each one has a plausible opposite:
 *
 *   - The account is CREATED before the code is checked, not after. The
 *     alternative - hold the registration in the session until the code
 *     arrives - loses the account if the tab is closed, and cannot tell a
 *     second attempt from the first. Here the row exists with verified = 0,
 *     so "register again" finds the address taken and re-sends the code
 *     rather than colliding.
 *   - The code is stored HASHED, like the password reset token. A read of the
 *     members table should not hand out working codes.
 *   - Verification is skipped, not enforced, when the mailer is not
 *     configured. Requiring a code this install cannot send would close
 *     registration on a site whose operator never touched the setting - so
 *     the requirement stands down and the reason is written to the error log
 *     where an admin will see it, instead of failing silently or loudly.
 */
class MemberVerify
{
    /** Digits in a code. Six is what people expect and can hold in their head. */
    public const LEN = 6;

    /** Is a code required for new registrations right now? */
    public static function enabled(): bool
    {
        if (Database::setting('email_otp', '1') !== '1') {
            return false;
        }
        if (!Mailer::enabled()) {
            // On, but unsendable. Say so once - ErrorLog deduplicates, so an
            // operator sees one row with a growing count, not a flood.
            ErrorLog::record(
                ErrorLog::ALERTS,
                'Email verification is switched on, but email sending is not configured - '
                . 'new members are being registered without a code.',
                'Settings > Members > Email verification, or Settings > Alerts > Email sending'
            );
            return false;
        }
        return true;
    }

    /** Minutes a code stays good for. */
    public static function ttlMinutes(): int
    {
        return max(2, min(1440, (int)Database::setting('otp_ttl_min', '15')));
    }

    /** Wrong guesses allowed before the code is burned. */
    public static function maxTries(): int
    {
        return max(1, min(20, (int)Database::setting('otp_tries', '5')));
    }

    /** Seconds between "send me another one" presses. */
    public static function resendSeconds(): int
    {
        return max(15, min(3600, (int)Database::setting('otp_resend_sec', '60')));
    }

    /**
     * Does this member row still need to verify before logging in?
     *
     * Reads the stored flag only - a member who registered while the setting
     * was on is not let through by an operator turning it off later, because
     * the whole point was to establish that the address is real. Use
     * markVerified() to release them deliberately.
     */
    public static function pending(array $member): bool
    {
        return (int)($member['verified'] ?? 1) === 0;
    }

    /**
     * Issue a fresh code and mail it. Returns [sent, message].
     *
     * The cooldown is enforced here rather than at the form, so a script
     * posting the resend action directly cannot mail-bomb an address either.
     */
    public static function issue(int $memberId, bool $respectCooldown = true): array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT id, email, otp_sent FROM members WHERE id = ?');
        $stmt->execute([$memberId]);
        $m = $stmt->fetch();
        $stmt->closeCursor();
        if (!$m) {
            return [false, 'That account no longer exists.'];
        }
        $wait = self::resendSeconds();
        $since = time() - (int)($m['otp_sent'] ?? 0);
        if ($respectCooldown && (int)($m['otp_sent'] ?? 0) > 0 && $since < $wait) {
            return [false, 'A code was just sent. You can ask for another in '
                         . ($wait - $since) . ' seconds.'];
        }

        $code = self::newCode();
        // The cooldown stamp goes down FIRST, before the send is attempted, so
        // a mailer that is failing slowly cannot be hammered by someone
        // holding down the resend button. The code itself is committed only if
        // the send works - otherwise a broken mailer would replace a code the
        // member already has in their inbox with one that was never delivered,
        // turning a temporary outage into a locked-out account.
        $pdo->prepare('UPDATE members SET otp_sent = ? WHERE id = ?')->execute([time(), (int)$m['id']]);

        $site = sma_setting('site_name', 'SignalMasterAi');
        $mins = self::ttlMinutes();
        $subject = $code . ' is your ' . $site . ' verification code';
        $body = "Your verification code is: $code\n\n"
              . "Type it on the site to finish creating your account. It stops working in "
              . "$mins minutes.\n\n"
              . "If you did not try to register at $site, you can ignore this email - "
              . "no account can be used without this code.";
        $html = '<p style="font-size:15px">Your verification code is:</p>'
              . '<p style="font-size:30px;font-weight:700;letter-spacing:6px;margin:14px 0">'
              . sma_e($code) . '</p>'
              . '<p style="font-size:14px;color:#555">Type it on the site to finish creating your '
              . 'account. It stops working in ' . $mins . ' minutes.</p>'
              . '<p style="font-size:13px;color:#777">If you did not try to register at '
              . sma_e($site) . ', you can ignore this email &mdash; no account can be used '
              . 'without this code.</p>';

        $ok = Mailer::send((string)$m['email'], $subject, $body, $html, [
            'kind' => EmailLog::ACCOUNT,
            'member' => (int)$m['id'],
            'context' => 'registration verification code',
            // The code leads the real subject so a phone can autofill it. The
            // log gets the sentence without it - see Mailer::send().
            'log_subject' => 'Verification code for ' . $site,
        ]);
        if (!$ok) {
            return [false, self::sendFailedMessage()];
        }
        $pdo->prepare('UPDATE members SET otp_hash = ?, otp_expires = ?, otp_tries = 0 WHERE id = ?')
            ->execute([hash('sha256', $code), time() + self::ttlMinutes() * 60, (int)$m['id']]);
        return [true, self::sentMessage((string)$m['email'])];
    }

    /**
     * Check a typed code. Returns [ok, message].
     *
     * Wrong guesses are counted in the database, not the session: dropping a
     * cookie must not hand an attacker a fresh set of attempts at a six-digit
     * number. Running out burns the code rather than the account - the member
     * asks for another one and carries on.
     */
    public static function check(int $memberId, string $typed): array
    {
        $typed = preg_replace('/\D+/', '', $typed) ?? '';
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT id, email, verified, otp_hash, otp_expires, otp_tries
                               FROM members WHERE id = ?');
        $stmt->execute([$memberId]);
        $m = $stmt->fetch();
        $stmt->closeCursor();
        if (!$m) {
            return [false, 'That account no longer exists.'];
        }
        if ((int)$m['verified'] === 1) {
            return [true, 'This address is already verified.'];
        }
        if ((string)$m['otp_hash'] === '' || (int)$m['otp_expires'] < time()) {
            return [false, 'That code has expired. Ask for a new one below.'];
        }
        if ((int)$m['otp_tries'] >= self::maxTries()) {
            return [false, 'Too many wrong codes. Ask for a new one below.'];
        }
        if ($typed === '' || !hash_equals((string)$m['otp_hash'], hash('sha256', $typed))) {
            $pdo->prepare('UPDATE members SET otp_tries = otp_tries + 1 WHERE id = ?')->execute([$memberId]);
            $left = self::maxTries() - ((int)$m['otp_tries'] + 1);
            return [false, 'That code is not right.'
                         . ($left > 0 ? ' ' . $left . ' more ' . ($left === 1 ? 'try' : 'tries')
                                        . ' before you need a new one.' : ' Ask for a new one below.')];
        }
        self::markVerified($memberId);
        return [true, 'Email verified.'];
    }

    /**
     * What the site says when a code has gone out.
     *
     * Shared, because the decoy in the registration form has to say it
     * word for word. Two copies of this sentence would drift, and the
     * difference between them is exactly the answer the decoy refuses to give.
     */
    public static function sentMessage(string $email): string
    {
        return 'A ' . self::LEN . '-digit code is on its way to ' . $email . '.';
    }

    /**
     * And what it says when the send failed. Shared for the same reason: a
     * mailer that is down must not answer the question either. On an install
     * whose sending is broken, every address has to fail alike.
     */
    public static function sendFailedMessage(): string
    {
        return 'The code could not be sent. Check the email settings, or contact the site owner.';
    }

    /**
     * Somebody tried to register with an address that already has an account.
     *
     * The person at the form is told the same thing a new signup is told, so
     * they learn nothing. The person who owns the address is told what
     * happened, because to them it is either "I forgot I had an account" -
     * answered by the reminder - or "someone is probing my email", which is
     * worth knowing.
     *
     * Throttled per address, once an hour. Otherwise closing an enumeration
     * hole would open a mail-bombing one: the same form, run in a loop,
     * pointed at a victim's inbox.
     *
     * Returns whether a mail went out, so the caller can report exactly what
     * a real registration reports. That symmetry is the whole point: on an
     * install whose mailer is broken, a new address is told the code could not
     * be sent, and an address that already exists has to be told the same
     * thing or the difference answers the question all over again.
     *
     * The throttled path returns the remembered outcome rather than a fixed
     * one, so the second probe within the hour reads like the first.
     */
    public static function notifyExisting(string $email): bool
    {
        $email = mb_strtolower(trim($email));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !Mailer::enabled()) {
            return false;
        }
        $key = 'regnotify:' . substr(hash('sha256', $email), 0, 20);
        $seen = Cache::get($key);
        if ($seen !== null) {
            return $seen === '1';
        }
        $site = Database::setting('site_name', 'SignalMasterAi');
        $ok = false;
        try {
            $ok = (bool)Mailer::send(
                $email,
                'Someone tried to sign up with your email - ' . $site,
                "Someone just tried to create an account on $site using this email address.\n\n"
                . "If that was you: you already have an account, so log in instead. If you have\n"
                . "forgotten the password, use the \"Forgot password\" link.\n\n"
                . "If it was not you: nothing has happened. No account was created and your\n"
                . "password has not changed. You can ignore this message.\n",
                Mailer::template(
                    'Someone tried to sign up with your email',
                    '<p style="margin:0">Someone just tried to create an account using this email '
                    . 'address. <strong>You already have one</strong>, so nothing was created and '
                    . 'your password has not changed.</p><p>If it was you, log in instead - or use '
                    . 'the forgotten-password link. If it was not, you can ignore this.</p>',
                    'Log in', rtrim((string)Database::setting('site_url'), '/') . '/account.php'
                ),
                ['kind' => EmailLog::ACCOUNT, 'context' => 'signup attempt on an existing address']
            );
        } catch (\Throwable $e) {
            $ok = false;
        }
        Cache::set($key, $ok ? '1' : '0', 3600);
        return $ok;
    }

    /**
     * Mark an address verified and clear the code.
     *
     * Also used by the admin panel, for the member who says the mail never
     * arrives and the operator who has confirmed the address another way.
     */
    public static function markVerified(int $memberId): void
    {
        // Was this a first verification? Read before the write, because after
        // it every account looks the same. An operator re-verifying somebody
        // who was already verified is not a new signup and is owed nothing.
        $fresh = false;
        try {
            $st = Database::pdo()->prepare('SELECT verified FROM members WHERE id = ?');
            $st->execute([$memberId]);
            $fresh = (int)($st->fetchColumn() ?: 0) === 0;
            $st->closeCursor();
        } catch (\Throwable $e) {
        }
        Database::pdo()->prepare(
            "UPDATE members SET verified = 1, otp_hash = '', otp_expires = 0, otp_tries = 0 WHERE id = ?"
        )->execute([$memberId]);
        // The account is usable now, which is when a trial starts. Silent and
        // guarded - see Trial::maybeStart().
        if ($fresh) {
            Trial::maybeStart($memberId);
        }
    }

    /** Require a code from an existing member again (admin action). */
    public static function markUnverified(int $memberId): void
    {
        Database::pdo()->prepare(
            "UPDATE members SET verified = 0, otp_hash = '', otp_expires = 0, otp_tries = 0, otp_sent = 0
             WHERE id = ?"
        )->execute([$memberId]);
    }

    /** How many accounts are sitting unverified. */
    public static function pendingCount(): int
    {
        try {
            return (int)Database::pdo()->query('SELECT COUNT(*) FROM members WHERE verified = 0')->fetchColumn();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * A code with no leading zero stripped and no bias.
     *
     * random_int over the full 6-digit range including leading zeros, kept as
     * a string throughout - an integer somewhere in the middle turns "047321"
     * into "47321" and a member typing what they were sent gets told it is
     * wrong.
     */
    private static function newCode(): string
    {
        return str_pad((string)random_int(0, (10 ** self::LEN) - 1), self::LEN, '0', STR_PAD_LEFT);
    }
}
