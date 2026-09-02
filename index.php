<?php
declare(strict_types=1);

/**
 * Homepage. When the landing page is enabled (Admin > Settings) this renders
 * the marketing hero; otherwise it forwards straight to the live charts.
 */

$config = require __DIR__ . '/src/bootstrap.php';

use SignalMasterAi\Database;
use SignalMasterAi\MemberAuth;

MemberAuth::start();
$member = MemberAuth::current();

// One place learns the site's own URL - see View::learnSiteUrl().
\SignalMasterAi\View::learnSiteUrl();

// Logged-in members go straight to their charts; guests see the landing.
if ($member !== null || sma_setting('landing_enabled', '1') !== '1') {
    header('Location: charts.php');
    exit;
}

$siteName = sma_setting('site_name', $config['app_name']);
$tagline  = sma_setting('site_tagline', $config['app_tagline']);
$notice   = sma_setting('site_notice');
$heroTitle = sma_setting('hero_title', 'AI-Powered Signals, Smarter Trading');
$heroSub   = sma_setting('hero_subtitle', '');
$heroCta   = sma_setting('hero_cta', 'View live signals');

// THE ONE BRASS WORD, WITHOUT TRUSTING RAW HTML FROM A SETTING.
//
// The type system's signature move is one word of the headline in italic
// brass - "earned status" colour, used nowhere else on the marketing site.
// hero_title is admin-editable and correctly passed through sma_e(), so it
// cannot carry a literal <em> tag; putting one in the stored string would
// either be stripped by the escaping (silently losing the effect) or, if
// escaping were ever loosened here to allow it, would turn a plain-text
// settings field into an HTML injection point for anyone with access to it.
//
// Split instead: the LAST word of the title is emphasised automatically,
// server-side, from plain text. Works for any headline an operator writes,
// including the default, and there is no path from the database to markup.
$heroWords = explode(' ', trim($heroTitle));
$heroLast  = array_pop($heroWords);
$heroLead  = implode(' ', $heroWords);

$hex = fn(string $k, string $d) => preg_match('/^#[0-9a-f]{6}$/i', $v = sma_setting($k, $d)) ? $v : $d;
$accent = $hex('accent_color', \SignalMasterAi\View::BRAND_DEFAULTS['accent_color']);
$upCol  = $hex('up_color', \SignalMasterAi\View::BRAND_DEFAULTS['up_color']);
$downCol= $hex('down_color', \SignalMasterAi\View::BRAND_DEFAULTS['down_color']);

$pdo = Database::pdo();
$stats = [
    'coins'   => (int)$pdo->query('SELECT COUNT(*) FROM symbols WHERE enabled = 1')->fetchColumn(),
    // BUY/SELL calls only. Counting every stored row put NEUTRAL verdicts in
    // the headline - most of what the engine writes - and sat that inflated
    // number next to a verified win rate drawn from a far smaller set, which
    // invites the reader to think all of them were scored. Each figure was
    // true; the pair was not.
    // ...and the same argument finishes the job. Dropping NEUTRAL fixed half
    // of it and left the other half standing: this still counted signals on
    // coins hidden from the record, on timeframes the site does not run, and
    // below the publish grade - none of which anyone was shown. So the card
    // still put an unfiltered total beside a published win rate, which on this
    // install read "254 of those 278" where the honest denominator is 268.
    // Same population on both halves of the sentence now.
    'signals' => \SignalMasterAi\TrackRecord::publishedCount(),
    'rules'   => (int)$pdo->query('SELECT COUNT(*) FROM ta_knowledge WHERE enabled = 1')->fetchColumn(),
];
// Counted the same way as the track record, and now literally by the same
// code - the comment used to make that claim while the query underneath
// omitted the visibility filter entirely, so the landing page advertised a
// win rate the track record it linked to did not agree with.
$trackStats = \SignalMasterAi\TrackRecord::stats();
$track = ['t' => $trackStats['total'], 'w' => $trackStats['wins']];
$plans = $pdo->query('SELECT * FROM plans WHERE enabled = 1 ORDER BY sort, days')->fetchAll();
$paidCoins = (int)$pdo->query("SELECT COUNT(*) FROM symbols WHERE enabled = 1 AND tier = 'paid'")->fetchColumn();

