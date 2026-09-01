<?php
declare(strict_types=1);

namespace SignalMasterAi;

/**
 * What a visitor sees before the site has been installed.
 *
 * Every page used to redirect straight into the installer. That is right for
 * the person who just uploaded the files and wrong for everybody else: an
 * upload takes minutes, DNS can point at the folder before then, and anyone
 * who arrives in that window is handed a database configuration form for a
 * site they do not own. Search engines are handed it too, and a 302 to an
 * installer is a poor thing to have indexed.
 *
 * So visitors get a plain "not ready yet" page with a 503 and a Retry-After,
 * which is the honest status code and the one crawlers understand as "come
 * back, do not index this". The page does not link the installer - anyone
 * who knows to look for install.php can still reach it directly (see
 * shouldHold() below, which never intercepts that one script), but a public
 * holding page is not the place to advertise where it lives.
 */
class Setup
{
    private static function chromeFonts(): string { return Chrome::fonts(); }

    /**
     * Should this request see the holding page rather than the installer?
     *
     * The installer itself and its assets must never be intercepted, or there
     * would be no way through. Everything else is fair game.
     */
    public static function shouldHold(string $script): bool
    {
        return !in_array($script, ['install.php'], true);
    }

    /**
     * Send the holding response and stop.
     *
     * Deliberately self-contained: no database, no stylesheet, no settings.
     * Nothing it needs exists yet, and a setup page that itself depends on
     * setup is how an install ends in a blank screen.
     *
     * Takes no installer URL - it used to print one as a "Site owner? Run
     * the installer" link, which meant every visitor before launch, crawler
     * included, was handed a signpost straight to the database setup form.
     * install.php is still reachable by anyone who navigates to it directly
     * (shouldHold() above never holds that request); this page just stops
     * pointing at it.
     */
    public static function hold(): never
    {
        if (!headers_sent()) {
            http_response_code(503);
            header('Retry-After: 3600');
            header('Content-Type: text/html; charset=utf-8');
            // A holding page must never be cached - the site could be live a
            // minute later and the visitor would still be looking at this.
            header('Cache-Control: no-store, max-age=0');
            header('X-Robots-Tag: noindex');
        }
        // The fonts link, the stylesheet, and the brand mark are all built by
        // PHP calls - but this page's body is one echoed heredoc, and a
        // heredoc only interpolates {$expr} and $var, never short-echo tags.
        // Those used to sit right in the markup below and were never
        // executing: the tags were being sent to the browser as literal
        // text, which is why the live page rendered with no styling at all.
        // Computing each piece into a variable first, then interpolating
        // the variable, is what actually runs the PHP before it reaches
        // the heredoc.
        $fonts = self::chromeFonts();
        $css = Chrome::css();
        $mark = Chrome::mark(40, '', 'mark-logo');
        echo <<<HTML
<!doctype html>
<html lang="en"><head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Coming soon</title>
{$fonts}<style>{$css}</style>
</head><body>
  <main class="card">
    {$mark}
    <h1>We're setting things up</h1>
    <p>This site isn't ready yet. Please check back shortly.</p>
  </main>
</body></html>
HTML;
        exit;
    }
}
