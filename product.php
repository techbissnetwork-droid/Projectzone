<?php
/** One marketplace listing, with the order form. */
require_once __DIR__ . '/app/bootstrap.php';
require_installed();
require_once APP_DIR . '/partials/sections.php';

$item = product_by_slug(get('slug'));

if (!$item) {
    http_response_code(404);
    $pageTitle = 'Not found — ' . setting('site.name');
    $activeNav = 'marketplace';
    include APP_DIR . '/partials/head.php';
    page_head('404', 'That project', 'is not listed.',
        'It may have sold or been taken down. Everything currently available is on the marketplace.',
        [['index.php', 'Home'], ['marketplace.php', 'Marketplace'], [null, 'Not found']],
        [['marketplace.php', 'Browse the marketplace &rarr;']]);
    include APP_DIR . '/partials/footer.php';
    exit;
}

$sym       = setting('site.currency', '$');
$price     = product_price($item);
$setupCost = (float) setting('market.setup.price', '450');
$errors    = [];
$sent      = false;

if (is_post()) {
    csrf_check();

    $data = [
        'reference'     => reference('ORD'),
        'product_id'    => (int) $item['id'],
        'user_id'       => current_user()['id'] ?? null,
        'buyer_name'    => post('name'),
        'buyer_email'   => strtolower(post('email')),
        'buyer_phone'   => post('phone'),
        'buyer_company' => post('company'),
        'wants_setup'   => post('setup') === '1' ? 1 : 0,
        'notes'         => post('notes'),
        'status'        => 'new',
        'created_at'    => now(),
    ];
    $data['amount'] = $price + ($data['wants_setup'] ? $setupCost : 0);

    if ($data['buyer_name'] === '')            { $errors[] = 'Enter your name.'; }
    if (!valid_email($data['buyer_email']))    { $errors[] = 'Enter a valid email address.'; }
    if (mb_strlen($data['notes']) > 4000)      { $errors[] = 'That note is too long.'; }
    if (post('company_website') !== '')        { $errors[] = 'Rejected.'; }   // honeypot

    if (!$errors) {
        $id = db_insert('orders', $data);
        $data['id'] = $id;
        mail_order($data, $item);
        mail_order_receipt($data, $item);
        log_activity('Marketplace order placed', 'order', $id, $data['buyer_name']);
        $sent = true;
    }
}

$pageTitle = $item['title'] . ' — ' . setting('site.name');
$pageDesc  = $item['summary'];
$activeNav = 'marketplace';
$more      = array_values(array_filter(active_products(4), fn($p) => $p['id'] !== $item['id']));

include APP_DIR . '/partials/head.php';
?>

<section class="phead"><div class="wrap">
  <div class="crumbs">
    <a href="index.php">Home</a> <span>/</span>
    <a href="marketplace.php">Marketplace</a> <span>/</span>
    <span><?= esc($item['title']) ?></span>
  </div>
  <span class="badge"><i aria-hidden="true"></i><?= esc($item['category'] ?: 'Project') ?><?php
    if (product_on_sale($item)) { echo ' &middot; on sale'; } ?></span>
  <h1><span class="chrome"><?= esc($item['title']) ?></span></h1>
  <p><?= esc($item['summary']) ?></p>
  <div class="hr-acts">
    <a class="pill" href="#order">Order this &mdash; <?= esc(money($price, $sym)) ?></a>
<?php if ($item['demo_url']): ?>
    <a class="pill ghost" href="<?= esc($item['demo_url']) ?>" rel="noopener" target="_blank">See it running</a>
<?php endif; ?>
  </div>
</div></section>

<section><div class="wrap">
  <div class="article">
    <div class="body">
<?php if ($item['cover_image']): ?>
      <div class="shot big"><img src="<?= esc($item['cover_image']) ?>" alt="<?= esc($item['title']) ?>"></div>
<?php endif; ?>
      <?= esc_para($item['body']) ?>
<?php if (lines($item['features'])): ?>
      <h3 class="subhead">What is included</h3>
      <ul class="ticks big">
<?php foreach (lines($item['features']) as $f): ?>
        <li><?= esc($f) ?></li>
<?php endforeach; ?>
      </ul>
<?php endif; ?>
    </div>
    <aside class="side">
      <div class="factbox price">
        <div class="amt"><?= esc(money($price, $sym)) ?>
<?php if (product_on_sale($item)): ?>
          <s><?= esc(money($item['price'], $sym)) ?></s>
<?php endif; ?>
        </div>
        <div class="per">One-off, all files included</div>
        <a class="pill lg" href="#order" style="width:100%;margin-top:18px">Order this project</a>
      </div>
      <div class="factbox">
        <h4>Details</h4>
<?php if ((int) $item['pages'] > 0): ?>
        <div class="fact"><span>Pages</span><strong><?= (int) $item['pages'] ?></strong></div>
<?php endif; ?>
<?php if ($item['category']): ?>
        <div class="fact"><span>Category</span><strong><?= esc($item['category']) ?></strong></div>
