<?php
/**
 * Broader positioning statement covering the full range of what
 * TECHBISS builds (e-commerce, automation, payments — not just
 * "get online"). Only replaces a value that still matches the
 * PREVIOUS seed text exactly, so an admin's own edit is left alone.
 */
return function (PDO $pdo, array $context): void {
    $new = 'TECHBISS builds the complete digital presence of your business — websites, apps, hosting, security, email, e-commerce, automation and payments.';
    $updates = [
        'site_tagline' => [
            'We help offline businesses get online — building the website or app, then handling the domain, hosting, email and everything after launch.',
            $new,
        ],
        'meta_description' => [
            'TECHBISS helps offline businesses get online with a new website or app, then takes care of the domain, hosting, email and everything after launch.',
            $new,
        ],
    ];
    $stmt = $pdo->prepare('UPDATE settings SET value = ? WHERE id = ? AND value = ?');
    foreach ($updates as $key => [$old, $newVal]) {
        $stmt->execute([$newVal, $key, $old]);
    }
};
