<?php
require __DIR__ . '/app/bootstrap.php';
require_installed();
require __DIR__ . '/app/partials/sections.php';

$title = txt('services.meta.title', 'Services — TECHBISS');
$desc  = txt('services.meta.desc');
require __DIR__ . '/app/partials/head.php';
require __DIR__ . '/app/partials/header.php';

$crumb = 'Services';
$eyebrowKey = 'services.hero.eyebrow';
$headingKey = 'services.hero.heading';
$leadKey    = 'services.hero.lead';
require __DIR__ . '/app/partials/pagehero.php';

$services = rows('services');
?>

<section class="sec">
  <div class="wrap">
    <?php foreach ($services as $i => $s) {
        feature_row($s, $i % 2 === 1);
    } ?>
  </div>
</section>

<?php
section_statement('services.statement');
section_faq('services', 'services.faq.heading', 'services.faq.sub');
section_cta('services');
require __DIR__ . '/app/partials/footer.php';
