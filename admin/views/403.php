<div class="empty-state" style="margin-top:2rem">
    <span class="empty-state__icon"><?= icon('lock') ?></span>
    <h1 style="font-size:1.3rem">You do not have access to this area</h1>
    <p>
        Your role does not include the <code class="mono"><?= e($permission ?? '') ?></code> permission.
        Ask a super admin if you need it.
    </p>
    <a class="btn btn--ghost mt-4" href="<?= e(url('/admin')) ?>">Back to the dashboard</a>
</div>
