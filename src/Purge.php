<?php
declare(strict_types=1);

namespace SignalMasterAi;

/**
 * Deleting data, in one place, as one list of choices.
 *
 * Removing things was spread across six admin pages and every one of them
 * decided for the operator what else went with it. Deleting a coin took its
 * candles and its verdict and - depending on a checkbox on that page only -
 * its signals; deleting the signal history took the live board and, again
 * depending on a box on a different page, the learned weights; clearing the
 * market cache lived under Settings. The same four kinds of data were
 * deletable from three pages with three different sets of side effects, and
 * nowhere could an operator see what any of it would actually remove.
 *
 * The question is always the same two questions - WHICH COINS, and WHICH KINDS
 * OF DATA - so it is asked once, in one form, with a count beside every line.
 *
 * KINDS ARE INDEPENDENT ON PURPOSE. "Delete the coin" and "delete its signals"
 * are different decisions and the second one rewrites the published track
 * record. "Delete the signals" and "delete what the engine learned from them"
 * are different decisions too: the learning is the conclusion, the signals are
 * the evidence, and clearing the evidence while keeping the conclusion leaves
 * the engine acting on something it can no longer revise. Bundling any of
 * these is how an operator loses a year of record while tidying up a coin
 * list.
 *
 * NOTHING HERE HAS A DEFAULT THAT DELETES. Every kind is opt-in per run; the
 * caller passes exactly what was ticked.
 */
final class Purge
{
    /**
     * Everything that can be deleted, once.
     *
     * key => [label, table, per-coin?, hint]
     *
     * `per-coin?` is whether narrowing to one coin means anything for this
     * kind. The learned model, for instance, is fitted across the whole
     * install, so "delete it for BTCUSDT" is not a thing that can be done.
     */
    public const KINDS = [
        'coin' => ['The coin itself', 'symbols', true,
            'Removes it from the scanner, charts, backtest and every watchlist. Reversible - add it '
            . 'back and it rescans.'],
        'signals' => ['Signal history', 'signals', true,
            'The published record: this page, the public track record and every figure drawn from '
            . 'them. Cannot be undone.'],
        'paper_trades' => ['Paper trades', 'paper_trades', true,
            'Members\' simulated positions and their journal entries against them.'],
        'learning' => ['What the engine learned', 'symbol_learning', true,
            'Per-coin rule multipliers, learned from settled outcomes. The shared rule weights in '
            . 'the knowledge base are not touched.'],
        'candles' => ['Cached candles', 'candles', true,
            'A download, not a record - whatever is still listed re-fetches on first view. This is '
            . 'what the backtester replays, so clearing it empties the backtester until it refills.'],
        'shadow_signals' => ['Shadow signals', 'shadow_signals', true,
            'Setups the engine declined, settled anyway to measure what refusing cost you.'],
        'board' => ['The live board', 'signal_state', true,
            'What the engine currently thinks, one row per coin and timeframe. Cron rebuilds it on '
            . 'the next run.'],
        'alerts_queued' => ['Queued alerts', 'alert_queue', true,
            'Anything waiting to be delivered. Already-sent alerts are not affected.'],
        // The only kind whose rows are not found by a symbol COLUMN - the
        // cache is keyed by string, so it needs its own matcher below.
        'analysis' => ['Cached analysis and backtests', 'cache', true,
            'The coin\'s saved backtest results, higher-timeframe reads, funding figures and gap '
            . 'reports. Working notes, not a record: every one of them is rebuilt on demand. Left '
            . 'behind when a coin is deleted they are simply stale, and they are what makes a '
            . 'backtest of a re-added coin show yesterday\'s answer.'],
    ];

    /** Kinds whose deletion changes what the public site publishes. */
    public const PUBLIC_FACING = ['signals'];

    /**
     * Normalise whatever a caller passes as a scope into a coin list or null.
     *
     * One place, because "which coins" arrives as a string from the coin list's
     * own Delete button, as an array of ticked boxes from the delete screen,
     * and as nothing at all when the answer is "every coin" - and the
     * difference between an empty list and null is the difference between
     * deleting nothing and deleting everything.
     *
     * @param  string|array<int,string>|null $scope
     * @return list<string>|null null means every coin
     */
    private static function scope(string|array|null $scope): ?array
    {
        if ($scope === null) {
            return null;
        }
        $list = is_array($scope) ? $scope : [$scope];
        $out = [];
        foreach ($list as $s) {
            $s = strtoupper(trim((string)$s));
            if ($s !== '' && !in_array($s, $out, true)) {
                $out[] = $s;
            }
        }
        return $out;
    }

