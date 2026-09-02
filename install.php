<?php
/**
 * One-time setup: writes app/config.php, creates the tables, loads the starting
 * content and creates the first admin account. Delete this file afterwards.
 */
require __DIR__ . '/app/helpers.php';

$configFile = __DIR__ . '/app/config.php';
$errors = [];
$done   = false;

/* Once an admin exists, refuse to run again — otherwise anyone could reinstall. */
$alreadyInstalled = false;
if (is_file($configFile)) {
    require_once __DIR__ . '/app/db.php';
    require_once __DIR__ . '/app/schema.php';
    try {
        $alreadyInstalled = schema_installed() && (int) scalar('SELECT COUNT(*) FROM users') > 0;
    } catch (Throwable $e) {
        $alreadyInstalled = false;
    }
}

if (!$alreadyInstalled && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $driver = ($_POST['driver'] ?? 'mysql') === 'sqlite' ? 'sqlite' : 'mysql';
    $in = [
        'host'  => trim((string) ($_POST['host'] ?? 'localhost')),
        'name'  => trim((string) ($_POST['name'] ?? '')),
        'user'  => trim((string) ($_POST['user'] ?? '')),
        'pass'  => (string) ($_POST['pass'] ?? ''),
        'aname' => trim((string) ($_POST['aname'] ?? '')),
        'email' => mb_strtolower(trim((string) ($_POST['email'] ?? ''))),
        'apass' => (string) ($_POST['apass'] ?? ''),
        'mailto' => trim((string) ($_POST['mailto'] ?? '')),
        'mailfrom' => trim((string) ($_POST['mailfrom'] ?? '')),
    ];

    if ($driver === 'mysql' && $in['name'] === '') {
        $errors[] = 'Enter the database name.';
    }
    if ($in['aname'] === '') {
        $errors[] = 'Enter your name.';
    }
    if (!filter_var($in['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address to sign in with.';
    }
    if (strlen($in['apass']) < 10) {
        $errors[] = 'Use at least 10 characters for the admin password.';
    }
    if ($in['mailto'] !== '' && !filter_var($in['mailto'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'The address enquiries are sent to is not valid.';
    }

    if (!$errors) {
        $config = [
            'db' => [
                'driver' => $driver,
                'host' => $in['host'], 'name' => $in['name'],
                'user' => $in['user'], 'pass' => $in['pass'],
                'charset' => 'utf8mb4',
                'sqlite_path' => __DIR__ . '/storage/techbiss.sqlite',
            ],
            'mail' => [
                'to' => $in['mailto'] ?: $in['email'],
                'from' => $in['mailfrom'] ?: $in['email'],
                'from_name' => 'TECHBISS Website',
            ],
            'base_url' => '',
            'debug' => false,
        ];

        $php = "<?php\n// Written by install.php. Safe to edit by hand.\nreturn " . var_export($config, true) . ";\n";
        if (@file_put_contents($configFile, $php) === false) {
            $errors[] = 'Could not write app/config.php. Give the app folder write permission (755) and try again.';
        } else {
            // Load the app fresh so it picks up the config we just wrote.
            require_once __DIR__ . '/app/db.php';
            require_once __DIR__ . '/app/schema.php';
            require_once __DIR__ . '/app/seed.php';
            try {
                db(); // fails fast on bad credentials
                schema_create_all();
                if ((int) scalar('SELECT COUNT(*) FROM content') === 0) {
                    seed_all();
                }
                if ((int) scalar('SELECT COUNT(*) FROM users') === 0) {
                    db_insert('users', [
                        'name' => $in['aname'],
                        'email' => $in['email'],
                        'password_hash' => password_hash($in['apass'], PASSWORD_DEFAULT),
                        'role' => 'admin',
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
                }
                $done = true;
            } catch (Throwable $e) {
                @unlink($configFile);
                $errors[] = 'Could not connect to the database: ' . $e->getMessage();
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Install — TECHBISS</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<link rel="stylesheet" href="admin/assets/admin.css">
</head>
<body>
<div class="ainstall">
  <div class="abrand abrand--big"><span class="amark">T</span> TECHBISS</div>

<?php if ($alreadyInstalled): ?>
  <div class="astep">
    <h1>Already installed</h1>
    <p class="amuted">This site is set up. For security, delete <code>install.php</code> from the server now.</p>
    <p><a class="abtn" href="admin/login.php">Go to the admin area</a></p>
  </div>

<?php elseif ($done): ?>
  <div class="astep">
    <h1>Done.</h1>
    <p class="amuted">The database is set up and your admin account is ready.</p>
    <ol>
      <li><strong>Delete <code>install.php</code> from the server.</strong> Anyone can reach it otherwise.</li>
      <li>Sign in and change anything you like under “Page text” and “Content”.</li>
      <li>Send yourself a test message through the contact form to confirm email works.</li>
    </ol>
    <p><a class="abtn" href="admin/login.php">Sign in</a> <a class="abtn" href="index.php">View the site</a></p>
  </div>

<?php else: ?>
  <div class="astep">
    <h1>Set up your site</h1>
    <p class="amuted">Two minutes. Create a database in cPanel first (MySQL Databases → create a database, create a user, add the user to the database with all privileges), then fill this in.</p>

    <?php if ($errors): ?>
      <div class="aflash aflash--err"><?php foreach ($errors as $er): ?><div><?= e($er) ?></div><?php endforeach; ?></div>
    <?php endif; ?>

    <form method="post">
      <h2>Database</h2>
      <div class="afield">
        <label for="driver">Type</label>
        <select id="driver" name="driver">
          <option value="mysql">MySQL — normal cPanel hosting</option>
          <option value="sqlite">SQLite — no database server needed (testing)</option>
        </select>
      </div>
      <div class="afield"><label for="host">Host</label><input type="text" id="host" name="host" value="localhost"></div>
      <div class="afield"><label for="name">Database name</label><input type="text" id="name" name="name" placeholder="youruser_techbiss"></div>
      <div class="afield"><label for="user">Database user</label><input type="text" id="user" name="user" placeholder="youruser_techbiss"></div>
      <div class="afield"><label for="pass">Database password</label><input type="password" id="pass" name="pass"></div>

      <h2>Your admin account</h2>
      <div class="afield"><label for="aname">Your name</label><input type="text" id="aname" name="aname" required></div>
      <div class="afield"><label for="email">Email to sign in with</label><input type="email" id="email" name="email" required></div>
      <div class="afield"><label for="apass">Password</label><input type="password" id="apass" name="apass" required><p class="ahint">At least 10 characters. This is the only way into the admin area.</p></div>

      <h2>Enquiry email</h2>
      <div class="afield"><label for="mailto">Send enquiries to</label><input type="email" id="mailto" name="mailto" placeholder="hello@yourdomain.com"><p class="ahint">Leave empty to use your sign-in address.</p></div>
      <div class="afield"><label for="mailfrom">Send them from</label><input type="email" id="mailfrom" name="mailfrom" placeholder="website@yourdomain.com"><p class="ahint">Must be an address on your own domain, or your host will refuse to send it.</p></div>

      <div class="arow"><button type="submit">Install</button></div>
    </form>
  </div>
<?php endif; ?>
</div>
</body>
</html>
