<?php
/** @var array $row */
use Techbiss\Core\Auth;
$val   = static fn (string $k, $d = '') => old($k, $row[$k] ?? $d);
$types = ['upgrade' => 'Upgrade', 'update' => 'Update', 'maintenance' => 'Maintenance', 'support' => 'Support', 'other' => 'Something else'];
?>
<div class="page-header">
    <div>
        <h1><?= e($row['reference']) ?></h1>
        <p><?= e($types[$row['request_type']] ?? ucfirst((string) $row['request_type'])) ?> request from <?= e($row['customer_name']) ?> · <?= e(format_date($row['created_at'], 'j F Y, H:i')) ?></p>
    </div>
    <div class="page-header__actions">
        <a class="btn btn--ghost btn--sm" href="<?= e(url('/admin/client-requests')) ?>"><?= icon('arrow-left') ?>Back</a>
    </div>
</div>

<div class="form-cols">
    <div>
        <div class="panel">
            <div class="panel__head"><span class="panel__title">What they asked for</span></div>
            <div class="panel__body">
                <?php if (!empty($row['project_name'])): ?>
                <div class="kv-list">
                    <div class="kv"><span class="kv__label">Project</span><span class="kv__value"><?= e($row['project_name']) ?></span></div>
                </div>
                <?php endif; ?>
                <p class="mt-4" style="white-space:pre-wrap;line-height:1.75;color:var(--text-soft)"><?= e($row['message']) ?></p>
            </div>
        </div>

        <?php if (Auth::can('client_requests.manage')): ?>
        <form method="post" action="<?= e(url('/admin/client-requests/' . (int) $row['id'])) ?>">
            <?= csrf_field() ?>
            <div class="panel">
                <div class="panel__head"><span class="panel__title">Status</span></div>
                <div class="panel__body">
                    <div class="form-section">
                        <div class="field">
                            <label class="label" for="cr-status">Status</label>
                            <select class="select" id="cr-status" name="status">
                                <?php foreach (['new' => 'New', 'in_progress' => 'In progress', 'resolved' => 'Resolved'] as $v => $l): ?>
                                <option value="<?= e($v) ?>" <?= $val('status') === $v ? 'selected' : '' ?>><?= e($l) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label class="label" for="cr-notes">Internal notes</label>
                            <textarea class="textarea" id="cr-notes" name="admin_notes" maxlength="5000"><?= e($val('admin_notes')) ?></textarea>
                        </div>
                    </div>
                </div>
                <div class="panel__foot">
                    <button class="btn btn--primary btn--sm" type="submit"><?= icon('check') ?>Save</button>
                </div>
            </div>
        </form>

        <form method="post" action="<?= e(url('/admin/client-requests/' . (int) $row['id'] . '/reply')) ?>">
            <?= csrf_field() ?>
            <div class="panel">
                <div class="panel__head"><span class="panel__title">Reply to <?= e($row['customer_name']) ?></span></div>
                <div class="panel__body">
                    <div class="form-section">
                        <div class="field">
                            <label class="label" for="cr-subject">Subject</label>
                            <input class="input" id="cr-subject" type="text" name="subject" maxlength="180"
                                   value="<?= e('Re: your request ' . $row['reference']) ?>">
                        </div>
                        <div class="field">
                            <label class="label" for="cr-message">Message</label>
                            <textarea class="textarea" id="cr-message" name="message" rows="6" maxlength="5000"></textarea>
                        </div>
                    </div>
                </div>
                <div class="panel__foot">
                    <button class="btn btn--primary btn--sm" type="submit"><?= icon('send') ?>Send email</button>
                </div>
            </div>
        </form>
        <?php endif; ?>
    </div>

    <aside class="form-aside">
        <div class="panel">
            <div class="panel__head"><span class="panel__title">Client</span></div>
            <div class="panel__body">
                <div class="kv-list">
                    <div class="kv"><span class="kv__label">Name</span><span class="kv__value"><?= e($row['customer_name']) ?></span></div>
                    <div class="kv"><span class="kv__label">Business</span><span class="kv__value"><?= e($row['customer_business'] ?: '—') ?></span></div>
                    <div class="kv"><span class="kv__label">Email</span><span class="kv__value"><a href="mailto:<?= e($row['customer_email']) ?>"><?= e($row['customer_email']) ?></a></span></div>
                    <div class="kv"><span class="kv__label">Phone</span><span class="kv__value"><?= e($row['customer_phone'] ?: '—') ?></span></div>
                    <?php if (!empty($row['project_url'])): ?>
                    <div class="kv"><span class="kv__label">Live site</span>
                        <span class="kv__value"><a href="<?= e($row['project_url']) ?>" target="_blank" rel="noopener noreferrer"><?= e($row['project_url']) ?></a></span></div>
                    <?php endif; ?>
                    <div class="kv"><span class="kv__label">IP address</span><span class="kv__value"><?= e($row['ip_address'] ?: '—') ?></span></div>
                </div>
            </div>
        </div>

        <?php if (Auth::can('client_requests.manage')): ?>
        <form method="post" action="<?= e(url('/admin/client-requests/' . (int) $row['id'] . '/delete')) ?>" data-confirm="Delete this request permanently?">
            <?= csrf_field() ?>
            <button class="btn btn--danger btn--sm btn--block" type="submit"><?= icon('trash') ?>Delete request</button>
        </form>
        <?php endif; ?>
    </aside>
</div>