    /**
     * How much there is, per kind, for one coin, several, or all of them.
     *
     * @param  string|array<int,string>|null $symbols null for every coin
     * @return array<string,int>
     */
    public static function counts(string|array|null $symbols = null): array
    {
        $pdo = Database::pdo();
        $syms = self::scope($symbols);
        $out = [];
        foreach (self::KINDS as $key => [$label, $table, $perCoin, $hint]) {
            try {
                // The cache has no symbol column - it is keyed by string, so
                // it is counted the same way it is deleted. Without this the
                // delete screen would offer a kind and show it as empty.
                if ($key === 'analysis') {
                    $out[$key] = self::countCache($syms);
                    continue;
                }
                if ($syms !== null && $perCoin) {
                    if (!$syms) {
                        // An empty selection is not "everything" - it is a
                        // selection of nothing, and it counts as nothing.
                        $out[$key] = 0;
                        continue;
                    }
                    $in = implode(',', array_fill(0, count($syms), '?'));
                    $st = $pdo->prepare("SELECT COUNT(*) FROM $table WHERE symbol IN ($in)");
                    $st->execute($syms);
                    $out[$key] = (int)$st->fetchColumn();
                    $st->closeCursor();
                } else {
                    $out[$key] = (int)$pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
                }
            } catch (\Throwable $e) {
                // A table this install has not migrated to yet counts as empty
                // rather than breaking the page that reports on all of them.
                $out[$key] = 0;
            }
        }
        return $out;
    }

    /**
     * Delete the chosen kinds, for one coin or for all of them.
     *
     * @param  list<string>                  $kinds   keys from KINDS
     * @param  string|array<int,string>|null $symbols one coin, several, or null for all
     * @return array<string,int> rows removed per kind, for the flash and the audit line
     */
    public static function run(array $kinds, string|array|null $symbols = null): array
    {
        $pdo = Database::pdo();
        $syms = self::scope($symbols);
        // A named-but-empty selection deletes nothing. Treating it as "every
        // coin" would turn a form submitted with no boxes ticked into a wipe.
        if ($syms !== null && !$syms) {
            return [];
        }
        $some = $syms !== null;
        $want = array_values(array_intersect(array_keys(self::KINDS), $kinds));
        $out = [];

        // Captured BEFORE the kind-processing loop below runs - if 'coin' is
        // one of the kinds being purged for EVERY coin, that loop's own pass
        // over 'coin' (always first - see KINDS' declared order, preserved
        // by array_intersect above) does `DELETE FROM symbols` outright, so
        // reading "which symbols existed" from the symbols table AFTER that
        // loop would always find it already empty and walk nothing. This is
        // exactly what quietly broke Watchlists::purgeSymbol() below: it
        // never ran for any coin on a "delete all coins" purge, leaving
        // members' named watchlists and alert/push subscriptions pointing at
        // symbols that no longer exist, forever.
        $coinWalk = null;
        if (in_array('coin', $want, true)) {
            $coinWalk = $syms;
            if ($coinWalk === null) {
                $coinWalk = [];
                foreach ($pdo->query('SELECT symbol FROM symbols') as $r) {
                    $coinWalk[] = (string)$r['symbol'];
                }
            }
        }

        // Whole-table deletes when every coin is in scope: on an install with a
        // year of candles, row-by-row is minutes and this is seconds.
        $go = static function (string $table) use ($pdo, $some, $syms): int {
            try {
                if ($some) {
                    $in = implode(',', array_fill(0, count($syms), '?'));
                    $st = $pdo->prepare("DELETE FROM $table WHERE symbol IN ($in)");
                    $st->execute($syms);
                    return $st->rowCount();
                }
                return $pdo->exec("DELETE FROM $table") ?: 0;
            } catch (\Throwable $e) {
                return 0;
            }
        };

        foreach ($want as $key) {
            if ($key === 'learning') {
                // Its own class: the cache it keeps has to be dropped with the
                // rows, or the engine goes on applying weights this just
                // deleted until the next request.
                if ($some) {
                    $n = 0;
                    foreach ($syms as $one) {
                        $n += SymbolLearning::forget($one);
                    }
                    $out[$key] = $n;
                } else {
                    $out[$key] = SymbolLearning::forgetAll();
                }
                continue;
            }
            if ($key === 'analysis') {
                $out[$key] = self::purgeCache($syms);
                continue;
            }
            $out[$key] = $go(self::KINDS[$key][1]);
        }

        // Removing the coin takes the things that are meaningless without it
        // whether or not they were ticked - a watchlist entry for a coin that
        // does not exist can never fire, and logs an unclearable error on every
        // alert sweep. These are not data an operator would choose to keep;
        // they are pointers at something that has gone.
        if (in_array('coin', $want, true)) {
            $out['fetch_log'] = $go('fetch_log');
            // Watchlists store "SYMBOL:tf" inside a CSV, so they are rewritten
            // per coin whichever scope this is - see Watchlists::purgeSymbol
            // for why a LIKE-and-REPLACE cannot do it. $coinWalk was captured
            // above, before the kind-processing loop had a chance to empty
            // the symbols table out from under this list.
            $walk = $coinWalk ?? [];
            $n = 0;
            foreach ($walk as $one) {
                $n += Watchlists::purgeSymbol($one);
            }
            $out['watchlists'] = $n;
            $out['board'] = ($out['board'] ?? 0) + $go('signal_state');
        }

        // The live board points at ledger rows. Delete signals without
        // clearing it and the scanner keeps serving calls whose plans are
        // gone; the state table is rebuilt from what is left instead.
        if (in_array('signals', $want, true) && !in_array('board', $want, true)
            && !in_array('coin', $want, true)) {
            try {
                Maintenance::syncSignalState();
            } catch (\Throwable $e) {
            }
        }

        return array_filter($out, static fn(int $n): bool => $n > 0);
    }