$base = \SignalMasterAi\View::baseUrl();
?>
<?php
// SEO overrides stay page-level; the shell itself is shared so the two public
// pages cannot drift apart again the way they did with the nav script.
$seoTitle = sma_setting('seo_title') !== '' ? sma_setting('seo_title') : $siteName . ' — ' . $tagline;
$seoDesc  = sma_setting('meta_description') !== '' ? sma_setting('meta_description')
          : ($heroSub !== '' ? $heroSub : $tagline);
ob_start(); ?>
/* THIS BLOCK IS WRITTEN AFTER style.css AND THEREFORE BEATS IT.
 *
 * That is why the landing page kept its old look while every other page
 * changed: the component layer in the stylesheet never got a chance here.
 * Anything below that duplicates a component is a rule that silently wins,
 * so the duplicates are gone and only the page's own layout is left.
 */
.hero-copy h1 {
  /* Sans now, matching the body copy directly beneath it and every other
     headline weight in the product (dashboard, tables, cards). This one used
     to be the lone serif accent (--font-display, Instrument Serif) against a
     page that is sans-serif everywhere else - a decorative flourish here,
     not a headline. One solid colour, no gradient clip: that trick is the
     house style of every SaaS landing page of the last five years, and it
     smears on any low-DPI screen since subpixel antialiasing turns off the
     moment text becomes a background clip. */
  font-family: var(--font-sans);
  /* Editorial scale, sized for .hero-copy's own 720px box - it no longer
     races an instrument panel for room beside it, so it can sit at the
     largest a centred single block of copy comfortably carries. */
  font-size: clamp(32px, 5.2vw, 56px); font-weight: 600;
  line-height: 1.15; letter-spacing: -.02em;
  color: var(--text); background: none; -webkit-background-clip: border-box; background-clip: border-box;
}
.hero-copy h1 em { font-style: normal; color: var(--brass); }
.hero-copy p.sub { color: var(--muted); font-size: clamp(14px, 1.6vw, 16px); line-height: 1.7; max-width: 560px; margin: 16px auto 0; }
/* .hero-split used to lay the copy out beside a live instrument panel
   pulled straight from the signals table - real symbol, real entry/stop/
   targets, on the public marketing page. Removed: a real, in-force trade
   plan is not something to publish outside the product itself. The copy
   is now the only thing in the split (.hero-copy, below, is styled
   accordingly), and the class name stays only because renaming it here
   would touch nothing but the selector text. */
.hero-split {
  position: relative; isolation: isolate;
  display: flex; flex-wrap: wrap; gap: 44px; align-items: center;
  max-width: 1180px; margin: 0 auto; padding: 72px 22px 32px;
}
/* COLOR, NOT CHROME. The copy asked to lose its box; the page still needed
   somewhere to put colour, or the hero reads as the same grey text on black
   every panel-less hero does. A soft two-tone glow sitting behind the copy -
   never a border, never a fill anything sits inside - does the job instead:
   it's the accent and brass the rest of the product already uses, blurred
   into light rather than drawn as shapes, isolated to its own stacking
   context so it can't ever paint over the text or the buttons above it. */
.hero-split::before {
  content: ""; position: absolute; z-index: -1; inset: -18% -8% -30%;
  background:
    radial-gradient(46% 60% at 18% 22%, color-mix(in srgb, var(--accent) 28%, transparent), transparent 68%),
    radial-gradient(38% 50% at 82% 68%, color-mix(in srgb, var(--brass) 20%, transparent), transparent 70%);
  filter: blur(60px);
  opacity: .8;
  pointer-events: none;
}
@media (max-width: 720px) {
  .hero-split::before { opacity: .55; filter: blur(40px); }
}
/* The copy used to share the split with a live instrument panel (flex:
   1.05 1 420px, left-aligned, sized to leave room beside it). With that
   panel gone, .hero-copy is always the only thing in .hero-split, so it
   gets the single well-proportioned centred block that used to be a
   :only-child special case for a quiet cycle - now just how the hero
   looks. */
