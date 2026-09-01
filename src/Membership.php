<?php
declare(strict_types=1);

namespace SignalMasterAi;

/**
 * What happens when a plan ends.
 *
 * Access was already correct: every read of a member's tier treats a past
 * paid_until as free, so an expired member could not open a premium coin, and
 * the alert dispatchers checked each pair against the tier before sending. In
 * that narrow sense nothing leaked.
 *
 * Everything else about the expiry was left undone, and it was left undone
 * FOREVER, because nothing anywhere looked for a plan that had run out:
 *
 *   - members.tier stayed 'paid'. Only the readers compensated, so the
 *     database itself said the wrong thing, and anything that trusted the
 *     column rather than the helper - an export, a count, an admin filter -
 *     got the wrong answer for as long as the row existed.
 *
 *   - The watchlist kept its premium pairs. The member saw coins they could
 *     no longer open, sitting in a list whose cap had just dropped from
 *     unlimited to whatever free gets - so their list was over the limit and
 *     nothing would trim it until they next edited it by hand.
 *
 *   - Cron went on scanning those pairs. EmailAlerts::watchedPairs() collects
 *     every watched pair from every member with no idea who is entitled to
 *     what, so an expired member's premium coins stayed in the rotation,
 *     spending scan budget on analyses that could never become an alert for
 *     the only person watching them.
 *
 * So the sweep is here, it runs from cron, and it also runs the moment an
 * operator changes somebody's tier by hand - an expiry and a downgrade are the
 * same event and must not have two different outcomes.
 */
class Membership
{
    /**
     * Flip every plan that has run out, and tidy up after each one.
     *
     * Returns [expired, pairsRemoved] so cron can report it rather than doing
     * something invisible to the operator.
     *
     * @return array{expired:int,pairs:int}
     */
    public static function expireDue(int $limit = 200): array
    {
        $pdo = Database::pdo();
        // paid_until = 0 on a paid account means "no end date" - a lifetime
        // grant - and must never be read as "expired at the epoch".
        $due = $pdo->query(
            "SELECT id FROM members
              WHERE tier = 'paid' AND paid_until > 0 AND paid_until < " . time() . "
              ORDER BY paid_until LIMIT " . max(1, min(1000, $limit))
        )->fetchAll(\PDO::FETCH_COLUMN);

        $pairs = 0;
        foreach ($due as $id) {
            $pdo->prepare("UPDATE members SET tier = 'free' WHERE id = ?")->execute([(int)$id]);
            $pairs += self::prune((int)$id, 'free');
        }
        return ['expired' => count($due), 'pairs' => $pairs];
    }

    /**
     * Bring one member's watchlists back inside what their tier allows.
     *
     * Two separate cuts, and they are different rules:
     *   - a coin the tier cannot access is removed outright, however short the
     *     list is. Keeping it would show somebody a coin they cannot open.
     *   - what survives is then trimmed to the tier's cap, oldest-listed
     *     first, because the cap is a count and the order in the list is the
     *     only ordering anybody has expressed.
     *
     * Returns how many pairs were dropped.
     */
    public static function prune(int $memberId, ?string $tier = null): int
    {
        $pdo = Database::pdo();
        if ($tier === null) {
            $q = $pdo->prepare('SELECT tier, paid_until FROM members WHERE id = ?');
            $q->execute([$memberId]);
            $m = $q->fetch() ?: [];
            $tier = ($m['tier'] ?? 'free') === 'paid'
                    && ((int)($m['paid_until'] ?? 0) === 0 || (int)$m['paid_until'] >= time())
                ? 'paid' : 'free';
        }
        $cap = Limits::of('watch', $tier);
        // Which coins this tier may be alerted about at all. Read once for the
        // whole member rather than once per pair: a long watchlist is the
        // normal case and this is called for every expiring account.
        $allowed = [];
        $rows = $pdo->query('SELECT symbol, tier FROM symbols WHERE enabled = 1'
                            . Visibility::sql('alerts', 'symbols'))->fetchAll();
        foreach ($rows as $r) {
            if (MemberAuth::canAccess((string)$r['tier'], $tier)) {
                $allowed[(string)$r['symbol']] = true;
            }
        }

        $removed = 0;
        // The two tables key differently - member_alerts is one row per member
        // with no id of its own, watchlists is many rows with one - so each
        // says which column identifies the row to write back to.
        foreach ([['member_alerts', 'member_id'], ['watchlists', 'id']] as [$table, $key]) {
            $sel = $pdo->prepare("SELECT $key AS rowkey, pairs FROM $table WHERE member_id = ?");
            $sel->execute([$memberId]);
            $upd = $pdo->prepare("UPDATE $table SET pairs = ? WHERE $key = ?");
            foreach ($sel->fetchAll() as $row) {
                $have = array_values(array_filter(array_map('trim',
                    explode(',', (string)$row['pairs']))));
                $keep = [];
                foreach ($have as $pair) {
                    $sym = strtok($pair, ':');
                    if ($sym !== false && isset($allowed[$sym])) {
                        $keep[] = $pair;
                    }
                }
                if ($cap > 0 && count($keep) > $cap) {
                    $keep = array_slice($keep, 0, $cap);
                }
                if (count($keep) !== count($have)) {
                    $removed += count($have) - count($keep);
                    $upd->execute([implode(',', $keep), (int)$row['rowkey']]);
                }
            }
        }
        return $removed;
    }

