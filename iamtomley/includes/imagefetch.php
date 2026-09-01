<?php
/**
 * Look up the "face" of a website from its URL — the Open Graph image, the
 * Twitter card image, the apple-touch-icon, the favicon — download it, and
 * keep a local copy in uploads/.
 *
 * Used by the admin when you paste a project or game URL: you get the site's
 * own logo on the card without hunting for an image file yourself.
 *
 * Everything here talks to a URL the admin typed, so it is written defensively:
 * only http/https, only public IP addresses (no poking at localhost or the
 * host's private network), a redirect limit, a response size limit and a short
 * timeout on every hop.
 */
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

const FETCH_TIMEOUT      = 8;                 // seconds per request
const FETCH_MAX_REDIRECT = 4;
const FETCH_MAX_HTML     = 768 * 1024;        // 768 KB of markup is plenty
const FETCH_MAX_IMAGE    = 4 * 1024 * 1024;   // 4 MB
const FETCH_UA           = 'Mozilla/5.0 (compatible; iamtomley-site-preview/1.0; +https://iamtomley.com)';

/** Image types we will store, mapped to the extension we save them under. */
function fetch_image_types(): array
{
    return [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG  => 'png',
        IMAGETYPE_GIF  => 'gif',
        IMAGETYPE_WEBP => 'webp',
        IMAGETYPE_BMP  => 'bmp',
    ];
}

// ─────────────────────────────────────────────────────────────────────
//  Safety: which URLs are we willing to open?
// ─────────────────────────────────────────────────────────────────────

/** Is this IP address one we're willing to connect to (i.e. a public one)? */
function ip_is_public(string $ip): bool
{
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
        return true;
    }
    return false;
}

/**
 * Check a URL is safe to request: http(s), a real hostname, a standard port,
 * and every address it resolves to is public. Returns '' when fine, otherwise
 * a short reason suitable for showing to the admin.
 */
function url_fetch_problem(string $url): string
{
    $p = parse_url($url);
    if (!$p || empty($p['scheme']) || empty($p['host'])) {
        return 'That does not look like a full web address.';
    }
    $scheme = strtolower((string) $p['scheme']);
    if ($scheme !== 'http' && $scheme !== 'https') {
        return 'Only http:// and https:// addresses can be checked.';
    }
    $port = (int) ($p['port'] ?? ($scheme === 'https' ? 443 : 80));
    if (!in_array($port, [80, 443, 8080, 8443], true)) {
        return 'That port is not allowed.';
    }

    $host = (string) $p['host'];
    // A bare IP is checked directly; a hostname is resolved first.
    if (filter_var($host, FILTER_VALIDATE_IP)) {
        return ip_is_public($host) ? '' : 'That address points at a private network.';
    }
    if (!preg_match('/^[A-Za-z0-9]([A-Za-z0-9\-\.]*[A-Za-z0-9])?$/', $host) || strpos($host, '.') === false) {
        return 'That host name is not valid.';
    }

    $ips = [];
    $a = @gethostbynamel($host);
    if (is_array($a)) { $ips = $a; }
    $aaaa = @dns_get_record($host, DNS_AAAA);
    if (is_array($aaaa)) {
        foreach ($aaaa as $rec) { if (!empty($rec['ipv6'])) { $ips[] = $rec['ipv6']; } }
    }
    if (!$ips) {
        return 'That domain could not be found.';
    }
    foreach ($ips as $ip) {
        if (!ip_is_public((string) $ip)) {
            return 'That address points at a private network.';
        }
    }
    return '';
}

// ─────────────────────────────────────────────────────────────────────
//  Fetching
// ─────────────────────────────────────────────────────────────────────

/**
 * Request a URL and return [body, finalUrl, contentType], or null on failure.
 * Redirects are followed by hand so each hop gets the same safety check.
 */