.hero-copy { flex: 0 1 720px; min-width: 0; max-width: 720px; margin: 0 auto; text-align: center; }
/* THE MISSING LABEL. This class had no rule anywhere in the stylesheet or
   this page - "AI-generated signals" was rendering as a plain,
   unstyled paragraph, the same weight and colour as body text, sitting
   directly above a headline of a completely different scale with nothing
   to mark it as a kicker rather than a stray sentence. Every other small
   caps label in the system (.trust-row span) is mono,
   letter-spaced and uppercase; this one gets the same treatment plus the
   accent colour, so it reads as what it is - a category tag for the
   headline, not a first draft of the sentence below it. */
.hero-copy .eyebrow {
  display: inline-flex; align-items: center; gap: 8px;
  margin: 0 0 18px; padding: 6px 14px 6px 11px; border-radius: 999px;
  font-family: var(--font-mono); font-size: 11px;
  font-weight: 500; letter-spacing: .12em; text-transform: uppercase;
  color: var(--accent);
  background: var(--accent-soft);
  border: 1px solid color-mix(in srgb, var(--accent) 32%, transparent);
}
/* The dot reads as "live signal" - the same small piece of vocabulary the
   trust-row ticks below already use, borrowed here instead of inventing a
   second one. */
.hero-copy .eyebrow::before {
  content: ""; width: 6px; height: 6px; border-radius: 50%; flex: none;
  background: var(--accent);
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--accent) 22%, transparent);
}
.hero-ctas { display: flex; gap: 12px; flex-wrap: wrap; justify-content: center; margin-top: 24px; }
/* The one glow the copy gets: a soft accent shadow under the filled button,
   not the flat drop-shadow every bordered element on the page already casts.
   It says "press this one" in colour instead of in a second sentence. */
.hero-ctas .cta-main {
  box-shadow: 0 10px 26px -10px color-mix(in srgb, var(--accent) 60%, transparent);
}
.trust-row { display: flex; gap: 22px; flex-wrap: wrap; justify-content: center; margin-top: 26px;
  padding-top: 20px; border-top: 1px solid var(--hair); }
.trust-row b { display: block; font-family: var(--font-mono); font-variant-numeric: tabular-nums;
  font-size: 22px; font-weight: 500; letter-spacing: -.02em; }
.trust-row > div { flex: 0 0 auto; min-width: 0; position: relative; padding-left: 14px; }
/* One coloured tick per stat, borrowed from the same palette the product
   already assigns meaning to elsewhere (live/up, accent, earned/brass) -
   rather than three identical grey numbers with nothing to tell them apart
   at a glance. */
.trust-row > div::before {
  content: ""; position: absolute; left: 0; top: 3px; width: 3px; height: 30px;
  border-radius: 3px; background: var(--up);
}
.trust-row > div:nth-child(2)::before { background: var(--accent); }
.trust-row > div:nth-child(3)::before { background: var(--brass); }
.trust-row > div:nth-child(4)::before { background: var(--up); }
/* This class had no colour rule anywhere - the verified win-rate figure was
   rendering in the same flat white as every other number in the row instead
   of the up/green every other win-rate on the site is shown in. */
.trust-row b.up { color: var(--up); }
/* display:block, or four uppercase mono labels sit inline and read as one
   run of text - "COINS TRACKEDGRADED SIGNALSANALYSIS RULES". */
.trust-row span { display: block; font-family: var(--font-mono); font-size: 11px;
  letter-spacing: .13em; text-transform: uppercase; color: var(--dim); white-space: nowrap; }

