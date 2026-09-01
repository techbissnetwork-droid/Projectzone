<?php
declare(strict_types=1);

namespace SignalMasterAi;

/**
 * What the browser actually asked for.
 *
 * One question, asked ten times in this codebase: is this request HTTPS?
 * Every one of them answered it the same way -
 *
 *     !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'
 *
 * - and every one of them is wrong on the hosting this product is mostly
 * deployed on. Behind Cloudflare, a shared-hosting reverse proxy, or any load
 * balancer that terminates TLS, PHP is handed a plain HTTP request and
 * $_SERVER['HTTPS'] is never set. The visitor is on https://; the application
 * believes they are not.
 *
 * That belief is not cosmetic:
 *
 *   - Session cookies for both the admin and member logins lose the Secure
 *     flag, so anything that can force one plaintext request to the domain
 *     gets a live session cookie.
 *   - View::learnSiteUrl() records site_url from the first request that ever
 *     arrives, permanently. Behind a proxy it records http://, and that value
 *     then goes into every alert email link, every payment return URL, the
 *     push contact claim and DataGuard's own self-check - all of them http on
 *     an https site, for the life of the install.
 *   - Canonical and og: URLs advertise the wrong scheme.
 *
 * So the proxy's own headers are honoured. The usual objection is that a
 * client can forge X-Forwarded-Proto, and it can - but consider what forging
 * it upward buys. Cookie flags are set per response from that same request, so
 * an attacker who claims https on a plain HTTP site gets a Secure cookie their
 * own browser then refuses to send back. It is a way to log yourself out. No
 * other visitor's request is touched.
 *
 * The one thing a forged header can reach is learnSiteUrl(), which fires once
 * on a fresh install and would need the attacker to be the very first visitor
 * - ahead of the operator who just installed it - and it writes a setting the
 * admin panel shows and lets them edit. That is worth accepting to stop
 * getting the scheme wrong for the large majority of real deployments.
 *
 * Note this is deliberately not gated behind the trust_proxy setting.
 * trust_proxy exists because a forged X-Forwarded-For lets someone else's
 * address absorb their rate limit, which is a real gain for an attacker;
 * claiming https is not.
 */
class Request
{
    /**
     * True when the visitor reached this page over TLS, including when the TLS
     * was terminated by something in front of PHP.
     */
    public static function isSecure(): bool
    {
        // Terminated by this server: the only signal that cannot be forged.
        if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
            return true;
        }
        if ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443) {
            return true;
        }
        if (strtolower((string)($_SERVER['REQUEST_SCHEME'] ?? '')) === 'https') {
            return true;
        }
        // Terminated in front of this server. X-Forwarded-Proto may carry a
        // chain ("https, http") when more than one hop added to it; the client
        // faced the first.
        $fwd = (string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '');
        if ($fwd !== '' && strtolower(trim(explode(',', $fwd)[0])) === 'https') {
            return true;
        }
        // Older and vendor spellings of the same claim.
        foreach (['HTTP_X_FORWARDED_SSL', 'HTTP_FRONT_END_HTTPS', 'HTTP_X_URL_SCHEME'] as $k) {
            $v = strtolower(trim((string)($_SERVER[$k] ?? '')));
            if ($v === 'on' || $v === 'https') {
                return true;
            }
        }
        // Cloudflare states it outright when the visitor was on TLS.
        if (!empty($_SERVER['HTTP_CF_VISITOR'])
            && str_contains((string)$_SERVER['HTTP_CF_VISITOR'], '"https"')) {
            return true;
        }
        return false;
    }

    /** 'https' or 'http', for building an absolute URL. */
    public static function scheme(): string
    {
        return self::isSecure() ? 'https' : 'http';
    }

    /**
     * Cookie parameters for setcookie(), which spells the expiry "expires".
     *
     * Secure is set from the request rather than from a setting, so an install
     * that is reached over both schemes keeps working on both instead of
     * locking out whichever one the operator did not configure for.
     */
    public static function cookieParams(string $path = '/', int $expires = 0): array
    {
        return ['expires' => $expires] + self::baseCookieParams($path);
    }

    /**
     * The same, for session_set_cookie_params(), which spells it "lifetime".
     *
     * Two methods rather than one, because the two functions take the same
     * idea under different key names and the mismatch is silent in the worst
     * way: an unrecognised key makes session_set_cookie_params() emit a
     * warning and carry on, so the flags still applied and every request wrote
     * a line to the error log for it. Zero is the right lifetime here anyway -
     * a session cookie that ends with the browser session.
     */
    public static function sessionCookieParams(string $path = '/'): array
    {
        return ['lifetime' => 0] + self::baseCookieParams($path);
    }

    /** The flags both spellings share. */
    private static function baseCookieParams(string $path): array
    {
        return [
            'path'     => $path,
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => self::isSecure(),
        ];
    }

    /**
     * Scheme + host + the directory the app is installed in, with no trailing
     * slash. Used for canonical tags, og: URLs and absolute links.
     */
    public static function baseUrl(): string
    {
        return self::scheme() . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
             . rtrim(dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    }

    /**
     * The address of the SITE, not of whatever script happens to be running.
     *
     * baseUrl() ends in the directory of the current script, which is right
     * for a canonical tag and wrong for anything a third party has to call
     * back. Run from /upgrade.php it gives https://site/, run from
     * /admin/billing.php it gives https://site/admin - so a payment gateway
     * told where to send its webhook got a different answer depending on which
     * page asked, and one of those answers is a 404. The same "test it from
     * the panel, then it behaves differently for a buyer" trap.
     *
     * The configured Site URL wins when there is one, because that is the
     * operator's own statement of the address and survives proxies, CDNs and
     * a host header this code should not be trusting for money. Otherwise it
     * falls back to the request, with the one directory this app can have an
     * entry point in - /admin - removed.
     */
    public static function siteUrl(): string
    {
        $set = rtrim((string)Database::setting('site_url', ''), '/');
        if ($set !== '' && preg_match('~^https?://~i', $set)) {
            return $set;
        }
        $base = self::baseUrl();
        return preg_replace('~/admin$~', '', $base) ?? $base;
    }

    /**
     * One Content-Security-Policy nonce per request.
     *
     * The public policy allows inline script only when it carries this value,
     * so an injected <script> - which cannot know a number generated after the
     * attacker's payload was stored - does not run. That is the whole point of
     * the exercise: the site has no XSS today, and this is what decides
     * whether the next one is a defaced page or nothing at all.
     *
     * Generated once and cached, because a page that stamped two different
     * nonces into two script tags would have one of them blocked, and the
     * failure would look like "the chart is broken on some pages".
     *
     * Falls back to a fixed value when random_bytes is unavailable rather than
     * throwing. A predictable nonce is no protection, but neither is a site
     * that will not render.
     */
    public static function cspNonce(): string
    {
        static $nonce = null;
        if ($nonce === null) {
            try {
                $nonce = base64_encode(random_bytes(16));
            } catch (\Throwable $e) {
                $nonce = 'sma-static-nonce';
            }
        }
        return $nonce;
    }

    /**
     * Is this request for the admin panel?
     *
     * Decides which script policy applies. Not a security boundary - the
     * panel has its own login for that - only a statement about which set of
     * pages still contains inline event handlers.
     */
    public static function isAdminArea(): bool
    {
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
        $path = (string)(parse_url($uri, PHP_URL_PATH) ?? $uri);
        return str_contains($path, '/admin/') || str_ends_with($path, '/admin');
    }
}
