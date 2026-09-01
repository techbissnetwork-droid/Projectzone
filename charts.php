<?php
declare(strict_types=1);

$config = require __DIR__ . '/src/bootstrap.php';

use SignalMasterAi\Database;
use SignalMasterAi\MemberAuth;
use SignalMasterAi\WebPush;

MemberAuth::start();
$member = MemberAuth::current();
$viewerTier = MemberAuth::tier();
// Verified win rate, coin by coin - see Gate::FEATURES['perf_coins'].
// Decided once here rather than per control, the same way $viewerTier itself
// is - the "Highest win rate" sort button and the "By coin" tab in the
// "Live setups" sheet below both read this one flag.
$winRateOpen = \SignalMasterAi\Gate::allows('perf_coins', $viewerTier);

// One place learns the site's own URL - see View::learnSiteUrl().
\SignalMasterAi\View::learnSiteUrl();

$siteName = sma_setting('site_name', $config['app_name']);
$tagline  = sma_setting('site_tagline', $config['app_tagline']);
$notice   = sma_setting('site_notice');
$footerText = sma_setting('footer_text');
$defaultSymbol   = sma_setting('default_symbol', $config['market']['default_symbol']);
$defaultInterval = sma_setting('default_interval', $config['market']['default_interval']);

// Timeframe buttons: admin-selected subset of the supported intervals.
$enabledIv = array_values(array_intersect(
    $config['market']['intervals'],
    array_map('trim', explode(',', sma_setting('enabled_intervals', implode(',', $config['market']['intervals']))))
));
$intervals = $enabledIv ?: $config['market']['intervals'];
// Deep links decide which chart opens. The scanner's setup cards, the
// portfolio's position rows, the landing page's demo and every broadcast
// alert all link here as charts.php?symbol=X&tf=Y - and until now the page
// ignored both, so clicking a Solana setup opened Bitcoin. Validated against
// the enabled lists, never trusted as given.
// A SIGNAL ID IS A DEEP LINK.
//
// The reference is printed on the chart card, in the alert email, in the
// Telegram message and on the member's webhook payload, so it is the string
// somebody will be holding when they want to look at that call again - and
// until this it named a signal with nowhere to take the name. ?ref=SM-XXXXXX
// resolves to the pair and frame it was read on and hands off to the ordinary
// ?symbol=&tf= path, so there is exactly one way this page opens a chart.
//
// Normalised, not matched literally: the same three transcription mistakes the
// admin lookup forgives. Unknown references fall through to the default chart
// rather than erroring - the reference is a label, it authorises nothing, and
// a stranger guessing at one should learn nothing from the difference.
if (($_GET['ref'] ?? '') !== '' && ($_GET['symbol'] ?? '') === '') {
    $refWanted = \SignalMasterAi\SignalRef::normalise((string)$_GET['ref']);
    if (\SignalMasterAi\SignalRef::valid($refWanted)) {
        $rq = Database::pdo()->prepare('SELECT symbol, tf FROM signals WHERE ref = ? LIMIT 1');
        $rq->execute([$refWanted]);
        $refRow = $rq->fetch();
        $rq->closeCursor();
        if ($refRow) {
            header('Location: charts.php?symbol=' . rawurlencode((string)$refRow['symbol'])
                 . '&tf=' . rawurlencode((string)$refRow['tf']));
            exit;
        }
    }
}
$wantTf = (string)($_GET['tf'] ?? '');
if (in_array($wantTf, $intervals, true)) {
    $defaultInterval = $wantTf;
}
if (!in_array($defaultInterval, $intervals, true)) {
    $defaultInterval = $intervals[0];
}

// Appearance (admin-themeable)
$hex = fn(string $k, string $d) => preg_match('/^#[0-9a-f]{6}$/i', $v = sma_setting($k, $d)) ? $v : $d;
$accent = $hex('accent_color', \SignalMasterAi\View::BRAND_DEFAULTS['accent_color']);
$upCol  = $hex('up_color', \SignalMasterAi\View::BRAND_DEFAULTS['up_color']);
$downCol= $hex('down_color', \SignalMasterAi\View::BRAND_DEFAULTS['down_color']);

$symbols = Database::pdo()
    ->query('SELECT symbol, label, tier FROM symbols WHERE enabled = 1'
          . \SignalMasterAi\Visibility::sql('chart', 'symbols') . ' ORDER BY symbol')
    ->fetchAll();
// Which frames each coin is read on. The timeframe buttons are one control
// shared by every coin, so without this a pair restricted to the daily still
// offered 5m - and the chart drew a series the engine has no verdict for.
$symbolTfs = [];
foreach ($symbols as $sRow) {
    $symbolTfs[$sRow['symbol']] = \SignalMasterAi\Visibility::tfsFor($sRow['symbol'], $intervals);
}

// Group by tier for the picker; lock what the viewer can't access yet.
$groups = ['public' => [], 'free' => [], 'paid' => []];
foreach ($symbols as $s) {
    $groups[$s['tier']][] = $s + ['locked' => !MemberAuth::canAccess($s['tier'], $viewerTier)];
}
// Default symbol must be accessible; fall back to the first unlocked one.
// A requested pair has to clear the same tier gate as any other - a link is
// not a way past the paywall.
$accessible = array_filter($symbols, fn($s) => MemberAuth::canAccess($s['tier'], $viewerTier));
// Matched case-insensitively against the stored value rather than upper-cased:
// non-crypto symbols are namespaced and lower-case (stooq:aapl.us), so folding
// the case would make every one of them unlinkable.
$wantSym = (string)preg_replace('/[^A-Za-z0-9:._-]/', '', (string)($_GET['symbol'] ?? ''));
// WHICH COIN THIS URL IS ABOUT - a different question from which coin the
// viewer is allowed to see, and the canonical below needs the first one.
//
// The gate is right: a link is not a way past the paywall. What was wrong is
// that the canonical was built from the coin the viewer ended up on. A search
// engine crawls as a guest, so every premium coin - and every coin at all,
// once the guest tier is narrower than the free one - fell back to the first
// unlocked pair and then told Google "this page is a duplicate of that one".
// One coin page got indexed and the entire long tail was filed as "Alternate
// page with proper canonical tag". This site's coin pages ARE its long tail.
//
// A locked page is still that coin's page: it shows the coin, its name, its
// tier and the reason it is locked. So the canonical stays on the coin the URL
// asked for whenever that coin really exists.
$canonSym = '';
if ($wantSym !== '') {
    foreach ($symbols as $s) {
        if (strcasecmp($s['symbol'], $wantSym) === 0) {
            $canonSym = $s['symbol'];
            break;
        }
    }
    foreach ($accessible as $s) {
        if (strcasecmp($s['symbol'], $wantSym) === 0) {
            $defaultSymbol = $s['symbol'];
            break;
        }
    }
}
if (!in_array($defaultSymbol, array_column($accessible, 'symbol'), true)) {
    $defaultSymbol = $accessible ? array_values($accessible)[0]['symbol'] : '';
}
// The default frame must exist for the coin actually chosen, or the page
// opens on a timeframe its own picker is about to remove.
$symTfs = $symbolTfs[$defaultSymbol] ?? $intervals;
if (!in_array($defaultInterval, $symTfs, true)) {
    $defaultInterval = $symTfs[0] ?? $intervals[0];
}
$groupTitles = ['public' => 'Free for everyone', 'free' => 'Members (free account)', 'paid' => 'Premium members'];
?>
<?php
$seoTitle = sma_setting('seo_title') !== '' ? sma_setting('seo_title') : $siteName . ' - AI chart analysis & signals';
// A DESCRIPTION LONG ENOUGH TO BE ONE.
//
// The fallback was the site tagline with a full stop - forty-three characters,
// where a search result gives you about a hundred and sixty. Google fills the
// rest in from whatever it finds on the page, and what it finds on this page
// is toolbar labels. Worse, every coin got the SAME sentence, so a search
// engine looking at forty chart pages saw forty identical descriptions.
//
// Each coin and timeframe is already its own canonical page, so the
// description says which one it is. Still overridden by the operator's own
// meta_description when they have set one.
$seoDesc = sma_setting('meta_description');
if ($seoDesc === '') {
    $seoDesc = $tagline . '. '
        . 'Live BUY / SELL signals with entry, stop loss and take-profit targets, '
        . 'multi-timeframe confluence and a verified track record of wins and losses.';
}
$base = \SignalMasterAi\View::baseUrl();

