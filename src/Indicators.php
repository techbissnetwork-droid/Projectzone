<?php
declare(strict_types=1);

namespace SignalMasterAi;

/**
 * Pure-PHP technical indicator library.
 * All methods take plain float arrays and return arrays aligned with the
 * input (leading values that cannot be computed are null).
 */
class Indicators
{
    /** Simple Moving Average */
    public static function sma(array $values, int $period): array
    {
        $out = array_fill(0, count($values), null);
        $sum = 0.0;
        foreach ($values as $i => $v) {
            $sum += $v;
            if ($i >= $period) {
                $sum -= $values[$i - $period];
            }
            if ($i >= $period - 1) {
                $out[$i] = $sum / $period;
            }
        }
        return $out;
    }

    /** Exponential Moving Average */
    public static function ema(array $values, int $period): array
    {
        $n = count($values);
        $out = array_fill(0, $n, null);
        if ($n < $period) {
            return $out;
        }
        $k = 2 / ($period + 1);
        // Seed with SMA of the first $period values
        $seed = array_sum(array_slice($values, 0, $period)) / $period;
        $out[$period - 1] = $seed;
        for ($i = $period; $i < $n; $i++) {
            $out[$i] = $values[$i] * $k + $out[$i - 1] * (1 - $k);
        }
        return $out;
    }

    /** Relative Strength Index (Wilder smoothing) */
    public static function rsi(array $closes, int $period = 14): array
    {
        $n = count($closes);
        $out = array_fill(0, $n, null);
        if ($n <= $period) {
            return $out;
        }
        $avgGain = 0.0;
        $avgLoss = 0.0;
        for ($i = 1; $i <= $period; $i++) {
            $diff = $closes[$i] - $closes[$i - 1];
            if ($diff >= 0) { $avgGain += $diff; } else { $avgLoss -= $diff; }
        }
        $avgGain /= $period;
        $avgLoss /= $period;
        $out[$period] = $avgLoss == 0.0 ? 100.0 : 100 - 100 / (1 + $avgGain / $avgLoss);
        for ($i = $period + 1; $i < $n; $i++) {
            $diff = $closes[$i] - $closes[$i - 1];
            $gain = $diff > 0 ? $diff : 0.0;
            $loss = $diff < 0 ? -$diff : 0.0;
            $avgGain = ($avgGain * ($period - 1) + $gain) / $period;
            $avgLoss = ($avgLoss * ($period - 1) + $loss) / $period;
            $out[$i] = $avgLoss == 0.0 ? 100.0 : 100 - 100 / (1 + $avgGain / $avgLoss);
        }
        return $out;
    }

    /** MACD: returns ['macd' => [], 'signal' => [], 'hist' => []] */
    public static function macd(array $closes, int $fast = 12, int $slow = 26, int $signal = 9): array
    {
        $n = count($closes);
        $emaFast = self::ema($closes, $fast);
        $emaSlow = self::ema($closes, $slow);
        $macd = array_fill(0, $n, null);
        for ($i = 0; $i < $n; $i++) {
            if ($emaFast[$i] !== null && $emaSlow[$i] !== null) {
                $macd[$i] = $emaFast[$i] - $emaSlow[$i];
            }
        }
        // Signal line = EMA of the macd values (skip leading nulls)
        $start = $slow - 1;
        $macdVals = array_slice($macd, $start);
        $sigVals = self::ema($macdVals, $signal);
        $sig = array_fill(0, $n, null);
        foreach ($sigVals as $i => $v) {
            $sig[$start + $i] = $v;
        }
        $hist = array_fill(0, $n, null);
        for ($i = 0; $i < $n; $i++) {
            if ($macd[$i] !== null && $sig[$i] !== null) {
                $hist[$i] = $macd[$i] - $sig[$i];
            }
        }
        return ['macd' => $macd, 'signal' => $sig, 'hist' => $hist];
    }

    /** Bollinger Bands: ['upper' => [], 'middle' => [], 'lower' => []] */
    public static function bollinger(array $closes, int $period = 20, float $mult = 2.0): array
    {
        $n = count($closes);
        $middle = self::sma($closes, $period);
        $upper = array_fill(0, $n, null);
        $lower = array_fill(0, $n, null);
        for ($i = $period - 1; $i < $n; $i++) {
            $slice = array_slice($closes, $i - $period + 1, $period);
            $mean = $middle[$i];
            $var = 0.0;
            foreach ($slice as $v) {
                $var += ($v - $mean) ** 2;
            }
            $sd = sqrt($var / $period);
            $upper[$i] = $mean + $mult * $sd;
            $lower[$i] = $mean - $mult * $sd;
        }
        return ['upper' => $upper, 'middle' => $middle, 'lower' => $lower];
    }

