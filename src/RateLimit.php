<?php
declare(strict_types=1);

namespace SignalMasterAi;

/**
 * Fixed-window per-client rate limiting for the public JSON API.
 *
 * `api.php?action=signal` runs a full technical analysis and `action=alerts`
 * can trigger three of them per call - both reachable by anonymous visitors on
 * public coins. Without a limiter a single script could pin the CPU and burn
 * the upstream exchange quota. Limits are admin-tunable and generous enough
 * that the site's own polling (a signal every 60 s, a ticker every 8 s) never
 * trips them.
 */
class RateLimit
{
    /** Per-action budgets: [requests, window seconds]. */
    private const BUDGETS = [
        'signal'   => [40, 60],
        'candles'  => [40, 60],
        'alerts'   => [30, 60],
        'ticker'   => [90, 60],
        'news'     => [30, 60],
        'scan'     => [30, 60],
        'feed'     => [60, 60],
        'symbols'  => [30, 60],
        'prefs'      => [30, 60],
        'watchlists' => [40, 60],
        'paper'      => [40, 60],
        'ask'        => [10, 60],   // each call reaches an upstream model
        'default'  => [120, 60],
    ];

    /** Best-effort client identity: proxy-aware, falls back to the peer IP. */
    public static function clientKey(): string
    {
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'cli');
        // Only trust forwarding headers when the operator opted in - otherwise
        // anyone could rotate the header and bypass the limiter entirely.
        if (Database::setting('trust_proxy', '0') === '1') {
            $fwd = (string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
            if ($fwd !== '') {
                $first = trim(explode(',', $fwd)[0]);
                if (filter_var($first, FILTER_VALIDATE_IP)) {
                    $ip = $first;
                }
            }
        }
        // Logged-in members get their own bucket so a shared office NAT does
        // not throttle paying users because of a noisy neighbour.
        $member = MemberAuth::currentId();
        return $member > 0 ? 'm' . $member : 'i' . hash('sha256', $ip);
    }

    /**
     * Login throttle, counted server-side.
     *
     * Both login forms used to count failures in $_SESSION, which is not a
     * throttle at all: the counter lives in a cookie the client controls, so
     * an attacker who simply never sends one gets an unlimited number of
     * guesses. Two buckets are kept - one per IP, one per account named - so
     * a botnet spraying one password cannot hide behind rotating addresses,
     * and a single address cannot grind through a dictionary on one account.
     *
     * Independent of rate_limit_enabled: an operator turning down API limits
     * has not asked to leave the password form open.
     *
     * Returns [allowed, retryAfterSeconds].
     */
    public static function loginCheck(string $scope, string $identifier): array
    {
        if (PHP_SAPI === 'cli') {
            return [true, 0];
        }
        $limit  = max(3, min(50, (int)Database::setting('login_attempt_limit', '8')));
        $window = max(60, min(3600, (int)Database::setting('login_attempt_window', '900')));
        foreach ([self::clientKey(), 'u' . hash('sha256', strtolower(trim($identifier)))] as $who) {
            $n = (int)(Cache::get('lf:' . $scope . ':' . $who) ?? 0);
            if ($n >= $limit) {
                return [false, $window];
            }
        }
        return [true, 0];
    }

    /**
     * Record a failed attempt against both buckets. Counted on failure only,
     * so a member logging in normally is never a step closer to a lockout.
     */
    public static function loginFailed(string $scope, string $identifier): void
    {
        if (PHP_SAPI === 'cli') {
            return;
        }
        $window = max(60, min(3600, (int)Database::setting('login_attempt_window', '900')));
        foreach ([self::clientKey(), 'u' . hash('sha256', strtolower(trim($identifier)))] as $who) {
            Cache::increment('lf:' . $scope . ':' . $who, $window);
        }
    }

    /** Clear both buckets after a successful login. */
    public static function loginPassed(string $scope, string $identifier): void
    {
        foreach ([self::clientKey(), 'u' . hash('sha256', strtolower(trim($identifier)))] as $who) {
            Cache::forget('lf:' . $scope . ':' . $who);
        }
    }

    /**
     * Returns [allowed, retryAfterSeconds]. Disabled entirely when the admin
     * turns `rate_limit_enabled` off, and never applies on the CLI.
     */
    public static function check(string $action): array
    {
        if (PHP_SAPI === 'cli' || Database::setting('rate_limit_enabled', '1') !== '1') {
            return [true, 0];
        }
        [$limit, $window] = self::BUDGETS[$action] ?? self::BUDGETS['default'];
        $scale = (float)Database::setting('rate_limit_scale', '1.0');
        if ($scale > 0 && abs($scale - 1.0) > 0.01) {
            $limit = (int)max(5, round($limit * $scale));
        }
        $bucket = 'rl:' . $action . ':' . self::clientKey() . ':' . intdiv(time(), $window);
        $count = Cache::increment($bucket, $window + 5);
        if ($count > $limit) {
            return [false, $window - (time() % $window)];
        }
        return [true, 0];
    }
}
