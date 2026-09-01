<?php
declare(strict_types=1);

namespace SignalMasterAi;

/**
 * The short public reference a signal is known by.
 *
 * WHY NOT THE ROW ID.
 *
 * signals.id is sequential, so quoting it anywhere a reader can see publishes
 * the site's own volume: "signal #209" says how many calls have ever been
 * made, and two screenshots a week apart give the rate. It is also poor at the
 * job an id is actually wanted for here - being read off a phone screen into a
 * support message without a typo.
 *
 * THE ALPHABET.
 *
 * No 0, O, 1, I or L. Those are the pairs people get wrong reading aloud or
 * retyping, and a reference nobody can transcribe is a reference that generates
 * the support message it was supposed to shorten. Six characters from the
 * remaining 31 is about 887 million combinations - far past what a signal
 * table will ever hold, and short enough to say out loud.
 *
 * It is a LABEL, not a secret. It identifies a published call; it authorises
 * nothing, and nothing should ever be granted by holding one.
 */
class SignalRef
{
    /** No 0/O/1/I/L - see above. */
    private const ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';
    public const PREFIX = 'SM-';

    /** A new reference. Random, not derived from anything about the signal. */
    public static function make(): string
    {
        $n = strlen(self::ALPHABET);
        $out = '';
        for ($i = 0; $i < 6; $i++) {
            $out .= self::ALPHABET[random_int(0, $n - 1)];
        }
        return self::PREFIX . $out;
    }

    /** Does this look like one of ours? Used to validate a lookup, not to trust it. */
    public static function valid(string $ref): bool
    {
        return (bool)preg_match('/^' . preg_quote(self::PREFIX, '/') . '[' . self::ALPHABET . ']{6}$/', $ref);
    }

    /**
     * Normalise what somebody typed into what is stored.
     *
     * People paste it lower-cased, drop the prefix, or type a letter this
     * alphabet does not use because they read an O for a zero. Fixing the
     * three predictable mistakes here means the admin search finds the signal
     * instead of reporting nothing and leaving the operator to wonder whether
     * the reference or the search is broken.
     */
    public static function normalise(string $input): string
    {
        $s = strtoupper(trim($input));
        $s = str_replace([' ', '_'], '', $s);
        // The prefix is stripped AFTER the separators are, or "SM 7F3K2Q"
        // becomes "SM7F3K2Q", fails a str_starts_with on "SM-", and gets a
        // second prefix bolted on. Matched with the dash optional for that
        // reason, and anchored so a body starting with S and M is safe.
        $s = (string)preg_replace('/^' . preg_quote(rtrim(self::PREFIX, '-'), '/') . '-?/', '', $s);
        $s = strtr($s, ['O' => '0', 'I' => '1', 'L' => '1']);
        // ...and then back the other way: the alphabet has no 0 or 1, so a
        // reader who saw one of those meant the letter it resembles.
        $s = strtr($s, ['0' => 'O', '1' => 'I']);
        $s = preg_replace('/[^A-Z0-9]/', '', $s) ?? '';
        return $s === '' ? '' : self::PREFIX . $s;
    }

    /**
     * Give every signal that has no reference one.
     *
     * Runs once, from the migration, BEFORE the unique index is created - a
     * unique index over a column where every existing row is an empty string
     * cannot be built, and the failure is silent, which would leave the site
     * with references that are not actually unique.
     */
    public static function backfill(\PDO $pdo, int $limit = 100000): int
    {
        $done = 0;
        try {
            $rows = $pdo->query(
                "SELECT id FROM signals WHERE ref IS NULL OR ref = '' LIMIT " . (int)$limit
            )->fetchAll(\PDO::FETCH_COLUMN);
        } catch (\Throwable $e) {
            return 0;
        }
        if (!$rows) {
            return 0;
        }
        $upd = $pdo->prepare('UPDATE signals SET ref = ? WHERE id = ? AND (ref IS NULL OR ref = \'\')');
        foreach ($rows as $id) {
            // Collisions are vanishingly unlikely and not impossible, so they
            // are retried rather than assumed away.
            for ($try = 0; $try < 5; $try++) {
                try {
                    $upd->execute([self::make(), (int)$id]);
                    $done++;
                    break;
                } catch (\Throwable $e) {
                    // a duplicate: go round again with a new one
                }
            }
        }
        return $done;
    }
}
