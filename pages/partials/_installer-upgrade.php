<?php
/**
 * The installer's upgrade screen.
 *
 * Shown instead of a dead "already installed" refusal. An existing site can
 * bring its database up to date here. Signing in for the upgrade already
 * proves who you are, so a successful run removes install.php in the same
 * step rather than asking for a second confirmation afterwards — leaving
 * the installer in place is the actual risk, not deleting it automatically.
 *
 * @var PDO|null $pdo @var array $uErrors @var array $uSteps @var bool $uDone
 * @var array|null $uLocked @var string $uEmail @var bool $uAuthed @var string $uName
 * @var array|null $uPlan @var string $installedUrl @var callable $eh @var bool $needsConfig
 */
$hasWork = $uPlan !== null
    && ($uPlan['tables'] + $uPlan['columns'] + $uPlan['indexes'] + $uPlan['data'] + $uPlan['copy']) > 0;

/** @var list<string> $planLines */
$planLines = [];
if ($uPlan !== null) {
    if ($uPlan['tables'] > 0)  { $planLines[] = $uPlan['tables'] . ' table' . ($uPlan['tables'] === 1 ? '' : 's') . ' to add'; }
    if ($uPlan['columns'] > 0) { $planLines[] = $uPlan['columns'] . ' column' . ($uPlan['columns'] === 1 ? '' : 's') . ' to add'; }
    if ($uPlan['indexes'] > 0) { $planLines[] = $uPlan['indexes'] . ' index' . ($uPlan['indexes'] === 1 ? '' : 'es') . ' to add'; }
    if ($uPlan['data'] > 0)    { $planLines[] = $uPlan['data'] . ' new setting' . ($uPlan['data'] === 1 ? '' : 's') . ', permission' . ($uPlan['data'] === 1 ? '' : 's') . ' or menu entr' . ($uPlan['data'] === 1 ? 'y' : 'ies') . ' to add'; }
    if ($uPlan['copy'] > 0)    { $planLines[] = $uPlan['copy'] . ' piece' . ($uPlan['copy'] === 1 ? '' : 's') . ' of seeded content to bring up to date'; }
}
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Upgrade this install</title>
    <link rel="icon" type="image/svg+xml" href="assets/images/brand/favicon.svg">
    <?php require __DIR__ . '/_installer-style.php'; ?>
