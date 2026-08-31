<?php
/** @var array $row */
use Techbiss\Repo\PurchaseRepo;
$val = static fn (string $k, $d = '') => old($k, $row[$k] ?? $d);
?>
<div class="page-header">
    <div>
        <h1><?= e($row['reference']) ?></h1>
        <p><?= e($row['package_name']) ?> for <?= e($row['customer_name']) ?> · requested <?= e(format_date($row['purchased_at'], 'j F Y')) ?></p>
    </div>
    <div class="page-header__actions">
        <a class="btn btn--ghost btn--sm" href="<?= e(url('/admin/purchases')) ?>"><?= icon('arrow-left') ?>Back</a>
        <a class="btn btn--quiet btn--sm" href="<?= e(url('/admin/customers/' . (int) $row['customer_id'])) ?>"><?= icon('user') ?>Customer</a>
    </div>
</div>

<div class="form-cols">
    <div>
        <div class="panel">
            <div class="panel__head"><span class="panel__title">Pricing breakdown</span></div>
            <div class="panel__body">
                <div class="stack stack-2">
                    <div class="summary-line"><span>Regular price</span><span class="num"><?= e(money($row['regular_price'])) ?></span></div>
                    <div class="summary-line"><span>Prepaid price</span><span class="num"><?= e(money($row['prepaid_price'])) ?></span></div>
                    <?php if ((float) $row['discount_amount'] > 0): ?>
                    <div class="summary-line summary-line--save"><span>Prepaid saving</span><span class="num">−<?= e(money($row['discount_amount'])) ?></span></div>
                    <?php endif; ?>
                    <?php foreach ($row['addons'] as $addon): ?>
                    <div class="summary-line summary-line--muted"><span>+ <?= e($addon['name']) ?></span><span class="num"><?= e(money($addon['price'])) ?></span></div>
                    <?php endforeach; ?>
                    <?php if ((float) $row['addons_total'] > 0): ?>
                    <div class="summary-line"><span>Add-ons subtotal</span><span class="num"><?= e(money($row['addons_total'])) ?></span></div>
                    <?php endif; ?>
                    <div class="summary-line summary-line--total"><span>Total</span><span class="num"><?= e(money($row['total_amount'])) ?> <?= e($row['currency']) ?></span></div>
                </div>
            </div>
        </div>

        <?php if (!empty($row['business_details']) || !empty($row['requirements'])): ?>
        <div class="panel">
            <div class="panel__head"><span class="panel__title">What the customer told us</span></div>
            <div class="panel__body">
                <div class="kv-list">
                    <?php if (!empty($row['business_details'])): ?>
                    <div class="kv"><span class="kv__label">About the business</span>
                        <span class="kv__value" style="white-space:pre-wrap;font-weight:400"><?= e($row['business_details']) ?></span></div>
                    <?php endif; ?>
                    <?php if (!empty($row['requirements'])): ?>
                    <div class="kv"><span class="kv__label">Requirements</span>
                        <span class="kv__value" style="white-space:pre-wrap;font-weight:400"><?= e($row['requirements']) ?></span></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <form method="post" action="<?= e(url('/admin/purchases/' . (int) $row['id'])) ?>">
            <?= csrf_field() ?>
            <div class="panel">
                <div class="panel__head">
                    <div>
                        <span class="panel__title">Status &amp; dates</span>
                        <div class="panel__sub">Marking a purchase paid sets the start and expiry dates automatically if they are blank.</div>
                    </div>
                </div>
                <div class="panel__body">
                    <div class="form-section">
                        <div class="field--row">
                            <div class="field">
                                <label class="label" for="s-pay">Payment status</label>
                                <select class="select" id="s-pay" name="payment_status">
                                    <?php foreach (PurchaseRepo::PAYMENT_STATUSES as $s): ?>
                                    <option value="<?= e($s) ?>" <?= $val('payment_status') === $s ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field">
                                <label class="label" for="s-pkg">Package status</label>
                                <select class="select" id="s-pkg" name="package_status">
                                    <?php foreach (PurchaseRepo::PACKAGE_STATUSES as $s): ?>
                                    <option value="<?= e($s) ?>" <?= $val('package_status') === $s ? 'selected' : '' ?>>
                                        <?= e($s === 'expiring' ? 'Expiring soon' : ucfirst($s)) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field">
                                <label class="label" for="s-ren">Renewal</label>
                                <select class="select" id="s-ren" name="renewal_status">
                                    <?php foreach (PurchaseRepo::RENEWAL_STATUSES as $s): ?>
                                    <option value="<?= e($s) ?>" <?= $val('renewal_status') === $s ? 'selected' : '' ?>>
                                        <?= e(ucfirst(str_replace('_', ' ', $s))) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="field--row">
                            <div class="field">
                                <label class="label" for="s-start">Start date</label>
                                <input class="input" id="s-start" type="date" name="starts_at" value="<?= e(substr((string) $val('starts_at'), 0, 10)) ?>">
                            </div>
                            <div class="field">
                                <label class="label" for="s-end">Expiry date</label>
                                <input class="input" id="s-end" type="date" name="expires_at" value="<?= e(substr((string) $val('expires_at'), 0, 10)) ?>">
                            </div>
                            <div class="field">
                                <label class="label" for="s-ref">Payment reference</label>
                                <input class="input" id="s-ref" type="text" name="payment_reference"
                                       value="<?= e($val('payment_reference')) ?>" maxlength="120" placeholder="Invoice or transaction ID">
                            </div>
                        </div>

                        <div class="field">
                            <label class="label" for="s-notes">Internal notes</label>
                            <textarea class="textarea" id="s-notes" name="admin_notes" maxlength="5000"><?= e($val('admin_notes')) ?></textarea>
                        </div>
                    </div>
                </div>
                <div class="panel__foot">
                    <button class="btn btn--primary btn--sm" type="submit"><?= icon('check') ?>Save</button>
                </div>
            </div>
        </form>
    </div>

    <aside class="form-aside">
        <div class="panel">
            <div class="panel__head"><span class="panel__title">Customer</span></div>
            <div class="panel__body">
                <div class="kv-list">
                    <div class="kv"><span class="kv__label">Name</span><span class="kv__value"><?= e($row['customer_name']) ?></span></div>
                    <div class="kv"><span class="kv__label">Business</span><span class="kv__value"><?= e($row['business_name'] ?: '—') ?></span></div>
                    <div class="kv"><span class="kv__label">Email</span>
                        <span class="kv__value"><a href="mailto:<?= e($row['customer_email']) ?>"><?= e($row['customer_email']) ?></a></span></div>
                    <div class="kv"><span class="kv__label">Phone</span><span class="kv__value"><?= e($row['customer_phone'] ?: '—') ?></span></div>
                    <div class="kv"><span class="kv__label">Country</span><span class="kv__value"><?= e($row['customer_country'] ?: '—') ?></span></div>
                    <div class="kv"><span class="kv__label">Payment method</span><span class="kv__value"><?= e(ucwords(str_replace('_', ' ', (string) $row['payment_method']))) ?></span></div>
                    <div class="kv"><span class="kv__label">Term</span><span class="kv__value"><?= (int) $row['duration_months'] ?> months</span></div>
                </div>
            </div>
        </div>

        <form method="post" action="<?= e(url('/admin/purchases/' . (int) $row['id'] . '/extend')) ?>">
            <?= csrf_field() ?>
            <div class="panel">
                <div class="panel__head"><span class="panel__title">Extend validity</span></div>
                <div class="panel__body">
                    <div class="field">
                        <label class="label" for="s-months">Extend by</label>
                        <select class="select" id="s-months" name="months">
                            <?php foreach ([1, 3, 6, 12, 24] as $m): ?>
                            <option value="<?= $m ?>" <?= $m === 12 ? 'selected' : '' ?>><?= $m ?> month<?= $m === 1 ? '' : 's' ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="hint">Pushes the expiry date out and marks the package active again.</span>
                    </div>
                </div>
                <div class="panel__foot">
                    <button class="btn btn--ghost btn--sm btn--block" type="submit"><?= icon('calendar') ?>Extend</button>
                </div>
            </div>
        </form>

        <form method="post" action="<?= e(url('/admin/purchases/' . (int) $row['id'] . '/delete')) ?>"
              data-confirm="Delete this purchase record permanently?">
            <?= csrf_field() ?>
            <button class="btn btn--danger btn--sm btn--block" type="submit"><?= icon('trash') ?>Delete record</button>
        </form>
    </aside>
</div>
