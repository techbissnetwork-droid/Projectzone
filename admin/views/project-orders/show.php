<?php
/** @var array $row */
use Techbiss\Repo\ProjectOrderRepo;

$val      = static fn (string $k, $d = '') => old($k, $row[$k] ?? $d);
$labels   = ProjectOrderRepo::orderStatusLabels();
$channels = ProjectOrderRepo::contactLabels();
$channel  = (string) $row['preferred_contact'];

$whatsapp = preg_replace('/[^0-9]/', '', (string) $row['customer_phone']);
$waLink   = ($whatsapp !== '' && strlen($whatsapp) >= 8)
    ? 'https://wa.me/' . $whatsapp . '?text=' . rawurlencode('Hi ' . $row['customer_name'] . ', about your enquiry ' . $row['reference'] . ' for ' . $row['project_name'] . ':')
    : '';
$mailLink = 'mailto:' . $row['customer_email']
    . '?subject=' . rawurlencode('Your enquiry ' . $row['reference'] . ' — ' . $row['project_name']);
?>
<div class="page-header">
    <div>
        <h1><?= e($row['reference']) ?></h1>
        <p><?= e($row['project_name']) ?> for <?= e($row['customer_name']) ?> · asked <?= e(format_date($row['ordered_at'], 'j F Y')) ?></p>
    </div>
    <div class="page-header__actions">
        <a class="btn btn--ghost btn--sm" href="<?= e(url('/admin/project-orders')) ?>"><?= icon('arrow-left') ?>Back</a>
        <a class="btn btn--quiet btn--sm" href="<?= e(url('/admin/customers/' . (int) $row['customer_id'])) ?>"><?= icon('user') ?>Customer</a>
    </div>
</div>

