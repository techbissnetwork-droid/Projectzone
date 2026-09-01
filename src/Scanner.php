<?php
declare(strict_types=1);

namespace SignalMasterAi;

/**
 * Market-wide signal scanner.
 *
 * The full-market background scan already analysed every enabled coin on
 * several timeframes and wrote the results to the signals table - and none of
 * it was ever shown to anyone. The only way to discover a setup was to open
 * each coin individually and look. This reads the latest stored verdict per
 * pair and turns it into a sortable, filterable board.
 *
 * Everything is served from stored signals: the scanner never triggers fresh
 * analysis, so a hundred rows cost one query rather than a hundred passes of
 * the engine.
 */
class Scanner
{
    /** For deriving a deadline on rows stored before signals published one. */


    /**
     * Latest signal per symbol/timeframe, tier-filtered for the viewer.
     *
     * @param array $filter ['tf'=>, 'signal'=>, 'grade'=>, 'min_score'=>, 'search'=>, 'sort'=>]
     * @param int   $limit  rows to return, or 0 for every row that passes the
     *                      filters. Unbounded is safe here in a way it would
     *                      not be on the signal ledger: this table holds one
     *                      row per coin and timeframe, so its size is the
     *                      operator's own coin list, not a history that grows
     *                      for ever.
     */
    /**
     * Why the board is shorter than the signal count, counted by reason.
     *
     * "The engine is generating signals and the scanner shows one row" is the
     * commonest question an operator has, and every part of the answer was
     * already computed here and thrown away. Six filters run over every stored
     * verdict; each one is a legitimate reason a pair is absent, and from the
     * outside they are indistinguishable from a broken scan.
     *
     * Filled by board() on every call, so it can never describe a different
     * run of different filters than the rows beside it.
     *
     * @var array<string,int>
     */
    public static array $drops = [];

    /**
     * Calls the live board drops for having already touched a target - not
     * because they are gone, but because the entry is. See the "ALREADY
     * MOVED IS ALREADY GONE" note in run() for why they cannot stay in the
     * main list; this is where they go instead, each carrying which target
     * (tp1/tp2/tp3) it reached, so a reader can see a call is still running
     * without it being mistaken for one still open to enter.
     *
     * Filled by board() on every call, same as $drops.
     *
     * @var list<array>
     */
    public static array $progress = [];

    /** The reasons, in the order board() applies them, with their wording. */
    public const DROP_REASONS = [
        'tf_off'    => 'the timeframe is switched off for the site or for that coin',
        'stale'     => 'nothing has re-scanned the pair inside the setup\'s own lifetime',
        'settled'   => 'the call has already finished - it is on the Closed tab',
        'moved'     => 'price has already reached the stop or a target, so the published entry has gone',
        'direction' => 'filtered out by the direction asked for',
        'neutral'   => 'no call - the engine sees no setup',
        'grade_ask' => 'below the grade the visitor asked for',
        'grade'     => 'below the site-wide publish floor',
        'score'     => 'below the minimum score asked for',
        'search'    => 'does not match the search box',
        'rr'        => 'not the signal type asked for - 1:1, 1:2 or 1:3',
    ];

    /**
     * The board, and the counters describing what it left out.
     *
     * Separate from board() because board() publishes those counters into
     * static state, and this class calls its own board twice more per page -
     * topSetups() and breadth() both need a board of their own. Those runs
     * were overwriting the counters belonging to the board the reader was
     * actually looking at, so the scanner's "28 of 33 are below grade A" was
     * describing topSetups' 400-row unfiltered run rather than the five rows
     * printed underneath it. Both numbers were real; they were from different
     * queries, which is worse than either being wrong.
     *
     * @return array{rows:list<array>,drops:array<string,int>}
     */
    public static function boardWithDrops(array $filter = [], int $limit = 300): array
    {
        $rows = self::run($filter, $limit, $drops, $progress);
        return ['rows' => $rows, 'drops' => $drops, 'progress' => $progress];
    }

    /**
     * The board alone, publishing its counters for the page to read.
     *
     * Kept because most callers want the rows and nothing else. A page that
     * prints the counters should use boardWithDrops() and hold its own copy -
     * anything else is a race against the next board() call on the page.
     */
    public static function board(array $filter = [], int $limit = 300): array
    {
        $rows = self::run($filter, $limit, $drops, $progress);
        self::$drops = $drops;
        self::$progress = $progress;
        return $rows;
    }

