<?php
/**
 * One-time installer.
 *
 * Creates the database tables, seeds the starting content, makes the first
 * admin account and writes app/config.php. It refuses to run a second time —
 * but delete it from the server anyway once you are done.
 */

require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/seed.php';

$configFile = APP_DIR . '/config.php';

/* Already installed? Say so and stop. */
if (is_file($configFile) && !empty(config())) {
    $ok = false;
    try {
        $ok = db_installed();
    } catch (Throwable $e) {
        $ok = false;
    }
    if ($ok) {
        install_page('Already installed', '
            <p>This site is installed. The installer will not run again.</p>
            <p class="warn"><strong>Delete <code>install.php</code> from the server now.</strong>
               It refuses to run twice, but it has no business being there.</p>
            <p><a class="btn" href="admin/">Go to the admin area</a>
               <a class="btn ghost" href="index.php">View the site</a></p>');
    }
}

$errors = [];
$step   = 'form';

if (is_post()) {
    csrf_check();

    $driver = post('driver', 'mysql') === 'sqlite' ? 'sqlite' : 'mysql';
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
        'base_url'    => rtrim(post('base_url'), '/'),
        'app_key'     => bin2hex(random_bytes(16)),
    ];

    $adminName  = post('admin_name');
    $adminEmail = strtolower(post('admin_email'));
    $adminPass  = $_POST['admin_password'] ?? '';

    /* --- validate ---------------------------------------------------- */
    if ($driver === 'mysql') {
        if ($cfg['database'] === '') { $errors[] = 'Enter the database name.'; }
        if ($cfg['username'] === '') { $errors[] = 'Enter the database username.'; }
    }
    if ($adminName === '')            { $errors[] = 'Enter your name.'; }
    if (!valid_email($adminEmail))    { $errors[] = 'Enter a valid email address for the admin account.'; }
    if ($p = password_problem($adminPass)) { $errors[] = 'Admin password: ' . $p; }
    if ($cfg['mail_from'] !== '' && !valid_email($cfg['mail_from'])) {
        $errors[] = 'The "from" address is not a valid email address.';
    }
    if ($cfg['mail_to'] !== '' && !valid_email($cfg['mail_to'])) {
        $errors[] = 'The "send enquiries to" address is not a valid email address.';
    }

    /* --- try the connection ------------------------------------------- */
    if (!$errors) {
        try {
            $GLOBALS['__install_config'] = $cfg;
            install_use_config($cfg);
            db();
        } catch (Throwable $e) {
            $errors[] = $driver === 'mysql'
                ? 'Could not connect to MySQL: ' . $e->getMessage()
                : 'Could not open the SQLite file: ' . $e->getMessage();
        }
    }

    /* --- create, seed, write config ----------------------------------- */
    if (!$errors) {
        try {
            schema_create();

            if (db_count('SELECT COUNT(*) FROM settings') === 0) {
                seed_all();
            }

            $existing = db_one('SELECT id FROM users WHERE email = ?', [$adminEmail]);
            if ($existing) {
                db_update('users', (int) $existing['id'], [
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
                $step = 'done';
            }
        } catch (Throwable $e) {
            $errors[] = 'Setup failed: ' . $e->getMessage();
        }
    }
}

/* --- done ------------------------------------------------------------- */
if ($step === 'done') {
    install_page('Installed', '
        <p class="ok">The database is set up and your admin account is ready.</p>
        <ol>
          <li><strong>Delete <code>install.php</code> from the server.</strong> This is the only
              step you must not skip.</li>
          <li>Sign in and change anything you like — every word on the public site is editable.</li>
        </ol>
        <p><a class="btn" href="admin/login.php">Sign in to the admin area</a>
           <a class="btn ghost" href="index.php">View the site</a></p>');
}

/* --- the form --------------------------------------------------------- */
$host    = $_POST['host']     ?? 'localhost';
$port    = $_POST['port']     ?? '3306';
$dbname  = $_POST['database'] ?? '';
$dbuser  = $_POST['username'] ?? '';
$aname   = $_POST['admin_name']  ?? '';
$aemail  = $_POST['admin_email'] ?? '';
$mfrom   = $_POST['mail_from']   ?? '';
$mto     = $_POST['mail_to']     ?? '';
$driver  = $_POST['driver']      ?? 'mysql';

$errorHtml = '';
if ($errors) {
    $errorHtml = '<div class="bad"><strong>That did not work:</strong><ul>';
    foreach ($errors as $e) {
        $errorHtml .= '<li>' . esc($e) . '</li>';
    }
    $errorHtml .= '</ul></div>';
}

$writable = is_writable(APP_DIR) ? '' :
    '<div class="warn">The <code>app</code> folder is not writable, so the installer will not be '
  . 'able to save <code>app/config.php</code>. Set it to 755 in your file manager first.</div>';

install_page('Install TECHBISS', $errorHtml . $writable . '
<form method="post" autocomplete="off">
  ' . csrf_field() . '

  <h2>1 &middot; Database</h2>
  <p class="hint">On cPanel: create a database and a user under <em>MySQL&nbsp;Databases</em>, add the
     user to the database with <strong>All Privileges</strong>, then copy the three values here.
     cPanel puts your account name in front of both, so they usually look like
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

  <h2>3 &middot; Email</h2>
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
  <div class="f"><label for="base_url">Site address <span class="hint">(optional)</span></label>
    <input id="base_url" name="base_url" placeholder="https://yourdomain.com">
    <span class="hint">Leave blank and it is detected automatically. Set it if links come out wrong.</span></div>

  <button class="btn" type="submit">Install</button>
  <p class="hint">Nothing is written until every field checks out. If mail fails later, no enquiry
     is lost — everything is saved to the database before mail is attempted.</p>
</form>

<script>
(function(){
  var fields = document.getElementById("mysqlfields");
  function sync(){
    var sqlite = document.querySelector("input[name=driver][value=sqlite]").checked;
    fields.style.display = sqlite ? "none" : "";
  }
  document.querySelectorAll("input[name=driver]").forEach(function(r){
    r.addEventListener("change", sync);
  });
  sync();
})();
</script>');


/* --- plumbing --------------------------------------------------------- */

/** Override config() for the duration of the install request. */
function install_use_config(array $cfg): void
{
    $GLOBALS['__install_config'] = $cfg;
}

/** The page chrome, so the installer looks like the rest of the site. */
function install_page(string $title, string $body): void
{
    ?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= esc($title) ?> — TECHBISS</title>
<meta name="robots" content="noindex">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=Manrope:wght@400;500;600&family=Azeret+Mono:wght@400&display=swap">
<style>
:root{--bg:#0b0b0d;--card:#16161a;--fg:#f4f4f6;--mute:rgba(244,244,246,.55);
  --line:rgba(255,255,255,.1);--line2:rgba(255,255,255,.2);--acc:#c9ff3d;--bad:#ff6b3d;--ok:#5ddba0;
  color-scheme:dark}
*{box-sizing:border-box;margin:0;padding:0}
body{background:var(--bg);color:var(--fg);font-family:'Manrope',system-ui,sans-serif;
  line-height:1.62;padding:6vh 5vw 12vh}
.shell{max-width:720px;margin:0 auto}
.brand{font-family:'Syne',system-ui,sans-serif;font-weight:800;font-size:22px;letter-spacing:-.04em;
  display:flex;align-items:center;gap:10px;margin-bottom:8px}
.brand i{width:11px;height:11px;border-radius:50%;background:var(--acc);box-shadow:0 0 16px var(--acc)}
h1{font-family:'Syne',system-ui,sans-serif;font-size:clamp(30px,5vw,46px);letter-spacing:-.04em;
  text-transform:uppercase;line-height:1;margin:14px 0 10px}
h2{font-family:'Syne',system-ui,sans-serif;font-size:19px;text-transform:uppercase;
  letter-spacing:-.02em;margin:34px 0 12px;padding-top:20px;border-top:1px solid var(--line)}
p{color:var(--mute);margin:10px 0}
a{color:var(--acc)}
code{font-family:'Azeret Mono',monospace;font-size:.88em;background:rgba(255,255,255,.06);
  padding:2px 6px;border-radius:4px;color:var(--fg)}
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
.row label{display:flex;align-items:center;gap:8px;text-transform:none;letter-spacing:0;
  font-family:'Manrope',sans-serif;font-size:15px;color:var(--fg)}
.btn{display:inline-block;margin-top:22px;padding:15px 30px;border-radius:999px;background:var(--acc);
  color:var(--bg);font-weight:600;border:0;cursor:pointer;font:inherit;font-weight:600;
  text-decoration:none}
.btn.ghost{background:transparent;color:var(--fg);border:1px solid var(--line2);margin-left:8px}
.btn:hover{filter:brightness(1.08)}
.bad,.warn,.ok{padding:16px 18px;border-radius:12px;margin:18px 0;font-size:15px}
.bad{border:1px solid var(--bad);color:var(--bad)}
.bad ul{margin:8px 0 0 18px}
.warn{border:1px solid rgba(201,255,61,.4);color:var(--acc)}
.ok{border:1px solid rgba(93,219,160,.5);color:var(--ok)}
ol{margin:16px 0 16px 20px;color:var(--mute)}
ol li{margin-bottom:8px}
</style>
</head>
<body>
<div class="shell">
  <span class="brand"><i></i>TECHBISS</span>
  <h1><?= esc($title) ?></h1>
  <?= $body ?>
</div>
</body>
</html><?php
    exit;
}