    /** Stochastic oscillator: ['k' => [], 'd' => []] */
    public static function stochastic(array $highs, array $lows, array $closes, int $kPeriod = 14, int $dPeriod = 3): array
    {
        $n = count($closes);
        $k = array_fill(0, $n, null);
        for ($i = $kPeriod - 1; $i < $n; $i++) {
            $hh = max(array_slice($highs, $i - $kPeriod + 1, $kPeriod));
            $ll = min(array_slice($lows, $i - $kPeriod + 1, $kPeriod));
            $k[$i] = $hh == $ll ? 50.0 : (($closes[$i] - $ll) / ($hh - $ll)) * 100;
        }
        // %D = SMA(%K)
        $d = array_fill(0, $n, null);
        for ($i = $kPeriod - 1 + $dPeriod - 1; $i < $n; $i++) {
            $sum = 0.0;
            $ok = true;
            for ($j = 0; $j < $dPeriod; $j++) {
                if ($k[$i - $j] === null) { $ok = false; break; }
                $sum += $k[$i - $j];
            }
            if ($ok) {
                $d[$i] = $sum / $dPeriod;
            }
        }
        return ['k' => $k, 'd' => $d];
    }

    /** Average True Range (Wilder smoothing) */
    public static function atr(array $highs, array $lows, array $closes, int $period = 14): array
    {
        $n = count($closes);
        $out = array_fill(0, $n, null);
        if ($n <= $period) {
            return $out;
        }
        $trs = [0.0];
        for ($i = 1; $i < $n; $i++) {
            $trs[$i] = max(
                $highs[$i] - $lows[$i],
                abs($highs[$i] - $closes[$i - 1]),
                abs($lows[$i] - $closes[$i - 1])
            );
        }
        $atr = array_sum(array_slice($trs, 1, $period)) / $period;
        $out[$period] = $atr;
        for ($i = $period + 1; $i < $n; $i++) {
            $atr = ($atr * ($period - 1) + $trs[$i]) / $period;
            $out[$i] = $atr;
        }
        return $out;
    }

    /**
     * ADX with directional indicators (Wilder).
     * Returns ['adx' => [], 'plus_di' => [], 'minus_di' => []].
     */
    public static function adx(array $highs, array $lows, array $closes, int $period = 14): array
    {
        $n = count($closes);
        $adx = array_fill(0, $n, null);
        $pdi = array_fill(0, $n, null);
        $mdi = array_fill(0, $n, null);
        if ($n <= $period * 2) {
            return ['adx' => $adx, 'plus_di' => $pdi, 'minus_di' => $mdi];
        }
        $trS = 0.0; $pdmS = 0.0; $mdmS = 0.0;
        $dxs = [];
        for ($i = 1; $i < $n; $i++) {
            $upMove = $highs[$i] - $highs[$i - 1];
            $downMove = $lows[$i - 1] - $lows[$i];
            $pdm = ($upMove > $downMove && $upMove > 0) ? $upMove : 0.0;
            $mdm = ($downMove > $upMove && $downMove > 0) ? $downMove : 0.0;
            $tr = max(
                $highs[$i] - $lows[$i],
                abs($highs[$i] - $closes[$i - 1]),
                abs($lows[$i] - $closes[$i - 1])
            );
            if ($i <= $period) {
                $trS += $tr; $pdmS += $pdm; $mdmS += $mdm;
                if ($i < $period) {
                    continue;
                }
            } else {
                $trS  = $trS  - $trS / $period  + $tr;
                $pdmS = $pdmS - $pdmS / $period + $pdm;
                $mdmS = $mdmS - $mdmS / $period + $mdm;
            }
            $p = $trS > 0 ? 100 * $pdmS / $trS : 0.0;
            $m = $trS > 0 ? 100 * $mdmS / $trS : 0.0;
            $pdi[$i] = $p;
            $mdi[$i] = $m;
            $dx = ($p + $m) > 0 ? 100 * abs($p - $m) / ($p + $m) : 0.0;
            $dxs[$i] = $dx;
            if ($i === $period * 2) {
                $adx[$i] = array_sum(array_slice($dxs, -$period, null, true)) / $period;
            } elseif ($i > $period * 2 && $adx[$i - 1] !== null) {
                $adx[$i] = ($adx[$i - 1] * ($period - 1) + $dx) / $period;
            }
        }
        return ['adx' => $adx, 'plus_di' => $pdi, 'minus_di' => $mdi];
    }

