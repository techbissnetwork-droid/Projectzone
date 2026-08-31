<?php
/** @var array $packages @var array $addons @var array $compareRows */
$showSave  = setting_bool('show_prepaid_savings', true);
$showPrice = setting_bool('public_pricing', false);
// Only advertise the prepaid model when prices are published at all, and at
// least one package genuinely has one.
$anyPrepaid = false;
$bestSaving = 0.0;
foreach ($packages as $p) {
    if ($p['pricing']['has_discount']) {
        $anyPrepaid = true;
        $bestSaving = max($bestSaving, (float) $p['pricing']['saving']);
    }
}
$example = null;
foreach ($packages as $p) {
    if ($p['pricing']['has_discount']) { $example = $p; break; }
}
if (!$showPrice) {
    $anyPrepaid = false;
    $example    = null;
}
?>
<?= $view->partial('partials/page-head', [
    'eyebrow' => 'Packages',
    'heading' => $showPrice
        ? ($anyPrepaid ? 'Pay upfront. Save more. Build better.' : 'Complete digital setups, clearly priced.')
        : 'Pick what you need. We price it with you.',
    'lead'    => $showPrice
        ? ($anyPrepaid
            ? 'Complete setups with published prices. Where a prepaid discount applies, you see the exact saving.'
            : 'Complete setups with everything included listed before you commit.')
        : 'Start from a package, tick what you actually want, add anything missing, and send it over. We reply with a figure.',
    'center'  => true,
]) ?>

<?php if ($anyPrepaid && $example !== null && $showSave): ?>
<section class="section--flush-top" style="padding-bottom:var(--s-8)">
    <div class="container container--narrow">
        <div class="prepaid-band" data-reveal>
            <div class="prepaid-band__cell">
                <div class="prepaid-band__label">Regular price</div>
                <div class="prepaid-band__value prepaid-band__value--strike"><?= e(money($example['pricing']['regular'])) ?></div>
                <p class="prepaid-band__text">Paid in stages across the project.</p>
            </div>
            <div class="prepaid-band__cell">
                <div class="prepaid-band__label">Prepaid price</div>
                <div class="prepaid-band__value"><?= e(money($example['pricing']['payable'])) ?></div>
                <p class="prepaid-band__text">Settled upfront when the project starts.</p>
            </div>
            <div class="prepaid-band__cell">
                <div class="prepaid-band__label">You save</div>
                <div class="prepaid-band__value prepaid-band__value--save"><?= e(money($example['pricing']['saving'])) ?></div>
                <p class="prepaid-band__text"><?= (int) $example['pricing']['percent'] ?>% off the <?= e($example['name']) ?> package.</p>
            </div>
        </div>
        <p class="hint text-center mt-4">
            Example based on the <?= e($example['name']) ?> package. Every package below shows its own pricing —
            packages without a prepaid price simply do not have one.
        </p>
    </div>
</section>
<?php endif; ?>

<section class="section<?= ($anyPrepaid && $example !== null) ? '' : ' section--flush-top' ?>">
    <div class="container">
        <h2 class="sr-only">Packages</h2>
        <?php if (!$packages): ?>
            <div class="empty-state">
                <span class="empty-state__icon"><?= icon('package') ?></span>
                <h3>Packages are being updated</h3>
                <p>Our packaged setups are being revised. Tell us what you need and we will quote it directly.</p>
                <a class="btn btn--primary mt-4" href="<?= e(url('/quote')) ?>">Request a quote</a>
            </div>
        <?php else: ?>
            <div class="slider" data-slider>
            <div class="slider__track slider__track--packages" data-reveal-stagger>
                <?php foreach ($packages as $package): ?>
                    <?= $view->partial('partials/package-card', ['package' => $package, 'compact' => true]) ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php if (count($packages) > 1 && $compareRows): ?>
