<?php
declare(strict_types=1);

/**
 * Common bootstrap: loads config, autoloads classes, connects the database.
 * Redirects to the installer until installation has completed.
 */

$config = require __DIR__ . '/../config.php';

/**
 * Class loader.
 *
 * The `is_file()` check used to fail silently, which turned "src/Broadcast.php
 * did not reach this server" into `Class "SignalMasterAi\Broadcast" not found`
 * - a message that reads like a code bug and sends the operator looking for
 * one. An upload that drops a file, an extract that runs out of disk part way
 * through, or a src/ directory copied without its newest members all produce
 * exactly that, and nothing on the site says which file is missing.
 *
 * So the loader now says what it actually found. A class inside this
 * namespace has exactly one home; if that file is not there, or is there and
 * cannot be read, that is the fact worth reporting. The class still fails to
 * load either way - this only replaces the misleading half of the message.
 */
spl_autoload_register(function (string $class): void {
    $prefix = 'SignalMasterAi\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $rel  = 'src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    $file = dirname(__DIR__) . '/' . $rel;
    if (is_file($file)) {
        if (!is_readable($file)) {
            throw new \RuntimeException(
                $rel . ' exists on this server but cannot be read (check its file permissions). '
                . 'Nothing that needs ' . $class . ' can run until that is fixed.');
        }
        require $file;
        return;
    }
    // Missing. Say so here rather than letting PHP report a class that no
    // longer appears to exist - the file is the thing to go and fix.
    throw new \RuntimeException(
        $rel . ' is missing from this server, so ' . $class . ' cannot load. '
        . 'Re-upload the src/ directory from the release - an upload or unzip '
        . 'that stops part way through leaves exactly this.');
});

if (!defined('SMA_INSTALLER') && !is_file(SMA_LOCK_FILE)) {
    // Not installed yet.
    //
    // This used to redirect every visitor into the installer. Right for the
    // person who just uploaded the files, wrong for anyone else who arrives
    // before that happens - and a 302 into a database configuration form is a
    // poor thing for a crawler to find. Visitors get a holding page with a
    // 503 and a Retry-After; the installer is one click from it.
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/');
    // From /admin/* the installer lives one level up.
    $base = preg_replace('#/admin$#', '', $base);
    $installUrl = ($base === '' ? '' : $base) . '/install.php';
    $script = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));

    // The JSON endpoints answer in JSON. An HTML holding page parsed as a feed
    // is a console error nobody can act on.
    if (in_array($script, ['api.php', 'cron.php', 'payment_webhook.php'], true)) {
        if (!headers_sent()) {
            http_response_code(503);
            header('Retry-After: 3600');
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode(['ok' => false, 'error' => 'This site has not been installed yet.']);
        exit;
    }
    require __DIR__ . '/Setup.php';
    if (\SignalMasterAi\Setup::shouldHold($script)) {
        \SignalMasterAi\Setup::hold();
    }
    header('Location: ' . $installUrl);
    exit;
}

// Production hardening: never print PHP errors/stack traces to visitors
// (paths and queries would leak); they still go to the server error log.
if (PHP_SAPI !== 'cli') {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}

// Baseline security headers on every page and endpoint.
if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    // CONTENT SECURITY POLICY.
    //
    // Everything the app loads is same-origin (no CDNs). The part that matters
    // is script-src, and it says two different things in two places on purpose:
    //
    //   Public and member pages get a NONCE and no 'unsafe-inline'. Injected
    //   script cannot carry a value generated after it was stored, so an XSS
    //   on these pages - the ones that render other people's data to the most
    //   visitors - does not execute. This is the layer that decides whether a
    //   future mistake is a defacement or a non-event.
    //
    //   The admin panel keeps 'unsafe-inline', because fifty-odd inline
    //   handlers live there ("onsubmit=return confirm(...)" and friends) and a
    //   nonce silently disables all of them - a delete button that stops
    //   asking before it deletes is a worse outcome than the thing being
    //   defended against. Everything there is behind a login, so the exposure
    //   is an operator attacking themselves. Converting those handlers is the
    //   way to close this properly; until then the difference is stated rather
    //   than hidden behind one policy that claims more than it delivers.
    $smaCspScript = \SignalMasterAi\Request::isAdminArea()
        ? "'self' 'unsafe-inline'"
        : "'self' 'nonce-" . \SignalMasterAi\Request::cspNonce() . "'";
    // Held in a variable rather than sent here: the policy now depends on a
    // setting, and there is no database open at this point in the file. Sent
    // below the boot, next to HSTS, which is already there for exactly this
    // reason. Nothing has been echoed yet, so the header is still in time.
    $GLOBALS['sma_csp_script'] = $smaCspScript;

}