function fetch_url(string $url, int $maxBytes, int $hops = 0): ?array
{
    if ($hops > FETCH_MAX_REDIRECT || url_fetch_problem($url) !== '') {
        return null;
    }

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($ch === false) { return null; }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_FOLLOWLOCATION => false,          // we follow them ourselves
            CURLOPT_TIMEOUT        => FETCH_TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => FETCH_TIMEOUT,
            CURLOPT_USERAGENT      => FETCH_UA,
            CURLOPT_ACCEPT_ENCODING => '',
            CURLOPT_HTTPHEADER     => ['Accept: text/html,image/*,*/*'],
            // Stop the download once it is clearly bigger than we allow.
            CURLOPT_NOPROGRESS     => false,
            CURLOPT_PROGRESSFUNCTION => static function ($res, $dlTotal, $dlNow) use ($maxBytes) {
                return ($dlTotal > $maxBytes || $dlNow > $maxBytes) ? 1 : 0;
            },
        ]);
        $raw = curl_exec($ch);
        if ($raw === false) { curl_close($ch); return null; }
        $code       = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $ctype      = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        $head = substr((string) $raw, 0, $headerSize);
        $body = substr((string) $raw, $headerSize);

        if ($code >= 300 && $code < 400 && preg_match('/^location:\s*(.+)$/mi', $head, $m)) {
            $next = absolute_url(trim($m[1]), $url);
            return $next === null ? null : fetch_url($next, $maxBytes, $hops + 1);
        }
        if ($code >= 400) { return null; }
        return [substr($body, 0, $maxBytes), $url, strtolower(trim(explode(';', $ctype)[0]))];
    }

    // No cURL — fall back to the stream wrapper (needs allow_url_fopen).
    if (!filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) {
        return null;
    }
    $ctx = stream_context_create(['http' => [
        'method'          => 'GET',
        'timeout'         => FETCH_TIMEOUT,
        'follow_location' => 0,
        'ignore_errors'   => true,
        'header'          => "User-Agent: " . FETCH_UA . "\r\nAccept: text/html,image/*,*/*\r\n",
    ]]);
    $fh = @fopen($url, 'rb', false, $ctx);
    if (!$fh) { return null; }
    $body = (string) stream_get_contents($fh, $maxBytes + 1);
    $meta = stream_get_meta_data($fh);
    fclose($fh);

    $code = 200; $location = null; $ctype = '';
    foreach (($meta['wrapper_data'] ?? []) as $line) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#i', (string) $line, $m)) { $code = (int) $m[1]; }
        elseif (stripos((string) $line, 'location:') === 0) { $location = trim(substr((string) $line, 9)); }
        elseif (stripos((string) $line, 'content-type:') === 0) { $ctype = strtolower(trim(explode(';', substr((string) $line, 13))[0])); }
    }
    if ($code >= 300 && $code < 400 && $location !== null) {
        $next = absolute_url($location, $url);
        return $next === null ? null : fetch_url($next, $maxBytes, $hops + 1);
    }
    if ($code >= 400) { return null; }
    return [substr($body, 0, $maxBytes), $url, $ctype];
}

/** Turn a possibly-relative link into an absolute URL against $base. */
function absolute_url(string $link, string $base): ?string
{
    $link = trim(html_entity_decode($link, ENT_QUOTES, 'UTF-8'));
    if ($link === '' || stripos($link, 'data:') === 0 || stripos($link, 'javascript:') === 0) {
        return null;
    }
    if (preg_match('#^https?://#i', $link)) { return $link; }

    $b = parse_url($base);
    if (!$b || empty($b['scheme']) || empty($b['host'])) { return null; }
    $origin = $b['scheme'] . '://' . $b['host'] . (isset($b['port']) ? ':' . $b['port'] : '');

    if (str_starts_with($link, '//'))  { return $b['scheme'] . ':' . $link; }
    if (str_starts_with($link, '/'))   { return $origin . $link; }
    if (str_starts_with($link, '#') || str_starts_with($link, '?')) { return null; }

    $dir = isset($b['path']) ? preg_replace('#/[^/]*$#', '/', $b['path']) : '/';
    $path = $dir . $link;
    // Flatten ./ and ../ segments.
    $parts = [];
    foreach (explode('/', $path) as $seg) {
        if ($seg === '.' || $seg === '') { continue; }
        if ($seg === '..') { array_pop($parts); continue; }
        $parts[] = $seg;
    }
    return $origin . '/' . implode('/', $parts);
}