<section class="section">
    <div class="container">
        <div class="section-head" data-reveal>
            <p class="eyebrow">Side by side</p>
            <h2 class="mt-4">Compare what is included.</h2>
            <p class="lead">Scroll horizontally on smaller screens.</p>
        </div>

        <div class="table-wrap" data-reveal>
            <table class="table compare-table">
                <caption class="sr-only">Feature comparison across packages</caption>
                <thead>
                    <tr>
                        <th scope="col">Feature</th>
                        <?php foreach ($packages as $p): ?>
                        <th scope="col">
                            <?= e($p['name']) ?>
                            <?php if (!empty($p['badge'])): ?><br><span class="badge badge--accent"><?= e($p['badge']) ?></span><?php endif; ?>
                        </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($showPrice): ?>
                    <tr>
                        <th scope="row" style="color:var(--text)">Price</th>
                        <?php foreach ($packages as $p): ?>
                        <td class="num" style="text-align:center">
                            <?php if ($p['pricing']['is_custom']): ?>
                                <span class="text-muted">On request</span>
                            <?php else: ?>
                                <strong><?= e(money($p['pricing']['payable'])) ?></strong>
                                <?php if ($p['pricing']['has_discount'] && $showSave): ?>
                                <br><span class="strike text-muted" style="font-size:var(--fs-xs)"><?= e(money($p['pricing']['regular'])) ?></span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endif; ?>
                    <?php foreach ($compareRows as $rowTitle): ?>
                    <tr>
                        <th scope="row"><?= e($rowTitle) ?></th>
                        <?php foreach ($packages as $p):
                            $state = 'absent';
                            foreach ($p['features'] as $f) {
                                if ((string) $f['title'] === $rowTitle) {
                                    $state = (int) $f['is_included'] === 1 ? 'yes' : 'no';
                                    break;
                                }
                            }
                        ?>
                        <td>
                            <?php if ($state === 'yes'): ?>
                                <span class="yes" title="Included"><?= icon('check') ?><span class="sr-only">Included</span></span>
                            <?php elseif ($state === 'no'): ?>
                                <span class="no" title="Not included"><?= icon('x') ?><span class="sr-only">Not included</span></span>
                            <?php else: ?>
                                <span class="no" aria-label="Not applicable">—</span>
                            <?php endif; ?>
                        </td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($addons): ?>
<section class="section">
    <div class="container">
        <div class="section-head" data-reveal>
            <p class="eyebrow">Add-ons</p>
            <h2 class="mt-4">Extend any package.</h2>
            <p class="lead">
                <?= $showPrice
                    ? 'Add these at checkout, or later. Each is priced individually so you always know what a change costs.'
                    : 'Tick any of these when you send your request, or add them later.' ?>
            </p>
        </div>

        <div class="slider" data-slider>
            <div class="slider__track slider__track--cards" data-reveal-stagger>
            <?php foreach ($addons as $i => $addon): ?>
            <div class="card card--interactive" style="--i:<?= $i ?>" data-reveal>
                <div class="row row--between" style="align-items:flex-start">
                    <h3 class="card__title" style="font-size:var(--fs-sm)"><?= e($addon['name']) ?></h3>
                    <?php if ($showPrice): ?>
                    <span class="badge badge--accent nowrap"><?= e(money($addon['price'])) ?></span>
                    <?php endif; ?>
                </div>
                <p class="card__text"><?= e($addon['description']) ?></p>
                <div class="card__footer">
                    <span class="hint"><?= e($addon['billing_period'] === 'one-time' ? 'One-time' : ucfirst((string) $addon['billing_period'])) ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="section">
    <div class="container container--narrow">
        <div class="notice notice--accent" data-reveal>
            <?= icon('shield') ?>
            <span>
                <strong style="color:var(--text)">No payment is taken on this website.</strong>
                Sending a request starts a conversation. We confirm the scope, then issue an invoice with payment
                instructions.
            </span>
        </div>
    </div>
</section>

<?= $view->partial('partials/cta-band', [
    'eyebrow'    => 'Not sure which one?',
    'heading'    => 'Message us and we will tell you which one fits.',
    'lead'       => 'Say what the business does and what you want online. We reply with the package and the price.',
    'waMessage'  => 'Hi, I am looking at your packages. Which one fits my business?',
    'mailSubject'=> 'Packages — which one fits?',
]) ?>
