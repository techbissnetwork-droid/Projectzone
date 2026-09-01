<?php
declare(strict_types=1);

namespace SignalMasterAi;

/**
 * Logistic regression over the fired-rule vectors of verified signals.
 *
 * Every stored signal already carries the exact set of rules that fired
 * (`reasons`) and how it resolved (`outcome`). That is a labelled training set
 * sitting in the database, and hand-set weights were the only thing consuming
 * it. A linear model fitted on that data does three things the weighted sum
 * cannot:
 *
 *   1. It learns correlation structure directly. Where the engine needs an
 *      explicit category cap to stop five oscillators counting five times, the
 *      model simply discovers that their coefficients overlap and splits the
 *      credit between them.
 *   2. Its output is a probability, so confidence stops being a heuristic
 *      rescaling of the score and becomes a calibrated P(win).
 *   3. It can weigh context - regime, timeframe, session - alongside rules.
 *
 * Pure PHP, batch gradient descent with L2 regularisation. Training is bounded
 * by iteration count and sample cap, runs nightly from the tuner, and is never
 * a hard dependency: when the model is absent, immature or disabled, the
 * engine falls back to the weighted sum exactly as before.
 */
class LearnedModel
{
    private const ITERATIONS = 300;
    private const LEARNING_RATE = 0.35;
    private const L2 = 0.02;
    /** Minimum verified signals before the model is allowed to speak. */
    private const MIN_SAMPLES = 120;

    /**
     * How volatile the coin was WHEN THE SIGNAL FIRED, as a class.
     *
     * The coin-awareness that per-coin weight tables cannot afford. Splitting
     * the record by symbol gives every coin its own thin slice; adding this as
     * a feature costs four coefficients and every sample still teaches the
     * model about every rule. The model can then learn "these rules are worth
     * less when the coin is wild" from the whole record rather than from one
     * pair's corner of it.
     *
     * Volatility rather than a category list, because a hand-kept list of
     * "memecoins" is a label somebody typed while ATR as a share of price is a
     * fact - and a memecoin that has gone quiet should be treated as quiet.
     * Read from the signal's own atr_pct, so it is the volatility at the time
     * of the trade and not today's.
     *
     * ONE definition, called by training and by prediction. Two would be a
     * model fitted on one set of labels and asked about another, which is the
     * quietest way to make a model worse while every number still looks right.
     */
    public static function volBucket($atrPct): ?string
    {
        if ($atrPct === null || !is_numeric($atrPct) || (float)$atrPct <= 0) {
            return null;
        }
        $v = (float)$atrPct;
        if ($v < 0.5)  { return 'calm'; }
        if ($v < 1.0)  { return 'normal'; }
        if ($v < 2.0)  { return 'lively'; }
        return 'wild';
    }

    /**
     * Feature vector for one signal: a bias term, one binary feature per rule
     * per side, plus contextual features.
     */
    private static function features(array $reasons, string $verdict, array $context = []): array
    {
        $f = ['__bias' => 1.0];
        $dirSide = $verdict === 'BUY' ? 'bullish' : 'bearish';
        foreach ($reasons as $rs) {
            if (!is_array($rs) || empty($rs['key']) || empty($rs['side'])) {
                continue;
            }
            // "aligned with the verdict" vs "arguing against it" are different
            // pieces of information and get their own coefficients.
            $suffix = ($rs['side'] === $dirSide) ? ':for' : ':against';
            $f[$rs['key'] . $suffix] = 1.0;
        }
        if (isset($context['tf'])) {
            $f['tf:' . $context['tf']] = 1.0;
        }
        // Coin class, as volatility at the time of the trade.
        $vol = self::volBucket($context['atr_pct'] ?? null);
        if ($vol !== null) {
            $f['vol:' . $vol] = 1.0;
        }
        if (isset($context['grade']) && $context['grade'] !== null) {
            $f['grade:' . $context['grade']] = 1.0;
        }
        if (isset($context['adx']) && is_numeric($context['adx'])) {
            $adx = (float)$context['adx'];
            $f['adx:' . ($adx >= 30 ? 'strong' : ($adx >= 20 ? 'mid' : 'weak'))] = 1.0;
        }
        if (isset($context['hour'])) {
            $f['session:' . intdiv((int)$context['hour'], 6)] = 1.0;   // four 6h blocks
        }
        return $f;
    }

