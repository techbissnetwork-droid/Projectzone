<?php
declare(strict_types=1);

namespace SignalMasterAi;

/**
 * What a new member should do next.
 *
 * Registration handed people a chart and nothing else. Everything that makes
 * the site worth an account - alerts that reach you with the site closed, a
 * portfolio that settles your followed signals against real price action, a
 * backtest of the coin you actually trade - was reachable only by finding it,
 * and most people do not go looking. A first-run checklist is the difference
 * between a member who tries the product and one who saw a chart once.
 *
 * The steps are derived from real state, not from a "seen it" flag: a step is
 * done when the thing is actually done, so the list cannot congratulate anyone
 * for work they have not finished, and it disappears by itself when there is
 * nothing left to suggest. Nothing to store, nothing to migrate, and no way
 * for it to drift out of step with the account it describes.
 */
class Onboarding
{
    /** Setup steps still outstanding, for the progress fraction. */
    private static int $outstanding = self::SETUP_STEPS;

    /** Steps that count towards "getting started": funds, follow, alerts. */
    private const SETUP_STEPS = 3;

    /**
     * Outstanding steps for this member, in the order they make sense.
     *
     * @return array<int,array{key:string,title:string,why:string,href:string,cta:string}>
     */
    public static function steps(int $memberId): array
    {
        if ($memberId <= 0) {
            return [];
        }
        $pdo = Database::pdo();
        $steps = [];

        // 1. Funds. Everything in the portfolio is sized against the wallet, so
        //    an empty one makes every other step read as broken.
        $prefs = MemberPrefs::get($memberId);
        if ((float)($prefs['account_size'] ?? 0) <= 0) {
            $steps[] = [
                'key' => 'funds',
                'title' => 'Add funds to your paper wallet',
                'why' => 'Simulated money, so positions can be sized the way you would really size '
                       . 'them. Nothing is deposited anywhere and no order reaches an exchange.',
                'href' => 'portfolio.php#wallet',
                'cta' => 'Open the wallet',
            ];
        }

        // 2. Follow something. Until a position exists the portfolio, the
        //    equity curve and the comparison against your own trades are all
        //    empty pages.
        $trades = (int)self::count($pdo, 'SELECT COUNT(*) FROM paper_trades WHERE member_id = ?', $memberId);
        if ($trades === 0) {
            $steps[] = [
                'key' => 'follow',
                'title' => 'Follow a signal and watch it settle',
                'why' => 'Open a coin with a live BUY or SELL, pick how much to commit and which '
                       . 'target closes it. It settles itself against real price action using the '
                       . 'same walker as the public track record.',
                'href' => 'charts.php',
                'cta' => 'Find a setup',
            ];
        }

        // 3. Alerts. The one feature that works while the site is closed, and
        //    the one nobody discovers on their own.
        $alerts = (int)self::count($pdo,
            'SELECT COUNT(*) FROM member_alerts WHERE member_id = ? AND enabled = 1', $memberId);
        if ($alerts === 0) {
            $steps[] = [
                'key' => 'alerts',
                'title' => 'Get told when a setup appears',
                'why' => 'Pick the pairs worth interrupting you for and how strong a call has to be. '
                       . 'Alerts reach you by email, browser push or Telegram with this site closed.',
                // The alert panel lives on the chart, beside the signal it is
                // about - not on the account page, which is where a reasonable
                // guess put this link and where there is no such section.
                'href' => 'charts.php#alertsBox',
                'cta' => 'Set up alerts',
            ];
        }

        // Everything above is a real setup step, so the progress fraction is
        // counted against those three and nothing else. Deriving "done" by
        // subtracting what is left from a fixed total gets it wrong the moment
        // one of the entries is conditional - a brand new member with three
        // steps outstanding was being congratulated on one.
        self::$outstanding = count($steps);

        // There used to be a fourth entry here suggesting the track record.
        // It could not be ticked off - there is no "has read the record" state
        // to derive it from - so a member who had finished all three real
        // steps was shown a box reading "3 of 3 done" that would not go away,
        // on every page, forever. A checklist whose own promise is that each
        // line is ticked off by doing it cannot carry a line that never is.
        // The track record is one tap away in the top navigation.
        return $steps;
    }

    /** Small helper so a missing table can never break a page. */
    private static function count(\PDO $pdo, string $sql, int $id): int
    {
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);
            return (int)$stmt->fetchColumn();
        } catch (\Throwable $e) {
            return 1;   // assume done rather than nag about something unknowable
        }
    }

    /**
     * Render the checklist, or nothing at all when there is nothing to suggest.
     *
     * Collapsed by default. Expanded, it filled the whole first screen of a
     * phone and pushed the chart - the thing the member came for - below the
     * fold, which turns help into an obstacle. One line is enough to say there
     * is something here; opening it is a tap, and that choice is remembered so
     * it neither nags on every page nor hides from someone using it.
     */
    public static function render(int $memberId): string
    {
        $steps = self::steps($memberId);
        if (!$steps) {
            return '';
        }
        $done = self::SETUP_STEPS - self::$outstanding;
        $next = sma_e($steps[0]['title']);
        $out = '<details class="onboard" id="onboardBox">'
             . '<summary class="onboard-head"><strong>Getting started</strong>'
             // Shorter because it has to fit one line beside the label and
             // the chevron. "2 of 3 done - next: Follow a signal and watch it
             // settle" is a sentence; "2 of 3 - Follow a signal..." is a hint,
             // and the ellipsis is the stylesheet's job rather than a
             // truncation here that would cut mid-word at some widths.
             . '<span>' . $done . '/' . self::SETUP_STEPS . ' &middot; ' . $next . '</span></summary>'
             . '<ol class="onboard-steps">';
        foreach ($steps as $s) {
            $out .= '<li><div><b>' . sma_e($s['title']) . '</b>'
                  . '<p>' . sma_e($s['why']) . '</p></div>'
                  . '<a class="btn-primary" href="' . sma_e($s['href']) . '">'
                  . sma_e($s['cta']) . '</a></li>';
        }
        $out .= '</ol><p class="onboard-note">This disappears on its own as you go &mdash; '
              . 'each line is ticked off by actually doing it, not by dismissing it.</p></details>';
        // Remembering the state beats defaulting to open: someone working
        // through the list keeps it open, everyone else never sees it again
        // beyond one line.
        return $out . '<script' . sma_nonce() . '>(function(){var d=document.getElementById("onboardBox");'
             . 'if(!d)return;try{if(localStorage.getItem("sma_onboard")==="1")d.open=true;}catch(e){}'
             . 'd.addEventListener("toggle",function(){try{'
             . 'localStorage.setItem("sma_onboard",d.open?"1":"0");}catch(e){}});})();</script>';
    }
}
