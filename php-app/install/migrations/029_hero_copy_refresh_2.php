<?php
/**
 * Requested homepage headline + title update. Only replaces a value that
 * still matches the PREVIOUS refresh's exact text (see
 * 008_hero_copy_refresh.php) — if an admin has already edited it by hand
 * through Settings, their wording is left alone.
 */
return function (PDO $pdo, array $context): void {
    $updates = [
        'hero_headline_main' => [
            'Your Digital Business',
            'We help offline businesses',
        ],
        'hero_headline_accent' => [
            'Starts Here.',
            'thrive online.',
        ],
        'seo_title' => [
            'TECHBISS - Your Digital Business Starts Here.',
            'TECHBISS — Helping offline businesses go online',
        ],
    ];
    $stmt = $pdo->prepare('UPDATE settings SET value = ? WHERE id = ? AND value = ?');
    foreach ($updates as $key => [$new, $old]) {
        $stmt->execute([$new, $key, $old]);
    }
};
