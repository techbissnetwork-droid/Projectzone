<?php
/**
 * Replaces the fixed-slot JSON-blob storage from migration 010 with real
 * tables, so admin/content.php can offer genuine add/delete per item
 * instead of a fixed number of edit-only slots. Seeds each table from
 * whatever is in the old *_json setting (which is either the original
 * default content, or an admin's edit made through the old fixed-slot
 * UI) so nothing is lost in the switch — falling back to
 * includes/default_content.php only if that setting is missing.
 */
return function (PDO $pdo, array $context): void {
    $defaults = require __DIR__ . '/../../includes/default_content.php';

    $pdo->exec("CREATE TABLE IF NOT EXISTS `content_services` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `icon` VARCHAR(40) NOT NULL DEFAULT 'star',
        `name` VARCHAR(150) NOT NULL,
        `blurb` TEXT NOT NULL,
        `bullets_json` TEXT NOT NULL,
        `sort_order` INT UNSIGNED NOT NULL DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `content_industries` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `icon` VARCHAR(40) NOT NULL DEFAULT 'star',
        `name` VARCHAR(150) NOT NULL,
        `out_json` TEXT NOT NULL,
        `sort_order` INT UNSIGNED NOT NULL DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `content_case_studies` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `sector` VARCHAR(100) NOT NULL DEFAULT '',
        `icon` VARCHAR(40) NOT NULL DEFAULT 'star',
        `client` VARCHAR(150) NOT NULL,
        `stat` VARCHAR(40) NOT NULL DEFAULT '',
        `stat_label` VARCHAR(150) NOT NULL DEFAULT '',
        `quote` TEXT NOT NULL,
        `body` TEXT NOT NULL,
        `sort_order` INT UNSIGNED NOT NULL DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `content_pricing_plans` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(100) NOT NULL,
        `monthly_price` INT UNSIGNED NULL,
        `yearly_price` INT UNSIGNED NULL,
        `description` TEXT NOT NULL,
        `features_json` TEXT NOT NULL,
        `cta` VARCHAR(100) NOT NULL DEFAULT 'Get started',
        `is_recommended` TINYINT(1) NOT NULL DEFAULT 0,
        `sort_order` INT UNSIGNED NOT NULL DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `content_pricing_faqs` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `question` VARCHAR(255) NOT NULL,
        `answer` TEXT NOT NULL,
        `sort_order` INT UNSIGNED NOT NULL DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `content_team` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `initials` VARCHAR(4) NOT NULL DEFAULT '',
        `name` VARCHAR(150) NOT NULL,
        `role` VARCHAR(150) NOT NULL DEFAULT '',
        `sort_order` INT UNSIGNED NOT NULL DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `content_values` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `icon` VARCHAR(40) NOT NULL DEFAULT 'star',
        `title` VARCHAR(150) NOT NULL,
        `description` TEXT NOT NULL,
        `sort_order` INT UNSIGNED NOT NULL DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Pull the seed source for each table: the old JSON setting if present
    // (carries forward any edit made through the old UI), else the
    // built-in defaults. Only seeds if the new table is still empty.
    $jsonOrDefault = function (string $settingKey, array $default) use ($pdo): array {
        $stmt = $pdo->prepare('SELECT value FROM settings WHERE id = ?');
        $stmt->execute([$settingKey]);
        $raw = $stmt->fetchColumn();
        if ($raw) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded) && $decoded !== []) {
                return $decoded;
            }
        }
        return $default;
    };
    $isEmpty = function (string $table) use ($pdo): bool {
        return (int)$pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn() === 0;
    };

    if ($isEmpty('content_services')) {
        $rows = $jsonOrDefault('services_json', $defaults['services']);
        $stmt = $pdo->prepare('INSERT INTO content_services (icon, name, blurb, bullets_json, sort_order) VALUES (?,?,?,?,?)');
        foreach ($rows as $i => $r) {
            $stmt->execute([$r['icon'] ?? 'star', $r['name'] ?? '', $r['blurb'] ?? '', json_encode($r['bullets'] ?? []), $i]);
        }
    }
    if ($isEmpty('content_industries')) {
        $rows = $jsonOrDefault('solutions_json', $defaults['solutions']);
        $stmt = $pdo->prepare('INSERT INTO content_industries (icon, name, out_json, sort_order) VALUES (?,?,?,?)');
        foreach ($rows as $i => $r) {
            $stmt->execute([$r['icon'] ?? 'star', $r['name'] ?? '', json_encode($r['out'] ?? []), $i]);
        }
    }
    if ($isEmpty('content_case_studies')) {
        $rows = $jsonOrDefault('case_studies_json', $defaults['case_studies']);
        $stmt = $pdo->prepare('INSERT INTO content_case_studies (sector, icon, client, stat, stat_label, quote, body, sort_order) VALUES (?,?,?,?,?,?,?,?)');
        foreach ($rows as $i => $r) {
            $stmt->execute([$r['sector'] ?? '', $r['icon'] ?? 'star', $r['client'] ?? '', $r['stat'] ?? '', $r['statLabel'] ?? '', $r['quote'] ?? '', $r['body'] ?? '', $i]);
        }
    }
    if ($isEmpty('content_pricing_plans')) {
        $rows = $jsonOrDefault('pricing_json', $defaults['pricing']);
        $stmt = $pdo->prepare('INSERT INTO content_pricing_plans (name, monthly_price, yearly_price, description, features_json, cta, is_recommended, sort_order) VALUES (?,?,?,?,?,?,?,?)');
        foreach ($rows as $i => $r) {
            $stmt->execute([$r['n'] ?? '', $r['m'] ?? null, $r['y'] ?? null, $r['d'] ?? '', json_encode($r['f'] ?? []), $r['cta'] ?? 'Get started', !empty($r['rec']) ? 1 : 0, $i]);
        }
    }
    if ($isEmpty('content_pricing_faqs')) {
        $rows = $jsonOrDefault('pricing_faq_json', $defaults['pricing_faq']);
        $stmt = $pdo->prepare('INSERT INTO content_pricing_faqs (question, answer, sort_order) VALUES (?,?,?)');
        foreach ($rows as $i => $r) {
            $stmt->execute([$r[0] ?? '', $r[1] ?? '', $i]);
        }
    }
    if ($isEmpty('content_team')) {
        $rows = $jsonOrDefault('team_json', $defaults['team']);
        $stmt = $pdo->prepare('INSERT INTO content_team (initials, name, role, sort_order) VALUES (?,?,?,?)');
        foreach ($rows as $i => $r) {
            $stmt->execute([$r['i'] ?? '', $r['n'] ?? '', $r['r'] ?? '', $i]);
        }
    }
    if ($isEmpty('content_values')) {
        $rows = $jsonOrDefault('values_json', $defaults['values']);
        $stmt = $pdo->prepare('INSERT INTO content_values (icon, title, description, sort_order) VALUES (?,?,?,?)');
        foreach ($rows as $i => $r) {
            $stmt->execute([$r['icon'] ?? 'star', $r['t'] ?? '', $r['d'] ?? '', $i]);
        }
    }
};
