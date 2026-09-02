</main>

<footer class="site">
  <div class="wrap">
    <div class="foot-grid">
      <div>
        <div class="foot-brand"><span class="brand__mark"><?= e(txt('site.mark', 'T')) ?></span><?= e(txt('site.name', 'TECHBISS')) ?></div>
        <p class="foot-desc"><?= e(txt('site.tagline')) ?></p>
        <div class="social">
          <a href="<?= e(txt('social.x', '#')) ?>" aria-label="X">X</a>
          <a href="<?= e(txt('social.linkedin', '#')) ?>" aria-label="LinkedIn">in</a>
          <a href="<?= e(txt('social.instagram', '#')) ?>" aria-label="Instagram">ig</a>
          <a href="<?= e(txt('social.whatsapp', '#')) ?>" aria-label="WhatsApp">wa</a>
        </div>
      </div>
      <div class="foot-col">
        <h4>Services</h4>
        <?php foreach (array_slice(rows('services'), 0, 6) as $s): ?>
          <a href="<?= e(base_url('services.php#' . $s['anchor'])) ?>"><?= e($s['title']) ?></a>
        <?php endforeach; ?>
      </div>
      <div class="foot-col">
        <h4>Company</h4>
        <a href="<?= e(base_url('about.php')) ?>">About</a>
        <a href="<?= e(base_url('industries.php')) ?>">Industries</a>
        <a href="<?= e(base_url('pricing.php')) ?>">Pricing</a>
        <a href="<?= e(base_url('contact.php')) ?>">Contact</a>
      </div>
      <div class="foot-col">
        <h4>Get in touch</h4>
        <a href="mailto:<?= e(txt('site.email')) ?>"><?= e(txt('site.email')) ?></a>
        <a href="tel:<?= e(txt('site.phone_link')) ?>"><?= e(txt('site.phone')) ?></a>
        <a href="<?= e(base_url('contact.php')) ?>"><?= e(txt('nav.cta', 'Talk to us')) ?> →</a>
      </div>
    </div>
    <div class="foot-bottom">
      <span>© <?= date('Y') ?> <?= e(strtoupper(txt('site.name', 'TECHBISS'))) ?> — ALL RIGHTS RESERVED</span>
      <span><?= e(txt('site.footer_note', 'ALWAYS ON')) ?></span>
    </div>
  </div>
</footer>

<script src="<?= e(base_url('assets/js/main.js')) ?>"></script>
</body>
</html>
