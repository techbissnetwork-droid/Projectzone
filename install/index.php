<?php
declare(strict_types=1);
define('TB_INSTALLING', true);
require_once __DIR__ . '/../app/bootstrap.php';

$configFile = base_path('config/config.php');
$lockFile   = __DIR__ . '/.installed';

if (is_file($lockFile) && is_file($configFile)) {
    http_response_code(403);
    exit('TECHBISS is already installed. Delete install/.installed to run the installer again.');
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('techbiss_install');
    session_start();
}

$step   = max(1, min(4, (int)($_GET['step'] ?? 1)));
$errors = [];
$data   = $_SESSION['install'] ?? [];

/* ── requirements ───────────────────────────────────────── */
$checks = [
    ['PHP 8.1 or newer',        PHP_VERSION_ID >= 80100, PHP_VERSION],
    ['PDO MySQL driver',        extension_loaded('pdo_mysql'), extension_loaded('pdo_mysql') ? 'loaded' : 'missing'],
    ['mbstring extension',      extension_loaded('mbstring'), extension_loaded('mbstring') ? 'loaded' : 'missing'],
    ['fileinfo extension',      extension_loaded('fileinfo'), extension_loaded('fileinfo') ? 'loaded' : 'missing'],
    ['openssl extension',       extension_loaded('openssl'), extension_loaded('openssl') ? 'loaded' : 'missing'],
    ['config/ is writable',     is_writable(base_path('config')), is_writable(base_path('config')) ? 'writable' : 'chmod 755 config'],
    ['uploads/ is writable',    is_writable(base_path('uploads')), is_writable(base_path('uploads')) ? 'writable' : 'chmod 755 uploads'],
];
$ready = array_reduce($checks, static fn($c, $r) => $c && $r[1], true);

/* ── step 2: database ───────────────────────────────────── */
if ($step === 2 && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
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
            $existing = $pdo->query("SHOW TABLES LIKE 'users'")->fetchColumn();
            if ($existing && empty($_POST['overwrite'])) {
                $errors[] = 'This database already has TECHBISS tables. Tick "drop existing tables" to reinstall, or use an empty database.';
            } else {
                $data['db'] = $db;
                $data['overwrite'] = !empty($_POST['overwrite']);
                $_SESSION['install'] = $data;
                header('Location: ?step=3');
                exit;
            }
        } catch (PDOException $ex) {
            $errors[] = 'Could not connect: ' . $ex->getMessage();
        }
    }
}