    /**
     * @param array<string,int>|null $drops    filled with the drop counters
     * @param list<array>|null       $progress filled with the touched-but-
     *                                          unsettled rows this run found
     */
    private static function run(array $filter, int $limit, ?array &$drops = null, ?array &$progress = null): array
    {
        $drops = array_fill_keys(array_keys(self::DROP_REASONS), 0);
        $drops['seen'] = 0;
        $viewerTier = MemberAuth::tier();
        $tf = (string)($filter['tf'] ?? '');
        $want = strtoupper((string)($filter['signal'] ?? ''));
        $minGrade = (string)($filter['grade'] ?? '');
        $minScore = (float)($filter['min_score'] ?? 0);
        $search = strtoupper(trim((string)($filter['search'] ?? '')));
        // Which of the three types to show: 1, 2 or 3 for exactly that one,
        // 0 or absent for all of them. A tier the reader asks for by name,
        // rather than a floor, because "show me the 1:3s" is the question
        // people actually have - a 1:1 is not a worse signal, it is a
        // different trade, and a floor would say otherwise.
        $wantTier = max(0, min(3, (int)($filter['rr'] ?? 0)));

        // One row per (symbol, tf) by construction now - signal_state keeps the
        // current verdict and nothing else, so the board no longer has to find
        // the newest row of a table that mixes live verdicts with history.
        //
        // A directional verdict is displayed from the LEDGER row that governs
        // it, not from the state row: the published entry, stop and targets
        // must read the same at hour six as at minute one. The state row only
        // supplies the display when the pair is NEUTRAL (nothing to freeze) or
        // when retention has since pruned the call it pointed at.
        $sql = "SELECT st.symbol, st.tf, st.`signal`, st.score, st.confidence, st.price,
                       st.indicators, st.changed_at, st.updated_at, st.signal_id,
                       sg.score AS g_score, sg.confidence AS g_conf, sg.price AS g_price,
                       sg.indicators AS g_ind, sg.created_at AS g_at, sg.outcome AS g_outcome,
                       sg.ref AS g_ref, sy.label, sy.tier
                FROM signal_state st
                LEFT JOIN signals sg ON sg.id = st.signal_id
                INNER JOIN symbols sy ON sy.symbol = st.symbol AND sy.enabled = 1"
              . Visibility::sql('scanner');
        $args = [];
        if ($tf !== '') {
            $sql .= ' WHERE st.tf = ?';
            $args[] = $tf;
        }
        // Four times the requested count because the rows are filtered in PHP
        // afterwards - by tier, timeframe, staleness and grade - so the query
        // has to over-fetch to be able to fill the page. With no limit asked
        // for, there is nothing to over-fetch against.
        $sql .= ' ORDER BY st.changed_at DESC'
              . ($limit > 0 ? ' LIMIT ' . (int)max(1, min(20000, $limit * 4)) : '');
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($args);

        $order = ['C' => 0, 'B' => 1, 'A' => 2, 'A+' => 3];
        $timeStop = max(1, (int)Database::setting('time_stop_bars', '24'));
        // Timeframes the site offers, so a coin's own list can be measured
        // against something. Filtered per row rather than in the query: the
        // list is per coin and stored as text, which SQL would have to match
        // with a LIKE per timeframe for no gain on a table this size.
        $siteTfs = Visibility::siteTfs(self::tfList());
        $rows = [];
        foreach ($stmt as $r) {
            $drops['seen']++;
            // A coin untimed on this frame is not shown on it. The verdict may
            // still be in the table from before the operator narrowed the
            // coin's timeframes, and it should stop being published the moment
            // they did.
            if (!Visibility::allowsTf((string)$r['symbol'], (string)$r['tf'], $siteTfs)) {
                $drops['tf_off']++;
                continue;
            }
            // Have we looked at this pair recently enough to show it at all?
            //
            // The board is the last background scan, and a coin can stop being
            // scanned - unticked from the rotation, or dropped from it after
            // failing - while its last verdict stays in the table. Nothing
            // would ever correct it: the row would present a call from an
            // unknown number of days ago as the current read, for ever. The
            // cut-off is the setup's own horizon, so anything the engine would
            // no longer stand behind is simply not listed. Opening the coin
            // still analyses it live.
            $tfSec = MarketData::TF_SECONDS[$r['tf']] ?? 3600;
            if (time() - (int)$r['updated_at'] > $timeStop * $tfSec) {
                $drops['stale']++;
                continue;
            }
            // The public reference, same field the closed board and the chart
            // panel already show - empty on a NEUTRAL row (there is no call to
            // name) and on a state row with no ledger entry behind it yet.
            $r['ref'] = '';
            if ($r['g_at'] !== null && $r['signal'] !== 'NEUTRAL') {
                $r['score'] = $r['g_score'];
                $r['confidence'] = $r['g_conf'];
                $r['price'] = $r['g_price'];
                $r['indicators'] = $r['g_ind'];
                $r['changed_at'] = $r['g_at'];
                $r['ref'] = (string)($r['g_ref'] ?? '');
            }
            $r['created_at'] = $r['changed_at'];
            $r['outcome'] = (string)($r['g_outcome'] ?? '');
            // A SETTLED CALL IS NOT A SETUP.
            //
            // The board joins the ledger row that governs each verdict and has
            // always READ its outcome - it just never acted on it. So a call
            // that hit its stop yesterday, or ran all three targets and
            // closed, went on being listed as a live BUY with its published
            // entry beside it, indefinitely: signal_state only rewrites a
            // verdict when the verdict CHANGES, and settling is not a change
            // of verdict. The only thing that ever sank such a row was the
            // separate time-stop test below, which asks whether the setup is
            // OLD - a different question, and one a stopped-out call an hour
            // into a 4h setup answers with "no".
            //
            // They are not lost: the Closed tab lists them with how far the
            // ladder got and what the call finally made.
            if ($r['outcome'] !== '' && $r['signal'] !== 'NEUTRAL') {
                $drops['settled']++;
                continue;
            }
            $locked = !MemberAuth::canAccess((string)$r['tier'], $viewerTier);
            $inds = json_decode((string)$r['indicators'], true);
            // Two readings on purpose. The visitor's own filter has always
            // treated "no grade recorded" as C, and changing that would move
            // rows around on a page nobody asked to change. The publish floor
            // needs the raw value, because a row from before grading existed
            // is not a C-grade setup - it is an unknown, and hiding it would
            // shrink an install's board the moment the operator set a floor.
            $rawGrade = is_array($inds) ? (string)($inds['grade'] ?? '') : '';
            $grade = $rawGrade !== '' ? $rawGrade : 'C';
            $levels = is_array($inds) ? ($inds['levels'] ?? null) : null;

            if ($want !== '' && $r['signal'] !== $want) {
                $drops['direction']++;
                continue;
            }
            // A coin with no setup is not a call. The board leaves them out
            // unless they are asked for by name, because a page whose job is
            // "what should I look at" cannot answer it under three hundred
            // rows saying "nothing".
            if (!empty($filter['exclude_neutral']) && $r['signal'] === 'NEUTRAL') {
                $drops['neutral']++;
                continue;
            }
            if ($minGrade !== '' && ($order[$grade] ?? 0) < ($order[$minGrade] ?? 0)) {
                $drops['grade_ask']++;
                continue;
            }
            // The site-wide publish floor, on top of whatever the visitor
            // asked for. A visitor can be stricter than the operator, never
            // looser - the floor decides what exists to be filtered.
            if (!Publish::allows($rawGrade)) {
                // Counted, not merely skipped. A board showing one row out of
                // fifty looks broken, and the difference between "the market
                // is quiet" and "your grade floor is hiding 49 of these" is
                // the difference between waiting and changing a setting. The
                // page could not tell the reader which, because nothing kept
                // the number.
                $drops['grade']++;
                continue;
            }
            if ($minScore > 0 && abs((float)$r['score']) < $minScore) {
                $drops['score']++;
                continue;
            }
            if ($search !== '' && !str_contains(strtoupper($r['symbol'] . ' ' . $r['label']), $search)) {
                $drops['search']++;
                continue;
            }
            // Rows stored before the types existed carry no tier. They are
            // dropped when a type is asked for - an unknown is not a match -
            // and left alone otherwise, so asking for nothing still shows
            // everything the board has.
            if ($wantTier > 0 && (int)(is_array($levels) ? ($levels['rr_tier'] ?? 0) : 0) !== $wantTier) {
                $drops['rr']++;
                continue;
            }

            // Has this setup outlived its own deadline?
            //
            // The board shows the newest stored verdict per pair, and a
            // verdict is only rewritten when it CHANGES - so a pair nobody has
            // looked at and cron has not rotated onto keeps presenting a call
            // from eight hours ago as if it were current. On a 15m chart that
            // is thirty-two bars after the engine's own time stop: the setup
            // is over, and the chart said so while the scanner did not.
            //
            // The signal publishes its deadline, so use it. Rows stored before
            // that existed fall back to the time stop for their timeframe,
            // which is what the verifier settles them on anyway.
            $expiresAt = is_array($levels) && !empty($levels['expires_at'])
                ? (int)$levels['expires_at']
                : (int)$r['created_at'] + $timeStop * (MarketData::TF_SECONDS[$r['tf']] ?? 3600);

            $rows[] = [
                'symbol' => $r['symbol'],
                'label'  => $r['label'],
                'tf'     => $r['tf'],
                'signal' => $r['signal'],
                'score'  => (float)$r['score'],
                'confidence' => (float)$r['confidence'],
                'grade'  => $grade,
                'price'  => (float)$r['price'],
                'levels' => $locked ? null : $levels,
                'rr'     => is_array($levels) ? ($levels['rr'] ?? null) : null,
                // Which of the three types this is: 1:1, 1:2 or 1:3. Carried
                // on the row rather than recomputed by every caller, and null
                // on a locked row like every other part of the plan.
                'rr_type' => is_array($levels) ? ($levels['rr_type'] ?? null) : null,
                'rr_tier' => is_array($levels) ? (int)($levels['rr_tier'] ?? 0) : 0,
                'age'    => time() - (int)$r['created_at'],
                'at'     => (int)$r['created_at'],
                'locked' => $locked,
                'tier'   => $r['tier'],
                'stale'  => is_array($levels) && !empty($levels['stale']),
                // Older than one candle of its own timeframe. The board is a
                // snapshot of the last background scan, not a live read: on a
                // long rotation a pair can be a full candle past the verdict
                // shown here, which is exactly how a row reading SELL leads to
                // a chart reading NEUTRAL. Flagged so the row says so itself.
                'aged'   => (time() - (int)$r['created_at']) > (MarketData::TF_SECONDS[$r['tf']] ?? 3600),
                'outcome' => $r['outcome'],
                'expires_at' => $expiresAt,
                'expired' => $r['signal'] !== 'NEUTRAL' && time() > $expiresAt,
                'ref'    => $r['ref'],
            ];
        }

        // Sort: strongest setups first by default.
        $sort = (string)($filter['sort'] ?? 'grade');
        usort($rows, function ($a, $b) use ($sort, $order) {
            // Expired setups sink, whatever the sort. A dead A+ is not a
            // better answer to "what should I look at" than a live B.
            if ($a['expired'] !== $b['expired']) {
                return $a['expired'] <=> $b['expired'];
            }
            return match ($sort) {
                'score'      => abs($b['score']) <=> abs($a['score']),
                'confidence' => $b['confidence'] <=> $a['confidence'],
                'recent'     => $a['age'] <=> $b['age'],
                'symbol'     => strcmp($a['symbol'], $b['symbol']),
                default      => [$order[$b['grade']] ?? 0, abs($b['score'])]
                                <=> [$order[$a['grade']] ?? 0, abs($a['score'])],
            };
        });

        // ALREADY MOVED IS ALREADY GONE FROM THIS LIST - BUT NOT OFF THE BOARD.
        //
        // A call whose price has since run to its first target, or through its
        // stop, is not a setup anybody can still take at the published entry -
        // and the entry is the whole reason THIS list exists. Settlement is
        // not the test: an unsettled call that reached TP1 four hours ago is
        // as untakeable as one the verifier has already closed, and the
        // verifier runs on cron rather than on the reader's page load.
        //
        // A stop touch is dropped outright - it is closing as a loss and the
        // verifier will settle it shortly, so there is nothing useful to show
        // in the meantime. A target touch is different: the call is still
        // open and still running, and a reader who took it wants to see that -
        // it goes to $progressList instead of vanishing, with the stage it
        // reached attached, so it can be shown in its own section rather than
        // mixed in with setups still open to enter. That is the whole point of
        // keeping the two apart: a fresh setup and one already three-fifths to
        // its target look identical once merged into one list, and a reader
        // cannot tell "you can still take this" from "this already ran"
        // without the split.
        //
        // Evaluated after the sort and only until the page is full, so the
        // cost is one indexed aggregate per row DISPLAYED rather than per row
        // considered. The walk is capped as well: a board with no row limit on
        // an install with thousands of pairs must not turn into thousands of
        // queries because the top of the list happened to be quiet.
        $live = [];
        $progressList = [];
        $checked = 0;
        $cap = $limit > 0 ? max(200, $limit * 4) : 800;
        foreach ($rows as $row) {
            if ($limit > 0 && count($live) >= $limit) {
                break;
            }
            if ($row['signal'] !== 'NEUTRAL' && is_array($row['levels']) && $checked < $cap) {
                $checked++;
                $hit = self::touched($row);
                if ($hit !== null) {
                    $drops['moved']++;
                    if ($hit !== 'stop' && ($limit <= 0 || count($progressList) < $limit)) {
                        $row['stage'] = $hit;
                        $progressList[] = $row;
                    }
                    continue;
                }
            }
            $live[] = $row;
        }
        $progress = $progressList;

        return $live;
    }