    /**
     * Cache rows belonging to a coin.
     *
     * Everything else here is found by a symbol column; the cache is keyed by
     * a string - "btres2:BTCUSDT:1h", "htf:BTCUSDT:4h:0", "fund:BTCUSDT" - so
     * it needs its own matcher, and the matcher has to be exact about
     * boundaries. A plain LIKE '%BTC%' would take BTCUSDT, ETHBTC and
     * BTCDOMUSDT with it: the symbol always sits between colons or at the end,
     * so that is what is matched.
     *
     * Backslash cannot be the LIKE escape on MySQL, which is why the rest of
     * this codebase uses '!' - the same choice here, though these keys have no
     * wildcards in them today.
     */
    /** The same match as purgeCache(), counted instead of deleted. */
    private static function countCache(?array $syms): int
    {
        $pdo = Database::pdo();
        try {
            if ($syms === null) {
                return (int)$pdo->query('SELECT COUNT(*) FROM cache')->fetchColumn();
            }
            if (!$syms) {
                return 0;
            }
            $n = 0;
            foreach ($syms as $one) {
                $st = $pdo->prepare(
                    "SELECT COUNT(*) FROM cache WHERE ckey LIKE ? ESCAPE '!'
                        OR ckey LIKE ? ESCAPE '!' OR ckey LIKE ? ESCAPE '!'");
                $st->execute(['%:' . $one . ':%', '%:' . $one, $one . ':%']);
                $n += (int)$st->fetchColumn();
                $st->closeCursor();
            }
            return $n;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private static function purgeCache(?array $syms): int
    {
        $pdo = Database::pdo();
        try {
            if ($syms === null) {
                return $pdo->exec('DELETE FROM cache') ?: 0;
            }
            $n = 0;
            foreach ($syms as $one) {
                $st = $pdo->prepare(
                    "DELETE FROM cache WHERE ckey LIKE ? ESCAPE '!' OR ckey LIKE ? ESCAPE '!'");
                $st->execute(['%:' . $one . ':%', '%:' . $one]);
                $n += $st->rowCount();
                // Some keys lead with the symbol rather than a prefix.
                $st = $pdo->prepare("DELETE FROM cache WHERE ckey LIKE ? ESCAPE '!'");
                $st->execute([$one . ':%']);
                $n += $st->rowCount();
            }
            return $n;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /** "11 signals, 3,609 cached candles, 2 learned rule weights" - non-zero only. */
    public static function describe(array $counts): string
    {
        $words = [
            'coin'           => ['coin', 'coins'],
            'signals'        => ['signal', 'signals'],
            'paper_trades'   => ['paper trade', 'paper trades'],
            'learning'       => ['learned rule weight', 'learned rule weights'],
            'candles'        => ['cached candle', 'cached candles'],
            'shadow_signals' => ['shadow signal', 'shadow signals'],
            'board'          => ['live verdict', 'live verdicts'],
            'analysis'       => ['cached analysis', 'cached analyses'],
            'alerts_queued'  => ['queued alert', 'queued alerts'],
            'fetch_log'      => ['fetch-log row', 'fetch-log rows'],
            'watchlists'     => ['watchlist entry', 'watchlist entries'],
        ];
        $bits = [];
        foreach ($words as $k => [$one, $many]) {
            $n = (int)($counts[$k] ?? 0);
            if ($n > 0) {
                $bits[] = number_format($n) . ' ' . ($n === 1 ? $one : $many);
            }
        }
        return $bits ? implode(', ', $bits) : 'nothing - there was none of it';
    }
}
