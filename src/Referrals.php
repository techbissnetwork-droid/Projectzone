<?php
declare(strict_types=1);

namespace SignalMasterAi;

/**
 * Referral credit.
 *
 * Coupons already existed but only as a discount the operator hands out. A
 * referral code is the member-driven version: each member gets one, and when
 * someone who arrived through it first pays, both sides get premium days.
 * Paying in access rather than cash keeps it entirely inside the existing
 * subscription machinery - no payouts, no balances, no accounting.
 */
class Referrals
{
    public static function enabled(): bool
    {
        return Database::setting('referral_enabled', '0') === '1';
    }

    /** Stable per-member code, created on first use. */
    public static function codeFor(int $memberId): string
    {
        if ($memberId <= 0) {
            return '';
        }
        $stmt = Database::pdo()->prepare('SELECT ref_code FROM members WHERE id = ?');
        $stmt->execute([$memberId]);
        $code = (string)($stmt->fetchColumn() ?: '');
        // Closed before the UPDATE below. A fetched-but-not-exhausted read
        // holds a read transaction, and the write that follows on the same
        // connection is then a read-to-write upgrade - the one case SQLite
        // fails without consulting busy_timeout. See Database::pdo().
        $stmt->closeCursor();
        if ($code !== '') {
            return $code;
        }
        // Short, unambiguous alphabet: no O/0 or I/1 to mistype.
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        for ($attempt = 0; $attempt < 6; $attempt++) {
            $candidate = '';
            for ($i = 0; $i < 7; $i++) {
                $candidate .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $chk = Database::pdo()->prepare('SELECT COUNT(*) FROM members WHERE ref_code = ?');
            $chk->execute([$candidate]);
            $free = (int)$chk->fetchColumn() === 0;
            $chk->closeCursor();
            if ($free) {
                Database::pdo()->prepare('UPDATE members SET ref_code = ? WHERE id = ?')
                    ->execute([$candidate, $memberId]);
                return $candidate;
            }
        }
        return '';
    }

    /** Remember an inbound code for the current visitor (30 days). */
    public static function remember(string $code): void
    {
        $code = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $code) ?? '');
        if ($code === '' || strlen($code) > 12) {
            return;
        }
        $stmt = Database::pdo()->prepare('SELECT id FROM members WHERE ref_code = ?');
        $stmt->execute([$code]);
        if ($stmt->fetchColumn()) {
            setcookie('sma_ref', $code, Request::cookieParams('/', time() + 30 * 86400));
        }
    }

    /** Attach the remembered referrer to a newly registered member. */
    public static function attach(int $memberId): void
    {
        $code = strtoupper((string)($_COOKIE['sma_ref'] ?? ''));
        if (!self::enabled() || $code === '' || $memberId <= 0) {
            return;
        }
        $stmt = Database::pdo()->prepare('SELECT id FROM members WHERE ref_code = ?');
        $stmt->execute([$code]);
        $referrer = (int)($stmt->fetchColumn() ?: 0);
        $stmt->closeCursor();          // before the UPDATE below
        // Self-referral is the obvious abuse; block it at the source.
        if ($referrer > 0 && $referrer !== $memberId) {
            Database::pdo()->prepare('UPDATE members SET referred_by = ? WHERE id = ? AND referred_by = 0')
                ->execute([$referrer, $memberId]);
        }
    }

    /**
     * Credit both sides after a member's first successful payment.
     *
     * IT USED TO PAY OUT BY DESTROYING THE RECORD OF WHO REFERRED WHOM.
     *
     * The idempotency guard was "set referred_by = 0", so the moment a
     * referral paid, the link between the two members was gone. The account
     * page counts referrals with WHERE referred_by = <me>, which means the
     * number a member reads as "signed up through your link" WENT DOWN every
     * time one of their referrals actually paid. Measured: a member who
     * referred two people, one of whom converted, was shown 1. Someone whose
     * referrals all converted - the best possible outcome - was shown 0, and
     * would reasonably conclude the feature was broken or that they had been
     * cheated out of the credit they had just been given.
     *
     * The guard is referral_paid now, which is what the column is for, and it
     * is claimed with a conditional UPDATE: whichever call flips 0 to 1 owns
     * the payout, so two completions racing on one member cannot both grant.
     * The link survives, so the counters can tell signed-up from converted.
     */
    public static function creditFirstPayment(int $memberId): int
    {
        if (!self::enabled() || $memberId <= 0) {
            return 0;
        }
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT referred_by FROM members WHERE id = ?');
        $stmt->execute([$memberId]);
        $referrer = (int)($stmt->fetchColumn() ?: 0);
        $stmt->closeCursor();          // before the claim below - see codeFor()
        if ($referrer <= 0) {
            return 0;
        }
        // Claim the payout before granting anything. Granting first and
        // marking after is how a crash between the two pays twice.
        $claim = $pdo->prepare('UPDATE members SET referral_paid = 1 WHERE id = ? AND referral_paid = 0');
        $claim->execute([$memberId]);
        if ($claim->rowCount() < 1) {
            return 0;               // somebody else already paid this one out
        }
        $days = max(1, min(365, (int)Database::setting('referral_days', '7')));
        foreach ([$referrer, $memberId] as $id) {
            self::grantDays($id, $days);
        }
        return $days;
    }

    /** Extend premium by N days, from now or from an existing expiry. */
    private static function grantDays(int $memberId, int $days): void
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT tier, paid_until FROM members WHERE id = ?');
        $stmt->execute([$memberId]);
        $m = $stmt->fetch();
        $stmt->closeCursor();
        if (!$m) {
            return;
        }
        // paid_until = 0 on a paid account means unlimited; never downgrade it.
        if ($m['tier'] === 'paid' && (int)$m['paid_until'] === 0) {
            return;
        }
        $from = max(time(), (int)$m['paid_until']);
        $pdo->prepare("UPDATE members SET tier = 'paid', paid_until = ? WHERE id = ?")
            ->execute([$from + $days * 86400, $memberId]);
    }

    /**
     * How many people this member has brought in, and how many converted.
     *
     * THE CONVERTED COUNT USED TO BE THE WHOLE SITE'S.
     *
     * Its query filtered on referral_paid and on having a paid payment, and
     * on NOTHING ELSE - no mention of the member being asked about. Every
     * member was therefore shown the site-wide number as their own. Measured
     * on a seeded install: an account that had referred nobody at all read
     * back converted = 1, the site's only conversion, belonging to somebody
     * else. On a real install with a few hundred conversions, every member
     * sees a few hundred.
     *
     * Both counts are scoped to the referrer now, and both are one query over
     * one indexed column.
     *
     * Referrals credited BEFORE this fix cannot be attributed: the old payout
     * erased referred_by, so those rows no longer name a referrer. They are
     * missing from both counts rather than being guessed at.
     *
     * @return array{pending:int,converted:int,total:int}
     */
    public static function stats(int $memberId): array
    {
        $pdo = Database::pdo();
        $q = $pdo->prepare(
            'SELECT COUNT(*) AS n, COALESCE(SUM(referral_paid), 0) AS paid
               FROM members WHERE referred_by = ?');
        $q->execute([$memberId]);
        $row = $q->fetch() ?: ['n' => 0, 'paid' => 0];
        $total = (int)$row['n'];
        $converted = (int)$row['paid'];
        return [
            'pending'   => max(0, $total - $converted),
            'converted' => $converted,
            'total'     => $total,
        ];
    }
}
