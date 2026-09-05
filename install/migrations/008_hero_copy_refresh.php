<?php
/**
 * Softer, more professional homepage/SEO copy. Only replaces a value that
 * still matches the ORIGINAL seed text exactly — if an admin has already
 * edited it by hand through Settings, their wording is left alone.
 */
return function (PDO $pdo, array $context): void {
    $updates = [
        'hero_headline_main' => [
            'Your business, finally',
            'We help offline businesses',
        ],
        'hero_headline_accent' => [
            'open online.',
            'thrive online.',
        ],
        'hero_subheadline' => [
            'TECHBISS builds your website or app from the ground up, then handles the domain, hosting, SSL, business email and app store publishing — so you launch with everything already working, and people can actually find you.',
            'TECHBISS builds your website or app, then sets up your domain, hosting, email and app store listing — so you launch with everything working and ready to be found.',
        ],
        'site_tagline' => [
            'We help offline businesses get online — website or app, domain, hosting, email and everything after launch.',
            'We help offline businesses get online — building the website or app, then handling the domain, hosting, email and everything after launch.',
        ],
        'seo_title' => [
            'TECHBISS — Get your business online',
            'TECHBISS — Helping offline businesses go online',
        ],
        'meta_description' => [
            'TECHBISS helps offline businesses get online: websites, apps, domains, hosting, email and everything after launch.',
            'TECHBISS helps offline businesses get online with a new website or app, then takes care of the domain, hosting, email and everything after launch.',
        ],
    ];
    $stmt = $pdo->prepare('UPDATE settings SET value = ? WHERE id = ? AND value = ?');
    foreach ($updates as $key => [$old, $new]) {
        $stmt->execute([$new, $key, $old]);
    }
};
