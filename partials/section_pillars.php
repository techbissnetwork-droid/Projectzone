<?php
if (!class_exists('Settings')) { http_response_code(404); exit('Not found.'); }
/** Technology pillars from content_items (kind = pillar). */
$pillars = Content::items('pillar');
if (!$pillars) { return; }
$secNum = $secNum ?? 8;

/**
 * The animated indicator under each pillar. $value is the pillar's own figure,
 * so the gauge reads it rather than showing a number of its own.
 */
function pillar_indicator(string $style, string $value = ''): string
{
    $num = preg_match('/\d+(?:\.\d+)?/', $value, $m) ? $m[0] : '';
    return match ($style) {
        'bars'    => '<div class="bars" aria-hidden="true">' . str_repeat('<i></i>', 8) . '</div>',
        'gauge'   => '<div class="gauge"><svg viewBox="0 0 100 56" aria-hidden="true">'
                   . '<path d="M8 50a42 42 0 0 1 84 0" fill="none" stroke="rgba(255,255,255,.1)" stroke-width="6" stroke-linecap="round"/>'
                   . '<path class="gauge__v" d="M8 50a42 42 0 0 1 84 0" fill="none" stroke="var(--sig)" stroke-width="6" stroke-linecap="round"/>'
                   . '</svg>' . ($num !== '' ? '<b class="mono">' . e($num) . '</b>' : '') . '</div>',
        'ticks'   => '<div class="ticks-row" aria-hidden="true">' . str_repeat('<i></i>', 20) . '</div>',
        'orbits'  => '<div class="orbits" aria-hidden="true"><i></i><i></i><i></i></div>',
        'devices' => '<div class="devices" aria-hidden="true"><i class="d1"></i><i class="d2"></i><i class="d3"></i></div>',
        default   => '<div class="meter"><i style="--v:100%"></i></div>',
    };
}
?>
<section class="trust" data-theme="deep">
  <div class="shell">
    <header class="sec__head sec__head--split">
      <div>
        <p class="eyebrow reveal"><span class="num"><?= sprintf('%02d', $secNum) ?></span> <?= e(Settings::get('pillars_eyebrow')) ?></p>
        <h2 class="sec__title reveal"><?= nl2br(e(Settings::get('pillars_title')), false) ?></h2>
      </div>
      <p class="sec__lede reveal"><?= e(Settings::get('pillars_lede')) ?></p>
    </header>

    <?php /* Four pillars read better as one row of four than as three and a
             stray; --n carries the count, the media queries below override it. */
       $pcols = count($pillars) <= 4 ? max(2, count($pillars)) : 3; ?>
    <div class="trust__grid" style="--n:<?= $pcols ?>">
      <?php foreach ($pillars as $p): ?>
        <?php /* No paragraph means this is a figure, not a description: it is laid
                 out as a stat tile with the value carrying the card. */ ?>
        <article class="pillar<?= $p['body'] ? '' : ' pillar--stat' ?> reveal" data-pillar="<?= e(slugify((string)$p['label'])) ?>">
          <header><h3><?= e($p['label']) ?></h3><i class="dot dot--live"></i></header>
          <?php if ($p['body']): ?>
            <p><?= e($p['body']) ?></p>
            <?= pillar_indicator((string)($p['icon'] ?? 'meter'), (string)$p['label']) ?>
            <?php if ($p['title']): ?><span class="mono"><?= e($p['title']) ?></span><?php endif; ?>
          <?php else: ?>
            <?php /* A figure reads value, then caption, then the indicator as a footnote. */ ?>
            <?php if ($p['title']): ?><span class="mono"><?= e($p['title']) ?></span><?php endif; ?>
            <?= pillar_indicator((string)($p['icon'] ?? 'meter'), (string)$p['label']) ?>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
