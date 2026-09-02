<?php
if (!class_exists('Settings')) { http_response_code(404); exit('Not found.'); }

/**
 * Summary versions of the heavy sections. The landing page shows these and
 * links to the full thing; each full version lives on its own page.
 */
function compact_block(string $kind, int $num): void
{
    switch ($kind) {

        /* ── offline → online ─────────────────────────────── */
        case 'journey':
            $rows = Content::items('journey');
            if (!$rows) { return; } ?>
            <section class="brief brief--journey" data-theme="deep">
              <div class="shell">
                <header class="brief__head">
                  <p class="eyebrow reveal"><span class="num"><?= sprintf('%02d', $num) ?></span> <?= e(Settings::get('journey_eyebrow')) ?></p>
                  <h2 class="sec__title reveal">From a business in one place<br>to one that runs everywhere.</h2>
                </header>
                <ol class="ribbon reveal">
                  <?php foreach ($rows as $i => $r): ?>
                    <li>
                      <b><?= e($r['label']) ?></b>
                      <span><?= e(excerpt($r['title'], 58)) ?></span>
                    </li>
                  <?php endforeach; ?>
                </ol>
                <p class="brief__more reveal">
                  <a class="link" href="<?= e(url('transformation.php')) ?>">See the whole transformation <span aria-hidden="true">→</span></a>
                </p>
              </div>
            </section>
            <?php break;

        /* ── system architecture ──────────────────────────── */
        case 'arch':
            $nodes = Content::items('arch');
            if (!$nodes) { return; }
            $layers = [[], [], [], []];
            foreach ($nodes as $n) {
                $layers[max(0, min(3, (int)$n['meta1']))][] = $n;
            } ?>
            <section class="brief brief--arch">
              <div class="shell brief__split">
                <div>
                  <p class="eyebrow reveal"><span class="num"><?= sprintf('%02d', $num) ?></span> <?= e(Settings::get('arch_eyebrow')) ?></p>
                  <h2 class="sec__title reveal"><?= nl2br(e(Settings::get('arch_title')), false) ?></h2>
                  <p class="sec__lede reveal"><?= e(Settings::get('arch_lede')) ?></p>
                  <p class="brief__more reveal">
                    <a class="link" href="<?= e(url('technology.php')) ?>">See how it fits together <span aria-hidden="true">→</span></a></p>
                </div>
                <div class="stack-viz reveal" aria-hidden="true">
                  <?php foreach ($layers as $li => $row):
                    if (!$row) { continue; } ?>
                    <div class="stack-viz__row" data-layer="<?= $li ?>">
                      <?php foreach ($row as $n): ?><span><?= e($n['label']) ?></span><?php endforeach; ?>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            </section>
            <?php break;

        /* ── how we work ──────────────────────────────────── */
        case 'process':
            $steps = Content::items('process');
            if (!$steps) { return; } ?>
            <section class="brief brief--process" data-theme="deep">
              <div class="shell">
                <header class="brief__head">
                  <p class="eyebrow reveal"><span class="num"><?= sprintf('%02d', $num) ?></span> <?= e(Settings::get('process_eyebrow')) ?></p>
                  <h2 class="sec__title reveal"><?= e(Settings::get('process_title')) ?></h2>
                </header>
                <ol class="steprow reveal">
                  <?php foreach ($steps as $i => $s): ?>
                    <li>
                      <i class="mono"><?= sprintf('%02d', $i + 1) ?></i>
                      <b><?= e($s['label']) ?></b>
                      <?php if ($s['title']): ?><span class="mono"><?= e($s['title']) ?></span><?php endif; ?>
                    </li>
                  <?php endforeach; ?>
                </ol>
                <p class="brief__more reveal">
                  <a class="link" href="<?= e(url('process.php')) ?>">Walk through each stage <span aria-hidden="true">→</span></a></p>
              </div>
            </section>
            <?php break;

        /* ── business transformations ─────────────────────── */
        case 'transform':
            $rows = Content::items('transform');
            if (!$rows) { return; }
            $tl = explode("\n", Settings::get('transform_title')); ?>
            <section class="brief brief--transform">
              <div class="shell">
                <header class="brief__head brief__head--center">
                  <p class="eyebrow reveal"><span class="num"><?= sprintf('%02d', $num) ?></span> <?= e(Settings::get('transform_eyebrow')) ?></p>
                  <h2 class="sec__title reveal"><?= e(trim($tl[0])) ?>
                    <?php if (isset($tl[1])): ?><br><span class="grad"><?= e(trim($tl[1])) ?></span><?php endif; ?></h2>
                </header>
                <ul class="swap reveal">
                  <?php foreach ($rows as $r): ?>
                    <li><b><?= e($r['label']) ?></b><i aria-hidden="true">→</i><span><?= e($r['title']) ?></span></li>
                  <?php endforeach; ?>
                </ul>
                <p class="brief__more brief__more--center reveal">
                  <a class="link" href="<?= e(url('transformation.php#types')) ?>">See what each one includes <span aria-hidden="true">→</span></a></p>
              </div>
            </section>
            <?php break;

        /* ── technology pillars ───────────────────────────── */
        case 'pillars':
            $rows = Content::items('pillar');
            if (!$rows) { return; } ?>
            <section class="brief brief--pillars" data-theme="deep">
              <div class="shell brief__split">
                <div>
                  <p class="eyebrow reveal"><span class="num"><?= sprintf('%02d', $num) ?></span> <?= e(Settings::get('pillars_eyebrow')) ?></p>
                  <h2 class="sec__title reveal"><?= nl2br(e(Settings::get('pillars_title')), false) ?></h2>
                  <p class="brief__more reveal">
                    <a class="link" href="<?= e(url('technology.php#pillars')) ?>">What we monitor for you <span aria-hidden="true">→</span></a></p>
                </div>
                <ul class="chiprow reveal">
                  <?php foreach ($rows as $r): ?>
                    <li><i class="dot dot--live"></i><b><?= e($r['label']) ?></b><span><?= e(excerpt($r['title'], 34)) ?></span></li>
                  <?php endforeach; ?>
                </ul>
              </div>
            </section>
            <?php break;
    }
}
