<?php
declare(strict_types=1);
require_once __DIR__ . '/app/bootstrap.php';
if (!Settings::bool('show_marketplace', true)) {
    http_response_code(404);
    exit('Not found.');
}

$slug = (string)($_GET['slug'] ?? '');
$p = Database::one('SELECT * FROM products WHERE slug = :s AND is_active = 1', ['s' => $slug]);
if (!$p) {
    http_response_code(404);
    $PAGE_TITLE = 'Not found';
    require __DIR__ . '/partials/public_header.php';
    echo '<section class="pagehead" data-theme="deep"><div class="shell">'
       . '<h1 class="pagehead__title">This product is not available.</h1>'
       . '<p class="pagehead__lede">It may have been unlisted. <a class="link" href="' . e(url('marketplace.php')) . '">Browse the marketplace →</a></p>'
       . '</div></section>';
    require __DIR__ . '/partials/public_footer.php';
    exit;
}

$me      = Auth::user();
$price   = $p['sale_price'] !== null ? (float)$p['sale_price'] : (float)$p['price'];
$methods = array_values(array_filter(Payments::active(), static fn($m) => Payments::isConfigured($m)));
$errors  = [];

if (post()) {
    Csrf::check();
    $name  = trim((string)($_POST['buyer_name'] ?? ''));
    $email = trim(mb_strtolower((string)($_POST['buyer_email'] ?? '')));
    $phone = trim((string)($_POST['buyer_phone'] ?? ''));
    $note  = trim((string)($_POST['notes'] ?? ''));
    $mid   = (int)($_POST['payment_method_id'] ?? 0);

    if ($name === '')      $errors[] = 'Enter your name.';
    if (!is_email($email)) $errors[] = 'Enter a valid email address.';
    if (mb_strlen($note) > 2000) $errors[] = 'Keep the note under 2000 characters.';

    $method = null;
    if ($methods) {
        foreach ($methods as $cand) {
            if ((int)$cand['id'] === $mid) { $method = $cand; break; }
        }
        if (!$method) {
            $errors[] = 'Choose how you would like to pay.';
        }
    }

    if (!$errors) {
        $userId = $me['id'] ?? Database::value('SELECT id FROM users WHERE email = :e', ['e' => $email]);
        $ref    = reference('ORD');
        $token  = bin2hex(random_bytes(16));
        $oid = Database::insert('orders', [
            'reference'         => $ref,
            'user_id'           => $userId ? (int)$userId : null,
            'product_id'        => (int)$p['id'],
            'buyer_name'        => $name,
            'buyer_email'       => $email,
            'buyer_phone'       => $phone ?: null,
            'amount'            => $price,
            'currency'          => Settings::get('currency', 'NPR'),
            'status'            => 'pending',
            'payment_method'    => $method['name'] ?? null,
            'payment_method_id' => $method['id'] ?? null,
            'access_token'      => $token,
            'notes'             => $note ?: null,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);
        log_activity('order.create', 'order', $oid, $ref . ' · ' . $p['title']);
        /* Redirect after posting so a refresh can never place a second order. */
        redirect('order.php?ref=' . urlencode($ref) . '&t=' . urlencode($token) . '&new=1');
    }
}

$PAGE_TITLE = $p['title'];
$META_DESC  = excerpt($p['summary'] ?: $p['body'], 158);
$CANONICAL  = url('product.php?slug=' . urlencode($p['slug']));
$OG_IMAGE   = $p['cover_image'] ? url($p['cover_image']) : null;
require __DIR__ . '/partials/public_header.php';
?>
<article class="detail" data-theme="deep">
  <div class="shell">
    <p class="eyebrow reveal"><a class="link" href="<?= e(url('marketplace.php')) ?>">← Marketplace</a>
      <?php if ($p['category']): ?><span class="num">/</span> <?= e($p['category']) ?><?php endif; ?></p>
    <h1 class="detail__title reveal"><?= e($p['title']) ?></h1>
    <?php if ($p['summary']): ?><p class="detail__lede reveal"><?= e($p['summary']) ?></p><?php endif; ?>

    <?php if ($p['cover_image']): ?>
      <figure class="detail__hero reveal tilt">
        <img src="<?= e(url($p['cover_image'])) ?>" alt="<?= e($p['title']) ?>" loading="eager" decoding="async"></figure>
    <?php endif; ?>

    <div class="detail__grid">
      <div class="detail__body reveal">
        <?php if ($p['body']): ?><p><?= enl($p['body']) ?></p><?php endif; ?>
        <?php $inc = lines($p['includes']); if ($inc): ?>
          <h2 class="detail__h2">What you get</h2>
          <ul class="ticks"><?php foreach ($inc as $x): ?><li><?= e($x) ?></li><?php endforeach; ?></ul>
        <?php endif; ?>

        <h2 class="detail__h2" id="buy">Order this project</h2>
          <?php if ($errors): ?>
            <div class="notice notice--err"><?php foreach ($errors as $er): ?><p><?= e($er) ?></p><?php endforeach; ?></div>
          <?php endif; ?>
          <form method="post" class="wform" id="order">
            <?= Csrf::field() ?>
            <div class="wform__row">
              <label><span>Your name</span>
                <input name="buyer_name" required value="<?= e(old('buyer_name', $me['name'] ?? '')) ?>"></label>
              <label><span>Email</span>
                <input name="buyer_email" type="email" required value="<?= e(old('buyer_email', $me['email'] ?? '')) ?>"></label>
            </div>
            <label><span>Phone <small>optional</small></span>
              <input name="buyer_phone" value="<?= e(old('buyer_phone', $me['phone'] ?? '')) ?>"></label>
            <label><span>Anything we should know? <small>optional</small></span>
              <textarea name="notes" rows="3" placeholder="Branding, changes you need, your timeline…"><?= e(old('notes')) ?></textarea></label>

            <?php if ($methods): ?>
              <fieldset class="paypick">
                <legend>How would you like to pay?</legend>
                <?php foreach ($methods as $i => $mth):
                  $checked = (int)old('payment_method_id', (string)($methods[0]['id'] ?? 0)) === (int)$mth['id']; ?>
                  <label class="paypick__opt">
                    <input type="radio" name="payment_method_id" value="<?= (int)$mth['id'] ?>"<?= $checked ? ' checked' : '' ?> required>
                    <span>
                      <b><?= e($mth['name']) ?>
                        <?php if (Payments::isGateway((string)$mth['provider'])): ?>
                          <i class="paypick__tag">pay online</i>
                        <?php else: ?>
                          <i class="paypick__tag paypick__tag--manual">confirmed by us</i>
                        <?php endif; ?>
                      </b>
                      <?php if ($mth['summary']): ?><em><?= e($mth['summary']) ?></em><?php endif; ?>
                    </span>
                  </label>
                <?php endforeach; ?>
              </fieldset>
            <?php endif; ?>

            <button class="btn btn--primary btn--lg magnetic" type="submit">Place order · <?= e(money($price)) ?> <span class="btn__arrow">→</span></button>
            <p class="wform__note">Nothing is charged on this page. You will see the payment details on the next screen.</p>
          </form>
      </div>

      <aside class="detail__meta reveal">
        <div class="pricebox">
          <span class="pricebox__now"><?= e(money($price)) ?></span>
          <?php if ($p['sale_price'] !== null): ?>
            <span class="pricebox__was"><?= e(money($p['price'])) ?></span>
            <span class="pricebox__off">Save <?= e(money((float)$p['price'] - $price)) ?></span>
          <?php endif; ?>
          <p class="pricebox__note">One-time · full source · installation included</p>
        </div>
        <a class="btn btn--primary btn--block magnetic" href="#buy">Order this project <span class="btn__arrow">→</span></a>
        <?php if ($p['demo_url']): ?>
          <a class="btn btn--ghost btn--block magnetic" href="<?= e($p['demo_url']) ?>" target="_blank" rel="noopener noreferrer">See the demo <span class="btn__arrow">↗</span></a>
        <?php endif; ?>
        <?php $tech = csv_list($p['tech']); if ($tech): ?>
          <h3>Built with</h3>
          <div class="tags"><?php foreach ($tech as $t): ?><span><?= e($t) ?></span><?php endforeach; ?></div>
        <?php endif; ?>
        <?php if ((int)$p['sales_count'] > 0): ?>
          <p class="detail__sold"><?= (int)$p['sales_count'] ?> sold</p>
        <?php endif; ?>
      </aside>
    </div>
  </div>
</article>
<?php require __DIR__ . '/partials/public_footer.php'; ?>
