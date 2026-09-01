<?php
declare(strict_types=1);

namespace SignalMasterAi;

/**
 * The publish floor: the lowest grade this site shows to anybody.
 *
 * There were already two grade gates and they answer different questions.
 * `alert_min_grade` decides what is worth interrupting someone for - a C-grade
 * flip still appeared on the scanner, it just did not ping a phone at 3am.
 * This one decides what appears at all: below the floor, a signal is recorded,
 * verified and learned from exactly as before, but no visitor, member or API
 * caller is shown it.
 *
 * Why a second setting rather than reusing the first: "do not wake me for
 * this" and "do not publish this" are genuinely different intents, and an
 * operator who wanted quieter alerts would be very surprised to find their
 * scanner had emptied out.
 *
 * Two decisions that are easy to get wrong:
 *
 *   - Default 'any'. An upgrade must never silently hide signals a site was
 *     already publishing.
 *   - A row with no grade recorded passes. Those are signals stored before
 *     grading existed; hiding them would make an install's history appear to
 *     shrink the moment the operator touched an unrelated setting.
 *
 * ONE DIRECTION ONLY: a channel's own floor can sit above this one, never
 * below it. alertFloor() and broadcastFloor() enforce that - a member pinged
 * about a signal that is not published anywhere is left holding a dead link,
 * which is worse than not telling them. The settings page already said "the
 * alert floor narrows this further, it cannot widen it"; before these two
 * methods existed that was only true when an operator happened to leave the
 * two settings in the right relative order, not something the code checked.
 */
class Publish
{
    public const RANK = ['C' => 0, 'B' => 1, 'A' => 2, 'A+' => 3];

    /** The configured floor, or '' when nothing is being hidden. */
    public static function floor(): string
    {
        $g = Database::setting('show_min_grade', 'any');
        return isset(self::RANK[$g]) && $g !== 'C' ? $g : '';
    }

    /** Is the site hiding anything right now? */
    public static function active(): bool
    {
        return self::floor() !== '';
    }

    /** The stricter of a channel's own floor and the publish floor. */
    private static function narrowedFloor(string $settingKey, string $default): string
    {
        $own = (string)Database::setting($settingKey, $default);
        if (!isset(self::RANK[$own])) {
            $own = '';   // 'any', or anything unrecognised
        }
        $pub = self::floor();
        if ($own === '') {
            return $pub;
        }
        if ($pub === '') {
            return $own;
        }
        return self::RANK[$pub] >= self::RANK[$own] ? $pub : $own;
    }

    /**
     * What push/email alerts must clear before they are sent - alert_min_grade,
     * or the publish floor, whichever is stricter. A signal below the publish
     * floor is not on the scanner, the coin page or the API, so an alert about
     * it would send someone to look for something that is not there.
     */
    public static function alertFloor(): string
    {
        return self::narrowedFloor('alert_min_grade', 'any');
    }

    /**
     * What a public broadcast (Telegram channel, Discord, Slack) must clear -
     * same reasoning as alertFloor(), for an audience that never even has a
     * member account to check the scanner from.
     */
    public static function broadcastFloor(): string
    {
        return self::narrowedFloor('broadcast_min_grade', 'A');
    }

    // ---------------------------------------------------------------- types
    //
    // WHEN THE OPERATOR HAS ALREADY CHOSEN, THE READER IS NOT ASKED AGAIN.
    //
    // min_rr is the lowest of the three signal types the site publishes. At 1
    // it publishes all three and choosing between them is genuinely the
    // reader's decision - a 1:1 is a shorter trade with a nearer target, not a
    // worse signal, so somebody who only wants quick 1:1s and somebody who
    // only wants to sit in 1:3s are both right. At 2 or 3 the operator has
    // narrowed the site, and a filter offering types that no longer exist is
    // a control that does nothing: pick "1:1 only" on a 1:3-only site and the
    // board goes empty with no explanation on the page for why.
    //
    // So the member-facing type controls - the scanner dropdown, the chart's
    // scanner sheet, and the alert-type checkboxes - appear only while the
    // operator is publishing all three. One question, asked here, so the three
    // places cannot disagree about whether it is being asked.

    /** The lowest type this site publishes: 1, 2 or 3. */
    public static function minType(): int
    {
        return max(1, min(3, (int)Database::setting('min_rr', '1')));
    }

    /** Has the operator narrowed the site to some of the three types? */
    public static function typeRestricted(): bool
    {
        return self::minType() > 1;
    }

    /** May a member choose which types they see and are alerted about? */
    public static function memberMayChooseType(): bool
    {
        return !self::typeRestricted();
    }

