<?php
declare(strict_types=1);

namespace Techbiss\Core;

final class Dates
{
    /**
     * Add whole months to a date, clamping to the last day of the target month.
     *
     * PHP's native "+1 month" overflows into the following month whenever the
     * source day does not exist in the target — 31 January plus one month gives
     * 3 March. For package terms that silently grants the customer extra days
     * and puts the renewal date in the wrong month, so every term calculation
     * goes through here instead.
     */
    public static function addMonths(string $date, int $months): string
    {
        $start = date_create_immutable($date);
        if ($start === false) {
            $start = date_create_immutable('today');
            if ($start === false) {
                return date('Y-m-d');
            }
        }

        $day = (int) $start->format('j');
        // Move to the first of the month, add the months, then re-apply the day
        // capped at however many days that month actually has.
        $target    = $start->modify('first day of this month')->modify(sprintf('%+d months', $months));
        $daysInMonth = (int) $target->format('t');

        return $target->setDate(
            (int) $target->format('Y'),
            (int) $target->format('n'),
            min($day, $daysInMonth)
        )->format('Y-m-d');
    }

    /** True when a date string is a real, parseable date. */
    public static function isValid(?string $date): bool
    {
        return $date !== null && $date !== '' && !str_starts_with($date, '0000') && strtotime($date) !== false;
    }
}
