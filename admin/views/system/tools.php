<?php
/** @var array $tables @var array $exports @var array $security @var array $migration */
use Techbiss\Core\Auth;
$human = static function (int $b): string {
    $u = ['B', 'KB', 'MB', 'GB']; $i = 0; $n = (float) $b;
    while ($n >= 1024 && $i < 3) { $n /= 1024; $i++; }
    return round($n, $i === 0 ? 0 : 1) . ' ' . $u[$i];
};
$totalBytes = 0;
foreach ($tables as $t) { $totalBytes += (int) $t['bytes']; }
?>
<div class="page-header">
    <div>
        <h1>System &amp; maintenance</h1>
        <p>Environment details, exports and housekeeping.</p>
    </div>
</div>

<div class="tiles">
    <div class="tile"><span class="tile__label"><?= icon('database') ?>Database size</span>
        <span class="tile__value"><?= e($human($totalBytes)) ?></span>
        <span class="tile__meta"><?= count($tables) ?> tables</span></div>
    <div class="tile"><span class="tile__label"><?= icon('image') ?>Uploads</span>
        <span class="tile__value"><?= e($human((int) $uploads)) ?></span>
        <span class="tile__meta">On disk</span></div>
    <div class="tile"><span class="tile__label"><?= icon('settings') ?>PHP</span>
        <span class="tile__value" style="font-size:1.2rem"><?= e($php) ?></span>
        <span class="tile__meta">MySQL <?= e(explode('-', (string) $server)[0]) ?></span></div>
    <div class="tile"><span class="tile__label"><?= icon('package') ?>TECHBISS</span>
        <span class="tile__value" style="font-size:1.2rem"><?= e($appVersion) ?></span>
        <span class="tile__meta">Application version</span></div>
</div>