// ─────────────────────────────────────────────────────────────────────
//  Finding the image inside a page
// ─────────────────────────────────────────────────────────────────────

/** The "sizes" attribute ("180x180") as a single number, for ranking icons. */
function icon_size_score(string $sizes): int
{
    if (preg_match('/(\d+)\s*[xX]\s*(\d+)/', $sizes, $m)) {
        return min((int) $m[1], (int) $m[2]);
    }
    return stripos($sizes, 'any') !== false ? 512 : 0;
}

/**
 * Pull every candidate image out of a page's HTML, best first.
 * Returns a list of ['url' => absolute URL, 'kind' => human label].
 */
function image_candidates(string $html, string $pageUrl): array
{
    // A <base href> changes what relative links resolve against.
    if (preg_match('#<base\b[^>]*\bhref\s*=\s*["\']([^"\']+)["\']#i', $html, $m)) {
        $rebased = absolute_url($m[1], $pageUrl);
        if ($rebased !== null) { $pageUrl = $rebased; }
    }

    $meta = [];    // property/name (lowercased) => content
    if (preg_match_all('#<meta\b[^>]*>#i', $html, $tags)) {
        foreach ($tags[0] as $tag) {
            if (!preg_match('#\b(?:property|name|itemprop)\s*=\s*["\']([^"\']+)["\']#i', $tag, $k)) { continue; }
            if (!preg_match('#\bcontent\s*=\s*["\']([^"\']*)["\']#i', $tag, $v)) { continue; }
            $key = strtolower(trim($k[1]));
            if (!isset($meta[$key]) && trim($v[1]) !== '') { $meta[$key] = trim($v[1]); }
        }
    }

    $links = [];   // ['rel' => …, 'href' => …, 'sizes' => …]
    if (preg_match_all('#<link\b[^>]*>#i', $html, $tags)) {
        foreach ($tags[0] as $tag) {
            if (!preg_match('#\brel\s*=\s*["\']([^"\']+)["\']#i', $tag, $r)) { continue; }
            if (!preg_match('#\bhref\s*=\s*["\']([^"\']+)["\']#i', $tag, $h)) { continue; }
            preg_match('#\bsizes\s*=\s*["\']([^"\']+)["\']#i', $tag, $s);
            $links[] = [
                'rel'   => strtolower(trim($r[1])),
                'href'  => trim($h[1]),
                'sizes' => $s[1] ?? '',
            ];
        }
    }

    // 'guess' marks the conventional /favicon.ico we try even when the page
    // never mentioned it — so a site with no artwork at all can be told apart
    // from one whose artwork simply would not download.
    $out = [];
    $push = static function (?string $href, string $kind, bool $guess = false) use (&$out, $pageUrl): void {
        if ($href === null || $href === '') { return; }
        $abs = absolute_url($href, $pageUrl);
        if ($abs === null) { return; }
        foreach ($out as $existing) { if ($existing['url'] === $abs) { return; } }
        $out[] = ['url' => $abs, 'kind' => $kind, 'guess' => $guess];
    };

    // 1. Social preview images — usually the nicest, biggest artwork.
    $push($meta['og:image:secure_url'] ?? $meta['og:image:url'] ?? $meta['og:image'] ?? null, 'Open Graph image');
    $push($meta['twitter:image:src'] ?? $meta['twitter:image'] ?? null, 'Twitter card image');
    $push($meta['image'] ?? null, 'Page image');
    $push($meta['msapplication-tileimage'] ?? null, 'Windows tile icon');

    // 2. Touch icons and favicons, largest declared size first.
    $icons = [];
    foreach ($links as $l) {
        $rels = preg_split('/\s+/', $l['rel']) ?: [];
        $isTouch = in_array('apple-touch-icon', $rels, true) || in_array('apple-touch-icon-precomposed', $rels, true);
        $isIcon  = in_array('icon', $rels, true) || in_array('shortcut', $rels, true) || in_array('fluid-icon', $rels, true);
        $isLogo  = in_array('mask-icon', $rels, true) || in_array('logo', $rels, true);
        if (!$isTouch && !$isIcon && !$isLogo) { continue; }
        $icons[] = [
            'href'  => $l['href'],
            'score' => icon_size_score($l['sizes']) + ($isTouch ? 400 : 0) + ($isLogo ? 50 : 0),
            'kind'  => $isTouch ? 'Apple touch icon' : ($isLogo ? 'Site logo' : 'Favicon'),
        ];
    }
    usort($icons, static fn($a, $b) => $b['score'] <=> $a['score']);
    foreach ($icons as $i) { $push($i['href'], $i['kind']); }

    // 3. Last resort: the conventional /favicon.ico at the site root, whether
    //    or not the page linked to it.
    $push('/favicon.ico', 'Favicon', true);

    return $out;
}

