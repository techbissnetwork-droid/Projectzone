<?php
declare(strict_types=1);
define('TB_INSTALLING', true);
require_once __DIR__ . '/../app/bootstrap.php';

$configFile = base_path('config/config.php');
$lockFile   = __DIR__ . '/.installed';
$installed  = is_file($lockFile) && is_file($configFile);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('techbiss_install');
    session_start();
}

/* Locked: the only way past is an administrator signing in and unlocking
   from the admin panel. The installer itself never accepts a password. */
if ($installed && empty($_SESSION['install_done'])) {
    http_response_code(403);
    ?><!doctype html><html lang="en"><head><meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex"><title>Installer locked</title>
    <link rel="stylesheet" href="../assets/css/install.css"></head><body>
    <div class="wrap">
      <header class="head">
        <span class="mark" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M12 2 21.5 7v10L12 22 2.5 17V7z" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"/><path d="M12 22V12l9.5-5M12 12 2.5 7" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round" opacity=".55"/></svg></span>
        <div><h1>Installer locked</h1><p>This site is already installed.</p></div>
      </header>
      <main class="card">
        <h2>Nothing to do here</h2>
        <p class="lede">The installer will not run again while <code>install/.installed</code> exists.</p>
        <div class="alert warn">
          <p><b>To upgrade after a code update</b>, sign in as an administrator and use
             <b>System → Run database update</b>. It adds anything new without touching your data —
             you never need to unlock this folder.</p>
          <p><b>To reinstall from scratch</b>, sign in as an administrator and unlock the installer
             from the same page. Unlocking requires a password; this page does not.</p>
        </div>
        <div class="actions">
          <a class="btn" href="../admin/system.php">Open System <span>→</span></a>
          <a class="btn ghost" href="../">View the site</a>
        </div>
        <p class="hint" style="margin-top:18px">Safest of all: delete the <code>install/</code> folder from your server.</p>
      </main>
      <p class="foot">TECHBISS platform installer</p>
    </div></body></html><?php
    exit;
}

$step   = max(1, min(5, (int)($_GET['step'] ?? 1)));
$errors = [];
$data   = $_SESSION['install'] ?? [];

