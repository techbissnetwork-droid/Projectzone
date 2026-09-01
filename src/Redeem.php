<?php
declare(strict_types=1);

namespace SignalMasterAi;

/**
 * Activation keys - premium bought once and handed over as a code.
 *
 * The site could already take money four ways, and every one of them assumed
 * the buyer and the payer are the same person sitting in front of the
 * checkout. That misses the way most of this product is actually sold: a
 * reseller pays once for fifty subscriptions, a giveaway winner is owed thirty
 * days, a support case has to be made good, an operator sells face to face and
 * needs to hand over something. All of that was done by hand - find the
 * member, edit their tier, guess a date - which is a job only the operator can
 * do, only while they are awake, and only if the buyer already has an account
 * they can name.
 *
 * A key removes the operator from the loop entirely. It is generated in
 * advance, it carries the plan with it, and redeeming it lands in exactly the
 * same place a card payment lands.
 *
 * TWO THINGS ARE DELIBERATE AND SHOULD STAY THAT WAY.
 *
 * First: a redemption is a payment. It creates a real payments row and goes
 * through Payments::approve() like everything else, rather than writing
 * members.paid_until itself. That is not ceremony - approve() is where
 * "extend from the existing expiry, not from now" lives, where the
 * double-credit claim lives, and where the order history a member can read
 * comes from. A second grant path would have started as three lines and ended
 * up as a second, subtly different definition of what buying premium means.
 *
 * Second: the key is stored as it will be typed, not as it is printed. Codes
 * are shown in groups of four with dashes because that is how a human copies
 * one off a card without losing their place, and they are matched with every
 * separator and every space stripped out, because that is not how a human
 * types one back in.
 */
class Redeem
{
    /**
     * No O/0, no I/1, no S/5 - the three pairs that get misread off a printed
     * card or a screenshot. Losing eight symbols costs nothing here: a 16
     * character key over this alphabet is still 32^16 combinations, so
     * guessing one is not a strategy, and the throttle below closes the
     * distance anyway.
     */
    private const ALPHABET = 'ABCDEFGHJKLMNPQRTUVWXYZ2346789';
    private const LENGTH   = 16;

    /** Is the feature switched on at all? */
    public static function enabled(): bool
    {
        return Database::setting('redeem_enabled', '1') === '1';
    }

    /**
     * Should the member-facing form be shown at all?
     *
     * Switched on is not the same as usable. An install that has never
     * generated a key would otherwise put "Have an activation key?" on the
     * checkout page of every visitor, which reads as a discount somebody else
     * got and they did not - the exact feeling that loses a sale. So the form
     * appears when a key exists that could actually be redeemed today, and
     * disappears again when the last one is spent.
     */
    public static function offered(): bool
    {
        if (!self::enabled()) {
            return false;
        }
        // SELECT 1 ... LIMIT 1, not COUNT(*). This runs on every load of the
        // account and upgrade pages, and the question is "is there one?" - an
        // operator who has generated fifty thousand cards should not have all
        // fifty thousand counted to answer it.
        $n = Database::pdo()->query(
            'SELECT 1 FROM redeem_codes
              WHERE enabled = 1 AND used_count < max_uses
                AND (expires_at = 0 OR expires_at > ' . time() . ') LIMIT 1'
        )->fetchColumn();
        return $n !== false;
    }