/* Spec breakpoint for marketing is 900px. */
@media (max-width: 900px) {
  .hero-split { gap: 18px; padding: 40px 18px 24px; align-items: normal; }
  .trust-row { gap: 18px; margin-top: 24px; }
  .trust-row b { font-size: 19px; }
}
/* .cta-main and .cta-ghost are drawn by the component layer now. The rules
   that were here set their own gradient, their own glow and their own weight,
   so the primary button on the landing page was a different object from the
   primary button everywhere else. Only the size difference stays. */
.cta-main, .cta-ghost { padding: 14px 30px; font-size: 15px; border-radius: 12px; }
/* .hero-stats/.hstat removed - dead rules, never emitted by any markup on
   this page. The row that IS on the page (four figures under the CTAs) is
   .trust-row, styled above; this was an older stat-pill layout that never
   got cleaned up when .trust-row replaced it. */
.sec { max-width: 1100px; margin: 0 auto; padding: 34px 22px; }
.sec h2 { text-align: center; font-size: 26px; margin-bottom: 8px; letter-spacing: -0.4px; }
.sec p.lead { text-align: center; color: var(--muted); font-size: 14px; max-width: 640px; margin: 0 auto 30px; }
.feat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 14px; }
.feat { background: var(--surface); border: 1px solid var(--border); border-radius: 14px; padding: 20px; transition: transform 0.15s ease, border-color 0.15s ease; }
.feat:hover { transform: translateY(-3px); border-color: color-mix(in srgb, var(--accent) 45%, var(--border)); }
/* The icon container: an accent-tinted rounded square holding a thin-stroke
   SVG, replacing six emoji. Emoji render as whatever the visiting device's
   own font ships - a different glyph shape and colour on every OS, none of
   them drawn by anyone who works on this product, and exactly the kind of
   decoration the rest of the redesign spent this whole conversation
   removing. The palette gets a say now: indigo, on a soft indigo ground. */
.feat .ic {
  width: 40px; height: 40px; border-radius: 10px;
  background: var(--accent-soft); color: var(--accent);
  display: flex; align-items: center; justify-content: center;
}
.feat .ic-svg { width: 20px; height: 20px; }
.feat h3 { font-size: 15px; margin: 10px 0 6px; }
.feat p { color: var(--muted); font-size: 13px; line-height: 1.65; }
.steps { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 14px; counter-reset: step; }
.step { background: var(--surface); border: 1px solid var(--border); border-radius: 14px; padding: 22px; position: relative; }
.step::before { counter-increment: step; content: counter(step);
  display: inline-flex; align-items: center; justify-content: center; width: 30px; height: 30px; border-radius: 50%;
  background: var(--accent); color: #fff; font-weight: 500; font-size: 14px; margin-bottom: 12px; }
.step h3 { font-size: 15px; margin-bottom: 6px; }
.step p { color: var(--muted); font-size: 13px; line-height: 1.65; }
/* 900px only fits 3 of the 220px-min cards, so the free tier plus 3 paid
   plans (the common case) stranded the 4th alone on row two with a big
   empty gap beside it. 1040px fits all 4 across at once (4*220 + 3*14 =
   922) while staying inside .sec's own 1100px cap. */
.price-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px; max-width: 1040px; margin: 0 auto; }
.price { background: var(--surface); border: 1px solid var(--border); border-radius: 16px; padding: 24px; text-align: center; }
/* Brass is earned status, and the premium tier is exactly that. A glow was
   doing the job a border should do, and it was the accent colour rather
   than the status colour. */
.price.hot { border-color: var(--line-warn); box-shadow: none; }
.price.hot .nm { color: var(--brass); }
.price .nm { font-weight: 500; font-size: 15px; }
/* Sans, matching every other number on the page (and the equivalent price
   on upgrade.php, which was never serif) - this was the one figure on the
   landing page still set in Instrument Serif once the section headings
   above moved off it too. 500 rather than the headings' 600: Inter Tight
   only ships 400 and 500, and a plain price reads fine at the lighter of
   the two rather than asking the browser to synthesise a bolder one. */
