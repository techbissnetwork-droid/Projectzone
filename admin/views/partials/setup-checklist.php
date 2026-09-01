<?php
/**
 * A dismissible reminder of the important settings still left empty.
 *
 * Never blocks anything — it is a nudge, not a gate. Dismissing it is
 * remembered for the current browser session only (sessionStorage), so it
 * quietly comes back next time there's still something on the list rather
 * than being silenced for good after one click.
 *
 * @var array $missingSetup
 */
if ($missingSetup === []) {
    return;
}
?>
<div class="notice notice--accent setup-checklist" data-setup-checklist hidden>
    <?= icon('settings') ?>
    <div class="setup-checklist__body">
        <strong>A few things are still empty</strong>
        <p class="mt-1" style="margin:.35rem 0 .65rem">
            Visitors won't see these until they're filled in — worth a couple of minutes when you get a chance.
        </p>
        <div class="chip-row">
            <?php foreach ($missingSetup as $item): ?>
            <a class="pill" href="<?= e(url('/admin/settings?group=' . $item['group'])) ?>"><?= e($item['label']) ?></a>
            <?php endforeach; ?>
        </div>
    </div>
    <button type="button" class="icon-btn setup-checklist__dismiss" data-setup-checklist-dismiss aria-label="Dismiss this reminder">
        <?= icon('x') ?>
    </button>
</div>