    /** Train on verified signals and persist the coefficients. */
    /**
     * @param int $limit 0 = use the configured window
     */
    public static function train(int $limit = 0): array
    {
        // A wider window than the rule tuner by design: this fits a
        // coefficient per feature rather than a multiplier per rule, so it
        // needs more rows to say anything. Same day cap applies, because the
        // question "how far back is still this market" has one answer, not
        // two.
        $limit = $limit > 0
            ? $limit
            : max(200, min(200000, (int)Database::setting('learn_model_rows', '3000')));
        [, $days] = Outcomes::learnWindow();
        $where = "outcome IN ('confirmed','invalid') AND `signal` != 'NEUTRAL'";
        if ($days > 0) {
            $where .= ' AND closed_at >= ' . (time() - $days * 86400);
        }
        $rows = Database::pdo()->query(
            "SELECT `signal`, tf, reasons, indicators, outcome, outcome_r, created_at, atr_pct FROM signals
             WHERE " . $where . "
             ORDER BY created_at DESC LIMIT " . (int)$limit
        )->fetchAll();

        $samples = [];
        foreach ($rows as $row) {
            $reasons = json_decode((string)$row['reasons'], true);
            if (!is_array($reasons) || !$reasons) {
                continue;
            }
            $inds = json_decode((string)$row['indicators'], true);
            $samples[] = [
                'x' => self::features($reasons, (string)$row['signal'], [
                    'tf' => (string)$row['tf'],
                    'grade' => is_array($inds) ? ($inds['grade'] ?? null) : null,
                    'adx' => is_array($inds) ? ($inds['adx'] ?? null) : null,
                    'hour' => (int)gmdate('G', (int)$row['created_at']),
                    // The same column the engine will hand it at decision
                    // time, bucketed by the same function.
                    'atr_pct' => $row['atr_pct'] ?? null,
                ]),
                // WHAT THE MODEL IS BEING ASKED TO PREDICT.
                //
                // This was 'reached a target label'. The model then learned to
                // pick setups that touch the first target, which is not the
                // same as setups that pay: a scale-out plan can touch it and
                // hand the rest back on the stop, and that trade was being
                // held up as a positive example. Trained on money now, the
                // same definition the track record and every counter uses.
                'y' => Outcomes::isWin($row) ? 1.0 : 0.0,
            ];
        }

        $n = count($samples);
        if ($n < self::MIN_SAMPLES) {
            $report = ['trained' => false, 'samples' => $n, 'need' => self::MIN_SAMPLES, 'at' => time()];
            Database::setSetting('learned_model_report', json_encode($report));
            return $report;
        }

        // Hold out the most recent 25% so accuracy is measured out of sample.
        //
        // $samples is ORDER BY created_at DESC - index 0 is the newest row,
        // not the oldest. array_slice(0, $split) on a newest-first list is
        // the newest portion, not the oldest one: this used to train on the
        // newest 75% and "validate" on the oldest 25%, backwards from what
        // the comment says and from ordinary walk-forward practice (train on
        // the past, validate on data that comes later). The accuracy gate
        // below decides whether the learned model is allowed to vote on live
        // confidence at all - scored against the wrong slice, it could pass a
        // model that doesn't actually generalise forward, or fail one that
        // does.
        $holdout = (int)floor($n * 0.25);
        $test  = array_slice($samples, 0, $holdout);
        $train = array_slice($samples, $holdout);

        $w = [];
        foreach ($train as $s) {
            foreach ($s['x'] as $k => $_) {
                $w[$k] ??= 0.0;
            }
        }
        $m = count($train);
        for ($iter = 0; $iter < self::ITERATIONS; $iter++) {
            $grad = [];
            foreach ($train as $s) {
                $z = 0.0;
                foreach ($s['x'] as $k => $v) {
                    $z += ($w[$k] ?? 0.0) * $v;
                }
                $err = self::sigmoid($z) - $s['y'];
                foreach ($s['x'] as $k => $v) {
                    $grad[$k] = ($grad[$k] ?? 0.0) + $err * $v;
                }
            }
            foreach ($w as $k => $wv) {
                $g = ($grad[$k] ?? 0.0) / $m;
                if ($k !== '__bias') {
                    $g += self::L2 * $wv;   // never regularise the intercept
                }
                $w[$k] = $wv - self::LEARNING_RATE * $g;
            }
        }

        // Out-of-sample quality: accuracy plus Brier score (calibration).
        $correct = 0;
        $brier = 0.0;
        foreach ($test as $s) {
            $p = self::predictWith($w, $s['x']);
            $brier += ($p - $s['y']) ** 2;
            if (($p >= 0.5 ? 1.0 : 0.0) === $s['y']) {
                $correct++;
            }
        }
        $testN = max(1, count($test));
        $baseRate = array_sum(array_column($samples, 'y')) / $n;

        $model = [
            'weights' => array_map(fn($v) => round($v, 5), $w),
            'trained_at' => time(),
            'samples' => $n,
            'test_n' => count($test),
            'accuracy' => round($correct / $testN, 4),
            'brier' => round($brier / $testN, 4),
            'base_rate' => round($baseRate, 4),
        ];
        Database::setSetting('learned_model', json_encode($model));
        $report = $model;
        unset($report['weights']);
        $report['trained'] = true;
        $report['features'] = count($w);
        Database::setSetting('learned_model_report', json_encode($report));
        return $report;
    }