.price .pr { font-family: var(--font-sans); font-size: 40px; font-weight: 500; margin: 8px 0 2px; color: var(--text); }
.price .per { color: var(--muted); font-size: 12px; }
.price ul { list-style: none; margin: 16px 0; text-align: left; }
.price li { font-size: 13px; color: var(--muted); padding: 5px 0; }
.price li::before { content: "✓ "; color: var(--up); font-weight: 500; }
.price a { display: block; margin-top: 8px; }
/* THE HONEST FOOTNOTE, PRESENTED HONESTLY.
   Five lines of centred yellow directly under the headline numbers shouted
   louder than the numbers did, and a caveat that reads as an alarm gets
   skipped like one. Same words, said in the site's ordinary quiet voice, in a
   bordered box that marks it as a note about the figures above - with the one
   sentence that actually matters left in the warning colour. Wider line
   height, left-aligned on a phone, because five centred lines of 12px is the
   hardest arrangement of text there is to read.

   Removed from the landing page entirely per feedback - the resolved-sample
   caveat and the "see the full track record" link (formerly .land-foot /
   .land-note / .land-more below the hero) no longer render here. The trust
   row above still shows the headline win-rate stat; the full breakdown and
   its methodology note live on performance.php for anyone who clicks through. */
.trust-strip { display: flex; gap: 8px 22px; justify-content: center; flex-wrap: wrap; margin-top: 26px; }
.trust-strip span { display: inline-flex; align-items: center; gap: 6px; color: var(--muted); font-size: 12.5px; }
.trust-strip .ic-svg { width: 14px; height: 14px; flex: none; color: var(--dim); }
footer .ic-svg { width: 12px; height: 12px; vertical-align: -1px; margin-right: 2px; }
<?php $heroCss = ob_get_clean();

\SignalMasterAi\View::head($seoTitle, $seoDesc, [
    'raw_title' => true,
    'keywords'  => sma_setting('meta_keywords'),
    'canonical' => $base . '/',
    'style'     => $heroCss,
]);
?>
<?php \SignalMasterAi\View::topbar('home'); ?>

<!-- ============================ HERO ============================
     A single centred block of copy: headline, subhead, the two calls to
     action and the trust-row stats. This used to sit beside a live
     instrument panel pulled from the signals table - a real symbol, a real
     entry/stop/targets, published on the marketing page as proof. Removed:
     an in-force trade plan belongs inside the product, not on a public page
     anyone (or any crawler) can load before ever creating an account. -->
<section class="hero-split">

  <div class="hero-copy">
    <p class="eyebrow rise d1">AI-generated signals</p>
    <h1 class="rise d2"><?= sma_e($heroLead) ?><?= $heroLead !== '' ? ' ' : '' ?><em><?= sma_e($heroLast) ?></em></h1>
    <?php if ($heroSub !== ''): ?>
      <p class="sub rise d3"><?= sma_e($heroSub) ?></p>
    <?php endif; ?>

    <div class="hero-ctas rise d4">
      <a class="cta-main" href="charts.php"><?= sma_e($heroCta) ?> &rarr;</a>
      <?php if (!$member && sma_setting('registration_enabled', '1') === '1'): ?>
        <a class="cta-ghost" href="account.php?tab=register">Create free account</a>
      <?php endif; ?>
    </div>

    <div class="trust-row rise d5">
      <div><b class="cnt" data-n="<?= (int)$stats['coins'] ?>">0</b><span>coins tracked</span></div>
      <div><b class="cnt" data-n="<?= (int)$stats['signals'] ?>">0</b><span>trading signals</span></div>
      <div><b class="cnt" data-n="<?= (int)$stats['rules'] ?>">0</b><span>analysis rules</span></div>
      <?php if ((int)($track['t'] ?? 0) >= 5): ?>
        <?php // One decimal, matching the track record this links to. ?>
        <div><b class="up"><?= $trackStats['winRate'] ?>%</b><span>verified win rate*</span></div>
      <?php endif; ?>
    </div>
  </div>

  <?php // The live instrument panel that used to sit here - a real symbol
        // pulled straight from the signals table, with its actual entry,
        // stop and targets - is gone. It showed a real, in-force trade
        // plan to every visitor, logged in or not; that is product
        // functionality, not marketing copy, and does not belong on a
        // public page. .hero-copy above is styled to stand alone as the
        // full width of the split (see .hero-copy, in the styles above)
        // now that there is nothing left to sit beside it. ?>
