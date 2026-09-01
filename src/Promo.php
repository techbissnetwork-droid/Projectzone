<?php
declare(strict_types=1);

namespace SignalMasterAi;

/**
 * A discount campaign: who sees it, when, and what it actually gives them.
 *
 * THE OFFER IS THE COUPON, NOT THE BANNER. The percentage, the expiry and
 * whether the code still has uses left all come from the coupons table -
 * the same row the checkout will read when somebody types the code. A promo
 * that stores its own "50% off" is a promo that will one day advertise 50%
 * while the checkout applies 20, or keep running after the code has expired,
 * and the person who finds out is a customer at the moment they were about
 * to pay. So there is one number and this reads it.
 *
 * It follows that a campaign switches itself off. No coupon, a disabled
 * coupon, an expired one, or one that has hit its use limit, and there is no
 * campaign - not a broken banner, no banner. The admin card says which of
 * those is true rather than leaving an operator to wonder why nothing shows.
 *
 * Targeting is deliberately coarse: an audience, an optional list of
 * specific people, and an optional random slice. Anything finer would be a
 * segmentation engine, and this is a crypto signals site with a coupon code.
 */
class Promo
{
    /** Per-request memo - asked once for the banner and once for the popup. */
    private static $memo = false;

    /**
     * The live campaign, or null.
     *
     * @return array{code:string,percent:int,ends:int,headline:string,body:string,
     *               banner:bool,popup:bool,timer:bool}|null
     */
    public static function active(): ?array
    {
        if (self::$memo !== false) {
            return self::$memo;
        }
        self::$memo = null;
        if (Database::setting('promo_enabled', '0') !== '1') {
            return null;
        }
        $code = trim(Database::setting('promo_coupon', ''));
        if ($code === '') {
            return null;
        }
        $stmt = Database::pdo()->prepare(
            'SELECT code, percent_off, max_uses, used_count, expires_at, enabled
             FROM coupons WHERE code = ?');
        $stmt->execute([$code]);
        $c = $stmt->fetch();
        $stmt->closeCursor();
        if (!$c || (int)$c['enabled'] !== 1) {
            return null;
        }
        // Out of uses is the same as gone. A banner offering a code the
        // checkout will refuse is worse than no banner: it converts interest
        // into a support message.
        if ((int)$c['max_uses'] > 0 && (int)$c['used_count'] >= (int)$c['max_uses']) {
            return null;
        }
        $now = time();
        $starts = (int)Database::setting('promo_starts', '0');
        if ($starts > 0 && $now < $starts) {
            return null;             // scheduled, not started
        }
        // The campaign ends at whichever comes first: the operator's end date
        // or the coupon's own expiry. A countdown that outlives the code it
        // is counting down to is a clock lying to a customer.
        $ends = (int)Database::setting('promo_ends', '0');
        $cEnd = (int)$c['expires_at'];
        if ($ends <= 0 || ($cEnd > 0 && $cEnd < $ends)) {
            $ends = $cEnd;
        }
        if ($ends > 0 && $now >= $ends) {
            return null;             // finished
        }
        $pct = max(1, min(100, (int)$c['percent_off']));
        $headline = trim(Database::setting('promo_headline', ''));
        $body     = trim(Database::setting('promo_body', ''));
        return self::$memo = [
            'code'     => (string)$c['code'],
            'percent'  => $pct,
            'ends'     => $ends,
            'headline' => $headline !== '' ? $headline : $pct . '% off Premium',
            'body'     => $body !== '' ? $body
                : 'Use code ' . $c['code'] . ' at checkout. Everything Premium includes, at '
                  . (100 - $pct) . '% of the usual price.',
            'banner'   => Database::setting('promo_banner', '1') === '1',
            'popup'    => Database::setting('promo_popup', '1') === '1',
            'timer'    => Database::setting('promo_timer', '1') === '1' && $ends > 0,
        ];
    }

    /**
     * Does this particular viewer get it?
     *
     * @param array|null $member the logged-in member row, or null for a guest
     */
    public static function forViewer(?array $member): ?array
    {
        $p = self::active();
        if ($p === null) {
            return null;
        }
        // A named list beats every other rule. "Send this to these people"
        // is an instruction, not a filter to be combined with the others -
        // an operator who types an address expects that address to get it,
        // and silently dropping them because they fell outside the random
        // slice would be the feature failing at the one job it was given.
        $emails = array_filter(array_map(
            static fn($e) => strtolower(trim($e)),
            explode(',', Database::setting('promo_emails', ''))
        ));
        if ($emails) {
            return ($member && in_array(strtolower((string)$member['email']), $emails, true))
                ? $p : null;
        }
        if (!self::audienceMatches($member)) {
            return null;
        }
        // A slice of the audience, chosen once and stably.
        //
        // Deterministic on the member and the code, NOT random per request:
        // rand() here would show the banner on one page and hide it on the
        // next, which reads as a broken site and makes the offer look fake.
        // The same member sees the same answer for the whole campaign, and a
        // different campaign reshuffles because the code is in the hash.
        $slice = max(0, min(100, (int)Database::setting('promo_random_pct', '100')));
        if ($slice >= 100) {
            return $p;
        }
        if ($slice === 0) {
            return null;
        }
        $who = $member ? 'm' . (int)$member['id'] : 'g' . session_id();
        return (crc32($p['code'] . '|' . $who) % 100) < $slice ? $p : null;
    }

    /** Whether the popup (as opposed to the banner) is due for this member. */
    public static function popupFor(?array $member): ?array
    {
        $p = self::forViewer($member);
        if ($p === null || !$p['popup']) {
            return null;
        }
        $hours = max(1, (int)Database::setting('promo_repeat_hours', '48'));
        if (!$member) {
            // A cookie, because a signed-out visitor has no row - and the
            // alternative shipped: "guests are re-asked" meant the panel
            // returned on every page view with no way to stop it. An
            // interruption a visitor cannot end is not a marketing decision,
            // it is a reason to close the tab, and audience=all pointed it at
            // everyone who had never heard of the site.
            $seen = (int)($_COOKIE['sma_promo_off'] ?? 0);
            return ($seen > 0 && time() - $seen < $hours * 3600) ? null : $p;
        }
        $seen = (int)($member['promo_dismissed'] ?? 0);
        return ($seen > 0 && time() - $seen < $hours * 3600) ? null : $p;
    }

    private static function audienceMatches(?array $member): bool
    {
        $aud = Database::setting('promo_audience', 'free');
        $tier = $member ? (string)($member['tier'] ?? 'free') : 'guest';
        switch ($aud) {
            case 'all':
                return true;
            case 'paid':
                return $tier === 'paid';
            case 'expired':
                // Was paying and is not now. The renewal conversation, which
                // is a different pitch from a first sale and the one most
                // worth discounting.
                return $tier !== 'paid' && (int)($member['paid_until'] ?? 0) > 0;
            case 'new':
                $days = max(1, (int)Database::setting('promo_new_days', '7'));
                return $member !== null && $tier !== 'paid'
                    && time() - (int)($member['created_at'] ?? 0) <= $days * 86400;
            case 'free':
            default:
                // Members only. A guest cannot buy without registering, so
                // the offer would be pointing at a wall.
                return $member !== null && $tier !== 'paid';
        }
    }

    /** "2 days, 4 hours" - for the copy, where a ticking clock is not wanted. */
    public static function remaining(int $ends): string
    {
        $s = $ends - time();
        if ($ends <= 0 || $s <= 0) {
            // active() never hands out a finished campaign, so this is
            // unreachable today - but it used to answer "1 min" for a
            // timestamp in the past, and a countdown that invents a minute it
            // does not have is the one kind of wrong this must never be.
            return 'ended';
        }
        if ($s >= 86400) {
            $d = intdiv($s, 86400);
            $h = intdiv($s % 86400, 3600);
            return $d . ($d === 1 ? ' day' : ' days') . ($h > 0 ? ', ' . $h . ($h === 1 ? ' hour' : ' hours') : '');
        }
        if ($s >= 3600) {
            $h = intdiv($s, 3600);
            $m = intdiv($s % 3600, 60);
            return $h . ($h === 1 ? ' hour' : ' hours') . ($m > 0 ? ', ' . $m . ' min' : '');
        }
        return max(1, intdiv($s, 60)) . ' min';
    }
}
