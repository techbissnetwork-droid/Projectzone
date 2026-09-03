<?php
$title = $title ?? 'Ready to start?';
$body = $body ?? 'Book a working session with an architect. Two weeks of fixed-scope discovery ends with a plan and a fixed price — and you keep all of it either way.';
$primary = $primary ?? ['label' => 'Start a project', 'path' => '/contact'];
$secondary = $secondary ?? ['label' => 'Browse the Marketplace', 'path' => '/marketplace'];
?>
<section class="section">
  <div class="container">
    <div class="cta-band spotlight" data-reveal>
      <div class="cta-band__inner">
        <div>
          <h2><?= e($title) ?></h2>
          <p><?= e($body) ?></p>
        </div>
        <div class="cta-band__actions">
          <a class="btn btn--primary btn--lg" href="<?= e(url($primary['path'])) ?>"><?= e($primary['label']) ?><?= icon('arrow-right') ?></a>
          <a class="btn btn--ghost btn--lg" href="<?= e(url($secondary['path'])) ?>"><?= e($secondary['label']) ?></a>
        </div>
      </div>
    </div>
  </div>
</section>
