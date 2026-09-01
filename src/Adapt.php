<?php
declare(strict_types=1);

namespace SignalMasterAi;

/**
 * The self-tuning loop: propose, validate, apply, watch, roll back.
 *
 * The pieces for this already existed and none of them talked to each other.
 * Rule weights tuned themselves from outcomes. The backtester could compare a
 * champion configuration against a challenger. Level multipliers were
 * optimised per timeframe. But nothing ever PROPOSED a challenger - an admin
 * had to guess which knob to try, run the comparison by hand, read the number
 * and decide. The engine could measure whether an idea was good and could not
 * have an idea.
 *
 * This closes that loop:
 *
 *   propose   read what the measured record says is weak, and turn it into
 *             concrete candidate settings. Not a grid search over everything -
 *             a small set of changes with a reason behind each one.
 *   validate  replay history under champion and challenger. A change is only
 *             adopted on a clear win, by a margin, on a sample big enough to
 *             mean it.
 *   apply     write it down: what changed, from what, to what, and the
 *             expectancy that justified it. A change with no record of why is
 *             indistinguishable from drift.
 *   watch     compare what has actually happened since against the expectancy
 *             that was promised. Backtests are optimistic; live results are
 *             the only ones that count.
 *   roll back when a change fails to deliver, put it back. Automatically, and
 *             loudly enough that the history shows it.
 *
 * Everything lives in settings - no tables, no queue, no daemon. It runs from
 * the same cron that already tunes weights, and does nothing at all until
 * there is enough verified history to be worth running.
 */
class Adapt
{
    /** Nothing is proposed until this many verified signals exist. */
    private const MIN_SAMPLES = 80;

    /** A challenger must beat the champion by this much R to be adopted. */
    private const MIN_EDGE = 0.03;

    /** How many changes to remember. */
    private const HISTORY = 40;

    /**
     * Candidate changes, each with the measured observation behind it.
     *
     * Deliberately few and deliberately reasoned. A grid search over every
     * setting would find something that looks better on any history by
     * accident, and the whole point of the validation step is to be harder to
     * fool than that.
     */
    public static function proposals(): array
    {
        $out = [];
        $stats = self::performance();
        if ($stats['n'] < self::MIN_SAMPLES) {
            return $out;
        }

        // --- Thresholds -----------------------------------------------------
        //
        // Judged on expectancy, never on win rate. Win rate cannot tell a good
        // system from a bad one: 45% at +2R makes money and 67% can lose a
        // fortune - this engine was doing exactly that on its fast timeframes,
        // winning two thirds of the time and bleeding on fees, and a win-rate
        // test would have called it healthy while raising the bar on the
        // timeframes that actually worked.
        $buyT = (float)Database::setting('cat_buy_threshold', '3');
        $perDay = $stats['per_day'];
        $exp = $stats['avg_r'];
        if ($exp !== null && $exp < 0.0 && $stats['n'] >= 120) {
            $out[] = [
                'key' => 'threshold_up',
                'why' => sprintf('%+.3fR per trade across %d verified signals (win rate %.0f%%) - '
                    . 'the bar is letting through setups that do not pay.',
                    $exp, $stats['n'], (float)$stats['winrate']),
                'settings' => [
                    'cat_buy_threshold' => (string)round($buyT * 1.15, 2),
                    'cat_sell_threshold' => (string)round(-$buyT * 1.15, 2),
                ],
            ];
        } elseif ($exp !== null && $exp > 0.15 && $perDay !== null && $perDay < 1.5) {
            $out[] = [
                'key' => 'threshold_down',
                'why' => sprintf('%+.3fR per trade but only %.1f signals a day - the bar may be '
                    . 'turning away setups worth taking.', $exp, $perDay),
                'settings' => [
                    'cat_buy_threshold' => (string)round($buyT * 0.9, 2),
                    'cat_sell_threshold' => (string)round(-$buyT * 0.9, 2),
                ],
            ];
        }

        // --- Counter-trend pricing ------------------------------------------
        // The ladder charges trades that fight it. Whether it charges enough
        // is a question the record can answer.
        $align = self::alignmentStats();
        if ($align['against']['n'] >= 25 && $align['with']['n'] >= 25
            && $align['against']['avg_r'] !== null && $align['with']['avg_r'] !== null) {
            $gap = $align['with']['avg_r'] - $align['against']['avg_r'];
            $pen = (float)Database::setting('mtf_penalty', '0.6');
            if ($gap > 0.15 && $pen < 1.2) {
                $out[] = [
                    'key' => 'mtf_penalty_up',
                    'why' => sprintf('Setups with the higher timeframes have averaged %+.2fR against '
                        . '%+.2fR for those fighting them - counter-trend trades are underpriced.',
                        $align['with']['avg_r'], $align['against']['avg_r']),
                    'settings' => ['mtf_penalty' => (string)round(min(1.2, $pen + 0.25), 2)],
                ];
            } elseif ($gap < 0.0 && $pen > 0.2) {
                $out[] = [
                    'key' => 'mtf_penalty_down',
                    'why' => sprintf('Counter-trend setups have averaged %+.2fR against %+.2fR for '
                        . 'aligned ones - the penalty is charging for something that is not costing.',
                        $align['against']['avg_r'], $align['with']['avg_r']),
                    'settings' => ['mtf_penalty' => (string)round(max(0.2, $pen - 0.25), 2)],
                ];
            }
        }

        // --- Time stop -------------------------------------------------------
        // Setups expiring untriggered in bulk are being given too little room
        // in time; almost none expiring suggests the opposite.
        if ($stats['expired_share'] !== null && $stats['n'] >= 120) {
            $bars = (int)Database::setting('time_stop_bars', '24');
            if ($stats['expired_share'] > 0.35 && $bars < 60) {
                $out[] = [
                    'key' => 'time_stop_up',
                    'why' => sprintf('%.0f%% of setups expire without touching either level - they '
                        . 'are not being given time to resolve.', $stats['expired_share'] * 100),
                    'settings' => ['time_stop_bars' => (string)min(60, (int)round($bars * 1.4))],
                ];
            } elseif ($stats['expired_share'] < 0.05 && $bars > 8) {
                $out[] = [
                    'key' => 'time_stop_down',
                    'why' => sprintf('Only %.0f%% of setups expire - capital is being tied up in '
                        . 'trades that have already decided.', $stats['expired_share'] * 100),
                    'settings' => ['time_stop_bars' => (string)max(8, (int)round($bars * 0.75))],
                ];
            }
        }

        return $out;
    }

