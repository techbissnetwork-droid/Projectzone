<?php
/** @var array $footerNav @var array $legalNav @var array $socialLinks @var \Techbiss\Repo\SettingsRepo $settings */
$siteName  = $settings->get('site_name', 'TECHBISS');
$copyright = str_replace('{year}', date('Y'), $settings->get('copyright', '© {year} ' . $siteName));
$logo      = media_url($settings->get('logo'));

// Split the footer menu into balanced columns.
$links   = $footerNav;
$perCol  = max(1, (int) ceil(count($links) / 2));
$columns = array_chunk($links, $perCol);
?>
<footer class="site-footer">
    <div class="container">
        <div class="footer-top">
            <div class="footer-intro">
                <a class="brand" href="<?= e(url('/')) ?>">
                    <?php if ($logo !== ''): ?>
                        <img class="brand__logo" src="<?= e($logo) ?>" alt="<?= e($siteName) ?>" width="140" height="30" loading="lazy">
                    <?php else: ?>
                        <svg class="brand__mark" aria-hidden="true" focusable="false"><use href="#tb-mark"/></svg>
                        <span class="brand__text"><?= e($siteName) ?></span>
                    <?php endif; ?>
                </a>
                <p><?= e($settings->get('footer_text')) ?></p>

                <div class="newsletter">
                    <div class="footer-col__title">Occasional updates</div>
                    <form class="newsletter__form" method="post" action="<?= e(url('/api/newsletter')) ?>" data-form="async">
                        <?= csrf_field() ?>
                        <input type="hidden" name="source" value="footer">
                        <div class="hp-field" aria-hidden="true">
                            <label for="nl-hp">Leave this empty</label>
                            <input id="nl-hp" type="text" name="website_url" tabindex="-1" autocomplete="off">
                        </div>
                        <label class="sr-only" for="newsletter-email">Email address</label>
                        <input class="input" id="newsletter-email" type="email" name="email"
                               placeholder="you@yourbusiness.com" required autocomplete="email">
                        <button class="btn btn--primary" type="submit" aria-label="Subscribe"><?= icon('arrow-right') ?></button>
                    </form>
                    <p class="newsletter__note">Practical writing on going digital. No spam, unsubscribe any time.</p>
                </div>
            </div>

            <div class="footer-cols">
                <?php foreach ($columns as $i => $col): ?>
                <div class="footer-col">
                    <div class="footer-col__title"><?= $i === 0 ? 'Explore' : 'Company' ?></div>
                    <ul>
                        <?php foreach ($col as $link): ?>
                        <li><a href="<?= e(url($link['url'])) ?>"><?= e($link['label']) ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endforeach; ?>

                <div class="footer-col">
                    <div class="footer-col__title">Get in touch</div>
                    <ul>
                        <?php if ($settings->get('contact_email') !== ''): ?>
                        <li><a href="mailto:<?= e($settings->get('contact_email')) ?>"><?= icon('mail') ?><?= e($settings->get('contact_email')) ?></a></li>
                        <?php endif; ?>
                        <?php if ($settings->get('sales_email') !== '' && $settings->get('sales_email') !== $settings->get('contact_email')): ?>
                        <li><a href="mailto:<?= e($settings->get('sales_email')) ?>"><?= icon('send') ?><?= e($settings->get('sales_email')) ?></a></li>
                        <?php endif; ?>
                        <?php if ($settings->get('support_email') !== '' && $settings->get('support_email') !== $settings->get('contact_email')): ?>
                        <li><a href="mailto:<?= e($settings->get('support_email')) ?>"><?= icon('shield') ?><?= e($settings->get('support_email')) ?></a></li>
                        <?php endif; ?>
                        <?php if ($settings->get('contact_phone') !== ''): ?>
                        <li><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $settings->get('contact_phone'))) ?>"><?= icon('phone') ?><?= e($settings->get('contact_phone')) ?></a></li>
                        <?php endif; ?>
                        <?php if ($settings->get('address') !== ''): ?>
                        <li><span style="display:inline-flex;gap:.4rem;align-items:flex-start;font-size:var(--fs-sm);color:var(--text-muted)"><?= icon('pin') ?><span><?= nl2br(e($settings->get('address'))) ?></span></span></li>
                        <?php endif; ?>
                        <?php if ($settings->get('business_hours') !== ''): ?>
                        <li><span style="display:inline-flex;gap:.4rem;align-items:center;font-size:var(--fs-sm);color:var(--text-muted)"><?= icon('clock') ?><?= e($settings->get('business_hours')) ?></span></li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>

        <div class="footer-bottom">
            <span><?= e($copyright) ?></span>

            <?php if ($legalNav): ?>
            <div class="footer-bottom__links">
                <?php foreach ($legalNav as $link): ?>
                <a href="<?= e(url($link['url'])) ?>"><?= e($link['label']) ?></a>
                <?php endforeach; ?>
                <a href="<?= e(url('/sitemap.xml')) ?>">Sitemap</a>
            </div>
            <?php endif; ?>

            <?php if ($socialLinks): ?>
            <div class="social-row">
                <?php foreach ($socialLinks as $social): ?>
                <a class="social-link" href="<?= e($social['url']) ?>" target="_blank" rel="noopener noreferrer"
                   aria-label="<?= e($social['label']) ?>" data-no-transition>
                    <?= icon($social['key'] === 'x' ? 'x' : $social['key']) ?>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</footer>
