<?php
declare(strict_types=1);

namespace SignalMasterAi;

/**
 * Where a coin is allowed to appear.
 *
 * There were two levers on a coin and both were blunt. "Enabled" is
 * all-or-nothing - on every surface of the site or on none of them - and
 * "tier" only decides who may look, not where. So an operator who wanted a
 * pair charted but kept out of the scanner, or published for its history but
 * not followable with position sizing, had one answer available: turn the
 * whole coin off.
 *
 * The master switch stays the master switch, and now genuinely means it: a
 * disabled coin is gone from every surface listed here, including the public
 * track record, which was quietly still publishing the history of coins the
 * operator had switched off months earlier. Below it, each surface can be
 * turned off on its own.
 *
 * Stored on symbols.hide_on as the surfaces to HIDE, comma-delimited with
 * sentinel commas (",scanner,track,"). Two consequences worth the slight
 * awkwardness: empty means "everywhere", so every coin that already exists
 * keeps behaving exactly as it did, and a LIKE '%,key,%' match works
 * identically on SQLite and MySQL, so the filter can live in the query
 * instead of in a loop over every row.
 */
class Visibility
{
    /**
     * The surfaces, in the order they are offered to the operator.
     *
     * @var array<string,array{0:string,1:string}> key => [label, what it covers]
     */
    public const SURFACES = [
        'chart'     => ['Chart', 'the coin picker on the chart page, and chart data from the API'],
        'scanner'   => ['Scanner', 'the scanner board, top setups and the latest-calls list on the home page'],
        'track'     => ['Track record', 'the public record of settled calls for this coin'],
        'alerts'    => ['Alerts', 'members can watch it for email, push and Telegram alerts'],
        'backtest'  => ['Backtest', 'the coin list in the member backtester'],
        'portfolio' => ['Follow', 'members can open a followed position on it'],
    ];

    /** Per-request cache: this is asked once per coin per page at minimum. */
    private static ?array $map = null;

    /** symbol => allowed timeframes, empty array meaning "whatever the site allows". */
    private static ?array $tfMap = null;

    /**
     * The timeframes this coin may be read on, already intersected with what
     * the site allows - so a coin pinned to 5m on an install that has since
     * turned 5m off does not keep a frame nothing else offers.
     *
     * An empty stored list, or one that intersects to nothing, means the site
     * list: a coin should never end up with no timeframes at all because a
     * setting changed underneath it.
     */
    /**
     * The timeframes the site offers, given every interval it supports. One
     * place, so a caller cannot forget the fallback: an empty or nonsense
     * setting means all of them, never none.
     */
    public static function siteTfs(array $all): array
    {
        $set = array_values(array_intersect($all, array_map('trim',
            explode(',', Database::setting('enabled_intervals', implode(',', $all))))));
        return $set ?: $all;
    }

    /**
     * A WHERE fragment restricting rows to the timeframes the site offers.
     *
     * Switching a timeframe off used to remove it from the scanner and the
     * chart and leave it in the track record, so an operator who dropped 15m
     * still had 15m trades counted in the published win rate and listed under
     * "every verified trade". The setting reads as one decision and behaved as
     * two.
     *
     * History is filtered rather than deleted: turning the frame back on
     * brings its record back with it. Site-level only - a per-coin timeframe
     * list says where the engine should look now, and re-deciding what a
     * finished trade was worth is a different question.
     *
     * Returns '' when every supported frame is on, so the common case adds
     * nothing to the query.
     */
    public static function tfSql(array $all, string $alias = ''): string
    {
        $tfs = self::siteTfs($all);
        if (count($tfs) >= count($all)) {
            return '';
        }
        $col = ($alias !== '' ? $alias . '.' : '') . 'tf';
        // The values come from the config's own interval list, but they are
        // quoted anyway - this is a WHERE clause, not a place to rely on where
        // a string came from.
        $quoted = array_map(static fn(string $t): string => "'" . str_replace("'", "''", $t) . "'", $tfs);
        return ' AND ' . $col . ' IN (' . implode(',', $quoted) . ')';
    }

    public static function tfsFor(string $symbol, array $siteTfs): array
    {
        $own = self::tfMap()[strtoupper($symbol)] ?? [];
        if (!$own) {
            return $siteTfs;
        }
        $ok = array_values(array_intersect($siteTfs, $own));
        return $ok ?: $siteTfs;
    }

    /** Is this coin read on this timeframe? */
    public static function allowsTf(string $symbol, string $tf, array $siteTfs): bool
    {
        return in_array($tf, self::tfsFor($symbol, $siteTfs), true);
    }

    /** The raw stored list, for the admin form - not intersected with anything. */
    public static function ownTfs(string $symbol): array
    {
        return self::tfMap()[strtoupper($symbol)] ?? [];
    }

