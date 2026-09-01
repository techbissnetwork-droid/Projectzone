<?php
declare(strict_types=1);

/**
 * Delete data: one screen, one form, one list of choices.
 *
 * Everything destructive used to be a button on the page that happened to
 * display the thing - coins on Coins, history on Signals, the market cache
 * under Settings - and each of them decided for the operator what else went
 * along. The same four kinds of data were deletable from three pages with
 * three different sets of side effects, and none of them said how much they
 * were about to remove.
 *
 * The question is always the same two: WHICH COINS, and WHICH KINDS OF DATA.
 * Asked once here, with a live count against every line, and executed by
 * Purge, which is also what the remaining in-place buttons call.
 */

$config = require __DIR__ . '/_admin.php';

use SignalMasterAi\Auth;
use SignalMasterAi\Database;
use SignalMasterAi\Purge;

Auth::requireLogin();
$pdo = Database::pdo();

$symbols = array_column($pdo->query('SELECT symbol FROM symbols ORDER BY symbol')->fetchAll(), 'symbol');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf();
    // "Update the counts" is a submit button on the same form, so it arrives
    // here as a post with act=count. It re-renders with the selection in the
    // query string and deletes nothing - a second form would have meant a
    // second copy of the coin list, and nested forms are not a thing.
    if (($_POST['act'] ?? '') === 'count') {
        $picked = array_values(array_intersect((array)($_POST['symbols'] ?? []), $symbols));
        $qs = $picked ? '?' . http_build_query(['symbols' => $picked]) : '';
        header('Location: data.php' . $qs);
        exit;
    }
    if (($_POST['act'] ?? '') === 'purge') {
        $scope = (string)($_POST['scope'] ?? 'all');
        // Only coins that are actually on the list. A name that is not is not a
        // scope, and falling back to "every coin" on a bad value would be the
        // worst failure this page could have - so anything unrecognised is
        // dropped, and a selection that ends up empty deletes nothing.
        $picked = $scope === 'some'
            ? array_values(array_intersect((array)($_POST['symbols'] ?? []), $symbols))
            : null;
        $kinds = array_keys((array)($_POST['kind'] ?? []));

        if ($scope === 'some' && !$picked) {
            flash('No coins were selected, so nothing was deleted.', 'warn');
        } elseif (!$kinds) {
            flash('Nothing was ticked, so nothing was deleted.', 'warn');
        } else {
            $gone = Purge::run($kinds, $picked);
            $what = Purge::describe($gone);
            // Named when there are few enough to read, counted when there are
            // not - "deleted from 23 coins" is what the operator needs in the
            // change log, not a paragraph of tickers.
            $where = $picked === null
                ? 'every coin'
                : (count($picked) <= 4 ? implode(', ', $picked) : count($picked) . ' coins');
            \SignalMasterAi\Audit::log('data.purge', $where, '',
                'deleted ' . $what . ($picked !== null && count($picked) > 4
                    ? ' (' . implode(', ', $picked) . ')' : ''));
            // Plain text, no markup: show_flash escapes what it is given - and
            // must, since these strings carry coin names and counts - so the
            // <strong> that used to be here printed as literal &lt;strong&gt;
            // tags in the one message that confirms a deletion.
            flash("Deleted from $where: $what.");
        }
        header('Location: data.php');
        exit;
    }
}

// Counts for the coins currently selected, so the numbers beside each line
// describe what THIS run would remove rather than the install as a whole.
// Carried in the query string so the counting survives a page load with no
// JavaScript - the script below only saves the round trip.
$pick = array_values(array_intersect((array)($_GET['symbols'] ?? []), $symbols));
$counts = Purge::counts($pick ?: null);
$csrf = Auth::csrfToken();

admin_header('Delete data', 'data',
    'One place to remove things, and one place to see how much of each there is. '
    . 'Every line is a separate decision - nothing here takes anything with it that you did not tick.');
show_flash();
?>

