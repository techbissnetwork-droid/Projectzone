<?php
if (!class_exists('Settings')) { http_response_code(404); exit('Not found.'); }
/** How we work. Stages come from content_items (kind = process). */
$steps = Content::items('process');
if (!$steps) { return; }
$secNum = $secNum ?? 5;
?>
<!-- ══════════════ 05 · PROCESS ══════════════ -->
<section class="proc" id="process">
  <div class="proc__track" id="procTrack">
    <div class="proc__stage">
      <div class="proc__glow" aria-hidden="true"></div>
      <div class="shell proc__inner">

        <header class="proc__head">
          <p class="eyebrow"><span class="num"><?= sprintf('%02d', $secNum) ?></span> <?= e(Settings::get('process_eyebrow')) ?></p>
          <h2 class="proc__title"><?= e(Settings::get('process_title')) ?></h2>
          <div class="proc__meter" aria-hidden="true"><i id="procBar"></i></div>
          <span class="proc__count mono"><b id="procNow">01</b> / <?= sprintf('%02d', count($steps)) ?></span>
        </header>

        <ol class="steps" id="steps">
          <?php foreach ($steps as $si => $st): ?>
            <li class="step<?= $si === 0 ? ' is-active' : '' ?>" data-step="<?= $si ?>">
              <div class="step__row">
                <span class="step__num mono"><?= sprintf('%02d', $si + 1) ?></span>
                <h3 class="step__name"><?= e($st['label']) ?></h3>
                <?php if ($st['title']): ?><span class="step__tag mono"><?= e($st['title']) ?></span><?php endif; ?>
              </div>
              <div class="step__open"><div class="step__openIn">
                <?php if ($st['body']): ?><p><?= e($st['body']) ?></p><?php endif; ?>
                <?php $ticks = lines($st['extra']); if ($ticks): ?>
                  <ul class="ticks"><?php foreach ($ticks as $t): ?><li><?= e($t) ?></li><?php endforeach; ?></ul>
                <?php endif; ?>
              </div></div>
            </li>
          <?php endforeach; ?>
        </ol>

        <div class="proc__visual" id="procVisual" aria-hidden="true">
          <?php
          $graphics = [
            '<div class="pv__radar"><i></i><i></i><i></i><span></span></div>',
            '<div class="pv__frames"><i></i><i></i><i></i><i></i></div>',
            '<div class="pv__code"><span></span><span></span><span></span><span></span><span></span><span></span></div>',
            '<div class="pv__launch"><i></i><i></i><i></i><b>DNS · SSL · MX</b></div>',
            '<div class="pv__growth"><i></i><i></i><i></i><i></i><i></i><i></i></div>',
          ];
          foreach ($steps as $si => $st): ?>
            <div class="pv" data-pv="<?= $si ?>">
              <?= $graphics[$si % count($graphics)] ?>
              <p class="mono"><?= e($st['meta1'] ?: $st['label']) ?></p>
            </div>
          <?php endforeach; ?>
        </div>
        </div>

      </div>
    </div>
  </div>
</section>