// ─────────────────────────────────────────────────────────────────────
//  Downloading and storing
// ─────────────────────────────────────────────────────────────────────

/** Is this SVG free of scripting? We refuse anything that can execute. */
function svg_is_safe(string $svg): bool
{
    if (stripos($svg, '<svg') === false) { return false; }
    $lower = strtolower($svg);
    foreach (['<script', '<foreignobject', 'javascript:', '<iframe', '<embed', '<object', '<use'] as $bad) {
        if (strpos($lower, $bad) !== false) { return false; }
    }
    // on*= event handlers (onload, onclick, …)
    return !preg_match('/\son[a-z]+\s*=/i', $svg);
}

/** Work out the file extension for downloaded bytes, or null if not an image. */
function image_extension(string $bytes, string $contentType = ''): ?string
{
    if (strlen($bytes) < 16) { return null; }

    $info = @getimagesizefromstring($bytes);
    if (is_array($info) && isset($info[2]) && isset(fetch_image_types()[$info[2]])) {
        return fetch_image_types()[$info[2]];
    }
    // .ico files start with a 4-byte icon-directory header.
    if (str_starts_with($bytes, "\x00\x00\x01\x00")) { return 'ico'; }
    // SVG is text; accept it only when it carries no scripting.
    if (stripos($contentType, 'svg') !== false || stripos(substr($bytes, 0, 1024), '<svg') !== false) {
        return svg_is_safe($bytes) ? 'svg' : null;
    }
    return null;
}

/**
 * Save image bytes into uploads/ and return the root-relative path
 * ("/uploads/logos/project-ab12cd.png"), or null if it could not be written.
 */
function store_image_bytes(string $bytes, string $ext, string $prefix = 'img'): ?string
{
    $dir = UPLOAD_DIR . '/logos';
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        return null;
    }
    $safePrefix = preg_replace('/[^a-z0-9\-]+/i', '-', $prefix) ?: 'img';
    $name = strtolower(trim($safePrefix, '-')) . '-' . bin2hex(random_bytes(5)) . '.' . $ext;
    return @file_put_contents($dir . '/' . $name, $bytes) === false
        ? null
        : '/uploads/logos/' . $name;
}

/**
 * The whole job: given a site address, find its logo/preview image, download
 * it and keep a local copy.
 *
 * Returns ['ok' => bool, 'path' => saved path, 'source' => image URL,
 *          'kind' => what was found, 'error' => message when ok is false].
 */