// The chart of one coin on one timeframe is its own page, so that is what it
// claims to be. It used to name the HOME PAGE as its canonical and its og:url,
// which tells every crawler that this page is the front page and every share
// preview to show the front page's title - and which merges every coin, every
// timeframe and the landing page into one indexable address.
// ...but only when the visitor ASKED for a coin. Bare /charts.php and
// /charts.php?symbol=<the default> are one page, and this named the second as
// the canonical of the first - so the sitemap listed /charts.php while the
// page it listed pointed somewhere else. That is precisely the report
// "Duplicate, Google chose different canonical than user": the site handed
// over two addresses for one page and then disagreed with itself about which
// counts. The bare URL is the honest canonical for the bare URL, and a deep
// link keeps naming what it actually shows.
$canonQ = [];
if ($canonSym !== '') { $canonQ['symbol'] = $canonSym; }
// The site default frame is dropped, so ?symbol=X and ?symbol=X&tf=<default>
// are one URL rather than two pages of identical content competing with each
// other. Any other frame is genuinely different content and keeps its own.
$siteTf = (string)sma_setting('default_interval', $config['market']['default_interval']);
if (isset($wantTf) && $wantTf !== '' && in_array($wantTf, $intervals, true)
    && $defaultInterval !== $siteTf) {
    $canonQ['tf'] = $defaultInterval;
}

// A coin that does not exist is not a page worth indexing, and it must not
// claim to be a copy of one that does. ?symbol=NOTREAL used to canonicalise
// itself onto a real coin, which invites a crawler to treat any invented
// parameter as another duplicate of that coin - an unbounded supply of them.
$badSymbol = $wantSym !== '' && $canonSym === '';

\SignalMasterAi\View::head($seoTitle, $seoDesc, [
    'raw_title' => true,
    'keywords'  => sma_setting('meta_keywords'),
    'query'     => $canonQ,
    'noindex'   => $badSymbol,
]);
?>
<?php \SignalMasterAi\View::topbar('charts'); ?>

<?php // A new member lands here with no idea that alerts, a portfolio and a
      // backtester exist. The checklist is derived from what they have
      // actually done, so it ticks itself off and then goes away. ?>
<?= $member ? \SignalMasterAi\Onboarding::render((int)$member['id']) : '' ?>

