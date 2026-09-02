<?php if (!class_exists('Settings')) { http_response_code(404); exit('Not found.'); }
?>
<!-- ══════════════ 09 · FINAL CTA ══════════════ -->
<section class="cta" id="contact" data-theme="deep">
  <div class="cta__field" aria-hidden="true"><span class="cta__halo"></span><canvas id="ctaCanvas"></canvas></div>
  <div class="shell cta__inner">
    <p class="eyebrow reveal"><span class="num">09</span> Start here</p>
    <h2 class="cta__title">
      <span class="mask"><span>Ready to take your</span></span>
      <span class="mask"><span>business <span class="grad">online?</span></span></span>
    </h2>
    <p class="cta__lede reveal">Tell us what you’re building. We’ll figure out the technology.</p>
    <div class="cta__actions reveal">
      <a class="btn btn--primary btn--lg magnetic" href="<?= e(url('contact.php')) ?>">Start Your Project <span class="btn__arrow">→</span></a>
      <a class="btn btn--ghost btn--lg magnetic" href="mailto:<?= e(Settings::get('contact_email')) ?>">Talk to <?= e(Settings::get('site_name', 'TECHBISS')) ?></a>
    </div>
    <ul class="cta__facts">
      <li><span class="mono">reply time</span><b>Within one business day</b></li>
      <li><span class="mono">first call</span><b>Free, no obligation</b></li>
      <li><span class="mono">engagement</span><b>Project or retainer</b></li>
    </ul>
  </div>
</section>
