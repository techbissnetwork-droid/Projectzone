<?php
/**
 * The tags that decide how a page looks in Google and when someone pastes the
 * link into WhatsApp, Facebook or X. All of it is editable from the admin —
 * nothing here needs a developer.
 */

/** The full <title>, with the suffix if one is set. */
function seo_title(string $title): string
{
    $suffix = trim(setting('seo.title_suffix', ''));
    if ($suffix === '' || str_contains($title, $suffix)) {
        return $title;
    }
    return $title . ' ' . $suffix;
}

function seo_description(string $description): string
{
    $d = trim($description) !== '' ? $description : setting('seo.default_description', '');
    return trim($d) !== '' ? $d : setting('site.tagline', '');
}

/** The image shown when the link is shared. Absolute URL, as the spec requires. */
function seo_social_image(): string
{
    $img = setting('brand.social_image', '');
    return $img !== '' ? url($img) : '';
}

function seo_favicon(): array
{
    $custom = setting('brand.favicon', '');
    if ($custom !== '') {
        $ext  = strtolower((string) pathinfo($custom, PATHINFO_EXTENSION));
        $type = ['svg' => 'image/svg+xml', 'png' => 'image/png', 'ico' => 'image/x-icon',
                 'gif' => 'image/gif', 'jpg' => 'image/jpeg', 'webp' => 'image/webp'][$ext] ?? 'image/png';
        return [url($custom), $type];
    }
    return [url('assets/favicon.svg'), 'image/svg+xml'];
}

/** The address of the page being viewed, without any tracking parameters. */
function canonical_url(): string
{
    $script = basename($_SERVER['SCRIPT_NAME'] ?? 'index.php');
    $path   = $script === 'index.php' ? '' : $script;

    /* Keep only the parameters that actually change the content. */
    $keep = [];
    foreach (['slug', 'sector', 'category'] as $k) {
        if (get($k) !== '') {
            $keep[$k] = get($k);
        }
    }
    $query = $keep ? '?' . http_build_query($keep) : '';
    return url($path) . $query;
}

/** Everything that goes in <head>, printed in one go. */
function seo_tags(string $title, string $description): void
{
    $title = seo_title($title);
    $desc  = seo_description($description);
    $image = seo_social_image();
    $url   = canonical_url();
    $name  = setting('site.name', 'TECHBISS');
    [$icon, $iconType] = seo_favicon();
    ?>
<title><?= esc($title) ?></title>
<meta name="description" content="<?= esc($desc) ?>">
<link rel="canonical" href="<?= esc($url) ?>">
<meta name="theme-color" content="#0b0b0d">
<?php if (setting('seo.noindex', '0') === '1'): ?>
<meta name="robots" content="noindex,nofollow">
<?php else: ?>
<meta name="robots" content="index,follow,max-image-preview:large">
<?php endif; ?>
<?php if ($code = setting('seo.google_verification', '')): ?>
<meta name="google-site-verification" content="<?= esc($code) ?>">
<?php endif; ?>
<link rel="icon" href="<?= esc($icon) ?>" type="<?= esc($iconType) ?>">
<link rel="apple-touch-icon" href="<?= esc($icon) ?>">

<meta property="og:type" content="website">
<meta property="og:site_name" content="<?= esc($name) ?>">
<meta property="og:title" content="<?= esc($title) ?>">
<meta property="og:description" content="<?= esc($desc) ?>">
<meta property="og:url" content="<?= esc($url) ?>">
<?php if ($image): ?>
<meta property="og:image" content="<?= esc($image) ?>">
<meta property="og:image:alt" content="<?= esc($name) ?>">
<?php endif; ?>

<meta name="twitter:card" content="<?= $image ? 'summary_large_image' : 'summary' ?>">
<meta name="twitter:title" content="<?= esc($title) ?>">
<meta name="twitter:description" content="<?= esc($desc) ?>">
<?php if ($image): ?>
<meta name="twitter:image" content="<?= esc($image) ?>">
<?php endif; ?>
<?php if ($handle = ltrim(setting('seo.twitter', ''), '@')): ?>
<meta name="twitter:site" content="@<?= esc($handle) ?>">
<?php endif; ?>
<?php
}

/**
 * The structured-data block. It is what lets Google show your phone number,
 * opening hours and address directly in the results.
 */
function seo_business_schema(): void
{
    $name = setting('site.name', 'TECHBISS');
    $data = [
        '@context'    => 'https://schema.org',
        '@type'       => 'ProfessionalService',
        'name'        => $name,
        'description' => seo_description(''),
        'url'         => base_url(),
    ];

    if ($img = seo_social_image()) {
        $data['image'] = $img;
    }
    if ($logo = setting('brand.logo', '')) {
        $data['logo'] = url($logo);
    }
    if ($email = setting('site.email', '')) {
        $data['email'] = $email;
    }
    if ($phone = setting('site.phone', '')) {
        $data['telephone'] = $phone;
    }

    $address = array_filter([
        '@type'           => 'PostalAddress',
        'streetAddress'   => setting('biz.street', ''),
        'addressLocality' => setting('biz.city', ''),
        'addressRegion'   => setting('biz.region', ''),
        'postalCode'      => setting('biz.postcode', ''),
        'addressCountry'  => setting('biz.country', ''),
    ]);
    if (count($address) > 1) {
        $data['address'] = $address;
    }

    $social = array_values(array_filter([
        setting('social.linkedin', ''), setting('social.instagram', ''),
    ], fn($u) => $u !== '' && $u !== '#'));
    if ($social) {
        $data['sameAs'] = $social;
    }

    echo '<script type="application/ld+json">'
       . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
       . '</script>' . "\n";
}

/** The header and footer wordmark: an image if one is uploaded, else the name. */
function brand_mark(bool $inFooter = false): void
{
    $logo = setting('brand.logo', '');
    $name = setting('site.name', 'TECHBISS');
    if ($logo !== '') {
        $h = max(16, min(80, (int) setting('brand.logo_height', '30')));
        ?><img src="<?= esc(url($logo)) ?>" alt="<?= esc($name) ?>"
               style="height:<?= $h ?>px;width:auto"><?php
        return;
    }
    ?><i aria-hidden="true"></i><?= esc($name) ?><?php
}
