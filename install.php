<?php
declare(strict_types=1);

/**
 * SignalMasterAi - Step-by-step installation wizard.
 *
 * Step 1: Requirements check
 * Step 2: Database configuration (SQLite or MySQL)
 * Step 3: Admin account
 * Step 4: Site settings
 * Step 5: Install & finish
 */

define('SMA_INSTALLER', true);
$config = require __DIR__ . '/config.php';

spl_autoload_register(function (string $class): void {
    $prefix = 'SignalMasterAi\\';
    if (str_starts_with($class, $prefix)) {
        $file = __DIR__ . '/src/' . substr($class, strlen($prefix)) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

session_name('sma_install');
// The install session holds database credentials and the administrator
// password being set - the most sensitive data this application ever puts in
// a session, started with none of the flags the other two use.
session_set_cookie_params(\SignalMasterAi\Request::sessionCookieParams());
session_start();

function e(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

$justInstalled = false;
if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['install_done']) && isset($_GET['done'])) {
    // Consumed once. A later visitor - or the same one on a refresh - is back
    // to the locked page, so the flag cannot become a permanent doorway.
    unset($_SESSION['install_done']);
    $justInstalled = true;
}
if (is_file(SMA_LOCK_FILE) && !$justInstalled) {
    // Already installed - never allow re-running the installer.
    //
    // This used to be its own tiny hand-rolled page - a bare font-family:
    // sans-serif and five hardcoded hex colours guessing at the real dark
    // theme rather than drawing it, on the one screen most likely to be
    // seen right after the site's actual "not installed" holding page or
    // the wizard itself. Chrome::fonts()/Chrome::css() were already an
    // autoload away; using them means this notice gets the real Instrument
    // theme - dark panel, ruled background, Inter Tight - instead of a
    // second, approximate one. Doctype, viewport and title stay written out
    // here rather than through Chrome::head(), matching how the wizard's
    // own <head> below does it (line ~458) - a DB-free page's meta tags are
    // its own literal text, only the shared tokens/components come from
    // Chrome.
    http_response_code(403);
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
       . '<meta name="viewport" content="width=device-width, initial-scale=1">'
       . '<title>Already installed</title>'
       . \SignalMasterAi\Chrome::fonts() . '<style>' . \SignalMasterAi\Chrome::css() . '</style>'
       . '</head><body><div class="sheet"><main class="card">'
       . '<h2>SignalMasterAi is already installed</h2>'
       . '<p>To reinstall, delete <code>data/installed.lock</code> on the server first.</p>'
       . '<p><a href="index.php">Go to the site</a> &middot; <a href="admin/login.php">Admin panel</a></p>'
       . '</main></div></body></html>';
    exit;
}

$step = max(1, min(4, (int)($_GET['step'] ?? 1)));
$errors = [];
$st = &$_SESSION['install'];
if (!is_array($st ?? null)) {
    $st = [];
}

// ---------------------------------------------------------------- helpers
function requirements(): array
{
    $dataDir = SMA_DATA_DIR;
    if (!is_dir($dataDir)) {
        @mkdir($dataDir, 0775, true);
    }
    return [
        ['PHP version >= 8.0', PHP_VERSION_ID >= 80000, 'Found ' . PHP_VERSION],
        ['PDO extension', extension_loaded('pdo'), 'Required for database access'],
        ['pdo_sqlite driver', extension_loaded('pdo_sqlite'), 'Needed for the zero-config SQLite option'],
        ['pdo_mysql driver (optional)', extension_loaded('pdo_mysql'), 'Only needed if you choose MySQL'],
        ['cURL extension', extension_loaded('curl'), 'Used to fetch chart data from the free market API'],
        ['JSON support', function_exists('json_encode'), 'Used throughout the app'],
        // Optional in the sense that the site installs and runs without it -
        // and named here anyway, because without it the API keys and passwords
        // you are about to type sit in the database as readable text. An
        // operator choosing a host deserves to see that before they choose.
        ['OpenSSL (optional)', function_exists('openssl_encrypt'),
         'Encrypts stored API keys and passwords; without it they are saved as plain text'],
        ['data/ directory writable', is_dir($dataDir) && is_writable($dataDir), $dataDir],
    ];
}

function requirementsOk(): bool
{
    foreach (requirements() as [$name, $ok]) {
        if (!$ok && !str_contains($name, 'optional')) {
            return false;
        }
    }
    return true;
}

/** Open a PDO connection for the chosen driver (throws on failure). */
function dbConnect(array $db, array $config): PDO
{
    if ($db['driver'] === 'mysql') {
        $m = $db['mysql'];
        return new PDO(
            "mysql:host={$m['host']};port={$m['port']};dbname={$m['database']};charset=utf8mb4",
            $m['username'], $m['password'],
            [PDO::ATTR_TIMEOUT => 5, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }
    $pdo = new PDO('sqlite:' . $config['db']['sqlite']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $pdo;
}

/**
 * Mail settings already in the target database, or [] if there are none.
 *
 * Re-running the installer on a working site asked for the mail server again,
 * with empty boxes and a warning about password resets having no way out - to
 * an operator who had configured it months earlier. Blank fields are not a
 * neutral question here: they read as "this is not set up", and the obvious
 * response is to type the details in again, from memory, which is how a
 * working mail server gets replaced with a typo.
 *
 * Nothing was ever overwritten by leaving it blank - the write block below has
 * always skipped an empty host - so this changes what is ASKED, not what is
 * saved. The password is never read back; it is not needed to report that one
 * exists, and reading it would only create somewhere new for it to leak.
 */
function existingSmtp(array $db, array $config): array
{
    try {
        $pdo = dbConnect($db, $config);
        $stmt = $pdo->prepare("SELECT skey, svalue FROM settings
                               WHERE skey IN ('smtp_mode','smtp_host','smtp_from_email','smtp_port')");
        $stmt->execute();
        $s = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $s[$r['skey']] = (string)$r['svalue'];
        }
        $mode = $s['smtp_mode'] ?? 'off';
        if ($mode === 'off' || $mode === '') {
            return [];
        }
        return [
            'mode' => $mode,
            'host' => $s['smtp_host'] ?? '',
            'port' => $s['smtp_port'] ?? '',
            'from' => $s['smtp_from_email'] ?? '',
        ];
    } catch (Throwable $e) {
        return [];          // no database yet, or no settings table - ask normally
    }
}

/** Does the target database already hold a SignalMasterAi installation? */
function detectExisting(array $db, array $config): bool
{
    try {
        $pdo = dbConnect($db, $config);
        $pdo->query('SELECT COUNT(*) FROM settings');
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

/** How many admin accounts an existing installation already has. */
function existingAdminCount(array $db, array $config): int
{
    try {
        return (int)dbConnect($db, $config)->query('SELECT COUNT(*) FROM users')->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

/** Every table this app owns (used by the fresh-wipe option). */
const SMA_TABLES = ['users', 'symbols', 'members', 'member_alerts', 'plans',
    'payment_methods', 'payments', 'push_subs', 'candles', 'fetch_log',
    'signals', 'ta_knowledge', 'settings', 'news_sources', 'news_items',
    'calendar_events'];

// ---------------------------------------------------------------- actions
$askDataMode = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postStep = (int)($_POST['step'] ?? 0);

    if ($postStep === 2) {
        // Second round: the keep/wipe confirmation for an existing database.
        if (isset($_POST['data_mode']) && !empty($st['db'])) {
            $st['data_mode'] = $_POST['data_mode'] === 'wipe' ? 'wipe' : 'keep';
            // Keeping the data with an admin already in place: the existing
            // login still works, so the admin step becomes a CHOICE rather
            // than a skip. Skipping it outright left an operator who had
            // forgotten the password with no way through the installer.
            unset($st['admin']['keep']);
            header('Location: install.php?step=3');
            exit;
        }

        $driver = $_POST['driver'] === 'mysql' ? 'mysql' : 'sqlite';
        $db = ['driver' => $driver];
        if ($driver === 'mysql') {
            $db['mysql'] = [
                'host'     => trim($_POST['mysql_host'] ?? '127.0.0.1'),
                'port'     => (int)($_POST['mysql_port'] ?? 3306),
                'database' => trim($_POST['mysql_db'] ?? ''),
                'username' => trim($_POST['mysql_user'] ?? ''),
                'password' => (string)($_POST['mysql_pass'] ?? ''),
                'charset'  => 'utf8mb4',
            ];
        }
        try {
            dbConnect($db, $config)->query('SELECT 1');
        } catch (Throwable $ex) {
            $errors[] = ($driver === 'mysql' ? 'MySQL connection failed: ' : 'SQLite test failed: ') . $ex->getMessage();
        }
        if (!$errors) {
            $st['db'] = $db;
            if (detectExisting($db, $config)) {
                // Existing installation found - ask what to do with the data.
                $askDataMode = true;
                $step = 2;
            } else {
                $st['data_mode'] = 'keep';
                header('Location: install.php?step=3');
                exit;
            }
        } else {
            $step = 2;
        }
    }

    if ($postStep === 3) {
        // Site name and mail server are optional here and fully editable in
        // the admin panel afterwards - but asking once, at the only moment the
        // operator is definitely paying attention, saves the common case of a
        // site that runs for a week before anyone notices no email ever left.
        $st['site'] = ['name' => mb_substr(trim((string)($_POST['site_name'] ?? '')), 0, 60)];
        $st['smtp'] = [];
        if (trim((string)($_POST['smtp_host'] ?? '')) !== '') {
            $st['smtp'] = [
                'host' => trim((string)$_POST['smtp_host']),
                'port' => max(1, min(65535, (int)($_POST['smtp_port'] ?? 587))),
                'user' => trim((string)($_POST['smtp_user'] ?? '')),
                'pass' => (string)($_POST['smtp_pass'] ?? ''),
                'from' => trim((string)($_POST['smtp_from'] ?? '')),
                'enc'  => in_array($_POST['smtp_enc'] ?? 'tls', ['tls', 'ssl', 'none'], true)
                    ? (string)$_POST['smtp_enc'] : 'tls',
            ];
            if ($st['smtp']['from'] !== '' && !filter_var($st['smtp']['from'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'The "from" address does not look like an email address.';
            }
        }

        if (($_POST['admin_mode'] ?? '') === 'keep') {
            $st['admin'] = ['keep' => true];
            if (!$errors) {
                header('Location: install.php?step=4');
                exit;
            }
        } else {
            $user = trim($_POST['admin_user'] ?? '');
            $pass = (string)($_POST['admin_pass'] ?? '');
            $pass2 = (string)($_POST['admin_pass2'] ?? '');
            if (!preg_match('/^[A-Za-z0-9_.-]{3,32}$/', $user)) {
                $errors[] = 'Username must be 3-32 characters (letters, digits, _ . -).';
            }
            if (strlen($pass) < 8) {
                $errors[] = 'Password must be at least 8 characters.';
            }
            if ($pass !== $pass2) {
                $errors[] = 'Passwords do not match.';
            }
            if (!$errors) {
                $st['admin'] = ['user' => $user, 'pass' => $pass];
                header('Location: install.php?step=4');
                exit;
            }
        }
        $step = 3;
    }

    if ($postStep === 4) {
        // Install. Everything else (site texts, symbols, email, APIs,
        // payments, appearance...) is configured in the admin panel.
        if (!requirementsOk() || empty($st['db']) || empty($st['admin'])) {
            $errors[] = 'Missing wizard data - please restart the installer.';
            $step = 1;
        } else {
            try {
                if (!is_dir(SMA_DATA_DIR)) {
                    mkdir(SMA_DATA_DIR, 0775, true);
                }
                // 1. Persist installer choices
                $local = "<?php\nreturn " . var_export(['db' => $st['db']], true) . ";\n";
                if (file_put_contents(SMA_DATA_DIR . '/config.local.php', $local) === false) {
                    throw new RuntimeException('Could not write data/config.local.php');
                }

                // 2. Fresh-install wipe if the admin chose it: drop every
                //    app table so seeding starts from a clean slate.
                $config = require __DIR__ . '/config.php';
                if (($st['data_mode'] ?? 'keep') === 'wipe') {
                    $wpdo = dbConnect($st['db'], $config);
                    foreach (SMA_TABLES as $t) {
                        $wpdo->exec("DROP TABLE IF EXISTS `$t`");
                    }
                    $wpdo = null;
                }

                // 3. Boot DB with the chosen driver (creates schema + defaults)
                \SignalMasterAi\Database::boot($config);
                $pdo = \SignalMasterAi\Database::pdo();

                // 3. Replace the seeded admin with the chosen account - unless
                //    the wizard kept an existing installation's admin login.
                if (empty($st['admin']['keep'])) {
                    $pdo->exec('DELETE FROM users');
                    $pdo->prepare('INSERT INTO users (username, password_hash) VALUES (?, ?)')
                        ->execute([$st['admin']['user'], password_hash($st['admin']['pass'], PASSWORD_DEFAULT)]);
                    // THE GENERATED PASSWORD IS NOW WRONG, SO ITS NOTE MUST GO.
                    //
                    // Booting the database seeds an administrator with a random
                    // password and writes it to data/FIRST-LOGIN-xxxx.txt. The
                    // line above then deletes that account and puts the
                    // operator's own in its place - and the note stayed, on
                    // every install ever done through this wizard.
                    //
                    // Two consequences, both real: a credential-shaped file
                    // sits in the one directory this application cannot assume
                    // is protected, and the admin dashboard opens with a red
                    // "this account still has its generated password" warning
                    // naming a file whose password does not work. A false alarm
                    // on the very first screen teaches an operator to ignore
                    // the alarms.
                    \SignalMasterAi\DataGuard::clearFirstLogin();
                    \SignalMasterAi\Database::setSetting('admin_first_login', '0');
                }

                // 3b. Optional answers from the wizard. Written with
                //     setSetting so they behave exactly as if typed into the
                //     admin panel, and skipped entirely when left blank.
                if (($st['site']['name'] ?? '') !== '') {
                    \SignalMasterAi\Database::setSetting('site_name', $st['site']['name']);
                }
                // Belt as well as braces: the form does not offer the fields
                // once mail is configured, but a resubmitted or hand-crafted
                // POST still must not silently replace a working mail server.
                if (!empty($st['smtp']['host']) && \SignalMasterAi\Database::setting('smtp_mode', 'off') === 'off') {
                    \SignalMasterAi\Database::setSetting('smtp_mode', 'smtp');
                    \SignalMasterAi\Database::setSetting('smtp_host', $st['smtp']['host']);
                    \SignalMasterAi\Database::setSetting('smtp_port', (string)$st['smtp']['port']);
                    \SignalMasterAi\Database::setSetting('smtp_user', $st['smtp']['user']);
                    if ($st['smtp']['pass'] !== '') {
                        \SignalMasterAi\Database::setSetting('smtp_pass', $st['smtp']['pass']);
                    }
                    if ($st['smtp']['from'] !== '') {
                        \SignalMasterAi\Database::setSetting('smtp_from_email', $st['smtp']['from']);
                    }
                    \SignalMasterAi\Database::setSetting('smtp_security', $st['smtp']['enc']);
                }

                // A database nobody can name.
                //
                // The data directory is denied by .htaccess, which nginx does
                // not read and an Apache with AllowOverride None ignores. On
                // those hosts the only thing standing between the file and the
                // open web is whether an attacker can guess its name, so a new
                // install stops using the one in the manual.
                if (($st['db']['driver'] ?? '') === 'sqlite') {
                    $newName = \SignalMasterAi\DataGuard::freshName();
                    $old = $config['db']['sqlite'];
                    $new = dirname($old) . '/' . $newName;
                    // CHECKPOINT AND CLOSE BEFORE MOVING THE FILE.
                    //
                    // SQLite in WAL mode keeps recent writes in <db>-wal, and
                    // rename() moves only <db> - so everything sitting in that
                    // sidecar is orphaned with it. Everything this installer
                    // had just done: the schema, the seed data, the
                    // administrator it created.
                    //
                    // What that looked like on a clean install, before this
                    // line existed: a five megabyte signalmasterai.sqlite-wal
                    // left in data/ with no database beside it, an empty users
                    // table in the renamed file, a second administrator seeded
                    // on the next boot, and TWO FIRST-LOGIN notes of which the
                    // first held a password that no longer worked. An operator
                    // reading the wrong one could not log in to their own site.
                    \SignalMasterAi\Database::close();
                    if (@rename($old, $new)) {
                        @file_put_contents(SMA_DATA_DIR . '/config.local.php',
                            "<?php\nreturn ['db' => ['sqlite' => __DIR__ . '/" . $newName . "']];\n");
                        // Any sidecar the checkpoint could not remove belongs
                        // to a database that is no longer there.
                        foreach (['-wal', '-shm'] as $sfx) {
                            if (is_file($old . $sfx)) {
                                @unlink($old . $sfx);
                            }
                        }
                        // Reopen against the file the config now names, so the
                        // settings written below land in the live database.
                        $config = require __DIR__ . '/config.php';
                        \SignalMasterAi\Database::boot($config);
                    }
                }

                // 4. Auto-detect the site URL from this very request (kept
                // editable in Admin > Settings; www/non-www handled at runtime)
                if (!empty($_SERVER['HTTP_HOST'])) {
                    $host = preg_replace('/[^A-Za-z0-9.\-:\[\]]/', '', explode(':', $_SERVER['HTTP_HOST'])[0]);
                    if ($host !== '') {
                        $scheme = \SignalMasterAi\Request::scheme();
                        $path = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/');
                        \SignalMasterAi\Database::setSetting('site_url', $scheme . '://' . $host . $path);
                        \SignalMasterAi\Database::setSetting('vapid_subject', 'mailto:admin@' . preg_replace('/^www\./i', '', $host));
                    }
                }

                // 5. Lock the installer
                file_put_contents(SMA_LOCK_FILE, date('c'));
                unset($_SESSION['install']);
                // THE SUCCESS PAGE HAS TO SURVIVE THE LOCK.
                //
                // The lock is written here and the redirect below asks for the
                // page that says the install worked - but the guard at the top
                // of this file sees the lock first and answers 403 "already
                // installed". So every fresh install ended on a refusal, and
                // the congratulations screen with the links to the site and the
                // admin panel was unreachable code. One-shot flag: this session
                // just installed, so it may read that one page.
                $_SESSION['install_done'] = true;
                header('Location: install.php?step=4&done=1');
                exit;
            } catch (Throwable $ex) {
                $errors[] = 'Installation failed: ' . $ex->getMessage();
                $step = 4;
            }
        }
    }
}

$done = isset($_GET['done']);
$steps = ['Requirements', 'Database', 'Admin account', 'Finish'];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Install SignalMasterAi</title>
<link rel="icon" type="image/svg+xml" href="assets/brand/logo.svg">
<link rel="icon" type="image/png" sizes="32x32" href="assets/brand/favicon-32.png">
<?= \SignalMasterAi\Chrome::fonts() ?>
<style>
<?= \SignalMasterAi\Chrome::css() ?>
/* Installer-only: the step rail and the requirement table live here
   because nothing else on the site has them. Everything above is the
   shared chrome, so the first screen anyone sees is the same design
   system as the last one. */
.wizard{width:100%;max-width:640px;position:relative;isolation:isolate}
/* THE FIRST SCREEN ANYONE SEES, AND IT LOOKED LIKE NO ONE HAD.
   Chrome::css()'s own comment says as much: this is the entire first
   impression, not a back-office form. Everything else on the site now
   carries a little colour - the hero's glow, the badge pills, the graded
   ticks - and this page carried none of it, just grey borders on a grey
   card. Same technique as the hero: two soft radial tints, blurred into
   light rather than drawn as shapes, isolated to their own stacking context
   so they can never sit on top of the text or the inputs above them. */
.wizard::before{
  content:"";position:absolute;z-index:-1;inset:-15% -25% -35%;
  background:
    radial-gradient(50% 55% at 20% 15%,color-mix(in srgb,var(--accent) 24%,transparent),transparent 68%),
    radial-gradient(42% 45% at 85% 75%,color-mix(in srgb,var(--brass) 16%,transparent),transparent 70%);
  filter:blur(56px);opacity:.8;pointer-events:none
}
@media (max-width:480px){ .wizard::before{opacity:.5;filter:blur(36px)} }
.steps{display:flex;gap:6px;margin-bottom:20px}
.steps span{flex:1;position:relative;overflow:hidden;text-align:center;font-size:11px;color:var(--dim);padding:8px 2px;border-radius:7px;background:var(--surface2);border:1px solid var(--border);font-family:var(--font-mono);letter-spacing:.04em}
.steps span.on{background:var(--accent-soft);color:var(--text);border-color:var(--accent)}
.steps span.doneStep{background:var(--bg-up);color:var(--up);border-color:var(--line-up)}
/* A thin filled edge under the current and completed steps - the closest
   thing to a progress bar this rail gets without drawing a second one
   underneath it. Transparent for a step not reached yet, so "how far along
   am I" reads at a glance instead of requiring three colours to be
   compared against each other first. */
.steps span::after{content:"";position:absolute;left:0;right:0;bottom:0;height:2px;background:transparent}
.steps span.on::after{background:var(--accent)}
.steps span.doneStep::after{background:var(--up)}
h2{font-size:17px;margin-bottom:14px}
table{width:100%;border-collapse:collapse;font-size:14px}
td{padding:8px 6px;border-bottom:1px solid var(--border)}
td small{color:var(--muted);display:block}
/* Requirement status, as a small pill rather than plain coloured text - the
   same badge language the rest of the app uses for a graded state (a grade
   letter, a plan tier) instead of one-off colour-only text that this page
   had invented for itself. */
.req-status{display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:999px;font-size:11.5px;font-weight:500;font-family:var(--font-mono);letter-spacing:.04em}
.req-status.ok{background:var(--bg-up);color:var(--up)}
.req-status.fail{background:var(--bg-down);color:var(--down)}
label{display:block;font-size:13px;color:var(--muted);margin:12px 0 4px}
input,select,textarea{width:100%;padding:9px 10px;border-radius:8px;border:1px solid var(--border);background:var(--surface2);color:var(--text);font-size:14px}
textarea{min-height:70px;resize:vertical}
input:focus,select:focus,textarea:focus{outline:none;border-color:var(--accent)}
.btn{display:inline-block;margin-top:18px;background:var(--accent);color:#fff;border:none;padding:10px 22px;border-radius:8px;font-size:14px;font-weight:500;cursor:pointer;text-decoration:none;box-shadow:0 8px 22px -10px color-mix(in srgb,var(--accent) 55%,transparent);transition:transform .15s ease,box-shadow .15s ease}
.btn:hover{transform:translateY(-1px)}
.btn.gray{background:var(--surface2);border:1px solid var(--border);box-shadow:none}
.err{background:var(--bg-down);border:1px solid var(--line-down);color:var(--on-down);padding:10px 12px;border-radius:8px;font-size:13px;margin-bottom:12px}
.notice{background:var(--bg-accent);border:1px solid var(--line-accent);padding:10px 12px;border-radius:8px;font-size:13px;color:var(--text);margin-bottom:12px}
.row{display:grid;grid-template-columns:1fr 1fr;gap:0 14px}
.summary td:first-child{color:var(--muted);width:40%}
.bigok{text-align:center;margin:14px 0;position:relative}
/* The one moment on this page worth a flourish - the whole wizard ends
   here. A soft green glow behind the checkmark, the same up/success colour
   the mark is already drawn in, and a quick pop-in on the mark itself
   rather than a static icon just appearing. prefers-reduced-motion already
   turns every animation on this page off globally (see Chrome::css()), so
   this needs no reduced-motion rule of its own. */
.bigok::before{
  content:"";position:absolute;left:50%;top:50%;width:120px;height:120px;
  transform:translate(-50%,-50%);border-radius:50%;
  background:radial-gradient(circle,color-mix(in srgb,var(--up) 30%,transparent),transparent 72%);
  filter:blur(6px);pointer-events:none
}
.bigok svg{position:relative;animation:bigokPop .5s cubic-bezier(.34,1.56,.64,1)}
@keyframes bigokPop{from{transform:scale(.7);opacity:0}to{transform:scale(1);opacity:1}}
.center{text-align:center}
.mysql-box{display:none;border:1px dashed var(--border);border-radius:8px;padding:4px 14px 14px;margin-top:10px}
/* CSS GRID DOES NOT REFLOW ON ITS OWN.
   .row forces two equal columns unconditionally - a flex-wrap layout would
   naturally stack on a narrow screen, but grid-template-columns:1fr 1fr
   holds firm at any viewport unless told otherwise. On a 360px phone that
   put "noreply@yourdomain.com" inside a ~150px-wide box, scrolling
   internally to read its own value. Below 480px each pair stacks to one
   full-width field per row, the same width every other input on this page
   already gets. */
@media (max-width:480px){ .row{grid-template-columns:1fr} }
/* FOUR LABELS, ONE PILL EACH, NOT ENOUGH ROOM AT 390PX.
   "Requirements" and "Admin account" wrapped mid-word inside their own
   pill at a phone's width - flex:1 splits four pills evenly regardless of
   how long the label inside each one is, and nothing here ever shrank the
   text or gave it a way out. The name is not lost by hiding it: the card
   right below repeats it in full ("Step 1 · Server requirements"), so the
   rail can fall back to just the number - or the checkmark, once a step is
   behind you - and stay one line, at the one width where four full labels
   never fit to begin with. */
@media (max-width:480px){
  .steps span{padding:10px 2px}
  .steps .step-n{font-size:13px}
  .steps .step-lbl{display:none}
}
</style>
</head>
<body>
<div class="wizard sheet">
  <h1><?= \SignalMasterAi\Chrome::mark(30, 'vertical-align:-6px') ?> SignalMasterAi <span style="color:var(--muted);font-weight:400">installer</span></h1>
  <p class="tagline">AI-Powered Signals, Smarter Trading &mdash; educational use only, not financial advice.</p>

  <div class="steps">
    <?php foreach ($steps as $idx => $name):
      $sNum = $idx + 1;
      $stCls = $sNum === $step ? 'on' : ($sNum < $step || $done ? 'doneStep' : '');
      // A finished step shows a checkmark instead of its own number - the
      // number answered "which step is this", which stops being the useful
      // question once the step is behind you; "did this one go through" is
      // what the checkmark answers instead. ?>
      <span class="<?= $stCls ?>"><b class="step-n"><?= $stCls === 'doneStep' ? '&check;' : $sNum ?></b><span class="step-lbl">. <?= e($name) ?></span></span>
    <?php endforeach; ?>
  </div>

  <div class="card">
    <?php foreach ($errors as $er): ?><div class="err"><?= e($er) ?></div><?php endforeach; ?>

    <?php if ($step === 1): ?>
      <h2>Step 1 &middot; Server requirements</h2>
      <table>
        <?php foreach (requirements() as [$name, $ok, $hint]): ?>
        <tr>
          <td><?= e($name) ?><small><?= e($hint) ?></small></td>
          <td style="text-align:right"><span class="req-status <?= $ok ? 'ok' : 'fail' ?>"><?= $ok ? 'OK' : 'Missing' ?></span></td>
        </tr>
        <?php endforeach; ?>
      </table>
      <?php if (requirementsOk()): ?>
        <a class="btn" href="install.php?step=2">Continue &rarr;</a>
      <?php else: ?>
        <div class="err" style="margin-top:14px">Fix the missing requirements above, then refresh this page.</div>
        <a class="btn gray" href="install.php?step=1">Re-check</a>
      <?php endif; ?>

    <?php elseif ($step === 2 && $askDataMode): ?>
      <h2>Step 2 &middot; Existing data found</h2>
      <div class="notice">This database already contains a SignalMasterAi installation
        (settings, members, signals&hellip;). Choose what to do with it:</div>
      <form method="post" action="install.php?step=2">
        <input type="hidden" name="step" value="2">
        <label style="display:flex;gap:10px;align-items:flex-start;border:1px solid var(--border);border-radius:10px;padding:12px;margin-bottom:10px;cursor:pointer">
          <input type="radio" name="data_mode" value="keep" checked style="margin-top:3px">
          <span><strong>Keep existing data (upgrade / migrate)</strong><br>
          <span style="color:var(--muted);font-size:13px">Members, payments, signals, settings and tuned rule weights are preserved.
          The schema is upgraded automatically and your existing admin login keeps working
          (the admin-account step is skipped).</span></span>
        </label>
        <label style="display:flex;gap:10px;align-items:flex-start;border:1px solid var(--line-down);border-radius:10px;padding:12px;cursor:pointer">
          <input type="radio" name="data_mode" value="wipe" style="margin-top:3px">
          <span><strong style="color:var(--down)">Fresh install (erase everything)</strong><br>
          <span style="color:var(--muted);font-size:13px">All existing SignalMasterAi tables are dropped - members, payments and
          signal history are permanently deleted - then everything is re-created with defaults.</span></span>
        </label>
        <button class="btn" type="submit">Continue &rarr;</button>
        <a class="btn gray" href="install.php?step=2">&larr; Back</a>
      </form>

    <?php elseif ($step === 2): ?>
      <h2>Step 2 &middot; Database</h2>
      <div class="notice">SQLite needs zero configuration and is perfect for most installs. Choose MySQL only if your host provides it.</div>
      <form method="post" action="install.php?step=2">
        <input type="hidden" name="step" value="2">
        <label>Database engine</label>
        <select name="driver" id="driver" onchange="document.getElementById('my').style.display=this.value==='mysql'?'block':'none'">
          <option value="sqlite" <?= ($st['db']['driver'] ?? '') !== 'mysql' ? 'selected' : '' ?>>SQLite (recommended, zero config)</option>
          <option value="mysql" <?= ($st['db']['driver'] ?? '') === 'mysql' ? 'selected' : '' ?>>MySQL / MariaDB</option>
        </select>
        <div class="mysql-box" id="my" style="<?= ($st['db']['driver'] ?? '') === 'mysql' ? 'display:block' : '' ?>">
          <div class="row">
            <div><label>Host</label><input name="mysql_host" value="<?= e($st['db']['mysql']['host'] ?? '127.0.0.1') ?>"></div>
            <div><label>Port</label><input name="mysql_port" value="<?= e((string)($st['db']['mysql']['port'] ?? 3306)) ?>"></div>
          </div>
          <label>Database name</label><input name="mysql_db" value="<?= e($st['db']['mysql']['database'] ?? '') ?>">
          <div class="row">
            <div><label>Username</label><input name="mysql_user" value="<?= e($st['db']['mysql']['username'] ?? '') ?>"></div>
            <div><label>Password</label><input name="mysql_pass" type="password" value=""></div>
          </div>
        </div>
        <button class="btn" type="submit">Test &amp; continue &rarr;</button>
      </form>

    <?php elseif ($step === 3):
      $hasAdmin = !empty($st['db']) && ($st['data_mode'] ?? 'keep') === 'keep'
                  && existingAdminCount($st['db'], $config) > 0;
    ?>
      <h2>Step 3 &middot; Admin account<?= $hasAdmin ? ' &amp; essentials' : '' ?></h2>
      <div class="notice">This account controls everything: symbols, the knowledge base, engine
        thresholds, site texts and data.</div>
      <form method="post" action="install.php?step=3">
        <input type="hidden" name="step" value="3">

        <?php if ($hasAdmin): ?>
          <?php // An existing install used to skip this step entirely, which
                // left an operator who had forgotten the password with no way
                // through the installer at all. It is a choice now. ?>
          <label style="display:flex;gap:10px;align-items:flex-start;border:1px solid var(--border);border-radius:10px;padding:12px;margin-bottom:10px;cursor:pointer">
            <input type="radio" name="admin_mode" value="keep" checked style="margin-top:3px"
                   onclick="document.getElementById('newAdm').style.display='none'">
            <span><strong>Keep the existing admin login</strong><br>
            <span style="color:var(--muted);font-size:13px">This database already has
              <?= existingAdminCount($st['db'], $config) ?> admin account(s). Nothing changes — sign
              in with the username and password you already use.</span></span>
          </label>
          <label style="display:flex;gap:10px;align-items:flex-start;border:1px solid var(--border);border-radius:10px;padding:12px;margin-bottom:14px;cursor:pointer">
            <input type="radio" name="admin_mode" value="new" style="margin-top:3px"
                   onclick="document.getElementById('newAdm').style.display='block'">
            <span><strong>Replace it with a new one</strong><br>
            <span style="color:var(--muted);font-size:13px">Use this if the password has been lost.
              The existing admin accounts are removed and replaced by the one below — members,
              signals and settings are untouched.</span></span>
          </label>
        <?php endif; ?>

        <div id="newAdm" <?= $hasAdmin ? 'style="display:none"' : '' ?>>
          <label>Admin username</label>
          <input name="admin_user" value="<?= e($st['admin']['user'] ?? 'admin') ?>" autocomplete="username">
          <div class="row">
            <div><label>Password (min 8 chars)</label><input name="admin_pass" type="password" autocomplete="new-password"></div>
            <div><label>Repeat password</label><input name="admin_pass2" type="password" autocomplete="new-password"></div>
          </div>
        </div>

        <h2 style="font-size:16px;margin-top:22px">Optional — you can set these later</h2>
        <label>Site name</label>
        <input name="site_name" value="<?= e($st['site']['name'] ?? '') ?>" placeholder="SignalMasterAi">

        <?php
        // Already configured? Then do not ask, and do not show a box that
        // looks like it wants filling in.
        $mail = !empty($st['db']) ? existingSmtp($st['db'], $config) : [];
        ?>
        <label style="margin-top:14px">Mail server (SMTP)</label>
        <?php if ($mail): ?>
          <div class="notice">&#10003; <strong>Already set up &mdash; left untouched.</strong>
            This site sends
            <?= $mail['mode'] === 'phpmail'
                ? 'through the server&rsquo;s own mail command'
                : 'through <strong>' . e($mail['host']) . ($mail['port'] !== '' ? ':' . e($mail['port']) : '') . '</strong>' ?><?=
              $mail['from'] !== '' ? ' as <strong>' . e($mail['from']) . '</strong>' : '' ?>.
            Nothing on this page will change it.
            <div style="color:var(--muted);font-size:13px;margin-top:8px">To change the mail server,
              finish here and edit it under <strong>Admin &rsaquo; Settings &rsaquo; Alerts</strong>,
              where you can also send a test message and check SPF and DKIM &mdash; which is a safer
              place to do it than an installer that cannot test the new details before saving them.</div>
          </div>
        <?php else: ?>
        <div style="color:var(--muted);font-size:13px;margin-bottom:8px">Leave the host blank to skip.
          Without it, password resets, alert emails and the daily digest have no way out — which is
          usually discovered a week later, by a member who never got their reset link.</div>
        <div class="row">
          <div><label>Host</label><input name="smtp_host" value="<?= e($st['smtp']['host'] ?? '') ?>" placeholder="smtp.yourhost.com"></div>
          <div><label>Port</label><input name="smtp_port" value="<?= e((string)($st['smtp']['port'] ?? 587)) ?>"></div>
        </div>
        <div class="row">
          <div><label>Username</label><input name="smtp_user" value="<?= e($st['smtp']['user'] ?? '') ?>" autocomplete="off"></div>
          <div><label>Password</label><input name="smtp_pass" type="password" value="" autocomplete="new-password"></div>
        </div>
        <div class="row">
          <div><label>From address</label><input name="smtp_from" value="<?= e($st['smtp']['from'] ?? '') ?>" placeholder="noreply@yourdomain.com"></div>
          <div><label>Encryption</label>
            <select name="smtp_enc">
              <?php foreach (['tls' => 'TLS (usual, port 587)', 'ssl' => 'SSL (port 465)', 'none' => 'None'] as $v => $l): ?>
                <option value="<?= $v ?>" <?= ($st['smtp']['enc'] ?? 'tls') === $v ? 'selected' : '' ?>><?= $l ?></option>
              <?php endforeach; ?>
            </select></div>
        </div>
        <?php endif; ?>

        <button class="btn" type="submit">Continue &rarr;</button>
      </form>

    <?php elseif ($step === 4 && !$done): ?>
      <h2>Step 4 &middot; Review &amp; install</h2>
      <?php if (empty($st['db']) || empty($st['admin'])): ?>
        <div class="err">Wizard data missing - please <a style="color:var(--on-down)" href="install.php?step=1">start over</a>.</div>
      <?php else: ?>
      <table class="summary">
        <tr><td>Database</td><td><?= e($st['db']['driver'] === 'mysql' ? 'MySQL (' . $st['db']['mysql']['database'] . ')' : 'SQLite (data/signalmasterai.sqlite)') ?></td></tr>
        <tr><td>Admin user</td><td><?= !empty($st['admin']['keep'])
            ? 'Existing admin kept - log in with your current username &amp; password'
            : e($st['admin']['user'] ?? '') ?></td></tr>
        <tr><td>Site name</td><td><?= ($st['site']['name'] ?? '') !== ''
            ? e($st['site']['name']) : 'Default (change any time in Settings)' ?></td></tr>
        <tr><td>Mail server</td><td><?= !empty($st['smtp']['host'])
            ? e($st['smtp']['host']) . ':' . (int)$st['smtp']['port'] . ' (' . e($st['smtp']['enc']) . ')'
            : 'Not configured - password resets and alert emails stay off until you add one' ?></td></tr>
        <tr><td>Existing data</td><td><?= ($st['data_mode'] ?? 'keep') === 'wipe'
            ? '<strong style="color:var(--down)">Fresh install - everything will be erased</strong>'
            : 'Kept (schema upgraded automatically)' ?></td></tr>
        <tr><td>Everything else</td><td>Site texts, coins, timeframes, email/SMTP, APIs, payments,
          appearance &mdash; all configured later in the admin panel</td></tr>
      </table>
      <p style="font-size:13px;color:var(--muted);margin-top:12px">Installing creates the database schema, seeds the technical-analysis knowledge base (<?= count(\SignalMasterAi\Database::seedRules()) ?> tunable rules), payment plans, your admin account and a 30-coin watchlist.</p>
      <form method="post" action="install.php?step=4">
        <input type="hidden" name="step" value="4">
        <button class="btn" type="submit">Install now</button>
        <a class="btn gray" href="install.php?step=<?= !empty($st['admin']['keep']) ? 2 : 3 ?>">&larr; Back</a>
      </form>
      <?php endif; ?>

    <?php elseif ($done): ?>
      <div class="bigok" aria-hidden="true">
        <svg viewBox="0 0 48 48" width="48" height="48" fill="none" stroke="var(--up)"
             stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="24" cy="24" r="19"/>
          <path d="M15 24.5l6 6 12-13"/>
        </svg>
      </div>
      <h2 class="center">Installation complete!</h2>
      <p class="center" style="color:var(--muted);font-size:14px;margin:8px 0 4px">The installer is now locked (<code>data/installed.lock</code>).</p>
      <p class="center" style="margin-top:16px">
        <a class="btn" href="index.php">Open the site</a>
        <a class="btn gray" href="admin/login.php">Admin panel</a>
      </p>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
