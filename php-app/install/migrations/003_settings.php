<?php
/**
 * Site-wide editable content (Admin > Settings). Seeded with the same
 * copy that used to be hardcoded, so nothing visibly changes until staff
 * actually edit something.
 */
return function (PDO $pdo, array $context): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `settings` (
        `id` VARCHAR(60) PRIMARY KEY,
        `value` TEXT NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $defaults = [
        'hero_headline_main' => 'Your business, finally',
        'hero_headline_accent' => 'open online.',
        'hero_subheadline' => "TECHBISS builds your website or app from the ground up, then handles the domain, hosting, SSL, business email and app store publishing — so you launch with everything already working, and people can actually find you.",
        'site_tagline' => 'We help offline businesses get online — website or app, domain, hosting, email and everything after launch.',
        'contact_email' => 'hello@techbiss.com',
        'contact_phone' => '+1 (415) 555-0148',
    ];

    $stmt = $pdo->prepare('INSERT IGNORE INTO settings (id, value) VALUES (?, ?)');
    foreach ($defaults as $key => $value) {
        $stmt->execute([$key, $value]);
    }
};
