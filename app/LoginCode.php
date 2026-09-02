<?php
declare(strict_types=1);

/**
 * Passwordless sign-in: a six-digit code, emailed to the address on the
 * account, good once and for ten minutes.
 *
 * Codes are stored hashed. Requesting one never reveals whether an account
 * exists, so the form cannot be used to enumerate customers.
 */
final class LoginCode
{
    public const TTL_MINUTES     = 10;
    public const MAX_ATTEMPTS    = 5;   // wrong codes before this one dies
    private const MAX_PER_HOUR   = 5;   // codes requested per address
    private const RESEND_SECONDS = 45;  // between requests

    /**
     * Issue a code and email it. Returns the same shape whether or not the
     * address has an account.
     * @return array{0:bool,1:string} [accepted, message]
     */
    public static function request(string $email, string $audience): array
    {
        $email = trim(mb_strtolower($email));
        if (!is_email($email)) {
            return [false, 'Enter a valid email address.'];
        }

        $recent = (int)Database::value(
            'SELECT COUNT(*) FROM login_codes WHERE email = :e AND created_at > :t',
            ['e' => $email, 't' => date('Y-m-d H:i:s', time() - 3600)], 0
        );
        if ($recent >= self::MAX_PER_HOUR) {
            return [false, 'Too many codes requested for that address. Try again in an hour.'];
        }
        $last = (string)Database::value(
            'SELECT created_at FROM login_codes WHERE email = :e ORDER BY id DESC LIMIT 1',
            ['e' => $email], ''
        );
        if ($last !== '' && (time() - strtotime($last)) < self::RESEND_SECONDS) {
            return [false, 'A code was just sent. Check your inbox, or wait a moment before asking for another.'];
        }

        $user = Database::one('SELECT * FROM users WHERE email = :e', ['e' => $email]);
        $eligible = $user
            && $user['status'] === 'active'
            && ($audience === 'staff' ? $user['role'] === 'admin' : $user['role'] !== 'admin');

        if ($eligible) {
            $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            Database::run('DELETE FROM login_codes WHERE email = :e AND used_at IS NULL', ['e' => $email]);
            Database::insert('login_codes', [
                'email'      => $email,
                'code_hash'  => password_hash($code, PASSWORD_DEFAULT),
                'audience'   => $audience,
                'expires_at' => date('Y-m-d H:i:s', time() + self::TTL_MINUTES * 60),
                'attempts'   => 0,
                'ip'         => client_ip(),
                'created_at' => now(),
            ]);

            $site = Settings::get('site_name', 'TECHBISS');
            [$sent, $err] = Mail::send(
                $email,
                $code . ' is your ' . $site . ' sign-in code',
                "Hello " . $user['name'] . ",\n\n"
                . "Your sign-in code is:\n\n    " . $code . "\n\n"
                . "It works once and expires in " . self::TTL_MINUTES . " minutes.\n\n"
                . "If you did not try to sign in, you can ignore this email — "
                . "nobody can get in without the code.\n\n"
                . "— " . $site . "\n" . rtrim(url(), '/') . "\n"
            );
            if (!$sent) {
                error_log('[TECHBISS] could not email a sign-in code: ' . (string)$err);
            }
            log_activity('login.code_sent', 'user', (int)$user['id'], $audience);
        } else {
            /* Spend similar time either way so timing does not leak accounts. */
            usleep(random_int(120000, 260000));
        }

        return [true, 'If that address has an account, a six-digit code is on its way. It expires in '
            . self::TTL_MINUTES . ' minutes.'];
    }

    /**
     * Check a code and sign the person in.
     * @return array{0:bool,1:string} [signed in, message]
     */
    public static function verify(string $email, string $code, string $audience): array
    {
        $email = trim(mb_strtolower($email));
        $code  = preg_replace('/\D+/', '', $code) ?? '';
        if ($email === '' || $code === '') {
            return [false, 'Enter the six-digit code from your email.'];
        }

        $row = Database::one(
            'SELECT * FROM login_codes WHERE email = :e AND audience = :a AND used_at IS NULL
             ORDER BY id DESC LIMIT 1',
            ['e' => $email, 'a' => $audience]
        );
        if (!$row) {
            return [false, 'That code is not valid. Request a new one.'];
        }
        if (strtotime((string)$row['expires_at']) < time()) {
            return [false, 'That code has expired. Request a new one.'];
        }
        if ((int)$row['attempts'] >= self::MAX_ATTEMPTS) {
            return [false, 'Too many wrong attempts on that code. Request a new one.'];
        }

        if (!password_verify($code, (string)$row['code_hash'])) {
            Database::update('login_codes', ['attempts' => (int)$row['attempts'] + 1], (int)$row['id']);
            $left = self::MAX_ATTEMPTS - ((int)$row['attempts'] + 1);
            return [false, $left > 0
                ? 'That code is not right. ' . $left . ' ' . ($left === 1 ? 'try' : 'tries') . ' left.'
                : 'That code is not right, and it has now been used up. Request a new one.'];
        }

        $user = Database::one('SELECT * FROM users WHERE email = :e', ['e' => $email]);
        if (!$user || $user['status'] !== 'active') {
            return [false, 'That account is not active. Contact support.'];
        }
        if ($audience === 'staff' && $user['role'] !== 'admin') {
            return [false, 'That account cannot sign in here.'];
        }
        if ($audience === 'client' && $user['role'] === 'admin') {
            return [false, 'Administrators sign in on the staff page.'];
        }

        Database::update('login_codes', ['used_at' => now()], (int)$row['id']);
        Database::run('DELETE FROM login_codes WHERE email = :e AND used_at IS NULL', ['e' => $email]);
        Auth::login($user);
        return [true, ''];
    }

    /** Housekeeping: drop codes that can no longer be used. */
    public static function prune(): void
    {
        try {
            Database::run('DELETE FROM login_codes WHERE expires_at < :t OR used_at IS NOT NULL',
                ['t' => date('Y-m-d H:i:s', time() - 86400)]);
        } catch (PDOException) {
            // The table may not exist yet on an install that has not migrated.
        }
    }
}