    /**
     * Ichimoku Cloud. Cloud values are already displaced: senkou_a/b[$i] is
     * the cloud boundary in effect AT bar $i (computed 26 bars earlier).
     * Returns ['tenkan' => [], 'kijun' => [], 'senkou_a' => [], 'senkou_b' => []].
     */
    public static function ichimoku(array $highs, array $lows): array
    {
        $n = count($highs);
        $mid = function (int $i, int $len) use ($highs, $lows): ?float {
            if ($i < $len - 1) {
                return null;
            }
            $hh = max(array_slice($highs, $i - $len + 1, $len));
            $ll = min(array_slice($lows, $i - $len + 1, $len));
            return ($hh + $ll) / 2;
        };
        $tenkan = []; $kijun = []; $sa = []; $sb = [];
        for ($i = 0; $i < $n; $i++) {
            $tenkan[$i] = $mid($i, 9);
            $kijun[$i]  = $mid($i, 26);
            $j = $i - 26;   // displacement: cloud at bar i was computed 26 bars ago
            if ($j >= 0 && $tenkan[$j] !== null && $kijun[$j] !== null) {
                $sa[$i] = ($tenkan[$j] + $kijun[$j]) / 2;
                $sb[$i] = $mid($j, 52);
            } else {
                $sa[$i] = null;
                $sb[$i] = null;
            }
        }
        return ['tenkan' => $tenkan, 'kijun' => $kijun, 'senkou_a' => $sa, 'senkou_b' => $sb];
    }

    /** Williams %R (-100..0) */
    public static function williamsR(array $highs, array $lows, array $closes, int $period = 14): array
    {
        $n = count($closes);
        $out = array_fill(0, $n, null);
        for ($i = $period - 1; $i < $n; $i++) {
            $hh = max(array_slice($highs, $i - $period + 1, $period));
            $ll = min(array_slice($lows, $i - $period + 1, $period));
            $out[$i] = $hh == $ll ? -50.0 : (($hh - $closes[$i]) / ($hh - $ll)) * -100;
        }
        return $out;
    }

    /** Commodity Channel Index */
    public static function cci(array $highs, array $lows, array $closes, int $period = 20): array
    {
        $n = count($closes);
        $tp = [];
        for ($i = 0; $i < $n; $i++) {
            $tp[$i] = ($highs[$i] + $lows[$i] + $closes[$i]) / 3;
        }
        $out = array_fill(0, $n, null);
        for ($i = $period - 1; $i < $n; $i++) {
            $slice = array_slice($tp, $i - $period + 1, $period);
            $mean = array_sum($slice) / $period;
            $dev = 0.0;
            foreach ($slice as $v) {
                $dev += abs($v - $mean);
            }
            $dev /= $period;
            $out[$i] = $dev > 0 ? ($tp[$i] - $mean) / (0.015 * $dev) : 0.0;
        }
        return $out;
    }

    /** Money Flow Index (volume-weighted RSI, 0..100) */
    public static function mfi(array $highs, array $lows, array $closes, array $volumes, int $period = 14): array
    {
        $n = count($closes);
        $out = array_fill(0, $n, null);
        $tp = [];
        for ($i = 0; $i < $n; $i++) {
            $tp[$i] = ($highs[$i] + $lows[$i] + $closes[$i]) / 3;
        }
        for ($i = $period; $i < $n; $i++) {
            $pos = 0.0; $neg = 0.0;
            for ($j = $i - $period + 1; $j <= $i; $j++) {
                $flow = $tp[$j] * $volumes[$j];
                if ($tp[$j] > $tp[$j - 1]) { $pos += $flow; } elseif ($tp[$j] < $tp[$j - 1]) { $neg += $flow; }
            }
            $out[$i] = $neg == 0.0 ? 100.0 : 100 - 100 / (1 + $pos / $neg);
        }
        return $out;
    }

    /** On-Balance Volume (cumulative) */
    public static function obv(array $closes, array $volumes): array
    {
        $n = count($closes);
        $out = [0.0];
        for ($i = 1; $i < $n; $i++) {
            $out[$i] = $out[$i - 1]
                + ($closes[$i] > $closes[$i - 1] ? $volumes[$i]
                : ($closes[$i] < $closes[$i - 1] ? -$volumes[$i] : 0.0));
        }
        return $out;
    }

