<?php
/**
 * Download the whole database as a .sql file.
 *
 * Written in plain PHP rather than shelling out to mysqldump, because shared
 * hosts usually block exec(). Fine for a site of this size; if the database
 * ever grows past a few hundred megabytes, use cPanel's own backup instead.
 */
require_once __DIR__ . '/../app/bootstrap.php';
require_installed();
require_admin();
require_once __DIR__ . '/_layout.php';

if (is_post() && post('action') === 'download') {
    csrf_check();
    log_activity('Downloaded a database backup', 'system');

    $name = 'techbiss-backup-' . date('Y-m-d-Hi') . '.sql';
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/sql; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('Cache-Control: private, no-store');

    $pdo    = db();
    $mysql  = db_driver() === 'mysql';
    $quote  = fn(string $id): string => $mysql ? '`' . $id . '`' : '"' . $id . '"';

    echo "-- TECHBISS database backup\n";
    echo '-- Taken ' . date('Y-m-d H:i:s') . "\n";
    echo '-- Driver ' . db_driver() . ', schema version ' . schema_installed_version() . "\n";
    echo "--\n-- Restore with:  mysql -u USER -p DATABASE < this-file.sql\n";
    echo "-- It replaces the tables it contains, so restore into an empty database\n";
    echo "-- unless you mean to overwrite.\n\n";

    if ($mysql) {
        echo "SET FOREIGN_KEY_CHECKS=0;\nSET NAMES utf8mb4;\n\n";
    }

    foreach (array_keys(schema_tables()) as $table) {
        if (!db_table_exists($table)) {
            continue;
        }
        $cols = db_table_columns($table);
        echo "-- ---------------------------------------------------------------\n";
        echo '-- ' . $table . "\n";
        echo "-- ---------------------------------------------------------------\n";
        echo 'DELETE FROM ' . $quote($table) . ";\n";

        $stmt = $pdo->query('SELECT * FROM ' . $quote($table));
        $rows = 0;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $values = [];
            foreach ($cols as $col) {
                $v = $row[$col] ?? null;
                $values[] = $v === null ? 'NULL' : $pdo->quote((string) $v);
            }
            echo 'INSERT INTO ' . $quote($table)
               . ' (' . implode(', ', array_map($quote, $cols)) . ')'
               . ' VALUES (' . implode(', ', $values) . ");\n";
            $rows++;
        }
        echo '-- ' . $rows . " row(s)\n\n";
    }

    if ($mysql) {
        echo "SET FOREIGN_KEY_CHECKS=1;\n";
    }
    echo "-- end of backup\n";
    exit;
}

/* --- the page --------------------------------------------------------- */
$counts = [];
$total  = 0;
foreach (array_keys(schema_tables()) as $table) {
    if (db_table_exists($table)) {
        $counts[$table] = db_count('SELECT COUNT(*) FROM ' . $table);
        $total += $counts[$table];
    }
}

$uploads = glob(APP_ROOT . '/storage/uploads/*.{jpg,png,gif,webp}', GLOB_BRACE) ?: [];
$files   = glob(APP_ROOT . '/storage/files/*') ?: [];
$files   = array_filter($files, fn($f) => is_file($f) && !str_starts_with(basename($f), '.'));

admin_head('Backup', 'backup.php');
admin_page_head('Backup',
    'Take a copy of everything in the database before you make a big change, or just now and then.');
?>

<div class="split">
  <div>
    <div class="panel">
      <header><h2>What is in there</h2></header>
      <div class="tablewrap"><table>
        <thead><tr><th>Table</th><th class="right">Rows</th></tr></thead>
        <tbody>
<?php foreach ($counts as $table => $n): ?>
          <tr><td><?= esc($table) ?></td><td class="right num"><?= number_format($n) ?></td></tr>
<?php endforeach; ?>
        </tbody>
      </table></div>
    </div>
  </div>

  <div>
    <div class="panel">
      <header><h2>Download</h2></header>
      <div class="pad">
        <div class="kv"><span>Rows in total</span><strong><?= number_format($total) ?></strong></div>
        <div class="kv"><span>Database</span><strong><?= esc(db_driver()) ?></strong></div>
        <form method="post" style="margin-top:16px">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="download">
          <button class="btn" type="submit">Download the database</button>
        </form>
        <p style="color:var(--mute);font-size:13px;margin-top:12px">
          A plain .sql file. Keep it somewhere other than this server &mdash; a backup on the
          same machine is not a backup.</p>
      </div>
    </div>

    <div class="panel">
      <header><h2>Not included</h2></header>
      <div class="pad">
        <p style="color:var(--mute);font-size:13.5px;margin-bottom:12px">
          This covers the database only. Your uploaded files are on disk and need copying
          separately &mdash; use cPanel&rsquo;s file manager or FTP.</p>
        <div class="kv"><span>Images uploaded</span><strong><?= count($uploads) ?></strong></div>
        <div class="kv"><span>Files for sale</span><strong><?= count($files) ?></strong></div>
        <p style="color:var(--dim);font-size:12.5px;margin-top:12px">
          Both live under <code style="font-family:var(--mono)">storage/</code>.</p>
      </div>
    </div>
  </div>
</div>

<?php admin_foot(); ?>