<?php admin_panel('What to delete', '', '', 'bad'); ?>
<form method="post" action="data.php" id="purgeForm">
  <input type="hidden" name="csrf" value="<?= $csrf ?>">
  <?php // The submit button at the foot carries act=purge; "Update the counts"
        // carries act=count. A hidden act here would win over neither
        // consistently across browsers, so there is not one. ?>

  <?php // Scope first, because it changes what every number below means. ?>
  <h3 style="margin:0 0 8px">Which coins</h3>
  <?php // ONE, SEVERAL, OR ALL.
        //
        // This was a dropdown that could only name one, so retiring a dozen
        // delisted pairs meant twelve passes through a form headed "this cannot
        // be undone". A checkbox per coin rather than a multi-select: a native
        // multi-select needs ctrl-click to add a second item and is close to
        // unusable on a phone, which is where half of this panel gets read. ?>
  <label class="chk" style="display:flex;gap:8px;align-items:center;margin-bottom:6px">
    <input type="radio" name="scope" value="all" <?= !$pick ? 'checked' : '' ?> style="width:auto">
    <span>Every coin<span class="hint" style="display:block">All
      <strong><?= count($symbols) ?></strong> on the list.</span></span>
  </label>
  <label class="chk" style="display:flex;gap:8px;align-items:center">
    <input type="radio" name="scope" value="some" id="scopeSome" <?= $pick ? 'checked' : '' ?> style="width:auto">
    <span>Only the coins I tick
      <span class="hint" id="pickCount" style="display:block"><?= $pick
        ? '<strong>' . count($pick) . '</strong> selected'
        : 'none selected yet' ?></span></span>
  </label>
  <?php // THE LIST BELONGS TO THE SECOND CHOICE, SO IT APPEARS WITH IT.
        //
        // Thirty-seven coin names sat under "Every coin" with the radio on
        // "Every coin", which reads as a contradiction: the screen is offering
        // a choice it has just been told is not being made. Worse, it invites
        // a tick - and ticking silently moves the scope, so the operator ends
        // up in a different mode from the one they set, on the one form in
        // this panel that cannot be undone.
        //
        // Hidden by CSS on the radio's own state (:has), so it is right before
        // a line of JavaScript runs and stays right if none does; the script
        // below is the fallback for browsers without :has, and the auto-switch
        // on tick stays as the safety net it was always meant to be. ?>
  <div class="coin-pick">
    <p class="hint" style="margin:0 0 6px">
      <a href="#" id="pickAll">Select all</a> &middot;
      <a href="#" id="pickNone">Select none</a> &middot;
      <button type="submit" name="act" value="count" class="btn small gray">Update the counts</button>
    </p>
    <div class="coin-pick-list">
      <?php foreach ($symbols as $s): ?>
        <label class="coin-pick-item">
          <input type="checkbox" name="symbols[]" value="<?= sma_e($s) ?>"
                 <?= in_array($s, $pick, true) ? 'checked' : '' ?> style="width:auto">
          <span><?= sma_e($s) ?></span>
        </label>
      <?php endforeach; ?>
    </div>
  </div>

  <h3 style="margin:14px 0 8px">Which data<?= $pick
      ? ' &mdash; ' . sma_e(count($pick) <= 3 ? implode(', ', $pick) : count($pick) . ' coins')
      : '' ?></h3>
  <?php // THREE COLUMNS DO NOT FIT ON A PHONE.
        //
        // Measured at 375px: the hint under each kind was clipped mid-word at
        // the container edge and the "How much" column - the count that is the
        // entire reason this table exists, and the number an operator checks
        // before deleting anything - was off the right of the screen entirely.
        // A table that hides its own figures is a list with extra rules.
        //
        // .purge-grid turns each row into a block below 720px: the tick sits
        // in the margin, the name and the hint wrap, and the count is a line
        // of its own that says what it is counting. ?>
  <table class="grid purge-grid">
    <tr><th style="width:34px"></th><th>Kind</th><th style="width:110px">How much</th></tr>
    <?php foreach (Purge::KINDS as $key => [$label, $table, $perCoin, $hint]):
          $n = (int)($counts[$key] ?? 0);
          $pub = in_array($key, Purge::PUBLIC_FACING, true); ?>
      <tr>
        <td><input type="checkbox" name="kind[<?= sma_e($key) ?>]" value="1"
                   id="k_<?= sma_e($key) ?>" style="width:auto"<?= $n === 0 ? ' disabled' : '' ?>></td>
        <td><label for="k_<?= sma_e($key) ?>" style="cursor:pointer">
              <strong><?= sma_e($label) ?></strong>
              <?php if ($pub): ?><span class="hint" style="color:var(--warn-text)">&nbsp;changes the public record</span><?php endif; ?>
              <span class="hint" style="display:block"><?= $hint ?></span></label></td>
        <td class="num"><?= $n > 0 ? number_format($n) : '<span class="hint">none</span>' ?></td>
      </tr>
    <?php endforeach; ?>
  </table>

  <?php // Written where the decision is made rather than in a help page.
        // Two of these pairs are the ones an operator gets wrong, and getting
        // them wrong is not recoverable. ?>
  <p class="hint" style="margin-top:12px">
    <strong>Signals are the evidence; the learning is the conclusion.</strong> Clearing the history
    and keeping the learning leaves the engine acting on conclusions it can no longer revise -
    it revises from settled signals, and there would be none. Clearing the learning and keeping
    the history is the useful pair: a fresh start, with the record that shows whether it worked.
  </p>
  <p class="hint">
    <strong>Deleting the coin is reversible; deleting its signals is not.</strong> A coin comes back
    by adding it again. Its settled calls are what this site published, and the track record already
    leaves out coins that are gone rather than counting them - so retiring a coin costs you nothing
    on that page unless you tick the history as well.
  </p>

  <button class="btn danger" type="submit" name="act" value="purge"
          style="margin-top:10px">Delete the ticked data</button>
