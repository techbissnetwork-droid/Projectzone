<?php
declare(strict_types=1);

namespace SignalMasterAi;

/**
 * Server-side, named watchlists.
 *
 * The watched-pair list lived in browser localStorage. The server already
 * stored the same pairs - `member_alerts.pairs` for email and
 * `push_subs.pairs` for Web Push - but nothing ever read them back, so a
 * member who logged in on their phone found an empty watchlist and had to
 * rebuild it, while the server quietly kept mailing them about the pairs they
 * could no longer see.
 *
 * Lists are per member, named, and one is marked active. Guests keep using
 * localStorage, which is the correct behaviour for someone with no account.
 */
class Watchlists
{
    public const MAX_LISTS = 12;

    /** All lists for a member, active one first. Creates a default if empty. */
    public static function all(int $memberId): array
    {
        if ($memberId <= 0) {
            return [];
        }
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM watchlists WHERE member_id = ? ORDER BY is_active DESC, id ASC'
        );
        $stmt->execute([$memberId]);
        $rows = $stmt->fetchAll();
        if (!$rows) {
            self::create($memberId, 'My watchlist', []);
            $stmt->execute([$memberId]);
            $rows = $stmt->fetchAll();
        }
        return array_map(static fn($r) => [
            'id'     => (int)$r['id'],
            'name'   => (string)$r['name'],
            'active' => (bool)$r['is_active'],
            'pairs'  => array_values(array_filter(explode(',', (string)$r['pairs']))),
        ], $rows);
    }

    public static function active(int $memberId): array
    {
        $all = self::all($memberId);
        foreach ($all as $l) {
            if ($l['active']) {
                return $l;
            }
        }
        return $all[0] ?? ['id' => 0, 'name' => 'My watchlist', 'active' => true, 'pairs' => []];
    }

    public static function create(int $memberId, string $name, array $pairs): int
    {
        if ($memberId <= 0) {
            return 0;
        }
        $pdo = Database::pdo();
        $count = (int)$pdo->query('SELECT COUNT(*) FROM watchlists WHERE member_id = ' . (int)$memberId)->fetchColumn();
        if ($count >= self::MAX_LISTS) {
            return 0;
        }
        $name = self::cleanName($name);
        $isActive = $count === 0 ? 1 : 0;
        $pdo->prepare('INSERT INTO watchlists (member_id, name, pairs, is_active, updated_at) VALUES (?,?,?,?,?)')
            ->execute([$memberId, $name, implode(',', self::cleanPairs($pairs)), $isActive, time()]);
        return (int)$pdo->lastInsertId();
    }

    public static function rename(int $memberId, int $listId, string $name): void
    {
        Database::pdo()->prepare('UPDATE watchlists SET name = ?, updated_at = ? WHERE id = ? AND member_id = ?')
            ->execute([self::cleanName($name), time(), $listId, $memberId]);
    }

    public static function setPairs(int $memberId, int $listId, array $pairs): array
    {
        $clean = self::cleanPairs($pairs);
        Database::pdo()->prepare('UPDATE watchlists SET pairs = ?, updated_at = ? WHERE id = ? AND member_id = ?')
            ->execute([implode(',', $clean), time(), $listId, $memberId]);
        self::syncAlertTargets($memberId);
        return $clean;
    }

    public static function setActive(int $memberId, int $listId): void
    {
        $pdo = Database::pdo();
        $pdo->prepare('UPDATE watchlists SET is_active = 0 WHERE member_id = ?')->execute([$memberId]);
        $pdo->prepare('UPDATE watchlists SET is_active = 1 WHERE id = ? AND member_id = ?')
            ->execute([$listId, $memberId]);
        self::syncAlertTargets($memberId);
    }

    public static function delete(int $memberId, int $listId): void
    {
        $pdo = Database::pdo();
        $pdo->prepare('DELETE FROM watchlists WHERE id = ? AND member_id = ?')->execute([$listId, $memberId]);
        // Never leave a member with no active list.
        $left = self::all($memberId);
        if ($left && !array_filter($left, fn($l) => $l['active'])) {
            self::setActive($memberId, $left[0]['id']);
        }
        self::syncAlertTargets($memberId);
    }

    /**
     * Push the active list into the delivery tables, so email and Web Push
     * always target exactly what the member sees on screen. This is the join
     * that was missing: three copies of "watched pairs" existed and drifted
     * apart from each other.
     */
    public static function syncAlertTargets(int $memberId): void
    {
        if ($memberId <= 0) {
            return;
        }
        $pairs = implode(',', self::active($memberId)['pairs']);
        $pdo = Database::pdo();
        $upd = $pdo->prepare('UPDATE member_alerts SET pairs = ? WHERE member_id = ?');
        $upd->execute([$pairs, $memberId]);
        if ($upd->rowCount() === 0) {
            $pdo->prepare('INSERT INTO member_alerts (member_id, enabled, pairs, last_sent) VALUES (?,?,?,?)')
                ->execute([$memberId, 0, $pairs, '{}']);
        }
        $pdo->prepare('UPDATE push_subs SET pairs = ? WHERE member_id = ?')->execute([$pairs, $memberId]);
    }

    /**
     * Adopt a browser's localStorage list on first login, so the migration
     * from device-local storage costs the member nothing.
     */
    public static function importLocal(int $memberId, array $pairs): array
    {
        $list = self::active($memberId);
        if ($list['pairs']) {
            return $list['pairs'];   // server already has data; do not clobber it
        }
        $merged = self::cleanPairs($pairs);
        if ($merged && $list['id'] > 0) {
            self::setPairs($memberId, $list['id'], $merged);
        }
        return $merged;
    }

    private static function cleanName(string $name): string
    {
        $name = trim(preg_replace('/\s+/', ' ', $name) ?? '');
        $name = mb_substr($name, 0, 40);
        return $name !== '' ? $name : 'Watchlist';
    }

    /**
     * How many watched pairs a viewer of this tier may hold.
     *
     * Three audiences, three settings, one definition. The cap used to be a
     * literal 12 written into api.php for anyone below the bulk-watch tier,
     * and a separate read of bulk_watch_max here - so the number an operator
     * set and the number the watchlist enforced were two different things,
     * and neither of them was the one a free member actually hit.
     *
     * PAIRS, not coins. "Watch all my coins" multiplies the coin list by every
     * ticked timeframe, so a cap of 60 with three frames ticked is twenty
     * coins - which is what an operator setting 60 will not expect. The admin
     * field says pairs and shows the arithmetic.
     *
     * 0 is "no limit": not literally unbounded, since the list still has to
     * fit in a request and a TEXT column, but far past any real watchlist, so
     * the number the operator sees is the number that applies.
     *
     * @param string|null $tier guest|free|paid, or null for the current viewer
     */
    public static function cap(?string $tier = null): int
    {
        // One definition of every tiered number - see Limits. This carried its
        // own copy of the three setting names and its own "0 means unlimited"
        // rule, which is exactly how the watchlist cap and the limits table
        // would have drifted apart the first time either was touched.
        $max = Limits::of('watch', $tier);
        return $max <= 0 ? 20000 : max(1, $max);
    }

    /** Validate SYMBOL:TF entries against the enabled watchlist and intervals. */
    private static function cleanPairs(array $pairs): array
    {
        $config = require SMA_ROOT . '/config.php';
        $intervals = $config['market']['intervals'];
        $stmt = Database::pdo()->prepare('SELECT COUNT(*) FROM symbols WHERE symbol = ? AND enabled = 1'
                                       . Visibility::sql('alerts', 'symbols'));
        // The same limit the alert endpoints use, instead of a second
        // hard-coded one. A watchlist truncated at 200 while the setting said
        // otherwise would have been the next wall an operator hit after
        // raising the first, with nothing to explain it.
        $cap = self::cap();
        $out = [];
        foreach (array_slice($pairs, 0, $cap) as $pair) {
            if (!is_string($pair)) {
                continue;
            }
            [$s, $tf] = array_pad(explode(':', $pair, 2), 2, '');
            $s = strtoupper((string)preg_replace('/[^A-Z0-9]/i', '', $s));
            if ($s === '' || !in_array($tf, $intervals, true)) {
                continue;
            }
            $stmt->execute([$s]);
            if ((int)$stmt->fetchColumn() > 0 && !in_array("$s:$tf", $out, true)) {
                $out[] = "$s:$tf";
            }
        }
        return $out;
    }

    /**
     * Remove a coin from every list that holds it, everywhere.
     *
     * Deleting a coin took it off the site but left it in people's lists,
     * because the pairs are stored as a CSV string rather than as rows with a
     * foreign key. The consequences were quiet and permanent: the member kept
     * a dead coin in their watchlist that could never fire, and the alert
     * sweep logged "a watched pair is not an enabled coin" for it on every
     * single cron run, for ever - an error nobody could clear, about a coin
     * that no longer existed.
     *
     * Every store of pairs is covered, because missing one leaves exactly the
     * same ghost: named watchlists, the email/Telegram subscription row, and
     * browser push subscriptions. Anything already queued for delivery goes
     * too - a bundled alert about a coin the operator has just removed should
     * not arrive ten minutes later.
     *
     * Returns the number of pair entries removed.
     */
    public static function purgeSymbol(string $symbol): int
    {
        $sym = strtoupper(trim($symbol));
        if ($sym === '') {
            return 0;
        }
        $pdo = Database::pdo();
        $removed = 0;

        // Rewritten in PHP rather than with a SQL string function: the value
        // is a CSV of "SYMBOL:tf" and a LIKE-and-REPLACE would also match
        // BTCUSDT inside BTCUSDTUP, and would leave stray commas behind.
        foreach ([['watchlists', 'id'], ['member_alerts', 'member_id'], ['push_subs', 'id']] as [$table, $key]) {
            try {
                $rows = $pdo->query("SELECT $key AS k, pairs FROM $table")->fetchAll();
            } catch (\Throwable $e) {
                continue;               // table not present on this install
            }
            $upd = $pdo->prepare("UPDATE $table SET pairs = ? WHERE $key = ?");
            foreach ($rows as $r) {
                $pairs = array_filter(explode(',', (string)$r['pairs']));
                $keep = array_values(array_filter($pairs, static function ($p) use ($sym) {
                    [$s] = array_pad(explode(':', (string)$p, 2), 2, '');
                    return strtoupper($s) !== $sym;
                }));
                if (count($keep) !== count($pairs)) {
                    $removed += count($pairs) - count($keep);
                    $upd->execute([implode(',', $keep), $r['k']]);
                }
            }
        }

        try {
            $pdo->prepare('DELETE FROM alert_queue WHERE symbol = ?')->execute([$sym]);
        } catch (\Throwable $e) {
            // queue table not present - nothing held, nothing to clear
        }
        return $removed;
    }
}
