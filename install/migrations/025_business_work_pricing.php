<?php
/**
 * Adds "app name" / "website name" to businesses (what the client's
 * product is actually called, separate from the account name on file),
 * a "work type" category to projects (Build, Upgrade, Setup,
 * Maintenance...), and replaces the old flat MRR number on businesses
 * with an explicit fixed-vs-recurring price — not every engagement is a
 * subscription.
 */
return function (PDO $pdo, array $context): void {
    $bizColumns = [
        'app_name' => "VARCHAR(150) NULL AFTER name",
        'website_name' => "VARCHAR(150) NULL AFTER app_name",
    ];
    foreach ($bizColumns as $col => $def) {
        $exists = $pdo->query("SHOW COLUMNS FROM businesses LIKE '$col'")->fetch();
        if (!$exists) {
            $pdo->exec("ALTER TABLE businesses ADD COLUMN `$col` $def");
        }
    }

    $hasPriceType = $pdo->query("SHOW COLUMNS FROM businesses LIKE 'price_type'")->fetch();
    if (!$hasPriceType) {
        $pdo->exec("ALTER TABLE businesses ADD COLUMN price_type ENUM('fixed','recurring') NOT NULL DEFAULT 'recurring' AFTER mrr_cents");
    }
    $hasPriceCents = $pdo->query("SHOW COLUMNS FROM businesses LIKE 'price_cents'")->fetch();
    if (!$hasPriceCents) {
        $pdo->exec("ALTER TABLE businesses ADD COLUMN price_cents INT UNSIGNED NOT NULL DEFAULT 0 AFTER price_type");
        $pdo->exec('UPDATE businesses SET price_cents = mrr_cents');
    }
    $hasMrr = $pdo->query("SHOW COLUMNS FROM businesses LIKE 'mrr_cents'")->fetch();
    if ($hasMrr) {
        $pdo->exec('ALTER TABLE businesses DROP COLUMN mrr_cents');
    }

    $hasWorkType = $pdo->query("SHOW COLUMNS FROM projects LIKE 'work_type'")->fetch();
    if (!$hasWorkType) {
        $pdo->exec("ALTER TABLE projects ADD COLUMN work_type ENUM('Build','Upgrade','Setup','Maintenance','Other') NOT NULL DEFAULT 'Build' AFTER title");
    }
};