\SignalMasterAi\Database::boot($config);

// CONTENT SECURITY POLICY, continued.
//
// WEBFONTS NEED SAYING OUT LOUD, OR THEY DO NOT ARRIVE.
//
// There was no font-src at all, so fonts fell through to default-src 'self'
// and a stylesheet from fonts.googleapis.com was refused by style-src. Adding
// the <link> without this is worse than not adding it: the browser blocks both
// silently, the page renders in the fallback chain, and the only evidence is a
// console line nobody reads. That is how the type in a design system goes
// missing while every file that names it looks correct.
//
// Widened only while the setting is on, so an operator who turns webfonts off
// gets the tighter policy back rather than carrying two origins they no longer
// use.
if (!headers_sent() && isset($GLOBALS['sma_csp_script'])) {
    $smaFonts    = \SignalMasterAi\Database::setting('webfonts', '1') === '1';
    $smaStyleSrc = "'self' 'unsafe-inline'" . ($smaFonts ? ' https://fonts.googleapis.com' : '');
    $smaFontSrc  = "'self'" . ($smaFonts ? ' https://fonts.gstatic.com' : '');
    header("Content-Security-Policy: default-src 'self'; script-src "
        . $GLOBALS['sma_csp_script'] . "; "
        . "style-src " . $smaStyleSrc . "; font-src " . $smaFontSrc . "; "
        . "img-src 'self' data: blob:; connect-src 'self'; "
        . "frame-ancestors 'self'; base-uri 'self'; form-action 'self'; object-src 'none'");
}

// HSTS: only ever on a request that already arrived over TLS.
//
// Below the boot rather than with the other headers, because it reads a
// setting and there was no database open up there.
//
// Sent over http it would be ignored anyway; the reason for the condition is
// the opposite case - an operator serving both schemes who is not ready to
// commit. This header is a promise the browser keeps for its whole max-age and
// cannot be withdrawn by deleting it, so it goes out only when the site is
// demonstrably already doing what it promises, and an operator who has to back
// out can switch it off and wait it out. Six months, no preload, and no
// includeSubDomains - this application does not know what else is on the
// domain and must not speak for it.
if (PHP_SAPI !== 'cli' && !headers_sent()
    && \SignalMasterAi\Request::isSecure()
    && \SignalMasterAi\Database::setting('hsts_enabled', '1') === '1') {
    header('Strict-Transport-Security: max-age=15552000');
}

// Record what breaks. Display behaviour is unchanged - this only writes the
// failure somewhere the operator will actually find it, instead of leaving it
// in a server log they have no way to read on shared hosting.
\SignalMasterAi\ErrorLog::install();
// Deny files in the data directory, written if missing. Three is_file()
// checks, and it self-heals an install restored from a backup that skipped
// dotfiles - which is exactly how the root .htaccess goes missing.
\SignalMasterAi\DataGuard::harden();

