<?php
$nav = [
    'index.php'      => 'Home',
    'services.php'   => 'Services',
    'industries.php' => 'Industries',
    'pricing.php'    => 'Pricing',
    'about.php'      => 'About',
    'contact.php'    => 'Contact',
];
$here = current_page();
?>
<header class="mast">
  <div class="wrap">
    <a class="brand" href="<?= e(base_url('index.php')) ?>"><span class="brand__mark"><?= e(txt('site.mark', 'T')) ?></span><?= e(txt('site.name', 'TECHBISS')) ?></a>
    <nav class="mast-nav" id="mastNav">
      <?php foreach ($nav as $file => $label): ?>
        <a href="<?= e(base_url($file)) ?>"<?= $file === $here ? ' class="is-current"' : '' ?>><?= e($label) ?></a>
      <?php endforeach; ?>
      <a class="nav-cta" href="<?= e(base_url('contact.php')) ?>"><?= e(txt('nav.cta', 'Talk to us')) ?> →</a>
    </nav>
    <a class="mast-cta magnetic" href="<?= e(base_url('contact.php')) ?>"><?= e(txt('nav.cta', 'Talk to us')) ?></a>
    <button class="burger" id="burger" aria-label="Menu" aria-controls="mastNav" aria-expanded="false"><span></span></button>
  </div>
</header>

<main id="main">