</section>

<?php // The resolved-sample caveat and "see the full verified track record"
      // link that used to sit here (the .land-foot box) have been removed
      // from the landing page per feedback. The headline win-rate stat is
      // still shown above in .trust-row; the full methodology and link to
      // performance.php now live only on that page itself, not repeated here. ?>

<section class="sec reveal">
  <h2>Everything a chart reader does — automated</h2>
  <p class="lead">AI reads every chart the way a veteran trader would, then keeps learning from what happens next.</p>
  <div class="feat-grid">
    <div class="feat"><div class="ic"><svg class="ic-svg" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 8.5h2.5l1.5-4 2 7 1.5-4.5H12.5"/></svg></div><h3>AI-generated signals</h3><p>BUY and SELL calls generated live by AI from <?= $stats['rules'] ?> technical-analysis rules — momentum, trend, volatility and price-pattern signals — and fine-tuned automatically as real results come in.</p></div>
    <div class="feat"><div class="ic"><svg class="ic-svg" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 3v10h10M6 10l2-3 2 2 3-4"/></svg></div><h3>Complete trade plans</h3><p>Every signal includes an entry, stop loss, three take-profit targets and risk:reward — plus clear exit rules, including a time stop for trades that go nowhere.</p></div>
    <div class="feat"><div class="ic"><svg class="ic-svg" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2.5 8.5l3 3 8-7"/></svg></div><h3>Market-verified track record</h3><p>Every signal is automatically confirmed or invalidated by the price action that follows it. The win rate you see is graded by the market, not by us.</p></div>
    <div class="feat"><div class="ic"><svg class="ic-svg" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 6.5a4 4 0 0 1 8 0c0 3 1.3 3.8 1.3 3.8H2.7S4 9.5 4 6.5Z"/><path d="M6.5 12.5a1.5 1.5 0 0 0 3 0"/></svg></div><h3>Instant alerts</h3><p>Browser push and email alerts the moment a coin you watch turns bullish or bearish, on the timeframes you choose. Push notifications work even with the browser closed.</p></div>
    <div class="feat"><div class="ic"><svg class="ic-svg" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2.5" y="3" width="11" height="10" rx="1.3"/><path d="M5 6.2h6M5 8.7h6M5 11.2h3.5"/></svg></div><h3>News & sentiment</h3><p>Live headlines from trusted sources and an economic calendar, with news sentiment for every coin factored directly into the signal engine.</p></div>
    <div class="feat"><div class="ic"><svg class="ic-svg" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2.5 12V3M2.5 12h11"/><rect x="4.5" y="8" width="2" height="4" rx=".5"/><rect x="7.5" y="5.5" width="2" height="6.5" rx=".5"/><rect x="10.5" y="7" width="2" height="5" rx=".5"/></svg></div><h3>Multi-timeframe confirmation</h3><p>Every signal checks the trend one timeframe higher and grades the setup A+ to C, so you can tell a strong setup from a weak one at a glance.</p></div>
  </div>
  <div class="trust-strip">
    <span><svg class="ic-svg" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2.5 8.5l3 3 8-7"/></svg> Every signal verified against real price action</span>
    <span><svg class="ic-svg" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2.5 13V7.5M6.5 13V4M10.5 13V9M13.5 13V2"/></svg> Losses shown, never hidden</span>
    <span><svg class="ic-svg" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3.5" y="7" width="9" height="6.5" rx="1.3"/><path d="M5.5 7V5a2.5 2.5 0 0 1 5 0v2"/></svg> No card details stored on this site</span>
    <span><svg class="ic-svg" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="8" cy="8" r="6"/><path d="M8 5v3l2 1.3"/></svg> Cancel anytime - plans simply expire</span>
  </div>