// www and non-www.
//
// Both spellings serve the site. They used to be forced onto whichever one
// site_url happened to learn first, which is a guess dressed as a decision -
// and a bad one to make automatically, because a 301 to a host the
// certificate does not cover turns a working site into a browser warning. So
// the default is now to serve both, and an operator who wants one canonical
// spelling picks it deliberately in Settings > Site once they know which host
// their certificate covers.
//
// API, cron and payment webhooks are never redirected whatever the setting:
// a gateway POSTing to a 301 mostly drops the body and retries as a GET.
if (PHP_SAPI !== 'cli' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $hostMode = \SignalMasterAi\Database::setting('canonical_host', 'both');
    $curHost = strtolower(explode(':', (string)($_SERVER['HTTP_HOST'] ?? ''))[0]);
    $script = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
    // Redirect only between the www and non-www spellings of a host this
    // site already knows it answers to.
    //
    // The original built the target from HTTP_HOST - "Host: evil.example"
    // was answered with a 301 to https://www.evil.example plus the original
    // path. On a server that routes unknown names to the first virtual host,
    // which is what shared hosting does by default, that is reachable, and a
    // 301 is the response a CDN in front of the site is most willing to cache
    // and hand to everyone else.
    //
    // Anchoring the target to site_url is not enough on its own either:
    // site_url is itself learned from the first request that ever arrives, so
    // a forged Host on a fresh install would simply move the problem. So the
    // configured address is used as a test, never as a destination. An
    // unrecognised Host gets no redirect at all, and the worst a poisoned
    // site_url can do here is stop the canonicalisation happening.
    $baseHost = strtolower((string)parse_url(
        (string)\SignalMasterAi\Database::setting('site_url'), PHP_URL_HOST
    ));
    $bare = static fn(string $h): string => str_starts_with($h, 'www.') ? substr($h, 4) : $h;
    $known = $baseHost !== '' && $bare($baseHost) === $bare($curHost);
    $wantHost = null;
    if ($known && !in_array($script, ['api.php', 'cron.php', 'payment_webhook.php'], true)) {
        if ($hostMode === 'www' && !str_starts_with($curHost, 'www.')) {
            $wantHost = 'www.' . $curHost;
        } elseif ($hostMode === 'nonwww' && str_starts_with($curHost, 'www.')) {
            $wantHost = substr($curHost, 4);
        }
    }

    // Never invent a hostname that cannot exist. A bare name is a development
    // machine and an IP address is not a domain at all - "www.127.0.0.1"
    // resolves nowhere, so a test install would redirect itself off the web.
    // Checked against the host being redirected TO, because that is the one
    // that has to resolve, and it has to look like a hostname and nothing
    // else: it goes into a Location header.
    // Tested on the bare form: "www.127.0.0.1" is not an IP address as far as
    // filter_var is concerned, so checking the target as written would wave
    // through the exact case this guard exists to stop.
    $target = (string)$wantHost;
    $realDomain = $target !== ''
        && preg_match('/^[a-z0-9.-]+$/', $target) === 1
        && str_contains($target, '.')
        && !filter_var($bare($target), FILTER_VALIDATE_IP)
        && $bare($target) !== 'localhost'
        && !str_ends_with($target, '.localhost');
    if ($wantHost !== null && $realDomain) {
        $scheme = \SignalMasterAi\Request::scheme();
        header('Location: ' . $scheme . '://' . $wantHost . ($_SERVER['REQUEST_URI'] ?? '/'), true, 301);
        exit;
    }
}

// Clean URLs: /charts rather than /charts.php.
//
// Rewriting every href in the source would be two hundred edits and a site
// that 404s end to end on any host without mod_rewrite. One output filter
// instead, off unless the operator has turned it on AND the self-test in
// Settings has confirmed the rewrite actually works on their server. The .php
// URLs keep serving either way, so nothing that is already linked or bookmarked
// stops working.
// The guard that makes the default safe: only a self-test that has actually
// come back negative turns this off. Untested stays on, because the shipped
// .htaccess works on an ordinary Apache and refusing to write clean links
// until someone visits a settings page would mean nobody ever gets them.
$smaClean = \SignalMasterAi\Database::setting('clean_urls', '1') === '1'
    && \SignalMasterAi\Cache::get('rewrite_ok') !== false;