    /**
     * Everything a member owns, in the order it has to go.
     *
     * Ten tables carry a member_id. There are no foreign keys and no
     * ON DELETE CASCADE - the schema is built to run on both SQLite and
     * MySQL and never declared them - so nothing follows a members row out
     * of the database on its own.
     */
    public const OWNED_TABLES = [
        'push_subs', 'alert_queue', 'member_alerts', 'watchlists',
        'paper_trades', 'member_prefs', 'redeem_uses',
        'email_log', 'payments',
    ];

    /**
     * Erase a member and everything of theirs.
     *
     * DELETING THE ROW WAS NOT DELETING THE MEMBER.
     *
     * The admin delete ran one statement - DELETE FROM members - and ten
     * tables kept their rows. Measured on a test account seeded with one row
     * in each: all ten survived the delete. That is not only untidy, it keeps
     * working:
     *
     *   - WebPush::send() reads push_subs with no join to members, so a
     *     deleted member's browser goes on receiving notifications for as
     *     long as the endpoint lives.
     *   - WebPush::watchedPairs() collects pairs from every push_subs row, so
     *     a deleted member's coins keep spending cron's scan budget on
     *     analyses nobody will ever read.
     *   - alert_queue keeps their pending digest, and email_log keeps their
     *     address.
     *
     * So "delete this member" left a person still being emailed, still being
     * pushed to, and still costing scan time - and left their email address
     * in the database after the one action that is supposed to remove it.
     *
     * Payments last and deliberately, because they are the one thing here
     * with a life of its own: a settled payment is an accounting record, and
     * an operator may need it after the account is gone. Whether it goes is
     * the caller's decision, not this function's assumption.
     *
     * @return array<string,int> rows removed per table, for a report that can
     *                           be checked rather than trusted
     */
    public static function erase(int $memberId, bool $keepPayments = true): array
    {
        $out = [];
        if ($memberId <= 0) {
            return $out;
        }
        $pdo = Database::pdo();
        foreach (self::OWNED_TABLES as $table) {
            if ($table === 'payments' && $keepPayments) {
                continue;
            }
            try {
                $st = $pdo->prepare("DELETE FROM $table WHERE member_id = ?");
                $st->execute([$memberId]);
                if ($st->rowCount() > 0) {
                    $out[$table] = $st->rowCount();
                }
            } catch (\Throwable $e) {
                // A table that does not exist on an older install must not
                // stop the erase - the point is to leave as little behind as
                // possible, and stopping halfway leaves more.
                $out[$table] = -1;
            }
        }
        // The member row goes last: while it exists, a half-finished erase is
        // still findable and can be run again. Delete it first and the
        // leftovers become orphans nobody can search for.
        $st = $pdo->prepare('DELETE FROM members WHERE id = ?');
        $st->execute([$memberId]);
        $out['members'] = $st->rowCount();
        return $out;
    }

    /** "3 paper trades, 1 subscription" - the erase report, for a human. */
    public static function describeErase(array $counts): string
    {
        $bits = [];
        foreach ($counts as $table => $n) {
            if ($table === 'members' || $n === 0) {
                continue;
            }
            $bits[] = ($n < 0 ? '?' : (string)$n) . ' ' . str_replace('_', ' ', $table);
        }
        return $bits ? implode(', ', $bits) : 'nothing else was stored';
    }
}
