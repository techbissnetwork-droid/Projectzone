<?php
if (!class_exists('Settings')) { http_response_code(404); exit('Not found.'); }
/** How we work. Stages come from content_items (kind = process). */
$steps = Content::items('process');
if (!$steps) { return; }
$secNum = $secNum ?? 5;
?>
<!-- ══════════════ 05 · PROCESS ══════════════ -->
<section class="flowsec flowsec--proc" id="process">
  <div class="shell">
    <header class="sec__head sec__head--split">
      <div>
        <p class="eyebrow reveal"><span class="num"><?= sprintf('%02d', $secNum) ?></span> <?= e(Settings::get('process_eyebrow')) ?></p>
        <h2 class="sec__title reveal"><?= nl2br(e(Settings::get('process_title')), false) ?></h2>
      </div>
      <p class="sec__lede reveal"><?= e(Settings::get('process_lede')) ?></p>
    </header>

    <div class="carousel proc__carousel reveal" data-carousel="Stage"
         style="<?= e(slide_vars(Settings::get('slides_process'), Settings::get('slides_process_phone'), '4x1', '4x1')) ?>">
      <ol class="carousel__rail" data-rail>
      <?php foreach ($steps as $si => $st): ?>
        <li class="flow__step">
          <div class="flow__mark" aria-hidden="true"><span class="flow__num mono"><?= sprintf('%02d', $si + 1) ?></span></div>
          <?php if ($st['title']): ?><span class="flow__tag mono"><?= e($st['title']) ?></span><?php endif; ?>
          <h3 class="flow__name"><?= e($st['label']) ?></h3>
          <?php if ($st['body']): ?><p class="flow__text"><?= e($st['body']) ?></p><?php endif; ?>
          <?php $ticks = lines($st['extra']); if ($ticks): ?>
            <ul class="ticks flow__ticks"><?php foreach (array_slice($ticks, 0, 3) as $t): ?><li><?= e($t) ?></li><?php endforeach; ?></ul>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
      </ol>
      <?php require __DIR__ . '/carousel_nav.php'; ?>
    </div>
  </div>
</section>