    /**
     * Parabolic SAR. Returns ['sar' => [], 'trend' => []] where trend is
     * +1 (SAR below price) or -1 per bar.
     */
    public static function psar(array $highs, array $lows, float $step = 0.02, float $maxAf = 0.2): array
    {
        $n = count($highs);
        $sar = array_fill(0, $n, null);
        $trend = array_fill(0, $n, null);
        if ($n < 3) {
            return ['sar' => $sar, 'trend' => $trend];
        }
        $up = $highs[1] >= $highs[0];
        $af = $step;
        $ep = $up ? $highs[1] : $lows[1];
        $cur = $up ? $lows[0] : $highs[0];
        for ($i = 2; $i < $n; $i++) {
            $cur = $cur + $af * ($ep - $cur);
            if ($up) {
                $cur = min($cur, $lows[$i - 1], $lows[$i - 2]);
                if ($lows[$i] < $cur) {           // flip to downtrend
                    $up = false; $cur = $ep; $ep = $lows[$i]; $af = $step;
                } elseif ($highs[$i] > $ep) {
                    $ep = $highs[$i]; $af = min($maxAf, $af + $step);
                }
            } else {
                $cur = max($cur, $highs[$i - 1], $highs[$i - 2]);
                if ($highs[$i] > $cur) {          // flip to uptrend
                    $up = true; $cur = $ep; $ep = $highs[$i]; $af = $step;
                } elseif ($lows[$i] < $ep) {
                    $ep = $lows[$i]; $af = min($maxAf, $af + $step);
                }
            }
            $sar[$i] = $cur;
            $trend[$i] = $up ? 1 : -1;
        }
        return ['sar' => $sar, 'trend' => $trend];
    }

    /**
     * SuperTrend. Returns ['trend' => []] with +1/-1 per bar.
     */
    public static function supertrend(array $highs, array $lows, array $closes, int $period = 10, float $mult = 3.0): array
    {
        $n = count($closes);
        $atr = self::atr($highs, $lows, $closes, $period);
        $trend = array_fill(0, $n, null);
        $upper = null; $lower = null; $dir = 1;
        for ($i = 0; $i < $n; $i++) {
            if ($atr[$i] === null) {
                continue;
            }
            $mid = ($highs[$i] + $lows[$i]) / 2;
            $bu = $mid + $mult * $atr[$i];
            $bl = $mid - $mult * $atr[$i];
            // Adopt the fresh basic band when it tightens the trailing stop or
            // price has broken through the previous one; otherwise HOLD the
            // previous final band - a trailing stop that is allowed to loosen
            // while the trend is intact is not trailing anything. This used to
            // be inverted: min()/max() (hold/tighten) sat in the adopt branch
            // and the bare fresh value sat in the hold branch, so the band
            // loosened on ordinary bars and only "reset" when it should have
            // held - producing wrong, early/late supertrend_dir flips right
            // after sustained trends, exactly where a trend-following read
            // should be most reliable.
            $upper = ($upper !== null && !($bu < $upper || $closes[$i - 1] > $upper)) ? $upper : $bu;
            $lower = ($lower !== null && !($bl > $lower || $closes[$i - 1] < $lower)) ? $lower : $bl;
            if ($dir === 1 && $closes[$i] < $lower) {
                $dir = -1;
            } elseif ($dir === -1 && $closes[$i] > $upper) {
                $dir = 1;
            }
            $trend[$i] = $dir;
        }
        return ['trend' => $trend];
    }

    /**
     * Donchian channel of the PRIOR $period bars (excludes the current bar,
     * so a close above 'upper' is a genuine breakout).
     * Returns ['upper' => [], 'lower' => []].
     */
    public static function donchian(array $highs, array $lows, int $period = 20): array
    {
        $n = count($highs);
        $up = array_fill(0, $n, null);
        $lo = array_fill(0, $n, null);
        for ($i = $period; $i < $n; $i++) {
            $up[$i] = max(array_slice($highs, $i - $period, $period));
            $lo[$i] = min(array_slice($lows, $i - $period, $period));
        }
        return ['upper' => $up, 'lower' => $lo];
    }

    /**
     * Swing points: indexes of local highs/lows using a lookback window.
     * Returns ['highs' => [idx...], 'lows' => [idx...]].
     *
     * $right is the number of confirming bars required on the RIGHT side. The
     * classic symmetric form ($right = $window) is correct for structure
     * sequencing, but it can never see a swing newer than $window bars - on
     * 15m that is over an hour of blindness for every level-proximity rule.
     * Pass a smaller $right for provisional swings (see swingsTiered()).
     */
    public static function swings(array $highs, array $lows, int $window = 5, ?int $right = null): array
    {
        $n = count($highs);
        $right ??= $window;
        $sh = [];
        $sl = [];
        for ($i = $window; $i < $n - $right; $i++) {
            $isHigh = true;
            $isLow = true;
            for ($j = $i - $window; $j <= $i + $right; $j++) {
                if ($highs[$j] > $highs[$i]) { $isHigh = false; }
                if ($lows[$j] < $lows[$i])   { $isLow  = false; }
                if (!$isHigh && !$isLow) { break; }
            }
            if ($isHigh) { $sh[] = $i; }
            if ($isLow)  { $sl[] = $i; }
        }
        return ['highs' => $sh, 'lows' => $sl];
    }

