<?php
/** @var array $row */
use Techbiss\Core\Auth;
$val = static fn (string $k, $d = '') => old($k, $row[$k] ?? $d);
$needs = array_filter(array_map('trim', explode(',', (string) $row['services_needed'])));
?>
<div class="page-header">
    <div>
        <h1><?= e($row['reference']) ?></h1>
        <p><?= e(ucfirst((string) $row['source'])) ?> request from <?= e($row['name']) ?> · <?= e(format_date($row['created_at'], 'j F Y, H:i')) ?></p>
    </div>
    <div class="page-header__actions">
        <a class="btn btn--ghost btn--sm" href="<?= e(url('/admin/quotes')) ?>"><?= icon('arrow-left') ?>Back</a>
        <a class="btn btn--primary btn--sm" href="mailto:<?= e($row['email']) ?>?subject=<?= rawurlencode('Your TECHBISS request ' . $row['reference']) ?>">
            <?= icon('send') ?>Reply
        </a>
    </div>
</div>

<div class="form-cols">
    <div>
        <div class="panel">
            <div class="panel__head"><span class="panel__title">What they asked for</span></div>
            <div class="panel__body">
                <div class="kv-list kv-list--2">
                    <div class="kv"><span class="kv__label">Business stage</span><span class="kv__value"><?= e($row['business_stage'] ?: '—') ?></span></div>
                    <div class="kv"><span class="kv__label">Industry</span><span class="kv__value"><?= e($row['industry_name'] ?: '—') ?></span></div>
                    <div class="kv"><span class="kv__label">Budget</span><span class="kv__value"><?= e($row['budget_range'] ?: '—') ?></span></div>
                    <div class="kv"><span class="kv__label">Timeline</span><span class="kv__value"><?= e($row['timeline'] ?: '—') ?></span></div>
                    <?php if (!empty($row['package_name'])): ?>
                    <div class="kv"><span class="kv__label">Package of interest</span><span class="kv__value"><?= e($row['package_name']) ?></span></div>
                    <?php endif; ?>
                    <?php if (!empty($row['website'])): ?>
                    <div class="kv"><span class="kv__label">Existing website</span>
                        <span class="kv__value"><a href="<?= e($row['website']) ?>" target="_blank" rel="noopener nofollow"><?= e($row['website']) ?></a></span></div>
                    <?php endif; ?>
                </div>

                <?php if ($needs): ?>
                <div class="mt-5">
                    <span class="kv__label">Services requested</span>
                    <div class="chip-row mt-2">
                        <?php foreach ($needs as $need): ?>
                        <span class="pill"><?= e(ucwords(str_replace('-', ' ', $need))) ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($row['project_details'])): ?>
                <div class="mt-5">
                    <span class="kv__label">Project details</span>
                    <p style="white-space:pre-wrap;line-height:1.75;color:var(--text-soft);margin-top:.5rem"><?= e($row['project_details']) ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if (Auth::can('leads.manage')): ?>
        <form method="post" action="<?= e(url('/admin/quotes/' . (int) $row['id'])) ?>">
            <?= csrf_field() ?>
            <div class="panel">
                <div class="panel__head"><span class="panel__title">Pipeline</span></div>
                <div class="panel__body">
                    <div class="form-section">
                        <div class="field--row">
                            <div class="field">
                                <label class="label" for="q-status">Status</label>
                                <select class="select" id="q-status" name="status">
                                    <?php foreach (['new' => 'New', 'reviewing' => 'Reviewing', 'quoted' => 'Quoted', 'won' => 'Won', 'lost' => 'Lost', 'archived' => 'Archived'] as $v => $l): ?>
                                    <option value="<?= e($v) ?>" <?= $val('status') === $v ? 'selected' : '' ?>><?= e($l) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field">
                                <label class="label" for="q-priority">Priority</label>
                                <select class="select" id="q-priority" name="priority">
                                    <?php foreach (['low' => 'Low', 'normal' => 'Normal', 'high' => 'High'] as $v => $l): ?>
                                    <option value="<?= e($v) ?>" <?= $val('priority') === $v ? 'selected' : '' ?>><?= e($l) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field">
                                <label class="label" for="q-value">Estimated value</label>
                                <input class="input" id="q-value" type="number" name="estimated_value" step="0.01" min="0"
                                       value="<?= e((string) $val('estimated_value', '')) ?>">
                            </div>
                        </div>
                        <div class="field">
                            <label class="label" for="q-notes">Internal notes</label>
                            <textarea class="textarea" id="q-notes" name="admin_notes" maxlength="5000"><?= e($val('admin_notes')) ?></textarea>
                        </div>
                    </div>
                </div>
                <div class="panel__foot">
                    <button class="btn btn--primary btn--sm" type="submit"><?= icon('check') ?>Save</button>
                </div>
            </div>
        </form>
        <?php endif; ?>
    </div>

    <aside class="form-aside">
        <div class="panel">
            <div class="panel__head"><span class="panel__title">Contact</span></div>
            <div class="panel__body">
                <div class="kv-list">
                    <div class="kv"><span class="kv__label">Name</span><span class="kv__value"><?= e($row['name']) ?></span></div>
                    <div class="kv"><span class="kv__label">Business</span><span class="kv__value"><?= e($row['business_name'] ?: '—') ?></span></div>
                    <div class="kv"><span class="kv__label">Email</span><span class="kv__value"><a href="mailto:<?= e($row['email']) ?>"><?= e($row['email']) ?></a></span></div>
                    <div class="kv"><span class="kv__label">Phone</span><span class="kv__value"><?= e($row['phone'] ?: '—') ?></span></div>
                    <div class="kv"><span class="kv__label">Country</span><span class="kv__value"><?= e($row['country'] ?: '—') ?></span></div>
                    <div class="kv"><span class="kv__label">IP address</span><span class="kv__value mono-sm"><?= e($row['ip_address'] ?: '—') ?></span></div>
                </div>
            </div>
        </div>

        <?php if (Auth::can('leads.manage')): ?>
        <form method="post" action="<?= e(url('/admin/quotes/' . (int) $row['id'] . '/delete')) ?>" data-confirm="Delete this request permanently?">
            <?= csrf_field() ?>
            <button class="btn btn--danger btn--sm btn--block" type="submit"><?= icon('trash') ?>Delete request</button>
        </form>
        <?php endif; ?>
    </aside>
</div>
