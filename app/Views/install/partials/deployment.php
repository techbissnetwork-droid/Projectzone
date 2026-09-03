<?php /** @var array $snippets @var string $url */ ?>
<h2 class="h5">Post-install checklist</h2>
<div class="checklist mt-3">
  <?php foreach ([
    ['Installer locked', 'storage/install.lock exists, so /install redirects away.', 'pass'],
    ['Force HTTPS', 'Redirect HTTP to HTTPS at the web server or load balancer.', 'warn'],
    ['Cache headers', 'Fingerprinted assets immutable for one year; HTML revalidated.', 'warn'],
    ['Point monitoring at /health', 'Returns version, database connectivity and cache state as JSON.', 'warn'],
    ['Schedule the cache sweep', 'A nightly cron keeps the filesystem cache tidy.', 'warn'],
    ['Test a restore', 'A backup you have never restored is a hypothesis, not a backup.', 'warn'],
  ] as $row): ?>
    <div class="checkrow checkrow--<?= e($row[2]) ?>">
      <span class="checkrow__icon"><?= icon($row[2] === 'pass' ? 'check' : 'alert') ?></span>
      <span><strong><?= e($row[0]) ?></strong><span><?= e($row[1]) ?></span></span>
    </div>
  <?php endforeach; ?>
</div>

<h2 class="h5 mt-7">Apache</h2>
<div class="codeblock mt-3" id="snippet-apache"><?= e($snippets['apache']) ?></div>
<button type="button" class="btn btn--sm btn--ghost mt-3" data-copy="snippet-apache">Copy Apache config</button>

<h2 class="h5 mt-7">nginx</h2>
<div class="codeblock mt-3" id="snippet-nginx"><?= e($snippets['nginx']) ?></div>
<button type="button" class="btn btn--sm btn--ghost mt-3" data-copy="snippet-nginx">Copy nginx config</button>

<h2 class="h5 mt-7">Cron</h2>
<div class="codeblock mt-3" id="snippet-cron"><?= e($snippets['cron']) ?></div>
<button type="button" class="btn btn--sm btn--ghost mt-3" data-copy="snippet-cron">Copy cron entry</button>

<h2 class="h5 mt-7">Verify</h2>
<div class="codeblock mt-3"><span class="c"># Platform and database state</span>
curl -s <?= e(rtrim($url, '/')) ?>/health | php -r 'echo json_encode(json_decode(file_get_contents("php://stdin")), 128);'

<span class="c"># Or from the server</span>
php bin/techbiss health</div>
