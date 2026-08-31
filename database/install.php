<?php
declare(strict_types=1);

/**
 * TECHBISS installer.
 *
 * Creates the schema, loads the baseline content and sets up the first
 * administrator. Delete this file once installation is complete — it refuses
 * to run again on its own if an admin account already exists.
 *
 * Also runnable from the command line:  php database/install.php
 */

$root = dirname(__DIR__);
require $root . '/includes/bootstrap.php';

use Techbiss\Core\Auth;
use Techbiss\Core\Csrf;
use Techbiss\Core\Database;
use Techbiss\Core\Request;
use Techbiss\Core\Validator;

$db  = Database::instance();
$cli = PHP_SAPI === 'cli';

/** Execute a .sql file statement by statement. */
$runSqlFile = static function (string $file) use ($db): array {
    if (!is_file($file)) {
        return ['ok' => false, 'message' => basename($file) . ' is missing.'];
    }
    $sql = (string) file_get_contents($file);
    // Strip line comments, then split on semicolons at end of line.
    $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
    $statements = array_filter(array_map('trim', preg_split('/;\s*[\r\n]/', $sql) ?: []));
    $count = 0;
    foreach ($statements as $statement) {
        if ($statement === '' || $statement === ';') {
            continue;
        }
        $db->pdo()->exec($statement);
        $count++;
    }
    return ['ok' => true, 'message' => $count . ' statements executed from ' . basename($file)];
};

$installed = $db->tableExists('admins') && $db->int('SELECT COUNT(*) FROM admins') > 0;

// ---------------------------------------------------------------------
// CLI mode
// ---------------------------------------------------------------------
if ($cli) {
    $args = getopt('', ['name:', 'email:', 'password:', 'force']);
    $force = isset($args['force']);

    if ($installed && !$force) {
        fwrite(STDERR, "TECHBISS is already installed. Pass --force to reload the schema (this DESTROYS existing data).\n");
        exit(1);
    }

    echo "Creating schema…\n";
    echo '  ' . $runSqlFile($root . '/database/schema.sql')['message'] . "\n";

    if (!$installed || $force) {
        $hasSeed = $db->int('SELECT COUNT(*) FROM roles') > 0;
        if (!$hasSeed) {
            echo "Loading baseline content…\n";
            echo '  ' . $runSqlFile($root . '/database/seed.sql')['message'] . "\n";
        } else {
            echo "Baseline content already present — skipping seed.\n";
        }
    }

    $name     = (string) ($args['name'] ?? 'Administrator');
    $email    = (string) ($args['email'] ?? '');
    $password = (string) ($args['password'] ?? '');

    if ($email === '' || $password === '') {
        echo "\nSchema ready. Create the first administrator with:\n";
        echo "  php database/install.php --name=\"Your Name\" --email=you@example.com --password='a-strong-password'\n";
        exit(0);
    }

    $roleId = (int) $db->value("SELECT id FROM roles WHERE slug = 'super-admin'", [], 0);
    if ($roleId === 0) {
        fwrite(STDERR, "The super-admin role is missing. Load database/seed.sql first.\n");
        exit(1);
    }
    if ($db->int('SELECT COUNT(*) FROM admins WHERE email = ?', [$email]) > 0) {
        fwrite(STDERR, "An administrator with that email already exists.\n");
        exit(1);
    }

    $now = date('Y-m-d H:i:s');
    $db->insert('admins', [
        'role_id'       => $roleId,
        'name'          => $name,
        'email'         => strtolower($email),
        'password_hash' => Auth::hash($password),
        'is_active'     => 1,
        'created_at'    => $now,
        'updated_at'    => $now,
    ]);

    echo "\nAdministrator created: {$email}\n";
    echo "Sign in at /admin/login and then delete database/install.php.\n";
    exit(0);
}

// ---------------------------------------------------------------------
// Browser mode
// ---------------------------------------------------------------------
$errors  = [];
$done    = false;
$request = Request::capture(\Techbiss\Core\App::basePath());

if ($installed) {
    http_response_code(403);
    echo '<!doctype html><meta charset="utf-8"><title>Already installed</title>'
        . '<body style="font:16px/1.7 ui-sans-serif,system-ui;background:#06070c;color:#e9ecf6;padding:56px;max-width:44rem;margin:auto">'
        . '<h1 style="font-size:1.5rem">TECHBISS is already installed</h1>'
        . '<p style="color:#838da3">An administrator account already exists, so the installer will not run again.</p>'
        . '<p style="color:#838da3"><strong>Delete <code>database/install.php</code> now.</strong></p>'
        . '<p><a href="' . htmlspecialchars(url('/admin/login'), ENT_QUOTES) . '" style="color:#4f8cff">Go to the admin sign-in page</a></p>'
        . '</body>';
    exit;
}

