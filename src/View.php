<?php
declare(strict_types=1);

namespace SignalMasterAi;

/**
 * Shared chrome for public pages.
 *
 * Every page previously repeated sixty lines of head boilerplate - meta tags,
 * favicons, Open Graph, the accent-colour style block - and the navigation
 * markup and its toggle script were copy-pasted between index.php and
 * charts.php. That is how the two drifted apart. New pages render their shell
 * from here so there is one place to change it.
 */
class View
{
    /**
     * The palette's own values for the three settings an operator can repaint.
     *
     * One constant, referenced everywhere the defaults are needed - the head,
     * the installer seed, the settings form and its sanitiser. They were four
     * separate literals, which is how the stylesheet ended up on one palette
     * and every PHP default on the previous one.
     */
    public const BRAND_DEFAULTS = [
        'accent_color' => '#6E7BFF',
        'up_color'     => '#2ED3A0',
        'down_color'   => '#FF6A5F',
    ];

    /** The values these settings shipped with before the Instrument palette. */
    public const BRAND_LEGACY = [
        'accent_color' => '#6E7BFF',
        'up_color'     => '#2ED3A0',
        'down_color'   => '#FF6A5F',
    ];

    /**
     * Document head plus the opening body tag.
     *
     * $opts:
     *   noindex  - emit a robots noindex tag
     *   raw_title - use $title verbatim instead of appending the site name
     *   keywords - meta keywords
     *   canonical - absolute URL for <link rel=canonical> and og:url. Leave it
     *               out and one is worked out from the request, which is right
     *               for every page whose content does not depend on the query
     *               string. Pass one on the pages where it does.
     *   query    - query parameters that are part of the page's identity, when
     *               the default canonical is used (charts.php?symbol=&tf=)
     *   style    - page-specific CSS appended after the token block
     *   head     - any additional raw head markup
     */

