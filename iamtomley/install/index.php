<?php
/**
 * iamtomley — installation wizard (3 steps: Requirements → Database → Admin).
 * Checks requirements, configures storage, creates the admin account,
 * seeds content, then locks itself.
 */
declare(strict_types=1);

$APP   = dirname(__DIR__);
$DATA  = $APP . '/data';
$LOCAL = $DATA . '/config.local.php';
$LOCK  = $DATA . '/installed.lock';

$BASE = str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/install/index.php')));
$BASE = ($BASE === '/' || $BASE === '.' || $BASE === '\\') ? '' : rtrim($BASE, '/');
function ee($s): string { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function asset(string $p): string { global $BASE; return $BASE . $p; }

if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; script-src 'self' 'unsafe-inline'; img-src 'self' data:; form-action 'self'; frame-ancestors 'self'");
}
if (session_status() !== PHP_SESSION_ACTIVE) { session_name('iamtomley_setup'); @session_start(); }
if (empty($_SESSION['itok'])) { $_SESSION['itok'] = bin2hex(random_bytes(16)); }
$ITOK = $_SESSION['itok'];

$already = is_file($LOCK);

$req = [
    'PHP ≥ 8.0'             => version_compare(PHP_VERSION, '8.0.0', '>='),
    'PDO extension'         => extension_loaded('pdo'),
    'pdo_sqlite (default)'  => extension_loaded('pdo_sqlite'),
    'pdo_mysql (for MySQL)' => extension_loaded('pdo_mysql'),
    'GD (image uploads)'    => extension_loaded('gd'),
    'data/ writable'        => is_dir($DATA) ? is_writable($DATA) : is_writable($APP),
];
$reqOk = $req['PHP ≥ 8.0'] && $req['PDO extension']
    && ($req['pdo_sqlite (default)'] || $req['pdo_mysql (for MySQL)'])
    && $req['data/ writable'];