    /**
     * Has this call's own plan already been reached, either way?
     *
     * One aggregate over the candles stored since the call was published: the
     * best price in its favour and the worst against it. Returns the label of
     * the first thing it touched, or null when the plan is still untouched and
     * the entry is still there to be taken.
     *
     * WHICH CAME FIRST IS NOT ANSWERED HERE, and does not need to be. This is
     * not settlement - the verifier does that, on sub-candle data, and its
     * answer is what the track record publishes. This asks the much smaller
     * question the scanner needs: is the published entry still live. Once
     * either level has been reached the answer is no, whichever came first.
     *
     * A locked row carries no levels, so a viewer without access to a coin's
     * plan never triggers the query at all.
     *
     * @return string|null 'stop', 'tp3', 'tp2', 'tp1', or null for untouched
     */
    public static function touched(array $row): ?string
    {
        $lv = $row['levels'] ?? null;
        if (!is_array($lv)) {
            return null;
        }
        $long = strtoupper((string)($row['signal'] ?? 'BUY')) === 'BUY';
        try {
            $stmt = Database::pdo()->prepare(
                'SELECT MAX(high) AS hi, MIN(low) AS lo FROM candles
                 WHERE symbol = ? AND tf = ? AND open_time > ?'
            );
            $stmt->execute([(string)$row['symbol'], (string)$row['tf'], (int)$row['at'] * 1000]);
            $agg = $stmt->fetch();
            $stmt->closeCursor();
        } catch (\Throwable $e) {
            // No candle history is not evidence that the plan was hit. A board
            // that hides rows when a query fails is worse than one that shows
            // a stale row, because the reader cannot tell it happened.
            return null;
        }
        if (!$agg || $agg['hi'] === null) {
            return null;
        }
        $best  = $long ? (float)$agg['hi'] : (float)$agg['lo'];
        $worst = $long ? (float)$agg['lo'] : (float)$agg['hi'];

        $stop = (float)($lv['stop_loss'] ?? 0);
        if ($stop > 0 && ($long ? $worst <= $stop : $worst >= $stop)) {
            return 'stop';
        }
        // Highest first, so a call that ran the whole ladder is reported as
        // what it achieved rather than as the first line it crossed.
        foreach (['tp3', 'tp2', 'tp1'] as $k) {
            $tp = (float)($lv[$k] ?? 0);
            if ($tp > 0 && ($long ? $best >= $tp : $best <= $tp)) {
                return $k;
            }
        }
        return null;
    }

