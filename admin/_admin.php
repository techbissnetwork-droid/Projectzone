<?php
declare(strict_types=1);

// NOT A PAGE. This file is included by others and has no business answering a
// request of its own: fetched directly it still runs a bootstrap, opens the
// database and executes whatever its top level does, and on a degraded install
// the admin bootstrap below renders a missing-files report - internal paths,
// to whoever asked. It returns nothing useful today; the guard is so that
// stays true after the next edit rather than by luck.
//
// SCRIPT_FILENAME is what the server decided to run. If that is this file,
// this file was the request.
if (isset($_SERVER['SCRIPT_FILENAME'])
    && realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)) {
    http_response_code(404);
    exit;
}

/** Shared admin bootstrap + layout helpers. */

$config = require __DIR__ . '/../src/bootstrap.php';

use SignalMasterAi\Auth;

Auth::start($config);

/**
 * Refuse to pretend the panel is fine when half of it did not upload.
 *
 * Here, before any page body runs, because by the time a page has started
 * rendering it is too late: the dashboard calls Maintenance, Maintenance calls
 * Mailer, and if Mailer.php is absent the autoloader throws mid-page. The
 * operator gets a 500 - or on a production host with display_errors off, a
 * blank white page - naming at most the one class that happened to be asked
 * for first. Five files were missing on the install that prompted this; the
 * fatal would have named one of them.
 *
 * So the check runs first and, when anything is missing, this IS the page:
 * every absent file, what each one costs, and how to fix it. A panel that
 * cannot load its own code has nothing more useful to show.
 *
 * Cached for six hours, so the healthy case - which is every install that
 * uploaded cleanly, forever - costs one cache read and renders nothing.
 *
 * Two conditions on it, both learned by getting them wrong:
 *
 * NOT ON login.php. This file is included there too, so an unconditional
 * guard replaces the login form with itself - and then nobody can ever reach
 * the state that lets them see it. The form has to keep working.
 *
 * ONLY WHEN LOGGED IN. The list names which parts of this site are currently
 * dead, and that is reconnaissance for anyone who asks. Logged out, the page's
 * own requireLogin() sends them to the form as usual and this says nothing.
 * An operator is one login away from the full list; a stranger is not.
 */
$smaSelf = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
$smaMissing = ($smaSelf === 'login.php' || $smaSelf === 'logout.php' || !Auth::check())
    ? []
    : \SignalMasterAi\Integrity::missingClassesCached(($_GET['recheck'] ?? '') === 'files');
if ($smaMissing) {
    $smaCosts = \SignalMasterAi\Integrity::consequences($smaMissing);
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    ?><!doctype html>
<html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Files are missing - admin</title>
<?= \SignalMasterAi\Chrome::fonts() ?>
<style>
<?= \SignalMasterAi\Chrome::css() ?>
/* A 503 is read at the worst possible moment, so it gets the same design
   as everything else rather than a hand-rolled red box. */
body{align-items:flex-start}
.box{max-width:640px;margin:24px auto;background:var(--surface);border:1px solid var(--border);border-left:2px solid var(--down);border-radius:0 12px 12px 0;padding:22px 24px}
.box h1{font-size:24px;margin-bottom:10px}
ul{padding-left:20px;margin:10px 0}li{margin:6px 0}
</style></head><body>
<div class="box">
  <h1><?= count($smaMissing) ?> file(s) did not make it onto this server</h1>
  <p>The admin panel cannot run without them:</p>
  <ul>
    <?php foreach (array_keys($smaMissing) as $smaClass): ?>
      <li><code>src/<?= sma_e($smaClass) ?>.php</code></li>
    <?php endforeach; ?>
  </ul>
  <?php if ($smaCosts): ?>
    <p class="muted">While they are missing:</p>
    <ul class="muted">
      <?php foreach ($smaCosts as $smaCost): ?><li><?= sma_e($smaCost) ?></li><?php endforeach; ?>
    </ul>
  <?php endif; ?>
  <p>Every one of these ships in the release zip. This is what an upload or unzip
     that stopped part-way leaves behind &mdash; re-upload the whole
     <code>src/</code> folder, overwriting what is there, then check again.</p>
  <p class="muted">Nothing else needs changing: your database, settings and members
     are untouched by this.</p>
  <a class="btn" href="?recheck=files">Check again</a>
</div>
</body></html>
    <?php
    exit;
}