    /**
     * Evaluate proposals against history and adopt clear winners.
     *
     * One change per run, always the best-scoring candidate. Applying several
     * at once makes the next drift check unable to say which one was
     * responsible, and a tuner that cannot attribute its own results cannot
     * roll them back either.
     */
    public static function run(array $symbols, string $tf = '1h', ?float $deadline = null): array
    {
        $report = ['at' => time(), 'tf' => $tf, 'proposals' => 0, 'applied' => null, 'results' => []];
        $proposals = self::proposals();
        $report['proposals'] = count($proposals);
        if (!$proposals || !$symbols) {
            return self::store($report);
        }

        $best = null;
        foreach ($proposals as $p) {
            // Proposals are deliberately few (see proposals() above), so this
            // is rarely hit - but each one replays real history per symbol,
            // and this still runs synchronously inside cron's own lock. A
            // slice that runs out is not a wrong answer, only a smaller one:
            // whatever was compared so far still stands, and next week's
            // claim re-reads the live record fresh, so nothing here is lost,
            // only deferred.
            if ($deadline !== null && microtime(true) >= $deadline) {
                $report['stopped_early'] = true;
                break;
            }
            try {
                $cmp = Backtest::compare($symbols, $tf, $p['settings']);
            } catch (\Throwable $e) {
                continue;
            }
            $delta = $cmp['delta'];
            $report['results'][] = [
                'key' => $p['key'], 'why' => $p['why'], 'delta' => $delta,
                'champion' => $cmp['champion']['expectancy'],
                'challenger' => $cmp['challenger']['expectancy'],
                'signals' => $cmp['challenger']['signals'],
            ];
            // A challenger that wins by producing almost nothing has not found
            // an edge, it has found a way to stop trading.
            if ($delta === null || $delta < self::MIN_EDGE
                || $cmp['challenger']['signals'] < max(20, $cmp['champion']['signals'] * 0.4)) {
                continue;
            }
            if ($best === null || $delta > $best['delta']) {
                $best = ['proposal' => $p, 'delta' => $delta,
                         'expected' => $cmp['challenger']['expectancy'],
                         'champion' => $cmp['champion']['expectancy']];
            }
        }

        if ($best !== null) {
            // Snapshot the live record before the change lands, so the drift
            // check has a like-for-like baseline to judge against later.
            $liveBefore = self::performance();
            $before = [];
            foreach ($best['proposal']['settings'] as $k => $v) {
                $before[$k] = Database::setting($k, '');
                Database::setSetting($k, (string)$v);
                Audit::log('adapt.apply', $k, (string)$before[$k], (string)$v, Audit::ACTOR_ENGINE);
            }
            $entry = [
                'at' => time(),
                'key' => $best['proposal']['key'],
                'why' => $best['proposal']['why'],
                'from' => $before,
                'to' => $best['proposal']['settings'],
                'expected' => $best['expected'],
                'champion_was' => $best['champion'],
                // What this install was actually achieving, live, at the
                // moment of the change. This - not the backtest figure beside
                // it - is what the drift check judges against later.
                'live_before' => $liveBefore['avg_r'],
                'live_before_n' => $liveBefore['n'],
                'delta' => $best['delta'],
                'status' => 'active',
                // The point results are measured from. Only signals created
                // after the change can say anything about it.
                'since' => time(),
            ];
            self::remember($entry);
            $report['applied'] = $entry;
        }
        return self::store($report);
    }