<main class="layout">
  <section class="panel chart-panel">

    <?php // WHOSE CHART THIS IS, AT THE TOP, WHERE IT CANNOT BE MISSED.
          //
          // The switch lived inside the order ticket, four hundred pixels down
          // the right-hand column, and it decides the single most consequential
          // thing on the page: whether the entry, stop and targets in front of
          // you are the engine's or your own. A control that changes whose
          // advice you are looking at belongs above the chart it changes, not
          // folded into a form.
          //
          // Two states, said twice - by which side is lit and by the sentence
          // underneath - because a segmented control read at a glance is a
          // shape, and a shape can be misread. ?>
    <?php // TWO WAYS A SIGNAL REACHES YOU, NOT TWO MODES OF ONE PAGE.
          //
          // Both halves are the engine's: the chart in front of you, and the
          // board of every other coin. The second segment is a LINK, not a
          // state - it opens the scanner, which is the other half of the same
          // question: this coin says X, and where else is there a setup right
          // now. ?>
    <div class="mode-bar" id="planBar" role="group" aria-label="Where the signal comes from">
      <div class="mode-seg" id="planModeTop">
        <button type="button" data-plan="auto" class="on">
          <?php // Short enough to sit on one line at 390px. "this coin, our
               // engine" wrapped and left the two halves of the control
               // different heights, which reads as a broken segment rather
               // than a considered one. The bold label carries the meaning;
               // this line only has to say which side of it you are on. ?>
          <b>Chart signal</b><small>this coin</small></button>
        <button type="button" class="seg-link" id="scanOpen">
          <b>Scanner</b><small>all coins, ranked</small></button>
      </div>
      <p class="mode-say" id="planSay"></p>
    </div>

    <?php // WHEN THE BOARD AND THE CHART DISAGREE, SAY SO.
          //
          // The board shows what the engine last STORED, on a completed
          // candle; the chart re-runs the engine on the candle in front of
          // you. Between the two the market moves, so a call read six hours
          // ago can be NEUTRAL by the time somebody taps it - and the ticket
          // answered that with "NO SETUP", which reads as the site losing a
          // signal it had just advertised. Reported exactly that way.
          //
          // Filled in by the sheet when a row is tapped and the live read
          // turns out to differ. Empty and hidden otherwise, so it never
          // speaks on a chart nobody arrived at from the board. ?>
    <p class="stale-call" id="staleCall" hidden></p>

    <?php // THE BOARD WITHOUT LEAVING THE COIN.
          //
          // Tapping Scanner used to navigate to the standalone scanner page,
          // which meant losing the chart to look at the list and losing the
          // list to look at a chart. It opens here instead: the live setups
          // over the top, tap one and it loads into the chart underneath,
          // ready to trade. That page has since been removed outright - this
          // sheet had already taken over everything it did, so a second page
          // answering the same question was a duplicate route rather than a
          // feature of its own.
          //
          // Rendered empty and filled from api.php?action=board, gated on the
          // same feature the old page was. A free member gets the upgrade
          // line rather than an error, because being shown the door is the
          // point of a locked feature. ?>
    <div class="scan-pop" id="scanPop" hidden role="dialog" aria-modal="true"
         aria-labelledby="scanPopTitle">
      <div class="scan-pop-card">
        <div class="scan-pop-head">
          <h2 id="scanPopTitle">Live setups</h2>
          <?php // Which coins actually win - the same 'perf_coins' data as
                // the "Highest win rate" sort below, but the ranked list, not
                // a filter on this one. An icon by the close button rather
                // than a row of its own text in the body: it does not narrow
                // this list the way the filters do, it is a different
                // question (the coin's own history). SAME popup as the setups
                // list, not a second one stacked on top of it - tapping it
                // swaps this card's body over to the coin list in place (see
                // openCoins()/closeCoins() in app.js) and the title above
                // changes with it, rather than opening anything new. Tapping
                // a coin swaps back, filtered to it. ?>
          <?php if ($winRateOpen): ?>
          <button type="button" id="scanPopCoinsBtn" class="scan-pop-icon-btn"
                  aria-label="By coin win rate" aria-pressed="false">
            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M2 13h12M4 11V7M7.5 11V4M11 11V8"/>
            </svg>
          </button>
          <?php endif; ?>
          <button type="button" id="scanClose" class="scan-pop-x" aria-label="Close">&times;</button>
        </div>
        <?php // Filtered in the sheet, not by reloading it. The board is
              // fetched once and narrowed in the browser, so tapping between
              // BUY and SELL is instant and does not cost a round trip - and
              // the counts stay honest because the filter says how many of how
              // many it is showing. ?>
        <?php // Live only. This sheet used to offer a Closed board too -
              // what happened to the last lot - alongside the live one, but
              // this is the "what can I take right now" surface; the settled
              // record already has its own page (performance.php), and
              // carrying a second, smaller copy of it here just gave the
              // reader two places disagreeing about which calls to trust. ?>
        <div class="scan-pop-filters">
          <?php // Three questions about the same list, not three separate
                // lists - strongest, newest and best-proven-coin all narrow
                // the same board and stack on top of each other, so each
                // button toggles on its own instead of swapping the others
                // off. Strongest is on by default, same as before; add
                // Latest or Highest win rate on top of it, or turn Strongest
                // off and run on the others alone. See apply() in app.js for
                // the combined ordering. ?>
          <div class="scan-pop-seg scan-pop-seg-multi" data-filter="sort" id="scanPopSort">
            <button type="button" data-val="score" class="on">Strongest</button>
            <button type="button" data-val="latest">Latest</button>
            <?php if ($winRateOpen): ?>
            <button type="button" data-val="winrate">Highest win rate</button>
            <?php endif; ?>
          </div>
        </div>
        <div class="scan-pop-filters" id="scanPopFilters">
          <div class="scan-pop-seg" data-filter="dir">
            <button type="button" data-val="" class="on">All</button>
            <button type="button" data-val="BUY">Buy</button>
            <button type="button" data-val="SELL">Sell</button>
          </div>
          <select id="scanPopTf" aria-label="Timeframe">
            <option value="">Any timeframe</option>
            <?php foreach ($config['market']['intervals'] as $iv): ?>
              <option value="<?= sma_e($iv) ?>"><?= sma_e($iv) ?></option>
            <?php endforeach; ?>
          </select>
          <select id="scanPopGrade" aria-label="Minimum grade">
            <option value="">Any grade</option>
            <option value="B">B and up</option>
            <option value="A">A and up</option>
            <option value="A+">A+ only</option>
          </select>
          <?php // The three types, asked for by name rather than as a floor: a
                // 1:1 is not a worse signal than a 1:3, it is a shorter trade
                // with a nearer target, and "1:2 and up" would tell the reader
                // otherwise. ?>
          <?php if (\SignalMasterAi\Publish::memberMayChooseType()): ?>
          <select id="scanPopRr" aria-label="Signal type">
            <option value="">Any type</option>
            <option value="1">1:1 only</option>
            <option value="2">1:2 only</option>
            <option value="3">1:3 only</option>
          </select>
          <?php endif; ?>
        </div>
        <?php // The one filter with no visible control of its own - dir, tf,
              // grade and type are all a select or a lit button, so tapping
              // "All" undoes them, but picking a coin off the by-coin list
              // (see coinClick() in app.js) leaves nothing on THIS screen
              // showing a coin is even selected, only a shorter list. This
              // is that control: it appears only while a coin filter is on,
              // names the coin, and clears it in one tap - and closing the
              // sheet clears it too (see close() in app.js), so it can never
              // outlive the visit that set it. ?>
        <?php if ($winRateOpen): ?>
        <button type="button" id="scanPopSymbolClear" class="scan-pop-symbol-clear" hidden></button>
        <?php endif; ?>
        <p class="scan-pop-note" id="scanPopNote">Loading&hellip;</p>
        <?php // With enough fresh setups on the board, the "Already running"
              // section (see renderProgress() in app.js) can sit a long scroll
              // below the last of them - a reader who wanted exactly that
              // section would have to pass every enterable setup to find it.
              // This jumps straight there without moving it out of its own
              // place at the bottom, or mixing it into the list above where it
              // could be mistaken for something still takeable. Hidden until
              // there is something to jump to (see render() in app.js). ?>
        <button type="button" id="scanPopJump" class="scan-pop-jump" hidden></button>
        <ul class="scan-pop-list" id="scanPopList"></ul>
        <?php // The coin list swaps in over the setups list above (both live
              // in this same card) rather than opening a second popup -
              // fetched the first time the icon is tapped (TrackRecord::
              // topCoins(), via the api.php 'board' action's view=bycoin
              // branch) rather than on every sheet-open, since most visits
              // never ask for it. Hidden until then. ?>
        <?php if ($winRateOpen): ?>
        <div class="scan-pop-topcoins" id="scanPopTopCoins" aria-label="Best win rate by coin" hidden></div>
        <?php endif; ?>
        <?php // No "open the full scanner" link: this IS the full scanner now,
              // and a link out of it would be the duplicate route the menu
              // just stopped carrying. The page still answers its URL for old
              // links and the sitemap. ?>
        <p class="scan-pop-foot" id="scanPopFoot"></p>
      </div>
    </div>

    <div class="controls">
      <div class="sym-pick">
        <input type="text" id="symbolSearch" class="sym-search" placeholder="Search coin&hellip;"
               autocomplete="off" autocapitalize="off" spellcheck="false" aria-label="Search coin">
        <div class="sym-results" id="symbolResults" hidden></div>
      </div>
      <?php // Named for a screen reader. Every other control on this bar has a
            // label or an aria-label and this one - the coin picker, the most
            // important control on the page - had neither, so it announced as
            // just "combo box". ?>
      <select id="symbol" aria-label="Coin">
        <?php foreach ($groups as $tier => $list): if (!$list) continue; ?>
        <optgroup label="<?= sma_e($groupTitles[$tier]) ?>">
          <?php foreach ($list as $s): ?>
            <option value="<?= sma_e($s['symbol']) ?>" <?= $s['locked'] ? 'disabled' : '' ?>
                    <?= $s['symbol'] === $defaultSymbol ? 'selected' : '' ?>>
              <?= $s['locked'] ? '🔒 ' : '' ?><?= sma_e($s['label']) ?> (<?= sma_e($s['symbol']) ?>)
            </option>
          <?php endforeach; ?>
        </optgroup>
        <?php endforeach; ?>
      </select>
      <?php if ($viewerTier === 'guest' && (count($groups['free']) || count($groups['paid']))): ?>
        <a class="unlock-hint" href="account.php?tab=register">🔒 Register free to unlock more coins</a>
      <?php elseif ($viewerTier === 'free' && count($groups['paid'])): ?>
        <a class="unlock-hint" href="upgrade.php">🔒 Premium unlocks <?= count($groups['paid']) ?> more coins</a>
      <?php endif; ?>
      <!-- Just the interval. Spelling out "1h - hourly" pushed the control to
           165px next to a coin picker, for a code every chart already uses. -->
      <select id="tfSelect" class="tf-select" aria-label="Timeframe">
        <?php foreach ($symTfs as $iv): ?>
          <option value="<?= sma_e($iv) ?>" <?= $iv === $defaultInterval ? 'selected' : '' ?>><?= sma_e($iv) ?></option>
        <?php endforeach; ?>
      </select>
      <button id="favBtn" class="fav-btn" title="Add to favourites">☆</button>
      <?php // The live price and the refresh button are one group: both are
            // about how current this chart is, and they were separated by the
            // whole width of the panel - the price sat with the coin picker
            // and Refresh was pushed to the far right by an auto margin, with
            // three hundred pixels of nothing between them. Grouped, the gap
            // becomes a deliberate space between "what am I looking at" and
            // "how fresh is it", instead of an accident of the layout. ?>
      <div class="ctrl-live">
        <span class="live-price" id="livePrice" title="Live price"><span class="live-dot"></span> <span id="livePriceVal">—</span></span>
        <button id="analyseBtn" class="primary">Refresh now</button>
      </div>
    </div>
    <div class="fav-bar" id="favBar"></div>
    <div class="chart-toolbar" id="chartToolbar">
      <!-- Chart type is one choice out of five, so it belongs in a dropdown
           rather than five buttons competing for the same row. -->
      <select id="ctType" class="ct-select" aria-label="Chart type">
        <option value="candles">Candles</option>
        <option value="heikin">Heikin-Ashi</option>
        <option value="bars">Bars</option>
        <option value="line">Line</option>
        <option value="area">Area</option>
      </select>

      <!-- Nine independent toggles were nine buttons on show at all times.
           They are the same toggles, behind one control, laid out as a grid
           so each is a target rather than a word in a run of words. -->
      <div class="ct-menu" id="ctIndMenu">
        <button type="button" class="ct-menu-btn" id="ctIndBtn" aria-expanded="false" aria-controls="ctIndPanel">
          Indicators <span class="ct-count" id="ctIndCount" hidden>0</span>
        </button>
        <div class="ct-menu-panel" id="ctIndPanel" hidden>
          <p class="ct-menu-head">On the price chart</p>
          <div class="ct-grid">
            <button type="button" class="ct-tile" data-ov="ema"><b>EMA</b><small>20 &amp; 50</small></button>
            <button type="button" class="ct-tile" data-ov="bb"><b>BB</b><small>Bollinger</small></button>
            <button type="button" class="ct-tile" data-ov="vwap"><b>VWAP</b><small>volume avg</small></button>
            <button type="button" class="ct-tile" data-ov="vol"><b>VOL</b><small>volume bars</small></button>
            <button type="button" class="ct-tile" data-ov="levels"><b>TP/SL</b><small>trade plan</small></button>
            <?php // Support and resistance were already computed and already
                  // printed as two numbers beside the chart. This draws them
                  // ON it, which is the only place they mean anything. ?>
            <button type="button" class="ct-tile" data-ov="sr"><b>S/R</b><small>support &amp; resistance</small></button>
            <button type="button" class="ct-tile" data-ov="markers"><b></b><small>past signals</small></button>
          </div>
          <p class="ct-menu-head">Panels &amp; scale</p>
          <div class="ct-grid">
            <button type="button" class="ct-tile" data-ov="rsi"><b>RSI</b><small>momentum</small></button>
            <button type="button" class="ct-tile" data-ov="macd"><b>MACD</b><small>trend</small></button>
            <button type="button" class="ct-tile" data-ov="log"><b>LOG</b><small>log scale</small></button>
          </div>
        </div>
      </div>

      <select id="compareSel" class="ct-select" aria-label="Compare with another coin"
              title="Compare against another coin (percent change)">
        <option value="">Compare&hellip;</option>
        <?php foreach ($groups as $tier => $list): foreach ($list as $s): if ($s['locked']) continue; ?>
          <option value="<?= sma_e($s['symbol']) ?>"><?= sma_e($s['symbol']) ?></option>
        <?php endforeach; endforeach; ?>
      </select>
      <div class="ct-group" title="Zoom - drag the price axis to rescale, scroll or pinch for time">
        <button type="button" class="ct-btn" id="ctZoomOut">&minus;</button>
        <button type="button" class="ct-btn" id="ctZoomIn">&#65291;</button>
        <button type="button" class="ct-btn" id="ctReset" title="Reset view">&#10226;</button>
        <button type="button" class="ct-btn" id="ctFull" title="Fullscreen"></button>
      </div>
    </div>

    <?php // What to do next, said only while a tool is armed. A permanent
          // instruction is wallpaper; one that appears when you pick a tool
          // and goes when you are done is an answer to the question you just
          // asked. ?>
    <div class="dw-doing" id="drawDoing" hidden>
      <span class="dw-doing-t" id="drawDoingText"></span>
      <button type="button" class="dw-done" id="drawDone">Done</button>
    </div>

    <div class="chart-wrap">
      <canvas id="chart" role="img"
              aria-label="Price chart. The same data is available as a table below."></canvas>
      <div id="chartStatus" class="chart-status">Loading chart&hellip;</div>
    </div>
    <details class="chart-data">
      <summary>View chart data as a table</summary>
      <div class="tbl-scroll"><table id="chartData" class="scan-table"></table></div>
    </details>
  </section>

  <?php // THE ENGINE'S PANEL.
        //
        // Everything in here is the engine talking: the verdict, the score,
        // the grade, the confidence meter, the higher-timeframe ladder, the
        // market structure read, its support and resistance, the evidence
        // balance and the rule-by-rule reasons - with the order ticket, the
        // alert bell and the watchlist alongside them. ?>
  <aside class="panel signal-panel" id="signalPanel">
    <div class="news-head signal-head">
      <h2 style="margin:0" id="signalPanelTitle">Signal</h2>
      <span class="live-dot" id="signalLiveDot" title="Signals update automatically"></span>
      <span class="news-updated" id="signalUpdated">auto</span>
