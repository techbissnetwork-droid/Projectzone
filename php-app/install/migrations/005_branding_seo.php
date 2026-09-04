<?php
/**
 * SEO/social + branding + appearance settings (Admin > Settings).
 * logo_path/favicon_path/social_image_path empty or pointing at the
 * shipped defaults means "use the built-in TECHBISS brand assets" —
 * staff only need to set them after uploading a custom file.
 */
return function (PDO $pdo, array $context): void {
    $defaults = [
        'seo_title' => 'TECHBISS — Get your business online',
        'meta_description' => 'TECHBISS helps offline businesses get online: websites, apps, domains, hosting, email and everything after launch.',
        'logo_path' => '',
        'favicon_path' => 'assets/favicon.ico',
        'social_image_path' => 'assets/social-default.png',
        'default_theme' => 'auto',
    ];
    $stmt = $pdo->prepare('INSERT IGNORE INTO settings (id, value) VALUES (?, ?)');
    foreach ($defaults as $key => $value) {
        $stmt->execute([$key, $value]);
    }
};