    /**
     * The Closed tab: calls this board used to carry, and how they finished.
     *
     * The live board drops a setup the moment it settles, which is right - a
     * finished call is not something to take - and on its own it is also how a
     * board earns a reputation for hiding its losers. The row does not
     * disappear, it moves, and it arrives with the thing the live board could
     * not show: which of the three targets the price actually reached before
     * it was over.
     *
     * THE LADDER IS RECONSTRUCTED, NOT LOOKED UP. The ledger stores one
     * outcome and one note - "TP2 reached" - which says where it stopped, not
     * where it got to on the way. Asking the candles for the best price
     * between the call and its close answers all three at once, and answers
     * them for a stopped-out call too, where the note says only "stop hit" and
     * the interesting fact is whether it had banked TP1 first.
     *
     * Bounded by the window rather than by a row count, so "the last two days"
     * means the same thing on a quiet install and a busy one, with the count
     * capped so a very busy one cannot render ten thousand rows.
     *
     * @return list<array<string,mixed>>
     */
    public static function closedBoard(array $filter = [], int $hours = 48, int $limit = 200): array
    {
        $viewerTier = MemberAuth::tier();
        $since = time() - max(1, $hours) * 3600;
        $tf = (string)($filter['tf'] ?? '');
        $want = strtoupper((string)($filter['signal'] ?? ''));
        $search = strtoupper(trim((string)($filter['search'] ?? '')));

        $sql = "SELECT sg.symbol, sg.tf, sg.`signal`, sg.price, sg.indicators, sg.grade,
                       sg.created_at, sg.closed_at, sg.outcome, sg.outcome_note, sg.outcome_r,
                       sg.ref, sy.label, sy.tier
                FROM signals sg
                INNER JOIN symbols sy ON sy.symbol = sg.symbol AND sy.enabled = 1
                WHERE sg.outcome IN ('confirmed','invalid','expired')
                  AND sg.`signal` != 'NEUTRAL'
                  AND sg.closed_at >= ?"
              . Visibility::sql('scanner')
              . Publish::sql('sg');
        $args = [$since];
        if ($tf !== '') {
            $sql .= ' AND sg.tf = ?';
            $args[] = $tf;
        }
        $sql .= ' ORDER BY sg.closed_at DESC LIMIT ' . (int)max(1, min(1000, $limit * 3));

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($args);
        $siteTfs = Visibility::siteTfs(self::tfList());
        $out = [];
        foreach ($stmt as $r) {
            if (count($out) >= $limit) {
                break;
            }
            if (!Visibility::allowsTf((string)$r['symbol'], (string)$r['tf'], $siteTfs)) {
                continue;
            }
            if ($want !== '' && $r['signal'] !== $want) {
                continue;
            }
            if ($search !== '' && !str_contains(strtoupper($r['symbol'] . ' ' . $r['label']), $search)) {
                continue;
            }
            $inds = json_decode((string)$r['indicators'], true);
            $lv = is_array($inds) ? ($inds['levels'] ?? null) : null;
            // A FINISHED PLAN IS SHOWN TO EVERYONE.
            //
            // The live board hides a premium coin's entry and targets because
            // the plan is the product. This one cannot be traded, so there is
            // nothing left to sell and every reason to show it: a board that
            // locks its own history is asking to be taken on trust. Same rule
            // as the settled rows on the track record.
            // THE OUTCOME IS THE AUTHORITY. THE CANDLES ONLY REFINE IT.
            //
            // This engine's three endings already answer most of the ladder:
            // `confirmed` means TP1 came before the stop, `invalid` means the
            // stop came first, and `expired` means neither happened inside the
            // time stop. So a row that is not confirmed reached no target, by
            // definition, and asking the candles is not a second opinion - it
            // is a chance to contradict the record.
            //
            // It took that chance. Rows settled as expired - the setup went
            // NOWHERE - were printing all three targets ticked, because the
            // aggregate found an extreme that cleared them inside a window the
            // verifier had already judged. Whatever the cause in any one case
            // (a level from a different era of the coin, a gap in the stored
            // candles), the board must not tell a reader something the record
            // it is drawn from denies.
            //
            // What the candles are still needed for is the one thing the
            // outcome does not say: which of TP2 and TP3 a confirmed call went
            // on to reach. TP1 is taken from the outcome itself rather than
            // re-derived, so the two can never disagree.
            $hit = ['tp1' => false, 'tp2' => false, 'tp3' => false];
            if (is_array($lv) && $r['outcome'] === 'confirmed') {
                // A TARGET ON THE WRONG SIDE OF ENTRY IS NOT A TARGET.
                //
                // Paper::reached asks whether the best price since the call
                // reached each level, and for a SELL that is a MIN compared
                // with <=. Hand it a malformed plan whose targets sit ABOVE
                // the entry and every one of them reads as reached, because
                // the lowest low of the last week is below a number the price
                // never had to travel towards. Rows settled as expired - the
                // setup went nowhere - were duly printing all three targets
                // ticked, which is the most misleading thing this board could
                // say. Levels are dropped unless they are in profit from
                // entry; the check belongs here rather than in Paper, which
                // settles the record and must not change what it once said.
                $entry = (float)($lv['entry'] ?? $r['price']);
                $long = strtoupper((string)$r['signal']) === 'BUY';
                $ok = static function ($v) use ($entry, $long): float {
                    $t = (float)($v ?? 0);
                    if ($t <= 0 || $entry <= 0) {
                        return 0.0;
                    }
                    return ($long ? $t > $entry : $t < $entry) ? $t : 0.0;
                };
                $hit = Paper::reached([
                    'symbol'    => $r['symbol'],
                    'tf'        => $r['tf'],
                    'side'      => $r['signal'],
                    'opened_at' => (int)$r['created_at'],
                    'closed_at' => (int)$r['closed_at'],
                    'tp1'       => $ok($lv['tp1'] ?? 0),
                    'tp2'       => $ok($lv['tp2'] ?? 0),
                    'tp3'       => $ok($lv['tp3'] ?? 0),
                ]);
                $hit['tp1'] = true;      // the outcome says so
            }
            $rawGrade = is_array($inds) ? (string)($inds['grade'] ?? '') : (string)($r['grade'] ?? '');
            $out[] = [
                'symbol' => $r['symbol'],
                'label'  => $r['label'],
                'tf'     => $r['tf'],
                'signal' => $r['signal'],
                // The public reference, so a settled row can be quoted in a
                // support message the same way a live one can.
                'ref'    => (string)($r['ref'] ?? ''),
                // WHICH TYPE IT WAS, ON THE SETTLED ROWS TOO.
                //
                // The live board carries this and the settled one did not, so
                // a reader who had narrowed the scanner to 1:3 and switched to
                // Settled got an empty list - the filter runs in the browser
                // over whatever rows arrived, and a row with no tier matches no
                // type by name. It emptied silently, which reads as "this site
                // has never closed a 1:3 trade".
                //
                // It is also the pairing worth having: the type is what the
                // call promised and the R beside it is what it delivered.
                'rr_type' => is_array($lv) ? ($lv['rr_type'] ?? null) : null,
                'rr_tier' => is_array($lv) ? (int)($lv['rr_tier'] ?? 0) : 0,
                'grade'  => $rawGrade !== '' ? $rawGrade : 'C',
                'price'  => (float)$r['price'],
                'levels' => $lv,
                'at'     => (int)$r['created_at'],
                'closed_at' => (int)$r['closed_at'],
                'held'   => max(0, (int)$r['closed_at'] - (int)$r['created_at']),
                'outcome' => (string)$r['outcome'],
                'note'    => View::shortOutcome($r['outcome_note'] ?? '', (string)$r['outcome']),
                // Blank rather than zero for an expired setup: it did not make
                // nothing, it was never a trade.
                'r'       => $r['outcome'] === 'expired' ? null : (float)$r['outcome_r'],
                'tp1'     => !empty($hit['tp1']),
                'tp2'     => !empty($hit['tp2']),
                'tp3'     => !empty($hit['tp3']),
                'tier'    => $r['tier'],
            ];
        }
        $stmt->closeCursor();
        return $out;
    }