    /**
     * Two-tier swing detection.
     *
     *   confirmed - symmetric window on both sides. Reliable, but always at
     *               least $window bars stale. Used for structure sequencing
     *               (higher highs / lower lows) and divergence anchors.
     *   active    - the confirmed set plus provisional swings that only have
     *               $right bars of confirmation and clear an ATR-significance
     *               filter. Used for "price is near support/resistance" and
     *               for structure-aware stop placement, where a level from an
     *               hour ago is worse than useless.
     *
     * Returns ['highs','lows','active_highs','active_lows'].
     */
    public static function swingsTiered(array $highs, array $lows, int $window = 5, int $right = 2, ?array $atr = null): array
    {
        $confirmed = self::swings($highs, $lows, $window, $window);
        $loose     = self::swings($highs, $lows, $window, $right);
        $n = count($highs);

        // Significance filter: a provisional pivot must stand out from the
        // local range by a fraction of ATR, otherwise every wiggle qualifies.
        $minMove = 0.0;
        if ($atr !== null) {
            for ($i = $n - 1; $i >= 0; $i--) {
                if (($atr[$i] ?? null) !== null) {
                    $minMove = 0.5 * (float)$atr[$i];
                    break;
                }
            }
        }
        $significant = function (int $idx, bool $isHigh) use ($highs, $lows, $minMove, $window, $n): bool {
            if ($minMove <= 0) {
                return true;
            }
            $from = max(0, $idx - $window);
            $to   = min($n - 1, $idx + $window);
            $slice = $isHigh
                ? array_slice($lows, $from, $to - $from + 1)
                : array_slice($highs, $from, $to - $from + 1);
            if (!$slice) {
                return true;
            }
            return $isHigh
                ? ($highs[$idx] - min($slice)) >= $minMove
                : (max($slice) - $lows[$idx]) >= $minMove;
        };

        $activeHighs = $confirmed['highs'];
        foreach ($loose['highs'] as $idx) {
            if (!in_array($idx, $activeHighs, true) && $significant($idx, true)) {
                $activeHighs[] = $idx;
            }
        }
        $activeLows = $confirmed['lows'];
        foreach ($loose['lows'] as $idx) {
            if (!in_array($idx, $activeLows, true) && $significant($idx, false)) {
                $activeLows[] = $idx;
            }
        }
        sort($activeHighs);
        sort($activeLows);

        return [
            'highs'        => $confirmed['highs'],
            'lows'         => $confirmed['lows'],
            'active_highs' => $activeHighs,
            'active_lows'  => $activeLows,
        ];
    }

    /**
     * Rolling VWAP over a fixed number of bars (session-less approximation).
     * VWAP is the reference institutional desks trade around; price above it
     * with rising slope is a materially different context from price below it.
     */
    public static function vwap(array $highs, array $lows, array $closes, array $volumes, int $period = 20): array
    {
        $n = count($closes);
        $out = array_fill(0, $n, null);
        $pv = 0.0;
        $vv = 0.0;
        $pvHist = [];
        $vHist = [];
        for ($i = 0; $i < $n; $i++) {
            $tp = ($highs[$i] + $lows[$i] + $closes[$i]) / 3;
            $pvHist[$i] = $tp * $volumes[$i];
            $vHist[$i] = $volumes[$i];
            $pv += $pvHist[$i];
            $vv += $vHist[$i];
            if ($i >= $period) {
                $pv -= $pvHist[$i - $period];
                $vv -= $vHist[$i - $period];
            }
            if ($i >= $period - 1) {
                $out[$i] = $vv > 0 ? $pv / $vv : null;
            }
        }
        return $out;
    }

    /**
     * Anchored VWAP from a fixed bar index (typically the last significant
     * swing). Values before the anchor are null. This is the level swing
     * traders actually watch after a pivot: reclaiming it flips control.
     */
    public static function anchoredVwap(array $highs, array $lows, array $closes, array $volumes, int $anchor): array
    {
        $n = count($closes);
        $out = array_fill(0, $n, null);
        if ($anchor < 0 || $anchor >= $n) {
            return $out;
        }
        $pv = 0.0;
        $vv = 0.0;
        for ($i = $anchor; $i < $n; $i++) {
            $tp = ($highs[$i] + $lows[$i] + $closes[$i]) / 3;
            $pv += $tp * $volumes[$i];
            $vv += $volumes[$i];
            $out[$i] = $vv > 0 ? $pv / $vv : null;
        }
        return $out;
    }

