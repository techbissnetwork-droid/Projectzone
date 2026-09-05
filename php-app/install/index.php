<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/migrate.php';

/**
 * Deletes the entire install/ folder (this script included). Safe to run
 * automatically: future updates arrive by re-uploading a zip that always
 * contains a full install/migrations/ folder again, and "what's already
 * applied" is tracked in the schema_migrations table, not on disk — so
 * nothing about the update mechanism depends on this folder surviving
 * between deploys.
 */
function install_self_destruct(): void
{
    $dir = __DIR__;
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    }
    @rmdir($dir);
}

function detect_base_url(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') == 443)
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    // This script lives in /install/, so the site root is one level up.
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/install/index.php'));
    $root = rtrim(dirname($scriptDir), '/');
    return $scheme . '://' . $host . $root;
}

$configExists = file_exists(CONFIG_PATH);
$lockExists = file_exists(INSTALL_LOCK_PATH);
$error = '';
$pdo = null;

if ($configExists && defined('DB_HOST')) {
    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    } catch (PDOException $e) {
        $error = 'Could not connect to the database using the settings in config.php: ' . $e->getMessage();
    }
}

$action = $_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['action'] ?? '') : '';
$formError = '';

// ---- Step: save database connection + site URL, write config.php ----
if (!$lockExists && $action === 'save_db') {
    if (!csrf_check((string)($_POST['csrf'] ?? ''))) {
        $formError = 'Your session expired — please try again.';
    } else {
        $dbHost = trim((string)($_POST['db_host'] ?? ''));
        $dbName = trim((string)($_POST['db_name'] ?? ''));
        $dbUser = trim((string)($_POST['db_user'] ?? ''));
        $dbPass = (string)($_POST['db_pass'] ?? '');
        $siteUrl = rtrim(trim((string)($_POST['site_url'] ?? '')), '/');

        if ($dbHost === '' || $dbName === '' || $dbUser === '' || $siteUrl === '') {
            $formError = 'Please fill in every field (password may be blank only if your database truly has none).';
        } else {
            try {
                new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                ]);
            } catch (PDOException $e) {
                $formError = 'Could not connect with those details: ' . $e->getMessage();
            }
            if ($formError === '') {
                $configCode = "<?php\n"
                    . "define('DB_HOST', " . var_export($dbHost, true) . ");\n"
                    . "define('DB_NAME', " . var_export($dbName, true) . ");\n"
                    . "define('DB_USER', " . var_export($dbUser, true) . ");\n"
                    . "define('DB_PASS', " . var_export($dbPass, true) . ");\n"
                    . "define('SITE_URL', " . var_export($siteUrl, true) . ");\n"
                    . "define('APP_DEBUG', false);\n";
                if (@file_put_contents(CONFIG_PATH, $configCode) === false) {
                    $formError = 'Could not write config.php automatically — the install/ folder needs to be '
                        . 'writable by the web server for this step, or you can create config.php yourself '
                        . '(copy config.sample.php, fill in these same values, save as config.php next to it).';
                } else {
                    header('Location: ./');
                    exit;
                }
            }
        }
    }
}

// ---- Step: create the first admin account, run the fresh-install migration ----
if ($pdo && !$lockExists && $action === 'create_admin') {
    if (!csrf_check((string)($_POST['csrf'] ?? ''))) {
        $formError = 'Your session expired — please try again.';
    } else {
        $name = trim((string)($_POST['admin_name'] ?? ''));
        $email = trim(strtolower((string)($_POST['admin_email'] ?? '')));
        $password = (string)($_POST['admin_password'] ?? '');
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $formError = 'Please enter your name and a valid email address.';
        } elseif (strlen($password) < 8) {
            $formError = 'Password must be at least 8 characters.';
        } else {
            $emailDomain = substr($email, strpos($email, '@') + 1) ?: 'example.com';
            $context = [
                'admin_name' => $name,
                'admin_email' => $email,
                'admin_password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'admin_role' => 'Founder & CEO',
                'admin_initials' => strtoupper(substr($name, 0, 1) . substr(strrchr($name, ' ') ?: '', 1, 1)),
                'email_domain' => $emailDomain,
            ];
            try {
                foreach (pending_migrations($pdo) as $file) {
                    run_migration($pdo, $file, $context);
                }
                if (!file_exists(INSTALL_LOCK_PATH)) {
                    file_put_contents(INSTALL_LOCK_PATH, date('c'));
                }
                header('Location: ./');
                exit;
            } catch (Throwable $e) {
                $formError = 'Setup failed while creating the database tables: ' . $e->getMessage();
            }
        }
    }
}