</section>

<section class="sec reveal">
  <h2>How it works</h2>
  <div class="steps">
    <div class="step"><h3>Open a chart</h3><p>Pick any of <?= number_format($stats['coins']) ?> coins and a timeframe from 15 minutes to 1 week. The analysis runs instantly and refreshes itself live.</p></div>
    <div class="step"><h3>Read the signal</h3><p>Every candle and indicator already read for you — see the verdict, its grade and the trade plan built alongside it, so you confirm and execute in seconds. Nothing is a black box.</p></div>
    <div class="step"><h3>Get alerted</h3><p>Watch your coins and let push or email alerts find you when a signal flips. Premium unlocks every coin.</p></div>
  </div>
</section>

<?php // A section used to sit here - "A real signal, right now" - showing
      // off the same live instrument panel the hero used to carry, with a
      // paragraph explaining the evidence ledger. Removed along with that
      // panel: both pointed at the same real, in-force trade plan, and
      // there is nothing left here to explain once it is gone. What's
      // behind a verdict is still covered by "How it works" above and the
      // FAQ below. ?>

<section class="sec reveal">
  <h2>How this is different</h2>
  <div class="cmp-wrap">
    <table class="cmp-table">
      <?php // The middle column is what a paid signal group typically offers,
            // NOT what this site does. Read with the heading clipped off, the
            // first row looks like a confession - which is why the layout
            // below stacks on a phone and repeats the column name on every
            // cell. The wording stays as written; it is the comparison that
            // has to survive a narrow screen. ?>
      <thead><tr><th scope="col">&nbsp;</th><th scope="col">Typical signal group</th><th scope="col"><?= sma_e($siteName) ?></th></tr></thead>
      <tbody>
        <?php // data-lbl carries the column heading down into each cell, because
              // below 460px the table stacks and the header row is gone. A
              // three-column comparison on a 375px phone either scrolls
              // sideways - where the reader never sees the column that makes
              // the point - or it stops being a table. ?>
        <?php $them = 'Typical signal group'; $us = sma_e($siteName); ?>
        <tr><th scope="row">Losing calls</th>
          <td data-lbl="<?= $them ?>">quietly deleted</td>
          <td class="yes" data-lbl="<?= $us ?>">published in the track record</td></tr>
        <tr><th scope="row">Why a signal fired</th>
          <td data-lbl="<?= $them ?>">&quot;trust the analysis&quot;</td>
          <td class="yes" data-lbl="<?= $us ?>">every rule listed with its weight</td></tr>
        <tr><th scope="row">Results measured</th>
          <td data-lbl="<?= $them ?>">screenshots</td>
          <td class="yes" data-lbl="<?= $us ?>">automatically, against real candles</td></tr>
        <tr><th scope="row">Trading costs</th>
          <td data-lbl="<?= $them ?>">ignored</td>
          <td class="yes" data-lbl="<?= $us ?>">subtracted from every result</td></tr>
        <tr><th scope="row">Headline metric</th>
          <td data-lbl="<?= $them ?>">win rate</td>
          <td class="yes" data-lbl="<?= $us ?>">expectancy and profit factor</td></tr>
        <tr><th scope="row">Your data</th>
          <td data-lbl="<?= $them ?>">a group chat</td>
          <td class="yes" data-lbl="<?= $us ?>">on this server, exportable</td></tr>
      </tbody>
    </table>
  </div>
</section>