<?php if (sma_setting('alerts_enabled', '1') === '1'): ?>
      <!-- Alerts are set up once and then left alone, so a permanent panel of
           watchlist, timeframes and email options sat open on every visit for
           a job already done. Behind a bell, with a count of what is watched. -->
      <div class="bell-menu" id="bellMenu">
        <button type="button" class="bell-btn" id="bellBtn" aria-expanded="false"
                aria-controls="bellPanel" aria-label="Signal alerts" title="Signal alerts">
          <span class="bell-count" id="bellCount" hidden>0</span>
        </button>
        <div class="bell-panel" id="bellPanel" hidden>
          <div class="bell-head">Signal alerts</div>
    <div class="alerts-box" id="alertsBox">
      <p class="alerts-hint">Get an instant browser notification when a watched coin turns
        <strong style="color:var(--up)">bullish</strong> or <strong style="color:var(--down)">bearish</strong> -
        even while this tab is in the background.</p>
      <button id="alertsEnableBtn" class="alerts-enable">Enable notifications</button>
      <div id="alertsConfig" style="display:none">
          <div class="wl-picker" id="watchListPicker"></div>
        <p class="wl-sync" id="watchSyncNote"><?= $member
          ? 'Your watchlist is saved to your account and follows you to any device.'
          : 'Saved in this browser. <a href="account.php?tab=register">Create a free account</a> to keep it across devices.' ?></p>
      <div class="tf-checks" id="alertTfChecks">
          <?php foreach ($intervals as $iv): ?>
            <label><input type="checkbox" value="<?= sma_e($iv) ?>" <?= $iv === $defaultInterval ? 'checked' : '' ?>> <?= sma_e($iv) ?></label>
          <?php endforeach; ?>
        </div>
        <button id="alertWatchBtn" class="alerts-watch">+ Watch <span id="alertWatchSym"></span> on selected timeframes</button>
        <?php
        $bulkNeedT = sma_setting('bulk_watch_tier', 'paid');
        $bulkAllowed = sma_setting('bulk_watch_enabled', '1') === '1' && (
            MemberAuth::meetsTier($bulkNeedT, $viewerTier)
        );
        ?>
        <?php
        // "Watch the coins that are actually working" - the same one-tap idea
        // as watch-all, aimed instead of exhaustive. Its own tier and toggle
        // rather than riding on bulk_watch_*: an operator may well want to
        // give everyone the full list and keep the curated one for premium,
        // and the reverse is just as reasonable.
        $topNeedT = sma_setting('top_watch_tier', 'paid');
        $topAllowed = sma_setting('top_watch_enabled', '1') === '1'
            && MemberAuth::meetsTier($topNeedT, $viewerTier);
        ?>
        <?php if ($topAllowed): ?>
          <div class="top-watch">
            <button id="alertTopWinBtn" class="alerts-watch top" data-by="winrate">Watch the
              <?= (int)sma_setting('top_watch_count', '10') ?> best win-rate coins</button>
            <button id="alertTopRetBtn" class="alerts-watch top" data-by="return">Watch the
              <?= (int)sma_setting('top_watch_count', '10') ?> highest-return coins</button>
            <p class="hint-p top-watch-note">From the verified track record, coins with at least
              <?= (int)sma_setting('top_watch_min', '10') ?> settled trades. Win rate is how often a
              call worked; return is how much it made &mdash; they disagree more often than you would
              think, so both are offered.</p>
          </div>
        <?php elseif (sma_setting('top_watch_enabled', '1') === '1' && $bulkAllowed === false): ?>
          <p class="email-opt muted-note">Watching the best-performing coins with one tap is a
            <?= $topNeedT === 'paid' ? '<a href="upgrade.php">Premium</a>' : '<a href="account.php?tab=register">member</a>' ?> feature.</p>
        <?php endif; ?>
        <?php if ($bulkAllowed): ?>
          <button id="alertBulkBtn" class="alerts-watch bulk">Watch ALL my coins on selected timeframes</button>
          <button id="alertClearBtn" class="alerts-clear">Clear all watches</button>
        <?php elseif (sma_setting('bulk_watch_enabled', '1') === '1'): ?>
          <p class="email-opt muted-note">Watching <em>all</em> coins with one tap is a
            <?= $bulkNeedT === 'paid' ? '<a href="upgrade.php">Premium</a>' : '<a href="account.php?tab=register">member</a>' ?> feature.</p>
        <?php endif; ?>
        <?php if ($member): ?>
        <label class="email-opt" id="emailOpt">
          <input type="checkbox" id="emailAlertChk">
          Also email me at <strong><?= sma_e($member['email']) ?></strong>
          <span class="email-note" id="emailNote"></span>
        </label>
        <?php else: ?>
        <p class="email-opt muted-note"><a href="account.php?tab=register">Register free</a> to also get signal alerts by email.</p>
        <?php endif; ?>
        <ul class="watch-list" id="watchList"></ul>
      </div>
    </div>
        </div>
      </div>