</form>
<script<?= sma_nonce() ?>>
(function () {
  // THE CONFIRM HAS TO KNOW WHICH BUTTON WAS PRESSED.
  //
  // It was an inline onsubmit, which fires for every submit - so "Update the
  // counts", a button that deletes nothing, asked "Delete the ticked data?
  // This cannot be undone." Answering yes then merely recounted, which teaches
  // an operator to dismiss the one dialog on this page that matters. Moved
  // into the handler, where the submitter is known. Nothing is lost with
  // JavaScript off: an inline onsubmit is JavaScript too.
  var form = document.getElementById('purgeForm');
  if (form) {
    var last = null;
    form.addEventListener('click', function (e) {
      var b = e.target.closest && e.target.closest('button[type="submit"]');
      if (b) { last = b; }
    }, true);
    form.addEventListener('submit', function (e) {
      var who = e.submitter || last;
      if (who && who.value === 'count') { return; }
      if (!confirm('Delete the ticked data? This cannot be undone.')) { e.preventDefault(); }
    });
  }

  var boxes = [].slice.call(document.querySelectorAll('input[name="symbols[]"]'));
  var some = document.getElementById('scopeSome');
  var count = document.getElementById('pickCount');
  if (!boxes.length) { return; }

  // Show the coin list only for the choice it belongs to. The stylesheet
  // already does this with :has; this is for the browsers that do not have it,
  // and it is written to agree with the CSS rather than to fight it - both
  // read the same radio.
  var pickBox = document.querySelector('.coin-pick');
  var supportsHas = false;
  try { supportsHas = CSS.supports('selector(:has(*))'); } catch (e) { supportsHas = false; }
  function scopeSync() {
    if (!pickBox || supportsHas) { return; }
    pickBox.style.display = (some && some.checked) ? '' : 'none';
  }
  [].slice.call(document.querySelectorAll('input[name="scope"]')).forEach(function (r) {
    r.addEventListener('change', scopeSync);
  });
  scopeSync();

  // Ticking a coin moves the scope with it. Choosing coins and leaving the
  // radio on "every coin" is the one way this form could delete the whole
  // install by accident, so the two cannot be out of step.
  function sync() {
    var n = boxes.filter(function (b) { return b.checked; }).length;
    if (some && n > 0) { some.checked = true; scopeSync(); }
    if (count) {
      count.innerHTML = n > 0 ? '<strong>' + n + '</strong> selected' : 'none selected yet';
    }
  }
  boxes.forEach(function (b) { b.addEventListener('change', sync); });

  function pick(on) {
    return function (e) {
      e.preventDefault();
      boxes.forEach(function (b) { b.checked = on; });
      sync();
    };
  }
  var all = document.getElementById('pickAll');
  var none = document.getElementById('pickNone');
  if (all) { all.addEventListener('click', pick(true)); }
  if (none) { none.addEventListener('click', pick(false)); }
  sync();
})();
</script>
<?php admin_panel_end(); ?>

<?php admin_panel('Where else this happens', ''); ?>
<p class="hint">Three things that also remove data live with the thing they belong to, because that
  is where you are when you decide:</p>
<ul class="hint" style="margin:6px 0 0 18px;line-height:1.9">
  <li><a href="symbols.php">Coin list</a> &mdash; the <strong>Delete</strong> on each row, for
    retiring one coin while you are looking at it. Same code as above.</li>
  <li><a href="signals.php">Signal history</a> &mdash; <strong>targeted prune</strong>, which deletes
    by filter rather than by coin: a timeframe you never meant to publish, or the NEUTRAL rows.</li>
  <li><a href="members.php">Member list</a> &mdash; deleting an account, which is about a person
    rather than about market data.</li>
  <li><a href="knowledge.php">Rule performance</a> &mdash; <strong>Release now</strong> on a benched
    rule, which clears its quarantine record early rather than removing market data.</li>
</ul>
<?php admin_panel_end(); ?>
<?php admin_footer(); ?>