    private static function sigmoid(float $z): float
    {
        if ($z < -30) {
            return 1e-13;
        }
        if ($z > 30) {
            return 1 - 1e-13;
        }
        return 1 / (1 + exp(-$z));
    }

    private static function predictWith(array $w, array $x): float
    {
        $z = 0.0;
        foreach ($x as $k => $v) {
            $z += ($w[$k] ?? 0.0) * $v;
        }
        return self::sigmoid($z);
    }

    /** Cached model, or null when untrained/disabled. */
    private static function model(): ?array
    {
        if (Database::setting('learned_model_enabled', '1') !== '1') {
            return null;
        }
        static $cached = false;
        if ($cached === false) {
            $m = json_decode(Database::setting('learned_model', ''), true);
            $cached = (is_array($m) && !empty($m['weights'])) ? $m : null;
        }
        return $cached;
    }

    /**
     * P(win) for a live analysis, or null when the model cannot speak yet.
     * A model that does not beat the base rate out of sample is ignored -
     * a confident-sounding number worse than "always guess the average"
     * would be actively harmful.
     *
     * SHRUNK TOWARD THE BASE RATE, LIKE EVERY OTHER CONFIDENCE TIER.
     *
     * The calibrated-quality tier and the legacy |score| tier both publish a
     * Wilson lower bound - a thin bucket cannot boast, because the bound
     * widens as samples shrink. This tier used to skip that entirely: a raw
     * logistic-regression point estimate, published unconditionally the
     * moment it existed, with no equivalent discount for how little the
     * out-of-sample test that validated the model actually proved. A model
     * whose accuracy just barely cleared the base rate on 40 held-out trades
     * is not owed the same trust as one that cleared it on 400, even though
     * both would return the exact same probability for the same input.
     *
     * Pulled toward the model's own base rate by n/(n+k) of the way, where n
     * is the out-of-sample count that validated THIS trained model (test_n,
     * from LearnedModel::train()) - the same shape Outcomes::shrinkage() uses
     * for per-coin multipliers, applied here to the model's confidence in
     * itself rather than to a rule weight.
     */
    public static function predict(array $reasons, string $verdict, array $context = []): ?float
    {
        $m = self::model();
        if ($m === null || $verdict === 'NEUTRAL') {
            return null;
        }
        $baseline = max((float)($m['base_rate'] ?? 0.5), 1 - (float)($m['base_rate'] ?? 0.5));
        if ((float)($m['accuracy'] ?? 0) < $baseline) {
            return null;
        }
        $p = self::predictWith($m['weights'], self::features($reasons, $verdict, $context));
        $testN = max(0, (int)($m['test_n'] ?? 0));
        $k = max(1, (int)Database::setting('learned_model_shrink_k', '150'));
        $trust = $testN / ($testN + $k);
        $base = (float)($m['base_rate'] ?? 0.5);
        return $base + $trust * ($p - $base);
    }

    /** Status for the admin panel. */
    public static function status(): array
    {
        $r = json_decode(Database::setting('learned_model_report', ''), true);
        return is_array($r) ? $r : ['trained' => false, 'samples' => 0, 'need' => self::MIN_SAMPLES];
    }

    /**
     * The most influential coefficients, for the admin to inspect what the
     * model actually learned. A rule whose learned coefficient contradicts its
     * hand-set weight is worth a human look.
     */
    public static function topFeatures(int $limit = 20): array
    {
        $m = self::model();
        if ($m === null) {
            return [];
        }
        $w = $m['weights'];
        unset($w['__bias']);
        uasort($w, fn($a, $b) => abs($b) <=> abs($a));
        return array_slice($w, 0, $limit, true);
    }
}