<?php endif; ?>
    </div>
    <div id="signalCard" class="signal-card idle">
      <div class="signal-badge" id="signalBadge">&mdash;</div>
      <!-- Screen readers get told when the verdict flips; the badge alone
           communicated the change by colour and animation only. -->
      <p class="visually-hidden" id="signalLive" aria-live="polite" role="status"></p>
      <?php // THE CALL'S PUBLIC REFERENCE.
            //
            // Six characters, drawn at random when the signal was published,
            // and the only short thing on this panel that identifies THIS call
            // rather than describing it. A member writing in about a trade had
            // nothing to quote but "the BTC one from this morning", which is
            // two questions and a guess; this is one string, and the admin
            // signal search takes it verbatim.
            //
            // Hidden unless the live reading agrees with a published call -
            // the reading itself is never stored, so most of the time there is
            // no reference to show and an empty box would only invite the
            // question of why it is empty. ?>
      <div class="sig-ref" id="sigRef" hidden>
        <span>Signal ID</span>
        <code id="sigRefText">&mdash;</code>
        <button type="button" class="sig-ref-copy" id="sigRefCopy"
                title="Copy this signal ID">Copy</button>
      </div>
      <?php // HOW LONG THIS CALL IS STILL A CALL.
            //
            // Every signal already carries its own deadline - the engine picks
            // the window when it picks the type, so a 1:3 gets three times the
            // room a 1:1 does and closing one on the other's clock would not
            // be the same trade. That deadline was in the alert email and
            // nowhere on the chart, so a reader looking at a setup had no way
            // to tell a call with twenty hours left from one with twenty
            // minutes.
            //
            // A number AND a bar. The number answers "how long have I got";
            // the bar answers the question the number cannot, which is how
            // much of this setup's life has already gone - a setup with two
            // hours left is a different proposition on a 4h call than on a
            // daily one. Ticks in the browser rather than on the poll, so it
            // moves like a clock instead of jumping once a minute. ?>
      <div class="sig-life" id="sigLife" hidden>
        <span class="sl-lbl">Valid for</span>
        <strong id="sigLifeText">&mdash;</strong>
        <span class="sl-bar" aria-hidden="true"><i id="sigLifeFill"></i></span>
      </div>
      <div class="signal-meta">
        <div><span>Confidence</span><strong id="sigConfidence">&mdash;</strong></div>
        <div><span>Score</span><strong id="sigScore">&mdash;</strong></div>
        <div><span>Grade</span><strong id="sigGrade">&mdash;</strong></div>
        <?php // "Price" beside a live price chip in the same viewport, showing a
              // different number, is a question rather than a fact. This one is
              // the price the ENGINE read when it formed this verdict; the chip
              // above the chart is the price now. They differ by however long
              // ago the analysis ran, and the label has to say which is which. ?>
        <div><span title="The price when this verdict was formed. The chip above the chart is the price now.">At analysis</span><strong id="sigPrice">&mdash;</strong></div>
      </div>
      <div class="score-gauge" id="scoreGauge" aria-hidden="true"></div>

      <!-- What the confidence number is made of. A percentage on its own asks
           to be trusted; the axes behind it can be argued with. -->
      <details class="conf-dims" id="confDims" hidden>
        <summary>Why this confidence <span id="confBasisTag"></span></summary>
        <div class="cd-grid" id="cdGrid"></div>
        <p class="cd-note" id="cdNote"></p>
      </details>

      <!-- The timeframes above this one, highest first. A verdict read on its
           own timeframe hides the thing that decides most trades: whether the
           rest of the market agrees. -->
      <!-- What kind of market this is, and what it changed. A classification
           that does not change behaviour is decoration, so the effects are
           spelled out next to the label. -->
      <div class="regime" id="regimePanel" hidden>
        <span class="rg-dot" id="rgDot"></span>
        <span class="rg-name" id="rgName">&mdash;</span>
        <span class="rg-effect" id="rgEffect"></span>
      </div>

      <div class="mtf" id="mtfLadder" hidden>
        <div class="mtf-head">Higher timeframes <strong id="mtfBias">&mdash;</strong></div>
        <div class="mtf-grid" id="mtfGrid"></div>
      </div>

      <!-- The plan is the verdict expressed as prices. Split into its own
           section below, the two had to be read together across a heading
           and a fold; they are one thing, so they sit in one card. -->
      <div class="trade-plan" id="tradePlan">
        <div class="tp-head" id="tpHead">Trade plan</div>
        <div class="tp-row"><span>Entry</span><strong id="tpEntry">&mdash;</strong></div>
        <div class="tp-row sl"><span>Stop loss</span><strong id="tpSl">&mdash;</strong></div>
        <div class="tp-row tp"><span>TP 1</span><strong id="tpTp1">&mdash;</strong></div>
        <div class="tp-row tp"><span>TP 2</span><strong id="tpTp2">&mdash;</strong></div>
        <div class="tp-row tp"><span>TP 3</span><strong id="tpTp3">&mdash;</strong></div>
        <div class="tp-row"><span>Risk : Reward</span><strong id="tpRr">&mdash;</strong></div>
        <div class="tp-row" id="tpMagnetRow" hidden>
          <span title="Nearest estimated pool of forced buying or selling ahead of the trade. Price is drawn to these because the orders there are not optional.">Liquidity magnet</span>
          <strong id="tpMagnet">&mdash;</strong></div>
        <details class="tp-exits"><summary>Exit rules</summary>
          <ul class="exit-rules" id="exitRules"></ul></details>
        <p class="tp-note" id="tpNote">Levels appear when the signal is BUY or SELL.</p>
      </div>

      <!-- Where price is standing, structurally. Not a call in itself - see
           the reasons list for what the engine actually did with it. -->
      <div class="smc" id="smcPanel" hidden>
        <div class="smc-head">Market structure <strong id="smcZone">&mdash;</strong></div>
        <div class="smc-grid" id="smcGrid"></div>
      </div>

      <!-- What the plan was derived from. A heading away it read as trivia;
           next to the entry and stop it is the reasoning behind them. -->
      <div class="levels" id="levels">
        <div><span>Support</span><strong id="lvlSupport">&mdash;</strong></div>
        <div><span>Resistance</span><strong id="lvlResistance">&mdash;</strong></div>
        <div><span>RSI(14)</span><strong id="lvlRsi">&mdash;</strong></div>
        <div><span>ATR(14)</span><strong id="lvlAtr">&mdash;</strong></div>
      </div>

      <button type="button" class="share-btn" id="shareBtn" title="Save or share this signal as an image">Share this signal</button>
      <div class="sig-history" id="sigHistory"></div>
    </div>



    

    <?php if (sma_setting('paper_enabled', '1') === '1'): ?>
    <?php if ($member): ?>
      <!-- Two steps on purpose. The old single button fired an order-shaped
           action on one click with nothing shown about what it would open,
           and its "added" label survived a change of coin - so it could read
           as already-followed on a pair you had never touched. -->
      <div class="follow-box" id="followBox">
        <!-- Live mark-to-market for an open position on this pair. Until now
             a member could only see what a trade did after it settled. -->
        <div class="live-pos" id="livePos" hidden>
          <div class="live-pos-head">
            <span class="live-pos-side" id="livePosSide">&mdash;</span>
            <span class="live-pos-tag">open paper position</span>
          </div>
          <div class="live-pos-pnl" id="livePosPnl">&mdash;</div>
          <dl class="follow-facts">
            <div><dt>Entry</dt><dd id="livePosEntry">&mdash;</dd></div>
            <div><dt>Now</dt><dd id="livePosNow">&mdash;</dd></div>
            <div><dt>Size</dt><dd id="livePosSize">&mdash;</dd></div>
          </dl>
        </div>
        <button type="button" id="followBtn" class="alerts-watch follow-btn">
          &#43; Paper-trade this signal</button>
        <!-- An order ticket, the way an exchange does it: the balance comes
             from the portfolio wallet, and the size for THIS trade is chosen
             here at the moment of opening, rather than being a global setting
             configured somewhere else beforehand. -->
        <div class="follow-confirm ticket" id="followConfirm" hidden>
          <div class="ticket-avail"><span>Available</span><strong id="ticketAvail">&mdash;</strong></div>

          <!-- A setup found an hour ago has usually moved. Entering at the
               published price when the market is elsewhere records a fill
               nobody could get; entering at market keeps the same stop and
               targets, which is what changes the risk:reward. -->
          <span class="ticket-label">Entry price</span>
          <div class="ticket-entry" id="ticketEntry">
            <button type="button" data-entry="signal" class="on">
              <b>Signal</b><small id="ticketSigPx">&mdash;</small></button>
            <button type="button" data-entry="market">
              <b>Market</b><small id="ticketMktPx">&mdash;</small></button>
          </div>

          <!-- Where the position closes itself. The site plan takes part off
               at target 1 and lets the rest run to target 2; naming a target
               closes the whole position there instead. The stop loss and the
               time stop apply either way. -->
          <span class="ticket-label">Close at</span>
          <div class="ticket-entry" id="ticketExit">
            <button type="button" data-exit-target="1"><b>TP 1</b><small id="ticketTp1">&mdash;</small></button>
            <button type="button" data-exit-target="2"><b>TP 2</b><small id="ticketTp2">&mdash;</small></button>
            <button type="button" data-exit-target="3" class="on"><b>TP 3</b><small id="ticketTp3">&mdash;</small></button>
            <button type="button" data-exit-target="-1"><b>No target</b><small>stop or time only</small></button>
            <button type="button" data-exit-target="0"><b>Auto</b><small>TP1 part, rest TP2</small></button>
          </div>

          <label class="ticket-label" for="ticketMargin">Amount to commit</label>
          <div class="ticket-pcts" id="ticketPcts">
            <button type="button" data-pct="10">10%</button>
            <button type="button" data-pct="25">25%</button>
            <button type="button" data-pct="50">50%</button>
            <button type="button" data-pct="100">Max</button>
          </div>
          <div class="ticket-amt">
            <input type="number" id="ticketMargin" min="0" step="any" inputmode="decimal" placeholder="0.00">
            <span>USD</span>
          </div>

          <label class="ticket-label" for="ticketLev">Leverage</label>
          <?php
          // The rungs are the operator's, not this file's. They used to be
          // eight hardcoded options here while the server clamped at 125 in
          // two other files - three copies of one decision, none of them
          // changeable without editing PHP. See Paper::leverageLadder().
          //
          // 5x stays the pre-selected rung when it is offered, because the
          // default that greets a new member should be modest; otherwise the
          // lowest rung is, which is 1x on any sane ladder.
          $levRungs = \SignalMasterAi\Paper::leverageLadder();
          $levPick = in_array(5.0, $levRungs, true) ? 5.0 : $levRungs[0];
          $levFmt = static fn(float $n): string =>
              rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
          ?>
          <select id="ticketLev">
            <?php foreach ($levRungs as $lv): ?>
              <option value="<?= sma_e($levFmt($lv)) ?>"<?= $lv === $levPick ? ' selected' : '' ?>>
                <?= sma_e($levFmt($lv)) ?>&times;<?= $lv <= 1.0 ? ' &mdash; no leverage' : '' ?>
              </option>
            <?php endforeach; ?>
          </select>

          <dl class="follow-facts" id="followFacts"></dl>
          <p class="ticket-err" id="ticketErr" hidden></p>

          <!-- Out of balance, without leaving the ticket.
               Running out of funds mid-order used to close the ticket and send
               the member to the portfolio to deposit - which means leaving the
               chart, topping up, coming back, finding the coin again and
               reopening the ticket, by which time the setup has usually moved.
               The wallet is simulated; there was never a reason for it to be a
               separate journey. Hidden until it is the thing in the way. -->
          <div class="ticket-topup" id="ticketTopUp" hidden>
            <span class="ticket-label">Add simulated funds</span>
            <div class="ticket-amt">
              <input type="number" id="ticketTopAmt" min="0" step="any" inputmode="decimal" placeholder="0.00">
              <span>USD</span>
            </div>
            <div class="ticket-pcts" id="ticketTopPresets">
              <button type="button" data-top="100">+$100</button>
              <button type="button" data-top="1000">+$1,000</button>
              <button type="button" data-top="10000">+$10,000</button>
            </div>
            <button type="button" class="follow-go" id="ticketTopGo">Add funds and open</button>
          </div>
          <div class="follow-actions">
            <button type="button" class="follow-go" id="followGo">Open paper trade</button>
            <button type="button" class="follow-cancel" id="followCancel">Cancel</button>
          </div>


          <p class="follow-lead">Simulated &mdash; no real money, no broker. Settles itself at the stop,
             a target or the time stop. Funds come from your
             <a href="portfolio.php">portfolio wallet</a>.</p>
        </div>
        <p class="tp-note" id="followNote"></p>
      </div>

      <?php // YOUR OPEN TRADES, ON THE CHART THEY WERE OPENED FROM.
            //
            // Every other charting application shows your positions beside the
            // price; this one sent you to a separate page to find out whether
            // you were still in the trade you opened here, and back again to
            // close it. A position is the reader's, never the engine's, so it
            // belongs on the chart it was opened from.
            //
            // Positions on THIS coin lead and are drawn on the chart; the rest
            // are listed under them, because "am I in anything else" is a real
            // question and switching coins to answer it is not an answer. ?>
      <div class="pos-panel" id="posPanel" hidden>
        <div class="pos-head">
          <span class="ticket-label">Your open trades</span>
          <span class="pos-wallet" id="posWallet"></span>
        </div>
        <ul class="pos-list" id="posList"></ul>
        <p class="tp-note" id="posNote"></p>
      </div>
    <?php else: ?>
      <p class="tp-note follow-btn"><a href="account.php?tab=register">Create a free account</a>
         to paper-trade signals and keep a portfolio.</p>
    <?php endif; ?>
    <?php endif; ?>

    <div id="aiExplain" class="ai-explain" hidden>
      <p class="ai-text" id="aiText"></p>
      <p class="ai-src">Plain-language summary of the analysis above. It describes what the rules
        found &mdash; it does not decide anything and never predicts a price.</p>
      <div class="ai-ask" id="aiAsk" hidden>
        <form id="aiAskForm">
          <input type="text" id="aiQuestion" maxlength="300" autocomplete="off"
                 placeholder="Ask about this setup&hellip;" aria-label="Ask a question about this setup">
          <button type="submit" class="ai-ask-btn">Ask</button>
        </form>
        <p class="ai-answer" id="aiAnswer" hidden></p>
      </div>
    </div>

    <details class="sec" data-sec="evidence">
    <summary>Evidence balance</summary>
    <div class="vote-meter" id="voteMeter" role="img" aria-label="Balance of bullish and bearish evidence by category">
      <p class="muted" style="font-size:12px">Waiting for the first analysis&hellip;</p>
    </div>
    </details>

    <details class="sec" data-sec="why" open>
    <summary>Why this signal?</summary>
    <ul id="reasons" class="reasons">
      <li class="muted">Analysing the chart automatically&hellip;</li>
    </ul>
    </details>


  </aside>
