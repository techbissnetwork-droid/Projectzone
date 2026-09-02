<?php
declare(strict_types=1);
require_once __DIR__ . '/app/bootstrap.php';
$services = Database::all('SELECT * FROM services WHERE is_active = 1 ORDER BY sort_order, id');
$PAGE_TITLE = 'Services';
$META_DESC  = 'Websites, apps, domains, hosting, SSL, business email, e-commerce, automation and payments — every service ' . Settings::get('site_name') . ' delivers.';
$CANONICAL  = url('services.php');
require __DIR__ . '/partials/public_header.php';
?>
<section class="pagehead" data-theme="deep">
  <div class="shell">
    <p class="eyebrow reveal">What we build</p>
    <h1 class="pagehead__title reveal">Everything your business<br>needs to exist <span class="grad">online.</span></h1>
    <p class="pagehead__lede reveal">Nine connected modules. Commission one, or let us run the entire stack as a single system.</p>
  </div>
</section>

<section class="svcs">
  <div class="shell">
    <div class="svcs__grid" id="svcGrid"><?php require __DIR__ . '/partials/service_cards.php'; ?></div>
    <p class="svcs__foot reveal">Also delivered: business digitization · custom web applications · maintenance &amp; technical support.
      <a class="link" href="<?= e(url('contact.php')) ?>">Ask about a full stack engagement <span aria-hidden="true">→</span></a></p>
  </div>
</section>

<?php require __DIR__ . '/partials/section_cta.php'; ?>
<?php require __DIR__ . '/partials/public_footer.php'; ?>
