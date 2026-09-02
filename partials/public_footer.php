<?php if (!class_exists('Settings')) { http_response_code(404); exit('Not found.'); }
?>
</main>
<footer class="foot">
  <div class="shell">
    <div class="foot__top">
      <div class="foot__brand">
        <a class="brand brand--lg" href="<?= e(url()) ?>">
          <?= Content::logo() ?>
          <?php if (Settings::get('logo_image', '') === ''): ?><span class="brand__word"><?= e(Settings::get('site_name', 'TECHBISS')) ?></span><?php endif; ?>
        </a>
        <p class="foot__statement"><?= e(Settings::get('footer_note', Settings::get('site_tagline'))) ?></p>
        <p class="foot__mail"><a class="link" href="mailto:<?= e(Settings::get('contact_email')) ?>"><?= e(Settings::get('contact_email')) ?></a></p>
        <?php if (Settings::get('contact_phone')): ?>
          <p class="foot__mail"><a class="link" href="tel:<?= e(Settings::get('contact_phone')) ?>"><?= e(Settings::get('contact_phone')) ?></a></p>
        <?php endif; ?>
      </div>

      <nav class="foot__cols" aria-label="Footer">
        <?php
        $footCols = [
            'footer_1' => Settings::get('foot_col1', 'Services'),
            'footer_2' => Settings::get('foot_col2', 'Company'),
            'footer_3' => Settings::get('foot_col3', 'Clients'),
        ];
        foreach ($footCols as $locKey => $heading):
            $links = Content::nav($locKey);
            if (!$links) { continue; } ?>
          <div class="foot__col">
            <h4><?= e($heading) ?></h4>
            <ul>
              <?php foreach ($links as $ln): ?>
                <li><a href="<?= e($ln['href']) ?>"<?= $ln['new_tab'] ? ' target="_blank" rel="noopener"' : '' ?>><?= e($ln['label']) ?></a></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endforeach; ?>
        <div class="foot__col">
          <h4>Contact</h4>
          <ul>
            <li><a href="mailto:<?= e(Settings::get('contact_email')) ?>"><?= e(Settings::get('contact_email')) ?></a></li>
            <?php if (Settings::get('contact_phone')): ?><li><a href="tel:<?= e(Settings::get('contact_phone')) ?>"><?= e(Settings::get('contact_phone')) ?></a></li><?php endif; ?>
            <?php if (Settings::get('contact_address')): ?><li><span class="muted"><?= enl(Settings::get('contact_address')) ?></span></li><?php endif; ?>
            <?php if (Settings::get('contact_hours')): ?><li><span class="muted"><?= e(Settings::get('contact_hours')) ?></span></li><?php endif; ?>
          </ul>
          <?php
          $social = array_filter([
              'LinkedIn' => Settings::get('social_linkedin'),
              'X'        => Settings::get('social_x'),
              'Facebook' => Settings::get('social_facebook'),
              'GitHub'   => Settings::get('social_github'),
          ]);
          if ($social): ?>
            <div class="foot__social">
              <?php
              $icons = [
                'LinkedIn' => '<rect x="2.5" y="2.5" width="19" height="19" rx="3.5" stroke="currentColor" stroke-width="1.3"/><path d="M7 10v7M7 7v.01M11 17v-4a2 2 0 0 1 4 0v4" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/>',
                'X'        => '<path d="M4 4l16 16M20 4 4 20" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/>',
                'Facebook' => '<rect x="2.5" y="2.5" width="19" height="19" rx="3.5" stroke="currentColor" stroke-width="1.3"/><path d="M15 8h-1.5A1.5 1.5 0 0 0 12 9.5V21M9.5 13h5" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/>',
                'GitHub'   => '<path d="M9 19c-4 1.2-4-2.2-5.5-2.8M15 21v-3.4c0-1 .1-1.4-.5-2 2.4-.3 4.5-1.2 4.5-5a3.9 3.9 0 0 0-1-2.7 3.6 3.6 0 0 0-.1-2.7s-.9-.3-3 1.1a10.3 10.3 0 0 0-5.4 0C7.4 3.2 6.5 3.5 6.5 3.5a3.6 3.6 0 0 0-.1 2.7 3.9 3.9 0 0 0-1 2.7c0 3.8 2.1 4.7 4.5 5-.6.6-.6 1.1-.5 2V21" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/>',
              ];
              foreach ($social as $netName => $link): ?>
                <a href="<?= e($link) ?>" target="_blank" rel="noopener noreferrer" aria-label="<?= e(Settings::get('site_name')) ?> on <?= e($netName) ?>">
                  <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><?= $icons[$netName] ?></svg></a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </nav>
    </div>

    <div class="foot__wordmark" aria-hidden="true"><?= e(Settings::get('site_name', 'TECHBISS')) ?></div>

    <div class="foot__bottom">
      <p>© <?= date('Y') ?> <?= e(Settings::get('site_name', 'TECHBISS')) ?>. All rights reserved.</p>
      <p class="muted">
        <a class="link" href="<?= e(url('login.php')) ?>">Client sign in</a>
        <span aria-hidden="true"> · </span>
        <a class="link" href="<?= e(url('staff-login.php')) ?>">Staff</a>
      </p>
    </div>
  </div>
</footer>
<script src="<?= e(asset('assets/js/site.js')) ?>" defer></script>
<?php if (!empty($WITH_VIZ)): ?><script src="<?= e(asset('assets/js/viz.js')) ?>" defer></script><?php endif; ?>
<?= Settings::get('custom_body_end') ?>
</body>
</html>