function detect_site_image(string $url, string $prefix = 'site'): array
{
    $fail = static fn(string $msg): array => ['ok' => false, 'path' => '', 'source' => '', 'kind' => '', 'error' => $msg];

    $url = trim($url);
    if ($url === '' || $url === '#') {
        return $fail('Add a link first, then detect the image.');
    }
    if (!preg_match('#^[a-z][a-z0-9+.\-]*://#i', $url)) {
        $url = 'https://' . ltrim($url, '/');
    }
    if (($problem = url_fetch_problem($url)) !== '') {
        return $fail($problem);
    }

    $page = fetch_url($url, FETCH_MAX_HTML);
    $candidates = [];
    if ($page !== null && (str_contains($page[2], 'html') || $page[2] === '')) {
        $candidates = image_candidates($page[0], $page[1]);
    } elseif ($page !== null && str_starts_with($page[2], 'image/')) {
        // The link points straight at an image.
        $candidates = [['url' => $page[1], 'kind' => 'Linked image']];
    } else {
        // The page itself would not load; still try the conventional favicon.
        $root = absolute_url('/favicon.ico', $url);
        if ($root !== null) { $candidates = [['url' => $root, 'kind' => 'Favicon', 'guess' => true]]; }
    }

    if (!$candidates) {
        return $fail('No logo or preview image was found on that site.');
    }

    // Did the page actually point at any artwork, or are we only guessing?
    $named = false;
    foreach ($candidates as $c) {
        if (empty($c['guess'])) { $named = true; break; }
    }

    foreach ($candidates as $c) {
        if (url_fetch_problem($c['url']) !== '') { continue; }
        $res = fetch_url($c['url'], FETCH_MAX_IMAGE);
        if ($res === null || $res[0] === '') { continue; }
        $ext = image_extension($res[0], $res[2]);
        if ($ext === null) { continue; }
        $path = store_image_bytes($res[0], $ext, $prefix);
        if ($path === null) {
            return $fail('Found an image, but uploads/ is not writable.');
        }
        return ['ok' => true, 'path' => $path, 'source' => $c['url'], 'kind' => $c['kind'], 'error' => ''];
    }

    return $fail($named
        ? 'That site lists an image, but it could not be downloaded.'
        : 'No logo or preview image was found on that site.');
}

/**
 * Store a file the admin picked with a file input, using the same checks and
 * the same uploads/logos folder as the auto-detected images.
 *
 * Returns ['ok' => bool, 'path' => saved path, 'error' => message].
 * A form submitted with no file chosen returns ok=false with an empty error,
 * which callers treat as "nothing to do".
 */
function store_uploaded_image(array $file, string $prefix = 'img'): array
{
    $fail = static fn(string $msg): array => ['ok' => false, 'path' => '', 'error' => $msg];

    $err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($err === UPLOAD_ERR_NO_FILE || ($file['name'] ?? '') === '') {
        return $fail('');
    }
    if ($err !== UPLOAD_ERR_OK) {
        return $fail($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE
            ? 'That image is larger than the server allows.'
            : 'The upload did not complete. Please try again.');
    }
    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return $fail('The upload did not complete. Please try again.');
    }
    if ((int) ($file['size'] ?? 0) > FETCH_MAX_IMAGE) {
        return $fail('Please use an image of 4 MB or less.');
    }

    $bytes = (string) @file_get_contents($tmp, false, null, 0, FETCH_MAX_IMAGE + 1);
    $ext = image_extension($bytes, (string) (@mime_content_type($tmp) ?: ''));
    if ($ext === null) {
        return $fail('That file is not a usable image (JPG, PNG, WebP, GIF, SVG or ICO).');
    }
    $path = store_image_bytes($bytes, $ext, $prefix);
    return $path === null
        ? $fail('Could not save the image — check that uploads/ is writable.')
        : ['ok' => true, 'path' => $path, 'error' => ''];
}

/** Delete a previously stored logo (used when replacing or removing one). */
function delete_stored_image(string $path): void
{
    $path = trim($path);
    if ($path === '' || !str_starts_with($path, '/uploads/logos/')) {
        return;   // only ever touch files this feature created
    }
    $file = APP_ROOT . $path;
    $real = realpath($file);
    $base = realpath(UPLOAD_DIR . '/logos');
    if ($real !== false && $base !== false && str_starts_with($real, $base) && is_file($real)) {
        @unlink($real);
    }
}