    /**
     * Has the last applied change delivered what the backtest promised?
     *
     * Backtests are optimistic by construction - they are fitted on the same
     * history they are scored against, and the market that produced that
     * history is not the one trading now. The only honest test of a change is
     * what happened after it, so that is what this measures, and a change that
     * fails is put back rather than left to quietly cost money.
     */
    public static function driftCheck(int $minSamples = 40): array
    {
        $hist = self::history();
        $last = null;
        $idx = null;
        foreach ($hist as $i => $h) {
            if (($h['status'] ?? '') === 'active') {
                $last = $h;
                $idx = $i;
            }
        }
        if ($last === null) {
            return ['checked' => false, 'reason' => 'no active change'];
        }

        $since = (int)($last['since'] ?? $last['at'] ?? 0);
        $live = self::performance($since);
        if ($live['n'] < $minSamples) {
            return ['checked' => false, 'reason' => 'only ' . $live['n'] . ' verified signals since the change',
                    'need' => $minSamples];
        }

        // Live against live. The obvious baseline is the champion's backtest
        // expectancy, and it is the wrong one: a backtest replays the candles
        // its own tuned weights and level distances were fitted on, which
        // measured about a fifth high on this install. Judging a live result
        // against that inflated figure would condemn almost every change,
        // including the good ones, and the tuner would spend its life
        // reverting improvements.
        //
        // So the baseline is what this install was actually achieving before
        // the change, captured at the moment it was applied. Older entries
        // that predate that field are left alone rather than judged against
        // a number that cannot answer the question.
        $baseline = $last['live_before'] ?? null;
        $actual = $live['avg_r'];
        $out = ['checked' => true, 'key' => $last['key'], 'n' => $live['n'],
                'actual' => $actual, 'expected' => $last['expected'] ?? null,
                'baseline' => $baseline, 'rolled_back' => false];
        if ($baseline === null) {
            $out['reason'] = 'no live baseline recorded for this change';
            return $out;
        }

        if ($actual === null || $baseline === null) {
            return $out;
        }
        // A little worse than the old configuration is noise. Clearly worse,
        // on a real sample, is a mistake worth undoing.
        if ($actual < $baseline - self::MIN_EDGE) {
            foreach ((array)($last['from'] ?? []) as $k => $v) {
                Audit::log('adapt.rollback', $k, Database::setting($k, ''), (string)$v,
                    Audit::ACTOR_ENGINE);
                Database::setSetting($k, (string)$v);
            }
            $hist[$idx]['status'] = 'rolled_back';
            $hist[$idx]['rolled_back_at'] = time();
            $hist[$idx]['actual'] = $actual;
            self::writeHistory($hist);
            $out['rolled_back'] = true;
        } else {
            $hist[$idx]['actual'] = $actual;
            $hist[$idx]['confirmed_at'] = time();
            self::writeHistory($hist);
        }
        return $out;
    }

    /**
     * Rule performance sliced by market regime.
     *
     * A rule that pays in a grinding trend and loses in a whipsaw has an
     * average that describes neither, and the flat per-rule record could not
     * see the difference. This is the input adaptive weighting needs to become
     * conditional rather than global.
     */
    public static function regimeStats(int $lastN = 1000): array
    {
        $rows = Database::pdo()->query(
            "SELECT `signal`, reasons, features, outcome, outcome_r FROM signals
             WHERE outcome IN ('confirmed','invalid') AND `signal` != 'NEUTRAL'
               AND features IS NOT NULL
             ORDER BY created_at DESC LIMIT " . (int)$lastN
        )->fetchAll();

        $out = [];
        foreach ($rows as $row) {
            $f = json_decode((string)$row['features'], true);
            $regime = is_array($f) ? (string)($f['regime'] ?? '') : '';
            if ($regime === '' || $regime === 'unknown') {
                continue;
            }
            $dirSide = $row['signal'] === 'BUY' ? 'bullish' : 'bearish';
            $won = Outcomes::isWin($row);
            $r = (float)($row['outcome_r'] ?? 0);
            foreach ((array)json_decode((string)$row['reasons'], true) as $rs) {
                if (!is_array($rs) || empty($rs['key']) || ($rs['side'] ?? '') !== $dirSide) {
                    continue;
                }
                $k = (string)$rs['key'];
                $out[$regime][$k] ??= ['fired' => 0, 'wins' => 0, 'r_sum' => 0.0];
                $out[$regime][$k]['fired']++;
                $out[$regime][$k]['r_sum'] += $r;
                if ($won) {
                    $out[$regime][$k]['wins']++;
                }
            }
        }
        foreach ($out as $regime => $rules) {
            foreach ($rules as $k => $s) {
                $out[$regime][$k]['avg_r'] = $s['fired'] > 0 ? round($s['r_sum'] / $s['fired'], 3) : null;
                $out[$regime][$k]['winrate'] = $s['fired'] > 0 ? round($s['wins'] / $s['fired'] * 100, 1) : null;
            }
        }
        return $out;
    }