/* ── detected defaults ──────────────────────────────────── */
function detect_url(): string
{
    $https  = (($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off')
           || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'
           || (int)($_SERVER['SERVER_PORT'] ?? 80) === 443;
    $host   = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/')));
    if (str_ends_with($script, '/install')) {
        $script = substr($script, 0, -8);
    }
    return ($https ? 'https://' : 'http://') . $host . rtrim($script, '/');
}
$detectedUrl = detect_url();
$detectedTz  = @date_default_timezone_get() ?: 'UTC';

/* ── step 1 · requirements ──────────────────────────────── */
$checks = [
    ['PHP 8.1 or newer',     PHP_VERSION_ID >= 80100, PHP_VERSION],
    ['PDO MySQL driver',     extension_loaded('pdo_mysql'), extension_loaded('pdo_mysql') ? 'loaded' : 'missing'],
    ['mbstring extension',   extension_loaded('mbstring'), extension_loaded('mbstring') ? 'loaded' : 'missing'],
    ['fileinfo extension',   extension_loaded('fileinfo'), extension_loaded('fileinfo') ? 'loaded' : 'missing'],
    ['openssl extension',    extension_loaded('openssl'), extension_loaded('openssl') ? 'loaded' : 'missing'],
    ['config/ is writable',  is_writable(base_path('config')), is_writable(base_path('config')) ? 'writable' : 'chmod 755 config'],
    ['uploads/ is writable', is_writable(base_path('uploads')), is_writable(base_path('uploads')) ? 'writable' : 'chmod 755 uploads'],
];
$ready = array_reduce($checks, static fn($c, $r) => $c && $r[1], true);

/* ── step 2 · database + mode ───────────────────────────── */
if ($step === 2 && post()) {
    $db = [
        'driver'   => 'mysql',
        'host'     => trim((string)($_POST['host'] ?? 'localhost')),
        'port'     => (int)($_POST['port'] ?? 3306),
        'database' => trim((string)($_POST['database'] ?? '')),
        'username' => trim((string)($_POST['username'] ?? '')),
        'password' => (string)($_POST['password'] ?? ''),
        'charset'  => 'utf8mb4',
    ];
    if ($db['database'] === '') $errors[] = 'Enter the database name.';
    if ($db['username'] === '') $errors[] = 'Enter the database username.';

    if (!$errors) {
        try {
            $pdo = new PDO(Database::dsn($db), $db['username'], $db['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $hasUsers = (bool)$pdo->query("SHOW TABLES LIKE 'users'")->fetchColumn();
            $data['db']       = $db;
            $data['existing'] = $hasUsers;
            $_SESSION['install'] = $data;
            header('Location: ?step=' . ($hasUsers ? 3 : 4));
            exit;
        } catch (PDOException $ex) {
            $errors[] = 'Could not connect: ' . $ex->getMessage();
        }
    }
}

/* ── step 3 · choose fresh or migrate (existing database only) ── */
if ($step === 3 && post()) {
    $mode = (string)($_POST['mode'] ?? '');
    if (!in_array($mode, ['migrate', 'fresh'], true)) {
        $errors[] = 'Choose what to do with the existing database.';
    } elseif ($mode === 'fresh' && (string)($_POST['confirm'] ?? '') !== 'DELETE') {
        $errors[] = 'Type DELETE to confirm that every existing record should be destroyed.';
    } else {
        $data['mode'] = $mode;
        $_SESSION['install'] = $data;
        header('Location: ?step=' . ($mode === 'migrate' ? 5 : 4));
        exit;
    }
}

/* ── step 4 · fresh install ─────────────────────────────── */
if ($step === 4 && post()) {
    if (empty($data['db'])) {
        header('Location: ?step=2');
        exit;
    }
    $site  = trim((string)($_POST['site_name'] ?? 'TECHBISS'));
    $name  = trim((string)($_POST['admin_name'] ?? ''));
    $email = trim(mb_strtolower((string)($_POST['admin_email'] ?? '')));
    $pass  = (string)($_POST['admin_password'] ?? '');
    $pass2 = (string)($_POST['admin_password2'] ?? '');
    $tz    = (string)($_POST['timezone'] ?? $detectedTz);
    $appUrl = rtrim(trim((string)($_POST['app_url'] ?? '')), '/');

    if ($site === '')                     $errors[] = 'Enter a site name.';
    if ($name === '')                     $errors[] = 'Enter the administrator name.';
    if (!is_email($email))                $errors[] = 'Enter a valid administrator email.';
    if (strlen($pass) < 10)               $errors[] = 'The password must be at least 10 characters.';
    if ($pass !== $pass2)                 $errors[] = 'The two passwords do not match.';
    if (!in_array($tz, timezone_identifiers_list(), true)) $errors[] = 'Pick a valid timezone.';

    if (!$errors) {
        try {
            $pdo = new PDO(Database::dsn($data['db']), $data['db']['username'], $data['db']['password'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $pdo->exec('SET NAMES utf8mb4');

            $sql = (string)file_get_contents(__DIR__ . '/schema.sql');
            $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? '';
            preg_match_all('/CREATE TABLE\s+(\w+)/i', $sql, $m);

            if (!empty($data['existing'])) {
                $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
                foreach (array_reverse($m[1]) as $t) {
                    $pdo->exec('DROP TABLE IF EXISTS ' . $t);
                }
                $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
            }
            foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
                $pdo->exec($stmt);
            }

            $ts = date('Y-m-d H:i:s');
            $pdo->prepare('INSERT INTO users (name,email,phone,password_hash,role,status,must_change,created_at)
                           VALUES (?,?,?,?,?,?,0,?)')
                ->execute([$name, $email, null, password_hash($pass, PASSWORD_DEFAULT), 'admin', 'active', $ts]);

            $defaults = Settings::defaults();
            $defaults['site_name']     = $site;
            $defaults['contact_email'] = $email;
            $ins = $pdo->prepare('INSERT INTO settings (skey,svalue,updated_at) VALUES (?,?,?)');
            foreach ($defaults as $k => $v) {
                $ins->execute([$k, $v, $ts]);
            }
            require __DIR__ . '/seed.php';
            install_seed($pdo, $ts);

            tb_write_config($configFile, $data['db'], $appUrl ?: $detectedUrl, $tz, $site, $email);
            @file_put_contents($lockFile, $ts);

            unset($_SESSION['install']);
            $_SESSION['install_done'] = ['email' => $email, 'site' => $site, 'mode' => 'fresh', 'log' => []];
            header('Location: ?step=5');
            exit;
        } catch (Throwable $ex) {
            $errors[] = 'Install failed: ' . $ex->getMessage();
        }
    }
}

/* ── step 5 · run the migration, or show the finish screen ─ */
if ($step === 5 && ($data['mode'] ?? '') === 'migrate' && empty($_SESSION['install_done'])) {
    if (post()) {
        $tz     = (string)($_POST['timezone'] ?? $detectedTz);
        $appUrl = rtrim(trim((string)($_POST['app_url'] ?? '')), '/');
        try {
            $pdo = new PDO(Database::dsn($data['db']), $data['db']['username'], $data['db']['password'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $pdo->exec('SET NAMES utf8mb4');
            require __DIR__ . '/migrations.php';
            $log = tb_migrate($pdo, 'mysql');

            $siteName  = (string)($pdo->query("SELECT svalue FROM settings WHERE skey='site_name'")->fetchColumn() ?: 'TECHBISS');
            $adminMail = (string)($pdo->query("SELECT email FROM users WHERE role='admin' ORDER BY id LIMIT 1")->fetchColumn() ?: '');
            tb_write_config($configFile, $data['db'], $appUrl ?: $detectedUrl, $tz, $siteName, $adminMail);
            @file_put_contents($lockFile, date('Y-m-d H:i:s'));

            unset($_SESSION['install']);
            $_SESSION['install_done'] = ['email' => $adminMail, 'site' => $siteName, 'mode' => 'migrate', 'log' => $log];
            header('Location: ?step=5');
            exit;
        } catch (Throwable $ex) {
            $errors[] = 'Update failed: ' . $ex->getMessage();
        }
    }
}

function tb_write_config(string $file, array $db, string $appUrl, string $tz, string $site, string $email): void
{
    $cfg = [
        'db'  => $db,
        'app' => ['url' => $appUrl, 'timezone' => $tz, 'debug' => false, 'key' => bin2hex(random_bytes(32))],
        'mail' => ['from_name' => $site, 'from_email' => $email],
    ];
    $php = "<?php\n// Generated by the TECHBISS installer on " . date('Y-m-d H:i:s') . ".\nreturn "
         . var_export($cfg, true) . ";\n";
    if (file_put_contents($file, $php) === false) {
        throw new RuntimeException('Could not write config/config.php. Make the config folder writable.');
    }
    @chmod($file, 0640);
}

$done   = $_SESSION['install_done'] ?? null;
$titles = [1 => 'Requirements', 2 => 'Database', 3 => 'Mode', 4 => 'Setup', 5 => 'Done'];
if (!empty($data['db']) && empty($data['existing']) && $step >= 3) {
    $titles[3] = 'Mode';
}
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Install TECHBISS</title>
<link rel="stylesheet" href="../assets/css/install.css">
</head>
<body>
<div class="wrap">
  <header class="head">
    <span class="mark" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M12 2 21.5 7v10L12 22 2.5 17V7z" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"/><path d="M12 22V12l9.5-5M12 12 2.5 7" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round" opacity=".55"/></svg></span>
    <div><h1>Install TECHBISS</h1><p>Nothing is written until you confirm.</p></div>
  </header>

  <ol class="steps">
    <?php foreach ($titles as $i => $t): ?>
      <li class="<?= $i === $step ? 'on' : ($i < $step ? 'done' : '') ?>"><b><?= $i ?></b><span><?= e($t) ?></span></li>
    <?php endforeach; ?>
  </ol>

  <?php if ($errors): ?>
    <div class="alert err"><?php foreach ($errors as $er): ?><p><?= e($er) ?></p><?php endforeach; ?></div>
  <?php endif; ?>

  <main class="card">
  <?php if ($step === 1): ?>
    <h2>Server requirements</h2>
    <table class="checks">
      <?php foreach ($checks as [$labelText, $pass, $note]): ?>
        <tr class="<?= $pass ? 'pass' : 'fail' ?>"><td><?= $pass ? '✓' : '✕' ?></td>
          <th scope="row"><?= e($labelText) ?></th><td class="note"><?= e($note) ?></td></tr>
      <?php endforeach; ?>
    </table>
    <?php if ($ready): ?><a class="btn" href="?step=2">Continue <span>→</span></a>
    <?php else: ?><p class="hint">Fix the items marked ✕, then reload.</p><a class="btn ghost" href="?step=1">Re-check</a><?php endif; ?>

  <?php elseif ($step === 2): ?>
    <h2>Database connection</h2>
    <p class="hint">Enter the details of your MySQL database. We detect whether it is empty and offer the right options next.</p>
    <form method="post" class="form">
      <div class="row two">
        <label>Host<input name="host" value="<?= e($_POST['host'] ?? $data['db']['host'] ?? 'localhost') ?>" required></label>
        <label>Port<input name="port" type="number" value="<?= e((string)($_POST['port'] ?? $data['db']['port'] ?? 3306)) ?>" required></label>
      </div>
      <label>Database name<input name="database" value="<?= e($_POST['database'] ?? $data['db']['database'] ?? '') ?>" required></label>
      <div class="row two">
        <label>Username<input name="username" value="<?= e($_POST['username'] ?? $data['db']['username'] ?? '') ?>" required></label>
        <label>Password<input name="password" type="password" value=""></label>
      </div>
      <button class="btn" type="submit">Test connection <span>→</span></button>
    </form>

  <?php elseif ($step === 3): ?>
    <h2>This database already has TECHBISS tables</h2>
    <p class="hint">Choose what to do. Only one of these keeps your data.</p>
    <form method="post" class="form">
      <label class="pick">
        <input type="radio" name="mode" value="migrate" checked>
        <span><b>Update it (recommended)</b>
          Adds any new tables, columns and settings from this version. Your projects,
          clients, tickets, orders and content are left exactly as they are.</span>
      </label>
      <label class="pick pick--danger">
        <input type="radio" name="mode" value="fresh">
        <span><b>Wipe and install fresh</b>
          Drops every TECHBISS table and starts over. Everything currently stored is destroyed.</span>
      </label>
      <label>Type <b>DELETE</b> to allow a wipe <small>leave blank if you are updating</small>
        <input name="confirm" autocomplete="off" placeholder="DELETE"></label>
      <button class="btn" type="submit">Continue <span>→</span></button>
    </form>

  <?php elseif ($step === 4): ?>
    <h2>Site and administrator</h2>
    <form method="post" class="form">
      <label>Site name<input name="site_name" value="<?= e($_POST['site_name'] ?? 'TECHBISS') ?>" required></label>
      <label>Site URL <small>detected automatically — change it only if it is wrong</small>
        <input name="app_url" value="<?= e($_POST['app_url'] ?? $detectedUrl) ?>"></label>
      <label>Timezone
        <select name="timezone">
          <?php $sel = $_POST['timezone'] ?? $detectedTz;
          foreach (timezone_identifiers_list() as $tz): ?>
            <option value="<?= e($tz) ?>"<?= $tz === $sel ? ' selected' : '' ?>><?= e($tz) ?></option>
          <?php endforeach; ?>
        </select></label>
      <hr>
      <label>Administrator name<input name="admin_name" value="<?= e($_POST['admin_name'] ?? '') ?>" required></label>
      <label>Administrator email<input name="admin_email" type="email" value="<?= e($_POST['admin_email'] ?? '') ?>" required></label>
      <div class="row two">
        <label>Password <small>10+ characters</small><input name="admin_password" type="password" required minlength="10"></label>
        <label>Repeat password<input name="admin_password2" type="password" required minlength="10"></label>
      </div>
      <button class="btn" type="submit">Install now <span>→</span></button>
    </form>

  <?php elseif ($step === 5 && !$done): ?>
    <h2>Update the database</h2>
    <p class="hint">This adds what is missing and changes nothing else. Take a database backup first if you can.</p>
    <form method="post" class="form">
      <label>Site URL <small>detected automatically</small>
        <input name="app_url" value="<?= e($_POST['app_url'] ?? $detectedUrl) ?>"></label>
      <label>Timezone
        <select name="timezone">
          <?php $sel = $_POST['timezone'] ?? $detectedTz;
          foreach (timezone_identifiers_list() as $tz): ?>
            <option value="<?= e($tz) ?>"<?= $tz === $sel ? ' selected' : '' ?>><?= e($tz) ?></option>
          <?php endforeach; ?>
        </select></label>
      <button class="btn" type="submit">Run the update <span>→</span></button>
    </form>

  <?php else: ?>
    <h2><?= ($done['mode'] ?? '') === 'migrate' ? 'Database updated' : 'Installed' ?></h2>
    <p class="lede"><?= e($done['site'] ?? 'TECHBISS') ?> is ready<?= !empty($done['email']) ? '. Sign in with <b>' . e($done['email']) . '</b>' : '' ?>.</p>
    <?php if (!empty($done['log'])): ?>
      <ul class="loglist"><?php foreach ($done['log'] as $line): ?><li><?= e($line) ?></li><?php endforeach; ?></ul>
    <?php endif; ?>
    <div class="alert warn">
      <p><b>Now lock this down.</b> Delete the <code>install/</code> folder from your server.
         Until you do, it is protected by a lock file — reopening the installer requires signing in
         as an administrator and unlocking it from <b>System</b>.</p>
    </div>
    <div class="actions">
      <a class="btn" href="../admin/">Open the admin panel <span>→</span></a>
      <a class="btn ghost" href="../">View the site</a>
    </div>
  <?php endif; ?>
  </main>
  <p class="foot">TECHBISS platform installer</p>
</div>
</body>
</html>
