<?php if (!class_exists('Settings')) { http_response_code(404); exit('Not found.'); }
?>
<!-- ══════════════ 09 · FINAL CTA ══════════════ -->
<section class="cta" id="contact" data-theme="deep">
  <div class="cta__field" aria-hidden="true"><span class="cta__halo"></span><canvas id="ctaCanvas"></canvas></div>
  <div class="shell cta__inner">
    <p class="eyebrow reveal"><span class="num"><?= sprintf('%02d', $secNum ?? 9) ?></span> <?= e(Settings::get('cta_eyebrow', 'Start here')) ?></p>
    <h2 class="cta__title">
      <span class="mask"><span><?= e(Settings::get('cta_title_a')) ?></span></span>
      <span class="mask"><span><?= e(Settings::get('cta_title_b')) ?> <span class="grad"><?= e(Settings::get('cta_title_c')) ?></span></span></span>
    </h2>
    <p class="cta__lede reveal"><?= e(Settings::get('cta_lede')) ?></p>
    <div class="cta__actions reveal">
      <a class="btn btn--primary btn--lg magnetic" href="<?= e(url('contact.php')) ?>"><?= e(Settings::get('cta_primary')) ?> <span class="btn__arrow">→</span></a>
      <a class="btn btn--ghost btn--lg magnetic" href="mailto:<?= e(Settings::get('contact_email')) ?>"><?= e(Settings::get('cta_secondary')) ?></a>
    </div>
    <ul class="cta__facts">
      <li><span class="mono">reply time</span><b>1 business day</b></li>
      <li><span class="mono">first call</span><b>Free</b></li>
      <li><span class="mono">engagement</span><b>Project or retainer</b></li>
    </ul>
  </div>
</section>