    /**
     * The one address this page should be indexed under.
     *
     * This site is deliberately reachable at two spellings of every URL:
     * .htaccess maps /charts to /charts.php and does NOT redirect between
     * them, because a 301 reaches the endpoints too and a payment gateway
     * POSTing to payment_webhook.php does not survive one. That decision is
     * right and it has a cost - every page has at least two addresses, four
     * with and without www - and a canonical tag is what pays it. Without one
     * a search engine sees a set of duplicates and picks a winner itself,
     * splitting whatever the page has earned across the copies.
     *
     * Built from the request rather than from site_url, so it stays correct on
     * an install that has moved or that has never had its URL set - but the
     * HOST comes from the canonical_host setting when the operator has chosen
     * one, because that is precisely the choice being recorded there.
     *
     * The spelling follows the Clean URLs setting, so the canonical matches
     * the links the site actually writes. A canonical pointing somewhere the
     * site never links to is a canonical nobody believes.
     */
    public static function canonicalUrl(array $query = []): string
    {
        $base = Request::baseUrl();
        $host = (string)Database::setting('canonical_host', 'both');
        if ($host === 'www' || $host === 'nonwww') {
            $base = preg_replace_callback('#^(https?://)([^/]+)#', static function ($m) use ($host) {
                $h = preg_replace('/^www\./i', '', $m[2]);
                return $m[1] . ($host === 'www' ? 'www.' . $h : $h);
            }, $base) ?? $base;
        }
        $path = (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        $file = basename($path);
        // The home page is the bare directory, never "/index.php" and never
        // "/index" - three spellings of one page is exactly the problem here.
        if ($file === '' || $file === 'index.php' || $file === 'index') {
            $url = $base . '/';
        } else {
            $name = preg_replace('/\.php$/', '', $file);
            $url = $base . '/' . $name . (self::cleanUrls() ? '' : '.php');
        }
        if ($query) {
            ksort($query);          // one order, so two orderings are one URL
            $url .= '?' . http_build_query($query);
        }
        return $url;
    }
    /**
     * Are extensionless URLs actually being served on this host?
     *
     * The setting alone is not the answer and using it alone was doing real
     * damage. bootstrap only rewrites the links in the page body when the
     * self-test has NOT come back negative - but the canonical tag and the
     * sitemap read the raw setting, so on a host without mod_rewrite the body
     * correctly linked to /charts.php while the canonical and the sitemap
     * published /charts. Search Console then reports exactly what it was
     * given: "Not found (404)" for the sitemap entries, and "Duplicate, Google
     * chose different canonical than user" for pages whose declared canonical
     * cannot be fetched at all.
     *
     * Same condition as bootstrap's, in one place both can call: untested
     * stays on, only a test that came back negative turns it off.
     */
    public static function cleanUrls(): bool
    {
        return Database::setting('clean_urls', '1') === '1'
            && Cache::get('rewrite_ok') !== false;
    }

    /**
     * The absolute base URL of this request - scheme, host, and the directory
     * the app is installed in. Used for canonical tags and og: URLs.
     *
     * Both public pages built this by hand from the same three superglobals.
     * It is only three lines, which is exactly why it gets copied, and exactly
     * why the two would eventually stop agreeing.
     */
    public static function baseUrl(): string
    {
        return Request::baseUrl();
    }

    /**
     * The base every INDEXABLE url must be built from - canonical tags and
     * the sitemap alike.
     *
     * They were built from different things: canonicalUrl() derives the host
     * from the request and applies canonical_host, while the sitemap read the
     * stored site_url. Those agree right up until they do not - an install set
     * up before its certificate has http:// in site_url and serves https, one
     * that chose non-www has www in site_url - and then every address in the
     * sitemap differs from the canonical of the page it points at. Google
     * reports that as "Duplicate, Google chose different canonical than user":
     * it was handed two addresses for one page by the same site and had to
     * pick, and whichever it picks, the other is wasted.
     *
     * site_url is still the right answer for an EMAIL, where there is no
     * request to derive a host from. It is the wrong answer here, where there
     * always is one - so the sitemap prefers the request and only falls back
     * to the stored value when generated from the command line.
     */
    public static function indexBase(): string
    {
        $base = (PHP_SAPI === 'cli' || empty($_SERVER['HTTP_HOST']))
            ? (string)Database::setting('site_url')
            : Request::baseUrl();
        $base = rtrim($base ?: Request::baseUrl(), '/');
        $host = (string)Database::setting('canonical_host', 'both');
        if ($host === 'www' || $host === 'nonwww') {
            $base = preg_replace_callback('#^(https?://)([^/]+)#', static function ($m) use ($host) {
                $h = preg_replace('/^www\./i', '', $m[2]);
                return $m[1] . ($host === 'www' ? 'www.' . $h : $h);
            }, $base) ?? $base;
        }
        return $base;
    }

    /**
     * Learn the site's own URL from the first request that arrives.
     *
     * Alert emails, the VAPID contact claim and payment return URLs all need
     * an absolute address, and asking an operator to type one during install
     * is a step people skip and then wonder why their links are broken. The
     * first visit supplies it for free.
     *
     * This lived twice, once in index.php and once in charts.php, and had
     * already drifted: one stripped "www." from the contact address and the
     * other did not, so which page a visitor happened to land on first decided
     * what went into the push subscription. That is the whole argument against
     * a copied block - it does not stay a copy.
     */
    public static function learnSiteUrl(): void
    {
        if (Database::setting('site_url') !== '' || empty($_SERVER['HTTP_HOST'])) {
            return;
        }
        $host = preg_replace('/[^A-Za-z0-9.\-:\[\]]/', '', explode(':', (string)$_SERVER['HTTP_HOST'])[0]);
        if ((string)$host === '') {
            return;
        }
        $path = rtrim(dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
        Database::setSetting('site_url', Request::scheme() . '://' . $host . $path);
        if (Database::setting('vapid_subject') === '') {
            // Without the www: it is a contact address, not a hostname, and
            // "admin@www.example.com" is rarely a mailbox that exists.
            Database::setSetting('vapid_subject',
                'mailto:admin@' . preg_replace('/^www\./i', '', (string)$host));
        }
    }

    public static function head(string $title, string $description = '', array $opts = []): void
    {
        $siteName = Database::setting('site_name', 'SignalMasterAi');
        $hex = static function (string $k, string $d): string {
            $v = Database::setting($k, $d);
            return preg_match('/^#[0-9a-f]{6}$/i', $v) ? $v : $d;
        };
        // THE COLOUR PICKER MUST NOT OUTRANK THE DESIGN SYSTEM.
        //
        // The block this feeds is written after style.css, so whatever it
        // names wins. It named three variables and left the rest, which on a
        // fresh install put GitHub blue back over the Instrument accent while
        // --accent-soft - the hover tint derived from that accent - stayed
        // indigo. Every button on the public site was one palette with a
        // hover state from another, and the stylesheet looked broken when the
        // stylesheet was right.
        //
        // Two changes. The defaults ARE the design tokens, so an install that
        // never touched the picker emits nothing and the stylesheet is left
        // alone. And when an operator has genuinely picked a colour, the tint
        // that pairs with it is derived from the pick instead of being left
        // behind at whatever the palette shipped.
        $accent = $hex('accent_color', self::BRAND_DEFAULTS['accent_color']);
        $up = $hex('up_color', self::BRAND_DEFAULTS['up_color']);
        $down = $hex('down_color', self::BRAND_DEFAULTS['down_color']);
        $brandVars = [];
        if (strcasecmp($accent, self::BRAND_DEFAULTS['accent_color']) !== 0) {
            $brandVars[] = '--accent:' . $accent;
            $brandVars[] = '--accent-soft:color-mix(in srgb,' . $accent . ' 14%,transparent)';
        }
        if (strcasecmp($up, self::BRAND_DEFAULTS['up_color']) !== 0) {
            $brandVars[] = '--up:' . $up;
        }
        if (strcasecmp($down, self::BRAND_DEFAULTS['down_color']) !== 0) {
            $brandVars[] = '--down:' . $down;
        }
        $brandCss = $brandVars === [] ? '' : ':root{' . implode(';', $brandVars) . '}';

        $base = Request::baseUrl();
        $custom = Database::setting('custom_logo');
        ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= sma_e(!empty($opts['raw_title']) ? $title : $title . ' — ' . $siteName) ?></title>
<meta name="description" content="<?= sma_e($description) ?>">
<?php if (!empty($opts['keywords'])): ?>
<meta name="keywords" content="<?= sma_e((string)$opts['keywords']) ?>">
<?php endif; ?>
<?php if ($custom !== ''): ?>
<link rel="icon" href="<?= sma_e(sma_logo()) ?>">
<link rel="apple-touch-icon" href="<?= sma_e(sma_logo()) ?>">
<?php else: ?>
<link rel="icon" type="image/svg+xml" href="assets/brand/logo.svg">
<link rel="icon" type="image/png" sizes="32x32" href="assets/brand/favicon-32.png">
<link rel="apple-touch-icon" href="assets/brand/apple-touch-icon.png">
<?php endif; ?>
<link rel="manifest" href="site.webmanifest">
<meta name="theme-color" content="#07090E">
<?php
$canon = (string)($opts['canonical'] ?? self::canonicalUrl((array)($opts['query'] ?? [])));
// og:url and the canonical are the same claim; they were allowed to disagree
// before, and on charts.php they did - it declared the HOME PAGE as its
// og:url, which tells every crawler and every share preview that the chart
// page is the front page.
?>
<link rel="canonical" href="<?= sma_e($canon) ?>">
<meta property="og:type" content="website">
<meta property="og:locale" content="en_US">
<meta property="og:site_name" content="<?= sma_e($siteName) ?>">
<meta property="og:title" content="<?= sma_e($title) ?>">
<meta property="og:description" content="<?= sma_e($description) ?>">
<meta property="og:url" content="<?= sma_e($canon) ?>">
<meta property="og:image" content="<?= sma_e($base) ?>/assets/brand/og-image.png">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="<?= sma_e($siteName) ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= sma_e($title) ?>">
<meta name="twitter:description" content="<?= sma_e($description) ?>">
<meta name="twitter:image" content="<?= sma_e($base) ?>/assets/brand/og-image.png">
<?php if (!empty($opts['noindex'])): ?>
<meta name="robots" content="noindex, follow">
<?php else: ?>
<?php // Told once, in the markup, rather than left for a crawler to infer:
      // what this site is, and what it is called. Nothing invented - the name,
      // the address and the logo an operator has already configured. ?>
<script<?= sma_nonce() ?> type="application/ld+json"><?= json_encode(array_filter([
    '@context' => 'https://schema.org',
    '@graph' => [
        array_filter([
            '@type' => 'Organization',
            '@id'   => $base . '/#org',
            'name'  => $siteName,
            'url'   => $base . '/',
            'logo'  => $custom !== '' ? $base . '/' . ltrim(sma_logo(), '/') : $base . '/assets/brand/logo.svg',
            'description' => Database::setting('site_tagline') ?: null,
        ]),
        [
            '@type'     => 'WebSite',
            '@id'       => $base . '/#site',
            'name'      => $siteName,
            'url'       => $base . '/',
            'publisher' => ['@id' => $base . '/#org'],
            'inLanguage' => 'en',
        ],
    ],
]), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
<?php endif; ?>
<?php // THE TYPE THE STYLESHEET HAS BEEN ASKING FOR.
      //
      // style.css names Instrument Serif, Inter Tight and JetBrains Mono and
      // nothing ever loaded them, so every install rendered the fallback
      // chain: a Palatino-class serif for display, the platform UI face for
      // body, the platform mono for data. Legible, and not the design - the
      // tabular figures that stop live prices jittering come from the mono.
      //
      // Behind a setting because this app deliberately depends on no external
      // service anywhere else (see the keyless market-data API in the README),
      // and an operator on an air-gapped box should not have two requests
      // hanging on fonts.gstatic.com. Turning it off is safe: the fallback
      // chain was chosen so the site is correct without a webfont at all.
      //
      // display=swap so text paints in the fallback immediately and swaps -
      // a blocking font request in the head is a blank page on a slow phone.
      if (Database::setting('webfonts', '1') === '1'): ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital,wght@0,400;1,400&amp;family=Inter+Tight:wght@400;500&amp;family=JetBrains+Mono:wght@400;500;700&amp;display=swap">
<?php endif; ?>
<link rel="stylesheet" href="<?= sma_asset('assets/style.css') ?>">
<style><?= $brandCss ?>
<?= $opts['style'] ?? '' ?></style>
<?= $opts['head'] ?? '' ?>
<script<?= sma_nonce() ?>>
// Applied before first paint so the chosen theme never flashes the wrong one.
(function () {
  try {
    var t = localStorage.getItem('sma_theme');
    if (t) document.documentElement.setAttribute('data-theme', t);
    if (localStorage.getItem('sma_calm') === '1') document.documentElement.setAttribute('data-calm', '1');
  } catch (e) {}
})();
</script>
</head>
<body>
<?php self::promoBanner(); ?>
<?php self::upsell(); ?>
        <?php
    }

    /**
     * The campaign strip across the top of the page.
     *
     * Separate from the popup and independently switchable, because they do
     * different jobs: the strip is a reminder somebody can ignore all week,
     * the popup interrupts once. An operator running a quiet evergreen
     * discount wants the first and not the second.
     *
     * Once per request, like the popup - head() and footer() are not used by
     * the same pages, and two banners on one page is worse than none.
     */
    private static function promoBanner(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        $p = Promo::forViewer(MemberAuth::current());
        if ($p === null || !$p['banner']) {
            return;
        }
        ?>
<div class="promo-bar" id="promoBar" data-code="<?= sma_e($p['code']) ?>"
     <?= $p['timer'] ? 'data-ends="' . (int)$p['ends'] . '"' : '' ?>>
  <a class="promo-bar-in" href="upgrade.php">
    <strong><?= sma_e($p['headline']) ?></strong>
    <span class="promo-code">code <?= sma_e($p['code']) ?></span>
    <?php if ($p['timer']): ?>
      <?php // Server-rendered text first, replaced by the ticking clock.
            // Without it the strip reads "ends in" and nothing at all for the
            // moment before the script runs - and forever if it never does. ?>
      <span class="promo-left" id="promoLeft">ends in <?= sma_e(Promo::remaining($p['ends'])) ?></span>
    <?php endif; ?>
  </a>
  <button type="button" class="promo-x" id="promoBarX" aria-label="Hide this offer">&times;</button>
</div>
        <?php
    }

    /** The campaign as an interrupting panel. */
    private static function promoPopup(array $p): void
    {
        ?>
<div class="upsell-wrap promo-wrap" id="upsellWrap" role="dialog" aria-modal="true"
     aria-labelledby="upsellTitle" data-promo="1"
     <?= $p['timer'] ? 'data-ends="' . (int)$p['ends'] . '"' : '' ?> hidden>
  <div class="upsell-card">
    <button type="button" class="upsell-x" id="upsellClose" aria-label="Close">&times;</button>
    <div class="promo-badge"><?= (int)$p['percent'] ?>% OFF</div>
    <h2 id="upsellTitle"><?= sma_e($p['headline']) ?></h2>
    <p class="upsell-sub"><?= sma_e($p['body']) ?></p>
    <?php if ($p['timer']): ?>
      <div class="promo-clock" id="promoClock" data-ends="<?= (int)$p['ends'] ?>">
        <?= sma_e(Promo::remaining($p['ends'])) ?> left</div>
    <?php endif; ?>
    <div class="promo-code-box">Use code <strong><?= sma_e($p['code']) ?></strong> at checkout</div>
    <?php // What the discount is ON, not just that there is one. A percentage
          // with nothing attached to it is a number; the benefit list is the
          // reason to care, and it is the same derived list the upgrade page
          // shows so the two cannot contradict each other. ?>
    <?php $bens = array_slice(Premium::benefits(), 0, 3); ?>
    <?php if ($bens): ?>
      <ul class="upsell-list">
        <?php foreach ($bens as $b): ?>
          <li><span class="upsell-ic"><?= $b['icon'] ?></span>
            <span><strong><?= sma_e($b['title']) ?></strong></span></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
    <a class="btn upsell-go" href="upgrade.php">Claim <?= (int)$p['percent'] ?>% off</a>
    <button type="button" class="upsell-later" id="upsellLater">No thanks</button>
  </div>
</div>
        <?php
    }

    /**
     * The brand mark: an operator's uploaded logo if they set one, otherwise
     * the live three-bar instrument mark (not the static gradient image in
     * assets/brand/logo.svg - a still logo mark next to a live-price product
     * reads as decoration, and this one reads as instrument).
     *
     * One place, called from the topbar AND the account page. The account
     * page used to call sma_logo() directly for its own repeat of the mark
     * inside the login/register card, which quietly skipped this and always
     * showed the static image - so a visitor saw one brand mark in the header
     * and a different one four inches below it on the same screen.
     */
    public static function brandMark(int $size = 40): void
    {
        if (Database::setting('custom_logo') !== '') {
            echo '<img class="logo-img" src="' . sma_e(sma_logo()) . '" alt="" width="' . $size . '" height="' . $size . '">';
            return;
        }
        ?>
        <svg class="logo-mark" viewBox="0 0 24 24" width="<?= $size ?>" height="<?= $size ?>" aria-hidden="true">
          <rect x="2" y="9" width="4" height="10" rx="1.5" fill="var(--indigo, var(--accent))"/>
          <rect x="10" y="4" width="4" height="16" rx="1.5" fill="var(--long, var(--up))"/>
          <rect x="18" y="12" width="4" height="8" rx="1.5" fill="var(--brass)"/>
        </svg>
        <?php
    }

    /** Site header with the responsive navigation. */
    public static function topbar(string $current = ''): void
    {
        $siteName = Database::setting('site_name', 'SignalMasterAi');
        $tagline  = Database::setting('site_tagline', '');
        $member   = MemberAuth::current();
        // A site with no plans on sale must not advertise a price list. One
        // query, cached with every other setting read on the page.
        $plansOnSale = (int)Database::pdo()
            ->query('SELECT COUNT(*) FROM plans WHERE enabled = 1')->fetchColumn() > 0;
        // Destinations first, always in the same order, so the menu does not
        // rearrange itself as the reader moves between pages. Every page that
        // exists and is enabled is reachable from every other page - four
        // pages used to hand-roll this block and between them Scanner,
        // Portfolio, Backtest and the track record were reachable from
        // nowhere at all.
        $links = [];
        // index.php bounces signed-in members straight to the chart, so for
        // them a "Home" link is a hop that lands somewhere else than it says.
        // Guests get it; members reach the landing page via the logo if they
        // ever want it.
        if (Database::setting('landing_enabled', '1') === '1' && !$member) {
            $links[] = ['index.php', 'Home', 'home'];
        }
        // A gated destination stays in the menu, with a padlock.
        //
        // Hiding it is the obvious move and the wrong one twice over: a free
        // member cannot want what they cannot see, so hiding is the surest
        // way to sell nothing - and a member who reads about the scanner and
        // then cannot find it anywhere concludes the site is broken rather
        // than that it is paid. Shown, marked, and honest about which side of
        // the line it is on.
        $lock = static fn(string $k): bool => !Gate::allows($k);
        // WHAT "LIVE SIGNALS" OPENS.
        //
        // It used to open the chart, which is one coin on one timeframe -
        // whichever the reader happened to be on. That is not what the words
        // promise. Somebody clicking "Live signals" is asking WHERE IS THERE A
        // SETUP RIGHT NOW, and the board that answers it is the scanner: every
        // watched coin ranked, with its grade and plan. The chart answers the
        // next question, about one coin, and gets its own entry.
        //
        // ORDER MATTERS AND SO DOES THE GATE. The scanner ships premium-only
        // (gate_scanner, default 'paid'), so on a default install the first
        // item in the menu now carries a padlock for a free member. That is
        // deliberate and it is the operator's dial, not this file's: leaving
        // it locked sells the scanner from the most-read spot on the site,
        // and an operator who would rather it were open sets gate_scanner to
        // free under Settings > Members. The chart below it is never gated,
        // so a free member always has a working destination either way.
        // ONE CHART, AND IT IS THE ENGINE'S.
        //
        // There were two: the engine's read of a coin, and a manual workspace
        // where a member drew their own levels and set their own stop. The
        // manual one has been removed - the site is a signal service, and a
        // second chart that published nothing was a drawing program bolted to
        // the side of it, with its own storage, its own API and its own half
        // of every conditional in the front end.
        //
        // ONE DOOR TO THE ENGINE, NOT TWO.
        //
        // The menu carried "Live signals" pointing at the scanner page and the
        // chart had a Scanner button that opened the same board in a sheet -
        // two routes to one thing, and the entry was named for the product
        // rather than for what it opens.
        //
        // It is "Signals" now and it opens the chart: the engine's read of the
        // coin in front of you, with the whole board a tap away in the sheet
        // over it. The scanner page still serves its URL - old links, bookmarks
        // and the sitemap keep working - it is simply no longer a second entry
        // in the menu competing with the first.
        //
        // Not gated here either. The chart is free and the sheet does its own
        // gating, so a free member gets a working destination and meets the
        // paywall at the thing that is actually paid for.
        $links[] = ['charts.php', 'Signals', 'charts'];
        if (Database::setting('paper_enabled', '1') === '1' && $member) {
            $links[] = ['portfolio.php', 'Portfolio', 'portfolio', $lock('portfolio')];
        }
        if (Database::setting('member_backtest_enabled', '1') === '1') {
            $links[] = ['backtest.php', 'Backtest', 'backtest', $lock('backtest')];
        }
        if (Database::setting('performance_page_enabled', '1') === '1') {
            $links[] = ['performance.php', 'Track record', 'performance'];
        }
        // PRICING, FOR THE PEOPLE WHO HAVE NOT PAID.
        //
        // The upgrade page used to bounce a signed-out visitor to the register
        // form, so there was nothing to link to and the nav did not link to it.
        // It answers a stranger's questions now, and the commonest question a
        // visitor has about a paid product - what does it cost - had no answer
        // anywhere in the navigation.
        //
        // Not shown to a member on an active plan: they have already bought
        // it, and a "Pricing" tab on every page after that is a bill in the
        // post. Their upgrade link lives in the account menu, where renewals
        // belong.
        if ($plansOnSale && ($member === null || MemberAuth::tier() !== 'paid')) {
            $links[] = ['upgrade.php', 'Pricing', 'upgrade'];
        }
        ?>
<?php // THE ANIMATED MARK, RESTORED.
      //
      // Three bars, one per accent the palette actually uses - indigo,
      // mint, brass - pulsing out of phase like the meters on the thing
      // this product watches. This is what the brand looked like in the
      // reference designs before it got replaced along the way by a static
      // image; put back because a still logo mark next to a live-price
      // page reads as decoration, and this one reads as instrument.
      //
      // Only the default. An operator who has uploaded their own logo made
      // a deliberate choice that has to win - the mark is not the site's
      // to insist on once someone has replaced it with their own. ?>
<header class="topbar">
  <div class="brand">
    <a href="index.php" class="brand-link">
      <?php self::brandMark(40); ?>
      <div><strong><?= sma_e($siteName) ?></strong><small><?= sma_e($tagline) ?></small></div>
    </a>
  </div>
  <?php // THE TAB BAR'S OWN LIST, HOISTED HERE.
        //
        // This used to run after </nav>, computed only for the bottom bar.
        // The nav loop just below needs the identical four keys - to know
        // which links the bar already carries, so the mobile account panel
        // does not show them a second time - and a second independent slice
        // of the same array is exactly how two things that must always
        // agree quietly stop agreeing. One computation; both consumers read
        // it. Four maximum: five is where labels start truncating at
        // 360px, and a truncated label is worse than an absent one.
        $tabs = array_values(array_filter($links, static fn($l) => $l[2] !== 'home'));
        $tabs = array_slice($tabs, 0, 4);
        $tabKeys = array_column($tabs, 2); ?>
  <?php // NAV COMES BEFORE THE UTILITY BUTTONS NOW, NOT AFTER.
        //
        // Nothing here changed for a phone: below 720px this whole element is
        // either display:none or a full-width dropdown positioned against the
        // bar itself (see the CSS), so where it sits in the markup was always
        // invisible on that layout. It matters now that the account links
        // inside it (below) collapse behind .nav-toggle on desktop too - the
        // dropdown they open is positioned against the RIGHT edge of this bar,
        // and that edge is wherever the last child happens to be. Moving nav
        // ahead of the toggle buttons makes the icon that opens it the last
        // thing on the bar, so the two align without hard-coding a pixel
        // offset that would drift the moment a link is added or removed. ?>
  <nav class="top-nav" id="topNav">
    <?php foreach ($links as $l): ?>
      <?php [$href, $label, $key] = $l; $locked = $l[3] ?? false;
            // nav-tabbed: also reachable from the bottom bar on a phone.
            // desktop shows it regardless (see the CSS); the mobile panel
            // hides it there and shows only what the bar had no room for. ?>
      <a href="<?= sma_e($href) ?>"
         class="admin-link<?= $key === $current ? ' on' : '' ?><?= $locked ? ' locked' : '' ?><?= (in_array($key, $tabKeys, true) || $key === 'home') ? ' nav-tabbed' : '' ?>"
         <?= $key === $current ? 'aria-current="page"' : '' ?>><?= sma_e($label) ?><?php
         // The padlock is a span rather than text in the label so a screen
         // reader gets the word, not a character it has to guess at.
         if ($locked): ?><span class="nav-lock" title="Premium feature" aria-hidden="true"></span><span class="sr-only"> - premium feature</span><?php endif; ?></a>
    <?php endforeach; ?>
    <?php // TWO KINDS OF ACCOUNT LINK: DIRECT, AND BEHIND THE ICON.
          //
          // A signed-out visitor's one job here is to sign up, so Log in and
          // Register render directly - unwrapped, same as a nav-tabbed link -
          // which makes them always visible on the bar at desktop widths (see
          // .top-nav's base rule) and, on a phone, hidden until the icon opens
          // the panel exactly as they always have been (visibility there comes
          // from .top-nav itself, not from any wrapper). A signed-in member
          // gets the same direct treatment for Go premium - the one thing this
          // bar wants every non-paying member to notice - but their own
          // account and Log out are lower-traffic, so THAT pair is wrapped in
          // .nav-account, which desktop hides by default and drops in as a
          // small menu under the icon on click; nothing to wrap for a guest,
          // since nothing of theirs is left to hide. ?>
    <span class="nav-sep" aria-hidden="true"></span>
    <?php if ($member): ?>
      <?php if ($member['tier'] !== 'paid'): ?>
        <a href="upgrade.php" class="admin-link accent">★ Go premium</a>
      <?php endif; ?>
      <div class="nav-account">
        <a href="account.php" class="admin-link member-link"><?= $member['tier'] === 'paid' ? '★ ' : '👤 ' ?><?= sma_e($member['email']) ?></a>
        <?php // Log out belongs in the menu itself. On a phone the nav collapses
              // to ☰, and burying the only way out one level deeper inside the
              // account page is a step nobody should have to guess at. ?>
        <a href="account.php?logout=1" class="admin-link">Log out</a>
      </div>
    <?php else: ?>
      <a href="account.php" class="admin-link">Log in</a>
      <?php if (Database::setting('registration_enabled', '1') === '1'): ?>
        <a href="account.php?tab=register" class="admin-link accent">Register free</a>
      <?php endif; ?>
    <?php endif; ?>
  </nav>
  <?php // OUT OF THE MENU AND ONTO THE BAR.
        //
        // The theme switch lived inside the collapsed navigation, so changing
        // the site from dark to light meant opening a menu, hunting past every
        // destination, and finding a single symbol among them. It is not a
        // destination - it changes the page you are already looking at, and a
        // control like that belongs where you can see it.
        //
        // Before the Menu button rather than after, so the two sit in reading
        // order and the tap targets do not fight: Menu stays the last thing on
        // the bar, which is where a thumb expects it. ?>
  <button type="button" class="theme-toggle theme-toggle-bar" id="themeToggle"
          aria-label="Switch colour theme"
          title="Light / dark theme (shift-click for calm mode)">&#9680;</button>
  <?php // A PROFILE ICON, NOT A HAMBURGER - THE SAME ONE AT EVERY WIDTH.
        //
        // This button used to be the only navigation visible on a phone -
        // three bars and the word "Menu" - because it was the only way to
        // reach Signals, Portfolio, Backtest or Track record. The tab bar
        // (now shown at every width - see .tabbar) does that job with all
        // four destinations one tap away, so a hamburger sitting right
        // beside it was asking a reader to open a second menu to find
        // things the bar under their thumb already shows.
        //
        // Shown at every width, same as the direct Log in / Register /
        // Go premium buttons beside it - the icon is not standing in for
        // those any more, it sits alongside them. What is still genuinely
        // BEHIND it, on click, differs by who is looking: for a guest there
        // is nothing left to hide (Log in and Register are already on the
        // bar), so the click has nothing to open; for a signed-in member it
        // opens the one thing that IS still hidden - their own account, and
        // logging out.
        //
        // Still the same trigger: same id, same aria-controls, same panel.
        // A signed-in premium member gets a brass ring - earned status, the
        // one place this bar uses that colour. ?>
  <button type="button" class="nav-toggle<?= $member ? ' has-account' : '' ?><?= $member && $member['tier'] === 'paid' ? ' is-paid' : '' ?>"
          id="navToggle" aria-controls="topNav" aria-expanded="false"
          aria-label="<?= $member ? 'Account' : 'Log in or register' ?>">
    <svg class="nav-toggle-ic" viewBox="0 0 20 20" fill="none" stroke="currentColor"
         stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <circle cx="10" cy="7" r="3.4"/>
      <path d="M3.5 17c1-3.6 4-5.4 6.5-5.4S15.5 13.4 16.5 17"/>
    </svg>
  </button>
</header>

<?php // ================= THE TAB BAR =================
      //
      // On a phone every destination on this site was behind a Menu button:
      // tap Menu, read a list, tap again. That is two taps and a hunt for the
      // thing people came to do, and it is why the site reads the same on
      // every screen - the only navigation visible anywhere is a hamburger.
      //
      // A bottom bar puts the four real destinations under the thumb, one tap
      // each, with the current one lit. It is built from the SAME $links
      // array, so it cannot drift from the menu and it inherits the padlocks
      // and the gate checks already computed above. Nothing new is queried.
      //
      // Bottom rather than top because the top of a 6-inch phone is the part
      // a thumb cannot reach, and this is the control used most.
      //
      // $tabs is already computed above, before the nav - see that comment
      // for why four is the ceiling. Everything past the fourth surfaces in
      // the account panel's overflow group instead of vanishing.
      $icons = [
        'charts'      => '<path d="M2 12l3.5-4L8 10l5.5-6"/>',
        'portfolio'   => '<path d="M2 13V6M6 13V3M10 13V8M14 13v-4"/>',
        'backtest'    => '<circle cx="8" cy="8" r="6"/><path d="M8 4.5V8l2.5 1.5"/>',
        'performance' => '<path d="M2 13h12M4 11V7M7.5 11V4M11 11V8"/>',
        'upgrade'     => '<path d="M8 2l1.8 3.9 4.2.5-3.1 2.9.8 4.2L8 11.5 4.3 13.5l.8-4.2L2 6.4l4.2-.5z"/>',
      ];
      if (count($tabs) > 1): ?>
<nav class="tabbar" aria-label="Main">
  <?php foreach ($tabs as $t): [$href, $label, $key] = $t; $locked = $t[3] ?? false; ?>
    <a href="<?= sma_e($href) ?>" class="tab<?= $key === $current ? ' on' : '' ?>"
       <?= $key === $current ? 'aria-current="page"' : '' ?>>
      <svg class="tab-ic" viewBox="0 0 16 16" fill="none" stroke="currentColor"
           stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <?= $icons[$key] ?? '<circle cx="8" cy="8" r="5"/>' ?>
      </svg>
      <span><?= sma_e($label) ?></span>
      <?php if ($locked): ?><i class="tab-lock" aria-hidden="true"></i><span class="sr-only"> - premium feature</span><?php endif; ?>
    </a>
  <?php endforeach; ?>
</nav>
<?php endif; ?>
        <?php
    }

    /** Footer plus the shared scripts. */
    public static function footer(): void
    {
        $siteName = Database::setting('site_name', 'SignalMasterAi');
        ?>
<footer class="footer">
  <p>
    <?php if (Database::setting('performance_page_enabled', '1') === '1'): ?>
      <a href="performance.php">Track record</a> &middot;
    <?php endif; ?>
    <a href="status.php">Status</a> &middot;
    <a href="page.php?p=terms">Terms</a> &middot;
    <a href="page.php?p=privacy">Privacy</a> &middot;
    <a href="page.php?p=risk">Risk disclosure</a>
  </p>
  <?php // One risk line per page, from a setting the operator controls. It
        // used to be said here AND in the signal card AND in the alert AND in
        // the meta description; five statements of the same thing do not add
        // legal weight, they read as a site that does not believe in itself. ?>
  <p><?= sma_e(Database::setting('site_notice')) ?></p>
</footer>
<?php // Also here, for the five pages that close with footer() but open with
      // their own head. upsell() renders once per request whichever fires
      // first, so a page calling both gets one dialog. ?>
<?php self::upsell(); ?>
<script src="<?= sma_asset('assets/ui.js') ?>" defer></script>
</body>
</html>
        <?php
    }

    /**
     * The upgrade prompt, for free members only and only when it is due.
     *
     * Rendered in the footer so it reaches every page that uses the shared
     * shell rather than being bolted onto one of them, and rendered as plain
     * markup that the script then shows: a dialog built by JavaScript is a
     * dialog that does not exist for a crawler, a reader-mode, or anyone
     * whose script failed - and the last of those is the case where an
     * unclosable half-rendered overlay would be worst.
     *
     * Everything it claims comes from Premium::benefits(), so it cannot
     * advertise a feature this site has switched off.
     */
    private static function upsell(): void
    {
        // Once per request. head() and footer() are not used by the same set
        // of pages - five pages close with footer() and never call head(),
        // and the busiest pages call head() and never call footer() - so it
        // is emitted from both and guarded here. Rendering it from only one
        // of them is how it came to be missing from the track record and the
        // chart, which are the two pages a free member actually reads.
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        $member = MemberAuth::current();

        // A live discount outranks the plain "here is what Premium adds".
        //
        // Both are the same pitch and the promo is the stronger version of
        // it, so showing both would be two overlays for one message - and
        // stacking them is how a site stops being read at all. The promo
        // banner is separate and can sit alongside anything.
        $promo = Promo::popupFor($member);
        if ($promo !== null) {
            self::promoPopup($promo);
            return;
        }
        $why = Premium::promptFor($member);
        if ($why === '') {
            return;
        }
        $benefits = Premium::benefits();
        $days = max(0, (int)Database::setting('upsell_popup_days', '3'));
        ?>
<div class="upsell-wrap" id="upsellWrap" role="dialog" aria-modal="true"
     aria-labelledby="upsellTitle" data-why="<?= sma_e($why) ?>" hidden>
  <div class="upsell-card">
    <button type="button" class="upsell-x" id="upsellClose" aria-label="Close">&times;</button>
    <h2 id="upsellTitle"><?= $why === 'new'
        ? 'Welcome &mdash; here is what Premium adds'
        : 'You have been with us ' . ($days === 1 ? 'a day' : $days . ' days') ?></h2>
    <p class="upsell-sub"><?= $why === 'new'
        ? 'Your free account works fully. This is simply what the paid tier gives you, so you know.'
        : 'Your free account keeps working exactly as it does now. This is what the paid tier adds.' ?></p>
    <ul class="upsell-list">
      <?php foreach ($benefits as $b): ?>
        <li><span class="upsell-ic"><?= $b['icon'] ?></span>
          <span><strong><?= sma_e($b['title']) ?></strong><br><?= sma_e($b['body']) ?></span></li>
      <?php endforeach; ?>
    </ul>
    <a class="btn upsell-go" href="upgrade.php">See the plans</a>
    <button type="button" class="upsell-later" id="upsellLater">Not now</button>
    <?php // Says what "not now" costs, because a dismissal control whose
          // consequence is hidden is a dark pattern in the other direction -
          // people click it warily instead of freely. ?>
    <p class="upsell-note">We will not ask again for
      <?= (int)Database::setting('upsell_popup_repeat', '14') ?> days.</p>
  </div>
</div>
        <?php
    }

    /**
     * The public label for how a trade ended: the target it reached, and
     * nothing after it.
     *
     * The engine stores the whole story - "TP2 reached, remainder stopped at
     * TP1", "TP1 reached, stopped at break-even" - because the admin signal
     * log needs to know what happened to the runner. On the public record
     * that tail is noise: a reader wants to know the trade got to its second
     * target, not the accounting of the last third of it. Worse, it split the
     * "How trades ended" table into a separate row per variation, so TP2
     * appeared three times and no row showed how often TP2 was actually hit.
     *
     * Everything before the first comma, which is exactly the headline the
     * notes were written to lead with. The stored value is untouched.
     */
    public static function shortOutcome(?string $note, string $outcome = ''): string
    {
        $n = trim((string)$note);
        if ($n === '') {
            return $outcome !== '' ? ucfirst($outcome) : '';
        }
        return trim(explode(',', $n)[0]);
    }

    /** Escaped price at a sensible precision for the magnitude involved. */
    /**
     * A price at the precision the instrument actually needs.
     *
     * The old floor of eight decimals held until a coin traded below it. At
     * 0.000000094 eight places round to 0.00000009 and anything under
     * 0.000000005 renders as a flat "0" - which is how a whole trade plan came
     * to read entry 0, stop 0, target 0.
     *
     * It also has to be used. PHP turns a float below about 0.0001 into
     * scientific notation the moment it is concatenated, so "Entry: " . price
     * produced "Entry: 4.91E-6" in alert emails, Telegram messages and
     * broadcast posts - a number nobody can paste into an order ticket, on
     * exactly the coins where the reader most needs to count the zeros.
     *
     * Accepts anything numeric rather than only ?float, because most callers
     * are reading a level straight out of decoded JSON, where it is a string.
     */
    public static function price($v): string
    {
        if ($v === null || $v === '' || !is_numeric($v)) {
            return '—';
        }
        $f = (float)$v;
        $out = rtrim(rtrim(number_format($f, SignalEngine::priceDecimals($f), '.', ','), '0'), '.');
        return $out === '' ? '0' : $out;
    }

    /** Human-readable age. */
    public static function ago(int $seconds): string
    {
        return Maintenance::ago($seconds);
    }

    /**
     * A length of time, not a point in the past. ago() reads "2h ago", which
     * is wrong for "held for" - a closed trade was not held two hours ago,
     * it was held FOR two hours. Two units so a day-and-a-half does not
     * round to one day.
     */
    public static function span(int $seconds): string
    {
        $seconds = max(0, $seconds);
        if ($seconds < 60) {
            return $seconds . 's';
        }
        foreach ([[86400, 'd', 3600, 'h'], [3600, 'h', 60, 'm'], [60, 'm', 1, 's']] as [$big, $bl, $small, $sl]) {
            if ($seconds >= $big) {
                $n = intdiv($seconds, $big);
                $rest = intdiv($seconds % $big, $small);
                return $n . $bl . ($rest > 0 ? ' ' . $rest . $sl : '');
            }
        }
        return $seconds . 's';
    }

    /**
     * The one-line context that belongs on every alert, from a stored
     * signal's decoded indicators.
     *
     * An alert used to carry a verdict, a price and a plan. Acting on that
     * meant not knowing two things the engine already had: whether the
     * timeframes above agreed, and when the setup stops being valid. The
     * second one matters most on the fast timeframes - a 5m setup is a
     * two-hour proposition, and a member reading the message six hours later
     * is looking at a trade that no longer exists.
     *
     * Returns [] when nothing is available, so alerts for signals stored
     * before these layers existed are unchanged rather than padded with
     * empty labels.
     */
    public static function signalContext(?array $indicators): array
    {
        if (!is_array($indicators)) {
            return [];
        }
        $out = [];

        $frames = (array)($indicators['mtf']['frames'] ?? []);
        if ($frames) {
            $bias = (float)($indicators['mtf_bias'] ?? 0);
            $word = $bias > 0.1 ? 'bullish' : ($bias < -0.1 ? 'bearish' : 'undecided');
            $out['Higher timeframes'] = implode(', ', array_keys($frames)) . ' — ' . $word;
        }

        $regime = (string)($indicators['regime']['name'] ?? '');
        if ($regime !== '' && $regime !== 'unknown') {
            $out['Market'] = strtolower(Regime::label($regime));
        }

        // A DEADLINE, NOT A DURATION.
        //
        // This said "Setup valid for 13 hours" - computed when the alert was
        // built, and printed in an email, a Telegram message and a push
        // notification that get read whenever they get read. Five hours later
        // it is off by five hours, and it is off in the direction that matters:
        // it tells somebody a finished setup has half a day left in it.
        //
        // The deadline is a fact that does not decay. The duration stays
        // beside it in brackets, because "until 04:12 UTC" alone makes a
        // reader do arithmetic to find out whether that is soon.
        $expiresAt = (int)($indicators['levels']['expires_at'] ?? 0);
        $expires = $indicators['levels']['expires_in'] ?? null;
        if ($expiresAt > 0) {
            $left = $expiresAt - time();
            $out['Setup valid until'] = gmdate('H:i', $expiresAt) . ' UTC'
                . ($left > 0
                    // span() already exists for "held for" on the track record and
                    // produces "18h 44m" - the same wording the chart's countdown
                    // uses. One definition, so a member reading the email and then
                    // opening the chart meets one vocabulary rather than two.
                    ? ' (' . self::span($left) . ' from now)'
                    : ' (its time stop has passed)');
        } elseif (is_string($expires) && $expires !== '') {
            // A signal stored before deadlines were recorded. Its duration is
            // all there is, so it is labelled as one.
            $out['Setup was valid for'] = $expires;
        }
        return $out;
    }
}