</main>

<?php // Switched on, AND allowed for this viewer. The gate is checked here
      // rather than inside the strip so a free member gets a short prompt in
      // its place instead of an empty panel - a section that renders its
      // heading and nothing else reads as broken, not as locked. ?>
<?php // ============ BELOW-THE-FOLD REBUILT AS A DASHBOARD ============
      //
      // Four sections used to be four full-width boxes, stacked, identical
      // in shape whether they held a one-line strip or a two-column news
      // feed. Same width, same weight, same distance apart: nothing on the
      // page told you news mattered more than the events list, so the page
      // read as a pile rather than an instrument with parts. Grouped into one
      // grid with named areas below, sized to what each section actually is.
      ?>
<div class="fold-grid">
<?php if (sma_setting('mtf_strip_enabled', '1') === '1'
          && !\SignalMasterAi\Gate::allows('mtf', $viewerTier)): ?>
<section class="layout reveal fg-mtf">
  <div class="panel">
    <details class="sec sec-lg" data-sec="mtfstrip">
      <summary>Every timeframe at once <span class="nav-lock"></span></summary>
      <p class="hint-p">The same coin read on every timeframe at once - the check that stops a 15m
        long being taken into a falling daily. Part of
        <a href="upgrade.php">Premium</a>.</p>
    </details>
  </div>
