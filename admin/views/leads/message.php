<?php
/** @var array $row */
use Techbiss\Core\Auth;
$val = static fn (string $k, $d = '') => old($k, $row[$k] ?? $d);
?>
<div class="page-header">
    <div>
        <h1><?= e($row['subject'] ?: 'Message from ' . $row['name']) ?></h1>
        <p>From <?= e($row['name']) ?> · <?= e(format_date($row['created_at'], 'j F Y, H:i')) ?></p>
    </div>
    <div class="page-header__actions">
        <a class="btn btn--ghost btn--sm" href="<?= e(url('/admin/messages')) ?>"><?= icon('arrow-left') ?>Back</a>
        <a class="btn btn--primary btn--sm" href="mailto:<?= e($row['email']) ?>?subject=<?= rawurlencode('Re: ' . ($row['subject'] ?: 'Your enquiry')) ?>">
            <?= icon('send') ?>Reply
        </a>
    </div>
</div>

<div class="form-cols">
    <div>
        <div class="panel">
            <div class="panel__head"><span class="panel__title">Message</span></div>
            <div class="panel__body">
                <p style="white-space:pre-wrap;line-height:1.75;color:var(--text-soft)"><?= e($row['message']) ?></p>
            </div>
        </div>

        <?php if (Auth::can('leads.manage')): ?>
        <form method="post" action="<?= e(url('/admin/messages/' . (int) $row['id'])) ?>">
            <?= csrf_field() ?>
            <div class="panel">
                <div class="panel__head"><span class="panel__title">Handling</span></div>
                <div class="panel__body">
                    <div class="form-section">
                        <div class="field">
                            <label class="label" for="m-status">Status</label>
                            <select class="select" id="m-status" name="status">
                                <?php foreach (['new' => 'New', 'read' => 'Read', 'replied' => 'Replied', 'archived' => 'Archived', 'spam' => 'Spam'] as $v => $l): ?>
                                <option value="<?= e($v) ?>" <?= $val('status') === $v ? 'selected' : '' ?>><?= e($l) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label class="label" for="m-notes">Internal notes</label>
                            <textarea class="textarea" id="m-notes" name="admin_notes" maxlength="5000"
                                      placeholder="What was agreed, what to follow up on."><?= e($val('admin_notes')) ?></textarea>
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
            <div class="panel__head"><span class="panel__title">Sender</span></div>
            <div class="panel__body">
                <div class="kv-list">
                    <div class="kv"><span class="kv__label">Name</span><span class="kv__value"><?= e($row['name']) ?></span></div>
                    <div class="kv"><span class="kv__label">Email</span><span class="kv__value"><a href="mailto:<?= e($row['email']) ?>"><?= e($row['email']) ?></a></span></div>
                    <div class="kv"><span class="kv__label">Company</span><span class="kv__value"><?= e($row['company'] ?: '—') ?></span></div>
                    <div class="kv"><span class="kv__label">Phone</span><span class="kv__value"><?= e($row['phone'] ?: '—') ?></span></div>
                    <div class="kv"><span class="kv__label">Country</span><span class="kv__value"><?= e($row['country'] ?: '—') ?></span></div>
                    <div class="kv"><span class="kv__label">IP address</span><span class="kv__value mono-sm"><?= e($row['ip_address'] ?: '—') ?></span></div>
                </div>
            </div>
        </div>

        <?php if (Auth::can('leads.manage')): ?>
        <form method="post" action="<?= e(url('/admin/messages/' . (int) $row['id'] . '/delete')) ?>" data-confirm="Delete this message permanently?">
            <?= csrf_field() ?>
            <button class="btn btn--danger btn--sm btn--block" type="submit"><?= icon('trash') ?>Delete message</button>
        </form>
        <?php endif; ?>
    </aside>
</div>