if ($request->isPost()) {
    Csrf::verify($request);

    $v = Validator::make($request->all())
        ->required('name', 'Your name', 2, 120)
        ->email('email')
        ->password('password', 'Password')
        ->matches('password', 'password_confirm');

    if ($v->fails()) {
        $errors = $v->errors();
    } else {
        try {
            $runSqlFile($root . '/database/schema.sql');
            if ($db->int('SELECT COUNT(*) FROM roles') === 0) {
                $runSqlFile($root . '/database/seed.sql');
            }
            $roleId = (int) $db->value("SELECT id FROM roles WHERE slug = 'super-admin'", [], 0);
            if ($roleId === 0) {
                throw new RuntimeException('The super-admin role could not be created.');
            }
            $now = date('Y-m-d H:i:s');
            $db->insert('admins', [
                'role_id'       => $roleId,
                'name'          => $v->get('name'),
                'email'         => $v->get('email'),
                'password_hash' => Auth::hash((string) $request->post('password')),
                'is_active'     => 1,
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);
            $done = true;
        } catch (Throwable $e) {
            $errors['general'] = 'Installation failed: ' . $e->getMessage();
        }
    }
}

$e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
?><!doctype html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Install TECHBISS</title>
    <link rel="stylesheet" href="<?= $e(asset('assets/css/design-system.css')) ?>">
    <link rel="stylesheet" href="<?= $e(asset('assets/css/site.css')) ?>">
</head>
<body>
<main class="section" style="min-height:100dvh;display:grid;place-items:center">
    <div class="container container--narrow">
        <div class="card card--pad-lg" style="max-width:520px;margin-inline:auto">
            <div class="row row--tight mb-5">
                <span class="brand__glyph" aria-hidden="true">T</span>
                <span class="brand__text">TECHBISS</span>
            </div>

            <?php if ($done): ?>
                <span class="icon-plate icon-plate--lg" style="background:rgba(52,211,153,.12);color:var(--success);border-color:transparent">
                    <?= icon('check') ?>
                </span>
                <h1 class="mt-5" style="font-size:1.35rem">Installation complete</h1>
                <p class="card__text mt-3">
                    The database is ready and your administrator account has been created.
                </p>
                <div class="notice notice--warning mt-5">
                    <?= icon('alert') ?>
                    <span><strong style="color:var(--text)">Delete <code>database/install.php</code> now.</strong>
                    Leaving the installer in place is a security risk.</span>
                </div>
                <div class="row mt-6">
                    <a class="btn btn--primary" href="<?= $e(url('/admin/login')) ?>">Sign in to the admin panel</a>
                    <a class="btn btn--ghost" href="<?= $e(url('/')) ?>">View the site</a>
                </div>
            <?php else: ?>
                <h1 style="font-size:1.35rem">Install TECHBISS</h1>
                <p class="card__text mt-2 mb-5">
                    This creates the database tables, loads the baseline content and sets up your administrator account.
                </p>

                <?php if (isset($errors['general'])): ?>
                <div class="notice notice--danger mb-4"><?= icon('alert') ?><span><?= $e($errors['general']) ?></span></div>
                <?php endif; ?>

                <form method="post" class="stack stack-4">
                    <?= Csrf::field() ?>
                    <div class="field">
                        <label class="label" for="i-name">Your name</label>
                        <input class="input<?= isset($errors['name']) ? ' is-invalid' : '' ?>" id="i-name" type="text"
                               name="name" value="<?= $e((string) $request->str('name')) ?>" required maxlength="120">
                        <?php if (isset($errors['name'])): ?><span class="field-error"><?= $e($errors['name']) ?></span><?php endif; ?>
                    </div>
                    <div class="field">
                        <label class="label" for="i-email">Email address</label>
                        <input class="input<?= isset($errors['email']) ? ' is-invalid' : '' ?>" id="i-email" type="email"
                               name="email" value="<?= $e((string) $request->str('email')) ?>" required maxlength="190">
                        <?php if (isset($errors['email'])): ?><span class="field-error"><?= $e($errors['email']) ?></span><?php endif; ?>
                    </div>
                    <div class="field">
                        <label class="label" for="i-password">Password</label>
                        <input class="input<?= isset($errors['password']) ? ' is-invalid' : '' ?>" id="i-password"
                               type="password" name="password" required minlength="10" autocomplete="new-password">
                        <span class="hint">At least 10 characters, containing both letters and numbers.</span>
                        <?php if (isset($errors['password'])): ?><span class="field-error"><?= $e($errors['password']) ?></span><?php endif; ?>
                    </div>
                    <div class="field">
                        <label class="label" for="i-confirm">Confirm password</label>
                        <input class="input" id="i-confirm" type="password" name="password_confirm" required autocomplete="new-password">
                    </div>
                    <button class="btn btn--primary btn--lg btn--block" type="submit">Install TECHBISS</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</main>
</body>
</html>