if (PHP_SAPI !== 'cli' && $smaClean) {
    ob_start('sma_clean_urls');
    // Redirects never pass through an output filter, so a form that posts and
    // redirects would drop the visitor back onto a .php address however clean
    // the links were. This catches the Location header on its way out - the
    // one place every redirect in the app has to go through.
    header_register_callback(static function (): void {
        foreach (headers_list() as $h) {
            if (stripos($h, 'Location:') !== 0) {
                continue;
            }
            $url = trim(substr($h, 9));
            $clean = sma_clean_urls('href="' . $url . '"');
            if (preg_match('/^href="(.*)"$/', $clean, $m) && $m[1] !== $url) {
                header('Location: ' . $m[1], true);
            }
            break;
        }
    });
}

// Inbound referral code (?ref=CODE) is remembered for 30 days, so the credit
// survives the visitor browsing before they register.
if (PHP_SAPI !== 'cli' && isset($_GET['ref']) && is_string($_GET['ref'])
    && \SignalMasterAi\Referrals::enabled()) {
    \SignalMasterAi\Referrals::remember($_GET['ref']);
}

/**
 * Rewrite the site's own links to their extensionless form.
 *
 * Runs as an output filter so the source keeps writing "charts.php" - two
 * hundred hrefs across twenty-seven files, and a single place that decides how
 * they are spelled. Off unless the operator turned it on and the self-test
 * confirmed their server rewrites; the .php URLs never stop serving either
 * way, so an old bookmark or an inbound link still lands.
 *
 * Only page links are touched. The endpoints keep their extension on purpose:
 * api.php is called by the front end with query strings, cron.php is pasted
 * into a hosting panel, payment_webhook.php is registered with a gateway that
 * will not follow a redirect, and install.php has to work before any of this
 * is configured. logo.php and pm-image.php serve binary data, not pages.
 */
function sma_clean_urls(string $html): string
{
    static $keep = ['api', 'cron', 'payment_webhook', 'install', 'logo', 'pm-image'];
    // The endpoints live at the site root. From an admin page they are written
    // "../api.php"; a bare "api.php" there is admin/api.php, which IS a page
    // and should be cleaned like any other. Without this the one admin page
    // whose name collides with an endpoint keeps its extension for ever.
    $inAdmin = str_ends_with(rtrim(dirname((string)($_SERVER['SCRIPT_NAME'] ?? '')), '/'), '/admin');
    return (string)preg_replace_callback(
        '/\b(href|action)="([^"#?:]*?)([A-Za-z0-9_-]+)\.php((?:[?#][^"]*)?)"/',
        static function (array $m) use ($keep, $inAdmin): string {
            $atRoot = $inAdmin ? str_contains($m[2], '../') : !str_contains($m[2], '/');
            if ($atRoot && in_array($m[3], $keep, true)) {
                return $m[0];
            }
            // index is the directory itself: /admin/index -> /admin/ reads
            // better and is one fewer thing to get wrong in a link.
            $target = $m[3] === 'index' ? ($m[2] !== '' ? $m[2] : './') : $m[2] . $m[3];
            return $m[1] . '="' . $target . $m[4] . '"';
        },
        $html
    ) ?: $html;
}

/** Convenience: read a runtime setting with a config fallback. */
function sma_setting(string $key, string $default = ''): string
{
    return \SignalMasterAi\Database::setting($key, $default);
}