    /** Verified-signal performance, optionally only since a timestamp. */
    public static function performance(int $since = 0): array
    {
        $sql = "SELECT outcome, outcome_r, created_at FROM signals
                WHERE outcome IN ('confirmed','invalid','expired') AND `signal` != 'NEUTRAL'";
        $args = [];
        if ($since > 0) {
            $sql .= ' AND created_at >= ?';
            $args[] = $since;
        }
        $stmt = Database::pdo()->prepare($sql . ' ORDER BY created_at DESC LIMIT 2000');
        $stmt->execute($args);
        $rows = $stmt->fetchAll();

        $decided = 0;
        $wins = 0;
        $expired = 0;
        $rSum = 0.0;
        $first = null;
        $lastT = null;
        foreach ($rows as $row) {
            $t = (int)$row['created_at'];
            $first = $first === null ? $t : min($first, $t);
            $lastT = $lastT === null ? $t : max($lastT, $t);
            if ($row['outcome'] === 'expired') {
                $expired++;
                continue;
            }
            $decided++;
            $rSum += (float)$row['outcome_r'];
            if (Outcomes::isWin($row)) {
                $wins++;
            }
        }
        $total = $decided + $expired;
        $days = ($first !== null && $lastT !== null && $lastT > $first)
            ? max(1.0, ($lastT - $first) / 86400) : null;
        return [
            'n' => $decided,
            'total' => $total,
            'winrate' => $decided > 0 ? round($wins / $decided * 100, 1) : null,
            'avg_r' => $decided > 0 ? round($rSum / $decided, 4) : null,
            'expired_share' => $total > 0 ? round($expired / $total, 4) : null,
            'per_day' => $days !== null ? round($total / $days, 2) : null,
        ];
    }

    /**
     * Expectancy split by whether the setup agreed with the higher timeframes.
     * Reads the stored feature vectors, so it only sees signals created since
     * the ladder started being recorded.
     */
    public static function alignmentStats(int $lastN = 1000): array
    {
        $rows = Database::pdo()->query(
            "SELECT `signal`, features, outcome, outcome_r FROM signals
             WHERE outcome IN ('confirmed','invalid') AND `signal` != 'NEUTRAL'
               AND features IS NOT NULL
             ORDER BY created_at DESC LIMIT " . (int)$lastN
        )->fetchAll();

        $b = ['with' => ['n' => 0, 'r' => 0.0], 'against' => ['n' => 0, 'r' => 0.0],
              'split' => ['n' => 0, 'r' => 0.0]];
        foreach ($rows as $row) {
            $f = json_decode((string)$row['features'], true);
            if (!is_array($f) || !isset($f['mtf_bias'])) {
                continue;
            }
            $dir = $row['signal'] === 'BUY' ? 1 : -1;
            $aligned = (float)$f['mtf_bias'] * $dir;
            $k = $aligned >= 0.35 ? 'with' : ($aligned <= -0.35 ? 'against' : 'split');
            $b[$k]['n']++;
            $b[$k]['r'] += (float)$row['outcome_r'];
        }
        foreach ($b as $k => $v) {
            $b[$k]['avg_r'] = $v['n'] > 0 ? round($v['r'] / $v['n'], 4) : null;
        }
        return $b;
    }

    /** The change log, oldest first. */
    public static function history(): array
    {
        $h = json_decode(Database::setting('adapt_history', ''), true);
        return is_array($h) ? $h : [];
    }

    private static function remember(array $entry): void
    {
        $h = self::history();
        $h[] = $entry;
        if (count($h) > self::HISTORY) {
            $h = array_slice($h, -self::HISTORY);
        }
        self::writeHistory($h);
    }

    private static function writeHistory(array $h): void
    {
        Database::setSetting('adapt_history', json_encode(array_values($h)));
    }

    private static function store(array $report): array
    {
        Database::setSetting('adapt_report', json_encode($report));
        return $report;
    }
}
