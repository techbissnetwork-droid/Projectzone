<?php
declare(strict_types=1);

namespace SignalMasterAi;

/**
 * Paper trading and trade journal.
 *
 * The site could tell a member what the engine's overall win rate was, but not
 * what would have happened to THEM - which signals they followed, at what
 * size, with what result. A paper portfolio closes that gap: positions open
 * from a real signal, settle against real price action using the same
 * conservative walker as the public track record, and roll up into an equity
 * curve the member can actually recognise as their own.
 *
 * The same table stores journal entries for real trades (source = 'live'), so
 * a member can compare what they actually did against what the engine said.
 */
class Paper
{
    /**
     * Where a position closes itself.
     *
     *   3, 2, 1  the whole position leaves at that target
     *   0        the site's scale-out plan: part at target 1, rest at target 2
     *  -1        no target at all - only the stop loss or the time stop
     *
     * The default is the third target. Letting a winner reach the furthest
     * published target is the plan most readers say they are following, and it
     * is the one that makes the published third target mean something; anyone
     * who banks earlier can say so per trade, on the ticket or later.
     */
    public const EXIT_DEFAULT = 3;
    public const EXIT_CHOICES = [-1, 0, 1, 2, 3];

    /**
     * Clamp any caller's value to a choice that exists.
     *
     * Casting first would be wrong: (int) turns "abc", "" and null into 0,
     * which is a real choice - the site plan - so a malformed request would
     * silently pick a plan instead of falling back to the default. Only
     * something that actually spells an integer is trusted to mean one.
     */
    /**
     * WHOSE TRADE THIS WAS, said the same way everywhere.
     *
     * Three things end up in this table and they answer different questions:
     *
     *   paper  - the member followed the site's signal, levels and all. The
     *            result is a measure of the ENGINE.
     *   manual - the member set their own stop and targets on the chart. The
     *            result is a measure of THEM.
     *   live   - a trade they took with real money and logged here afterwards.
     *
     * A portfolio that shows the three as one number answers neither question:
     * a good month following signals and a bad month freelancing average out
     * into a figure that describes nobody. One definition, because two cards
     * already rendered this badge and a third page would have invented a
     * third wording.
     *
     * @return array{0:string,1:string} label and tooltip, or ['',''] for none
     */
    public static function sourceTag(string $source): array
    {
        return [
            'manual' => ['your plan', 'You set the stop and targets yourself'],
            'live'   => ['real trade', 'A trade you took with real money and logged here'],
            'paper'  => ['signal', 'Opened from the site signal, with its levels'],
        ][$source] ?? ['signal', 'Opened from the site signal, with its levels'];
    }

    public static function exitChoice($v): int
    {
        if (is_float($v) && floor($v) === $v) {
            $v = (int)$v;
        }
        if (is_string($v) && preg_match('/^\s*-?\d+\s*$/', $v)) {
            $v = (int)trim($v);
        }
        return is_int($v) && in_array($v, self::EXIT_CHOICES, true) ? $v : self::EXIT_DEFAULT;
    }

