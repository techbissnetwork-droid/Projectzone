<?php
/**
 * Installer and updater.
 *
 * What it does depends on what it finds:
 *
 *   Nothing installed  → the install form. On success it writes app/config.php,
 *                        creates a lock file and then deletes itself.
 *   Already installed  → asks for an administrator sign-in first, then offers
 *                        to update the database, delete the installer, or wipe
 *                        and start again.
 *
 * The lock file is what makes the second case stick: even if this file is
 * re-uploaded later, it will not touch anything without an admin password.
 */

require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/seed.php';

$configFile = APP_DIR . '/config.php';
$lockFile   = APP_DIR . '/install.lock';

/* ---------------------------------------------------------------------- */
/* work out where we are                                                  */
/* ---------------------------------------------------------------------- */

$hasConfig = is_file($configFile) && !empty(config());
$locked    = is_file($lockFile);
$dbOk      = false;
$dbError   = null;
$installed = false;

if ($hasConfig) {
    try {
        db();
        $dbOk      = true;
        $installed = db_table_exists('settings') && db_table_exists('users');
    } catch (Throwable $e) {
        $dbError = $e->getMessage();
    }
}

/* Anything already set up is protected by a password from here on. */
$needsAuth = $locked || $installed;

$detectedUrl = detect_base_url();

/* ---------------------------------------------------------------------- */
/* locked, but the database cannot be reached                             */
/* ---------------------------------------------------------------------- */