<?php endif; ?>
        <div class="fact"><span>Setup service</span>
          <strong><?= esc(money($setupCost, $sym)) ?></strong></div>
      </div>
<?php if (lines($item['tech'])): ?>
      <div class="factbox">
        <h4>Built with</h4>
        <div class="tagrow">
<?php foreach (lines($item['tech']) as $t): ?><b><?= esc($t) ?></b><?php endforeach; ?>
        </div>
      </div>
<?php endif; ?>
    </aside>
  </div>
</div></section>

<section id="order"><div class="wrap">
  <div class="sh rv"><span class="no">Order</span><h2>Take this one.</h2>
    <p>Nothing is charged here. Send the order and we reply within one business day with payment
       details and a handover date.</p></div>

<?php if ($sent): ?>
  <div class="notebox ok rv">
    <h3>Order received.</h3>
    <p>We have it, and a copy is on its way to your inbox. We will reply within one business day
       with payment details and a date for handover.</p>
    <a class="pill" href="marketplace.php">Back to the marketplace</a>
  </div>
<?php else: ?>
  <div class="formgrid rv">
    <form method="post" class="enquiry" action="product.php?slug=<?= urlencode($item['slug']) ?>#order">
      <?= csrf_field() ?>
<?php if ($errors): ?>
      <div class="formstatus show bad">
        <?php foreach ($errors as $e) { echo esc($e) . '<br>'; } ?>
      </div>
<?php endif; ?>
      <div class="two">
        <div class="field"><label for="name">Your name <span class="req">*</span></label>
          <input id="name" name="name" required value="<?= esc(post('name')) ?>">
          <span class="err">We need a name to put on the order.</span></div>
        <div class="field"><label for="email">Email <span class="req">*</span></label>
          <input id="email" name="email" type="email" required value="<?= esc(post('email')) ?>">
          <span class="err">Enter an address we can reply to.</span></div>
      </div>
      <div class="two">
        <div class="field"><label for="phone">Phone</label>
          <input id="phone" name="phone" value="<?= esc(post('phone')) ?>"></div>
        <div class="field"><label for="company">Business name</label>
          <input id="company" name="company" value="<?= esc(post('company')) ?>"></div>
      </div>

      <div class="field">
        <label>Setup</label>
        <label class="check">
          <input type="checkbox" name="setup" value="1"<?= post('setup') === '1' ? ' checked' : '' ?>>
          <span>Set it up for me on my own domain, hosting, SSL and email
                (+<?= esc(money($setupCost, $sym)) ?>)</span>
        </label>
      </div>

      <div class="field"><label for="notes">Anything we should know</label>
        <textarea id="notes" name="notes" placeholder="Your domain, when you need it live, changes you already know you want…"><?= esc(post('notes')) ?></textarea></div>

      <input class="hp" type="text" name="company_website" tabindex="-1" autocomplete="off" aria-hidden="true">
      <div id="formstatus" class="formstatus"></div>
      <button class="pill lg" type="submit">Send the order</button>
      <p class="formnote">No payment is taken on this page and no account is created.
         Your details are used to fulfil the order and nothing else.</p>
    </form>

    <aside class="contactside">
      <div class="cbit">
        <h4>Order total</h4>
        <strong id="totalline"><?= esc(money($price, $sym)) ?></strong>
        <p><?= esc($item['title']) ?>, all files included. Add setup and it becomes
           <?= esc(money($price + $setupCost, $sym)) ?>.</p>
      </div>
      <div class="cbit">
        <h4>Prefer to talk first?</h4>
        <a href="mailto:<?= esc(setting('site.email')) ?>"><?= esc(setting('site.email')) ?></a>
        <p><?= esc(setting('site.hours')) ?></p>
      </div>
      <div class="cbit">
        <h4>What happens next</h4>
        <p>We confirm within one business day, send payment details, and agree a handover date.
           Everything ends up registered in your name.</p>
      </div>
    </aside>
  </div>
<?php endif; ?>
</div></section>

<?php if ($more): ?>
<section><div class="wrap">
  <div class="sh rv"><span class="no">Also ready</span><h2>Other projects<br>on the shelf.</h2></div>
  <div class="grid-3">
<?php foreach (array_slice($more, 0, 3) as $p) { product_card($p); } ?>
  </div>
</div></section>
<?php endif; ?>

<script>
/* Keep the sidebar total in step with the setup checkbox. */
(function () {
  var box = document.querySelector('input[name="setup"]');
  var out = document.getElementById('totalline');
  if (!box || !out) return;
  var base = <?= json_encode($price) ?>, setup = <?= json_encode($setupCost) ?>;
  var symbol = <?= json_encode($sym) ?>;
  box.addEventListener('change', function () {
    var total = base + (box.checked ? setup : 0);
    out.textContent = symbol + total.toLocaleString('en-US');
  });
})();
</script>

<?php include APP_DIR . '/partials/footer.php'; ?>
