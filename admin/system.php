<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/guard.php';
$me = require_admin();

$lockFile   = base_path('install/.installed');
$installDir = base_path('install');
$log        = [];

if (post()) {
    Csrf::check();
    $do = (string)($_POST['do'] ?? '');

    /* Run pending migrations from inside the panel — the safe path. */
    if ($do === 'migrate') {
        try {
            require_once base_path('install/migrations.php');
            $driver = $GLOBALS['TB_CONFIG']['db']['driver'] ?? 'mysql';
            $log = tb_migrate(Database::pdo(), $driver);
            Settings::load();
            log_activity('system.migrate', null, null, implode('; ', $log));
            Flash::ok('Database update finished.');
        } catch (Throwable $ex) {
            Flash::err('Update failed: ' . $ex->getMessage());
        }
        $_SESSION['migrate_log'] = $log;
        redirect('admin/system.php');
    }

    /* Unlocking is deliberately gated behind the admin's own password. */
    if ($do === 'unlock') {
        if (!password_verify((string)($_POST['password'] ?? ''), $me['password_hash'])) {
            Flash::err('That password is not correct. The installer stays locked.');
        } elseif (!is_file($lockFile)) {
            Flash::err('The installer is not locked.');
        } elseif (@unlink($lockFile)) {
            log_activity('system.unlock', null, null, 'installer unlocked by ' . $me['email']);
            Flash::ok('Installer unlocked. Lock it again as soon as you are finished — or delete install/ entirely.');
        } else {
            Flash::err('Could not delete install/.installed. Remove it over FTP or set the folder writable.');
        }
        redirect('admin/system.php');
    }

    if ($do === 'lock') {
        if (@file_put_contents($lockFile, now()) !== false) {
            log_activity('system.lock');
            Flash::ok('Installer locked again.');
        } else {
            Flash::err('Could not write install/.installed.');
        }
        redirect('admin/system.php');
    }
}

$log = $_SESSION['migrate_log'] ?? [];
unset($_SESSION['migrate_log']);

$locked      = is_file($lockFile);
$installGone = !is_dir($installDir);
$driver      = $GLOBALS['TB_CONFIG']['db']['driver'] ?? 'mysql';

/* What the code expects versus what the database has. */
$pending = [];
try {
    require_once base_path('install/migrations.php');
    $have = tb_tables(Database::pdo(), $driver);
    $sql  = (string)@file_get_contents(base_path('install/schema.sql'));
    preg_match_all('/CREATE TABLE\s+(\w+)/i', $sql, $m);
    foreach ($m[1] as $t) {
        if (!in_array(strtolower($t), $have, true)) {
            $pending[] = 'Missing table: ' . $t;
        }
    }
    $keys = array_flip(Database::pdo()->query('SELECT skey FROM settings')->fetchAll(PDO::FETCH_COLUMN));
    $miss = 0;
    foreach (array_keys(Settings::defaults()) as $k) {
        if (!isset($keys[$k])) {
            $miss++;
        }
    }
    if ($miss) {
        $pending[] = $miss . ' setting' . ($miss === 1 ? '' : 's') . ' not yet in the database';
    }
} catch (Throwable $ex) {
    $pending[] = 'Could not compare the schema: ' . $ex->getMessage();
}

$counts = [];
foreach (['projects','users','tickets','orders','portfolio','products','pages','content_items'] as $t) {
    try { $counts[$t] = (int)Database::value('SELECT COUNT(*) FROM ' . $t, [], 0); }
    catch (Throwable) { $counts[$t] = -1; }
}

$PAGE_TITLE = 'System';
$AREA = 'admin';
require __DIR__ . '/../partials/app_header.php';
?>
<?php if ($log): ?>
  <div class="alert ok"><p><b>Update log</b></p>
    <?php foreach ($log as $line): ?><p><?= e($line) ?></p><?php endforeach; ?></div>
<?php endif; ?>

