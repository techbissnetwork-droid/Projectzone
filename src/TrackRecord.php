<?php
declare(strict_types=1);

namespace SignalMasterAi;

/**
 * The site's record, counted once.
 *
 * Four pages asked "how is the engine doing" and three of them got a different
 * answer, because each carried its own copy of the query and only one carried
 * the whole filter:
 *
 *   track record   106 settled, 54.7%    (visibility + publish floor + tfs)
 *   admin panel    146 settled, 59.6%    (no filter at all)
 *   landing page   its own third number  (no filter, under a comment that
 *                                         said "counted the same way as the
 *                                         track record")
 *   open calls     72 on one page, 120 on the other
 *
 * None of that was a maths bug. Every one of those queries is correct about
 * the population it selects; they simply did not agree on the population. And
 * an operator reading two of them side by side cannot tell that - they see one
 * number contradicting another and no way to know which to believe, which is
 * corrosive on the one part of the product whose whole job is being believed.
 *
 * So there is one definition here and the pages call it. Patching the three
 * that were wrong would have left the fourth page anyone adds free to get it
 * wrong again in exactly the same way, and the landing page proves that risk
 * is real: it was already carrying a comment claiming it matched.
 *
 * THE POPULATION IS WHAT THE SITE PUBLISHED. Not what the engine produced -
 * a signal nobody was ever shown is not part of this site's record, and
 * counting it answers "how good is the engine" when every reader is asking
 * "how did the calls I would have received do". The two differ by exactly the
 * setups the operator chose to withhold, which is the operator's own decision
 * showing up in their own numbers.
 */
class TrackRecord
{
    /** Per-request memo. These are read several times per page render. */
    private static array $memo = [];

    /**
     * The WHERE fragment that defines "published": coins visible on the track
     * record, timeframes the site runs, and the publish grade floor.
     *
     * @param string $alias table alias for the signals table, '' for none
     */
    public static function sql(string $alias = ''): string
    {
        $intervals = self::intervals();
        return ($alias === ''
                ? Visibility::subquery('track')
                : Visibility::subquery('track', $alias . '.symbol'))
            . Publish::sql($alias)
            . Visibility::tfSql($intervals, $alias);
    }