    /**
     * Volume profile over the last $lookback bars.
     *
     * Distributes each bar's volume across its high-low range into $bins price
     * buckets, then reports the Point of Control (highest-volume price) and
     * the Value Area (the contiguous band around POC holding 70% of volume).
     * These are far better support/resistance than raw swing highs and lows,
     * because they mark where business was actually done.
     *
     * Returns ['poc','vah','val','bins'] or null when there is not enough data.
     */
    public static function volumeProfile(array $highs, array $lows, array $volumes, int $lookback = 120, int $bins = 48): ?array
    {
        $n = count($highs);
        if ($n < 20) {
            return null;
        }
        $from = max(0, $n - $lookback);
        $hi = max(array_slice($highs, $from));
        $lo = min(array_slice($lows, $from));
        if (!is_finite($hi) || !is_finite($lo) || $hi <= $lo) {
            return null;
        }
        $step = ($hi - $lo) / $bins;
        $hist = array_fill(0, $bins, 0.0);
        for ($i = $from; $i < $n; $i++) {
            $barLo = $lows[$i];
            $barHi = $highs[$i];
            $vol = $volumes[$i];
            if ($vol <= 0 || $barHi < $barLo) {
                continue;
            }
            $b0 = (int)max(0, min($bins - 1, floor(($barLo - $lo) / $step)));
            $b1 = (int)max(0, min($bins - 1, floor(($barHi - $lo) / $step)));
            $span = $b1 - $b0 + 1;
            $share = $vol / $span;
            for ($b = $b0; $b <= $b1; $b++) {
                $hist[$b] += $share;
            }
        }
        $total = array_sum($hist);
        if ($total <= 0) {
            return null;
        }
        $pocBin = 0;
        foreach ($hist as $b => $v) {
            if ($v > $hist[$pocBin]) {
                $pocBin = $b;
            }
        }
        // Grow outward from the POC until 70% of volume is enclosed.
        $target = $total * 0.70;
        $acc = $hist[$pocBin];
        $lowBin = $pocBin;
        $highBin = $pocBin;
        while ($acc < $target && ($lowBin > 0 || $highBin < $bins - 1)) {
            $below = $lowBin > 0 ? $hist[$lowBin - 1] : -1;
            $above = $highBin < $bins - 1 ? $hist[$highBin + 1] : -1;
            if ($above >= $below) {
                $highBin++;
                $acc += max(0, $above);
            } else {
                $lowBin--;
                $acc += max(0, $below);
            }
        }
        $price = fn(int $bin): float => $lo + ($bin + 0.5) * $step;
        return [
            'poc' => $price($pocBin),
            'val' => $lo + $lowBin * $step,
            'vah' => $lo + ($highBin + 1) * $step,
            'bins' => $hist,
            'low' => $lo,
            'high' => $hi,
        ];
    }