<div class="split">
  <div class="stack">
    <section class="card">
      <div class="card__head"><h2>Database update</h2>
        <span class="badge <?= $pending ? 'warn' : 'ok' ?>"><?= $pending ? count($pending) . ' pending' : 'Up to date' ?></span></div>
      <div class="card__body">
        <?php if ($pending): ?>
          <p class="dim">This code expects things the database does not have yet:</p>
          <ul class="ticks" style="margin:12px 0 16px">
            <?php foreach ($pending as $p): ?><li><?= e($p) ?></li><?php endforeach; ?>
          </ul>
        <?php else: ?>
          <p class="dim">Every table, column and setting this version expects is present.</p>
        <?php endif; ?>
        <p class="hint" style="margin:12px 0 16px">
          Run this after pulling a code update. It only adds what is missing — your projects,
          clients, tickets, orders and content are never touched. Take a database backup first if you can.
        </p>
        <form method="post" data-confirm="Run the database update now?">
          <?= Csrf::field() ?><input type="hidden" name="do" value="migrate">
          <button class="btn" type="submit">Run database update</button>
        </form>
      </div>
    </section>

    <section class="card">
      <div class="card__head"><h2>Installer</h2>
        <span class="badge <?= $installGone ? 'ok' : ($locked ? 'warn' : 'danger') ?>">
          <?= $installGone ? 'Removed' : ($locked ? 'Locked' : 'UNLOCKED') ?></span></div>
      <div class="card__body">
        <?php if ($installGone): ?>
          <p class="dim">The <span class="mono">install/</span> folder is gone from the server. Nothing can re-run the installer — this is the safest state.</p>
        <?php elseif ($locked): ?>
          <p class="dim">The installer refuses to run. To reinstall from scratch you must unlock it here with your own password.</p>
          <div class="alert warn" style="margin:14px 0">
            <p><b>You almost never need this.</b> To upgrade after a code update, use
              <b>Run database update</b> above — it keeps your data. Unlocking is only for wiping the site and starting again.</p>
          </div>
          <form method="post" class="form" data-confirm="Unlock the installer? Anyone who reaches /install/ could then wipe this site.">
            <?= Csrf::field() ?><input type="hidden" name="do" value="unlock">
            <label class="field" style="max-width:340px"><span>Your password</span>
              <input name="password" type="password" required autocomplete="current-password"></label>
            <div class="formfoot"><button class="btn danger" type="submit">Unlock installer</button></div>
          </form>
        <?php else: ?>
          <div class="alert err" style="margin-bottom:14px">
            <p><b>The installer is unlocked right now.</b> Anyone who can reach
              <span class="mono"><?= e(url('install/')) ?></span> could reinstall over this site. Lock it as soon as you are done.</p>
          </div>
          <form method="post"><?= Csrf::field() ?><input type="hidden" name="do" value="lock">
            <button class="btn" type="submit">Lock the installer</button></form>
        <?php endif; ?>
        <?php if (!$installGone): ?>
          <p class="hint" style="margin-top:16px">Best of all: delete the <span class="mono">install/</span> folder from your server once the site is live.</p>
        <?php endif; ?>
      </div>
    </section>
  </div>

  <div class="stack">
    <section class="card">
      <div class="card__head"><h2>Environment</h2></div>
      <div class="card__body">
        <table class="data" style="margin:-8px 0"><tbody>
          <tr><th>PHP</th><td class="right mono"><?= e(PHP_VERSION) ?></td></tr>
          <tr><th>Database</th><td class="right mono"><?= e($driver) ?></td></tr>
          <tr><th>Site URL</th><td class="right mono"><?= e(rtrim(url(), '/')) ?></td></tr>
          <tr><th>Timezone</th><td class="right mono"><?= e(date_default_timezone_get()) ?></td></tr>
          <tr><th>Uploads writable</th><td class="right"><span class="badge <?= is_writable(base_path('uploads')) ? 'ok' : 'danger' ?>">
            <?= is_writable(base_path('uploads')) ? 'Yes' : 'No' ?></span></td></tr>
          <tr><th>Config writable</th><td class="right"><span class="badge <?= is_writable(base_path('config/config.php')) ? 'warn' : 'ok' ?>">
            <?= is_writable(base_path('config/config.php')) ? 'Yes' : 'Read-only' ?></span></td></tr>
        </tbody></table>
      </div>
    </section>

    <section class="card">
      <div class="card__head"><h2>Records</h2></div>
      <div class="card__body">
        <table class="data" style="margin:-8px 0"><tbody>
          <?php foreach ($counts as $t => $n): ?>
            <tr><th><?= e(label($t)) ?></th>
                <td class="right mono"><?= $n < 0 ? '<span class="muted">table missing</span>' : $n ?></td></tr>
          <?php endforeach; ?>
        </tbody></table>
      </div>
    </section>
  </div>
</div>
<?php require __DIR__ . '/../partials/app_footer.php'; ?>