    /**
     * WHY THE TRACK RECORD SHOWS THE NUMBER IT SHOWS.
     *
     * Reported: a site had 50 verified signals, an operator changed something
     * in the panel, and it became 13 - and putting the setting back left it at
     * 13. Nothing on the page could explain either half of that, because the
     * count is the end of a pipeline with four ways to shrink and no way to
     * see which one did it.
     *
     * Three of the four are filters and are reversible: a coin hidden from the
     * track surface, the grade publish floor, and a timeframe switched off
     * site-wide. The fourth is not: signal_retention_days deletes settled rows
     * on the next cron run, and no setting change brings them back.
     *
     * That is the distinction this answers. If `settled` is still 50 the rows
     * are there and a filter is hiding them - and each line says how many it
     * removes. If `settled` is itself 13, they were deleted, and the only
     * question left is by what.
     *
     * `prunable` is the same question asked forwards: how many settled signals
     * the CURRENT retention window would delete on the next run. An operator
     * about to lower it can see the cost before paying it.
     *
     * @return array<string,mixed>
     */
    public static function census(): array
    {
        $pdo = Database::pdo();
        $one = static function (string $where) use ($pdo): int {
            try {
                return (int)$pdo->query(
                    "SELECT COUNT(*) FROM signals WHERE outcome IN ('confirmed','invalid')" . $where
                )->fetchColumn();
            } catch (\Throwable $e) {
                return 0;
            }
        };
        $settled = $one('');
        $intervals = self::intervals();
        $visOnly = $one(Visibility::subquery('track'));
        $pubOnly = $one(Publish::sql());
        $tfOnly  = $one(Visibility::tfSql($intervals));
        $published = $one(self::sql());

        $days = (int)Database::setting('signal_retention_days', '365');
        $prunable = 0;
        if ($days > 0) {
            try {
                $st = $pdo->prepare(
                    "SELECT COUNT(*) FROM signals
                      WHERE outcome NOT IN ('') AND closed_at > 0 AND closed_at < ?");
                $st->execute([time() - $days * 86400]);
                $prunable = (int)$st->fetchColumn();
                $st->closeCursor();
            } catch (\Throwable $e) {
                $prunable = 0;
            }
        }
        $oldest = 0;
        try {
            $oldest = (int)$pdo->query(
                "SELECT MIN(closed_at) FROM signals
                  WHERE outcome IN ('confirmed','invalid') AND closed_at > 0")->fetchColumn();
        } catch (\Throwable $e) {
        }

        return [
            'settled'        => $settled,
            'published'      => $published,
            // Each filter measured ALONE against the whole settled set, so the
            // lines are readable on their own. They do not sum to the total
            // removed - two filters can hide the same row - which is why the
            // published figure is queried rather than subtracted.
            'hidden_symbol'  => max(0, $settled - $visOnly),
            'hidden_grade'   => max(0, $settled - $pubOnly),
            'hidden_tf'      => max(0, $settled - $tfOnly),
            'retention_days' => $days,
            'prunable'       => $prunable,
            'oldest_closed'  => $oldest,
        ];
    }

    /**
     * Settled figures over the published set.
     *
     * A win is a trade that MADE MONEY, not one that reached a label:
     * "confirmed" means TP1 came before the stop, and with part of the
     * position banked there a trade can still end fractionally negative once
     * costs are taken. Counting one of those as a win inflates the headline
     * with a trade that lost.
     *
     * @return array{total:int,wins:int,sumR:float,winRate:?float,avgR:?float}
     */
    public static function stats(?int $days = null): array
    {
        // WINDOWED BY THE SAME METHOD, NOT A SECOND ONE.
        //
        // "How is it doing lately" and "how is it doing" are the same question
        // over a different slice, and answering them in two places is how a
        // 7-day win rate ends up computed differently from the all-time one -
        // a different definition of a win, or costs counted on one side only.
        // The window is a WHERE clause; everything after it is untouched.
        //
        // Measured on closed_at, not created_at: a call opened five weeks ago
        // and settled yesterday belongs to the week it was decided in, because
        // that is when its result became known. Sorting it by when it was made
        // would leave the last 7 days perpetually missing its slowest trades,
        // which are disproportionately the losers.
        $days = ($days !== null && $days > 0) ? $days : null;
        $key = 'stats:' . ($days ?? 'all');
        if (isset(self::$memo[$key])) {
            return self::$memo[$key];
        }
        $window = $days !== null
            ? ' AND closed_at >= ' . (time() - $days * 86400) : '';
        // The two sides of the average, not just the average.
        //
        // A win rate and an expectancy sitting side by side look like they
        // must agree, and they routinely do not: 64.6% winners with a negative
        // expectancy is not a contradiction, it is small wins and full-size
        // losses. Without the two halves nobody can see which, so the page
        // shows a good number next to a bad one and leaves the reader to
        // decide the site is broken. Summed here, where the population is
        // already defined, rather than re-queried by whoever wants to explain
        // it - that is how the win rate came to disagree with itself before.
        $row = Database::pdo()->query(
            "SELECT COUNT(*) t,
                    SUM(CASE WHEN outcome_r > 0 THEN 1 ELSE 0 END) w,
                    SUM(outcome_r) r,
                    SUM(CASE WHEN outcome_r > 0 THEN outcome_r ELSE 0 END) rwin,
                    SUM(CASE WHEN outcome_r < 0 THEN outcome_r ELSE 0 END) rloss,
                    SUM(CASE WHEN outcome_r < 0 THEN 1 ELSE 0 END) nloss
             FROM signals WHERE outcome IN ('confirmed','invalid')" . self::sql() . $window
        )->fetch();
        $total = (int)($row['t'] ?? 0);
        $nWin  = (int)($row['w'] ?? 0);
        $nLoss = (int)($row['nloss'] ?? 0);
        return self::$memo[$key] = [
            'total'   => $total,
            'wins'    => $nWin,
            // No 'losses' key. It counted outcome='invalid' while 'wins'
            // counted money, so wins + losses did not equal total - a loaded
            // gun for the next caller. Nothing read it.
            'sumR'    => (float)($row['r'] ?? 0),
            'winRate' => $total > 0 ? round($nWin / $total * 100, 1) : null,
            'avgR'    => $total > 0 ? round((float)$row['r'] / $total, 2) : null,
            // Average size of a winner and of a loser, in R. The loser is
            // returned positive - it is a magnitude, and printing "-0.9" beside
            // "+0.4" invites it to be read as a sum rather than a comparison.
            'avgWin'  => $nWin > 0 ? round((float)$row['rwin'] / $nWin, 2) : null,
            'avgLoss' => $nLoss > 0 ? round(abs((float)$row['rloss']) / $nLoss, 2) : null,
        ];
    }

    /** Calls issued and not yet settled, over the same published set. */
    public static function openCount(): int
    {
        return self::$memo['open'] ??= (int)Database::pdo()->query(
            "SELECT COUNT(*) FROM signals WHERE outcome = '' AND `signal` != 'NEUTRAL'" . self::sql()
        )->fetchColumn();
    }

    /**
     * Every published call, whatever state it is in - settled, still open, or
     * expired as a no-trade.
     *
     * The denominator for "N of those M calls have resolved". M was a count of
     * every signal the engine ever produced, so the landing page put an
     * unfiltered total and a published win rate on the same card and invited
     * the reader to divide one by the other. On this install that read "254 of
     * those 278", where the honest figure is 268 - the ten missing were never
     * published to anyone and have no business in a sentence about what was.
     */
    public static function publishedCount(): int
    {
        return self::$memo['published'] ??= (int)Database::pdo()->query(
            "SELECT COUNT(*) FROM signals WHERE `signal` != 'NEUTRAL'" . self::sql()
        )->fetchColumn();
    }

    /** Settled with no stop and no target reached - counted as no-trades. */
    public static function expiredCount(): int
    {
        return self::$memo['expired'] ??= (int)Database::pdo()->query(
            "SELECT COUNT(*) FROM signals WHERE outcome = 'expired'" . self::sql()
        )->fetchColumn();
    }

    /**
     * The same settled figures with NOTHING filtered out - every signal the
     * engine produced, published or not.
     *
     * Exists for one caller: the admin dashboard, which shows the gap between
     * the two so an operator can see whether their publish floor is picking
     * better calls or merely fewer. It is not a second version of the record
     * and no public page should use it.
     *
     * @return array{total:int,wins:int,winRate:?float}
     */
    public static function rawStats(): array
    {
        if (isset(self::$memo['raw'])) {
            return self::$memo['raw'];
        }
        $row = Database::pdo()->query(
            "SELECT COUNT(*) t, SUM(CASE WHEN outcome_r > 0 THEN 1 ELSE 0 END) w
             FROM signals WHERE outcome IN ('confirmed','invalid')"
        )->fetch();
        $total = (int)($row['t'] ?? 0);
        return self::$memo['raw'] = [
            'total'   => $total,
            'wins'    => (int)($row['w'] ?? 0),
            'winRate' => $total > 0 ? round((int)$row['w'] / $total * 100, 1) : null,
        ];
    }

    /**
     * The coins with the best verified record, best first.
     *
     * For "watch the winners" - a member should not have to read a table and
     * copy names across by hand when the site already knows which coins have
     * earned their place. Measured on THE PUBLISHED SET, like everything else
     * here: recommending a coin on the strength of calls nobody was shown
     * would be recommending a result the member could not have had.
     *
     * $minSample is not a detail. A coin with three settled trades and three
     * wins is a 100% win rate and means nothing; sorted without a floor it
     * would take every top slot and the feature would recommend precisely the
     * coins there is least reason to trust. The floor is what makes the
     * ordering honest, so it is a setting rather than a constant.
     *
     * Two orderings, because they answer different questions and disagree
     * often. Win rate is how often a call worked; return is how much the coin
     * actually made, and a coin can win 70% of the time and still lose money
     * if the losses are bigger than the wins. Neither is the "right" one, so
     * the member picks.
     *
     * @param string $by 'winrate' or 'return'
     * @return list<array{symbol:string,settled:int,wins:int,winRate:float,sumR:float}>
     */
    public static function topCoins(string $by = 'winrate', int $limit = 10, int $minSample = 10): array
    {
        $limit = max(1, min(500, $limit));
        $minSample = max(1, min(1000, $minSample));
        // *1.0 rather than a CAST: the two drivers spell their cast types
        // differently and integer division would silently make every win rate
        // 0 or 1 on one of them.
        $order = $by === 'return'
            ? 'SUM(outcome_r) DESC, COUNT(*) DESC'
            : 'SUM(CASE WHEN outcome_r > 0 THEN 1 ELSE 0 END) * 1.0 / COUNT(*) DESC, COUNT(*) DESC';
        $rows = Database::pdo()->query(
            "SELECT symbol,
                    COUNT(*) n,
                    SUM(CASE WHEN outcome_r > 0 THEN 1 ELSE 0 END) w,
                    SUM(outcome_r) r
             FROM signals WHERE outcome IN ('confirmed','invalid')" . self::sql() . "
             GROUP BY symbol
             HAVING COUNT(*) >= " . $minSample . "
             ORDER BY " . $order . "
             LIMIT " . $limit
        )->fetchAll();
        $out = [];
        foreach ($rows as $row) {
            $n = (int)$row['n'];
            $out[] = [
                'symbol'  => (string)$row['symbol'],
                'settled' => $n,
                'wins'    => (int)$row['w'],
                'winRate' => $n > 0 ? round((int)$row['w'] / $n * 100, 1) : 0.0,
                'sumR'    => round((float)$row['r'], 2),
            ];
        }
        return $out;
    }

    /**
     * The same verified win rate as topCoins(), looked up for a specific set
     * of symbols instead of ranked across all of them.
     *
     * topCoins() answers "who's winning" for a leaderboard; this answers "how
     * has THIS coin done" for a board that is already showing a specific list
     * of coins (the scanner) and wants to attach each row's own number rather
     * than a top-10 shortlist. Same population, same visibility and publish
     * floor, same minimum-sample floor - a coin under the floor is left out of
     * the result entirely rather than returned with a misleadingly precise
     * rate from three trades, so a caller can tell "0%" from "not enough data
     * yet" by whether the symbol is present.
     *
     * @param list<string> $symbols
     * @return array<string, array{settled:int,wins:int,winRate:float}> keyed by symbol
     */
    public static function winRatesFor(array $symbols, int $minSample = 5): array
    {
        $symbols = array_values(array_unique(array_filter(array_map(
            static fn($s) => strtoupper(trim((string)$s)),
            $symbols
        ), static fn($s) => $s !== '')));
        if (!$symbols) {
            return [];
        }
        $minSample = max(1, min(1000, $minSample));
        $ph = implode(',', array_fill(0, count($symbols), '?'));
        $rows = Database::pdo()->prepare(
            "SELECT symbol,
                    COUNT(*) n,
                    SUM(CASE WHEN outcome_r > 0 THEN 1 ELSE 0 END) w
             FROM signals WHERE outcome IN ('confirmed','invalid') AND symbol IN ($ph)" . self::sql() . "
             GROUP BY symbol
             HAVING COUNT(*) >= " . $minSample
        );
        $rows->execute($symbols);
        $out = [];
        foreach ($rows->fetchAll() as $row) {
            $n = (int)$row['n'];
            $out[(string)$row['symbol']] = [
                'settled' => $n,
                'wins'    => (int)$row['w'],
                'winRate' => $n > 0 ? round((int)$row['w'] / $n * 100, 1) : 0.0,
            ];
        }
        return $out;
    }

    /**
     * Every timeframe the product knows about.
     *
     * Read from config rather than taken as an argument, because a caller that
     * has to supply it is a caller that can supply the wrong one - and passing
     * a shorter list quietly narrows the record instead of failing. bootstrap
     * returns the config array; it is cheap and already in the opcache.
     */
    private static function intervals(): array
    {
        static $tfs = null;
        if ($tfs !== null) {
            return $tfs;
        }
        $cfg = require __DIR__ . '/../config.php';
        return $tfs = (array)($cfg['market']['intervals'] ?? ['15m', '1h', '4h', '1d']);
    }
}