$errors = [];
$done   = false;
$setup  = $_SESSION['setup'] ?? [
    'db_driver' => 'sqlite', 'db_host' => 'localhost', 'db_name' => '', 'db_user' => '', 'db_pass' => '',
    'brand_name' => 'iamtomley', 'site_url' => '', 'base_url' => '', 'admin_user' => 'admin',
];
$step = 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$already) {
    $step = max(1, min(3, (int) ($_POST['step'] ?? 1)));
    if (!hash_equals($ITOK, (string) ($_POST['itok'] ?? ''))) {
        $errors[] = 'Your setup session expired. Please try again.';
    } else {
        $nav = ($_POST['nav'] ?? 'next') === 'back' ? 'back' : 'next';

        // Capture the fields of the step being submitted.
        if ($step === 2) {
            $setup['db_driver'] = (($_POST['db_driver'] ?? 'sqlite') === 'mysql') ? 'mysql' : 'sqlite';
            $setup['db_host'] = trim((string) ($_POST['db_host'] ?? 'localhost'));
            $setup['db_name'] = trim((string) ($_POST['db_name'] ?? ''));
            $setup['db_user'] = trim((string) ($_POST['db_user'] ?? ''));
            $setup['db_pass'] = (string) ($_POST['db_pass'] ?? '');
        } elseif ($step === 3) {
            $setup['brand_name'] = trim((string) ($_POST['brand_name'] ?? ''));
            $setup['site_url']   = trim((string) ($_POST['site_url'] ?? ''));
            $setup['base_url']   = trim((string) ($_POST['base_url'] ?? ''));
            $setup['admin_user'] = trim((string) ($_POST['admin_user'] ?? ''));
            $setup['mode']       = (($_POST['mode'] ?? 'fresh') === 'migrate') ? 'migrate' : 'fresh';
        }
        $_SESSION['setup'] = $setup;

        if ($nav === 'back') {
            $step = max(1, $step - 1);
        } elseif ($step === 1) {
            $reqOk ? $step = 2 : $errors[] = 'Please resolve the required items before continuing.';
        } elseif ($step === 2) {
            if ($setup['db_driver'] === 'mysql') {
                if ($setup['db_name'] === '') {
                    $errors[] = 'MySQL database name is required.';
                } else {
                    try {
                        new PDO(
                            "mysql:host={$setup['db_host']};dbname={$setup['db_name']};charset=utf8mb4",
                            $setup['db_user'], $setup['db_pass'],
                            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                        );
                    } catch (Throwable $ex) {
                        $errors[] = 'Could not connect to MySQL: ' . $ex->getMessage();
                    }
                }
            }
            // Detect whether this database already has app data (migrate vs fresh).
            if (!$errors) {
                $setup['has_data'] = false;
                try {
                    if ($setup['db_driver'] === 'mysql') {
                        $t = new PDO(
                            "mysql:host={$setup['db_host']};dbname={$setup['db_name']};charset=utf8mb4",
                            $setup['db_user'], $setup['db_pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                        );
                    } else {
                        $sq = $DATA . '/app.db';
                        $t = is_file($sq) ? new PDO('sqlite:' . $sq) : null;
                    }
                    if ($t) {
                        $setup['has_data'] = (int) $t->query("SELECT COUNT(*) FROM settings")->fetchColumn() > 0;
                    }
                } catch (Throwable $e) {
                    $setup['has_data'] = false;
                }
                $_SESSION['setup'] = $setup;
            }
            $step = $errors ? 2 : 3;
        } elseif ($step === 3) {
            $mode    = $setup['mode'] ?? 'fresh';
            $adminU  = $setup['admin_user'];
            $adminP  = (string) ($_POST['admin_pass'] ?? '');
            $adminP2 = (string) ($_POST['admin_pass2'] ?? '');
            if ($mode === 'fresh') {
                if ($adminU === '')       { $errors[] = 'Admin username is required.'; }
                if (strlen($adminP) < 6)  { $errors[] = 'Admin password must be at least 6 characters.'; }
                if ($adminP !== $adminP2) { $errors[] = 'Passwords do not match.'; }
            } else { // migrate: admin fields optional (only to reset the login)
                if ($adminP !== '' && strlen($adminP) < 6) { $errors[] = 'New password must be at least 6 characters.'; }
                if ($adminP !== '' && $adminP !== $adminP2) { $errors[] = 'Passwords do not match.'; }
            }

            if (!$errors) {
                if (!is_dir($DATA)) { @mkdir($DATA, 0775, true); }
                $L = ["<?php", "// Generated by the iamtomley installer. Safe to edit."];
                $L[] = "defined('DB_DRIVER') || define('DB_DRIVER', " . var_export($setup['db_driver'], true) . ");";
                if ($setup['db_driver'] === 'mysql') {
                    $L[] = "defined('DB_HOST') || define('DB_HOST', " . var_export($setup['db_host'], true) . ");";
                    $L[] = "defined('DB_NAME') || define('DB_NAME', " . var_export($setup['db_name'], true) . ");";
                    $L[] = "defined('DB_USER') || define('DB_USER', " . var_export($setup['db_user'], true) . ");";
                    $L[] = "defined('DB_PASS') || define('DB_PASS', " . var_export($setup['db_pass'], true) . ");";
                }
                if ($setup['base_url'] !== '') {
                    $L[] = "defined('APP_BASE_URL') || define('APP_BASE_URL', " . var_export(rtrim($setup['base_url'], '/'), true) . ");";
                }
                if (@file_put_contents($LOCAL, implode("\n", $L) . "\n") === false) {
                    $errors[] = 'Could not write data/config.local.php — check folder permissions.';
                }
            }

            if (!$errors) {
                define('DB_DRIVER', $setup['db_driver']);
                if ($setup['db_driver'] === 'mysql') {
                    define('DB_HOST', $setup['db_host']); define('DB_NAME', $setup['db_name']);
                    define('DB_USER', $setup['db_user']); define('DB_PASS', $setup['db_pass']);
                }
                if ($setup['base_url'] !== '') { define('APP_BASE_URL', rtrim($setup['base_url'], '/')); }

                try {
                    require $APP . '/includes/functions.php';
                    $pdo = db();   // connect + ensure schema (seeds only if empty)

                    if ($mode === 'fresh') {
                        // Wipe everything and seed a clean site with the entered admin.
                        drop_app_tables($pdo);
                        migrate($pdo);
                        $minId = (int) $pdo->query("SELECT MIN(id) FROM users")->fetchColumn();
                        $pdo->prepare("UPDATE users SET username = ?, password_hash = ? WHERE id = ?")
                            ->execute([$adminU, password_hash($adminP, PASSWORD_DEFAULT), $minId]);
                        if ($setup['brand_name'] !== '') { set_setting('brand_name', $setup['brand_name']); }
                        if ($setup['site_url'] !== '')   { set_setting('seo_canonical', $setup['site_url']); }
                    } else {
                        // Migrate: keep existing content/users; schema is already up to date.
                        $userCount = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
                        if ($userCount === 0 && $adminU !== '' && $adminP !== '') {
                            $pdo->prepare("INSERT INTO users (username, password_hash) VALUES (?, ?)")
                                ->execute([$adminU, password_hash($adminP, PASSWORD_DEFAULT)]);
                        } elseif ($adminP !== '') {
                            // Optional: reset the primary admin's login
                            $minId = (int) $pdo->query("SELECT MIN(id) FROM users")->fetchColumn();
                            if ($adminU !== '') {
                                $pdo->prepare("UPDATE users SET username = ?, password_hash = ? WHERE id = ?")
                                    ->execute([$adminU, password_hash($adminP, PASSWORD_DEFAULT), $minId]);
                            } else {
                                $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")
                                    ->execute([password_hash($adminP, PASSWORD_DEFAULT), $minId]);
                            }
                        }
                    }

                    @file_put_contents($LOCK, "installed " . gmdate('c') . "\n");
                    $done = true;
                    unset($_SESSION['setup']);
                } catch (Throwable $ex) {
                    @unlink($LOCAL);
                    $errors[] = 'Setup failed: ' . $ex->getMessage();
                    $step = 2; // most failures are DB-related
                }
            } else {
                $step = 3;
            }
        }
    }
} elseif (isset($_GET['step'])) {
    $step = max(1, min(3, (int) $_GET['step']));
}

$in = fn(string $k) => ee($setup[$k] ?? '');
$steps = [1 => 'Requirements', 2 => 'Database', 3 => 'Admin & site'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="robots" content="noindex, nofollow" />
  <title>Install · iamtomley</title>
  <link rel="icon" type="image/svg+xml" href="<?= ee(asset('/favicon/favicon.svg')) ?>" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="<?= ee(asset('/admin/assets/admin.css')) ?>" />
  <style>
    .install-wrap{max-width:640px;margin:0 auto;padding:2.5rem 1.25rem;}
    .req{display:flex;justify-content:space-between;padding:.55rem .2rem;border-bottom:1px solid var(--border);font-size:.9rem;} .req:last-child{border:none;}
    .ok{color:var(--success);} .no{color:var(--danger);}
    .stepper{display:flex;align-items:center;gap:.5rem;margin:1.25rem 0 1.75rem;}
    .stepper .st{display:flex;align-items:center;gap:.55rem;flex:1;}
    .stepper .num{width:30px;height:30px;flex-shrink:0;border-radius:50%;display:grid;place-items:center;font-weight:700;font-size:.85rem;
      border:1px solid var(--border);background:var(--glass);color:var(--text-mute);transition:all .25s;}
    .stepper .st.active .num{background:var(--grad);color:#04120c;border-color:transparent;box-shadow:0 6px 18px rgba(0,255,179,.3);}
    .stepper .st.done .num{background:rgba(0,255,179,.12);color:var(--mint);border-color:rgba(0,255,179,.3);}
    .stepper .lbl{font-size:.82rem;color:var(--text-mute);white-space:nowrap;} .stepper .st.active .lbl{color:var(--text);font-weight:600;}
    .stepper .bar{height:1px;flex:1;background:var(--border);min-width:12px;}
    .mysql-only{display:none;} body.show-mysql .mysql-only{display:block;}
    .wiz-actions{display:flex;justify-content:space-between;gap:.6rem;margin-top:.5rem;}
    @media(max-width:560px){.stepper .lbl{display:none;}}
  </style>
</head>
<body>
<div class="install-wrap">
  <div class="brand" style="font-size:1.5rem;margin-bottom:.35rem"><span class="dot"></span>iamtomley</div>

  <?php if ($already): ?>
    <div class="panel">
      <h2>Already installed</h2>
      <p class="small" style="line-height:1.7">This site is already set up. For security, delete the <span class="pill">install/</span> folder. To run setup again, remove <span class="pill">data/installed.lock</span>.</p>
      <div class="actions" style="margin-top:1rem">
        <a class="btn btn-primary" href="<?= ee(asset('/index.php')) ?>">Go to site</a>
        <a class="btn btn-ghost" href="<?= ee(asset('/admin/')) ?>">Admin panel</a>
      </div>
    </div>

  <?php elseif ($done): ?>
    <div class="panel">
      <h2 style="color:var(--success)">🎉 Installation complete</h2>
      <p class="small" style="line-height:1.7">Your site is ready. Please <strong>delete the <span class="pill">install/</span> folder</strong> now for security.</p>
      <div class="actions" style="margin-top:1rem">
        <a class="btn btn-primary" href="<?= ee(asset('/index.php')) ?>">View your site</a>
        <a class="btn btn-ghost" href="<?= ee(asset('/admin/login.php')) ?>">Sign in to admin</a>
      </div>
    </div>

  <?php else: ?>
    <div class="stepper">
      <?php $i = 0; foreach ($steps as $n => $label): $i++; ?>
        <div class="st <?= $n === $step ? 'active' : ($n < $step ? 'done' : '') ?>">
          <span class="num"><?= $n < $step ? '✓' : $n ?></span><span class="lbl"><?= ee($label) ?></span>
        </div>
        <?php if ($i < count($steps)): ?><span class="bar"></span><?php endif; ?>
      <?php endforeach; ?>
    </div>

    <?php if ($errors): ?>
      <div class="alert alert-error"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <span><?= implode('<br>', array_map('ee', $errors)) ?></span></div>
    <?php endif; ?>

    <form method="post" action="<?= ee(asset('/install/')) ?>">
      <input type="hidden" name="itok" value="<?= ee($ITOK) ?>">
      <input type="hidden" name="step" value="<?= (int) $step ?>">

      <?php if ($step === 1): ?>
        <div class="panel">
          <h2>Step 1 — Server requirements</h2>
          <?php foreach ($req as $label => $ok): ?>
            <div class="req"><span><?= ee($label) ?></span><span class="<?= $ok ? 'ok' : 'no' ?>"><?= $ok ? '✓ OK' : '✕ Missing' ?></span></div>
          <?php endforeach; ?>
          <?php if (!$reqOk): ?><div class="alert alert-error" style="margin-top:1rem">Resolve the required items above, then reload this page.</div><?php endif; ?>
        </div>
        <div class="wiz-actions">
          <span></span>
          <button class="btn btn-primary" type="submit" name="nav" value="next" <?= $reqOk ? '' : 'disabled' ?>>Next →</button>
        </div>

      <?php elseif ($step === 2): ?>
        <div class="panel">
          <h2>Step 2 — Database</h2>
          <div class="field">
            <label>Storage</label>
            <select name="db_driver" id="dbDriver">
              <option value="sqlite" <?= $setup['db_driver'] === 'sqlite' ? 'selected' : '' ?>>SQLite — zero config (recommended)</option>
              <option value="mysql" <?= $setup['db_driver'] === 'mysql' ? 'selected' : '' ?>>MySQL / MariaDB</option>
            </select>
          </div>
          <div class="mysql-only">
            <div class="grid-2">
              <div class="field"><label>MySQL host</label><input type="text" name="db_host" value="<?= $in('db_host') ?>" placeholder="localhost"></div>
              <div class="field"><label>Database name</label><input type="text" name="db_name" value="<?= $in('db_name') ?>"></div>
              <div class="field"><label>Username</label><input type="text" name="db_user" value="<?= $in('db_user') ?>"></div>
              <div class="field"><label>Password</label><input type="password" name="db_pass" value="<?= $in('db_pass') ?>"></div>
            </div>
            <div class="hint">The database must already exist; tables (utf8mb4) are created automatically.</div>
          </div>
        </div>
        <div class="wiz-actions">
          <button class="btn btn-ghost" type="submit" name="nav" value="back">← Back</button>
          <button class="btn btn-primary" type="submit" name="nav" value="next">Next →</button>
        </div>

      <?php else: ?>
        <?php $modeDefault = $setup['mode'] ?? (!empty($setup['has_data']) ? 'migrate' : 'fresh'); ?>
        <div class="panel">
          <h2>Step 3 — Admin &amp; site</h2>

          <?php if (!empty($setup['has_data'])): ?>
            <div class="alert alert-warn" style="margin-bottom:1.1rem">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
              This database already contains a site. Choose how to proceed below.
            </div>
          <?php endif; ?>

          <div class="field">
            <label>Setup mode</label>
            <div class="row-inline" style="gap:1.5rem;flex-wrap:wrap;margin-top:.35rem">
              <label class="row-inline" style="gap:.5rem;cursor:pointer"><input type="radio" name="mode" value="fresh" <?= $modeDefault === 'fresh' ? 'checked' : '' ?>> <span>Fresh install</span></label>
              <label class="row-inline" style="gap:.5rem;cursor:pointer"><input type="radio" name="mode" value="migrate" <?= $modeDefault === 'migrate' ? 'checked' : '' ?>> <span>Migrate existing</span></label>
            </div>
            <div class="hint"><strong>Fresh install</strong> erases any existing content in this database and seeds a clean site. <strong>Migrate</strong> keeps your existing data, users and login — it only updates the schema, so no admin details are needed.</div>
          </div>

          <div class="field"><label>Base path override (optional)</label><input type="text" name="base_url" value="<?= $in('base_url') ?>" placeholder="auto — e.g. /portfolio"><div class="hint">Only if the site lives in a subfolder and auto-detection fails.</div></div>

          <div id="adminFields">
            <div class="grid-2">
              <div class="field"><label>Brand / name</label><input type="text" name="brand_name" value="<?= $in('brand_name') ?>"></div>
              <div class="field"><label>Site URL (optional)</label><input type="url" name="site_url" value="<?= $in('site_url') ?>" placeholder="https://www.iamtomley.com/"></div>
            </div>
            <div class="grid-2">
              <div class="field"><label>Admin username</label><input type="text" name="admin_user" value="<?= $in('admin_user') ?>"></div>
              <div class="field"></div>
              <div class="field"><label>Password</label><input type="password" name="admin_pass" autocomplete="new-password"><div class="hint">Min 6 characters.</div></div>
              <div class="field"><label>Confirm password</label><input type="password" name="admin_pass2" autocomplete="new-password"></div>
            </div>
          </div>
          <div id="migrateNote" class="alert alert-success" style="display:none"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><polyline points="20 6 9 17 4 12"/></svg>Migrate keeps your existing content and admin login — nothing else to enter. Just click Install.</div>
        </div>
        <div class="wiz-actions">
          <button class="btn btn-ghost" type="submit" name="nav" value="back">← Back</button>
          <button class="btn btn-primary" type="submit" name="nav" value="next">Install now ✓</button>
        </div>
      <?php endif; ?>
    </form>

    <script>
      (function () {
        var sel = document.getElementById('dbDriver');
        if (sel) {
          var syncDb = function () { document.body.classList.toggle('show-mysql', sel.value === 'mysql'); };
          sel.addEventListener('change', syncDb); syncDb();
        }
        // Hide admin fields when Migrate is chosen — nothing to ask.
        var af = document.getElementById('adminFields');
        var note = document.getElementById('migrateNote');
        var modes = document.querySelectorAll('input[name="mode"]');
        function syncMode() {
          var m = document.querySelector('input[name="mode"]:checked');
          var migrate = m && m.value === 'migrate';
          if (af) af.style.display = migrate ? 'none' : '';
          if (note) note.style.display = migrate ? 'flex' : 'none';
        }
        modes.forEach(function (r) { r.addEventListener('change', syncMode); });
        syncMode();
      })();
    </script>
  <?php endif; ?>
</div>
</body>
</html>
