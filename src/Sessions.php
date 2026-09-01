<?php
declare(strict_types=1);

namespace SignalMasterAi;

/**
 * Trading sessions, in one place.
 *
 * "Which hours does this engine actually make money in" was already measured -
 * Outcomes::timeBuckets() has kept expectancy per hour of day for a long time,
 * and the engine's optional session filter reads it. What was missing is the
 * only form of that answer anybody asks for out loud. Nobody says "avoid the
 * 03:00 UTC hour"; they say "it does better in the Asian session", and a
 * twenty-four row table of UTC hours does not answer that question so much as
 * hand over the raw material for it.
 *
 * WHY THESE FIVE, AND WHY THEY DO NOT OVERLAP.
 *
 * The real sessions overlap - London is open while New York opens, which is
 * the busiest stretch of the day and the reason traders name it separately. A
 * trade opened at 14:00 UTC is in London AND in New York, so a table with both
 * would count it twice and its columns would not add up to the record. Rather
 * than double-count, the overlap is its own block. The five blocks are
 * contiguous, cover the whole day, and every settled signal lands in exactly
 * one - so the counts sum to the total on the page, which is the property that
 * makes a breakdown checkable.
 *
 * UTC, because that is what the exchange stamps and what a session is defined
 * against. Boundaries do not shift with daylight saving: crypto has no opening
 * bell, these are conventions about when the desks that move it are staffed,
 * and moving the lines twice a year would make two halves of a year's record
 * incomparable for no gain.
 *
 * Deliberately not a setting. Sessions are a shared vocabulary; an install
 * whose "London" started at 06:00 would publish a table that reads like every
 * other one and means something else.
 */
final class Sessions
{
    /**
     * key => [label, start hour (inclusive), end hour (exclusive), who is trading]
     *
     * Ordered by clock, not by performance - a reader compares blocks against
     * their own day, and a table that reorders itself by result is one they
     * have to re-read every visit.
     */
    public const BLOCKS = [
        'asia'    => ['Asian',             0,  8, 'Tokyo, Hong Kong, Singapore'],
        'london'  => ['London',            8, 13, 'Europe, before the US wakes'],
        'overlap' => ['London + New York', 13, 17, 'both desks open — the busiest hours'],
        'newyork' => ['New York',         17, 21, 'US afternoon, Europe closed'],
        'late'    => ['Late US / Pacific', 21, 24, 'thinnest books of the day'],
    ];

    /** Which block an hour of the UTC day falls in. */
    public static function forHour(int $hour): string
    {
        $hour = (($hour % 24) + 24) % 24;
        foreach (self::BLOCKS as $key => $b) {
            if ($hour >= $b[1] && $hour < $b[2]) {
                return $key;
            }
        }
        return 'late';   // unreachable while the blocks cover 0-23
    }

    /** Which block a unix timestamp falls in. */
    public static function forTime(int $ts): string
    {
        return self::forHour((int)gmdate('G', $ts));
    }

    public static function label(string $key): string
    {
        return self::BLOCKS[$key][0] ?? $key;
    }

    public static function blurb(string $key): string
    {
        return self::BLOCKS[$key][3] ?? '';
    }

    /** "13:00-17:00 UTC" */
    public static function range(string $key): string
    {
        $b = self::BLOCKS[$key] ?? null;
        if (!$b) {
            return '';
        }
        return sprintf("%02d:00\u{2013}%02d:00 UTC", $b[1], $b[2] % 24);
    }

    /**
     * The same block in somebody else's clock.
     *
     * A reader in Mumbai does not plan their evening around 13:00 UTC. Given a
     * timezone the member has already told us about, this says when the block
     * starts and ends for them - the one piece of arithmetic that turns the
     * table from a fact about the engine into something they can act on.
     *
     * Returns '' for an unknown or empty zone rather than guessing, and marks
     * the end with a plus when the block runs past their midnight, because
     * "22:30-02:30" read without it looks like a typo.
     */
    public static function localRange(string $key, string $tz): string
    {
        $b = self::BLOCKS[$key] ?? null;
        if (!$b || $tz === '' || !in_array($tz, \DateTimeZone::listIdentifiers(), true)) {
            return '';
        }
        try {
            $zone = new \DateTimeZone($tz);
        } catch (\Exception $e) {
            return '';
        }
        // Anchored to today, so a zone with daylight saving reports the offset
        // its reader is living in rather than one from a fixed date in winter.
        //
        // Counted as HOURS FROM MIDNIGHT rather than as an hour of the clock:
        // the last block ends at hour 24, and `24 % 24` reads as midnight at
        // the START of the same day. That put the block's end twenty-four
        // hours before its own start, and the day-roll marker below then fired
        // on the one block that does not roll.
        $fmt = function (int $h) use ($zone): array {
            $d = new \DateTime(gmdate('Y-m-d') . ' 00:00', new \DateTimeZone('UTC'));
            $d->modify('+' . $h . ' hours');
            $d->setTimezone($zone);
            return [$d->format('H:i'), $d->format('Y-m-d')];
        };
        [$s, $sd] = $fmt($b[1]);
        [$e, $ed] = $fmt($b[2]);
        return $s . "\u{2013}" . $e . ($ed !== $sd ? '+1' : '');
    }

    /**
     * Bucket settled signals into the five blocks.
     *
     * Grouped in PHP rather than in SQL on purpose: hour-of-day from a unix
     * timestamp needs integer division and a modulo, and SQLite and MySQL
     * disagree about both the division and the CAST that fixes it. One loop
     * here is exact on either database and cannot drift between them.
     *
     * @param  iterable<array{created_at:mixed, outcome:mixed, outcome_r:mixed}> $rows
     * @return array<string, array{n:int, wins:int, r:float, winrate:?float, avg_r:?float}>
     */
    public static function bucket(iterable $rows): array
    {
        $out = [];
        foreach (array_keys(self::BLOCKS) as $k) {
            $out[$k] = ['n' => 0, 'wins' => 0, 'r' => 0.0, 'winrate' => null, 'avg_r' => null];
        }
        foreach ($rows as $row) {
            $k = self::forTime((int)$row['created_at']);
            $r = (float)$row['outcome_r'];
            $out[$k]['n']++;
            // A win is a trade that made money, not one that reached a label -
            // the same definition the headline figures use. See TrackRecord.
            if ($r > 0) {
                $out[$k]['wins']++;
            }
            $out[$k]['r'] += $r;
        }
        foreach ($out as $k => $v) {
            if ($v['n'] > 0) {
                $out[$k]['winrate'] = round($v['wins'] / $v['n'] * 100, 1);
                $out[$k]['avg_r']   = round($v['r'] / $v['n'], 2);
            }
        }
        return $out;
    }
}
