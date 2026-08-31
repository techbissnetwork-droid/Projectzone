<?php
/**
 * The one place anyone asks for work.
 *
 * No packages, no prices, no multi-step form. Tick what you want, say
 * anything else in your own words, and send it — the conversation continues
 * on WhatsApp or by email, which is where the figure is agreed. A copy is
 * kept in the admin so nothing is lost if the message never gets sent.
 *
 * @var array $services @var string $preselect @var array $ready
 */
$wa   = whatsapp_link('');
$mail = email_link('Request from the website');
?>
<?= $view->partial('partials/page-head', [
    'eyebrow' => 'Tell us what you need',
    'heading' => 'Pick what you want. We reply with a price.',
    'lead'    => 'Tick anything you need, add the rest in your own words, then send it. No forms to fill in and nothing to pay here.',
]) ?>

<section class="section section--flush-top">
    <div class="container container--narrow">
        <?php if ($ready): ?>
        <div class="card card--pad-lg mb-6" data-reveal>
            <p class="eyebrow mb-3">Your message is ready</p>
            <pre class="request-preview"><?= e($ready['message']) ?></pre>
            <div class="card__footer stack stack-2">
                <a class="btn btn--primary btn--lg btn--block" href="<?= e($ready['link']) ?>"
                   <?= $ready['channel'] === 'whatsapp' ? 'target="_blank" rel="noopener noreferrer"' : '' ?>
                   data-auto-open>
                    <?= icon($ready['channel'] === 'whatsapp' ? 'whatsapp' : 'mail') ?>
                    Open <?= $ready['channel'] === 'whatsapp' ? 'WhatsApp' : 'your email app' ?>
                </a>
            </div>
            <p class="hint mt-4">It should open by itself. If it does not, use the button — or copy the text above and send it however you like.</p>
        </div>
        <?php endif; ?>

        <form class="card card--pad-lg" method="post" action="<?= e(url('/request')) ?>" data-request-form data-reveal>
            <?= csrf_field() ?>

            <fieldset style="border:0;padding:0;margin:0">
                <legend class="eyebrow mb-3">What do you need?</legend>
                <div class="option-grid">
                    <?php foreach ($services as $service): ?>
                    <label class="option-card">
                        <input type="checkbox" name="services[]" value="<?= e($service['slug']) ?>"
                               <?= $preselect === $service['slug'] ? 'checked' : '' ?>>
                        <span class="check__box" aria-hidden="true"></span>
                        <span class="flex-1">
                            <span class="option-card__title"><?= e($service['name']) ?></span>
                            <?php if (!empty($service['short_description'])): ?>
                            <span class="option-card__text"><?= e(str_limit($service['short_description'], 80)) ?></span>
                            <?php endif; ?>
                        </span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>

            <div class="field mt-6">
                <label class="label" for="req-details">Anything else?</label>
                <textarea class="textarea" id="req-details" name="details" rows="4"
                          placeholder="What the business does, what you want it to do online, anything specific."></textarea>
            </div>

            <div class="grid grid-2 mt-4">
                <div class="field">
                    <label class="label" for="req-name">Your name</label>
                    <input class="input" type="text" id="req-name" name="name" maxlength="120" autocomplete="name">
                </div>
                <div class="field">
                    <label class="label" for="req-business">Business name</label>
                    <input class="input" type="text" id="req-business" name="business_name" maxlength="190" autocomplete="organization">
                </div>
            </div>

            <div class="card__footer stack stack-2">
                <?php if ($wa !== ''): ?>
                <button class="btn btn--primary btn--lg btn--block" type="submit" name="via" value="whatsapp">
                    <?= icon('whatsapp') ?>Send on WhatsApp
                </button>
                <?php endif; ?>
                <?php if ($mail !== ''): ?>
                <button class="btn <?= $wa === '' ? 'btn--primary btn--lg' : 'btn--ghost' ?> btn--block" type="submit" name="via" value="email">
                    <?= icon('mail') ?>Send by email
                </button>
                <?php endif; ?>
                <?php if ($wa === '' && $mail === ''): ?>
                <a class="btn btn--primary btn--lg btn--block" href="<?= e(url('/contact')) ?>">Send us a message</a>
                <?php endif; ?>
            </div>

            <p class="hint mt-4"><?= icon('shield') ?> No payment is taken here. We open the message so you can read it before you send it.</p>
        </form>
    </div>
</section>