    /**
     * Open a paper position from a live signal payload.
     *
     * $reason receives why a null was returned. The two refusals mean opposite
     * things to a member - "there is nothing to follow here" versus "you are
     * already following this" - and reporting both as one message made the
     * button look broken to anyone who clicked it twice.
     */
    public static function open(
        int $memberId,
        array $signal,
        array $prefs = [],
        string $source = 'paper',
        ?string &$reason = null,
        float $margin = 0.0,
        float $leverage = 1.0,
        ?float $entryPrice = null,
        int $exitTarget = self::EXIT_DEFAULT
    ): ?int {
        $reason = null;
        $levels = $signal['levels'] ?? null;
        if ($memberId <= 0 || !is_array($levels) || !in_array($signal['signal'] ?? '', ['BUY', 'SELL'], true)) {
            $reason = 'no_setup';
            return null;
        }
        // One open position per pair/timeframe per member - a signal that
        // re-fires should not silently stack size.
        $dupe = Database::pdo()->prepare(
            "SELECT id FROM paper_trades WHERE member_id = ? AND symbol = ? AND tf = ? AND status = 'open'"
        );
        $dupe->execute([$memberId, $signal['symbol'], $signal['tf']]);
        $isDupe = (bool)$dupe->fetchColumn();
        // EVERY READ ON THIS PATH IS CLOSED BEFORE THE INSERT.
        //
        // See the note in Database::pdo(). A fetched-but-not-exhausted SELECT
        // holds a read transaction open on the connection, and the INSERT at
        // the end of this method is then a read-to-write upgrade - the one
        // case where SQLite answers SQLITE_BUSY WITHOUT consulting the busy
        // handler, so the ten-second timeout set at connect time is never
        // applied. Measured: three members opening at the same instant, and
        // one got an uncaught "database is locked" from the INSERT - a 500 on
        // the order ticket, at the moment somebody was trying to take a trade.
        $dupe->closeCursor();
        if ($isDupe) {
            $reason = 'duplicate';
            return null;
        }

        // How many can be running at once, per tier - see Limits. Checked here
        // rather than in the page, because the page is one of three ways a
        // position gets opened, and a limit enforced in markup is not a limit.
        //
        // The tier is read from the row rather than from the session: this is
        // called with a member id, and settling or opening on somebody's
        // behalf must not pick up whoever happens to be logged in.
        $cnt = Database::pdo()->prepare(
            "SELECT COUNT(*) FROM paper_trades WHERE member_id = ? AND status = 'open'"
        );
        $cnt->execute([$memberId]);
        $openNow = (int)$cnt->fetchColumn();
        $cnt->closeCursor();
        $who = Database::pdo()->prepare('SELECT tier, paid_until FROM members WHERE id = ?');
        $who->execute([$memberId]);
        $m = $who->fetch() ?: [];
        $who->closeCursor();
        // An expired plan is a free plan, which is the rule everywhere else.
        $tier = ($m['tier'] ?? 'free') === 'paid'
                && ((int)($m['paid_until'] ?? 0) === 0 || (int)$m['paid_until'] >= time())
            ? 'paid' : 'free';
        if (!Limits::allows('positions', $openNow, $tier)) {
            $reason = 'position_limit';
            return null;
        }

        // The funds are real money as far as this portfolio is concerned:
        // margin already committed to open trades cannot be committed again,
        // so a new position is sized against what is left rather than against
        // the full balance. Without this the balance was decorative - a member
        // could open twenty positions on a thousand dollars.
        $prefs = $prefs ?: MemberPrefs::get($memberId);
        $funds = self::funds($memberId, $prefs);
        if ($funds['available'] <= 0) {
            $reason = 'insufficient_funds';
            return null;
        }

        // Size is chosen per trade, the way an order ticket works: the member
        // commits an amount of the balance at a leverage and that decides the
        // quantity. A stored risk-percent setting could not answer "how much
        // of what I have do I want in this one", which is the actual question
        // at the moment of opening.
        // Entry is either the price the signal published or the price the
        // market is at now. A setup found an hour ago has usually moved, and
        // recording a fill at a price nobody could still get would flatter
        // every result that follows. The caller resolves the live price
        // server-side; a price supplied by the client is never trusted.
        $entry = $entryPrice !== null && $entryPrice > 0 ? $entryPrice : (float)$levels['entry'];
        $stop = (float)$levels['stop_loss'];
        $isBuy = $signal['signal'] === 'BUY';
        // Entering the wrong side of the stop is not a position, it is an
        // instant loss - refuse it rather than book it.
        if (($isBuy && $entry <= $stop) || (!$isBuy && $entry >= $stop)) {
            $reason = 'past_stop';
            return null;
        }
        $margin = round(max(0.0, (float)$margin), 2);
        $lev = max(1.0, min(self::maxLeverage(), (float)$leverage));
        if ($margin <= 0) {
            $reason = 'no_amount';
            return null;
        }
        if ($margin > $funds['available'] + 1e-9) {
            $reason = 'insufficient_funds';
            return null;
        }
        $units = $entry > 0 ? ($margin * $lev) / $entry : 0.0;
        if ($units <= 0) {
            $reason = 'no_amount';
            return null;
        }

        // Execution telemetry. Slippage is the gap between the price the setup
        // was published at and the price this position actually opened at: a
        // member who takes a signal forty minutes late is not trading the same
        // setup, and until it was recorded there was no way to tell how much
        // of a result came from the engine and how much from taking it late.
        // Signed so that positive is always the worse price - paying up on a
        // buy, selling cheaper on a sell - so the number reads the same way
        // for both sides. Spread and volatility describe what the market cost
        // to enter at that moment. All three are optional: telemetry must
        // never stop a fill.
        $signalPrice = (float)($levels['entry'] ?? 0);
        $slipPct = $signalPrice > 0 ? round(($entry - $signalPrice) / $signalPrice * 100 * ($isBuy ? 1 : -1), 5) : null;
        $spreadPct = $signal['spread_pct'] ?? null;
        $atrPct = $signal['atr_pct'] ?? null;
        if ($atrPct === null) {
            $atr = $signal['indicators']['atr'] ?? null;
            $price = (float)($signal['price'] ?? $entry);
            $atrPct = is_numeric($atr) && $price > 0 ? round((float)$atr / $price * 100, 4) : null;
        }

        $cols = 'member_id, signal_id, symbol, tf, side, entry, stop_loss,
                tp1, tp2, tp3, units, margin, leverage, status, source, opened_at, exit_target';
        // The deadline the signal published travels with the position, so the
        // settler closes it when the member was told it would close.
        $expiresBars = isset($levels['expires_bars']) ? (int)$levels['expires_bars'] : null;
        $vals = [
            $memberId, (int)($signal['signal_id'] ?? 0), $signal['symbol'], $signal['tf'],
            $signal['signal'], $entry, (float)$levels['stop_loss'],
            (float)($levels['tp1'] ?? 0), (float)($levels['tp2'] ?? 0), (float)($levels['tp3'] ?? 0),
            $units, $margin, $lev, 'open', $source, time(),
            self::exitChoice($exitTarget),
        ];
        $marks = static fn (int $n): string => implode(',', array_fill(0, $n, '?'));
        // The published price is stored beside the fill, not just the
        // percentage between them: "the signal said 64,180 and I got in at
        // 64,300" is the sentence a member reads, and a slippage figure
        // cannot be read back into it.
        $extra = [$slipPct, $spreadPct, $atrPct, $expiresBars, $signalPrice > 0 ? $signalPrice : null];
        try {
            Database::pdo()->prepare(
                'INSERT INTO paper_trades (' . $cols . ', slippage_pct, spread_pct, atr_pct,
                    expires_bars, signal_price)
                 VALUES (' . $marks(count($vals) + count($extra)) . ')'
            )->execute(array_merge($vals, $extra));
        } catch (\Throwable $e) {
            Database::pdo()->prepare(
                'INSERT INTO paper_trades (' . $cols . ') VALUES (' . $marks(count($vals)) . ')'
            )->execute($vals);
        }
        return (int)Database::pdo()->lastInsertId();
    }

    /** The highest leverage the site will ever offer, whatever is typed in. */
    public const MAX_LEVERAGE_CEILING = 125.0;

    /** The default ladder shown on the ticket. */
    public const LEVERAGE_LADDER_DEFAULT = '1,2,3,5,10,20,50,100';

    /**
     * The most leverage a member may take, as the operator set it.
     *
     * IT USED TO BE 125 IN TWO FILES AND A LIST OF OPTIONS IN A THIRD.
     *
     * Three copies of one decision, none of them changeable without editing
     * PHP: the ticket offered up to 100x, the preferences form clamped at 125,
     * and Paper::open() clamped at 125 again. An operator running a cautious
     * site had no way to say "20x is as far as this goes", and one running a
     * permissive one had no way to say anything either.
     *
     * It matters more than an ordinary dial. At 100x a 1% move against a
     * position takes the whole margin, so the ladder's top rung decides how
     * fast a member can empty their wallet - and until the cap was added
     * alongside this, how far past empty they could go.
     */
    public static function maxLeverage(): float
    {
        $v = (float)Database::setting('paper_max_leverage', (string)self::MAX_LEVERAGE_CEILING);
        return max(1.0, min(self::MAX_LEVERAGE_CEILING, $v));
    }

    /**
     * The rungs offered on the ticket, never above the ceiling.
     *
     * Filtered rather than trusted: an operator who lowers the cap to 20 and
     * forgets to edit the ladder would otherwise be shown a 100x button that
     * the server silently turns into 20x - a control that lies about what it
     * does.
     *
     * @return array<int,float>
     */
    public static function leverageLadder(): array
    {
        $raw = trim((string)Database::setting('paper_leverages', self::LEVERAGE_LADDER_DEFAULT));
        $max = self::maxLeverage();
        $out = [];
        foreach (explode(',', $raw !== '' ? $raw : self::LEVERAGE_LADDER_DEFAULT) as $bit) {
            $v = (float)trim($bit);
            if ($v >= 1.0 && $v <= $max && !in_array($v, $out, true)) {
                $out[] = $v;
            }
        }
        sort($out);
        // Never an empty select. 1x is "no leverage", which is always a valid
        // thing to want and always within any cap.
        return $out ?: [1.0];
    }

    /**
     * A loss can never exceed the margin that was committed.
     *
     * WHAT THE PORTFOLIO WAS TEACHING, AND WHY IT WAS THE OPPOSITE OF TRUE.
     *
     * Positions are opened by committing an amount of the balance at a
     * leverage - isolated margin, in the language of every venue that offers
     * it. The defining property of isolated margin is that the committed
     * amount is the MOST that can be lost: the exchange force-closes the
     * position when the loss eats the margin, and the rest of the account is
     * never at risk.
     *
     * Neither half of that was modelled. Measured: a member with a $120
     * balance committed $100 at 100x, price moved 10% against them, and they
     * finished on MINUS $880 - a $1,000 loss on a $120 account, from a
     * position a real exchange would have closed at roughly 1%.
     *
     * So the paper portfolio taught the reverse of the real lesson about
     * leverage twice over. It hid how violently fast liquidation arrives, and
     * it invented losses that cannot happen. And a negative balance is not a
     * state the rest of the code has an answer for: available funds floor at
     * zero, so the member is locked out of trading entirely with no way back
     * except a deposit.
     *
     * The cap goes here, in one place, used by both the manual close and the
     * settler - the two paths that book a result.
     */
    public static function bookedPnl(float $pnl, float $margin): float
    {
        $cap = round(max(0.0, $margin), 2);
        return $cap > 0 ? round(max($pnl, -$cap), 2) : round($pnl, 2);
    }

    /** Did this result hit the margin cap - ie. would it have been liquidated? */
    /**
     * THE MONEY AND THE R MUST DESCRIBE THE SAME TRADE.
     *
     * bookedPnl caps a loss at the margin committed, because that is what an
     * isolated position can lose - the venue liquidates it there and the rest
     * of the balance is untouched. The R beside it was NOT capped, and both
     * close paths stored it that way, so a liquidated position was recorded as
     * "-$50.00 (-109.19R)": the money says you lost the fifty you committed,
     * the R says you lost a hundred and nine times your intended risk.
     *
     * That is not only a contradiction on screen. outcome_r is what the
     * member's own expectancy, profit factor and average R are computed from,
     * so one liquidation dragged a whole performance summary into fiction -
     * and the summary is the reason the portfolio exists.
     *
     * Capped here, once, for both paths: the R a booked loss actually
     * represents is that loss divided by the risk the position was sized on.
     *
     * @return array{0:float,1:float,2:bool} booked pnl, matching R, liquidated
     */
    public static function book(float $rawPnl, float $margin, float $riskPerUnit,
                                float $units, float $rawR): array
    {
        $pnl = self::bookedPnl($rawPnl, $margin);
        $liq = self::wasLiquidated($rawPnl, $margin);
        $r = $rawR;
        if ($liq) {
            $plannedRisk = abs($riskPerUnit) * abs($units);
            // No planned risk means no R to express the loss in - a journal
            // entry with the stop at the entry, say. The raw figure is left
            // alone rather than replaced with a made-up one.
            if ($plannedRisk > 0) {
                $r = round($pnl / $plannedRisk, 3);
            }
        }
        return [$pnl, $r, $liq];
    }

    /**
     * Re-cap the R on positions that were liquidated before book() existed.
     *
     * Those rows stored a capped loss and an uncapped R - see book() - and the
     * member's expectancy, profit factor and average R are all computed from
     * outcome_r, so one of them drags a whole performance page into fiction.
     * Fixing the code forward leaves the history wrong, and the history is
     * what the page is.
     *
     * DELIBERATELY NARROW. Only closed rows that actually hit the cap: a
     * margin above zero, a loss at or past that margin, and a planned risk to
     * divide by. Everything else keeps the number it was settled with, because
     * a rewrite of a member's record has to be provably confined to the rows
     * that are wrong.
     *
     * @return int rows corrected
     */
    public static function recapLiquidations(): int
    {
        $pdo = Database::pdo();
        try {
            $rows = $pdo->query(
                "SELECT id, entry, stop_loss, units, margin, pnl, outcome_r
                   FROM paper_trades
                  WHERE status = 'closed' AND margin > 0 AND pnl < 0 AND pnl <= -margin"
            )->fetchAll();
        } catch (\Throwable $e) {
            return 0;
        }
        if (!$rows) {
            return 0;
        }
        $upd = $pdo->prepare('UPDATE paper_trades SET outcome_r = ? WHERE id = ?');
        $fixed = 0;
        foreach ($rows as $t) {
            $risk = abs((float)$t['entry'] - (float)$t['stop_loss']) * abs((float)$t['units']);
            if ($risk <= 0) {
                continue;
            }
            $want = round((float)$t['pnl'] / $risk, 3);
            // Only when it actually differs. Re-writing a row to the value it
            // already holds is a write nobody asked for.
            if (abs($want - (float)$t['outcome_r']) > 0.01) {
                $upd->execute([$want, (int)$t['id']]);
                $fixed++;
            }
        }
        return $fixed;
    }

    public static function wasLiquidated(float $rawPnl, float $margin): bool
    {
        return $margin > 0 && $rawPnl < -$margin;
    }

    /** Manually close a position at a given price (journal / early exit). */
    public static function close(int $memberId, int $tradeId, float $price, string $note = ''): void
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM paper_trades WHERE id = ? AND member_id = ?');
        $stmt->execute([$tradeId, $memberId]);
        $t = $stmt->fetch();
        $stmt->closeCursor();
        if (!$t || $t['status'] !== 'open') {
            return;
        }
        $dir = $t['side'] === 'BUY' ? 1 : -1;
        $risk = abs((float)$t['entry'] - (float)$t['stop_loss']);
        $r = $risk > 0 ? round($dir * ($price - (float)$t['entry']) / $risk, 3) : 0.0;
        $rawPnl = round($dir * ($price - (float)$t['entry']) * (float)$t['units'], 2);
        $margin = (float)($t['margin'] ?? 0);
        [$pnl, $r, $liq] = self::book($rawPnl, $margin, $risk, (float)$t['units'], $r);
        if ($liq) {
            // Say so on the trade. "Closed at a loss of exactly your margin"
            // with no explanation reads like a rounding error; it is the whole
            // position being wiped out, which is the thing leverage does.
            $note = trim($note . ' - liquidated: the loss reached the '
                . number_format($margin, 2) . ' committed, which is the most an isolated '
                . 'position can lose');
        }
        // The exit price is recorded, not just what it was worth: a member
        // reading a closed trade wants to see in at what, out at what, and
        // until now the price they typed here vanished the moment the R was
        // worked out from it.
        try {
            Database::pdo()->prepare(
                "UPDATE paper_trades SET status = 'closed', outcome_r = ?, pnl = ?, note = ?,
                        closed_at = ?, exit_price = ?
                 WHERE id = ? AND member_id = ?"
            )->execute([$r, $pnl, mb_substr($note, 0, 500), time(), $price, $tradeId, $memberId]);
        } catch (\Throwable $e) {
            Database::pdo()->prepare(
                "UPDATE paper_trades SET status = 'closed', outcome_r = ?, pnl = ?, note = ?, closed_at = ?
                 WHERE id = ? AND member_id = ?"
            )->execute([$r, $pnl, mb_substr($note, 0, 500), time(), $tradeId, $memberId]);
        }
    }

    /**
     * Settle open paper positions against stored candles, using the same
     * conservative walker as the public track record so the member's numbers
     * and the site's numbers are produced by identical logic.
     */
    /**
     * Which published targets an open position has actually reached.
     *
     * The card showed Target 1 and nothing else, permanently in green - and
     * green on a price is the colour of "done". So a position whose first
     * target was passed an hour ago looked exactly like one that has never got
     * near it, and Target 2 and Target 3 existed only inside the auto-close
     * dropdown, where a member has to open a select to discover what the rest
     * of the plan even is.
     *
     * Reached, not "currently past". Price comes back: a trade that touched
     * Target 1 and retraced has still reached Target 1, and that is the thing
     * the member wants to know. Read from the candles already on disk between
     * the open and now - the same series settlement walks - so it costs one
     * indexed query per position and no new columns.
     *
     * @param array<string,mixed> $t A row from paper_trades.
     * @return array{tp1:bool,tp2:bool,tp3:bool,extreme:?float}
     */
    public static function reached(array $t): array
    {
        $out = ['tp1' => false, 'tp2' => false, 'tp3' => false, 'extreme' => null];
        $long = strtoupper((string)($t['side'] ?? 'BUY')) === 'BUY';
        // A closed position is judged on the window it was actually open for.
        // Reading to "now" would credit it with a target price reached days
        // after it was stopped out, which is the one thing a track record must
        // never do.
        $closedAt = (int)($t['closed_at'] ?? 0);
        try {
            $sql = 'SELECT MAX(high) AS hi, MIN(low) AS lo FROM candles
                    WHERE symbol = ? AND tf = ? AND open_time > ?';
            $args = [(string)$t['symbol'], (string)$t['tf'], (int)($t['opened_at'] ?? 0) * 1000];
            if ($closedAt > 0) {
                $sql .= ' AND open_time <= ?';
                $args[] = $closedAt * 1000;
            }
            $stmt = Database::pdo()->prepare($sql);
            $stmt->execute($args);
            $row = $stmt->fetch();
            $stmt->closeCursor();          // see Database::pdo() - a held read blocks later writes
        } catch (\Throwable $e) {
            return $out;
        }
        if (!$row || $row['hi'] === null) {
            return $out;
        }
        $extreme = $long ? (float)$row['hi'] : (float)$row['lo'];
        $out['extreme'] = $extreme;
        foreach (['tp1', 'tp2', 'tp3'] as $k) {
            $tp = (float)($t[$k] ?? 0);
            if ($tp <= 0) {
                continue;               // not every trade publishes three
            }
            $out[$k] = $long ? $extreme >= $tp : $extreme <= $tp;
        }
        return $out;
    }

    public static function settle(int $limit = 200): int
    {
        $pdo = Database::pdo();
        $open = $pdo->query(
            "SELECT * FROM paper_trades WHERE status = 'open' ORDER BY opened_at ASC LIMIT " . (int)$limit
        )->fetchAll();
        if (!$open) {
            return 0;
        }
        $candles = $pdo->prepare(
            'SELECT open_time, high, low, close FROM candles WHERE symbol = ? AND tf = ? AND open_time > ?
             ORDER BY open_time ASC'
        );
        $upd = $pdo->prepare(
            "UPDATE paper_trades SET status = 'closed', outcome_r = ?, pnl = ?, note = ?, closed_at = ?
             WHERE id = ?"
        );
        // With excursion telemetry when the columns exist; the plain statement
        // above stays as the fallback so a partially upgraded database still
        // closes its positions.
        $updFull = $pdo->prepare(
            "UPDATE paper_trades SET status = 'closed', outcome_r = ?, pnl = ?, note = ?, closed_at = ?,
                    mae_r = ?, mfe_r = ?, mae_sec = ?, mfe_sec = ?, exit_price = ?
             WHERE id = ?"
        );
        // Cost per round trip, as a share of notional - the same figure the
        // walker deducts, needed here to turn the net R it returns back into
        // the price the position left at.
        $costPct = max(0.0, (float)Database::setting('round_trip_cost_pct', '0.1')) / 100;
        $timeStopBars = max(1, (int)Database::setting('time_stop_bars', '24'));
        $tfSec = MarketData::TF_SECONDS;
        $closed = 0;
        foreach ($open as $t) {
            $sec = $tfSec[$t['tf']] ?? 3600;
            // The deadline this position was opened under, not today's global
            // default - see the column comment in Database::migrate().
            $bars = max(1, (int)($t['expires_bars'] ?? 0) ?: $timeStopBars);
            $candles->execute([$t['symbol'], $t['tf'], ((int)$t['opened_at']) * 1000]);
            $bars_ = $candles->fetchAll();

            // The member's own exit, when they named one.
            //
            // Passing the chosen target as tp1 with no tp2 is not a trick: it
            // is exactly the plan being described - the whole position leaves
            // at that price - and the walker already settles that shape, with
            // the same conservative stop-before-target rule on an ambiguous
            // bar. exit_target 0 keeps the site's scale-out plan, which is
            // still the default and still what the public track record uses.
            $want = self::exitChoice($t['exit_target'] ?? self::EXIT_DEFAULT);
            $chosen = $want > 0 ? (float)($t['tp' . $want] ?? 0) : 0.0;
            if ($want === -1) {
                // No target means no price the walker can ever touch, so it is
                // given one: far above the market on a long, far below it on a
                // short. The position then has exactly two ways out, which is
                // what "no target" means - the stop loss, or the time stop.
                $chosen = $t['side'] === 'BUY'
                    ? (float)$t['entry'] * 1e9
                    : (float)$t['entry'] * 1e-9;
            }
            if ($want > 0 && $chosen <= 0) {
                // Named a target this position never had - a journal entry with
                // one target, say. Fall back to the deepest one it does have
                // rather than settling against a price of zero.
                foreach ([3, 2, 1] as $fallback) {
                    if ((float)($t['tp' . $fallback] ?? 0) > 0) {
                        $chosen = (float)$t['tp' . $fallback];
                        break;
                    }
                }
            }
            $plan = $want !== 0 && $chosen > 0
                ? ['entry' => (float)$t['entry'], 'stop_loss' => (float)$t['stop_loss'],
                   'tp1' => $chosen, 'tp2' => 0.0]
                : ['entry' => (float)$t['entry'], 'stop_loss' => (float)$t['stop_loss'],
                   'tp1' => (float)$t['tp1'], 'tp2' => (float)$t['tp2']];

            $res = Outcomes::walk(
                $t['side'] === 'BUY', $plan, (int)$t['opened_at'], $sec, $bars, $bars_
            );
            if ($res === null) {
                continue;
            }
            [$outcome, $note, $closedAt, $r] = $res;
            $ex = $res[4] ?? [];
            $risk = abs((float)$t['entry'] - (float)$t['stop_loss']);
            $isBuy = $t['side'] === 'BUY';
            $entryPx0 = (float)$t['entry'];

            // A time stop is a close, and a close realises whatever the market
            // is worth at that moment. The walker scores an expired setup at
            // exactly 0 R, which is right for the public track record - it did
            // not resolve, so it is a no-trade there - and wrong for a wallet,
            // where the position was open the whole time and the money moved.
            // So an expired paper position is marked out at the close of the
            // bar it timed out on.
            if ($outcome === 'expired' && $risk > 0) {
                $mark = null;
                foreach ($bars_ as $c) {
                    if ((int)((int)$c['open_time'] / 1000) <= (int)$closedAt) {
                        $mark = (float)$c['close'];
                    }
                }
                if ($mark !== null && $mark > 0) {
                    $costR = $entryPx0 > 0 ? $entryPx0 * $costPct / $risk : 0.0;
                    $r = round(($isBuy ? $mark - $entryPx0 : $entryPx0 - $mark) / $risk - $costR, 3);
                }
            }
            $rawPnl = round($r * $risk * (float)$t['units'], 2);
            [$pnl, $r, $liqHere] = self::book($rawPnl, (float)($t['margin'] ?? 0),
                                              $risk, (float)$t['units'], $r);
            $why = $outcome === 'expired' ? 'Time stop - closed at the market' : $note;
            if ($liqHere) {
                // The settler never said this, so a position wiped out by
                // leverage was recorded as an ordinary stop-out for exactly
                // the margin - a number that looks like a coincidence until
                // somebody works out what happened. close() has always
                // explained it; this is the same sentence.
                $why = trim($why . ' - liquidated: the loss reached the '
                    . number_format((float)($t['margin'] ?? 0), 2) . ' committed, which is the '
                    . 'most an isolated position can lose');
            }
            if ($want > 0 && $outcome === 'confirmed') {
                $why = 'Target ' . $want . ' reached';
            }
            // Where the position left the market, read back out of the result
            // the walker returned. A straight stop-out lands exactly on the
            // stop and a target taken in full lands exactly on the target;
            // a plan that scaled out at TP1 and let the rest run lands in
            // between, which is the average fill it really achieved. The
            // walker deducts costs from R, so they are added back first -
            // otherwise the price would be shifted by the fee rather than
            // being the price the market traded at.
            $entryPx = $entryPx0;
            $costR = $risk > 0 && $entryPx > 0 ? $entryPx * $costPct / $risk : 0.0;
            $exitPx = $risk > 0
                ? round($entryPx + ($isBuy ? 1 : -1) * ($r + $costR) * $risk, 10)
                : null;
            try {
                $updFull->execute([$r, $pnl, $why, $closedAt,
                    $ex['mae_r'] ?? null, $ex['mfe_r'] ?? null,
                    $ex['mae_sec'] ?? null, $ex['mfe_sec'] ?? null, $exitPx, $t['id']]);
            } catch (\Throwable $e) {
                $upd->execute([$r, $pnl, $why, $closedAt, $t['id']]);
            }
            $closed++;
        }
        return $closed;
    }

    /** Open positions plus closed history for one member. */
    /**
     * The member's trading funds as an account rather than a static number.
     *
     * Starting funds move with realised results, and margin committed to open
     * positions is not available to commit again. "available" is what a new
     * trade can actually draw on.
     */
    public static function funds(int $memberId, array $prefs = []): array
    {
        $prefs = $prefs ?: MemberPrefs::get($memberId);
        // A wallet nobody has funded is empty, and says so.
        //
        // It used to open at a $10,000 "example" balance, which is a strange
        // thing to show someone on a page about their own results: the first
        // number a new member reads is money they do not have, the deposited
        // figure agrees with it, and the only clue is a small tag. Simulated
        // capital is still simulated, but it should arrive because they added
        // it - that is the whole point of the wallet, and the first step of
        // the getting-started list already asks for it.
        $start = max(0.0, (float)($prefs['account_size'] ?? 0));
        $unfunded = $start <= 0;
        $lev = max(1.0, (float)($prefs['leverage'] ?? 1));

        $pdo = Database::pdo();
        // Only simulated positions touch the wallet. A journal entry records a
        // trade the member took somewhere else, at a size they type in by
        // hand - letting those lock up margin meant one mistyped size could
        // swallow the whole balance and block every real paper trade after it.
        $realised = $pdo->prepare(
            "SELECT COALESCE(SUM(pnl), 0) FROM paper_trades
              WHERE member_id = ? AND status = 'closed' AND source <> 'live'"
        );
        $realised->execute([$memberId]);
        $pnl = (float)$realised->fetchColumn();
        $realised->closeCursor();

        // The margin each position actually committed, recorded when it was
        // opened. Deriving it from one account-wide leverage was only ever
        // right while every trade shared the same setting.
        $openStmt = $pdo->prepare(
            "SELECT COALESCE(SUM(margin), 0) FROM paper_trades
              WHERE member_id = ? AND status = 'open' AND source <> 'live'"
        );
        $openStmt->execute([$memberId]);
        $used = (float)$openStmt->fetchColumn();
        $openStmt->closeCursor();

        $balance = $start + $pnl;
        return [
            'start'     => round($start, 2),
            'unfunded'  => $unfunded,
            'realised'  => round($pnl, 2),
            'balance'   => round($balance, 2),
            'used'      => round($used, 2),
            'available' => round(max(0.0, $balance - $used), 2),
            'leverage'  => $lev,
        ];
    }

    /**
     * WHEN THIS POSITION RUNS OUT OF TIME.
     *
     * Every paper trade inherits the deadline of the plan it was opened from -
     * expires_bars, stored on the row, because the engine picks the window when
     * it picks the type and a 1:3 closed on a 1:1's clock is not the trade that
     * was published. A row from before that column existed falls back to the
     * site-wide time stop, which is what settled it at the time.
     *
     * One definition, used by the settler, by the API that draws the countdown
     * and by the email that reports the close - three places that must agree
     * about when a trade ended or they are describing different trades.
     */
    public static function deadline(array $t): int
    {
        $sec = MarketData::TF_SECONDS[$t['tf'] ?? ''] ?? 3600;
        $bars = (int)($t['expires_bars'] ?? 0);
        if ($bars <= 0) {
            $bars = max(1, (int)Database::setting('time_stop_bars', '24'));
        }
        return (int)($t['opened_at'] ?? 0) + $bars * $sec;
    }

    public static function positions(int $memberId, string $status = 'open', int $limit = 100): array
    {
        // LEFT JOIN, not an extra query per row: signal_id is a plain foreign
        // key with nothing enforcing it stays valid (the signal it points at
        // can be pruned long after the trade that came from it is history),
        // so a plain JOIN would silently drop a position whose signal is
        // gone. pt.* keeps every column this call has always returned, under
        // its original name - signal_ref is new, additive, and null exactly
        // when there is nothing to show (a journal entry with no signal
        // behind it, same as charts.php's own reference box).
        $stmt = Database::pdo()->prepare(
            'SELECT pt.*, s.ref AS signal_ref FROM paper_trades pt
             LEFT JOIN signals s ON s.id = pt.signal_id
             WHERE pt.member_id = ? AND pt.status = ?
             ORDER BY ' . ($status === 'open' ? 'pt.opened_at DESC' : 'pt.closed_at DESC') . ' LIMIT ' . (int)$limit
        );
        $stmt->execute([$memberId, $status]);
        return $stmt->fetchAll();
    }

    /**
     * Performance summary and equity curve for a member.
     *
     * Reports expectancy and profit factor alongside win rate, because win
     * rate on its own is the statistic most likely to mislead: 40% at 2R is a
     * profitable system and 60% at 0.5R is not.
     */
    public static function summary(int $memberId, string $source = ''): array
    {
        $sql = "SELECT outcome_r, pnl, closed_at, symbol, side FROM paper_trades
                WHERE member_id = ? AND status = 'closed'";
        $args = [$memberId];
        if ($source !== '') {
            $sql .= ' AND source = ?';
            $args[] = $source;
        }
        $stmt = Database::pdo()->prepare($sql . ' ORDER BY closed_at ASC LIMIT 1000');
        $stmt->execute($args);
        $rows = $stmt->fetchAll();

        $n = count($rows);
        $wins = 0;
        $rSum = 0.0;
        $pnl = 0.0;
        $grossWin = 0.0;
        $grossLoss = 0.0;
        $curve = [];
        $equity = 0.0;
        $peak = 0.0;
        $maxDd = 0.0;
        foreach ($rows as $row) {
            $r = (float)$row['outcome_r'];
            $rSum += $r;
            $pnl += (float)$row['pnl'];
            if ($r > 0) {
                $wins++;
                $grossWin += $r;
            } else {
                $grossLoss += abs($r);
            }
            $equity += $r;
            $peak = max($peak, $equity);
            $maxDd = max($maxDd, $peak - $equity);
            $curve[] = ['at' => (int)$row['closed_at'], 'equity' => round($equity, 3)];
        }
        return [
            'trades'      => $n,
            'wins'        => $wins,
            'winrate'     => $n > 0 ? round($wins / $n * 100, 1) : null,
            'expectancy'  => $n > 0 ? round($rSum / $n, 3) : null,
            'total_r'     => round($rSum, 2),
            'pnl'         => round($pnl, 2),
            'profit_factor' => $grossLoss > 0 ? round($grossWin / $grossLoss, 2) : null,
            'max_drawdown_r' => round($maxDd, 2),
            'curve'       => $curve,
            'open'        => (int)Database::pdo()->query(
                "SELECT COUNT(*) FROM paper_trades WHERE member_id = " . (int)$memberId . " AND status = 'open'"
            )->fetchColumn(),
        ];
    }

    /** Members who asked for signals to be auto-followed into paper trades. */
    public static function autoFollowMembers(): array
    {
        $rows = Database::pdo()->query(
            "SELECT member_id FROM member_prefs WHERE member_id > 0"
        )->fetchAll();
        $out = [];
        foreach ($rows as $r) {
            if (Database::setting('paper_autofollow_' . (int)$r['member_id'], '0') === '1') {
                $out[] = (int)$r['member_id'];
            }
        }
        return $out;
    }
}