</section>
<?php endif; ?>
<?php if (sma_setting('mtf_strip_enabled', '1') === '1'
          && \SignalMasterAi\Gate::allows('mtf', $viewerTier)): ?>
<!-- One verdict per timeframe. A 5m BUY under a falling daily is a different
     trade from a 5m BUY with everything above it agreeing, and the signal card
     on its own cannot show that. Read from what has already been analysed, so
     a stale frame says how old it is rather than pretending to be current. -->
<section class="layout reveal fg-mtf">
  <div class="panel">
    <details class="sec sec-lg" data-sec="mtfstrip" open>
      <summary>Every timeframe at once</summary>
      <div class="tfv-strip" id="mtfStrip"><span class="muted">Loading&hellip;</span></div>
      <p class="attribution">Each cell is the last verdict stored for that timeframe on this pair.
        Agreement across the ladder is the setup worth taking; a lone call against everything above
        it is the one to be careful with.</p>
    </details>
  </div>
</section>
<?php endif; ?>

<?php if (sma_setting('context_panel_enabled', '1') === '1'): ?>
<!-- Market context: the reads that describe the market this pair trades in
     rather than the pair itself. Every figure here is already an engine input;
     showing them means a verdict is not a number out of nowhere. Each cell
     fills independently, so one unavailable feed leaves a dash instead of
     blanking the panel. -->
