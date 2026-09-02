</main>

<footer class="site-foot"><div class="wrap">
  <div class="fg">
    <div>
      <a class="logo" href="index.php"><i aria-hidden="true"></i><?= esc(setting('site.name', 'TECHBISS')) ?></a>
      <p><?= esc(setting('site.tagline')) ?></p>
    </div>
    <div><h5>Company</h5><ul>
      <li><a href="services.php">Services</a></li>
      <li><a href="industries.php">Industries</a></li>
      <li><a href="portfolio.php">Work</a></li>
      <li><a href="marketplace.php">Marketplace</a></li>
      <li><a href="pricing.php">Pricing</a></li>
      <li><a href="about.php">About</a></li>
    </ul></div>
    <div><h5>Contact</h5><ul>
      <li><a href="mailto:<?= esc(setting('site.email')) ?>"><?= esc(setting('site.email')) ?></a></li>
      <li><a href="mailto:<?= esc(setting('site.support_email')) ?>"><?= esc(setting('site.support_email')) ?></a></li>
<?php if (setting('site.phone')): ?>
      <li><?= esc(setting('site.phone')) ?></li>
<?php endif; ?>
      <li><?= esc(setting('site.hours')) ?></li>
    </ul></div>
    <div><h5>Your account</h5><ul>
      <li><a href="client/login.php">Client portal</a></li>
      <li><a href="contact.php">Start a project</a></li>
<?php foreach ([['social.linkedin','LinkedIn'],['social.instagram','Instagram'],['social.whatsapp','WhatsApp']] as [$k,$label]):
        $u = setting($k); if ($u === '' || $u === '#') continue; ?>
      <li><a href="<?= esc($u) ?>" rel="noopener"><?= $label ?></a></li>
<?php endforeach; ?>
    </ul></div>
  </div>
  <div class="fb">
    <span>&copy; <?= date('Y') ?> <?= esc(setting('site.name', 'TECHBISS')) ?></span>
    <span>Always on</span>
  </div>
</div></footer>

<script src="assets/js/site.js"></script>
</body>
</html>