/**
 * @param string $lede One line saying what this page is for, printed under the
 *                     title. Optional only so a page can be converted without
 *                     breaking; every page in this panel passes one.
 */
function admin_header(string $title, string $active, string $lede = ''): void
{
    $siteName = sma_setting('site_name', 'SignalMasterAi');
    // Grouped by what an operator came to do, not by which file it lives in -
    // and grouped the SAME way the settings page is, so there is one
    // arrangement of this admin panel to learn instead of two.
    //
    // Every group that has settings of its own links straight to them. The
    // alternative was what this had: "All settings", one page, a hundred and
    // forty fields, and an operator who knew exactly which knob they wanted
    // and no way to guess which of eight tabs it was filed under.
    // The panel's map lives in Panel::CATEGORIES - see there for why it was
    // rewritten by subject rather than regrouped again.
    $smaHiddenNav = \SignalMasterAi\Master::hiddenAdminPages();
    ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= sma_e($title) ?> - <?= sma_e($siteName) ?> admin</title>
<?php if (sma_setting('custom_logo') !== ''): ?>
<link rel="icon" href="<?= sma_e(sma_logo('../')) ?>">
<?php else: ?>
<link rel="icon" type="image/svg+xml" href="../assets/brand/logo.svg">
<link rel="icon" type="image/png" sizes="32x32" href="../assets/brand/favicon-32.png">
<link rel="apple-touch-icon" href="../assets/brand/apple-touch-icon.png">
<?php endif; ?>
<?php // Same two faces as the public site, minus the display serif - there is
      // no serif anywhere in the panel. Behind the same setting, so turning
      // webfonts off turns them off everywhere rather than in one half of the
      // application.
      if (sma_setting('webfonts', '1') === '1'): ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter+Tight:wght@400;500&amp;family=JetBrains+Mono:wght@400;500;700&amp;display=swap">
<?php endif; ?>
<link rel="stylesheet" href="admin.css?v=<?= @filemtime(__DIR__ . '/admin.css') ?: 1 ?>">
<?php // Scroll progress, click ripples and accordion fades live in ui.js and
      // are used by every public page. The admin panel carried its own copy of
      // all three - and ui.js already carries a comment about index.php and
      // charts.php having done exactly that before it, which is how you end up
      // with three. Deferred so it never blocks the panel rendering. ?>
<script src="../assets/ui.js?v=<?= @filemtime(__DIR__ . '/../assets/ui.js') ?: 1 ?>" defer></script>
</head>
<body>
<div class="admin-shell">
  <aside class="sidebar" id="adminSidebar">
    <div class="side-brand">
      <a href="index.php" class="side-brand-link" aria-label="Admin dashboard">
        <?php // Same mark as the public site, same rule: an uploaded logo
              // wins, otherwise the three animated bars. The panel already
              // checks custom_logo once above for the favicon; reused here
              // rather than a second independent check drifting from it. ?>
        <?php if (sma_setting('custom_logo') !== ''): ?>
          <img src="<?= sma_e(sma_logo('../')) ?>" alt="" class="side-logo">
        <?php else: ?>
          <svg class="side-logo logo-mark" viewBox="0 0 24 24" aria-hidden="true">
            <rect x="2" y="9" width="4" height="10" rx="1.5" fill="var(--indigo, var(--accent))"/>
            <rect x="10" y="4" width="4" height="16" rx="1.5" fill="var(--long, var(--up))"/>
            <rect x="18" y="12" width="4" height="8" rx="1.5" fill="var(--brass)"/>
          </svg>
        <?php endif; ?>
        <div class="side-name"><?= sma_e($siteName) ?><small>admin panel</small></div>
      </a>
      <button type="button" class="nav-toggle" id="admNavToggle" aria-controls="admMenu" aria-expanded="false">
        <span class="nav-toggle-bars" aria-hidden="true"><i></i><i></i><i></i></span> Menu
      </button>
    </div>
    <div class="side-menu" id="admMenu">
      <nav>
        <?php // Dropdowns, one per subject, defined once in Panel::CATEGORIES and
              // shared with the landing grid so the two cannot drift.
              //
              // A tree in a sidebar makes you read every branch to find one
              // leaf. A short row of subjects, each opening to its own list,
              // is the same information at a tenth of the reading. ?>
        <?php $smaCats = \SignalMasterAi\Panel::all(); ?>
        <?php $smaHere = \SignalMasterAi\Panel::categoryOf(basename($_SERVER['SCRIPT_NAME'] ?? '')); ?>
        <?php // Dashboard used to open this row too - it's the first tab on
              // the bottom bar now (see .acct-dock below), so the subject row
              // starts at the first real subject instead of naming the same
              // destination twice. ?>
        <?php foreach ($smaCats as $cKey => [$cIcon, $cTitle, $cBlurb, $cItems]): ?>
          <?php // A subject with one destination is a link, not a dropdown.
                // Opening a menu to reveal a single item is a tap that buys
                // nothing, and it is a promise of choice the menu cannot keep. ?>
          <?php if (count($cItems) === 1): ?>
            <a class="cat-home <?= $cKey === $smaHere ? 'on' : '' ?>"
               href="<?= $cItems[0][1] ?>" title="<?= sma_e($cItems[0][2]) ?>">
              <i aria-hidden="true"><?= $cIcon ?></i> <?= sma_e($cTitle) ?></a>
            <?php continue; ?>
          <?php endif; ?>
          <details class="cat-drop" data-cat="<?= sma_e($cKey) ?>">
            <summary class="cat-btn <?= $cKey === $smaHere ? 'here' : '' ?>">
              <i aria-hidden="true"><?= $cIcon ?></i> <?= sma_e($cTitle) ?></summary>
            <div class="cat-menu">
              <p class="cat-blurb"><?= sma_e($cBlurb) ?></p>
              <?php foreach ($cItems as [$iLabel, $iHref, $iWhat]): ?>
                <a href="<?= $iHref ?>">
                  <strong><?= sma_e($iLabel) ?></strong>
                  <span><?= sma_e($iWhat) ?></span></a>
              <?php endforeach; ?>
            </div>
          </details>
        <?php endforeach; ?>
      </nav>
    </div>
  </aside>
  <?php // Who is signed in, and the couple of things every operator jumps out
        // to - off the header and on a bar of its own, full width, icon over
        // label: the exact shape of the public site's own .tabbar (see
        // style.css), not a shrunken corner version of it. Same reason that
        // bar gives: a row under the thumb reads at a glance, where the same
        // links buried in the subject row do not.
        //
        // Fixed rather than in the flow, so the bar stays put on a page that
        // scrolls. The account tab is a `<details>`, the same native
        // disclosure the subject dropdowns already use, so it opens and
        // closes without a script of its own; the outside-click handler
        // further down (next to the sidebar drawer's own) is the only bit
        // that IS bespoke, because a native <details> does not close itself
        // on an outside click. ?>
  <nav class="acct-dock" aria-label="Quick links">
    <a class="acct-tab<?= $active === 'dashboard' ? ' on' : '' ?>" href="index.php"
       <?= $active === 'dashboard' ? 'aria-current="page"' : '' ?>>
      <svg class="acct-tab-ic" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"
           stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M2 8l6-5 6 5M4 7v6h8V7"/>
      </svg>
      <span>Dashboard</span>
    </a>
    <a class="acct-tab" href="../charts.php" target="_blank">
      <svg class="acct-tab-ic" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"
           stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M2 12l3.5-4L8 10l5.5-6"/>
      </svg>
      <span>Live charts</span>
    </a>
    <a class="acct-tab" href="../index.php" target="_blank">
      <svg class="acct-tab-ic" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"
           stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M4 12L12 4M6 4h6v6"/>
      </svg>
      <span>Landing</span>
    </a>
    <details class="acct-chip" id="acctChip">
      <summary class="acct-tab-sum">
        <svg class="acct-tab-ic" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"
             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <circle cx="8" cy="6" r="2.8"/>
          <path d="M3 13.5c.8-3 3-4.5 5-4.5s4.2 1.5 5 4.5"/>
        </svg>
        <span><?= sma_e($_SESSION['admin_user'] ?? 'Account') ?></span>
      </summary>
      <div class="acct-menu">
        <div class="acct-name"><?= sma_e($_SESSION['admin_user'] ?? '') ?></div>
        <a href="logout.php">Log out</a>
      </div>
    </details>
  </nav>
  <main class="content">
    <?php
    // Cron health: once cron has run at least once, warn everywhere if it
    // stops (alerts + background signals degrade to the traffic fallback).
    //
    // EVERYWHERE EXCEPT THE DASHBOARD. The dashboard raises the same warning
    // itself, with the hint that names the actual cron command, so on that one
    // page the operator was reading it twice - and this copy ends "check the
    // cron job on the dashboard", which on the dashboard is a link to the page
    // you are already looking at. A warning repeated next to itself does not
    // read as twice as urgent; it reads as a panel that does not know what it
    // has already said, and it is the first thing to start ignoring.
    //
    // Keyed on the script rather than on a flag the dashboard sets, because
    // this runs before any page body: whatever admin/index.php knows about its
    // own alerts, it has not worked it out yet.
    $cronLast = (int)sma_setting('cron_last_run', '0');
    $onDash = basename((string)($_SERVER['SCRIPT_NAME'] ?? '')) === 'index.php';
    if (!$onDash && $cronLast > 0 && time() - $cronLast > 600): ?>
      <div class="error"><strong>Cron looks stalled</strong> - last run
        <?= (int)floor((time() - $cronLast) / 60) ?> min ago. Push/email/Telegram alerts and
        background signals now rely on visitor traffic only. Check the cron job on the
        <a href="index.php" style="color:inherit">dashboard</a>.</div>
    <?php endif; ?>
    <h1><?= sma_e($title) ?></h1>
    <?php if ($lede !== ''): ?>
      <p class="page-lede"><?= $lede ?></p>
    <?php endif; ?>
    <?php
}