<div class="form-cols">
    <div>
        <div class="panel">
            <div class="panel__head">
                <div>
                    <span class="panel__title">Start the conversation</span>
                    <div class="panel__sub">They asked to be reached on <strong><?= e($channels[$channel] ?? 'Email') ?></strong>.</div>
                </div>
            </div>
            <div class="panel__body">
                <div class="row row--tight">
                    <?php if ($waLink !== ''): ?>
                    <a class="btn btn--primary btn--sm" target="_blank" rel="noopener noreferrer" href="<?= e($waLink) ?>">
                        <?= icon('whatsapp') ?>WhatsApp
                    </a>
                    <?php endif; ?>
                    <a class="btn btn--ghost btn--sm" href="<?= e($mailLink) ?>"><?= icon('mail') ?>Email</a>
                    <?php if (!empty($row['customer_phone'])): ?>
                    <a class="btn btn--quiet btn--sm" href="tel:<?= e(preg_replace('/[^0-9+]/', '', (string) $row['customer_phone'])) ?>">
                        <?= icon('phone') ?>Call
                    </a>
                    <?php endif; ?>
                </div>
                <?php if ($channel === 'whatsapp' && $waLink === ''): ?>
                <p class="hint mt-4">
                    They chose WhatsApp but left no usable phone number, so there is no WhatsApp link to open. Email them instead.
                </p>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($row['requirements']) || !empty($row['domain_name']) || !empty($row['business_details'])): ?>
        <div class="panel">
            <div class="panel__head"><span class="panel__title">What they told us</span></div>
            <div class="panel__body">
                <div class="kv-list">
                    <?php if (!empty($row['business_details'])): ?>
                    <div class="kv"><span class="kv__label">Business</span>
                        <span class="kv__value" style="white-space:pre-wrap;font-weight:400"><?= e($row['business_details']) ?></span></div>
                    <?php endif; ?>
                    <?php if (!empty($row['domain_name'])): ?>
                    <div class="kv"><span class="kv__label">Domain</span>
                        <span class="kv__value mono-sm"><?= e($row['domain_name']) ?></span></div>
                    <?php endif; ?>
                    <?php if (!empty($row['requirements'])): ?>
                    <div class="kv"><span class="kv__label">What they need</span>
                        <span class="kv__value" style="white-space:pre-wrap;font-weight:400"><?= e($row['requirements']) ?></span></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <form method="post" action="<?= e(url('/admin/project-orders/' . (int) $row['id'])) ?>">
            <?= csrf_field() ?>
            <div class="panel">
                <div class="panel__head">
                    <div>
                        <span class="panel__title">Progress &amp; the agreed price</span>
                        <div class="panel__sub">Leave the amount blank until you have actually agreed one. Marking it delivered stamps today's date.</div>
                    </div>
                </div>
                <div class="panel__body">
                    <div class="form-section">
                        <div class="field--row">
                            <div class="field">
                                <label class="label" for="o-status">Enquiry status</label>
                                <select class="select" id="o-status" name="order_status">
                                    <?php foreach ($labels as $key => $label): ?>
                                    <option value="<?= e($key) ?>" <?= $val('order_status') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field">
                                <label class="label" for="o-pay">Payment status</label>
                                <select class="select" id="o-pay" name="payment_status">
                                    <?php foreach (ProjectOrderRepo::PAYMENT_STATUSES as $s): ?>
                                    <option value="<?= e($s) ?>" <?= $val('payment_status') === $s ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="field--row">
                            <div class="field">
                                <label class="label" for="o-amount">Agreed price</label>
                                <input class="input" id="o-amount" type="number" step="0.01" min="0" name="quoted_amount"
                                       value="<?= $row['quoted_amount'] !== null ? e((string) $val('quoted_amount')) : '' ?>"
                                       placeholder="Not agreed yet">
                                <span class="hint">Never shown to the customer on the website.</span>
                            </div>
                            <div class="field">
                                <label class="label" for="o-currency">Currency</label>
                                <input class="input" id="o-currency" type="text" name="currency"
                                       value="<?= e($val('currency', 'USD')) ?>" maxlength="6">
                            </div>
                            <div class="field">
                                <label class="label" for="o-ref">Payment reference</label>
                                <input class="input" id="o-ref" type="text" name="payment_reference"
                                       value="<?= e($val('payment_reference')) ?>" maxlength="120" placeholder="Invoice or transaction ID">
                            </div>
                        </div>

                        <div class="field">
                            <label class="label" for="o-domain">Domain</label>
                            <input class="input" id="o-domain" type="text" name="domain_name"
                                   value="<?= e($val('domain_name')) ?>" maxlength="190" placeholder="Where it will be set up">
                        </div>

                        <div class="field">
                            <label class="label" for="o-notes">Internal notes</label>
                            <textarea class="textarea" id="o-notes" name="admin_notes" maxlength="5000"><?= e($val('admin_notes')) ?></textarea>
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
                    <div class="kv"><span class="kv__label">Prefers</span><span class="kv__value"><?= e($channels[$channel] ?? 'Email') ?></span></div>
                </div>
            </div>
        </div>

        <div class="panel">
            <div class="panel__head"><span class="panel__title">Project</span></div>
            <div class="panel__body">
                <div class="kv-list">
                    <div class="kv"><span class="kv__label">Asked about</span><span class="kv__value"><?= e($row['project_name']) ?></span></div>
                    <?php if (!empty($row['delivered_at'])): ?>
                    <div class="kv"><span class="kv__label">Delivered</span><span class="kv__value"><?= e(format_date($row['delivered_at'], 'j F Y')) ?></span></div>
                    <?php endif; ?>
                </div>
                <?php if (!empty($row['project_slug'])): ?>
                <a class="btn btn--ghost btn--sm btn--block mt-4" target="_blank" rel="noopener"
                   href="<?= e(url('/premade-projects/' . $row['project_slug'])) ?>"><?= icon('external') ?>View the project page</a>
                <?php else: ?>
                <p class="hint mt-4">The project this refers to has since been deleted. The name above is what they asked about.</p>
                <?php endif; ?>
            </div>
        </div>

        <form method="post" action="<?= e(url('/admin/project-orders/' . (int) $row['id'] . '/delete')) ?>"
              data-confirm="Delete this enquiry permanently?">
            <?= csrf_field() ?>
            <button class="btn btn--danger btn--sm btn--block" type="submit"><?= icon('trash') ?>Delete record</button>
        </form>
    </aside>
</div>
