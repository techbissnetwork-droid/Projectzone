<?php
/** @var array $row @var array $purchases @var array $quotes @var array $industries */
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
                    <div class="form-section">
                        <div class="field--row">
                            <div class="field">
                                <label class="label" for="cu-name">Name <span class="req">*</span></label>
                                <input class="input" id="cu-name" type="text" name="name" value="<?= e($val('name')) ?>" required maxlength="190">
                            </div>
                            <div class="field">
                                <label class="label" for="cu-business">Business name</label>
                                <input class="input" id="cu-business" type="text" name="business_name" value="<?= e($val('business_name')) ?>" maxlength="190">
                            </div>
                        </div>
                        <div class="field--row">
                            <div class="field">
                                <label class="label" for="cu-email">Email <span class="req">*</span></label>
                                <input class="input<?= error_for('email') ? ' is-invalid' : '' ?>" id="cu-email" type="email"
                                       name="email" value="<?= e($val('email')) ?>" required maxlength="190">
                                <?php if (error_for('email')): ?><span class="field-error"><?= e(error_for('email')) ?></span><?php endif; ?>
                            </div>
                            <div class="field">
                                <label class="label" for="cu-phone">Phone</label>
                                <input class="input" id="cu-phone" type="tel" name="phone" value="<?= e($val('phone')) ?>" maxlength="32">
                            </div>
                        </div>
                        <div class="field--row">
                            <div class="field">
                                <label class="label" for="cu-country">Country</label>
                                <input class="input" id="cu-country" type="text" name="country" value="<?= e($val('country')) ?>" maxlength="80">
                            </div>
                            <div class="field">
                                <label class="label" for="cu-city">City</label>
                                <input class="input" id="cu-city" type="text" name="city" value="<?= e($val('city')) ?>" maxlength="120">
                            </div>
                            <div class="field">
                                <label class="label" for="cu-website">Website</label>
                                <input class="input" id="cu-website" type="text" name="website" value="<?= e($val('website')) ?>" maxlength="255">
                            </div>
                        </div>
                        <div class="field--row">
                            <div class="field">
                                <label class="label" for="cu-industry">Industry</label>
                                <select class="select" id="cu-industry" name="industry_id">
                                    <option value="">None</option>
                                    <?php foreach ($industries as $ind): ?>
                                    <option value="<?= (int) $ind['id'] ?>" <?= (int) $val('industry_id') === (int) $ind['id'] ? 'selected' : '' ?>><?= e($ind['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field">
                                <label class="label" for="cu-status">Status</label>
                                <select class="select" id="cu-status" name="status">
                                    <?php foreach (['lead' => 'Lead', 'active' => 'Active customer', 'inactive' => 'Inactive'] as $v => $l): ?>
                                    <option value="<?= e($v) ?>" <?= $val('status') === $v ? 'selected' : '' ?>><?= e($l) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="field">
                            <label class="label" for="cu-notes">Internal notes</label>
                            <textarea class="textarea" id="cu-notes" name="notes" maxlength="5000"><?= e($val('notes')) ?></textarea>
                        </div>
                    </div>
                </div>
                <div class="panel__foot">
                    <button class="btn btn--primary btn--sm" type="submit"><?= icon('check') ?>Save</button>
                </div>
            </div>
        </form>

        <?php if ($purchases): ?>
        <div class="panel">
            <div class="panel__head"><span class="panel__title">Purchases</span></div>
            <div class="table-wrap" style="border:0;border-radius:0;background:none">
                <table class="data-table" style="min-width:520px">
                    <thead><tr><th>Reference</th><th>Package</th><th class="num">Total</th><th>Payment</th><th class="num">Expires</th></tr></thead>
                    <tbody>
                        <?php foreach ($purchases as $p): ?>
                        <tr>
                            <td><a class="mono-sm" href="<?= e(url('/admin/purchases/' . (int) $p['id'])) ?>"><?= e($p['reference']) ?></a></td>
                            <td><?= e($p['package_name']) ?></td>
                            <td class="num"><?= e(money($p['total_amount'])) ?></td>
                            <td><span class="status-dot status-dot--<?= $p['payment_status'] === 'paid' ? 'live' : 'warn' ?>"><?= e(ucfirst((string) $p['payment_status'])) ?></span></td>
                            <td class="num"><span class="hint"><?= $p['expires_at'] ? e(format_date($p['expires_at'])) : '—' ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

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
                    <div class="kv"><span class="kv__label">Purchases</span><span class="kv__value"><?= count($purchases) ?></span></div>
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
