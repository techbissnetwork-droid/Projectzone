<?php
/** Sections that appear on more than one page, so the markup lives once. */

function section_statement(string $key): void
{
    $text = txt($key);
    if ($text === '') {
        return;
    }
    ?>
    <section class="statement">
      <div class="wrap">
        <p id="statementText"><?= statement_html($text) ?></p>
      </div>
    </section>
    <?php
}

function section_cta(string $prefix): void
{
    ?>
    <section class="ctaband">
      <div class="wrap">
        <div class="ctabox reveal">
          <div>
            <h3><?= e(txt($prefix . '.cta.heading')) ?></h3>
            <p><?= e(txt($prefix . '.cta.body')) ?></p>
          </div>
          <a class="btn btn--primary magnetic" href="<?= e(base_url('contact.php')) ?>"><?= e(txt($prefix . '.cta.button', 'Talk to us →')) ?></a>
        </div>
      </div>
    </section>
    <?php
}

function section_faq(string $page, string $headingKey, string $subKey): void
{
    $items = all(
        'SELECT * FROM faqs WHERE page = :p AND is_active = 1 ORDER BY sort ASC, id ASC',
        ['p' => $page]
    );
    if (!$items) {
        return;
    }
    ?>
    <section class="sec">
      <div class="wrap">
        <div class="sec-head reveal">
          <h2><?= e(txt($headingKey)) ?></h2>
          <p><?= e(txt($subKey)) ?></p>
        </div>
        <div class="faq reveal">
          <?php foreach ($items as $f): ?>
            <details>
              <summary><?= e($f['question']) ?></summary>
              <p><?= nl2br(e($f['answer'])) ?></p>
            </details>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
    <?php
}

function section_stats(): void
{
    $items = rows('stats');
    if (!$items) {
        return;
    }
    ?>
    <section class="sec sec--line">
      <div class="wrap">
        <div class="stats reveal">
          <?php foreach ($items as $s): ?>
            <div class="stat">
              <p class="stat__n"><span data-count="<?= e($s['value']) ?>" data-suffix="<?= e($s['suffix']) ?>">0</span></p>
              <p class="stat__l"><?= e($s['label']) ?></p>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
    <?php
}

function section_quotes(string $headingKey, string $subKey): void
{
    $items = rows('testimonials');
    if (!$items) {
        return;
    }
    ?>
    <section class="sec">
      <div class="wrap">
        <div class="sec-head reveal">
          <h2><?= e(txt($headingKey)) ?></h2>
          <p><?= e(txt($subKey)) ?></p>
        </div>
        <div class="quotes reveal">
          <?php foreach ($items as $t): ?>
            <div class="quote">
              <p>&ldquo;<?= e($t['quote']) ?>&rdquo;</p>
              <footer>
                <span class="quote__av"><?= e($t['avatar'] ?: initials($t['name'])) ?></span>
                <span class="quote__who"><b><?= e($t['name']) ?></b><?= e($t['role']) ?></span>
              </footer>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
    <?php
}

/** The little terminal-style panel used beside feature rows. */
function panel_html(?string $title, ?string $rowsText): void
{
    $rows = panel_rows($rowsText);
    if (!$rows) {
        return;
    }
    $last = array_key_last($rows);
    ?>
    <div class="panel">
      <div class="panel__bar"><i></i><i></i><i></i><span><?= e($title ?: 'STATUS') ?></span></div>
      <div class="panel__body">
        <?php foreach ($rows as $i => $r): ?>
          <div class="panel__line">
            <span><?= e($r['label']) ?></span>
            <?php if ($i === $last): ?><em><?= e($r['value']) ?></em><?php else: ?><b><?= e($r['value']) ?></b><?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php
}

/** Feature row: text on one side, panel on the other, alternating sides. */
function feature_row(array $d, bool $flip): void
{
    ?>
    <div class="frow<?= $flip ? ' frow--flip' : '' ?> reveal"<?= !empty($d['anchor']) ? ' id="' . e($d['anchor']) . '"' : '' ?>>
      <div>
        <?php if (!empty($d['kicker'])): ?><p class="frow__kicker"><?= e($d['kicker']) ?></p><?php endif; ?>
        <h3><?= e($d['heading'] ?: $d['title'] ?? '') ?></h3>
        <?php if (!empty($d['body'])): ?><p><?= e($d['body']) ?></p><?php endif; ?>
        <?php $bul = lines($d['bullets'] ?? ''); if ($bul): ?>
          <ul class="checks">
            <?php foreach ($bul as $b): ?><li><?= e($b) ?></li><?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
      <div class="frow__media">
        <?php panel_html($d['panel_title'] ?? '', $d['panel'] ?? ''); ?>
      </div>
    </div>
    <?php
}