<section class="sec reveal">
  <h2>Questions</h2>
  <div class="faq">
    <details><summary>Is this financial advice?</summary>
      <p>No. Every signal is produced by software reading the chart, not by a person recommending a
         trade. It can be wrong &mdash; the
         <a href="performance.php">track record</a> shows exactly how often, wins and losses both
         &mdash; and every trading decision you make is yours alone.</p></details>
    <details><summary>Where do the signals come from?</summary>
      <p>An AI engine that runs a knowledge base of technical-analysis rules — momentum, trend,
         volatility, candlestick patterns, market structure, volume, positioning and news sentiment —
         evaluated on completed candles. Each rule earns more say when it keeps being right and less
         when it isn't, so the engine improves itself from real results instead of staying fixed.</p></details>
    <details><summary>How is the win rate calculated?</summary>
      <p>A signal counts as a win when the first target is reached before the stop loss, a loss when
         the stop goes first, and expired when neither happens inside the time stop. When a single
         candle spans both levels the stop is assumed first unless finer candles prove otherwise.
         Trading costs are subtracted. Nothing is excluded for looking bad.</p></details>
    <details><summary>Why show expectancy instead of win rate?</summary>
      <p>Because win rate on its own decides nothing. A 40% win rate at 2R makes money; a 60% win
         rate at 0.5R loses it. Expectancy — average R per trade — is the number that answers the
         question people actually care about.</p></details>
    <details><summary>Do I need to keep the site open?</summary>
      <p>No. Alerts are delivered by browser push (which works with the browser closed), email and
         Telegram, filtered by the grade, confidence, direction and quiet hours you choose.</p></details>
    <details><summary>Can I cancel?</summary>
      <p>There is nothing to cancel — plans simply expire. No card details are stored on this site.</p></details>
  </div>
</section>

<?php if ($plans): ?>
<section class="sec reveal">
  <h2>Simple pricing</h2>
  <p class="lead">Free forever for public coins. Premium unlocks <?= $paidCoins > 0 ? number_format($paidCoins) . '+' : 'all' ?> premium coins and every feature, and activates automatically when you pay with crypto.</p>
  <div class="price-grid">
    <div class="price">
      <div class="nm">Free</div>
      <div class="pr">$0</div>
      <div class="per">forever</div>
      <ul><li>Public coins &amp; signals</li><li>Live news &amp; events</li><li>Push &amp; email alerts</li><li>Member coins on registration</li></ul>
      <a class="cta-ghost" href="account.php?tab=register">Register free</a>
    </div>
    <?php foreach ($plans as $i => $p): ?>
    <div class="price <?= $i === 1 || count($plans) === 1 ? 'hot' : '' ?>">
      <div class="nm"><?= sma_e($p['name']) ?> · Premium</div>
      <div class="pr">$<?= number_format((float)$p['price_usd'], 2) ?></div>
      <div class="per"><?= \SignalMasterAi\Payments::planLength((int)$p['days']) ?></div>
      <ul><li>Everything in Free</li><li>All premium coins</li><li>All timeframes &amp; alerts</li><li>Crypto checkout, instant</li></ul>
      <a class="cta-main" style="font-size:14px;padding:12px 20px" href="upgrade.php">Go premium</a>
    </div>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<footer class="footer">
  <p>
    <?php if (sma_setting('performance_page_enabled', '1') === '1'): ?>
      <a href="performance.php" style="color:var(--muted)"><svg class="ic-svg" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2.5 13V7.5M6.5 13V4M10.5 13V9M13.5 13V2"/></svg> Track record</a> &middot;
    <?php endif; ?>
    <a href="page.php?p=terms" style="color:var(--muted)">Terms</a> &middot;
    <a href="page.php?p=privacy" style="color:var(--muted)">Privacy</a> &middot;
    <a href="page.php?p=risk" style="color:var(--muted)">Risk disclosure</a>
  </p>
  <p><?= sma_e(sma_setting('site_notice')) ?></p>
</footer>
<?php // The scroll-reveal observer, the per-card stagger and the hero
      // count-up used to each have their own copy sitting right here -
      // this page's own <style>/<script>, independent of and slightly out
      // of step with the same three things ui.js and style.css already do
      // for every other page. All three now live once, shared, in
      // assets/ui.js and assets/style.css's "ALIVE" section - nothing
      // page-specific was lost, .feat/.step/.price get the same staggered
      // reveal and .cnt[data-n] the same count-up as before. ?>
<script src="assets/ui.js?v=<?= @filemtime(__DIR__ . '/assets/ui.js') ?: 1 ?>" defer></script>
</body>
</html>