/**
 * Open a titled block. The whole panel is built out of these: a heading, an
 * optional line explaining it, then whatever the block contains.
 *
 * The markup is trivial - that is the point. Nine pages hand-wrote
 * <div class="panel"> against a class this stylesheet never defined, four
 * opened with a bare <h2> and no container at all, and the rest used
 * .form-card whether or not there was a form in them. Going through one
 * function is what stops the next page from inventing a tenth shape.
 */
function admin_panel(string $title, string $lede = '', string $id = '', string $class = ''): void
{
    echo '<section class="panel' . ($class !== '' ? ' ' . sma_e($class) : '') . '"'
        . ($id !== '' ? ' id="' . sma_e($id) . '"' : '') . '>';
    if ($title !== '') {
        echo '<h2>' . sma_e($title) . '</h2>';
    }
    if ($lede !== '') {
        echo '<p class="panel-lede">' . $lede . '</p>';   // trusted markup: links
    }
}

/**
 * Split a page's panels into sections, the way Settings already does.
 *
 * Billing was five subjects stacked into one four-thousand-pixel scroll -
 * plans, payment methods, gateway credentials, coupons, activation keys - and
 * the only way to reach the fourth was to scroll past the first three every
 * time. Settings had already solved this; the answer was not to invent a
 * second arrangement for the same admin panel but to use that one.
 *
 * So the classes here are Settings' classes on purpose: the strip looks and
 * behaves identically, and an operator learns this panel once. The script is
 * kept separate from Settings' because that page's version also drives a
 * search box and a sticky save bar; this is the tab behaviour alone.
 *
 * Each tab owns exactly one panel, matched by id, which is why no panel needs
 * marking up differently - admin_panel() already takes the id.
 *
 * PROGRESSIVE ENHANCEMENT: nothing is hidden in the markup. With no
 * JavaScript every panel is on the page exactly as before, which is the state
 * this replaced and is still a working page.
 *
 * @param array<int,array{id:string,icon:string,label:string,lede:string}> $tabs
 */
