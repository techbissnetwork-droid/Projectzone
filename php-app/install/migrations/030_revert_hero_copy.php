<?php
/**
 * Reverts 029_hero_copy_refresh_2.php — the requested headline/title
 * change is being undone. Only replaces a value that still matches
 * 029's exact text, so a manual edit made in between is left alone.
 */
return function (PDO $pdo, array $context): void {
    $updates = [
        'hero_headline_main' => [
            'We help offline businesses',
            'Your Digital Business',
        ],
        'hero_headline_accent' => [
            'thrive online.',
            'Starts Here.',
        ],
        'seo_title' => [
            'TECHBISS — Helping offline businesses go online',
            'TECHBISS - Your Digital Business Starts Here.',
        ],
    ];
    $stmt = $pdo->prepare('UPDATE settings SET value = ? WHERE id = ? AND value = ?');
    foreach ($updates as $key => [$new, $old]) {
        $stmt->execute([$new, $key, $old]);
    }
};