function sma_e(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/**
 * The nonce attribute for an inline <script>, ready to paste into the tag.
 *
 * Every inline script on a public or member page needs it: that policy has no
 * 'unsafe-inline', so a script tag without this one attribute simply does not
 * run - silently, with only a console message. Returns a complete
 * ` nonce="..."` including the leading space so it cannot be forgotten in the
 * middle of a tag, and an empty string in the admin panel, whose policy still
 * allows inline script and where an unnecessary nonce would be noise.
 */
function sma_nonce(): string
{
    if (\SignalMasterAi\Request::isAdminArea()) {
        return '';
    }
    return ' nonce="' . htmlspecialchars(\SignalMasterAi\Request::cspNonce(), ENT_QUOTES, 'UTF-8') . '"';
}

/**
 * JSON for inline <script> contexts: HEX flags stop any stored value from
 * breaking out of the script tag (e.g. a "</script>" inside a string).
 */
function sma_js(mixed $v): string
{
    return json_encode($v, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: 'null';
}

/**
 * A URL that is safe to put in an href, src or Location.
 *
 * sma_e() is the wrong tool for this and looks like the right one.
 * "javascript:alert(document.domain)" contains no HTML metacharacter, so
 * htmlspecialchars passes it through untouched and it lands in the attribute
 * exactly as written - a link that runs script when clicked. Verified: a news
 * item whose URL was that string rendered as href="javascript:..." in the
 * admin panel, and news URLs come from third-party RSS feeds.
 *
 * Only http and https survive. Anything else with a scheme - javascript:,
 * data:, vbscript:, file: - becomes the fallback, as does a protocol-relative
 * "//evil.example" whose host is not ours.
 *
 * Control characters are rejected before the scheme is examined, and that
 * order is the whole point: browsers strip tabs and newlines out of a URL
 * before parsing it, so "java\tscript:alert(1)" is a working javascript URL
 * that no test for a leading "javascript:" will ever match.
 */
function sma_href(?string $url, string $fallback = ''): string
{
    $ok = sma_url($url);
    return $ok === '' ? $fallback : sma_e($ok);
}

/**
 * The same check, returning the URL unescaped.
 *
 * For the places that need the address itself rather than markup - a Location
 * header, a curl target - where HTML escaping would be wrong. Returns '' when
 * the URL is not one this application is willing to send anyone to.
 */
function sma_url(?string $url): string
{
    $u = trim((string)$url);
    if ($u === '' || preg_match('/[\x00-\x1F\x7F]/', $u)) {
        return '';
    }
    if (str_starts_with($u, '//')) {
        return '';                              // scheme ours, host theirs
    }
    if (preg_match('#^[a-z][a-z0-9+.\-]*:#i', $u) && !preg_match('#^https?://#i', $u)) {
        return '';
    }
    return $u;
}

/**
 * Site logo href: the admin-uploaded custom logo when one is set (served via
 * logo.php because the uploads dir is web-blocked), else the bundled default.
 * $prefix handles pages living in subdirectories (admin uses '../').
 */
function sma_logo(string $prefix = ''): string
{
    $custom = \SignalMasterAi\Database::setting('custom_logo');
    return $custom !== ''
        ? $prefix . 'logo.php?v=' . rawurlencode($custom)
        : $prefix . 'assets/brand/logo.svg';
}

/**
 * Asset href with a cache-busting version (file mtime), so browsers pick up
 * new CSS/JS immediately after an upgrade instead of serving stale styles.
 */
function sma_asset(string $href): string
{
    $file = SMA_ROOT . '/' . ltrim((string)preg_replace('#^(\.\./)+#', '', $href), '/');
    return $href . '?v=' . (@filemtime($file) ?: 1);
}

/**
 * The traded asset's own ticker, for labelling quantities.
 *
 * "0.4217 units" says nothing; "0.4217 BTC" is the thing you are holding.
 * Pairs are quoted against a handful of known currencies, so the base is
 * whatever remains once the quote is stripped. Non-crypto symbols are
 * namespaced (stooq:aapl.us) and carry their ticker in the middle.
 */
function sma_base_asset(string $symbol): string
{
    $symbol = trim($symbol);
    if ($symbol === '') {
        return '';
    }
    if (str_contains($symbol, ':')) {                 // stooq:aapl.us -> AAPL
        $rest = substr($symbol, strpos($symbol, ':') + 1);
        return strtoupper(explode('.', $rest)[0]);
    }
    $upper = strtoupper($symbol);
    // Longest first, so USDT is stripped before USD.
    foreach (['USDT', 'FDUSD', 'TUSD', 'BUSD', 'USDC', 'USD', 'EUR', 'TRY', 'BTC', 'ETH', 'BNB'] as $quote) {
        if (strlen($upper) > strlen($quote) && str_ends_with($upper, $quote)) {
            return substr($upper, 0, -strlen($quote));
        }
    }
    return $upper;
}

return $config;