function admin_tabs(array $tabs): void
{
    if (!$tabs) {
        return;
    }
    $ids = array_column($tabs, 'id');
    ?>
<nav class="settings-tabs" id="panelTabs" aria-label="Sections on this page">
  <?php foreach ($tabs as $t): ?>
  <a href="#<?= sma_e($t['id']) ?>" data-lede="<?= sma_e($t['lede']) ?>"><i><?= $t['icon'] ?></i><b><?= sma_e($t['label']) ?></b></a>
  <?php endforeach; ?>
</nav>
<p class="tab-lede" id="panelLede"></p>
<script>
(function () {
  var ids = <?= json_encode($ids, JSON_UNESCAPED_SLASHES) ?>;
  var bar = document.getElementById('panelTabs');
  var lede = document.getElementById('panelLede');
  if (!bar) { return; }
  var links = bar.querySelectorAll('a');

  function show(id) {
    ids.forEach(function (p) {
      var el = document.getElementById(p);
      if (el) { el.style.display = p === id ? '' : 'none'; }
    });
    links.forEach(function (a) {
      var on = a.getAttribute('href') === '#' + id;
      a.classList.toggle('on', on);
      a.setAttribute('aria-current', on ? 'true' : 'false');
      if (on && lede) { lede.textContent = a.getAttribute('data-lede') || ''; }
      // The strip scrolls on a phone, so arriving at billing.php#keys with it
      // scrolled left means the one section asked for is the one off screen.
      // scrollLeft rather than scrollIntoView, which would move the page too.
      if (on && bar.scrollWidth > bar.clientWidth) {
        var l = a.offsetLeft - bar.offsetLeft;
        if (l < bar.scrollLeft || l + a.offsetWidth > bar.scrollLeft + bar.clientWidth) {
          bar.scrollLeft = Math.max(0, l - 12);
        }
      }
    });
  }

  // The hash is either a section or something inside one - every POST on
  // these pages redirects back to an anchor, and landing on #keys after
  // generating keys has to open the section those keys are in.
  function current() {
    var h = (location.hash || '').slice(1);
    if (!/^[\w-]+$/.test(h)) { return ids[0]; }
    if (ids.indexOf(h) !== -1) { return h; }
    var el = document.getElementById(h);
    var panel = el ? el.closest('section.panel[id]') : null;
    return (panel && ids.indexOf(panel.id) !== -1) ? panel.id : ids[0];
  }

  // WAIT FOR THE PANELS.
  //
  // This strip is printed above the panels it controls, so at the moment this
  // script parses, getElementById() finds none of them and the first show()
  // hides nothing: the tab reads "Plans" over all five sections stacked as
  // before. It looked right in every later interaction, because by then the
  // document was complete - which is exactly how a first-paint bug survives
  // testing. Caught by counting visible panels on a cold load.
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { show(current()); });
  } else {
    show(current());
  }
  window.addEventListener('hashchange', function () { show(current()); });
})();
</script>
    <?php
}

