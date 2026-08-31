<?php
/**
 * The installer's upgrade screen.
 *
 * Shown instead of a dead "already installed" refusal. An existing site can
 * bring its database up to date here, optionally add another administrator,
 * and — the step people otherwise skip — delete the installer afterwards.
 *
 * @var PDO|null $pdo @var array $uErrors @var array $uSteps @var bool $uDone
 * @var array|null $uLocked @var string $uEmail @var bool $uAuthed @var string $uName
 * @var array|null $uPlan @var string $installedUrl @var callable $eh
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
    if ($uPlan['copy'] > 0)    { $planLines[] = $uPlan['copy'] . ' piece' . ($uPlan['copy'] === 1 ? '' : 's') . ' of seeded wording to bring up to date'; }
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

        <?php if ($uLocked !== null): ?>
            <h1><?= $uLocked['ok'] ? 'Installer removed' : 'The installer is still there' ?></h1>
            <?php if ($uLocked['ok']): ?>
                <p class="muted">install.php has been deleted. Setup can no longer be reached from a browser.</p>
            <?php else: ?>
                <div class="alert alert--warn"><?= $eh($uLocked['error']) ?></div>
            <?php endif; ?>
            <div class="actions">
                <a class="btn btn--primary" href="<?= $eh($installedUrl) ?>">Go to the admin sign-in page</a>
            </div>

        <?php elseif ($uDone): ?>
            <h1>Upgrade complete</h1>
            <p class="muted">Signed in as <?= $eh($uName) ?>.</p>
            <div class="check-list">
                <?php foreach ($uSteps as $line): ?>
                <div class="check ok"><span class="mark">✓</span><span><?= $eh($line) ?></span></div>
                <?php endforeach; ?>
            </div>
            <div class="alert alert--warn">
                <strong>Delete <code>install.php</code> now.</strong>
                Leaving the installer in place is a security risk.
            </div>
            <form method="post">
                <input type="hidden" name="action" value="lock">
                <input type="hidden" name="upgrade_email" value="<?= $eh($uEmail) ?>">
                <label class="field">
                    <span>Confirm your password to remove it</span>
                    <input type="password" name="upgrade_password" autocomplete="current-password" required>
                </label>
                <div class="actions">
                    <button class="btn btn--primary" type="submit">Delete install.php</button>
                    <a class="btn btn--quiet" href="<?= $eh($installedUrl) ?>">I will delete it myself</a>
                </div>
            </form>

        <?php else: ?>
            <h1>This site is already installed</h1>
            <p class="muted">
                Setup will not run again over a live site. What you can do here is bring the database
                up to date after an update. Only new tables, columns and settings are added — nothing
                is dropped, renamed or overwritten, and wording you have edited yourself is left alone.
            </p>

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
                <p class="muted">You can still add an administrator here, or remove the installer.</p>
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
                    </label>

                    <label class="check-inline">
                        <input type="checkbox" name="add_admin" value="1" <?= isset($_POST['add_admin']) ? 'checked' : '' ?>>
                        <span>Also create a new administrator
                            <em>Leave this unticked to keep the administrators you already have. Nothing is removed either way.</em>
                        </span>
                    </label>

                    <label class="field">
                        <span>New administrator name</span>
                        <input type="text" name="new_admin_name" value="<?= $eh($_POST['new_admin_name'] ?? '') ?>" autocomplete="name"
                               class="<?= isset($uErrors['new_admin_name']) ? 'bad' : '' ?>">
                        <?php if (isset($uErrors['new_admin_name'])): ?><em class="err"><?= $eh($uErrors['new_admin_name']) ?></em><?php endif; ?>
                    </label>
                    <div class="row">
                        <label class="field">
                            <span>Email</span>
                            <input type="email" name="new_admin_email" value="<?= $eh($_POST['new_admin_email'] ?? '') ?>"
                                   class="<?= isset($uErrors['new_admin_email']) ? 'bad' : '' ?>">
                            <?php if (isset($uErrors['new_admin_email'])): ?><em class="err"><?= $eh($uErrors['new_admin_email']) ?></em><?php endif; ?>
                        </label>
                        <label class="field">
                            <span>Password</span>
                            <input type="password" name="new_admin_password" autocomplete="new-password"
                                   class="<?= isset($uErrors['new_admin_password']) ? 'bad' : '' ?>">
                            <?php if (isset($uErrors['new_admin_password'])): ?><em class="err"><?= $eh($uErrors['new_admin_password']) ?></em><?php endif; ?>
                        </label>
                    </div>

                    <div class="actions">
                        <button class="btn btn--primary" type="submit">Run the upgrade</button>
                        <a class="btn btn--quiet" href="<?= $eh($installedUrl) ?>">Go to the admin</a>
                    </div>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</main>
</body>
</html>
