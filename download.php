<?php
/**
 * Hands a buyer the files they paid for.
 *
 * The link carries a random token tied to one order. Files live in
 * storage/files, which the web server refuses to serve, so this is the only
 * way to reach them and every request is checked first.
 */
require_once __DIR__ . '/app/bootstrap.php';
require_installed();
require_once APP_DIR . '/partials/sections.php';

$token = get('token');
$order = null;

if ($token !== '' && preg_match('/^[a-f0-9]{48}$/', $token)) {
    $order = db_one('SELECT * FROM orders WHERE download_token = ?', [$token]);
}

/** Show a plain page and stop. */
function download_problem(string $heading, string $body): void
{
    global $pageTitle, $pageDesc, $activeNav;
    http_response_code(410);
    $pageTitle = 'Download — ' . setting('site.name', 'TECHBISS');
    $pageDesc  = '';
    $activeNav = 'marketplace';
    include APP_DIR . '/partials/head.php';
    page_head('Download', $heading, '',
        $body,
        [['index.php', 'Home'], ['marketplace.php', 'Marketplace'], [null, 'Download']],
        [['contact.php', 'Get in touch &rarr;']]);
    include APP_DIR . '/partials/footer.php';
    exit;
}

if (!$order) {
    download_problem('Link not', 'recognised.',
        'That download link does not match an order. Check you copied the whole thing from the '
        . 'email, or reply to us and we will send a fresh one.');
}

if ($order['download_expires'] && strtotime((string) $order['download_expires']) < time()) {
    download_problem('Link has', 'expired.',
        'Download links last a limited time for security. Reply to your order email and we will '
        . 'issue a new one straight away — your purchase is still on file.');
}

$product = $order['product_id']
    ? db_one('SELECT * FROM products WHERE id = ?', [$order['product_id']])
    : null;

$path = $product['file_path'] ?? '';
$full = $path ? APP_ROOT . '/' . $path : '';

if (!$path || !is_file($full)) {
    error_log('[techbiss] order ' . $order['reference'] . ' has no file on disk');
    download_problem('Files are not', 'ready yet.',
        'Your order is confirmed but the files are not attached to it yet. That is on us — '
        . 'get in touch and we will sort it out today.');
}

/* --- confirmation page, then the actual file ------------------------- */
if (get('go') !== '1') {
    $pageTitle = 'Your download — ' . setting('site.name', 'TECHBISS');
    $pageDesc  = '';
    $activeNav = 'marketplace';
    $bytes     = filesize($full);
    $sizeMb    = $bytes >= 1048576
        ? number_format($bytes / 1048576, 1) . ' MB'
        : max(1, (int) round($bytes / 1024)) . ' KB';
    $expires   = $order['download_expires'];

    include APP_DIR . '/partials/head.php';
    ?>
    <section class="phead"><div class="wrap">
      <div class="crumbs">
        <a href="index.php">Home</a> <span>/</span>
        <a href="marketplace.php">Marketplace</a> <span>/</span>
        <span>Download</span>
      </div>
      <span class="badge"><i aria-hidden="true"></i>Order <?= esc($order['reference']) ?></span>
      <h1><span class="chrome">Your files</span><br><span class="acc">are ready.</span></h1>
      <p>Thanks <?= esc(explode(' ', $order['buyer_name'])[0]) ?>. Everything for
         <strong><?= esc($product['title']) ?></strong> is in the download below.</p>
      <div class="hr-acts">
        <a class="pill lg" href="download.php?token=<?= urlencode($token) ?>&amp;go=1">
          Download <?= esc($sizeMb) ?> &darr;</a>
      </div>
    </div></section>

    <section><div class="wrap">
      <div class="article">
        <div class="body">
          <h3 class="subhead">What to do with it</h3>
          <ul class="ticks big">
            <li>Unzip it and upload the contents to your hosting.</li>
            <li>Open <code>install.php</code> in a browser and follow the three steps.</li>
            <li>Delete <code>install.php</code> afterwards — it tells you to, and it tries to
                remove itself.</li>
          </ul>
          <p style="margin-top:22px">Stuck at any point, reply to your order email. If you bought
             the setup service we do all of this for you and hand over the logins.</p>
        </div>
        <aside class="side">
          <div class="factbox">
            <h4>Your order</h4>
            <div class="fact"><span>Reference</span><strong><?= esc($order['reference']) ?></strong></div>
            <div class="fact"><span>Project</span><strong><?= esc($product['title']) ?></strong></div>
            <div class="fact"><span>File size</span><strong><?= esc($sizeMb) ?></strong></div>
<?php if ($expires): ?>
            <div class="fact"><span>Link expires</span><strong><?= esc(date_human($expires)) ?></strong></div>
<?php endif; ?>
<?php if ((int) $order['download_count'] > 0): ?>
            <div class="fact"><span>Downloaded</span>
              <strong><?= (int) $order['download_count'] ?> time<?= (int) $order['download_count'] === 1 ? '' : 's' ?></strong></div>
<?php endif; ?>
          </div>
          <div class="factbox">
            <h4>Keep this link</h4>
            <p style="color:var(--mute);font-size:14px">It is in your order email. If it expires
               before you have downloaded the files, just ask and we will reissue it.</p>
          </div>
        </aside>
      </div>
    </div></section>
    <?php
    include APP_DIR . '/partials/footer.php';
    exit;
}

/* --- send the file ---------------------------------------------------- */
db_run('UPDATE orders SET download_count = download_count + 1 WHERE id = ?', [$order['id']]);
log_activity('Downloaded order ' . $order['reference'], 'order', (int) $order['id'],
    $order['buyer_name']);

$filename = download_filename((string) $product['title'], $path);

while (ob_get_level() > 0) {
    ob_end_clean();
}
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($full));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($full);
exit;