function admin_panel_end(): void
{
    echo '</section>';
}

/**
 * "Nothing here yet", said the same way everywhere.
 *
 * Each list in this panel had its own wording for empty and half of them just
 * said "No rows." - which tells an operator nothing about whether that is
 * normal, whether something is broken, or what would put a row there. The
 * second argument is the part that matters: what fills this.
 */
function admin_empty(string $what, string $how = ''): void
{
    echo '<div class="empty"><b>' . sma_e($what) . '</b>' . $how . '</div>';
}

function admin_footer(): void
{
    ?>
  </main>
  <?php // THE BUILD STAMP THAT NOTHING DISPLAYED.
        //
        // src/Version.php exists for exactly one reason, in its own words: a
        // fix and a report of the same fault can only be told apart if both
        // sides know what is running. "Still broken" after an upload means
        // either the fix is wrong or the fix is not there, and there was
        // nothing on this site that answered which.
        //
        // It was never rendered anywhere. The constant was bumped release
        // after release and no operator could see it - so the class solved
        // its problem for the person who wrote it and for nobody else. It
        // goes here, on every admin page, beside the file date it checks
        // itself against. ?>
  <p style="text-align:center;opacity:.55;font-size:12px;margin:26px 0 10px">
    Build <?= sma_e(\SignalMasterAi\Version::stamp()) ?>
  </p>
</div>
<script>
// A BLOCK THAT ONLY APPLIES WHILE ANOTHER CONTROL SAYS SO, SAYS SO.
//
// The SMTP host, port, username and password sat fully lit under "Email
// sending: Disabled"; the Telegram token under Telegram off; the trial length
// under a trial that is not running. Six or seven controls per card that do
// nothing, with nothing on screen admitting it - and an operator who fills
// them in and walks away has configured something that is not switched on.
//
// A NOTE, NOT A DIM AND NOT A HIDE. Dimming with opacity halves the contrast
// of the very text somebody is being asked to read, which is a worse fault
// than the one it fixes. Hiding would stop the useful thing people actually
// do here - fill the credentials in first, switch it on second. So the block
// keeps its ink and gains one line saying what it is waiting for.
//
// Declared in the markup as data-dep / data-dep-value / data-dep-note, so a
// card added later gets this by describing itself rather than by finding this
// script. Multiple accepted values are separated by a pipe.
(function () {
  var deps = [].slice.call(document.querySelectorAll('[data-dep]'));
  if (!deps.length) { return; }
  function valueOf(name) {
    var el = document.querySelector('[name="' + name + '"]');
    if (!el) { return null; }
    if (el.type === 'checkbox') { return el.checked ? '1' : '0'; }
    return el.value;
  }
  function apply() {
    deps.forEach(function (d) {
      var want = (d.getAttribute('data-dep-value') || '1').split('|');
      var have = valueOf(d.getAttribute('data-dep'));
      var on = have !== null && want.indexOf(have) !== -1;
      var note = d.querySelector('.dep-note');
      if (!note) {
        var text = d.getAttribute('data-dep-note');
        if (!text) { return; }
        note = document.createElement('p');
        note.className = 'dep-note';
        note.textContent = text;
        d.insertBefore(note, d.firstChild);
      }
      note.hidden = on;
    });
  }
  // One listener on the document rather than one per governing control: the
  // governing control is named in the markup and may not exist yet when this
  // runs, and re-reading four values on a change costs nothing.
  document.addEventListener('change', apply);
  apply();
})();
(function () {
  var side = document.getElementById('adminSidebar');
  var btn = document.getElementById('admNavToggle');
  if (!side || !btn) return;
  btn.addEventListener('click', function (e) {
    e.stopPropagation();
    var open = side.classList.toggle('open');
    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
  });
  document.addEventListener('click', function (e) {
    if (side.classList.contains('open') && !side.contains(e.target)) {
      side.classList.remove('open');
      btn.setAttribute('aria-expanded', 'false');
    }
  });
})();
// Same reasoning as the sidebar drawer just above: a native <details> does
// not close itself when the click lands somewhere else on the page, and left
// alone the account menu would stay open over whatever the operator clicks
// next.
(function () {
  var chip = document.getElementById('acctChip');
  if (!chip) { return; }
  document.addEventListener('click', function (e) {
    if (chip.open && !chip.contains(e.target)) { chip.open = false; }
  });
})();
// How much of the panel header is on screen right now, published as
// --adm-head so anything else that wants to stick can sit under it rather
// than behind it.
//
// It cannot be a constant, for two reasons. The subject row is one line on a
// wide screen and wraps to two around 1300px, so the height itself moves. And
// the header only really sticks on wide screens: below 860px the stylesheet
// puts overflow-x: hidden on <html> to guarantee no sideways scroll, which
// also stops position: sticky working anywhere inside it - so on a phone the
// header scrolls away and anything offset by its height would float with a
// band of empty page above it.
//
// Measuring the bottom edge instead of the height covers both: it is the full
// height while the header is up there, and it falls to 0 as the header
// leaves. Read inside requestAnimationFrame so a fast scroll costs one
// measurement per frame rather than one per event.
(function () {
  var head = document.getElementById('adminSidebar');
  if (!head) { return; }
  var queued = false;
  function apply() {
    queued = false;
    var b = head.getBoundingClientRect().bottom;
    document.documentElement.style.setProperty('--adm-head', Math.max(0, Math.round(b)) + 'px');
  }
  function sync() {
    if (queued) { return; }
    queued = true;
    requestAnimationFrame(apply);
  }
  apply();
  addEventListener('scroll', sync, { passive: true });
  addEventListener('resize', sync);
  addEventListener('load', sync);
  if (window.ResizeObserver) { new ResizeObserver(sync).observe(head); }
})();
</script>
</body>
</html>
    <?php
}

/**
 * Set or read the one-shot admin message. $type distinguishes a completed
 * action from one that did nothing ("not enough data to decide"), which the
 * engine tools need to report honestly rather than dressing up as success.
 */
function flash(?string $msg = null, string $type = 'ok'): ?array
{
    if ($msg !== null) {
        $_SESSION['flash'] = ['msg' => $msg, 'type' => in_array($type, ['ok', 'warn'], true) ? $type : 'ok'];
        return null;
    }
    $m = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    // Tolerate the old string-only format left in a session across an upgrade.
    return is_string($m) ? ['msg' => $m, 'type' => 'ok'] : $m;
}

function show_flash(): void
{
    $m = flash();
    if ($m !== null) {
        echo '<div class="flash ' . sma_e($m['type']) . '">' . sma_e($m['msg']) . '</div>';
    }
}

return $config;
