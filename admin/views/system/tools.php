<?php
/** @var array $tables @var array $exports */
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
                The SQL backup contains your content and lead data. Store it somewhere private — it is not encrypted.
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
        delete <code>database/install.php</code> after installation, keep <code>config/config.php</code> outside version control,
        serve the whole site over HTTPS and enable <code>cookie_secure</code> in the config once you do.
    </span>
</div>