    /**
     * Estimated liquidation clusters.
     *
     * Leveraged positions are forced closed at an arithmetically fixed
     * distance from their entry - a 25x long dies about 4% below it, a 50x
     * long about 2%. So every bar that traded volume seeded liquidation
     * prices at knowable levels, and where the projections from many bars
     * land on the same price, a cascade is waiting: stops there get filled by
     * a market order nobody chose to send. That is why price so often pokes
     * through an obvious level and immediately reverses.
     *
     * Paid heatmap feeds model this from real position data. With only public
     * candles the honest approximation is to treat each bar's typical price
     * as the average entry of the positions opened in it, weight it by that
     * bar's volume as a proxy for how much size was opened, and project.
     *
     * The part that matters is the sweep filter: a liquidation level is spent
     * once price trades through it. Without that the output is a list of
     * levels that were consumed days ago. Anything already reached is dropped,
     * so what remains is only fuel that is still sitting there.
     *
     * Returns clusters sorted strongest first, each with:
     *   price, side ('long' = fuel below, 'short' = fuel above),
     *   weight (0-1, relative to the strongest), distance_pct.
     */
    public static function liquidationClusters(
        array $highs,
        array $lows,
        array $closes,
        array $volumes,
        float $price,
        int $lookback = 240,
        array $leverages = [10, 25, 50, 100],
        int $bins = 240
    ): ?array {
        $n = count($closes);
        if ($n < 30 || $price <= 0) {
            return null;
        }
        $from = max(0, $n - $lookback);

        // How far price has travelled after each bar, so spent levels can be
        // discarded. Built once, backwards, rather than rescanned per bar.
        $minAfter = array_fill(0, $n, INF);
        $maxAfter = array_fill(0, $n, -INF);
        for ($i = $n - 2; $i >= 0; $i--) {
            $minAfter[$i] = min($minAfter[$i + 1], $lows[$i + 1]);
            $maxAfter[$i] = max($maxAfter[$i + 1], $highs[$i + 1]);
        }

        // Only levels close enough to be reachable are worth reporting.
        $window = 0.30;
        $lo = $price * (1 - $window);
        $hi = $price * (1 + $window);
        $step = ($hi - $lo) / $bins;
        if ($step <= 0) {
            return null;
        }
        $longHist  = array_fill(0, $bins, 0.0);
        $shortHist = array_fill(0, $bins, 0.0);
        // Weighted price sums, so a cluster can be reported at the centroid of
        // the liquidations inside it rather than at the middle of its bin. A
        // bin is necessarily wide enough to hold several leverage tiers, and
        // on a high-priced instrument its centre can sit hundreds of dollars
        // from where the orders actually are - which is the whole decision
        // when the number is being used to place a stop.
        $longPx  = array_fill(0, $bins, 0.0);
        $shortPx = array_fill(0, $bins, 0.0);

        for ($i = $from; $i < $n; $i++) {
            $vol = $volumes[$i] ?? 0;
            if ($vol <= 0) {
                continue;
            }
            $entry = ($highs[$i] + $lows[$i] + $closes[$i]) / 3;
            if ($entry <= 0) {
                continue;
            }
            foreach ($leverages as $lev) {
                if ($lev <= 1) {
                    continue;
                }
                // Longs opened here are liquidated below, shorts above.
                $longLiq  = $entry * (1 - 1 / $lev);
                $shortLiq = $entry * (1 + 1 / $lev);
                // Spent if price has already traded through it since.
                if ($longLiq >= $lo && $longLiq <= $hi && $minAfter[$i] > $longLiq) {
                    $b = (int)max(0, min($bins - 1, floor(($longLiq - $lo) / $step)));
                    $longHist[$b] += $vol;
                    $longPx[$b]   += $vol * $longLiq;
                }
                if ($shortLiq >= $lo && $shortLiq <= $hi && $maxAfter[$i] < $shortLiq) {
                    $b = (int)max(0, min($bins - 1, floor(($shortLiq - $lo) / $step)));
                    $shortHist[$b] += $vol;
                    $shortPx[$b]   += $vol * $shortLiq;
                }
            }
        }

        $peak = max(max($longHist), max($shortHist));
        if ($peak <= 0) {
            return null;
        }
        // A cluster has to stand out from its neighbours to be one; otherwise
        // a smoothly spread histogram reports a hundred "levels". It also has
        // to be a real share of the largest pool of fuel on the chart - the
        // local-peak test alone promotes a single stray bar to a "cluster"
        // whenever the side it sits on is otherwise empty.
        $floor = $peak * 0.15;
        $out = [];
        foreach (['long' => [$longHist, $longPx], 'short' => [$shortHist, $shortPx]] as $side => [$hist, $pxSum]) {
            $mean = array_sum($hist) / $bins;
            foreach ($hist as $b => $v) {
                if ($v <= 0 || $v < $mean * 2 || $v < $floor) {
                    continue;
                }
                $left  = $hist[$b - 1] ?? 0.0;
                $right = $hist[$b + 1] ?? 0.0;
                if ($v < $left || $v < $right) {
                    continue;               // not a local peak
                }
                $at = $pxSum[$b] / $v;
                $out[] = [
                    'price'         => round($at, 8),
                    'side'          => $side,
                    'weight'        => round($v / $peak, 4),
                    'distance_pct'  => round(($at - $price) / $price * 100, 2),
                ];
            }
        }
        if (!$out) {
            return null;
        }
        usort($out, fn($a, $b) => $b['weight'] <=> $a['weight']);
        return array_slice($out, 0, 12);
    }

    /**
     * Choppiness Index (0-100). Above ~61 the market is consolidating and
     * breakout/trend signals fail at a much higher rate; below ~38 it is
     * trending. The engine uses this as an explicit no-trade gate, which ADX
     * alone (which only dampens weights) never provided.
     */
    public static function choppiness(array $highs, array $lows, array $closes, int $period = 14): array
    {
        $n = count($closes);
        $out = array_fill(0, $n, null);
        $atr = self::atr($highs, $lows, $closes, 1);   // period-1 ATR == true range
        for ($i = $period; $i < $n; $i++) {
            $sumTr = 0.0;
            $ok = true;
            for ($j = $i - $period + 1; $j <= $i; $j++) {
                if (($atr[$j] ?? null) === null) { $ok = false; break; }
                $sumTr += $atr[$j];
            }
            if (!$ok || $sumTr <= 0) {
                continue;
            }
            $hh = max(array_slice($highs, $i - $period + 1, $period));
            $ll = min(array_slice($lows, $i - $period + 1, $period));
            $range = $hh - $ll;
            if ($range <= 0) {
                continue;
            }
            $out[$i] = 100 * log10($sumTr / $range) / log10($period);
        }
        return $out;
    }