    /**
     * The form a key is stored and compared in: capitals and digits, nothing
     * else. Whatever the member pastes - dashes, spaces, lowercase, a stray
     * newline off the end of a copied cell - reduces to the same string.
     */
    public static function normalise(string $raw): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $raw) ?? '');
    }

    /** The form a key is printed in: groups of four, dash separated. */
    public static function format(string $code): string
    {
        return implode('-', str_split(self::normalise($code), 4));
    }

    /**
     * Make a batch.
     *
     * Every key in one batch shares its plan, its limits and its label, so a
     * batch is the unit an operator actually thinks in ("the fifty I sold to
     * that reseller") and the unit they can later disable in one go.
     *
     * @param array{plan_id?:int,plan_name?:string,days:int,max_uses?:int,expires_at?:int,note?:string} $opts
     * @return string[] the generated keys, formatted for printing
     */
    public static function generate(int $count, array $opts): array
    {
        $pdo   = Database::pdo();
        $count = max(1, min(500, $count));
        // 0 is a real value here and means a lifetime key - the same thing 0
        // means on a plan and in the grant-by-hand form. Clamping it up to 1
        // would have turned "lifetime" into "one day" without saying so.
        $days  = max(0, min(3650, (int)($opts['days'] ?? 30)));
        $batch = 'B' . gmdate('ymdHis') . strtoupper(bin2hex(random_bytes(2)));

        $ins = $pdo->prepare(
            'INSERT INTO redeem_codes (code, plan_id, plan_name, days, max_uses, expires_at,
                                       note, batch, created_at)
             VALUES (?,?,?,?,?,?,?,?,?)'
        );
        $out = [];
        for ($i = 0; $i < $count; $i++) {
            // Retried rather than assumed unique. The collision odds are
            // negligible and the UNIQUE index would throw rather than
            // duplicate, but an insert that throws halfway through a batch of
            // fifty leaves the operator with a partial batch and no error they
            // can act on.
            for ($try = 0; $try < 8; $try++) {
                $code = '';
                for ($c = 0; $c < self::LENGTH; $c++) {
                    $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
                }
                try {
                    $ins->execute([
                        $code,
                        (int)($opts['plan_id'] ?? 0),
                        mb_substr((string)($opts['plan_name'] ?? 'Activation key'), 0, 64),
                        $days,
                        max(1, min(100000, (int)($opts['max_uses'] ?? 1))),
                        max(0, (int)($opts['expires_at'] ?? 0)),
                        mb_substr((string)($opts['note'] ?? ''), 0, 190),
                        $batch,
                        time(),
                    ]);
                    $out[] = self::format($code);
                    break;
                } catch (\Throwable $e) {
                    // taken - draw another
                }
            }
        }
        return $out;
    }

    /** Look a key up by anything a member might have typed. Null if unknown. */
    public static function find(string $raw): ?array
    {
        $code = self::normalise($raw);
        if ($code === '' || strlen($code) > 32) {
            return null;
        }
        $stmt = Database::pdo()->prepare('SELECT * FROM redeem_codes WHERE code = ?');
        $stmt->execute([$code]);
        $row = $stmt->fetch();
        $stmt->closeCursor();
        return $row ?: null;
    }

    /**
     * Why this key cannot be used, in words the person holding it can act on.
     * Null when it is good.
     *
     * "Expired" and "already used" are told apart on purpose. They are the two
     * outcomes a support message is about, and answering both with one blank
     * "invalid" turns every one of them into a conversation.
     */
    public static function reject(array $row, int $memberId): ?string
    {
        if ((int)$row['enabled'] !== 1) {
            return 'That key has been cancelled. Contact whoever gave it to you.';
        }
        if ((int)$row['expires_at'] > 0 && (int)$row['expires_at'] < time()) {
            return 'That key expired on ' . gmdate('M j, Y', (int)$row['expires_at']) . '.';
        }
        if ((int)$row['used_count'] >= (int)$row['max_uses']) {
            return 'That key has already been used.';
        }
        $stmt = Database::pdo()->prepare(
            'SELECT COUNT(*) FROM redeem_uses WHERE code_id = ? AND member_id = ?');
        $stmt->execute([(int)$row['id'], $memberId]);
        if ((int)$stmt->fetchColumn() > 0) {
            // A multi-use key exists to cover several people, not to let one
            // person hold premium open forever off a single code.
            return 'You have already redeemed that key on this account.';
        }
        return null;
    }

    /**
     * Redeem a key for a member.
     *
     * Returns [ok, message, days]. The message is shown to the member exactly
     * as it comes back, so it never names anything they cannot see - no code
     * ids, no batch labels, no counts.
     */
    public static function apply(string $raw, int $memberId): array
    {
        if (!self::enabled()) {
            return [false, 'Activation keys are not accepted on this site.', 0];
        }
        if ($memberId <= 0) {
            return [false, 'Sign in first, then redeem your key.', 0];
        }
        // Guessing throttle. The key space makes brute force hopeless on
        // paper; this makes it hopeless in practice, and it is counted
        // server-side per account AND per address so neither dropping a cookie
        // nor switching accounts resets it.
        //
        // FAILURES ONLY. Counting successes as well meant a customer handed a
        // stack of twelve single-month cards - the exact thing a reseller
        // sells - was locked out on the eleventh, having done nothing wrong.
        // A redemption that worked is not a guess.
        $ip = substr(sha1((string)($_SERVER['REMOTE_ADDR'] ?? '')), 0, 16);
        if ((int)(Cache::get('rdm:m' . $memberId) ?? 0) >= 10
            || (int)(Cache::get('rdm:i' . $ip) ?? 0) >= 20) {
            return [false, 'Too many attempts. Try again in an hour.', 0];
        }
        $fail = static function (string $msg) use ($memberId, $ip): array {
            Cache::increment('rdm:m' . $memberId, 3600);
            Cache::increment('rdm:i' . $ip, 3600);
            return [false, $msg, 0];
        };

        // A KEY SPENT ON AN ACCOUNT THAT CANNOT GAIN FROM IT IS A KEY BURNT.
        //
        // paid_until = 0 on a paid account is lifetime access. Applying a key
        // to it consumes the key and uses up its seat, and approve() correctly
        // refuses to turn "no end date" into a date - so the member ends up
        // exactly where they started, one key poorer. The forms that offer
        // this are hidden from a lifetime account, but hiding a form is not a
        // rule; this is. Not counted as a failed guess: nothing was guessed.
        $me = Database::pdo()->prepare('SELECT tier, paid_until FROM members WHERE id = ?');
        $me->execute([$memberId]);
        $mine = $me->fetch();
        $me->closeCursor();
        if ($mine && (string)$mine['tier'] === 'paid' && (int)$mine['paid_until'] === 0) {
            return [false, 'This account already has lifetime premium, so a key would add nothing. '
                         . 'Keep it for another account.', 0];
        }

        $row = self::find($raw);
        if (!$row) {
            return $fail('That key was not recognised - check it and try again.');
        }
        if (($why = self::reject($row, $memberId)) !== null) {
            return $fail($why);
        }

        $pdo = Database::pdo();
        // ATOMIC CLAIM, and it has to be this shape.
        //
        // The check above can pass in two requests at once - a double-tapped
        // button, two tabs, a reseller's fifty customers hitting a shared key
        // in the same second. Only the UPDATE decides: it re-tests the whole
        // condition in the database, and rowCount() tells this caller whether
        // it was the one that took the seat.
        $claim = $pdo->prepare(
            'UPDATE redeem_codes SET used_count = used_count + 1
              WHERE id = ? AND enabled = 1 AND used_count < max_uses
                AND (expires_at = 0 OR expires_at > ?)'
        );
        $claim->execute([(int)$row['id'], time()]);
        if ($claim->rowCount() === 0) {
            return [false, 'That key has just been used up.', 0];
        }

        $days = (int)$row['days'];
        // CLAIM THE MEMBER'S SEAT BEFORE GRANTING ANYTHING.
        //
        // reject() above already asked "has this account used this key?", and
        // that question cannot be trusted on its own: it is a read, and the
        // write that would make it false comes later. Two requests from two
        // sessions of one account both read "no" and both went on to collect.
        //
        // So the row goes in first, and the UNIQUE index on
        // (code_id, member_id) is what actually decides. If it throws, this
        // request lost the race - hand the seat back and say the same thing
        // reject() would have said.
        try {
            $pdo->prepare('INSERT INTO redeem_uses (code_id, member_id, payment_id, created_at)
                           VALUES (?,?,0,?)')
                ->execute([(int)$row['id'], $memberId, time()]);
        } catch (\Throwable $e) {
            $pdo->prepare('UPDATE redeem_codes SET used_count = used_count - 1 WHERE id = ? AND used_count > 0')
                ->execute([(int)$row['id']]);
            return [false, 'You have already redeemed that key on this account.', 0];
        }
        $useId = (int)$pdo->lastInsertId();

        try {
            // A redemption is a payment - see the note at the top of the file.
            // Zero dollars, named for what it was, and visible in the member's
            // own order history alongside anything they actually paid for.
            $pdo->prepare(
                "INSERT INTO payments (member_id, plan_id, plan_name, plan_days, method_name, kind,
                                       amount_usd, currency, status, note, created_at, updated_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?)"
            )->execute([
                $memberId, (int)$row['plan_id'], (string)$row['plan_name'], $days,
                'Activation key', 'redeem', 0.0, '', 'pending',
                'Key ' . self::format((string)$row['code']), time(), time(),
            ]);
            $paymentId = (int)$pdo->lastInsertId();
            Payments::approve($paymentId);

            // Which payment this redemption became. Written after the fact
            // because the seat had to be claimed before the grant existed.
            $pdo->prepare('UPDATE redeem_uses SET payment_id = ? WHERE id = ?')
                ->execute([$paymentId, $useId]);
        } catch (\Throwable $e) {
            // Hand the seat back, and the member's claim on it with it. A key
            // that was charged but never credited is the one failure here
            // nobody can recover from without the operator, which is the whole
            // thing this feature exists to avoid.
            $pdo->prepare('DELETE FROM redeem_uses WHERE id = ?')->execute([$useId]);
            $pdo->prepare('UPDATE redeem_codes SET used_count = used_count - 1 WHERE id = ? AND used_count > 0')
                ->execute([(int)$row['id']]);
            ErrorLog::record(ErrorLog::PAYMENT, 'Key redemption failed: ' . $e->getMessage(),
                'member ' . $memberId);
            return [false, 'Something went wrong activating that key - nothing was used up. Try again.', 0];
        }

        return [true, $days > 0
            ? 'Key accepted - ' . Payments::planLength($days) . ' of premium added to your account.'
            : 'Key accepted - premium is now yours for good, with no expiry date.', $days];
    }

    /** Batch labels newest first, for the admin filter. */
    public static function batches(int $limit = 60): array
    {
        return Database::pdo()->query(
            // note comes back too: the operator types a label ("Reseller A")
            // when generating and had no way to see it again afterwards, which
            // made the field pure decoration and the batch list a wall of
            // identical timestamps.
            // unused = keys nobody has touched, which is the only number the
            // batch actions can act on: cancelling and deleting deliberately
            // leave a redeemed key alone, so a batch with none of these has
            // nothing for those buttons to do.
            'SELECT batch, COUNT(*) n, MIN(created_at) created_at, MAX(plan_name) plan_name,
                    MAX(note) note, MAX(days) days, SUM(used_count) used,
                    SUM(CASE WHEN used_count = 0 THEN 1 ELSE 0 END) unused
               FROM redeem_codes GROUP BY batch
              ORDER BY MIN(created_at) DESC LIMIT ' . max(1, min(500, $limit))
        )->fetchAll();
    }
}
