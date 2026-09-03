<?php /** @var array $nav @var array $site */ ?>
<footer class="footer">
  <div class="container container--wide">
    <div class="footer__grid">
      <div class="footer__brand">
        <a class="logo" href="<?= e(url('/')) ?>">
          <span class="logo__mark"><?= icon('layers', ['stroke' => 1.8]) ?></span>
          <span class="logo__text">TECHBISS<small>PLATFORM</small></span>
        </a>
        <p><?= e($site['brand']['positioning']) ?></p>

        <div class="footer__newsletter">
          <h4>Field notes, monthly</h4>
          <form method="post" action="<?= e(url('/newsletter')) ?>">
            <?= csrf_field() ?>
            <label class="sr-only" for="footer-email">Email address</label>
            <input class="input" type="email" id="footer-email" name="email" required
                   placeholder="you@company.com" autocomplete="email">
            <button class="btn btn--primary" type="submit" aria-label="Subscribe"><?= icon('arrow-right') ?></button>
          </form>
        </div>

        <div class="footer__certs">
          <?php foreach ($site['certifications'] as $cert): ?>
            <span><?= e($cert['label']) ?></span>
          <?php endforeach; ?>
        </div>
      </div>

      <?php foreach ($nav['footer'] as $column): ?>
        <div class="footer__col">
          <h4><?= e($column['title']) ?></h4>
          <?php foreach ($column['links'] as $link): ?>
            <a href="<?= e(url($link['path'])) ?>"><?= e($link['label']) ?></a>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="footer__grid mt-7" style="--gutter:0">
      <div class="footer__col">
        <h4>Sign in</h4>
        <?php foreach ($nav['portals'] as $portal): ?>
          <a href="<?= e(url($portal['path'])) ?>"><?= e($portal['label']) ?></a>
        <?php endforeach; ?>
        <a href="<?= e(url('/install')) ?>">Advanced Installer</a>
      </div>
      <?php foreach (array_slice($site['offices'], 0, 4) as $office): ?>
        <div class="footer__col">
          <h4><?= e($office['city']) ?></h4>
          <p class="tiny dim" style="line-height:1.6"><?= e($office['address']) ?></p>
          <a href="tel:<?= e(preg_replace('/[^\d+]/', '', $office['phone'])) ?>"><?= e($office['phone']) ?></a>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="footer__bottom">
      <span>© <?= date('Y') ?> <?= e(config('app.legal_name', 'TECHBISS')) ?>. All rights reserved.</span>
      <nav class="footer__legal" aria-label="Legal">
        <?php foreach ($nav['legal'] as $link): ?>
          <a href="<?= e(url($link['path'])) ?>"><?= e($link['label']) ?></a>
        <?php endforeach; ?>
      </nav>
      <div class="footer__social">
        <?php foreach ($site['brand']['social'] as $social): ?>
          <a href="<?= e($social['url']) ?>" rel="noopener noreferrer" target="_blank"
             aria-label="<?= e($social['label']) ?>">
            <?= icon($social['icon'] === 'x' ? 'x-social' : $social['icon']) ?>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</footer>