    /** Replace one coin's timeframe list. An empty list means "site default". */
    public static function setTfs(string $symbol, array $tfs, array $siteTfs): void
    {
        $clean = array_values(array_intersect($siteTfs, $tfs));
        // Storing every allowed frame is the same statement as storing none,
        // and "none" survives the site list changing. Keep the simpler one.
        $value = (count($clean) === count($siteTfs)) ? '' : implode(',', $clean);
        Database::pdo()->prepare('UPDATE symbols SET tfs = ? WHERE symbol = ?')
            ->execute([$value, strtoupper($symbol)]);
        self::$tfMap = null;
    }

    private static function tfMap(): array
    {
        if (self::$tfMap !== null) {
            return self::$tfMap;
        }
        self::$tfMap = [];
        try {
            foreach (Database::pdo()->query('SELECT symbol, tfs FROM symbols') as $r) {
                self::$tfMap[strtoupper((string)$r['symbol'])] =
                    array_values(array_filter(array_map('trim', explode(',', (string)$r['tfs']))));
            }
        } catch (\Throwable $e) {
            self::$tfMap = [];
        }
        return self::$tfMap;
    }

    /** Surfaces this coin is hidden on. */
    public static function hiddenFor(string $symbol): array
    {
        return self::map()[strtoupper($symbol)] ?? [];
    }

    /** Is this coin allowed on this surface? Unknown coins are not. */
    public static function shows(string $symbol, string $surface): bool
    {
        $map = self::map();
        $key = strtoupper($symbol);
        return isset($map[$key]) && !in_array($surface, $map[$key], true);
    }

    /**
     * SQL to append to a query that already joins symbols, so the filter runs
     * in the database rather than over the rows it returns.
     *
     * $alias is the symbols table alias. An unknown surface returns the empty
     * string rather than a filter that silently matches nothing - a typo in a
     * key should not empty a page.
     */
    public static function sql(string $surface, string $alias = 'sy'): string
    {
        if (!isset(self::SURFACES[$surface])) {
            return '';
        }
        return " AND $alias.hide_on NOT LIKE '%,$surface,%'";
    }

    /**
     * The same filter for a query with no symbols join, as a subquery on the
     * symbol column. Carries the master switch too, which is the point: one
     * call and a surface cannot forget either half of it.
     */
    public static function subquery(string $surface, string $column = 'symbol'): string
    {
        $vis = isset(self::SURFACES[$surface])
            ? " AND hide_on NOT LIKE '%,$surface,%'" : '';
        return " AND $column IN (SELECT symbol FROM symbols WHERE enabled = 1$vis)";
    }

    /** Replace one coin's hidden list. Unknown keys are dropped, not stored. */
    public static function set(string $symbol, array $hidden): void
    {
        $clean = array_values(array_intersect(array_keys(self::SURFACES), $hidden));
        $value = $clean ? ',' . implode(',', $clean) . ',' : '';
        Database::pdo()->prepare('UPDATE symbols SET hide_on = ? WHERE symbol = ?')
            ->execute([$value, strtoupper($symbol)]);
        self::$map = null;
    }

    /** Turn one surface on or off for many coins at once. */
    public static function bulk(array $symbols, string $surface, bool $show): int
    {
        if (!isset(self::SURFACES[$surface]) || !$symbols) {
            return 0;
        }
        $n = 0;
        foreach ($symbols as $sym) {
            $hidden = self::hiddenFor($sym);
            $has = in_array($surface, $hidden, true);
            if ($show === $has) {
                $hidden = $show
                    ? array_values(array_diff($hidden, [$surface]))
                    : array_merge($hidden, [$surface]);
                self::set($sym, $hidden);
                $n++;
            }
        }
        return $n;
    }

    /** How many coins are hidden on each surface, for the admin summary. */
    public static function counts(): array
    {
        $out = array_fill_keys(array_keys(self::SURFACES), 0);
        foreach (self::map() as $hidden) {
            foreach ($hidden as $s) {
                if (isset($out[$s])) {
                    $out[$s]++;
                }
            }
        }
        return $out;
    }

    /** symbol => hidden surfaces, for enabled coins only. */
    private static function map(): array
    {
        if (self::$map !== null) {
            return self::$map;
        }
        self::$map = [];
        try {
            foreach (Database::pdo()->query('SELECT symbol, hide_on FROM symbols WHERE enabled = 1') as $r) {
                self::$map[strtoupper((string)$r['symbol'])] =
                    array_values(array_filter(explode(',', (string)$r['hide_on'])));
            }
        } catch (\Throwable $e) {
            // Before the migration adds the column, fall back to the master
            // switch alone - every enabled coin on every surface, which is
            // exactly what the site did before this class existed. Returning
            // an empty map here would hide the entire market instead.
            self::$map = [];
            try {
                foreach (Database::pdo()->query('SELECT symbol FROM symbols WHERE enabled = 1') as $r) {
                    self::$map[strtoupper((string)$r['symbol'])] = [];
                }
            } catch (\Throwable $e2) {
            }
        }
        return self::$map;
    }
}