    /**
     * Percentile rank (0-100) of the latest value within a trailing window.
     * Used for volatility regime: Bollinger width in its 20th percentile means
     * a squeeze (expansion likely), the 90th means exhaustion.
     */
    public static function percentileRank(array $series, int $lookback = 100): ?float
    {
        $vals = [];
        for ($i = count($series) - 1; $i >= 0 && count($vals) < $lookback; $i--) {
            if (($series[$i] ?? null) !== null) {
                $vals[] = (float)$series[$i];
            }
        }
        if (count($vals) < 10) {
            return null;
        }
        $current = $vals[0];
        $below = 0;
        foreach ($vals as $v) {
            if ($v < $current) {
                $below++;
            }
        }
        return round($below / count($vals) * 100, 1);
    }

    /** Bollinger bandwidth series: (upper - lower) / middle, as a percentage. */
    public static function bandwidth(array $closes, int $period = 20, float $mult = 2.0): array
    {
        $bb = self::bollinger($closes, $period, $mult);
        $n = count($closes);
        $out = array_fill(0, $n, null);
        for ($i = 0; $i < $n; $i++) {
            if ($bb['upper'][$i] !== null && $bb['middle'][$i] > 0) {
                $out[$i] = ($bb['upper'][$i] - $bb['lower'][$i]) / $bb['middle'][$i] * 100;
            }
        }
        return $out;
    }

    /**
     * Linear-regression slope of the last $period values, normalised by price
     * so it is comparable across symbols. Used to describe higher-timeframe
     * direction with more nuance than "EMA is above EMA".
     */
    public static function slope(array $values, int $period = 10): ?float
    {
        $vals = [];
        for ($i = count($values) - 1; $i >= 0 && count($vals) < $period; $i--) {
            if (($values[$i] ?? null) !== null) {
                $vals[] = (float)$values[$i];
            }
        }
        $m = count($vals);
        if ($m < 3) {
            return null;
        }
        $vals = array_reverse($vals);
        $sumX = 0.0; $sumY = 0.0; $sumXy = 0.0; $sumXx = 0.0;
        foreach ($vals as $x => $y) {
            $sumX += $x; $sumY += $y; $sumXy += $x * $y; $sumXx += $x * $x;
        }
        $denom = $m * $sumXx - $sumX * $sumX;
        if (abs($denom) < 1e-12) {
            return null;
        }
        $slope = ($m * $sumXy - $sumX * $sumY) / $denom;
        $mean = $sumY / $m;
        return $mean != 0.0 ? $slope / abs($mean) * 100 : null;   // % of price per bar
    }

    /**
     * How often has this instrument actually covered $distance, in the
     * direction being traded, within $bars bars?
     *
     * Every target on a signal card is a multiplier of ATR, and every time
     * stop is a bar count, and nothing in the engine ever checked whether the
     * two are compatible. A target three ATR away on a coin that typically
     * travels one and a half ATR inside the time stop is not an ambitious
     * target - it is a trade that expires by construction, and it will be
     * recorded as "went nowhere" rather than as the mistake it was.
     *
     * So the question is asked the only way it can be answered honestly: over
     * the recent history of this instrument, in what share of windows of the
     * same length did price reach that far? Measured on highs for a long and
     * lows for a short, because that is how a target actually gets hit -
     * intrabar, not at a close.
     *
     * Returns 0..1, or null when there is not enough history to say. Null must
     * be treated as "cannot say", never as "unreachable".
     */
    public static function reachProbability(array $highs, array $lows, array $closes,
                                            float $distance, int $bars, bool $isBuy,
                                            int $sample = 300): ?float
    {
        $n = count($closes);
        $bars = max(1, $bars);
        if ($distance <= 0 || $n < $bars + 30) {
            return null;
        }
        // Windows must be complete, so the last $bars bars cannot start one.
        $last = $n - $bars - 1;
        $first = max(0, $last - $sample + 1);
        $hits = 0;
        $seen = 0;
        for ($i = $first; $i <= $last; $i++) {
            $from = (float)$closes[$i];
            $best = $isBuy ? -INF : INF;
            for ($k = $i + 1; $k <= $i + $bars; $k++) {
                $v = $isBuy ? (float)$highs[$k] : (float)$lows[$k];
                if ($isBuy ? $v > $best : $v < $best) {
                    $best = $v;
                }
            }
            $travel = $isBuy ? $best - $from : $from - $best;
            $seen++;
            if ($travel >= $distance) {
                $hits++;
            }
        }
        return $seen >= 30 ? $hits / $seen : null;
    }
}
