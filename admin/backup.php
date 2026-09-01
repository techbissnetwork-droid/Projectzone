<?php
declare(strict_types=1);

/** One-click database backup download (admin only). */

$config = require __DIR__ . '/_admin.php';

use SignalMasterAi\Auth;
use SignalMasterAi\Database;

Auth::requireLogin();
$pdo = Database::pdo();
$stamp = gmdate('Ymd-His');

if ($config['db']['driver'] !== 'mysql') {
    // SQLite: checkpoint the WAL so the single file is complete, then stream it.
    $pdo->exec('PRAGMA wal_checkpoint(TRUNCATE)');
    $file = $config['db']['sqlite'];
    if (!is_file($file)) {
        http_response_code(404);
        exit('Database file not found');
    }
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="signalmasterai-backup-' . $stamp . '.sqlite"');
    header('Content-Length: ' . (string)filesize($file));
    readfile($file);
    exit;
}

// MySQL: portable SQL dump built over PDO (no shell access needed).
header('Content-Type: application/sql; charset=utf-8');
header('Content-Disposition: attachment; filename="signalmasterai-backup-' . $stamp . '.sql"');
echo "-- SignalMasterAi backup " . gmdate('c') . "\nSET FOREIGN_KEY_CHECKS=0;\n\n";
$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
foreach ($tables as $t) {
    $create = $pdo->query("SHOW CREATE TABLE `$t`")->fetch(PDO::FETCH_NUM);
    echo "DROP TABLE IF EXISTS `$t`;\n" . $create[1] . ";\n\n";
    $rows = $pdo->query("SELECT * FROM `$t`");
    foreach ($rows as $row) {
        $vals = array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote((string)$v), array_values($row));
        echo "INSERT INTO `$t` VALUES (" . implode(',', $vals) . ");\n";
    }
    echo "\n";
}
echo "SET FOREIGN_KEY_CHECKS=1;\n";
