<?php
require __DIR__ . '/app/bootstrap.php';
http_response_code(404);
$title = 'Page not found — ' . txt('site.name', 'TECHBISS');
$desc  = "That page isn't here — but the site is up. Here's the way back.";
require __DIR__ . '/app/partials/head.php';
require __DIR__ . '/app/partials/header.php';
?>
<section class="page-hero" style="padding-bottom:clamp(2rem,5vw,3rem)">
  <div class="wrap">
    <p class="crumbs"><a href="<?= e(base_url('index.php')) ?>">Home</a> / 404</p>
    <p class="eyebrow"><span class="live"></span> Page not found — everything else is fine</p>
    <h1>That page isn't here.</h1>
    <p class="lead">The link may be old, or something moved. The site itself is up — here's the way back.</p>
    <div class="hero-cta" style="margin-top:1.6rem">
      <a class="btn btn--primary magnetic" href="<?= e(base_url('index.php')) ?>">Back to home →</a>
      <a class="btn btn--ghost magnetic" href="<?= e(base_url('contact.php')) ?>">Tell us what broke</a>
    </div>
  </div>
</section>

<section class="sec">
  <div class="wrap">
    <div class="sec-head reveal">
      <h2>Try one of these instead.</h2>
      <p>The four pages people are usually looking for.</p>
    </div>
    <div class="bento reveal">
      <?php foreach ([['services.php','Services','Websites, apps, domain, hosting, SSL, email, SEO and support.'],
                      ['pricing.php','Pricing','Fixed-price packages and monthly care plans.'],
                      ['industries.php','Industries','What the setup looks like in your sector.'],
                      ['contact.php','Contact','One business day, always a human.']] as $n => [$href,$name,$blurb]): ?>
        <a class="card card--b" data-tilt href="<?= e(base_url($href)) ?>">
          <span class="card__ghost">0<?= $n + 1 ?></span><span class="card__no">0<?= $n + 1 ?></span>
          <div class="card__glow"></div>
          <h3 class="card__name"><?= e($name) ?></h3>
          <p class="card__desc"><?= e($blurb) ?></p>
          <span class="card__more">Open →</span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php require __DIR__ . '/app/partials/footer.php'; ?>