if ($needsAuth && !$dbOk) {
    install_page('Installer locked', '
        <div class="warn">This site has been installed before, so the installer will not run
          again on its own.</div>
        <p>It cannot reach the database to check who you are:</p>
        <p><code>' . esc($dbError ?: 'no database configured') . '</code></p>
        <h2>Getting back in</h2>
        <ol>
          <li>Fix the database details in <code>app/config.php</code> — usually the password or
              the database name.</li>
          <li>Reload this page and sign in as an administrator.</li>
        </ol>
        <p class="hint">Deliberately starting over from nothing? Delete
          <code>app/install.lock</code> and <code>app/config.php</code> from the server, then
          reload. That does not touch the database — it only makes the installer offer a fresh
          install again.</p>', 'Installer locked');
}

/* ---------------------------------------------------------------------- */
/* sign-in gate                                                           */
/* ---------------------------------------------------------------------- */

$authError = null;

if ($needsAuth && !is_admin() && is_post() && post('action') === 'signin') {
    csrf_check();
    attempt_login(post('email'), $_POST['password'] ?? '', [ROLE_ADMIN], $authError);
    if (!$authError) {
        redirect('install.php');
    }
}

if ($needsAuth && !is_admin()) {
    install_page('Sign in', '
        <div class="warn">This site is already installed. Sign in as an administrator to update
          or reset it.</div>
        ' . ($authError ? '<div class="bad">' . esc($authError) . '</div>' : '') . '
        <form method="post" autocomplete="off">
          ' . csrf_field() . '
          <input type="hidden" name="action" value="signin">
          <div class="f"><label for="email">Administrator email</label>
            <input id="email" name="email" type="email" required autofocus
                   value="' . esc(post('email')) . '"></div>
          <div class="f"><label for="password">Password</label>
            <input id="password" name="password" type="password" required></div>
          <button class="btn" type="submit">Sign in</button>
        </form>
        <p class="hint">Nothing on this page will run until you do. If you have finished with the
          installer, the safest thing is to delete <code>install.php</code> from the server —
          you can do that from the next screen.</p>', 'Already installed');
}

/* ---------------------------------------------------------------------- */
/* actions, once signed in                                                */
/* ---------------------------------------------------------------------- */

$notice = '';

if ($needsAuth && is_admin() && is_post()) {
    csrf_check();

    /* --- update the database ------------------------------------------ */
    if (post('action') === 'migrate') {
        try {
            $changes = schema_migrate();
            $added   = seed_missing_settings();
            foreach ($added as $key) {
                $changes[] = 'Added the "' . $key . '" setting';
            }
            schema_set_version(SCHEMA_VERSION);
            log_activity('Ran database update to version ' . SCHEMA_VERSION, 'system');

            $notice = $changes
                ? '<div class="ok"><strong>Database updated.</strong><ul><li>'
                  . implode('</li><li>', array_map('esc', $changes)) . '</li></ul></div>'
                : '<div class="ok">Already up to date — nothing needed changing.</div>';
        } catch (Throwable $e) {
            $notice = '<div class="bad">The update failed: ' . esc($e->getMessage())
                    . '<br>No data was removed. Nothing has changed except anything listed above '
                    . 'as already done.</div>';
        }
    }

    /* --- delete this file --------------------------------------------- */
    if (post('action') === 'remove') {
        if (@unlink(__FILE__)) {
            $target = base_url() . '/admin/';
            header('Location: ' . $target);
            exit;
        }
        $notice = '<div class="bad"><strong>Could not delete install.php.</strong> The web server
          does not have permission. Delete it yourself in the cPanel file manager or over FTP.
          Until then it stays locked and will keep asking for this password.</div>';
    }

    /* --- wipe and start again ----------------------------------------- */
    if (post('action') === 'wipe') {
        $phrase = post('confirm');
        $name   = post('admin_name');
        $email  = strtolower(post('admin_email'));
        $pass   = $_POST['admin_password'] ?? '';
        $errors = [];

        if ($phrase !== 'ERASE')            { $errors[] = 'Type ERASE in the confirmation box to go ahead.'; }
        if ($name === '')                   { $errors[] = 'Enter a name for the new administrator.'; }
        if (!valid_email($email))           { $errors[] = 'Enter a valid email for the new administrator.'; }
        if ($p = password_problem($pass))   { $errors[] = 'Password: ' . $p; }

        if ($errors) {
            $notice = '<div class="bad"><strong>Nothing was erased:</strong><ul><li>'
                    . implode('</li><li>', array_map('esc', $errors)) . '</li></ul></div>';
        } else {
            try {
                schema_drop_all();
                schema_create();
                seed_all();
                schema_set_version(SCHEMA_VERSION);

                $userId = db_insert('users', [
                    'name'          => $name,
                    'email'         => $email,
                    'password_hash' => hash_password($pass),
                    'role'          => ROLE_ADMIN,
                    'status'        => 'active',
                    'created_at'    => now(),
                ]);
                session_regenerate_id(true);
                $_SESSION['user_id'] = $userId;

                write_lock($lockFile, 'reset');
                $notice = '<div class="ok"><strong>Everything was erased and set up fresh.</strong>
                    The starting content is back, and your new administrator account is ready.</div>';
            } catch (Throwable $e) {
                $notice = '<div class="bad">The reset failed part-way: ' . esc($e->getMessage())
                        . '</div>';
            }
        }
    }
}

/* ---------------------------------------------------------------------- */
/* the maintenance screen                                                 */
/* ---------------------------------------------------------------------- */

if ($needsAuth && is_admin()) {
    $cfg      = config();
    $version  = schema_installed_version();
    $missing  = schema_missing_tables();
    $needsUpd = schema_needs_update() || $missing;

    $status = '<div class="status">'
        . row('Site address', esc($cfg['base_url'] ?: $detectedUrl)
            . ($cfg['base_url'] ? '' : ' <span class="tagpill">detected</span>'))
        . row('Database', ($cfg['driver'] ?? '') === 'sqlite'
            ? 'SQLite file' : 'MySQL — ' . esc($cfg['database'] ?? ''))
        . row('Schema version', $version . ' of ' . SCHEMA_VERSION
            . ($needsUpd ? ' <span class="tagpill warnpill">update available</span>'
                         : ' <span class="tagpill okpill">up to date</span>'))
        . row('Tables', $missing
            ? '<span class="warnpill tagpill">' . count($missing) . ' missing: '
              . esc(implode(', ', $missing)) . '</span>'
            : count(schema_tables()) . ' present')
        . row('PHP', esc(PHP_VERSION))
        . row('Mail sent from', esc($cfg['mail_from'] ?? 'not set'))
        . '</div>';

    $updateBox = '
      <h2>Update the database</h2>
      <p>Adds any new tables, columns and settings this version expects.
         <strong>It never deletes anything and never overwrites text you have edited.</strong>
         Run it after uploading a newer copy of the files.</p>
      <form method="post">' . csrf_field() . '
        <input type="hidden" name="action" value="migrate">
        <button class="btn" type="submit">' . ($needsUpd ? 'Run the update' : 'Check again') . '</button>
      </form>';

    $removeBox = '
      <h2>Delete the installer</h2>
      <p>Once the site is running, this file should not be on the server at all. Deleting it is
         the last step of an install.</p>
      <form method="post">' . csrf_field() . '
        <input type="hidden" name="action" value="remove">
        <button class="btn" type="submit">Delete install.php and go to the admin area</button>
      </form>';

    $wipeBox = '
      <h2>Start again from nothing</h2>
      <div class="danger">
        <p><strong>This erases everything.</strong> Every enquiry, order, project, client account,
           support message and portfolio entry is dropped and the site goes back to its starting
           content. There is no undo and no backup taken. Uploaded images are left on disk but
           nothing will point at them.</p>
        <p>Only do this on a site that is not live yet.</p>
      </div>
      <form method="post" onsubmit="return confirm(\'Erase everything and start again? This cannot be undone.\')">
        ' . csrf_field() . '
        <input type="hidden" name="action" value="wipe">
        <p class="hint">You are erasing the users table too, so set up the new administrator here.</p>
        <div class="two">
          <div class="f"><label for="admin_name">New administrator name</label>
            <input id="admin_name" name="admin_name"></div>
          <div class="f"><label for="admin_email">Email</label>
            <input id="admin_email" name="admin_email" type="email"></div>
        </div>
        <div class="f"><label for="admin_password">Password</label>
          <input id="admin_password" name="admin_password" type="password">
          <span class="hint">At least 10 characters, with a letter and a number.</span></div>
        <div class="f"><label for="confirm">Type ERASE to confirm</label>
          <input id="confirm" name="confirm" autocomplete="off" placeholder="ERASE"></div>
        <button class="btn danger-btn" type="submit">Erase everything and start again</button>
      </form>';

    install_page('Installer', $notice . '
      <p class="lede">This site is installed, so the installer is locked to administrators.
         From here you can bring the database up to date, delete this file, or start over.</p>
      ' . $status . $updateBox . $removeBox . '
      <p><a class="btn ghost" href="admin/">Go to the admin area</a>
         <a class="btn ghost" href="index.php">View the site</a></p>
      ' . $wipeBox, 'Installer');
}

/* ---------------------------------------------------------------------- */
/* fresh install                                                          */
/* ---------------------------------------------------------------------- */

$errors = [];
$step   = 'form';

if (is_post() && post('action') === 'install') {
    csrf_check();

    $driver = post('driver', 'mysql') === 'sqlite' ? 'sqlite' : 'mysql';
    $useDetected = post('auto_url', '1') === '1';

    $cfg = [
        'driver'      => $driver,
        'host'        => post('host', 'localhost'),
        'port'        => post('port', '3306'),
        'database'    => post('database'),
        'username'    => post('username'),
        'password'    => $_POST['password'] ?? '',
        'sqlite_path' => APP_ROOT . '/storage/techbiss.sqlite',
        'mail_from'   => post('mail_from'),
        'mail_to'     => post('mail_to'),
        'base_url'    => $useDetected ? '' : rtrim(post('base_url'), '/'),
        'app_key'     => bin2hex(random_bytes(16)),
    ];

    $adminName  = post('admin_name');
    $adminEmail = strtolower(post('admin_email'));
    $adminPass  = $_POST['admin_password'] ?? '';

    if ($driver === 'mysql') {
        if ($cfg['database'] === '') { $errors[] = 'Enter the database name.'; }
        if ($cfg['username'] === '') { $errors[] = 'Enter the database username.'; }
    }
    if ($adminName === '')           { $errors[] = 'Enter your name.'; }
    if (!valid_email($adminEmail))   { $errors[] = 'Enter a valid email address for the admin account.'; }
    if ($p = password_problem($adminPass)) { $errors[] = 'Admin password: ' . $p; }
    if ($cfg['mail_from'] !== '' && !valid_email($cfg['mail_from'])) {
        $errors[] = 'The "from" address is not a valid email address.';
    }
    if ($cfg['mail_to'] !== '' && !valid_email($cfg['mail_to'])) {
        $errors[] = 'The "send enquiries to" address is not a valid email address.';
    }
    if (!$useDetected && post('base_url') !== '' && !preg_match('#^https?://#i', post('base_url'))) {
        $errors[] = 'The site address must start with http:// or https://';
    }

    if (!$errors) {
        try {
            $GLOBALS['__install_config'] = $cfg;
            db();
        } catch (Throwable $e) {
            $errors[] = $driver === 'mysql'
                ? 'Could not connect to MySQL: ' . $e->getMessage()
                : 'Could not open the SQLite file: ' . $e->getMessage();
        }
    }

    if (!$errors) {
        try {
            /* An existing database is migrated, never flattened. */
            $existing = db_table_exists('settings');
            if ($existing) {
                schema_migrate();
                seed_missing_settings();
            } else {
                schema_create();
                seed_all();
            }
            schema_set_version(SCHEMA_VERSION);

            $already = db_one('SELECT id FROM users WHERE email = ?', [$adminEmail]);
            if ($already) {
                db_update('users', (int) $already['id'], [
                    'name'          => $adminName,
                    'password_hash' => hash_password($adminPass),
                    'role'          => ROLE_ADMIN,
                    'status'        => 'active',
                ]);
            } else {
                db_insert('users', [
                    'name'          => $adminName,
                    'email'         => $adminEmail,
                    'password_hash' => hash_password($adminPass),
                    'role'          => ROLE_ADMIN,
                    'status'        => 'active',
                    'created_at'    => now(),
                ]);
            }

            if ($cfg['mail_to']) {
                setting_set('site.email', $cfg['mail_to']);
            }

            $written = @file_put_contents(
                $configFile,
                "<?php\n// Written by install.php. Keep this file out of version control.\nreturn "
                . var_export($cfg, true) . ";\n"
            );
            if ($written === false) {
                $errors[] = 'The database is ready, but app/config.php could not be written. '
                          . 'Give the app folder write permission (755) and try again, or create '
                          . 'the file by hand from app/config.sample.php.';
            } else {
                @chmod($configFile, 0644);
                write_lock($lockFile, $existing ? 'migrated' : 'fresh');
                $step = 'done';
            }
        } catch (Throwable $e) {
            $errors[] = 'Setup failed: ' . $e->getMessage();
        }
    }
}

/* --- finished ---------------------------------------------------------- */
if ($step === 'done') {
    $selfDeleted = @unlink(__FILE__);

    install_page('Installed',
        '<div class="ok">The database is set up and your admin account is ready.</div>'
        . '<div class="status">'
        . row('Site address', esc(base_url()) . ' <span class="tagpill">detected</span>')
        . row('Admin area', esc(base_url()) . '/admin/')
        . row('Client portal', esc(base_url()) . '/client/')
        . '</div>'
        . ($selfDeleted
            ? '<div class="ok"><strong>The installer has deleted itself.</strong> Nothing else to
                 tidy up — you are done.</div>'
            : '<div class="warn"><strong>Delete <code>install.php</code> from the server.</strong>
                 It could not remove itself because the web server has no permission to. It is
                 locked and now needs an administrator password, but it still should not be there.
                 Delete it in the cPanel file manager or over FTP.</div>')
        . '<h2>What next</h2>
           <ol>
             <li>Sign in and put your own prices, statistics and contact details in.</li>
             <li>Delete the example portfolio entries and marketplace listings.</li>
             <li>Send yourself a test message from the contact page to check mail is working.</li>
           </ol>
           <p><a class="btn" href="admin/login.php">Sign in to the admin area</a>
              <a class="btn ghost" href="index.php">View the site</a></p>', 'Installed');
}

/* --- the install form -------------------------------------------------- */
$host   = $_POST['host']     ?? 'localhost';
$port   = $_POST['port']     ?? '3306';
$dbname = $_POST['database'] ?? '';
$dbuser = $_POST['username'] ?? '';
$aname  = $_POST['admin_name']  ?? '';
$aemail = $_POST['admin_email'] ?? '';
$mfrom  = $_POST['mail_from']   ?? '';
$mto    = $_POST['mail_to']     ?? '';
$driver = $_POST['driver']      ?? 'mysql';
$autoUrl = ($_POST['auto_url'] ?? '1') === '1';

$errorHtml = '';
if ($errors) {
    $errorHtml = '<div class="bad"><strong>That did not work:</strong><ul><li>'
               . implode('</li><li>', array_map('esc', $errors)) . '</li></ul></div>';
}

$writable = is_writable(APP_DIR) ? '' :
    '<div class="warn">The <code>app</code> folder is not writable, so the installer will not be '
  . 'able to save <code>app/config.php</code>. Set it to 755 in your file manager first.</div>';

$existingNote = ($hasConfig && $dbOk && !$installed)
    ? '<div class="warn">A database connection is already configured but has no tables yet. '
    . 'Filling this in will set them up.</div>' : '';

install_page('Install', $errorHtml . $writable . $existingNote . '
<p class="lede">Three short sections and you are done. Nothing is written until every field
   checks out, and the installer deletes itself at the end.</p>

<form method="post" autocomplete="off">
  ' . csrf_field() . '
  <input type="hidden" name="action" value="install">

  <h2>1 &middot; Database</h2>
  <p class="hint">On cPanel: create a database and a user under <em>MySQL&nbsp;Databases</em>, add
     the user to the database with <strong>All Privileges</strong>, then copy the three values
     here. cPanel puts your account name in front of both, so they usually look like
     <code>myaccount_techbiss</code>.</p>

  <div class="row">
    <label><input type="radio" name="driver" value="mysql" ' . ($driver === 'mysql' ? 'checked' : '') . '>
      MySQL <span class="hint">— use this on real hosting</span></label>
    <label><input type="radio" name="driver" value="sqlite" ' . ($driver === 'sqlite' ? 'checked' : '') . '>
      SQLite <span class="hint">— a file, for testing on your own computer</span></label>
  </div>

  <div id="mysqlfields">
    <div class="two">
      <div class="f"><label for="host">Database host</label>
        <input id="host" name="host" value="' . esc($host) . '" placeholder="localhost"></div>
      <div class="f"><label for="port">Port</label>
        <input id="port" name="port" value="' . esc($port) . '" placeholder="3306"></div>
    </div>
    <div class="f"><label for="database">Database name</label>
      <input id="database" name="database" value="' . esc($dbname) . '" placeholder="myaccount_techbiss"></div>
    <div class="two">
      <div class="f"><label for="username">Database user</label>
        <input id="username" name="username" value="' . esc($dbuser) . '" placeholder="myaccount_tb"></div>
      <div class="f"><label for="password">Database password</label>
        <input id="password" name="password" type="password"></div>
    </div>
  </div>

  <h2>2 &middot; Your admin account</h2>
  <div class="two">
    <div class="f"><label for="admin_name">Your name</label>
      <input id="admin_name" name="admin_name" value="' . esc($aname) . '" required></div>
    <div class="f"><label for="admin_email">Your email</label>
      <input id="admin_email" name="admin_email" type="email" value="' . esc($aemail) . '" required></div>
  </div>
  <div class="f"><label for="admin_password">Password</label>
    <input id="admin_password" name="admin_password" type="password" required>
    <span class="hint">At least 10 characters, with a letter and a number.</span></div>

  <h2>3 &middot; Email and address</h2>
  <p class="hint">The <em>from</em> address must be on your own domain — create the mailbox in
     cPanel &rarr; <em>Email Accounts</em> first. Shared hosts refuse to send mail claiming to be
     from Gmail or Yahoo, and receiving servers treat it as spoofed.</p>
  <div class="two">
    <div class="f"><label for="mail_from">Send mail from</label>
      <input id="mail_from" name="mail_from" type="email" value="' . esc($mfrom) . '"
             placeholder="website@yourdomain.com"></div>
    <div class="f"><label for="mail_to">Send enquiries to</label>
      <input id="mail_to" name="mail_to" type="email" value="' . esc($mto) . '"
             placeholder="hello@yourdomain.com"></div>
  </div>

  <div class="f">
    <label>Site address</label>
    <label class="check">
      <input type="checkbox" name="auto_url" value="1" id="autourl" ' . ($autoUrl ? 'checked' : '') . '>
      <span>Detect it automatically — <code>' . esc($detectedUrl) . '</code></span>
    </label>
    <span class="hint">Detected from this very request, and re-checked on every page, so it keeps
      working if you move domain or add HTTPS. Only set it by hand if you serve the same files on
      more than one address.</span>
  </div>
  <div class="f" id="urlfield" style="display:none">
    <label for="base_url">Site address, set by hand</label>
    <input id="base_url" name="base_url" value="' . esc($_POST['base_url'] ?? $detectedUrl) . '"
           placeholder="https://yourdomain.com">
  </div>

  <button class="btn" type="submit">Install</button>
  <p class="hint">If mail fails later, no enquiry is lost — everything is saved to the database
     before mail is attempted.</p>
</form>

<script>
(function () {
  var fields = document.getElementById("mysqlfields");
  function syncDriver() {
    fields.style.display =
      document.querySelector("input[name=driver][value=sqlite]").checked ? "none" : "";
  }
  document.querySelectorAll("input[name=driver]").forEach(function (r) {
    r.addEventListener("change", syncDriver);
  });
  syncDriver();

  var auto = document.getElementById("autourl");
  var urlField = document.getElementById("urlfield");
  function syncUrl() { urlField.style.display = auto.checked ? "none" : ""; }
  auto.addEventListener("change", syncUrl);
  syncUrl();
})();
</script>', 'Install TECHBISS');


/* ====================================================================== */
/* plumbing                                                               */
/* ====================================================================== */

/** One line in a status list. */
function row(string $label, string $value): string
{
    return '<div class="srow"><span>' . esc($label) . '</span><strong>' . $value . '</strong></div>';
}

/** Record that this site has been set up, so the installer stays locked. */
function write_lock(string $file, string $how): void
{
    @file_put_contents($file, json_encode([
        'installed_at' => date('c'),
        'how'          => $how,
        'version'      => SCHEMA_VERSION,
    ], JSON_PRETTY_PRINT) . "\n");
    @chmod($file, 0644);
}

/** The page chrome, so the installer looks like the rest of the site. */
function install_page(string $title, string $body, string $heading = ''): void
{
    ?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= esc($title) ?> — TECHBISS</title>
<meta name="robots" content="noindex,nofollow">
<link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=Manrope:wght@400;500;600&family=Azeret+Mono:wght@400&display=swap">
<style>
:root{--bg:#0b0b0d;--card:#16161a;--card2:#1c1c22;--fg:#f4f4f6;
  --mute:rgba(244,244,246,.55);--dim:rgba(244,244,246,.3);
  --line:rgba(255,255,255,.1);--line2:rgba(255,255,255,.2);
  --acc:#c9ff3d;--bad:#ff6b3d;--ok:#5ddba0;--warn:#ffc457;
  color-scheme:dark}
*{box-sizing:border-box;margin:0;padding:0}
body{background:var(--bg);color:var(--fg);font-family:'Manrope',system-ui,sans-serif;
  line-height:1.62;padding:6vh 5vw 14vh}
.shell{max-width:760px;margin:0 auto}
.brand{font-family:'Syne',system-ui,sans-serif;font-weight:800;font-size:22px;letter-spacing:-.04em;
  display:flex;align-items:center;gap:10px;margin-bottom:8px}
.brand i{width:11px;height:11px;border-radius:50%;background:var(--acc);box-shadow:0 0 16px var(--acc)}
h1{font-family:'Syne',system-ui,sans-serif;font-size:clamp(30px,5vw,46px);letter-spacing:-.04em;
  text-transform:uppercase;line-height:1;margin:14px 0 10px}
h2{font-family:'Syne',system-ui,sans-serif;font-size:19px;text-transform:uppercase;
  letter-spacing:-.02em;margin:38px 0 12px;padding-top:22px;border-top:1px solid var(--line)}
p{color:var(--mute);margin:10px 0}
p.lede{color:var(--fg);font-size:17px;max-width:62ch}
a{color:var(--acc)}
code{font-family:'Azeret Mono',monospace;font-size:.88em;background:rgba(255,255,255,.06);
  padding:2px 6px;border-radius:4px;color:var(--fg);word-break:break-all}
.hint{font-size:13.5px;color:var(--mute)}
form{margin-top:10px}
.f{display:grid;gap:6px;margin:14px 0}
.two{display:grid;grid-template-columns:1fr 1fr;gap:14px}
@media(max-width:620px){.two{grid-template-columns:1fr}}
label{font-family:'Azeret Mono',monospace;font-size:10.5px;letter-spacing:.14em;
  text-transform:uppercase;color:var(--mute)}
label .hint{text-transform:none;letter-spacing:0;font-family:'Manrope',sans-serif}
input[type=text],input[type=email],input[type=password],input:not([type]){
  width:100%;padding:14px 16px;border-radius:11px;border:1px solid var(--line);background:var(--card);
  color:var(--fg);font:inherit;font-size:15.5px}
input:focus{border-color:var(--acc);outline:none}
.row{display:flex;gap:22px;flex-wrap:wrap;margin:14px 0}
.row label,label.check{display:flex;align-items:flex-start;gap:10px;text-transform:none;
  letter-spacing:0;font-family:'Manrope',sans-serif;font-size:15px;color:var(--fg)}
label.check{padding:13px 15px;border:1px solid var(--line);border-radius:11px;background:var(--card);
  cursor:pointer}
label.check input,.row label input{width:17px;height:17px;accent-color:var(--acc);flex:none;margin-top:2px}
.btn{display:inline-block;margin-top:18px;padding:14px 28px;border-radius:999px;background:var(--acc);
  color:var(--bg);font-weight:600;border:0;cursor:pointer;font:inherit;font-weight:600;
  text-decoration:none}
.btn.ghost{background:transparent;color:var(--fg);border:1px solid var(--line2);margin-right:8px}
.btn.danger-btn{background:transparent;border:1px solid var(--bad);color:var(--bad)}
.btn.danger-btn:hover{background:var(--bad);color:var(--bg)}
.btn:hover{filter:brightness(1.08)}
.bad,.warn,.ok,.danger{padding:16px 18px;border-radius:12px;margin:18px 0;font-size:15px}
.bad{border:1px solid var(--bad);color:var(--bad)}
.warn{border:1px solid var(--warn);color:var(--warn)}
.ok{border:1px solid var(--ok);color:var(--ok)}
.danger{border:1px solid var(--bad);background:rgba(255,107,61,.07);color:var(--fg)}
.danger p{color:var(--fg)}
.bad ul,.ok ul{margin:8px 0 0 18px}
ol{margin:16px 0 16px 20px;color:var(--mute)}
ol li{margin-bottom:8px}
.status{border:1px solid var(--line);border-radius:12px;background:var(--card);padding:6px 18px;
  margin:20px 0}
.srow{display:flex;justify-content:space-between;gap:16px;padding:12px 0;
  border-bottom:1px solid var(--line);font-size:14.5px;flex-wrap:wrap}
.srow:last-child{border-bottom:0}
.srow span{color:var(--mute)}
.srow strong{text-align:right;font-weight:600;word-break:break-word}
.tagpill{font-family:'Azeret Mono',monospace;font-size:9.5px;letter-spacing:.14em;
  text-transform:uppercase;padding:3px 9px;border-radius:999px;border:1px solid var(--line2);
  color:var(--mute);font-weight:400;white-space:nowrap}
.okpill{border-color:var(--ok);color:var(--ok)}
.warnpill{border-color:var(--warn);color:var(--warn)}
</style>
</head>
<body>
<div class="shell">
  <span class="brand"><i></i>TECHBISS</span>
  <h1><?= esc($heading !== '' ? $heading : $title) ?></h1>
  <?= $body ?>
</div>
</body>
</html><?php
    exit;
}