<?php
$secClass = static fn (string $s): string => match ($s) {
    'pass' => 'live', 'warn' => 'warn', 'fail' => 'danger', default => 'info',
};
$security = is_array($security) ? $security : [];
$secFails = 0;
foreach ($security as $s) { if ($s['status'] === 'fail') { $secFails++; } }
?>
<?php if ($migration['pending'] > 0 || $migration['error'] !== '' || $migration['mismatched'] !== []): ?>
<div class="panel">
    <div class="panel__head">
        <div>
            <span class="panel__title">Database updates</span>
            <div class="panel__sub">
                This copy of the software expects fields your database does not have yet.
            </div>
        </div>
        <?php if ($migration['pending'] > 0): ?>
        <span class="badge badge--accent nowrap"><?= (int) $migration['pending'] ?> pending</span>
        <?php endif; ?>
    </div>
    <div class="panel__body">
        <?php if ($migration['error'] !== ''): ?>
        <div class="alert alert--warn">
            <strong>The database could not be checked.</strong> <?= e($migration['error']) ?>
        </div>
        <?php endif; ?>

        <?php if ($migration['pending'] > 0): ?>
        <p>
            These are additions only — new tables, new fields and the permissions a new feature needs.
            Nothing existing is removed, renamed or overwritten, so your content is not at risk.
            Take a backup first anyway if you would rather be careful.
        </p>
        <ul class="plain-list mt-4">
            <?php foreach (array_slice($migration['items'], 0, 40) as $item): ?>
            <li class="hint"><?= e($item) ?></li>
            <?php endforeach; ?>
            <?php if (count($migration['items']) > 40): ?>
            <li class="hint">…and <?= count($migration['items']) - 40 ?> more.</li>
            <?php endif; ?>
        </ul>
        <form method="post" class="mt-5" action="<?= e(url('/admin/system/migrate')) ?>"
              data-confirm="Apply <?= (int) $migration['pending'] ?> database updates? These are additions only.">
            <?= csrf_field() ?>
            <button class="btn btn--primary btn--sm" type="submit"><?= icon('database') ?>Apply the updates</button>
        </form>
        <?php endif; ?>

        <?php if ($migration['mismatched'] !== []): ?>
        <div class="alert alert--warn mt-5">
            <strong>These fields exist but no longer match the current schema:</strong>
            <?= e(implode(', ', array_slice($migration['mismatched'], 0, 20))) ?>.
            Nothing was changed — altering a field that already holds data is your call, not the software's.
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<div class="panel">
    <div class="panel__head">
        <div>
            <span class="panel__title">Security check</span>
            <div class="panel__sub">
                Asks this server for the files that should be private, and reports what actually comes back.
            </div>
        </div>
        <div class="row row--tight">
            <?php if ($secFails > 0): ?>
            <span class="badge badge--accent nowrap"><?= (int) $secFails ?> need<?= $secFails === 1 ? 's' : '' ?> attention</span>
            <?php endif; ?>
            <form method="post" action="<?= e(url('/admin/system/recheck')) ?>">
                <?= csrf_field() ?>
                <button class="btn btn--quiet btn--sm" type="submit"><?= icon('refresh') ?>Run again</button>
            </form>
        </div>
    </div>
    <div class="panel__body">
        <?php if ($security === []): ?>
        <div class="empty-state" style="border:0;background:none;padding:1.5rem 0">
            <p style="margin:0">
                Not run yet. This asks your server for the files that are supposed to be private — such as
                <code>config/config.php</code> — and reports what it actually gets back.
            </p>
            <form method="post" class="mt-4" action="<?= e(url('/admin/system/recheck')) ?>">
                <?= csrf_field() ?>
                <button class="btn btn--primary btn--sm" type="submit"><?= icon('shield') ?>Run the check</button>
            </form>
        </div>
        <?php else: ?>
        <div class="stack stack-4">
            <?php foreach ($security as $item): ?>
            <div>
                <div class="row row--tight">
                    <span class="status-dot status-dot--<?= e($secClass($item['status'])) ?>"><?= e($item['detail']) ?></span>
                    <strong style="font-size:var(--fs-sm)"><?= e($item['label']) ?></strong>
                </div>
                <p class="hint mt-2" style="max-width:70ch"><?= e($item['note']) ?></p>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="grid-panels">
    <?php if (Auth::can('export.manage')): ?>
    <div class="panel">
        <div class="panel__head">
            <div>
                <span class="panel__title">Export data</span>
                <div class="panel__sub">CSV downloads of your own records.</div>
            </div>
        </div>
        <div class="panel__body">
            <div class="stack stack-2">
                <?php foreach ($exports as $exp):
                    if (!Auth::can($exp['permission'])) { continue; } ?>
                <a class="btn btn--ghost btn--sm btn--block" href="<?= e(url($exp['url'])) ?>" style="justify-content:flex-start">
                    <?= icon('download') ?><?= e($exp['label']) ?>
                </a>
                <?php endforeach; ?>
                <a class="btn btn--ghost btn--sm btn--block" href="<?= e(url('/admin/system/backup')) ?>" style="justify-content:flex-start">
                    <?= icon('database') ?>Full database backup (.sql)
                </a>
            </div>
            <p class="help-text mt-4">
                <strong>The SQL backup is a complete copy of the database.</strong> It contains administrator password
                hashes and the personal details of every customer and lead, and it is not encrypted. Store it somewhere
                private and delete copies you no longer need. The CSV exports above carry only the records named on each
                button.
            </p>
        </div>
    </div>
    <?php endif; ?>

    <div class="panel">
        <div class="panel__head"><span class="panel__title">Housekeeping</span></div>
        <div class="panel__body">
            <div class="stack stack-4">
                <form method="post" action="<?= e(url('/admin/system/cache')) ?>">
                    <?= csrf_field() ?>
                    <button class="btn btn--ghost btn--sm btn--block" type="submit" style="justify-content:flex-start">
                        <?= icon('refresh') ?>Clear the application cache
                    </button>
                    <span class="hint">Use this if a content change is not appearing on the public site.</span>
                </form>

                <?php if (Auth::can('logs.view')): ?>
                <form method="post" action="<?= e(url('/admin/system/prune-logs')) ?>"
                      data-confirm="Delete activity log entries older than the selected period?">
                    <?= csrf_field() ?>
                    <div class="row row--tight">
                        <label class="sr-only" for="prune-days">Keep logs for</label>
                        <select class="select" id="prune-days" name="days" style="max-width:150px">
                            <option value="30">30 days</option>
                            <option value="90">90 days</option>
                            <option value="180" selected>180 days</option>
                            <option value="365">1 year</option>
                        </select>
                        <button class="btn btn--ghost btn--sm" type="submit"><?= icon('trash') ?>Prune older</button>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="panel">
    <div class="panel__head">
        <div>
            <span class="panel__title">Database tables</span>
            <div class="panel__sub">Row counts are engine estimates, not exact figures.</div>
        </div>
    </div>
    <div class="table-wrap" style="border:0;border-radius:0;background:none;max-height:420px">
        <table class="data-table" style="min-width:420px">
            <thead><tr><th>Table</th><th class="num">Rows (approx.)</th><th class="num">Size</th></tr></thead>
            <tbody>
                <?php foreach ($tables as $t): ?>
                <tr>
                    <td><span class="mono-sm"><?= e($t['name']) ?></span></td>
                    <td class="num"><?= number_format((int) $t['rows_estimate']) ?></td>
                    <td class="num"><span class="hint"><?= e($human((int) $t['bytes'])) ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="notice notice--warning mt-4">
    <?= icon('alert') ?>
    <span>
        <strong style="color:var(--text)">Security checklist:</strong>
        delete <code>install.php</code> after installation, keep <code>config/config.php</code> outside version control,
        serve the whole site over HTTPS and enable <code>cookie_secure</code> in the config once you do.
    </span>
</div>
