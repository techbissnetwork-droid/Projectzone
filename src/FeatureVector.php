<?php
declare(strict_types=1);

namespace SignalMasterAi;

/**
 * The full numeric description of a setup at the moment it fired.
 *
 * The learner has until now seen a signal as a bag of rule keys: which rules
 * voted, and which side they took. That throws away almost everything that
 * made the setup what it was. "RSI oversold" fired identically at RSI 29 in a
 * quiet range and at RSI 4 in a collapse, and no model trained on the bag of
 * keys can tell those apart, because by the time it sees them the difference
 * has already been discarded.
 *
 * This class keeps the difference. Every value is either bounded by
 * construction (RSI, ADX, Stochastic) or expressed relative to something that
 * scales with the instrument - ATR for distances, percent for volatility - so
 * a feature means the same thing on a $64,000 pair as on a $0.16 one, and a
 * model can be trained across the whole universe instead of per symbol.
 *
 * Stored as JSON on the signal row at creation time rather than re-derived
 * later: candles get pruned by retention, indicator settings get retuned, and
 * a vector rebuilt six months on would describe a setup that never existed.
 */
class FeatureVector
{
    /**
     * Build the vector from a completed analysis.
     *
     * Missing inputs are omitted rather than zero-filled. Zero is a real RSI
     * and a real distance-from-VWAP; using it to mean "unknown" would teach a
     * model that unavailable data is a signal in itself.
     */
    public static function build(array $result, ?float $spreadPct = null): array
    {
        $ind = is_array($result['indicators'] ?? null) ? $result['indicators'] : [];
        $price = (float)($result['price'] ?? 0);
        $atr = self::num($ind['atr'] ?? null);
        $f = [];

        $put = static function (string $key, $value) use (&$f): void {
            if (is_numeric($value) && is_finite((float)$value)) {
                $f[$key] = round((float)$value, 6);
            }
        };

        // --- Context -----------------------------------------------------
        $f['tf'] = (string)($result['tf'] ?? '');
        $f['side'] = (string)($result['signal'] ?? 'NEUTRAL');
        $put('hour', (int)gmdate('G', (int)($result['generated'] ?? time())));
        $put('dow', (int)gmdate('w', (int)($result['generated'] ?? time())));

        // --- Oscillators: already bounded, kept raw ----------------------
        foreach (['rsi', 'stoch_k', 'willr', 'cci', 'mfi', 'adx', 'chop'] as $k) {
            $put($k, $ind[$k] ?? null);
        }

        // --- Volatility ---------------------------------------------------
        // ATR as a percentage of price is the one volatility number that is
        // comparable across every instrument, and it is what "volatility at
        // entry" means everywhere else in the system.
        if ($atr !== null && $price > 0) {
            $put('atr_pct', $atr / $price * 100);
        }
        $put('bw_pct', $ind['bw_pct'] ?? null);
        if ($spreadPct !== null) {
            $put('spread_pct', $spreadPct);
        }

        // --- Distances, in ATR --------------------------------------------
        // How far price sits from each reference, measured in units of the
        // instrument's own volatility. "Two ATR above the 200 EMA" carries the
        // same meaning on any pair; "$1,240 above" carries none.
        if ($atr > 0 && $price > 0) {
            foreach (['ema20', 'ema50', 'ema200', 'vwap', 'poc', 'vah', 'val',
                      'support', 'resistance'] as $k) {
                $v = self::num($ind[$k] ?? null);
                if ($v !== null) {
                    $put('d_' . $k, ($price - $v) / $atr);
                }
            }
            $macd = self::num($ind['macd'] ?? null);
            $macdSig = self::num($ind['macd_signal'] ?? null);
            if ($macd !== null && $macdSig !== null) {
                $put('macd_hist_atr', ($macd - $macdSig) / $atr);
            }
        }

        // --- Structure ----------------------------------------------------
        // Where price sits inside the Bollinger channel, 0 at the lower band
        // and 1 at the upper. Scale-free by construction and far more useful
        // than the two band prices on their own.
        $bu = self::num($ind['bb_upper'] ?? null);
        $bl = self::num($ind['bb_lower'] ?? null);
        if ($bu !== null && $bl !== null && $bu > $bl && $price > 0) {
            $put('bb_pos', ($price - $bl) / ($bu - $bl));
        }
        $e20 = self::num($ind['ema20'] ?? null);
        $e50 = self::num($ind['ema50'] ?? null);
        $e200 = self::num($ind['ema200'] ?? null);
        if ($e20 !== null && $e50 !== null && $e200 !== null) {
            // Stacked moving averages: +1 fully bullish, -1 fully bearish,
            // 0 tangled - the single most common definition of "in a trend".
            $put('ema_stack', $e20 > $e50 && $e50 > $e200 ? 1 : ($e20 < $e50 && $e50 < $e200 ? -1 : 0));
        }
        $put('supertrend', $ind['supertrend'] ?? null);

        // --- Higher-timeframe ladder ----------------------------------------
        // Both the net bias and each rung, because "everything above is down"
        // and "the daily is down but the 1h has turned" are different setups
        // that average to the same number.
        $put('mtf_bias', $ind['mtf_bias'] ?? null);
        foreach ((array)($ind['mtf']['frames'] ?? []) as $htf => $fr) {
            if (is_array($fr)) {
                $put('mtf_' . $htf, (int)($fr['dir'] ?? 0) * (float)($fr['strength'] ?? 0));
            }
        }

        // --- Market regime ---------------------------------------------------
        // The two axes and how sure the classification was. A model learning
        // from these should be able to find that a rule which pays in a grind
        // loses in a whipsaw, which is invisible from the rule key alone.
        $reg = is_array($ind['regime'] ?? null) ? $ind['regime'] : [];
        if ($reg) {
            $f['regime'] = (string)($reg['name'] ?? 'unknown');
            $put('regime_trendiness', $reg['trendiness'] ?? null);
            $put('regime_energy', $reg['energy'] ?? null);
            $put('regime_certainty', $reg['certainty'] ?? null);
        }

        // --- Smart-money structures -----------------------------------------
        // Recorded whether or not they were voted on. The engine only votes
        // these where the context lines up, but the learner should see the
        // ones it declined to act on too - "there was a bullish order block
        // 3 ATR away and we ignored it" is exactly the kind of thing worth
        // finding out was a mistake.
        $smc = is_array($ind['smc'] ?? null) ? $ind['smc'] : [];
        if ($smc) {
            $put('smc_zone_pct', $smc['zone_pct'] ?? null);
            $put('smc_structure_dir', $smc['structure_dir'] ?? null);
            foreach (['bos', 'choch', 'sweep', 'order_block', 'fvg', 'breaker'] as $k) {
                $s = $smc[$k] ?? null;
                if (!is_array($s)) {
                    continue;
                }
                // Direction and freshness for everything; distance and depth
                // where the structure has one.
                $put('smc_' . $k, (int)($s['dir'] ?? 0));
                $put('smc_' . $k . '_age', $s['age'] ?? null);
                foreach (['distance', 'depth', 'size'] as $extra) {
                    if (isset($s[$extra])) {
                        $put('smc_' . $k . '_' . $extra, $s[$extra]);
                    }
                }
            }
            foreach (['eq_highs', 'eq_lows'] as $k) {
                if (is_array($smc[$k] ?? null)) {
                    $put('smc_' . $k . '_distance', $smc[$k]['distance'] ?? null);
                }
            }
        }

        // --- Sentiment ----------------------------------------------------
        $put('sentiment_coin', $ind['sentiment_coin'] ?? null);
        $put('sentiment_market', $ind['sentiment_market'] ?? null);

        // --- The engine's own view ----------------------------------------
        $put('score', $result['score'] ?? null);
        $put('raw_score', $result['raw_score'] ?? null);
        $put('confidence', $result['confidence'] ?? null);
        // The combined quality and its six axes. conf_quality is what the
        // calibration map is built over, so it has to be stored with the
        // signal or the map can never be rebuilt.
        $put('conf_quality', $result['confidence_quality'] ?? null);
        foreach ((array)($result['confidence_dims'] ?? []) as $axis => $v) {
            $put('conf_' . $axis, $v);
        }
        $put('bull_cats', $result['bull_categories'] ?? null);
        $put('bear_cats', $result['bear_categories'] ?? null);
        $put('rules_evaluated', $result['rules_evaluated'] ?? null);
        if (isset($result['grade'])) {
            $f['grade'] = (string)$result['grade'];
        }

        // Per-category net contribution, after the correlation caps. A score
        // of 4 built from one category is a different setup from a 4 built
        // from five, and the total alone cannot tell them apart.
        foreach ((array)($result['categories'] ?? []) as $cat => $c) {
            if (is_array($c)) {
                $put('cat_' . $cat, $c['capped'] ?? null);
                if (!empty($c['clipped'])) {
                    $f['cat_' . $cat . '_clipped'] = 1;
                }
            }
        }

        // --- Vote census ---------------------------------------------------
        // Counted by the side each rule actually took, not by whether it
        // agreed with the verdict: a NEUTRAL result has no side to agree with,
        // and forcing one would record "9 rules against" for a setup where
        // nine rules were bullish and the engine simply declined to call it.
        $bull = 0;
        $bear = 0;
        $wBull = 0.0;
        $wBear = 0.0;
        $fired = [];
        foreach ((array)($result['reasons'] ?? []) as $rs) {
            if (!is_array($rs) || empty($rs['key']) || empty($rs['side'])) {
                continue;
            }
            $isBull = $rs['side'] === 'bullish';
            $fired[] = ($isBull ? '+' : '-') . $rs['key'];
            $w = abs((float)($rs['weight'] ?? 1));
            if ($isBull) {
                $bull++;
                $wBull += $w;
            } else {
                $bear++;
                $wBear += $w;
            }
        }
        $put('votes_bull', $bull);
        $put('votes_bear', $bear);
        $put('weight_bull', $wBull);
        $put('weight_bear', $wBear);
        if ($bull + $bear > 0) {
            // How one-sided the evidence was, independent of direction. A
            // setup 9 rules to 1 and a setup 9 to 8 can carry the same net
            // score, and they are not the same setup.
            $put('vote_agreement', abs($bull - $bear) / ($bull + $bear));
            // Signed against the verdict, only where a verdict exists.
            $side = (string)($result['signal'] ?? '');
            if ($side === 'BUY' || $side === 'SELL') {
                $for = $side === 'BUY' ? $bull : $bear;
                $against = $side === 'BUY' ? $bear : $bull;
                $put('votes_for', $for);
                $put('votes_against', $against);
                $put('weight_for', $side === 'BUY' ? $wBull : $wBear);
                $put('weight_against', $side === 'BUY' ? $wBear : $wBull);
                $put('vote_edge', ($for - $against) / ($for + $against));
            }
        }
        $f['fired'] = $fired;

        // --- The plan itself ------------------------------------------------
        $levels = is_array($result['levels'] ?? null) ? $result['levels'] : [];
        if ($levels) {
            $put('rr', $levels['rr'] ?? null);
            $entry = self::num($levels['entry'] ?? null);
            $stop = self::num($levels['stop_loss'] ?? null);
            if ($entry !== null && $stop !== null && $entry > 0) {
                $put('stop_pct', abs($entry - $stop) / $entry * 100);
                if ($atr > 0) {
                    $put('stop_atr', abs($entry - $stop) / $atr);
                }
            }
            $put('expires_bars', $levels['expires_bars'] ?? null);
            // Distance to the nearest estimated liquidation cluster, in R.
            // A target sitting the wrong side of a magnet is a different
            // proposition from one sitting in front of it.
            $put('liq_magnet_r', $levels['liq_magnet_r'] ?? null);
        }

        return $f;
    }

    /** Numeric value or null - json_decode leaves nulls and strings around. */
    private static function num($v): ?float
    {
        return is_numeric($v) ? (float)$v : null;
    }
}