/* ── step 3: site + admin, then write everything ────────── */
if ($step === 3 && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (empty($data['db'])) {
        header('Location: ?step=2');
        exit;
    }
    $site  = trim((string)($_POST['site_name'] ?? 'TECHBISS'));
    $name  = trim((string)($_POST['admin_name'] ?? ''));
    $email = trim(mb_strtolower((string)($_POST['admin_email'] ?? '')));
    $pass  = (string)($_POST['admin_password'] ?? '');
    $pass2 = (string)($_POST['admin_password2'] ?? '');
    $tz    = (string)($_POST['timezone'] ?? 'Asia/Kathmandu');

    if ($site === '')                     $errors[] = 'Enter a site name.';
    if ($name === '')                     $errors[] = 'Enter the administrator name.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid administrator email.';
    if (strlen($pass) < 10)               $errors[] = 'The password must be at least 10 characters.';
    if ($pass !== $pass2)                 $errors[] = 'The two passwords do not match.';
    if (!in_array($tz, timezone_identifiers_list(), true)) $errors[] = 'Pick a valid timezone.';

    if (!$errors) {
        try {
            $pdo = new PDO(Database::dsn($data['db']), $data['db']['username'], $data['db']['password'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $pdo->exec('SET NAMES utf8mb4');

            $sql = (string)file_get_contents(__DIR__ . '/schema.sql');
            preg_match_all('/CREATE TABLE\s+([a-z_]+)/i', $sql, $m);
            $tables = $m[1];

            if (!empty($data['overwrite'])) {
                $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
                foreach (array_reverse($tables) as $t) {
                    $pdo->exec('DROP TABLE IF EXISTS ' . $t);
                }
                $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
            }

            foreach (array_filter(array_map('trim', explode(';', preg_replace('/^\s*--.*$/m', '', $sql) ?? ''))) as $stmt) {
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

            $appUrl = rtrim((string)($_POST['app_url'] ?? ''), '/');
            $cfg = [
                'db'  => $data['db'],
                'app' => [
                    'url'      => $appUrl,
                    'timezone' => $tz,
                    'debug'    => false,
                    'key'      => bin2hex(random_bytes(32)),
                ],
                'mail' => [
                    'from_name'  => $site,
                    'from_email' => $email,
                ],
            ];
            $php = "<?php\n// Generated by the TECHBISS installer on " . $ts . ".\nreturn "
                 . var_export($cfg, true) . ";\n";
            if (file_put_contents($configFile, $php) === false) {
                throw new RuntimeException('Could not write config/config.php. Make the config folder writable.');
            }
            @chmod($configFile, 0640);
            @file_put_contents($lockFile, $ts);

            unset($_SESSION['install']);
            $_SESSION['install_done'] = ['email' => $email, 'site' => $site];
            header('Location: ?step=4');
            exit;
        } catch (Throwable $ex) {
            $errors[] = 'Install failed: ' . $ex->getMessage();
        }
    }
}

$done = $_SESSION['install_done'] ?? null;
$titles = [1 => 'Requirements', 2 => 'Database', 3 => 'Site &amp; administrator', 4 => 'Done'];
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Install TECHBISS</title>
<link rel="stylesheet" href="../assets/css/install.css">
</head>
<body>
<div class="wrap">
  <header class="head">
    <span class="mark" aria-hidden="true">
      <svg viewBox="0 0 24 24" fill="none"><path d="M12 2 21.5 7v10L12 22 2.5 17V7z" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"/><path d="M12 22V12l9.5-5M12 12 2.5 7" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round" opacity=".55"/></svg>
    </span>
    <div>
      <h1>Install TECHBISS</h1>
      <p>Four steps. Nothing is written until the last one.</p>
    </div>
  </header>

  <ol class="steps">
    <?php foreach ($titles as $i => $t): ?>
      <li class="<?= $i === $step ? 'on' : ($i < $step ? 'done' : '') ?>"><b><?= $i ?></b><span><?= $t ?></span></li>
    <?php endforeach; ?>
  </ol>

  <?php if ($errors): ?>
    <div class="alert err">
      <?php foreach ($errors as $er): ?><p><?= e($er) ?></p><?php endforeach; ?>
    </div>
  <?php endif; ?>

  <main class="card">
  <?php if ($step === 1): ?>
    <h2>Server requirements</h2>
    <table class="checks">
      <?php foreach ($checks as [$labelText, $pass, $note]): ?>
        <tr class="<?= $pass ? 'pass' : 'fail' ?>">
          <td><?= $pass ? '✓' : '✕' ?></td>
          <th scope="row"><?= e($labelText) ?></th>
          <td class="note"><?= e($note) ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
    <?php if ($ready): ?>
      <a class="btn" href="?step=2">Continue <span>→</span></a>
    <?php else: ?>
      <p class="hint">Fix the items marked ✕, then reload this page.</p>
      <a class="btn ghost" href="?step=1">Re-check</a>
    <?php endif; ?>

  <?php elseif ($step === 2): ?>
    <h2>Database connection</h2>
    <p class="hint">Create an empty MySQL database first, then enter its details.</p>
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
      <label class="check"><input type="checkbox" name="overwrite" value="1"> Drop existing TECHBISS tables (destroys all current data)</label>
      <button class="btn" type="submit">Test &amp; continue <span>→</span></button>
    </form>

  <?php elseif ($step === 3): ?>
    <h2>Site and administrator</h2>
    <form method="post" class="form">
      <label>Site name<input name="site_name" value="<?= e($_POST['site_name'] ?? 'TECHBISS') ?>" required></label>
      <label>Site URL <small>leave blank to detect automatically</small>
        <input name="app_url" placeholder="https://techbiss.com" value="<?= e($_POST['app_url'] ?? '') ?>"></label>
      <label>Timezone
        <select name="timezone">
          <?php $sel = $_POST['timezone'] ?? 'Asia/Kathmandu';
          foreach (timezone_identifiers_list() as $tz): ?>
            <option value="<?= e($tz) ?>"<?= $tz === $sel ? ' selected' : '' ?>><?= e($tz) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <hr>
      <label>Administrator name<input name="admin_name" value="<?= e($_POST['admin_name'] ?? '') ?>" required></label>
      <label>Administrator email<input name="admin_email" type="email" value="<?= e($_POST['admin_email'] ?? '') ?>" required></label>
      <div class="row two">
        <label>Password <small>10+ characters</small><input name="admin_password" type="password" required minlength="10"></label>
        <label>Repeat password<input name="admin_password2" type="password" required minlength="10"></label>
      </div>
      <button class="btn" type="submit">Install now <span>→</span></button>
    </form>

  <?php else: ?>
    <h2>Installed</h2>
    <p class="lede"><?= e($done['site'] ?? 'TECHBISS') ?> is ready. Sign in with <b><?= e($done['email'] ?? '') ?></b>.</p>
    <div class="alert warn"><p><b>One last thing:</b> delete the <code>install/</code> folder from your server. The installer will refuse to run again while <code>install/.installed</code> exists, but removing the folder is safer.</p></div>
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