// ---- Step: apply any pending migrations to an already-installed site ----
// Once the site is locked (already fully installed), this requires an
// authenticated staff session — otherwise any anonymous visitor could
// trigger schema migrations at will.
if ($pdo && $action === 'run_migrations') {
    if ($lockExists && !current_staff()) {
        $formError = 'Please sign in to /admin/ first, then come back here to run pending updates.';
    } elseif (!csrf_check((string)($_POST['csrf'] ?? ''))) {
        $formError = 'Your session expired — please try again.';
    } else {
        try {
            foreach (pending_migrations($pdo) as $file) {
                run_migration($pdo, $file, []);
            }
            if (!file_exists(INSTALL_LOCK_PATH)) {
                file_put_contents(INSTALL_LOCK_PATH, date('c'));
            }
            header('Location: ./');
            exit;
        } catch (Throwable $e) {
            $formError = 'Migration failed: ' . $e->getMessage();
        }
    }
}

// ---- Step: staff-triggered deletion of this install/ folder ----
// Never automatic — a stray fresh install or update run must not silently
// remove the tools an admin might still need. This requires an
// authenticated staff session plus CSRF, same as the other post-install
// actions above.
if ($lockExists && $action === 'delete_install') {
    if (!current_staff()) {
        $formError = 'Please sign in to /admin/ first, then come back here to delete the install folder.';
    } elseif (!csrf_check((string)($_POST['csrf'] ?? ''))) {
        $formError = 'Your session expired — please try again.';
    } else {
        install_self_destruct();
        header('Location: ../admin/');
        exit;
    }
}

// ---- Step: wipe the database and start over from a blank install ----
// Deliberately destructive, so it's gated behind an authenticated staff
// session (same reasoning as run_migrations above) PLUS a typed
// confirmation phrase — a stray click here can't nuke a live site.
if ($pdo && $lockExists && $action === 'wipe_reinstall') {
    if (!current_staff()) {
        $formError = 'Please sign in to /admin/ first, then come back here to wipe and reinstall.';
    } elseif (!csrf_check((string)($_POST['csrf'] ?? ''))) {
        $formError = 'Your session expired — please try again.';
    } elseif (trim((string)($_POST['confirm_text'] ?? '')) !== 'DELETE EVERYTHING') {
        $formError = 'Type DELETE EVERYTHING exactly (all capitals) to confirm you want to wipe the database.';
    } else {
        try {
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
            $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
            foreach ($tables as $t) {
                $pdo->exec('DROP TABLE `' . $t . '`');
            }
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
            @unlink(INSTALL_LOCK_PATH);
            header('Location: ./');
            exit;
        } catch (Throwable $e) {
            $formError = 'Could not wipe the database: ' . $e->getMessage();
        }
    }
}

$token = csrf_token();
$detectedUrl = detect_base_url();

// Decide what to render.
$view = 'setup';
$pending = [];
if ($configExists && $pdo) {
    $pending = pending_migrations($pdo);
    $needsAdminAccount = in_array('001_initial', array_map(fn($f) => basename($f, '.php'), $pending), true) && !$lockExists;
    if ($needsAdminAccount) {
        // Never call current_staff() here: right after a wipe, the
        // staff table itself doesn't exist yet, and this branch is
        // exactly the "no tables yet" case.
        $view = 'admin_account';
    } elseif ($lockExists && current_staff()) {
        // Already installed and signed in: this is the one place that
        // asks "update this install, or wipe it and start fresh?" —
        // both are equally real options, not one hidden behind the other.
        $view = 'manage';
    } elseif ($lockExists && !empty($pending)) {
        $view = 'pending_locked';
    } else {
        if (!$lockExists) {
            @file_put_contents(INSTALL_LOCK_PATH, date('c'));
            $lockExists = true;
        }
        $view = 'done';
    }
} elseif ($configExists && $error) {
    $view = $lockExists ? 'db_error_locked' : 'db_error_unlocked';
}