    /**
     * The type a request asked for, or 0 for "no filter".
     *
     * Returns 0 whenever the operator has narrowed the site, so a hidden
     * control cannot be filtered by anyway. A disabled input is a suggestion
     * to a browser, not a rule to a request - the same reason the grade floor
     * is enforced in MemberPrefs rather than in the form that renders it.
     */
    public static function askedType($raw): int
    {
        if (!self::memberMayChooseType()) {
            return 0;
        }
        $t = (int)$raw;
        return $t >= 1 && $t <= 3 ? $t : 0;
    }

    /** Does one signal type clear the floor? Untyped calls always pass. */
    public static function allowsType(int $tier): bool
    {
        return $tier < 1 || $tier >= self::minType();
    }

    /** Does one grade clear the floor? */
    public static function allows(string $grade): bool
    {
        $floor = self::floor();
        if ($floor === '' || $grade === '') {
            return true;
        }
        return (self::RANK[$grade] ?? 0) >= self::RANK[$floor];
    }

    /**
     * SQL fragment for the floor, e.g. " AND (s.grade = '' OR s.grade IN
     * ('A','A+'))". Returns '' when no floor is set, so it can be
     * concatenated unconditionally.
     *
     * The grades are interpolated rather than bound because they come from
     * self::RANK - a fixed set of four literals this file owns - and because
     * a fragment that also needed its parameters threaded through every
     * caller would be got wrong somewhere.
     */
    public static function sql(string $alias = ''): string
    {
        $floor = self::floor();
        if ($floor === '') {
            return '';
        }
        $col = ($alias !== '' ? $alias . '.' : '') . 'grade';
        $ok = [];
        foreach (self::RANK as $g => $rank) {
            if ($rank >= self::RANK[$floor]) {
                $ok[] = "'" . $g . "'";
            }
        }
        return " AND ($col = '' OR $col IN (" . implode(',', $ok) . '))';
    }

    /**
     * One line for a public page saying what it covers, or '' when the site
     * publishes everything and there is nothing to say.
     *
     * A site that quietly publishes a third of its signals and says nothing is
     * harder to trust than one that says which third. That principle is right;
     * the first version of this sentence got the delivery wrong in three ways
     * at once, and on the track record - the page a stranger reads to decide
     * whether to trust this site at all - it did real damage.
     *
     * It led with what is WITHHELD. "This site publishes grade A setups and
     * above. Weaker calls are still analysed and scored behind the scenes,
     * they are simply not shown." A reader who has not yet been told what a
     * grade is reads that as: we hide our bad calls.
     *
     * It CONTRADICTED the paragraph two lines below it, which says nothing on
     * the page is curated by hand. Both are true - a grade floor is a rule
     * applied before the fact, not a hand-picked list after it - but nothing
     * on the page said so, and a filter admitted and then denied reads worse
     * than either alone.
     *
     * And it was written in the vocabulary of the admin panel. "Scored behind
     * the scenes" is how an operator describes their own engine. To a visitor
     * it describes something happening out of sight.
     *
     * So: state the rule, not the omission, and lead with the part that is
     * actually a strength - this record is measured on what was published, so
     * it is the record a follower would have. The detail belongs in the
     * methodology, which is where a reader who wants it goes looking.
     */
    public static function note(bool $isRecord = false): string
    {
        $floor = self::floor();
        if ($floor === '') {
            return '';
        }
        // WHAT THE FLOOR IS SET TO IS NOT THE READER'S BUSINESS.
        //
        // The scanner used to open with "Showing grade A setups and above -
        // 6 of 12 pairs are below that and are not published." Every word of
        // that is true and every word of it is written for the operator: a
        // visitor learns that the board is a filtered view, that six things
        // exist which they are not being shown, and the exact setting doing
        // it. None of that helps them read the board, and "we are hiding six
        // from you" is a strange thing for a page to volunteer. The operator
        // needs the number and has it, counted per reason, on the admin
        // dashboard - which is where a question about the site's own
        // configuration belongs.
        //
        // The track record keeps a line, because that page makes a claim
        // about a population and has to say which one. It no longer names the
        // grade: "the calls this site published" is the honest description of
        // the sample.
        //
        // It used to end "Nothing is added or removed after the fact." - which
        // is the sentence performance.php prints immediately before appending
        // this one, so the reader met it twice in a row. Said once, by the
        // page that owns the surrounding sentence.
        return $isRecord
            ? 'Measured on the calls this site published, which is what a follower would have '
              . 'received.'
            : '';
    }
}