<section class="layout reveal fg-context">
  <div class="panel">
    <details class="sec sec-lg" data-sec="context" open>
      <summary>Market context <span class="live-dot" title="Auto-refreshes"></span></summary>
      <div class="ctx-grid" id="ctxGrid">
        <div class="ctx-fng">
          <div class="fng-gauge" id="fngGauge" role="img" aria-label="Fear and Greed index">
            <div class="fng-track"><span class="fng-needle" id="fngNeedle"></span></div>
            <div class="fng-scale"><span>Extreme fear</span><span>Neutral</span><span>Extreme greed</span></div>
          </div>
          <div class="ctx-fng-read"><b id="fngValue">&mdash;</b><span id="fngLabel">Fear &amp; Greed</span>
            <small id="fngTrend"></small></div>
        </div>
        <dl class="ctx-facts">
          <div><dt>Funding</dt><dd id="ctxFunding">&mdash;</dd></div>
          <div><dt>Long / short</dt><dd id="ctxLs">&mdash;</dd></div>
          <div><dt>Open interest</dt><dd id="ctxOi">&mdash;</dd></div>
          <div><dt>Taker flow</dt><dd id="ctxTaker">&mdash;</dd></div>
          <div><dt>Breadth</dt><dd id="ctxBreadth">&mdash;</dd></div>
        </dl>
      </div>
      <p class="attribution">Fear &amp; Greed via alternative.me (updates once a day). Funding, long/short,
        open interest and taker flow are free exchange futures data &mdash; spot-only coins and some
        regions return nothing, which shows as a dash rather than a zero.</p>
    </details>
  </div>
</section>
<?php endif; ?>

<?php if (sma_setting('news_enabled', '1') === '1'): ?>
<section class="layout news-layout reveal fg-news">
  <!-- Collapsible like every other section now, and remembered per browser -
       open by default, because reading the headlines is the point of the
       block rather than something to go looking for. -->
  <div class="panel">
    <details class="sec sec-lg" data-sec="news" open>
      <summary>Live market news
        <span class="live-dot" title="Auto-refreshes"></span>
        <span class="news-updated" id="newsUpdated"></span>
      </summary>
      <ul class="news-list" id="newsList">
        <li class="muted">Loading news&hellip;</li>
      </ul>
      <p class="attribution">Headlines &copy; their respective publishers &mdash; each item links to the original article.</p>
    </details>
  </div>
  <div class="panel">
    <details class="sec sec-lg" data-sec="events" open>
      <summary>Upcoming events</summary>
      <ul class="events-list" id="eventsList">
        <li class="muted">Loading calendar&hellip;</li>
      </ul>
      <p class="attribution">Economic calendar via ForexFactory. Times shown in your local timezone.</p>
    </details>
  </div>
</section>
<?php endif; ?>
</div><!-- /.fold-grid -->


<footer class="footer">
  <p><?= sma_e($siteName) ?> <?= sma_e($footerText) ?></p>
  <p>
    <?php if (sma_setting('performance_page_enabled', '1') === '1'): ?>
      <a href="performance.php" style="color:var(--muted)">Track record</a> &middot;
    <?php endif; ?>
    <a href="page.php?p=terms" style="color:var(--muted)">Terms</a> &middot;
    <a href="page.php?p=privacy" style="color:var(--muted)">Privacy</a> &middot;
    <a href="page.php?p=risk" style="color:var(--muted)">Risk disclosure</a>
  </p>
  <p><?= sma_e(sma_setting('site_notice')) ?></p>
</footer>

<script<?= sma_nonce() ?>>
window.SMA = {
  defaultSymbol: <?= sma_js($defaultSymbol) ?>,
  defaultInterval: <?= sma_js($defaultInterval) ?>,
  newsEnabled: <?= sma_js(sma_setting('news_enabled', '1') === '1') ?>,
  newsPollSeconds: <?= max(30, (int)sma_setting('news_poll_seconds', '90')) ?>,
  signalAutoSeconds: <?= max(0, (int)sma_setting('signal_auto_seconds', '60')) ?>,
  vapidKey: <?= sma_js(sma_setting('alerts_enabled', '1') === '1' ? WebPush::publicKey() : '') ?>,
  isMember: <?= sma_js($member !== null) ?>,
  emailAlertsOn: <?= sma_js($member !== null ? (function () use ($member) {
      $st = \SignalMasterAi\Database::pdo()->prepare('SELECT enabled FROM member_alerts WHERE member_id = ?');
      $st->execute([$member['id']]);
      return (bool)$st->fetchColumn();
  })() : false) ?>,
  // Whether the site can send mail at all. Ticking the box and being told
  // nothing is the state a member reads as "done" and then waits in for an
  // email that was never going to be sent.
  emailConfigured: <?= sma_js(\SignalMasterAi\Mailer::enabled()) ?>,
  // symbol => the timeframes it is read on, so the picker can follow the coin.
  symbolTfs: <?= sma_js($symbolTfs) ?>,
  allTfs: <?= sma_js($intervals) ?>,
  siteName: <?= sma_js($siteName) ?>,
  upColor: <?= sma_js($upCol) ?>,
  downColor: <?= sma_js($downCol) ?>,
<?php
// Bulk "watch all coins": does this viewer qualify, and what's their pair cap?
$bulkNeed = sma_setting('bulk_watch_tier', 'paid');
$bulkOk = sma_setting('bulk_watch_enabled', '1') === '1' && (
    MemberAuth::meetsTier($bulkNeed, $viewerTier)
);
$topOk = sma_setting('top_watch_enabled', '1') === '1'
    && MemberAuth::meetsTier(sma_setting('top_watch_tier', 'paid'), $viewerTier);
?>
  bulkWatch: <?= sma_js($bulkOk) ?>,
  <?php // The viewer's own limit, from the one place that defines it. This
        // used to read bulk_watch_max directly and fall back to a literal 12,
        // so the number the browser enforced and the number the server
        // enforced were two different settings - the client would let a member
        // add pairs the save then silently dropped. 0 (no limit) becomes a
        // number far past any real watchlist, because the client works in
        // numbers rather than special cases. ?>
  alertMaxPairs: <?= \SignalMasterAi\Watchlists::cap($viewerTier) ?>,
  topWatch: <?= sma_js($topOk) ?>,
  topWatchCount: <?= (int)sma_setting('top_watch_count', '10') ?>
};
</script>
<script src="<?= sma_asset('assets/app.js') ?>"></script>
<script src="assets/ui.js?v=<?= @filemtime(__DIR__ . '/assets/ui.js') ?: 1 ?>" defer></script>
</body>
</html>