$phpOk = version_compare(PHP_VERSION, '8.0.0', '>=');
$pdoOk = extension_loaded('pdo_mysql');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Set up TECHBISS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/style.css">
<style>
body{ min-height:100vh; padding:40px 20px; }
.install-wrap{ max-width:560px; margin:0 auto; }
.install-wrap .card{ margin-bottom:18px; }
.req-row{ display:flex; justify-content:space-between; align-items:center; padding:10px 0; border-bottom:1px solid var(--border-soft); font-size:.9rem; }
.req-row:last-child{ border-bottom:none; }
</style>
</head>
<body>
<main class="install-wrap">
  <div class="flex items-center gap-12" style="margin-bottom:22px;">
    <div class="logo-mark" style="width:36px;height:36px;"><svg viewBox="0 0 24 24" fill="none"><rect x="3" y="3" width="18" height="18" rx="6" fill="var(--accent-1)"/><rect x="7.5" y="7.5" width="9" height="2.6" rx="1.3" fill="#fff2ea"/><rect x="10.7" y="7.5" width="2.6" height="9.5" rx="1.3" fill="#fff2ea"/></svg></div>
    <b style="font-family:var(--font-display);font-size:1.1rem;">techbiss setup</b>
  </div>

  <?php if ($view === 'setup'): ?>
    <div class="card">
      <h3 style="margin-bottom:14px;">1. Requirements</h3>
      <div class="req-row"><span>PHP 8.0 or newer</span><span class="badge <?= $phpOk ? 'success' : 'danger' ?>"><?= $phpOk ? 'OK — ' . PHP_VERSION : 'Missing (' . PHP_VERSION . ')' ?></span></div>
      <div class="req-row"><span>pdo_mysql extension</span><span class="badge <?= $pdoOk ? 'success' : 'danger' ?>"><?= $pdoOk ? 'OK' : 'Missing' ?></span></div>
      <div class="req-row"><span>install/ folder writable</span><span class="badge <?= is_writable(__DIR__) ? 'success' : 'warning' ?>"><?= is_writable(__DIR__) ? 'OK' : 'Check permissions' ?></span></div>
    </div>

    <?php if (!$phpOk || !$pdoOk): ?>
      <p class="badge danger" style="margin-bottom:18px;">Your server doesn't meet the requirements above yet — fix those first, then reload this page.</p>
    <?php else: ?>
    <div class="card">
      <h3 style="margin-bottom:14px;">2. Database connection</h3>
      <?php if ($formError): ?><p class="badge danger" style="margin-bottom:16px;"><?= e($formError) ?></p><?php endif; ?>
      <form method="post">
        <input type="hidden" name="action" value="save_db">
        <input type="hidden" name="csrf" value="<?= e($token) ?>">
        <p style="font-size:.85rem;color:var(--ink-faint);margin-bottom:14px;">Create an empty database in phpMyAdmin (or your host's panel) first, then enter its details here — this installer creates all the tables for you.</p>
        <div class="field"><label>Database host</label><input name="db_host" value="<?= e($_POST['db_host'] ?? 'localhost') ?>" required></div>
        <div class="field"><label>Database name</label><input name="db_name" value="<?= e($_POST['db_name'] ?? '') ?>" required></div>
        <div class="field"><label>Database username</label><input name="db_user" value="<?= e($_POST['db_user'] ?? '') ?>" required></div>
        <div class="field"><label>Database password</label><input type="password" name="db_pass" value=""></div>
        <div class="field"><label>Site URL <span style="color:var(--ink-faint);font-weight:400;">(auto-detected — edit if wrong)</span></label><input name="site_url" value="<?= e($_POST['site_url'] ?? $detectedUrl) ?>" required></div>
        <button class="btn btn-primary btn-block" type="submit">Connect &amp; continue</button>
      </form>
    </div>
    <?php endif; ?>

  <?php elseif ($view === 'db_error_unlocked' || $view === 'db_error_locked'): ?>
    <div class="card">
      <h3 style="margin-bottom:14px;">Database connection failed</h3>
      <p class="badge danger" style="margin-bottom:16px;"><?= e($error) ?></p>
      <p style="font-size:.9rem;color:var(--ink-faint);">config.php already exists with saved database settings, but this server can't currently connect with them.</p>
      <?php if ($view === 'db_error_locked'): ?>
        <p style="font-size:.9rem;margin-top:10px;">This site was already installed. This is most likely the database being temporarily unreachable, or credentials that changed on the host side — edit <code>config.php</code> directly to fix it. For safety, the installer won't let you overwrite the configuration of an already-installed site through this page.</p>
      <?php else: ?>
        <form method="post" style="margin-top:16px;">
          <input type="hidden" name="csrf" value="<?= e($token) ?>">
          <button class="btn btn-ghost btn-block" type="submit" formaction="reset.php" onclick="return confirm('This deletes the saved config.php so you can re-enter database details. Continue?');">Start over with different database details</button>
        </form>
      <?php endif; ?>
    </div>

  <?php elseif ($view === 'admin_account'): ?>
    <div class="card">
      <h3 style="margin-bottom:6px;">3. Create your admin account</h3>
      <p style="font-size:.85rem;color:var(--ink-faint);margin-bottom:14px;">This becomes your staff login at <code>/admin/</code>. A few illustrative teammates are added alongside you with the same password, purely so the admin panel isn't empty on day one — rename or remove them once you're in.</p>
      <?php if ($formError): ?><p class="badge danger" style="margin-bottom:16px;"><?= e($formError) ?></p><?php endif; ?>
      <form method="post">
        <input type="hidden" name="action" value="create_admin">
        <input type="hidden" name="csrf" value="<?= e($token) ?>">
        <div class="field"><label>Your name</label><input name="admin_name" value="<?= e($_POST['admin_name'] ?? '') ?>" required></div>
        <div class="field"><label>Your email</label><input type="email" name="admin_email" value="<?= e($_POST['admin_email'] ?? '') ?>" required></div>
        <div class="field"><label>Password</label><input type="password" name="admin_password" minlength="8" required></div>
        <button class="btn btn-primary btn-block" type="submit">Create account &amp; install</button>
      </form>
    </div>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= e($token) ?>">
      <button class="btn btn-ghost btn-block" type="submit" formaction="reset.php" onclick="return confirm('This deletes the saved config.php so you can connect to a different database. Nothing has been installed yet, so this is safe. Continue?');">Start over with different database details</button>
    </form>

  <?php elseif ($view === 'pending_locked'): ?>
    <div class="card">
      <h3 style="margin-bottom:6px;">Update available</h3>
      <p style="font-size:.9rem;color:var(--ink-faint);margin-bottom:0;">There's a pending update for this site. Sign in to the admin panel first, then reload this page to run it.</p>
      <?php if ($formError): ?><p class="badge danger" style="margin-top:16px;"><?= e($formError) ?></p><?php endif; ?>
      <a href="../admin/login.php" class="btn btn-primary btn-block" style="margin-top:16px;">Staff sign in</a>
    </div>

  <?php elseif ($view === 'manage'): ?>
    <div class="card" style="text-align:center;">
      <div class="blob-icon lg soft" style="margin:0 auto 14px;"><svg class="icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
      <h3>This site is already installed.</h3>
      <p style="margin-bottom:20px;color:var(--ink-faint);">Site is installed at <?= e(defined('SITE_URL') ? SITE_URL : $detectedUrl) ?></p>
      <div class="flex gap-12" style="justify-content:center;flex-wrap:wrap;">
        <a href="../" class="btn btn-primary">View the site</a>
        <a href="../admin/" class="btn btn-ghost">Go to admin</a>
      </div>
    </div>

    <div class="card" style="border-color:var(--accent-1);">
      <h3 style="margin-bottom:6px;">Option A — Update</h3>
      <?php if ($formError && ($_POST['action'] ?? '') === 'run_migrations'): ?><p class="badge danger" style="margin-bottom:14px;"><?= e($formError) ?></p><?php endif; ?>
      <?php if (!empty($pending)): ?>
        <p style="font-size:.9rem;color:var(--ink-faint);margin-bottom:14px;"><?= count($pending) ?> pending update<?= count($pending) === 1 ? '' : 's' ?> found. Nothing you already have will be dropped or overwritten.</p>
        <ul style="margin-bottom:16px;padding-left:20px;font-size:.88rem;">
          <?php foreach ($pending as $f): ?><li><?= e(basename($f, '.php')) ?></li><?php endforeach; ?>
        </ul>
        <form method="post">
          <input type="hidden" name="action" value="run_migrations">
          <input type="hidden" name="csrf" value="<?= e($token) ?>">
          <button class="btn btn-primary btn-block" type="submit">Run update<?= count($pending) === 1 ? '' : 's' ?></button>
        </form>
      <?php else: ?>
        <p class="badge success" style="margin-bottom:0;">Everything is already up to date — nothing pending.</p>
      <?php endif; ?>
    </div>

    <div class="card" style="border-color:var(--danger);">
      <h3 style="margin-bottom:6px;color:var(--danger);">Option B — Fresh install</h3>
      <p style="font-size:.85rem;color:var(--ink-faint);margin-bottom:14px;">Permanently deletes every business, customer, ticket, project and setting in this database, then sends you back to step 1 to create a brand new admin account from scratch. This cannot be undone — if this site has real data on it, back it up first.</p>
      <?php if ($formError && ($_POST['action'] ?? '') === 'wipe_reinstall'): ?><p class="badge danger" style="margin-bottom:14px;"><?= e($formError) ?></p><?php endif; ?>
      <form method="post" onsubmit="return confirm('Really delete everything in this database? This cannot be undone.');">
        <input type="hidden" name="action" value="wipe_reinstall">
        <input type="hidden" name="csrf" value="<?= e($token) ?>">
        <div class="field"><label>Type <code>DELETE EVERYTHING</code> to confirm</label><input name="confirm_text" placeholder="DELETE EVERYTHING" required></div>
        <button class="btn btn-block" style="background:var(--danger);color:#fff;" type="submit">Wipe database &amp; reinstall</button>
      </form>
    </div>

    <div class="card" style="border-color:var(--danger);">
      <h3 style="margin-bottom:6px;color:var(--danger);">Delete the install folder</h3>
      <p style="font-size:.85rem;color:var(--ink-faint);margin-bottom:14px;">Once you're done setting up or updating, you can remove this <code>install/</code> folder from the server. It isn't required for the site to keep running — future updates arrive by re-uploading a zip that includes a fresh copy of it. This step is optional and permanent: you'll need to re-upload the folder to run an update or wipe the database again later.</p>
      <?php if ($formError && ($_POST['action'] ?? '') === 'delete_install'): ?><p class="badge danger" style="margin-bottom:14px;"><?= e($formError) ?></p><?php endif; ?>
      <form method="post" onsubmit="return confirm('Delete the install/ folder now? You will need to re-upload it to run future updates.');">
        <input type="hidden" name="action" value="delete_install">
        <input type="hidden" name="csrf" value="<?= e($token) ?>">
        <button class="btn btn-block" style="background:var(--danger);color:#fff;" type="submit">Delete install/ folder</button>
      </form>
    </div>

  <?php else: /* done — already installed, visiting anonymously, nothing pending */ ?>
    <div class="card" style="text-align:center;">
      <div class="blob-icon lg soft" style="margin:0 auto 14px;"><svg class="icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
      <h3>You're live.</h3>
      <p style="margin-bottom:20px;color:var(--ink-faint);">Site is installed at <?= e(defined('SITE_URL') ? SITE_URL : $detectedUrl) ?></p>
      <div class="flex gap-12" style="justify-content:center;flex-wrap:wrap;">
        <a href="../" class="btn btn-primary">View the site</a>
        <a href="../admin/" class="btn btn-ghost">Staff sign in</a>
      </div>
      <p style="margin-top:20px;font-size:.82rem;color:var(--ink-faint);">This <code>install/</code> folder is still here — it isn't deleted automatically. Sign in to <code>/admin/</code>, then come back to this page to remove it with one click whenever you're ready.</p>
    </div>
  <?php endif; ?>
</main>
</body>
</html>