</head>
<body>
<main class="wrap">
    <div class="card">
        <div class="brand">
            <img class="glyph" src="assets/images/brand/logo-mark.svg" width="32" height="32" alt="">
            <span class="name">TECHBISS</span>
        </div>

        <?php if ($uDone): ?>
            <h1><?= ($uLocked['ok'] ?? false) ? 'Upgrade complete — installer removed' : 'Upgrade complete' ?></h1>
            <p class="muted">Signed in as <?= $eh($uName) ?>.</p>
            <div class="check-list">
                <?php foreach ($uSteps as $line): ?>
                <div class="check ok"><span class="mark">✓</span><span><?= $eh($line) ?></span></div>
                <?php endforeach; ?>
                <?php if ($uLocked['ok'] ?? false): ?>
                <div class="check ok"><span class="mark">✓</span><span>Deleted install.php — setup can no longer be reached from a browser</span></div>
                <?php endif; ?>
            </div>
            <?php if (!($uLocked['ok'] ?? false)): ?>
            <div class="alert alert--warn">
                <strong>install.php is still on the server.</strong> <?= $eh($uLocked['error'] ?? '') ?>
                Delete the file yourself — leaving it in place is a security risk.
            </div>
            <?php endif; ?>
            <div class="actions">
                <a class="btn btn--primary" href="<?= $eh($installedUrl) ?>">Go to the admin sign-in page</a>
            </div>

        <?php else: ?>
            <?php if ($needsConfig): ?>
            <h1>That database already has a site in it</h1>
            <p class="muted">
                These files are new but the database is not, so this is a move or a restore rather
                than a first install. Setting it up again would seed over your content. Instead the
                upgrade writes the missing <code>config/config.php</code> and brings the database up
                to date: only new tables, columns and settings are added, nothing is dropped or
                overwritten, and wording you have edited yourself is left alone.
            </p>
            <?php else: ?>
            <h1>This site is already installed</h1>
            <p class="muted">
                Setup will not run again over a live site. What you can do here is bring the database
                up to date after an update. Only new tables, columns and settings are added — nothing
                is dropped, renamed or overwritten, and wording you have edited yourself is left alone.
            </p>
            <?php endif; ?>

            <?php if (!$pdo instanceof PDO): ?>
                <div class="alert alert--warn">
                    The database named in <code>config/config.php</code> could not be reached, so there is
                    nothing to upgrade. Fix the credentials and reload this page.
                </div>
                <div class="actions">
                    <a class="btn btn--primary" href="<?= $eh($installedUrl) ?>">Go to the admin sign-in page</a>
                </div>
            <?php else: ?>

                <?php if (isset($uErrors['plan'])): ?>
                <div class="alert alert--warn">The schema could not be read: <?= $eh($uErrors['plan']) ?></div>
                <?php elseif ($hasWork): ?>
                <div class="check-list">
                    <?php foreach ($planLines as $line): ?>
                    <div class="check warn"><span class="mark">+</span><span><?= $eh($line) ?></span></div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="check-list">
                    <div class="check ok"><span class="mark">✓</span><span>The database is already up to date</span></div>
                </div>
                <p class="muted">Signing in below still removes the installer.</p>
                <?php endif; ?>

                <?php if (isset($uErrors['upgrade'])): ?>
                <div class="alert alert--warn">
                    The upgrade stopped: <?= $eh($uErrors['upgrade']) ?><br>
                    Nothing after that point was applied. Fix the cause and run it again — the steps
                    that did succeed are simply skipped.
                </div>
                <?php endif; ?>

                <form method="post">
                    <input type="hidden" name="action" value="upgrade">

                    <?php if (isset($uErrors['auth'])): ?>
                    <div class="alert alert--warn"><?= $eh($uErrors['auth']) ?></div>
                    <?php endif; ?>

                    <label class="field">
                        <span>Administrator email</span>
                        <input type="email" name="upgrade_email" value="<?= $eh($uEmail) ?>" autocomplete="username" required
                               class="<?= isset($uErrors['auth']) ? 'bad' : '' ?>">
                        <em>Sign in as an existing super administrator. Changing a live database is not open to anyone who finds this page.</em>
                    </label>
                    <label class="field">
                        <span>Password</span>
                        <input type="password" name="upgrade_password" autocomplete="current-password" required
                               class="<?= isset($uErrors['auth']) ? 'bad' : '' ?>">
                        <em>Running the upgrade also removes install.php once it succeeds — no separate confirmation step.</em>
                    </label>

                    <div class="actions">
                        <button class="btn btn--primary" type="submit"><?= $needsConfig ? 'Connect and upgrade' : 'Run the upgrade' ?></button>
                        <a class="btn btn--quiet" href="<?= $eh($installedUrl) ?>">Go to the admin</a>
                    </div>
                </form>

                <details class="advanced">
                    <summary>Want a fresh install instead? (destroys existing data)</summary>
                    <p class="muted small">
                        This drops every table in this database and walks you through setup again from
                        scratch — new site details, a new administrator, everything currently in it gone.
                        Use this only if you mean to start over, not to upgrade.
                    </p>

                    <?php if (isset($uErrors['fresh'])): ?>
                    <div class="alert alert--bad"><?= $eh($uErrors['fresh']) ?></div>
                    <?php endif; ?>

                    <form method="post">
                        <input type="hidden" name="action" value="fresh">

                        <label class="field">
                            <span>Administrator email</span>
                            <input type="email" name="upgrade_email" autocomplete="username" required
                                   class="<?= isset($uErrors['auth']) ? 'bad' : '' ?>">
                            <em>Sign in as an existing super administrator — wiping a live database is not open to anyone who finds this page.</em>
                        </label>
                        <label class="field">
                            <span>Password</span>
                            <input type="password" name="upgrade_password" autocomplete="current-password" required
                                   class="<?= isset($uErrors['auth']) ? 'bad' : '' ?>">
                        </label>
                        <label class="field">
                            <span>Type DELETE to confirm</span>
                            <input type="text" name="confirm_wipe" autocomplete="off" required
                                   placeholder="DELETE" class="<?= isset($uErrors['fresh']) ? 'bad' : '' ?>">
                            <em>Case-sensitive. There is no undo once this runs.</em>
                        </label>

                        <div class="actions">
                            <button class="btn btn--bad" type="submit">Wipe database and start fresh</button>
                        </div>
                    </form>
                </details>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</main>
</body>
</html>
