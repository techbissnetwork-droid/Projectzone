<?php
/**
 * Add a client by hand.
 *
 * Customers used to arrive only from an enquiry on the website, which is no
 * use when the work came in over WhatsApp or in person — and the job book
 * needs someone to attach a project to.
 *
 * @var array $industries
 */
$val = static fn (string $k, $d = '') => old($k, $d);
?>
<div class="page-header">
    <div>
        <h1>New customer</h1>
        <p>Someone you are working with. Their email and phone are what the job book contacts them on later.</p>
    </div>
    <div class="page-header__actions">
        <a class="btn btn--ghost btn--sm" href="<?= e(url('/admin/customers')) ?>"><?= icon('arrow-left') ?>Back</a>
    </div>
</div>

<form method="post" action="<?= e(url('/admin/customers')) ?>" data-dirty-guard>
    <?= csrf_field() ?>
    <div class="panel">
        <div class="panel__head"><span class="panel__title">Details</span></div>
        <div class="panel__body">
            <?= $view->partial('customers/_fields', ['val' => $val, 'industries' => $industries]) ?>
        </div>
        <div class="panel__foot">
            <button class="btn btn--primary btn--sm" type="submit"><?= icon('check') ?>Add customer</button>
        </div>
    </div>
</form>
