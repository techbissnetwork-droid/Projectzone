<?php
declare(strict_types=1);

namespace SignalMasterAi;

/**
 * What the engine has learned about each coin.
 *
 * The evidence and the conclusion are different things and are now stored
 * apart. `signals` is the evidence: what was published and how it resolved,
 * and it is the record the public track record is built from. This is the
 * conclusion the tuner draws from that evidence - a multiplier per (coin,
 * rule) saying how much to trust a rule on that coin specifically. Deleting
 * one has never implied deleting the other, and until this table existed the
 * operator could not act on that: the conclusions lived in a JSON blob in the
 * settings row, where they could only be cleared all at once or not at all.
 *
 * WHY A TABLE AND NOT A SECOND DATABASE. A separate connection would mean two
 * sets of credentials, two backups, two things to restore in the right order,
 * and no transaction spanning them - on hosting whose whole premise is that
 * there is one archive to upload. The separation that was actually wanted is
 * lifecycle separation: clear the learning without touching the signals, clear
 * the signals without touching the learning, and see how big each is. A table
 * gives that. A second database would give it too, and charge for it.
 *
 * WHAT THE BLOB WAS COSTING, concretely: the writer had a 48,000-byte budget
 * and enforced it by POPPING COINS OFF THE END until the JSON fit. On an
 * install past that size the least-evidenced coins were silently un-learned on
 * every tuning run, and the only trace was a "dropped" count in a report
 * nobody had a reason to open. Rows have no such ceiling.
 */
final class SymbolLearning
{
    /** Read once per request - the engine asks for this on every symbol it scans. */
    private static ?array $cache = null;

    /**
     * Every learned multiplier, as symbol => rule_key => mult.
     *
     * Same shape the JSON blob had, so the engine and the admin page read it
     * the way they always did. One query, because the engine scans hundreds of
     * pairs per cron run and a query per pair would be a query per pair.
     *
     * @return array<string, array<string, float>>
     */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $out = [];
        try {
            foreach (Database::pdo()->query(
                'SELECT symbol, rule_key, mult FROM symbol_learning'
            ) as $r) {
                $out[(string)$r['symbol']][(string)$r['rule_key']] = (float)$r['mult'];
            }
        } catch (\Throwable $e) {
            // An install that has not migrated yet reads as "nothing learned",
            // which is the same thing the engine does with an empty blob.
            $out = [];
        }
        return self::$cache = $out;
    }

    /** What one coin has learned. */
    public static function forSymbol(string $symbol): array
    {
        return self::all()[strtoupper(trim($symbol))] ?? [];
    }

    /**
     * Replace the whole table with a freshly computed set.
     *
     * The tuner recomputes every coin it has evidence for in one pass, and a
     * coin that has dropped below the sample floor should stop being adjusted
     * rather than keep its last multiplier for ever. So this is a replace, in
     * a transaction: a half-written table would have the engine trading on
     * some coins' new conclusions and other coins' old ones.
     *
     * @param  array<string, array<string, float>> $bySymbol
     * @return array{coins:int,mults:int}
     */
    public static function replaceAll(array $bySymbol): array
    {
        $pdo = Database::pdo();
        $now = time();
        $coins = 0;
        $mults = 0;
        $own = !$pdo->inTransaction();
        if ($own) {
            $pdo->beginTransaction();
        }
        try {
            $pdo->exec('DELETE FROM symbol_learning');
            $ins = $pdo->prepare(
                'INSERT INTO symbol_learning (symbol, rule_key, mult, samples, updated_at)
                 VALUES (?, ?, ?, ?, ?)'
            );
            foreach ($bySymbol as $sym => $rules) {
                if (!is_array($rules) || !$rules) {
                    continue;
                }
                $coins++;
                foreach ($rules as $key => $mult) {
                    $ins->execute([
                        strtoupper((string)$sym), (string)$key, (float)$mult,
                        0, $now,
                    ]);
                    $mults++;
                }
            }
            if ($own) {
                $pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($own && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        self::$cache = null;
        return ['coins' => $coins, 'mults' => $mults];
    }

    /** Forget one coin. Returns how many learned rules went. */
    public static function forget(string $symbol): int
    {
        $sym = strtoupper(trim($symbol));
        if ($sym === '') {
            return 0;
        }
        try {
            $st = Database::pdo()->prepare('DELETE FROM symbol_learning WHERE symbol = ?');
            $st->execute([$sym]);
            self::$cache = null;
            return $st->rowCount();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /** Forget everything. Returns how many learned rules went. */
    public static function forgetAll(): int
    {
        try {
            $n = (int)Database::pdo()->query('SELECT COUNT(*) FROM symbol_learning')->fetchColumn();
            Database::pdo()->exec('DELETE FROM symbol_learning');
            self::$cache = null;
            return $n;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /** How much there is, for the panel that offers to delete it. */
    public static function size(): array
    {
        try {
            $r = Database::pdo()->query(
                'SELECT COUNT(*) AS mults, COUNT(DISTINCT symbol) AS coins FROM symbol_learning'
            )->fetch();
            return ['coins' => (int)($r['coins'] ?? 0), 'mults' => (int)($r['mults'] ?? 0)];
        } catch (\Throwable $e) {
            return ['coins' => 0, 'mults' => 0];
        }
    }

    /**
     * Move the old JSON blob into the table, once.
     *
     * Called from the migration pass. The setting is blanked afterwards rather
     * than left as a second copy: two places holding the same conclusions is
     * exactly the arrangement this table exists to end, and a stale blob would
     * be read by any code that had not been updated - silently, and with the
     * wrong answer.
     */
    public static function migrateFromSetting(): int
    {
        $blob = Database::setting('sym_rule_mult', '');
        if ($blob === '' || $blob === '{}') {
            return 0;
        }
        $mult = json_decode($blob, true);
        if (!is_array($mult) || !$mult) {
            return 0;
        }
        $rep = self::replaceAll($mult);
        Database::setSetting('sym_rule_mult', '{}');
        return $rep['mults'];
    }
}