    /**
     * The current verdict for one pair/timeframe, for the alert dispatchers.
     *
     * They used to read the newest signals row, which worked only because a
     * NEUTRAL was written every time a pair went quiet. Now the verdict lives
     * in signal_state and the numbers come from the ledger row it governs, so
     * a subscriber is alerted about the call as it was published.
     *
     * `key` is what a dispatcher should remember instead of the bare verdict:
     * it carries the ledger row id, so a second BUY issued after the first one
     * closed still reads as a change and still sends. Returns null for a pair
     * that has never been scanned.
     */
    public static function current(string $symbol, string $tf): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT st.`signal`, st.score, st.confidence, st.price, st.indicators,
                    st.signal_id, st.changed_at,
                    sg.score AS g_score, sg.confidence AS g_conf, sg.price AS g_price,
                    sg.indicators AS g_ind, sg.created_at AS g_at, sg.outcome AS g_outcome,
                    sg.ref AS g_ref
             FROM signal_state st
             LEFT JOIN signals sg ON sg.id = st.signal_id
             WHERE st.symbol = ? AND st.tf = ?'
        );
        $stmt->execute([$symbol, $tf]);
        $r = $stmt->fetch();
        $stmt->closeCursor();
        if (!$r) {
            return null;
        }
        return self::shape($r);
    }

    /**
     * Every current verdict for a set of coins, in one query, keyed SYM:TF.
     *
     * THE ALERT SWEEPS ASK PER MEMBER; THE ANSWER IS PER PAIR. Both dispatchers
     * walk their subscriber rows and call current() for each watched pair, so
     * the cost is members x pairs - a thousand members watching the same three
     * hundred coins ran the same three hundred lookups a thousand times over,
     * three hundred thousand queries a minute for twelve hundred distinct
     * answers. Measured at 29us an iteration that is 8.6 seconds of a cron run
     * spent asking a question it had already answered, and it grows with the
     * subscriber list, which is the one number a business wants to grow.
     *
     * So the sweeps collect their coins first and load the answers once. The
     * result is bounded by coins x timeframes - it does not move when members
     * are added.
     *
     * Loaded by symbol rather than by symbol+timeframe: the pair list would
     * need a bound parameter each, and the number of coins is what makes the
     * query big. Chunked so a very long watchlist cannot exceed the driver's
     * placeholder limit.
     *
     * @param string[] $symbols
     * @return array<string, array> keyed "SYMBOL:TF"
     */
    public static function currentFor(array $symbols): array
    {
        $symbols = array_values(array_unique(array_filter($symbols)));
        if (!$symbols) {
            return [];
        }
        $out = [];
        foreach (array_chunk($symbols, 300) as $chunk) {
            $in = implode(',', array_fill(0, count($chunk), '?'));
            $stmt = Database::pdo()->prepare(
                'SELECT st.symbol, st.tf, st.`signal`, st.score, st.confidence, st.price,
                        st.indicators, st.signal_id, st.changed_at,
                        sg.score AS g_score, sg.confidence AS g_conf, sg.price AS g_price,
                        sg.indicators AS g_ind, sg.created_at AS g_at, sg.outcome AS g_outcome,
                        sg.ref AS g_ref
                 FROM signal_state st
                 LEFT JOIN signals sg ON sg.id = st.signal_id
                 WHERE st.symbol IN (' . $in . ')'
            );
            $stmt->execute($chunk);
            foreach ($stmt->fetchAll() as $r) {
                $out[$r['symbol'] . ':' . $r['tf']] = self::shape($r);
            }
            $stmt->closeCursor();
        }
        return $out;
    }

    /**
     * Coins an alert may fire for, as symbol => tier, in one query.
     *
     * Same story as currentFor: the sweeps ran this lookup once per member per
     * pair, and the answer depends on neither. Carries the identical enabled +
     * alert-visibility filter, so a coin missing from this map is a coin the
     * per-pair statement would have returned nothing for.
     *
     * @return array<string, string>
     */
    public static function alertTiers(): array
    {
        $out = [];
        foreach (Database::pdo()->query('SELECT symbol, tier FROM symbols WHERE enabled = 1'
                                        . Visibility::sql('alerts', 'symbols')) as $r) {
            $out[(string)$r['symbol']] = (string)$r['tier'];
        }
        return $out;
    }

    /** One signal_state row (joined to its ledger row) as a dispatcher sees it. */
    private static function shape(array $r): array
    {
        $frozen = $r['g_at'] !== null && $r['signal'] !== 'NEUTRAL';
        return [
            'signal'     => (string)$r['signal'],
            'score'      => (float)($frozen ? $r['g_score'] : $r['score']),
            'confidence' => (float)($frozen ? $r['g_conf'] : $r['confidence']),
            'price'      => (float)($frozen ? $r['g_price'] : $r['price']),
            'indicators' => (string)($frozen ? $r['g_ind'] : $r['indicators']),
            'at'         => (int)($frozen ? $r['g_at'] : $r['changed_at']),
            'outcome'    => (string)($r['g_outcome'] ?? ''),
            'signal_id'  => (int)$r['signal_id'],
            // The public reference of the ledger row this state points at.
            // Carried here rather than looked up by each dispatcher, so the
            // email, the Telegram message and the webhook all quote the same
            // string for the same call - which is the entire value of having
            // one. Empty for a pair whose state has no ledger row yet.
            'ref'        => (string)($r['g_ref'] ?? ''),
            'key'        => (int)$r['signal_id'] > 0
                ? $r['signal'] . '#' . (int)$r['signal_id']
                : (string)$r['signal'],
        ];
    }

    /**
     * Has the verdict changed since the dispatcher last sent for this pair?
     *
     * Subscriptions written before the ledger id joined the key hold a bare
     * verdict; comparing those against the new key would read as a flip and
     * re-alert every watched pair the first time cron ran after an upgrade,
     * so they are compared the old way until the next real flip rewrites them.
     */
    /** Every interval the engine understands, newest-first order irrelevant. */
    private static function tfList(): array
    {
        return array_keys(MarketData::TF_SECONDS);
    }

    public static function flipped(?string $prev, array $cur): bool
    {
        if ($prev === null) {
            return false;
        }
        return str_contains($prev, '#')
            ? $prev !== $cur['key']
            : $prev !== $cur['signal'];
    }

    /**
     * Should this pair alert right now, for a subscriber who has never been
     * sent anything about it?
     *
     * flipped() answers false for a pair with no history, and that was the
     * whole behaviour: a member added twenty coins, every one of them was
     * silently recorded as "already sent" on the next run, and nothing arrived
     * until each coin's verdict happened to CHANGE. A coin sitting on a
     * standing BUY sends nothing for as long as it holds - days, on the higher
     * timeframes - and the member, reasonably, concludes the alerts are
     * broken.
     *
     * The original caution was right about the risk and wrong about the fix:
     * the danger was sixty separate emails on the first run, and batching
     * already solved that - one run is now one message however many pairs
     * moved. So a first sighting sends, with one condition: the call has to
     * still be live. A BUY whose entry was three days and thirty candles ago
     * is not news, it is a plan the reader cannot act on, and sending it is
     * worse than sending nothing. The deadline used is the signal's own, the
     * same one the scanner and the verifier use.
     */
    public static function firstSightFires(array $sig, string $tf): bool
    {
        if (!in_array($sig['signal'] ?? '', ['BUY', 'SELL'], true)) {
            return false;
        }
        if (($sig['outcome'] ?? '') !== '') {
            return false;           // already settled - hit its target or its stop
        }
        $inds = json_decode((string)($sig['indicators'] ?? ''), true);
        $levels = is_array($inds) ? ($inds['levels'] ?? null) : null;
        $timeStop = max(1, (int)Database::setting('time_stop_bars', '24'));
        $expiresAt = is_array($levels) && !empty($levels['expires_at'])
            ? (int)$levels['expires_at']
            : (int)($sig['at'] ?? 0) + $timeStop * (MarketData::TF_SECONDS[$tf] ?? 3600);
        return time() <= $expiresAt;
    }

    /**
     * Does a watched pair fire for this subscriber on this run?
     *
     * One decision, used by email, push and Telegram alike - they each had
     * their own copy of `flipped()` at the same point in the same loop, so a
     * change to the rule had three places to miss.
     */
    public static function shouldAlert(?string $prev, array $sig, string $tf): bool
    {
        if ($prev !== null) {
            return self::flipped($prev, $sig);
        }
        return Database::setting('alert_on_subscribe', '1') === '1'
            && self::firstSightFires($sig, $tf);
    }

    /**
     * The best actionable setups right now: directional, well graded, fresh,
     * and not already run away from their entry.
     */
    public static function topSetups(int $limit = 12): array
    {
        $rows = self::run(['sort' => 'grade'], 400);
        // A flat age cap could not mean the same thing on every timeframe:
        // three hours is thirty-six bars on 5m and a quarter of a bar on the
        // daily. The setup's own deadline does. The setting is kept as an
        // upper bound for operators who want one.
        $maxAge = max(300, (int)Database::setting('top_setup_max_age', '10800'));
        $out = [];
        foreach ($rows as $r) {
            if ($r['signal'] === 'NEUTRAL' || $r['locked'] || $r['stale'] || $r['expired']) {
                continue;
            }
            // Already settled: the stop or the first target has been hit, so
            // the entry it published is history, not a setup to take now.
            if ($r['outcome'] !== '') {
                continue;
            }
            if ($r['age'] > $maxAge || !in_array($r['grade'], ['A+', 'A', 'B'], true)) {
                continue;
            }
            $out[] = $r;
            if (count($out) >= $limit) {
                break;
            }
        }
        return $out;
    }

    /** Counts for the scanner header: how the market is leaning overall. */
    public static function breadth(string $tf = ''): array
    {
        // Calls only. A count of coins with nothing to say was the largest
        // number on the page and the least useful one - it moved with the
        // size of the watchlist, not with the market, and it was read at a
        // glance as though it meant something about direction.
        $rows = self::run($tf !== '' ? ['tf' => $tf, 'exclude_neutral' => true]
                                     : ['exclude_neutral' => true], 1000);
        $buy = $sell = 0;
        foreach ($rows as $r) {
            if ($r['signal'] === 'BUY') {
                $buy++;
            } elseif ($r['signal'] === 'SELL') {
                $sell++;
            }
        }
        $directional = $buy + $sell;
        return [
            'buy' => $buy, 'sell' => $sell,
            'total' => $directional,
            // Share of calls that are bullish - a simple, honest breadth
            // reading rather than a proprietary-sounding index.
            'bull_pct' => $directional > 0 ? round($buy / $directional * 100) : null,
        ];
    }
}
