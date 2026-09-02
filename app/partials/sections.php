<?php
/** Reusable page pieces, so no template repeats this markup. */

/** The tall hero used on every inner page. */
function page_head(string $eyebrow, string $line1, string $line2, string $lead, array $crumbs = [], array $actions = []): void
{
    ?>
<section class="phead"><div class="wrap">
<?php if ($crumbs): ?>
  <div class="crumbs">
<?php   $last = array_key_last($crumbs);
        foreach ($crumbs as $i => [$href, $label]): ?>
    <?php if ($href && $i !== $last): ?><a href="<?= esc($href) ?>"><?= esc($label) ?></a> <span>/</span>
    <?php else: ?><span><?= esc($label) ?></span><?php endif; ?>
<?php   endforeach; ?>
  </div>
<?php endif; ?>
  <span class="badge"><i aria-hidden="true"></i><?= esc($eyebrow) ?></span>
  <h1><span class="chrome"><?= esc($line1) ?></span><br><span class="acc"><?= esc($line2) ?></span></h1>
<?php if ($lead): ?>
  <p><?= esc($lead) ?></p>
<?php endif; ?>
<?php if ($actions): ?>
  <div class="hr-acts">
<?php   foreach ($actions as $i => [$href, $label]): ?>
    <a class="pill<?= $i ? ' ghost' : '' ?>" href="<?= esc($href) ?>"><?= $label ?></a>
<?php   endforeach; ?>
  </div>
<?php endif; ?>
</div></section>
<?php
}

/** The scrolling word strip. */
function runner_strip(): void
{
    $words = setting_lines('marquee.words', ['Websites', 'Apps', 'Domains', 'Hosting', 'SSL']);
    ?>
<div class="runner" aria-hidden="true"><div class="t" id="runner">
<?php foreach ($words as $w): ?><span><?= esc($w) ?></span><?php endforeach; ?>
</div></div>
<?php
}

/** The closing call to action. */
function closing_cta(string $heading, string $body, array $primary, ?array $secondary = null): void
{
    ?>
<section><div class="wrap">
  <div class="close rv">
    <h2 class="chrome"><?= esc($heading) ?></h2>
    <p><?= esc($body) ?></p>
    <div class="acts">
      <a class="pill lg" href="<?= esc($primary[0]) ?>"><?= $primary[1] ?></a>
<?php if ($secondary): ?>
      <a class="pill ghost lg" href="<?= esc($secondary[0]) ?>"><?= $secondary[1] ?></a>
<?php endif; ?>
    </div>
  </div>
</div></section>
<?php
}

/** A big statement line with {braces} highlighted. */
function statement(string $text): void
{
    if (trim($text) === '') {
        return;
    }
    ?>
<section class="tight"><div class="wrap">
  <p class="say rv"><?= highlighted($text) ?></p>
</div></section>
<?php
}

/** The FAQ accordion. */
function faq_block(string $page, string $heading, string $sub = ''): void
{
    $faqs = faqs_for($page);
    if (!$faqs) {
        return;
    }
    ?>
<section><div class="wrap">
  <div class="sh rv"><span class="no">Questions</span>
    <h2><?= esc($heading) ?></h2>
<?php if ($sub): ?>    <p><?= esc($sub) ?></p><?php endif; ?>
  </div>
  <div class="acc rv">
<?php foreach ($faqs as $i => $f): ?>
    <div class="item">
      <button class="q" type="button" aria-expanded="<?= $i === 0 ? 'true' : 'false' ?>">
        <?= esc($f['question']) ?><span class="pm" aria-hidden="true">+</span></button>
      <div class="a"><p><?= esc($f['answer']) ?></p></div>
    </div>
<?php endforeach; ?>
  </div>
</div></section>
<?php
}

/** One portfolio card. */
function portfolio_card(array $p, bool $showBadge = false): void
{
    ?>
  <article class="card work rv">
<?php if ($p['cover_image']): ?>
    <div class="shot"><img src="<?= esc($p['cover_image']) ?>" alt="" loading="lazy"></div>
<?php else: ?>
    <div class="shot placeholder" aria-hidden="true"><span><?= esc(strtoupper(substr($p['title'], 0, 2))) ?></span></div>
<?php endif; ?>
    <div class="meta-row">
      <span class="no"><?= esc($p['sector'] ?: 'Project') ?></span>
<?php if ($showBadge && $p['visibility'] !== 'public'): ?>
      <span class="pilltag private">Admin only</span>
<?php endif; ?>
    </div>
    <h3><?= esc($p['title']) ?></h3>
    <p><?= esc($p['summary']) ?></p>
    <div class="tagrow">
<?php foreach (array_slice(lines($p['services_used']), 0, 4) as $s): ?>
      <b><?= esc($s) ?></b>
<?php endforeach; ?>
    </div>
    <a class="stretch" href="project.php?slug=<?= urlencode($p['slug']) ?>">
      <span class="sr">Read about <?= esc($p['title']) ?></span></a>
  </article>
<?php
}

/** One marketplace card. */
function product_card(array $p): void
{
    $price   = product_price($p);
    $onSale  = product_on_sale($p);
    $symbol  = setting('site.currency', '$');
    ?>
  <article class="card product rv">
<?php if ($p['cover_image']): ?>
    <div class="shot"><img src="<?= esc($p['cover_image']) ?>" alt="" loading="lazy"></div>
<?php else: ?>
    <div class="shot placeholder" aria-hidden="true"><span><?= esc(strtoupper(substr($p['title'], 0, 2))) ?></span></div>
<?php endif; ?>
    <div class="meta-row">
      <span class="no"><?= esc($p['category'] ?: 'Project') ?></span>
<?php if ($onSale): ?>      <span class="pilltag sale">On sale</span><?php endif; ?>
    </div>
    <h3><?= esc($p['title']) ?></h3>
    <p><?= esc($p['summary']) ?></p>
    <div class="priceline">
      <strong><?= esc(money($price, $symbol)) ?></strong>
<?php if ($onSale): ?>      <s><?= esc(money($p['price'], $symbol)) ?></s><?php endif; ?>
<?php if ((int) $p['pages'] > 0): ?>      <span><?= (int) $p['pages'] ?> pages</span><?php endif; ?>
    </div>
    <a class="stretch" href="product.php?slug=<?= urlencode($p['slug']) ?>">
      <span class="sr">See <?= esc($p['title']) ?></span></a>
  </article>
<?php
}
