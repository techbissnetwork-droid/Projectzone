<?php
/** @var array $row @var array $quotes @var array $industries */
$val = static fn (string $k, $d = '') => old($k, $row[$k] ?? $d);
?>
<div class="page-header">
    <div>
        <h1><?= e($row['name']) ?></h1>
        <p><?= e($row['business_name'] ?: 'No business name recorded') ?> · customer since <?= e(format_date($row['created_at'], 'F Y')) ?></p>
    </div>
    <div class="page-header__actions">
        <a class="btn btn--ghost btn--sm" href="<?= e(url('/admin/customers')) ?>"><?= icon('arrow-left') ?>Back</a>
        <a class="btn btn--quiet btn--sm" href="mailto:<?= e($row['email']) ?>"><?= icon('mail') ?>Email</a>
    </div>
</div>

<div class="form-cols">
    <div>
        <form method="post" action="<?= e(url('/admin/customers/' . (int) $row['id'])) ?>" data-dirty-guard>
            <?= csrf_field() ?>
            <div class="panel">
                <div class="panel__head"><span class="panel__title">Details</span></div>
                <div class="panel__body">
                    <?= $view->partial('customers/_fields', ['val' => $val, 'industries' => $industries]) ?>
                </div>
                <div class="panel__foot">
                    <button class="btn btn--primary btn--sm" type="submit"><?= icon('check') ?>Save</button>
                </div>
            </div>
        </form>

        <?php if ($quotes): ?>
        <div class="panel">
            <div class="panel__head"><span class="panel__title">Requests from this email</span></div>
            <div class="table-wrap" style="border:0;border-radius:0;background:none">
                <table class="data-table" style="min-width:480px">
                    <thead><tr><th>Reference</th><th>Source</th><th>Status</th><th class="num">Received</th></tr></thead>
                    <tbody>
                        <?php foreach ($quotes as $q): ?>
                        <tr>
                            <td><a class="mono-sm" href="<?= e(url('/admin/quotes/' . (int) $q['id'])) ?>"><?= e($q['reference']) ?></a></td>
                            <td><span class="badge"><?= e(ucfirst((string) $q['source'])) ?></span></td>
                            <td><span class="status-dot status-dot--<?= $q['status'] === 'new' ? 'warn' : 'draft' ?>"><?= e(ucfirst((string) $q['status'])) ?></span></td>
                            <td class="num"><span class="hint"><?= e(time_ago($q['created_at'])) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <aside class="form-aside">
        <div class="panel">
            <div class="panel__body">
                <div class="kv-list">
                    <div class="kv"><span class="kv__label">Record created</span><span class="kv__value"><?= e(format_date($row['created_at'], 'j M Y')) ?></span></div>
                    <div class="kv"><span class="kv__label">Last updated</span><span class="kv__value"><?= e(time_ago($row['updated_at'])) ?></span></div>
                    <div class="kv"><span class="kv__label">Requests</span><span class="kv__value"><?= count($quotes) ?></span></div>
                </div>
            </div>
        </div>

        <form method="post" action="<?= e(url('/admin/customers/' . (int) $row['id'] . '/delete')) ?>"
              data-confirm="Delete this customer and every purchase record attached to them? This cannot be undone.">
            <?= csrf_field() ?>
            <button class="btn btn--danger btn--sm btn--block" type="submit"><?= icon('trash') ?>Delete customer</button>
        </form>
    </aside>
</div>
