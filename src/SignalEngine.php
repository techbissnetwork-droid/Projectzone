<?php
declare(strict_types=1);

namespace SignalMasterAi;

/**
 * Knowledge-base-driven signal engine.
 *
 * Each rule stored in ta_knowledge encodes one piece of chart-reading
 * experience (momentum, trend, volatility, candlestick patterns, market
 * structure). The engine evaluates every enabled rule against the latest
 * candles, sums weight-adjusted votes, and emits BUY / SELL / NEUTRAL with a
 * confidence score and the list of human-readable reasons that fired.
 *
 * Output is educational chart commentary only - NOT financial advice.
 */
class SignalEngine
{
    private array $rules = [];
    private float $buyThreshold;
    private float $sellThreshold;
    private ?MarketData $md = null;
    private bool $dry = false;
    private ?string $btPrev = null;
    private array $tfMult = [];
    /** {symbol: {rule_key: multiplier}} - see Outcomes::applySymbolMultipliers(). */
    private array $symMult = [];
    private ?int $asOfMs = null;
    private array $categories = [];
    private array $profile = [];
    private int $htfDepth = 0;
    private bool $noStore = false;
    private ?array $levelOverride = null;
    private array $settingOverride = [];

    /** Every enabled rule, before quarantine - see applyQuarantine(). */
    private array $allRules = [];

    /**
     * Which overridden keys this instance actually read.
     *
     * AN OVERRIDE NOBODY READS IS A MEASUREMENT THAT LIES.
     *
     * The preset accuracy test hands the engine a preset's values and reports
     * what that preset would have done. If a key never reaches a decision the
     * report describes a configuration that was never tried, and it cannot say
     * so, because a setting that is ignored looks exactly like a setting that
     * made no difference. Measured here: of Selective's ten values, five were
     * inert in a replay - including its headline score threshold.
     *
     * So the engine records what it read, and every tool that overrides
     * settings can report what it handed over that was never looked at.
     */
    private array $cfgRead = [];

    /** Attach a MarketData source to enable higher-timeframe confluence. */
    public function withMarketData(MarketData $md): static
    {
        $this->md = $md;
        return $this;
    }

    /**
     * Apply a viewer's strategy profile: thresholds, disabled categories and
     * personal rule weights. This is what lets one member run the engine
     * conservatively while another runs it aggressively, off the same candles,
     * without the admin having to pick one setting for everybody.
     */
    public function withProfile(array $profile): static
    {
        $this->profile = $profile;
        if (isset($profile['buy_threshold']) && is_numeric($profile['buy_threshold'])) {
            $this->buyThreshold = (float)$profile['buy_threshold'];
        }
        if (isset($profile['sell_threshold']) && is_numeric($profile['sell_threshold'])) {
            $this->sellThreshold = (float)$profile['sell_threshold'];
        }
        foreach ((array)($profile['disabled_categories'] ?? []) as $cat) {
            unset($this->categories[$cat]);
        }
        return $this;
    }

    /**
     * Candles for a cross-series lookup (higher timeframe, BTC regime).
     *
     * In a backtest this must be served AS OF the simulated bar. Reading the
     * live table there let the higher-timeframe and Bitcoin-regime rules see
     * price action that had not happened yet, which is the classic way a
     * backtest reports an edge that does not exist.
     */
    private function htfCandles(string $symbol, string $tf): array
    {
        if ($this->md === null) {
            return [];
        }
        return $this->asOfMs !== null
            ? $this->md->candlesAsOf($symbol, $tf, $this->asOfMs)
            : $this->md->candles($symbol, $tf);
    }

    /** Recursion guard for the higher-timeframe engine pass. */
    private function withHtfDepth(int $d): static
    {
        $this->htfDepth = $d;
        return $this;
    }

    /**
     * Override the ATR level multipliers for this instance only.
     *
     * The level optimiser needs to try dozens of stop/target combinations. It
     * used to do that by writing each candidate into the settings table and
     * restoring afterwards - which meant an interrupted run (timeout, fatal,
     * deploy) left the LIVE engine using whatever combination was mid-flight,
     * because a `finally` block does not survive SIGTERM. Passing the
     * candidate through the object touches no global state at all.
     */
    public function withLevels(?array $levels): static
    {
        $this->levelOverride = $levels;
        return $this;
    }

    /**
     * Override arbitrary engine settings for this instance only.
     *
     * Used by the A/B comparison so "what if the threshold were higher?" can
     * be answered without writing the challenger configuration into the live
     * settings table and hoping the restore runs.
     */
    public function withSettingOverrides(array $settings): static
    {
        $this->settingOverride = $settings;
        // Quarantine was applied in the constructor against the live setting,
        // because these overrides did not exist yet. Re-apply them.
        $this->applyQuarantine();
        // Thresholds are resolved in the constructor, so re-resolve them here.
        //
        // WHICH THRESHOLD KEY BITES DEPENDS ON THE SCORING MODE, AND ONLY ONE
        // OF THE TWO PAIRS IS EVER READ.
        //
        // In 'category' mode - the shipped default - the engine reads
        // cat_buy_threshold and never looks at buy_threshold. Every preset
        // shipped its threshold under the OTHER name, so "Selective: score
        // threshold 3.0" changed nothing whatsoever on a default install.
        // Measured: overriding buy_threshold to 99 left all 76 signals
        // standing, and cat_buy_threshold to 99 removed every one of them.
        if ($this->modeNow() === 'category') {
            $this->buyThreshold  = (float)$this->cfg('cat_buy_threshold', '3.0');
            $this->sellThreshold = (float)$this->cfg('cat_sell_threshold', '-3.0');
        } else {
            $this->buyThreshold  = (float)$this->cfg('buy_threshold', '2.0');
            $this->sellThreshold = (float)$this->cfg('sell_threshold', '-2.0');
        }
        return $this;
    }

    /**
     * Merge a symbol's stored engine overrides into this instance.
     *
     * A member profile is more specific than a coin setting, so anything the
     * profile already fixed wins.
     */
    private function applySymbolOverrides(string $symbol): void
    {
        static $cache = [];
        if (!array_key_exists($symbol, $cache)) {
            try {
                $stmt = Database::pdo()->prepare('SELECT engine_json FROM symbols WHERE symbol = ?');
                $stmt->execute([$symbol]);
                $raw = (string)($stmt->fetchColumn() ?: '');
                $stmt->closeCursor();
                $cache[$symbol] = $raw !== '' ? (json_decode($raw, true) ?: []) : [];
            } catch (\Throwable $e) {
                $cache[$symbol] = [];   // pre-migration column
            }
        }
        $ov = $cache[$symbol];
        if (!$ov) {
            return;
        }
        foreach ($ov as $k => $v) {
            if (!array_key_exists($k, $this->settingOverride)) {
                $this->settingOverride[$k] = $v;
            }
        }
        if (isset($ov['cat_buy_threshold']) && !isset($this->profile['buy_threshold'])) {
            $this->buyThreshold = (float)$ov['cat_buy_threshold'];
        }
        if (isset($ov['cat_sell_threshold']) && !isset($this->profile['sell_threshold'])) {
            $this->sellThreshold = (float)$ov['cat_sell_threshold'];
        }
    }

    /**
     * Overridden keys this run never consulted.
     *
     * @return array<int,string>
     */
    public function unusedOverrides(): array
    {
        return array_values(array_diff(
            array_keys($this->settingOverride),
            array_keys($this->cfgRead)
        ));
    }

    /** Setting lookup honouring this instance's overrides. */
    private function cfg(string $key, string $default = ''): string
    {
        if (array_key_exists($key, $this->settingOverride)) {
            $this->cfgRead[$key] = true;
            return (string)$this->settingOverride[$key];
        }
        return Database::setting($key, $default);
    }

    /**
     * Analyse without writing history. Used by the higher-timeframe pass, the
     * scanner preview and profile-customised member analyses, none of which
     * should pollute the shared audit trail with rows nobody asked for.
     */
    public function suppressStore(): static
    {
        $this->noStore = true;
        return $this;
    }

    /**
     * Backtest mode: nothing is stored, and inputs that only exist "now"
     * (news sentiment, calendar events, funding, Fear & Greed) are skipped
     * because their historical values are unknown - only pure chart rules
     * run, exactly as they would have on that candle.
     */
    public function dry(): static
    {
        $this->dry = true;
        return $this;
    }

    /** Backtest mode: the previous verdict, for hysteresis/flip tracking. */
    public function setPrevVerdict(?string $v): static
    {
        $this->btPrev = $v;
        return $this;
    }

    public function __construct()
    {
        $pdo = Database::pdo();
        foreach ($pdo->query('SELECT * FROM ta_knowledge WHERE enabled = 1') as $r) {
            $this->rules[$r['rule_key']] = $r;
        }
        // Quarantined rules: benched by the learning pass after persistent
        // negative expectancy. Tuning a bad rule's weight down still lets it
        // vote; this stops it voting at all until its probation is up and it
        // has had a chance to collect fresh evidence.
        // The unfiltered set is kept because the filter has to be applied
        // AGAIN once overrides arrive - see applyQuarantine().
        $this->allRules = $this->rules;
        $this->applyQuarantine();
        try {
            foreach ($pdo->query('SELECT * FROM ta_categories WHERE enabled = 1') as $c) {
                $this->categories[$c['category']] = $c;
            }
        } catch (\Throwable $e) {
            $this->categories = [];   // pre-migration install: falls back to sum mode
        }
        // Category scoring uses its own thresholds: capping changes the scale
        // of the score, so reusing the sum-mode numbers would silently loosen
        // the gate. Both sets are kept so switching modes is reversible.
        if ($this->modeNow() === 'category') {
            $this->buyThreshold  = (float)$this->cfg('cat_buy_threshold', '3.0');
            $this->sellThreshold = (float)$this->cfg('cat_sell_threshold', '-3.0');
        } else {
            $this->buyThreshold  = (float)$this->cfg('buy_threshold', '2.0');
            $this->sellThreshold = (float)$this->cfg('sell_threshold', '-2.0');
        }
        // Per-timeframe rule multipliers learned from verified outcomes.
        $this->tfMult = json_decode($this->cfg('tf_rule_mult', '{}'), true) ?: [];
        // Per-coin, shrunk toward the global weight by how much each coin has
        // proved - a coin with little history is silently identical to it.
        // One query for the whole table, cached for the request - the scan
        // runs this constructor per pair and per timeframe, and the old blob
        // was decoded from a cached setting every time for the same reason.
        $this->symMult = SymbolLearning::all();
    }

    /**
     * 'category' (correlation-capped, default) or 'sum' (legacy behaviour).
     *
     * The site-wide answer, for screens and for callers with no run in hand.
     * Inside a run use modeNow() - see the note there.
     */
    public static function scoringMode(): string
    {
        return Database::setting('scoring_mode', 'category') === 'sum' ? 'sum' : 'category';
    }

    /**
     * The mode THIS run is scoring in.
     *
     * A DIAL THE OVERRIDE LAYER COULD NOT REACH, AND THE ONE THAT DECIDES
     * WHICH THRESHOLDS ARE EVEN CONSULTED.
     *
     * Every threshold read below goes through cfg(), so a preset, a backtest
     * or an A/B challenger can move it. The mode did not: it was read with
     * Database::setting() directly, which skips settingOverride entirely. So
     * a run asking for sum mode got category mode and then had its cat_*
     * thresholds applied - the same family of failure as every preset
     * shipping its threshold under the name category mode does not read,
     * which is documented twenty lines above this.
     *
     * Found by the preset measurement's own unused-override report, which is
     * what that report is for.
     */
    private function modeNow(): string
    {
        return $this->cfg('scoring_mode', 'category') === 'sum' ? 'sum' : 'category';
    }

    /**
     * Bench the rules the learning pass has quarantined.
     *
     * AND WHY IT IS CALLED TWICE.
     *
     * This runs in the constructor, where settingOverride is necessarily
     * still empty - the caller has not had a chance to set it yet. Reading
     * the switch through cfg() there is therefore not enough on its own: the
     * answer would always be the live site's, which is exactly the bug.
     *
     * So the unfiltered rule set is kept and the filter re-applied the moment
     * overrides land. Rebuilt from allRules rather than un-deleting, because
     * an override that switches quarantine OFF has to bring the benched rules
     * back, and you cannot restore what you have already thrown away.
     */
    private function applyQuarantine(): void
    {
        $this->rules = $this->allRules;
        if ($this->cfg('quarantine_enabled', '1') !== '1') {
            return;
        }
        $q = (array)json_decode($this->cfg('quarantined_rules', '[]'), true);
        foreach (array_is_list($q) ? $q : array_keys($q) as $qk) {
            unset($this->rules[(string)$qk]);
        }
    }


    /**
     * Accuracy: drop the still-forming candle so every rule reads CLOSED bars.
     * A "breakout" or "engulfing candle" seen mid-bar often un-happens by the
     * close - analysing only completed candles removes that repaint noise.
     * Admin-toggleable (closed_candle_only).
     */
    /**
     * How closely two price series have been moving together lately.
     *
     * Pearson correlation of the two RETURN series, not of the prices. Two
     * prices that both drift upward correlate near +1 whatever they do day to
     * day, which would make every coin in a bull market look like it follows
     * Bitcoin perfectly - the exact mistake this exists to avoid. Returns
     * measure what is actually being asked: when BTC moves, does this move.
     *
     * Aligned on the most recent bars of both series, because the two lists
     * can be different lengths - a coin listed last month has fewer candles
     * than Bitcoin, and the overlap is the only part where the question means
     * anything. Null when there is not enough overlap to answer, which the
     * caller must treat as "unknown" rather than as "uncorrelated".
     *
     * @param array<int,float> $a
     * @param array<int,float> $b
     */
    public static function correlation(array $a, array $b, int $bars = 120): ?float
    {
        $ret = static function (array $c) use ($bars): array {
            $c = array_values(array_slice(array_map('floatval', $c), -($bars + 1)));
            $out = [];
            for ($i = 1, $n = count($c); $i < $n; $i++) {
                if ($c[$i - 1] > 0) {
                    $out[] = ($c[$i] - $c[$i - 1]) / $c[$i - 1];
                }
            }
            return $out;
        };
        $ra = $ret($a);
        $rb = $ret($b);
        $n = min(count($ra), count($rb));
        if ($n < 30) {
            return null;                       // too little overlap to mean anything
        }
        $ra = array_slice($ra, -$n);
        $rb = array_slice($rb, -$n);
        $ma = array_sum($ra) / $n;
        $mb = array_sum($rb) / $n;
        $sa = $sb = $sab = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $x = $ra[$i] - $ma;
            $y = $rb[$i] - $mb;
            $sa += $x * $x;
            $sb += $y * $y;
            $sab += $x * $y;
        }
        if ($sa <= 0.0 || $sb <= 0.0) {
            return null;                       // a flat series has no correlation
        }
        return max(-1.0, min(1.0, $sab / sqrt($sa * $sb)));
    }

    private function closedOnly(array $candles, string $tf): array
    {
        $n = count($candles);
        if ($n === 0 || $this->cfg('closed_candle_only', '1') !== '1') {
            return $candles;
        }
        $tfMs = (MarketData::TF_SECONDS[$tf] ?? 3600) * 1000;
        $last = $candles[$n - 1];
        if (isset($last['t']) && (float)$last['t'] + $tfMs > $this->nowMs()) {
            array_pop($candles);
        }
        return $candles;
    }

    /**
     * "Now" in milliseconds, anchored on the exchange clock when available.
     *
     * Deciding whether the newest bar is still forming used to compare against
     * the web server's own clock. A host drifting by minutes - routine on
     * cheap shared hosting - keeps a forming candle or drops a closed one, and
     * every rule in that pass reads the wrong bar with no visible symptom.
     *
     * In backtests the caller pins this to the simulated bar time instead, so
     * historical bars are always treated as closed.
     */
    private function nowMs(): float
    {
        if ($this->asOfMs !== null) {
            return (float)$this->asOfMs;
        }
        if ($this->md !== null) {
            try {
                return (float)$this->md->nowMs();
            } catch (\Throwable $e) {
                // fall through to the local clock
            }
        }
        return microtime(true) * 1000;
    }

    /**
     * Backtest mode: freeze the engine's notion of "now" at a simulated bar so
     * higher-timeframe lookups cannot see the future.
     */
    public function asOf(?int $ms): static
    {
        $this->asOfMs = $ms;
        return $this;
    }

    /**
     * @param array $candles list of ['t','o','h','l','c','v'], oldest first
     */
    public function analyse(string $symbol, string $tf, array $candles): array
    {
        // Per-symbol overrides: a single global threshold cannot suit both BTC
        // on the daily and a thin meme coin on 15m.
        $this->applySymbolOverrides($symbol);

        // Keep the unfiltered closes: rules read closed bars only, but the
        // chase guard needs to know where price is RIGHT NOW relative to the
        // entry the signal bar produced.
        $closesRaw = array_column($candles, 'c');
        $candles = $this->closedOnly($candles, $tf);
        $n = count($candles);
        if ($n < 60) {
            throw new \RuntimeException('Not enough candles for analysis (need at least 60)');
        }

        $opens  = array_column($candles, 'o');
        $highs  = array_column($candles, 'h');
        $lows   = array_column($candles, 'l');
        $closes = array_column($candles, 'c');
        $vols   = array_column($candles, 'v');
        $i = $n - 1;          // latest candle
        $price = $closes[$i];

        $rsi   = Indicators::rsi($closes);
        $macd  = Indicators::macd($closes);
        $ema20 = Indicators::ema($closes, 20);
        $ema50 = Indicators::ema($closes, 50);
        $ema200= Indicators::ema($closes, 200);
        $bb    = Indicators::bollinger($closes);
        $stoch = Indicators::stochastic($highs, $lows, $closes);
        $atr   = Indicators::atr($highs, $lows, $closes);
        $volSma= Indicators::sma($vols, 20);
        $swingT= Indicators::swingsTiered($highs, $lows, 5, 2, $atr);
        $swing = ['highs' => $swingT['highs'], 'lows' => $swingT['lows']];
        $adx   = Indicators::adx($highs, $lows, $closes);
        $ichi  = Indicators::ichimoku($highs, $lows);
        $willr = Indicators::williamsR($highs, $lows, $closes);
        $cci   = Indicators::cci($highs, $lows, $closes);
        $mfi   = Indicators::mfi($highs, $lows, $closes, $vols);
        $obv   = Indicators::obv($closes, $vols);
        $obvSma= Indicators::sma($obv, 20);
        $psar  = Indicators::psar($highs, $lows);
        $sTrend= Indicators::supertrend($highs, $lows, $closes);
        $don   = Indicators::donchian($highs, $lows);

        // Extra series for the rules added in this revision.
        $vwap  = Indicators::vwap($highs, $lows, $closes, $vols, 20);
        $chop  = Indicators::choppiness($highs, $lows, $closes);
        $bwSer = Indicators::bandwidth($closes);
        $bwPct = Indicators::percentileRank($bwSer, 120);
        $vProf = Indicators::volumeProfile($highs, $lows, $vols, 150);
        // Where forced selling and forced buying is still waiting. Used to
        // keep stops out of the path of a cascade and to name the magnet a
        // move is most likely reaching for.
        $liq = $this->cfg('liq_clusters_enabled', '1') === '1'
            ? Indicators::liquidationClusters(
                $highs, $lows, $closes, $vols, $price,
                max(60, (int)$this->cfg('liq_lookback_bars', '240')),
                array_values(array_filter(array_map(
                    'intval',
                    explode(',', $this->cfg('liq_leverages', '10,25,50,100'))
                ), fn(int $l): bool => $l > 1)) ?: [10, 25, 50, 100]
            )
            : null;

        $score = 0.0;
        $rawScore = 0.0;     // legacy uncapped sum, kept for comparison
        $maxScore = 0.0;
        $reasons = [];
        $catVotes = [];      // category => net weighted vote

        // ADX regime filter (accuracy): in a strong trend (ADX >= 30)
        // mean-reversion rules are half-weighted; in chop (ADX < 15)
        // trend-following rules are half-weighted. A veteran does not fade a
        // freight train, nor trend-follow a flat market.
        $adxNow = $adx['adx'][$i] ?? null;
        $regimeOn = $this->cfg('regime_filter_enabled', '1') === '1' && $adxNow !== null;

        // The two-axis regime read. The weight-halving above is one dimension
        // of a two-dimensional problem: ADX says whether price is going
        // somewhere, and says nothing about how hard it is moving while it
        // does. Both matter, and they matter to different things - the first
        // to which rules deserve their weight, the second to how wide a stop
        // has to be to survive being right. The policy this returns drives
        // thresholds, stop and target distances, required confirmations and
        // the time stop, scaled down towards no-op when the classification
        // itself is borderline.
        $regime = ['name' => 'unknown', 'trendiness' => null, 'energy' => null, 'certainty' => 0.0,
                   'policy' => ['threshold' => 1.0, 'stop' => 1.0, 'target' => 1.0,
                                'categories' => 0, 'time_stop' => 1.0, 'why' => '']];
        if ($this->cfg('regime_detect_enabled', '1') === '1') {
            try {
                $regime = Regime::detect($adxNow, $chop[$i] ?? null, $atr, $bwSer, $closes);
            } catch (\Throwable $e) {
                // An unclassified market simply gets the neutral policy.
            }
        }
        $regPolicy = $regime['policy'];
        $meanRev = ['rsi_oversold', 'rsi_overbought', 'bb_lower_touch', 'bb_upper_touch',
            'stoch_oversold_cross', 'stoch_overbought_cross', 'willr_oversold', 'willr_overbought',
            'cci_oversold', 'cci_overbought', 'mfi_oversold', 'mfi_overbought', 'fib_golden_zone'];
        $trendFollow = ['ema_trend_bull', 'ema_trend_bear', 'supertrend_dir', 'psar_flip_bull',
            'psar_flip_bear', 'donchian_breakout_bull', 'donchian_breakout_bear', 'adx_trend',
            'higher_highs_lows', 'lower_highs_lows'];

        // Helper: register a rule vote. $dir: +1 bullish, -1 bearish.
        // $scale lets a rule vote at less than its full weight when the
        // evidence it found is itself graded. Almost every rule is a yes/no
        // observation and leaves it at 1; the multi-timeframe ladder is not -
        // "every frame above agrees" and "the frames above are split" are
        // both confirmations, and they should not carry the same weight.
        $vote = function (string $key, int $dir, string $detail, float $scale = 1.0)
            use (&$rawScore, &$reasons, &$catVotes, $regimeOn, $adxNow, $meanRev, $trendFollow, $tf, $symbol): void {
            if (!isset($this->rules[$key])) {
                return;
            }
            $cat = (string)($this->rules[$key]['category'] ?? 'other');
            // A profile may switch off whole evidence categories (a pure
            // price-action trader wanting nothing from news or positioning).
            if ($this->categories !== [] && $cat !== 'other' && !isset($this->categories[$cat])) {
                return;
            }
            $w = (float)$this->rules[$key]['weight'];
            // Personal rule weights from the viewer's profile, when set.
            $pw = $this->profile['rule_weights'][$key] ?? null;
            if (is_numeric($pw)) {
                $w = max(0.0, min(5.0, (float)$pw));
                $detail .= ' [your weight]';
            }
            if ($w <= 0) {
                return;
            }
            // Per-timeframe multiplier: the same rule can be strong on 1d and
            // noise on 15m; these are learned from verified outcomes.
            // NOT IN A BACKTEST. Both learned multipliers are fitted on
            // settled outcomes - including the very trades a backtest is about
            // to re-run - so applying them to history is asking the engine to
            // trade a period using what it learned from that period. The
            // learned model is already skipped in dry mode for this reason;
            // these two were not, so every backtest and every
            // champion-challenger comparison was flattered by hindsight.
            $learned = !$this->dry;
            $tm = $learned ? (float)($this->tfMult[$tf][$key] ?? 1.0) : 1.0;
            if ($tm > 0 && abs($tm - 1.0) >= 0.01) {
                $w *= $tm;
                $detail .= sprintf(' [x%.2f learned %s multiplier]', $tm, $tf);
            }
            // Per-coin multiplier, on top of the per-frame one and for the same
            // reason: a rule can work on one pair and not another. It is
            // already shrunk toward 1.0 by how much this coin has settled, so
            // a pair with a short record moves the weight barely at all - and
            // whatever it does move is said out loud, in the signal's own
            // reasons, rather than happening quietly.
            $sm = $learned ? (float)($this->symMult[$symbol][$key] ?? 1.0) : 1.0;
            if ($sm > 0 && abs($sm - 1.0) >= 0.01) {
                $w *= $sm;
                $detail .= sprintf(' [x%.2f learned %s multiplier]', $sm, $symbol);
            }
            if ($regimeOn) {
                if ($adxNow >= 30 && in_array($key, $meanRev, true)) {
                    $w *= 0.5;
                    $detail .= ' [half weight: strong trend regime, ADX ' . round($adxNow, 1) . ']';
                } elseif ($adxNow < 15 && in_array($key, $trendFollow, true)) {
                    $w *= 0.5;
                    $detail .= ' [half weight: choppy regime, ADX ' . round($adxNow, 1) . ']';
                }
            }
            $w *= max(0.0, min(1.0, $scale));
            if ($w <= 0) {
                return;
            }
            $rawScore += $dir * $w;
            $catVotes[$cat] = ($catVotes[$cat] ?? 0.0) + $dir * $w;
            $reasons[] = [
                'rule'   => $this->rules[$key]['name'],
                'key'    => $key,
                'cat'    => $cat,
                'side'   => $dir > 0 ? 'bullish' : 'bearish',
                'weight' => round($w, 3),
                'detail' => $detail,
            ];
        };
        // Every enabled rule contributes its weight to the confidence divisor.
        foreach ($this->rules as $r) {
            $maxScore += (float)$r['weight'];
        }

        // ---- Momentum: RSI -------------------------------------------------
        $rsiNow = $rsi[$i];
        if ($rsiNow !== null) {
            if ($rsiNow <= 30) {
                $vote('rsi_oversold', +1, sprintf('RSI(14) at %.1f - oversold zone', $rsiNow));
            } elseif ($rsiNow >= 70) {
                $vote('rsi_overbought', -1, sprintf('RSI(14) at %.1f - overbought zone', $rsiNow));
            }
        }

        // ---- Momentum: MACD cross (within last 3 bars) ---------------------
        for ($b = $i; $b > $i - 3 && $b > 0; $b--) {
            if ($macd['macd'][$b] === null || $macd['signal'][$b] === null ||
                $macd['macd'][$b - 1] === null || $macd['signal'][$b - 1] === null) {
                continue;
            }
            $nowUp  = $macd['macd'][$b] > $macd['signal'][$b];
            $wasUp  = $macd['macd'][$b - 1] > $macd['signal'][$b - 1];
            if ($nowUp && !$wasUp) {
                $vote('macd_bull_cross', +1, sprintf('MACD crossed above signal %d bar(s) ago', $i - $b));
                break;
            }
            if (!$nowUp && $wasUp) {
                $vote('macd_bear_cross', -1, sprintf('MACD crossed below signal %d bar(s) ago', $i - $b));
                break;
            }
        }

        // ---- Trend: EMA alignment ------------------------------------------
        if ($ema20[$i] !== null && $ema50[$i] !== null) {
            if ($ema20[$i] > $ema50[$i] && $price > $ema20[$i]) {
                $vote('ema_trend_bull', +1, 'EMA20 > EMA50 and price above both (uptrend alignment)');
            } elseif ($ema20[$i] < $ema50[$i] && $price < $ema20[$i]) {
                $vote('ema_trend_bear', -1, 'EMA20 < EMA50 and price below both (downtrend alignment)');
            }
        }
        if ($ema200[$i] !== null) {
            $vote('ema200_regime', $price >= $ema200[$i] ? +1 : -1,
                sprintf('Price %s the 200-EMA (%s regime)',
                    $price >= $ema200[$i] ? 'above' : 'below',
                    $price >= $ema200[$i] ? 'bullish' : 'bearish'));
        }

        // ---- Volatility: Bollinger tags ------------------------------------
        if ($bb['lower'][$i] !== null) {
            if ($price <= $bb['lower'][$i]) {
                $vote('bb_lower_touch', +1, 'Close at/below lower Bollinger band - statistically stretched down');
            } elseif ($price >= $bb['upper'][$i]) {
                $vote('bb_upper_touch', -1, 'Close at/above upper Bollinger band - statistically stretched up');
            }
        }

        // ---- Momentum: Stochastic cross ------------------------------------
        if ($stoch['k'][$i] !== null && $stoch['d'][$i] !== null &&
            $stoch['k'][$i - 1] !== null && $stoch['d'][$i - 1] !== null) {
            $kNow = $stoch['k'][$i]; $dNow = $stoch['d'][$i];
            $crossUp   = $kNow > $dNow && $stoch['k'][$i - 1] <= $stoch['d'][$i - 1];
            $crossDown = $kNow < $dNow && $stoch['k'][$i - 1] >= $stoch['d'][$i - 1];
            if ($crossUp && $kNow < 25) {
                $vote('stoch_oversold_cross', +1, sprintf('Stochastic %%K crossed above %%D at %.1f (oversold)', $kNow));
            } elseif ($crossDown && $kNow > 75) {
                $vote('stoch_overbought_cross', -1, sprintf('Stochastic %%K crossed below %%D at %.1f (overbought)', $kNow));
            }
        }

        // ---- Candlestick patterns on the latest bar ------------------------
        $body  = abs($closes[$i] - $opens[$i]);
        $pBody = abs($closes[$i - 1] - $opens[$i - 1]);
        $upWick   = $highs[$i] - max($opens[$i], $closes[$i]);
        $downWick = min($opens[$i], $closes[$i]) - $lows[$i];
        $green = $closes[$i] > $opens[$i];
        $prevGreen = $closes[$i - 1] > $opens[$i - 1];

        if ($green && !$prevGreen && $body > $pBody
            && $closes[$i] >= max($opens[$i - 1], $closes[$i - 1])
            && $opens[$i]  <= min($opens[$i - 1], $closes[$i - 1])) {
            $vote('bullish_engulfing', +1, 'Latest candle fully engulfs prior red body');
        }
        if (!$green && $prevGreen && $body > $pBody
            && $opens[$i]  >= max($opens[$i - 1], $closes[$i - 1])
            && $closes[$i] <= min($opens[$i - 1], $closes[$i - 1])) {
            $vote('bearish_engulfing', -1, 'Latest candle fully engulfs prior green body');
        }
        if ($body > 0 && $downWick >= 2 * $body && $upWick <= $body) {
            $vote('hammer', +1, 'Hammer: long lower wick rejecting lower prices');
        }
        if ($body > 0 && $upWick >= 2 * $body && $downWick <= $body) {
            $vote('shooting_star', -1, 'Shooting star: long upper wick rejecting higher prices');
        }

        // ---- Volume confirmation -------------------------------------------
        if ($volSma[$i] !== null && $volSma[$i] > 0 && $vols[$i] > 1.5 * $volSma[$i] && $body > 0) {
            $vote('volume_confirmation', $green ? +1 : -1,
                sprintf('Volume %.1fx its 20-bar average confirms the %s candle',
                    $vols[$i] / $volSma[$i], $green ? 'bullish' : 'bearish'));
        }

        // ---- Market structure: swing sequence ------------------------------
        $sh = $swing['highs'];
        $sl = $swing['lows'];
        if (count($sh) >= 2 && count($sl) >= 2) {
            $hh = $highs[end($sh)] > $highs[$sh[count($sh) - 2]];
            $hl = $lows[end($sl)]  > $lows[$sl[count($sl) - 2]];
            $lh = $highs[end($sh)] < $highs[$sh[count($sh) - 2]];
            $ll = $lows[end($sl)]  < $lows[$sl[count($sl) - 2]];
            if ($hh && $hl) {
                $vote('higher_highs_lows', +1, 'Structure: higher swing highs and higher swing lows');
            } elseif ($lh && $ll) {
                $vote('lower_highs_lows', -1, 'Structure: lower swing highs and lower swing lows');
            }
        }

        // ---- Market structure: support / resistance proximity --------------
        $atrNow = $atr[$i] ?? null;
        $support = null;
        $resistance = null;
        // Proximity uses the ACTIVE (provisional) swing set. The strict set
        // needs five confirming bars on the right, so the nearest level it can
        // offer is always at least five bars stale - on 15m that is over an
        // hour, which is worse than useless for "price is at support".
        $activeLows  = $swingT['active_lows'];
        $activeHighs = $swingT['active_highs'];
        if ($activeLows)  { $support = $lows[end($activeLows)]; }
        if ($activeHighs) { $resistance = $highs[end($activeHighs)]; }
        if ($atrNow !== null && $atrNow > 0) {
            if ($support !== null && $price > $support && ($price - $support) <= $atrNow) {
                $vote('near_support', +1, sprintf('Price within 1 ATR of swing support %.6g', $support));
            }
            if ($resistance !== null && $price < $resistance && ($resistance - $price) <= $atrNow) {
                $vote('near_resistance', -1, sprintf('Price within 1 ATR of swing resistance %.6g', $resistance));
            }
        }

        // ---- Extended indicator suite --------------------------------------

        // ADX trend strength + direction
        if ($adx['adx'][$i] !== null && $adx['adx'][$i] >= 25
            && $adx['plus_di'][$i] !== null && $adx['minus_di'][$i] !== null) {
            $dirUp = $adx['plus_di'][$i] > $adx['minus_di'][$i];
            $vote('adx_trend', $dirUp ? +1 : -1,
                sprintf('ADX %.1f confirms a strong %s trend (+DI %.1f vs -DI %.1f)',
                    $adx['adx'][$i], $dirUp ? 'up' : 'down', $adx['plus_di'][$i], $adx['minus_di'][$i]));
        }

        // Ichimoku cloud position + TK cross
        if ($ichi['senkou_a'][$i] !== null && $ichi['senkou_b'][$i] !== null) {
            $cloudTop = max($ichi['senkou_a'][$i], $ichi['senkou_b'][$i]);
            $cloudBot = min($ichi['senkou_a'][$i], $ichi['senkou_b'][$i]);
            if ($price > $cloudTop) {
                $vote('ichimoku_cloud', +1, 'Price trading above the Ichimoku cloud (bullish regime)');
            } elseif ($price < $cloudBot) {
                $vote('ichimoku_cloud', -1, 'Price trading below the Ichimoku cloud (bearish regime)');
            }
        }
        if ($ichi['tenkan'][$i] !== null && $ichi['kijun'][$i] !== null
            && $ichi['tenkan'][$i - 1] !== null && $ichi['kijun'][$i - 1] !== null) {
            $nowUp = $ichi['tenkan'][$i] > $ichi['kijun'][$i];
            $wasUp = $ichi['tenkan'][$i - 1] > $ichi['kijun'][$i - 1];
            if ($nowUp && !$wasUp) {
                $vote('ichimoku_tk_cross_bull', +1, 'Tenkan-sen crossed above Kijun-sen');
            } elseif (!$nowUp && $wasUp) {
                $vote('ichimoku_tk_cross_bear', -1, 'Tenkan-sen crossed below Kijun-sen');
            }
        }

        // Williams %R extremes
        if ($willr[$i] !== null) {
            if ($willr[$i] <= -80) {
                $vote('willr_oversold', +1, sprintf('Williams %%R at %.1f - oversold', $willr[$i]));
            } elseif ($willr[$i] >= -20) {
                $vote('willr_overbought', -1, sprintf('Williams %%R at %.1f - overbought', $willr[$i]));
            }
        }

        // CCI extremes
        if ($cci[$i] !== null) {
            if ($cci[$i] <= -100) {
                $vote('cci_oversold', +1, sprintf('CCI(20) at %.0f - extreme below the mean', $cci[$i]));
            } elseif ($cci[$i] >= 100) {
                $vote('cci_overbought', -1, sprintf('CCI(20) at %.0f - extreme above the mean', $cci[$i]));
            }
        }

        // Money Flow Index extremes
        if ($mfi[$i] !== null) {
            if ($mfi[$i] <= 20) {
                $vote('mfi_oversold', +1, sprintf('MFI at %.1f - volume-weighted selling exhaustion', $mfi[$i]));
            } elseif ($mfi[$i] >= 80) {
                $vote('mfi_overbought', -1, sprintf('MFI at %.1f - volume-weighted buying exhaustion', $mfi[$i]));
            }
        }

        // OBV vs its average: accumulation / distribution
        if ($obvSma[$i] !== null) {
            $vote('obv_trend', $obv[$i] >= $obvSma[$i] ? +1 : -1,
                $obv[$i] >= $obvSma[$i]
                    ? 'OBV above its 20-bar average - net accumulation'
                    : 'OBV below its 20-bar average - net distribution');
        }

        // Parabolic SAR flip within the last 3 bars
        for ($b = $i; $b > $i - 3 && $b > 0; $b--) {
            if ($psar['trend'][$b] === null || $psar['trend'][$b - 1] === null) {
                continue;
            }
            if ($psar['trend'][$b] === 1 && $psar['trend'][$b - 1] === -1) {
                $vote('psar_flip_bull', +1, sprintf('Parabolic SAR flipped below price %d bar(s) ago', $i - $b));
                break;
            }
            if ($psar['trend'][$b] === -1 && $psar['trend'][$b - 1] === 1) {
                $vote('psar_flip_bear', -1, sprintf('Parabolic SAR flipped above price %d bar(s) ago', $i - $b));
                break;
            }
        }

        // SuperTrend direction
        if ($sTrend['trend'][$i] !== null) {
            $vote('supertrend_dir', $sTrend['trend'][$i] > 0 ? +1 : -1,
                'SuperTrend(10, 3) is ' . ($sTrend['trend'][$i] > 0 ? 'bullish' : 'bearish'));
        }

        // Donchian 20-bar breakout / breakdown
        if ($don['upper'][$i] !== null) {
            if ($closes[$i] > $don['upper'][$i]) {
                $vote('donchian_breakout_bull', +1, sprintf('Close above the prior 20-bar high %.6g - breakout', $don['upper'][$i]));
            } elseif ($closes[$i] < $don['lower'][$i]) {
                $vote('donchian_breakout_bear', -1, sprintf('Close below the prior 20-bar low %.6g - breakdown', $don['lower'][$i]));
            }
        }

        // ---- Value & flow layer ---------------------------------------------

        // VWAP position: the benchmark desks are measured against.
        if (($vwap[$i] ?? null) !== null && $vwap[$i] > 0) {
            $dist = ($price - $vwap[$i]) / $vwap[$i] * 100;
            if (abs($dist) >= 0.05) {
                $vote('vwap_position', $price > $vwap[$i] ? +1 : -1,
                    sprintf('Price %.2f%% %s the 20-bar VWAP (%.6g)',
                        abs($dist), $price > $vwap[$i] ? 'above' : 'below', $vwap[$i]));
            }
        }

        // Anchored VWAP from the last significant swing: reclaiming it hands
        // control back to the side that lost the move.
        $anchor = null;
        if ($activeHighs || $activeLows) {
            $anchor = max($activeHighs ? end($activeHighs) : 0, $activeLows ? end($activeLows) : 0);
        }
        if ($anchor !== null && $anchor > 0 && $i - $anchor >= 3) {
            $avw = Indicators::anchoredVwap($highs, $lows, $closes, $vols, $anchor);
            if (($avw[$i] ?? null) !== null && ($avw[$i - 1] ?? null) !== null) {
                $nowAbove = $price > $avw[$i];
                $wasAbove = $closes[$i - 1] > $avw[$i - 1];
                if ($nowAbove !== $wasAbove) {
                    $vote('vwap_reclaim', $nowAbove ? +1 : -1,
                        sprintf('Price just %s the VWAP anchored at the swing %d bars back (%.6g)',
                            $nowAbove ? 'reclaimed' : 'lost', $i - $anchor, $avw[$i]));
                }
            }
        }

        // Volume profile: value-area edges are where business was done.
        if ($vProf !== null && $atrNow !== null && $atrNow > 0) {
            $tol = 0.3 * $atrNow;
            if ($price <= $vProf['val'] + $tol && $price >= $vProf['val'] - $tol) {
                $vote('volume_profile_edge', +1,
                    sprintf('Price at the value-area low %.6g (POC %.6g) - the lower edge of where volume traded',
                        $vProf['val'], $vProf['poc']));
            } elseif ($price >= $vProf['vah'] - $tol && $price <= $vProf['vah'] + $tol) {
                $vote('volume_profile_edge', -1,
                    sprintf('Price at the value-area high %.6g (POC %.6g) - the upper edge of where volume traded',
                        $vProf['vah'], $vProf['poc']));
            }
        }

        // Volatility squeeze: coiled range, expansion pending. Direction comes
        // from structure, so the vote follows the prevailing EMA alignment.
        if ($bwPct !== null && $bwPct <= 20 && $ema20[$i] !== null && $ema50[$i] !== null) {
            $vote('bb_squeeze', $ema20[$i] >= $ema50[$i] ? +1 : -1,
                sprintf('Bollinger bandwidth in the %.0fth percentile - squeeze, expansion pending %s',
                    $bwPct, $ema20[$i] >= $ema50[$i] ? 'upward' : 'downward'));
        }

        // ---- Positioning layer (live only; no history for backtests) --------
        if (!$this->dry && $this->md !== null && str_ends_with($symbol, 'USDT')
            && MarketData::isCrypto($symbol)) {
            // Open interest: is there new money behind the move?
            if (isset($this->rules['oi_confirmation']) && $this->cfg('oi_enabled', '1') === '1') {
                $oi = $this->md->openInterestChange($symbol, $tf);
                $thr = max(0.1, (float)$this->cfg('oi_change_pct', '1.5'));
                if ($oi !== null && abs($oi) >= $thr && $ema20[$i] !== null) {
                    $priceUp = $price >= $ema20[$i];
                    if ($oi > 0) {
                        $vote('oi_confirmation', $priceUp ? +1 : -1,
                            sprintf('Open interest +%.2f%% while price is %s - new money is taking risk behind this move',
                                $oi, $priceUp ? 'holding up' : 'pressing down'));
                    } else {
                        // Falling OI: the move is positions closing, not opening.
                        $vote('oi_confirmation', $priceUp ? -1 : +1,
                            sprintf('Open interest %.2f%% while price is %s - this is position closing (%s), not fresh conviction',
                                $oi, $priceUp ? 'rising' : 'falling',
                                $priceUp ? 'short covering' : 'long liquidation'));
                    }
                }
            }
            // Long/short crowding.
            if (isset($this->rules['long_short_extreme'])) {
                $lsr = $this->md->longShortRatio($symbol);
                if ($lsr !== null && ($lsr >= 2.5 || $lsr <= 0.6)) {
                    $vote('long_short_extreme', $lsr >= 2.5 ? -1 : +1,
                        sprintf('Top-trader long/short ratio at %.2f - the %s side is crowded',
                            $lsr, $lsr >= 2.5 ? 'long' : 'short'));
                }
            }
            // Resting liquidity at the top of book.
            if (isset($this->rules['book_imbalance']) && $this->cfg('book_enabled', '1') === '1') {
                $imb = $this->md->bookImbalance($symbol);
                $ithr = max(0.05, min(0.9, (float)$this->cfg('book_imbalance', '0.25')));
                if ($imb !== null && abs($imb) >= $ithr) {
                    $vote('book_imbalance', $imb > 0 ? +1 : -1,
                        sprintf('Order book %.0f%% skewed to the %s within the top 100 levels',
                            abs($imb) * 100, $imb > 0 ? 'bid' : 'offer'));
                }
            }
        }

        // ---- Smart-money concepts, as context rather than as calls ---------
        //
        // Every one of these votes is conditional. There is an order block
        // behind price on every chart ever drawn, an unfilled gap somewhere,
        // and liquidity resting above every high - so the mere presence of a
        // structure is worth nothing and is never voted on. What is worth
        // something is price standing IN one, having just done something,
        // while the rest of the picture agrees.
        //
        // They all live in the structure category, whose correlation cap is
        // 2.5 against a buy threshold of 3. That is deliberate: smart-money
        // reads can shade a verdict and can never produce one on their own.
        $smc = ['bos' => null, 'choch' => null, 'sweep' => null, 'order_block' => null,
                'fvg' => null, 'breaker' => null, 'eq_highs' => null, 'eq_lows' => null,
                'range' => null, 'zone' => null, 'zone_pct' => null, 'structure_dir' => 0];
        if ($this->cfg('smc_enabled', '1') === '1') {
            $smc = Smc::analyse($opens, $highs, $lows, $closes, $swingT, $atr[$i] ?? null);
            $fresh = max(1, (int)$this->cfg('smc_fresh_bars', '6'));
            $near  = max(0.05, (float)$this->cfg('smc_near_atr', '0.5'));
            $zone  = (string)($smc['zone'] ?? '');

            // Break of structure: price closed beyond the last swing in the
            // direction structure was already pointing. Only while it is
            // recent - a break twenty bars ago is history, not a trigger.
            if (is_array($smc['bos']) && $smc['bos']['age'] <= $fresh) {
                $vote('smc_bos', (int)$smc['bos']['dir'], sprintf(
                    'Break of structure %s through %.6g, %d bar(s) ago - structure continued',
                    $smc['bos']['dir'] > 0 ? 'up' : 'down', $smc['bos']['level'], $smc['bos']['age']));
            }

            // Change of character: the first break the other way. The single
            // most useful thing in this vocabulary, and the most abused - it
            // only means anything while it is new.
            if (is_array($smc['choch']) && $smc['choch']['age'] <= $fresh) {
                $vote('smc_choch', (int)$smc['choch']['dir'], sprintf(
                    'Change of character %s through %.6g, %d bar(s) ago - structure turned',
                    $smc['choch']['dir'] > 0 ? 'up' : 'down', $smc['choch']['level'], $smc['choch']['age']));
            }

            // Liquidity sweep: stops taken beyond a swing and immediately
            // rejected. Weighted by how deep the raid went.
            if (is_array($smc['sweep']) && $smc['sweep']['age'] <= 3) {
                $vote('smc_liquidity_sweep', (int)$smc['sweep']['dir'], sprintf(
                    'Liquidity swept %s %.6g by %.2f ATR and rejected within the bar',
                    $smc['sweep']['dir'] > 0 ? 'below' : 'above',
                    $smc['sweep']['level'], $smc['sweep']['depth']),
                    min(1.0, 0.4 + (float)$smc['sweep']['depth']));
            }

            // Order block, but only where price is actually in it, and only
            // from the right half of the range. Buying a bullish block in a
            // premium is buying expensive from a level that is meant to be
            // cheap, which is the entire point of the concept.
            if (is_array($smc['order_block']) && $smc['order_block']['distance'] <= $near) {
                $obDir = (int)$smc['order_block']['dir'];
                $zoneOk = $obDir > 0 ? $zone !== 'premium' : $zone !== 'discount';
                if ($zoneOk) {
                    $vote('smc_order_block', $obDir, sprintf(
                        'Price is %s the %s order block %.6g-%.6g (%d bars old)%s',
                        $smc['order_block']['mitigated'] ? 'inside' : 'at',
                        $obDir > 0 ? 'bullish' : 'bearish',
                        $smc['order_block']['bottom'], $smc['order_block']['top'],
                        $smc['order_block']['age'],
                        $zone !== '' ? ' in the ' . $zone : ''));
                }
            }

            // Breaker: an order block price closed through. The level failed,
            // and failed levels tend to hold from the other side.
            if (is_array($smc['breaker']) && $smc['breaker']['distance'] <= $near) {
                // THE SAME ZONE TEST THE ORDER BLOCK GETS.
                //
                // A breaker is an order block read from the other side, so the
                // objection is identical: going long off a level sitting in a
                // premium is buying expensive from something that is only
                // interesting when it is cheap. The block above has refused
                // that since it was written; the breaker never did.
                $bkDir = (int)$smc['breaker']['dir'];
                $bkZoneOk = $bkDir > 0 ? $zone !== 'premium' : $zone !== 'discount';
                if ($bkZoneOk) {
                    $vote('smc_breaker', $bkDir, sprintf(
                        'Price is back at the broken block %.6g-%.6g, now acting as %s%s',
                        $smc['breaker']['bottom'], $smc['breaker']['top'],
                        $bkDir > 0 ? 'support' : 'resistance',
                        $zone !== '' ? ' in the ' . $zone : ''));
                }
            }

            // Fair value gap: only while price is inside the imbalance, which
            // is the one moment it is a decision point rather than a target.
            if (is_array($smc['fvg']) && !empty($smc['fvg']['inside'])) {
                $vote('smc_fvg', (int)$smc['fvg']['dir'], sprintf(
                    'Price is filling the %s fair value gap %.6g-%.6g (%.2f ATR wide, %d bars old)',
                    $smc['fvg']['dir'] > 0 ? 'bullish' : 'bearish',
                    $smc['fvg']['bottom'], $smc['fvg']['top'],
                    $smc['fvg']['size'], $smc['fvg']['age']),
                    min(1.0, 0.5 + (float)$smc['fvg']['size'] / 2));
            }

            // Equal highs and lows are a magnet, not a direction: price tends
            // to go and take them. So a shelf close overhead argues UP, which
            // is the opposite of how a resistance level is usually read.
            foreach ([['eq_highs', +1, 'highs above'], ['eq_lows', -1, 'lows below']] as [$k, $dir, $what]) {
                $eq = $smc[$k];
                if (is_array($eq) && $eq['distance'] <= max($near, 1.5)) {
                    $vote('smc_liquidity_target', $dir, sprintf(
                        'Equal %s at %.6g, %.2f ATR away - resting liquidity price tends to reach for',
                        $what, $eq['level'], $eq['distance']));
                }
            }

            // Premium and discount: where in the range this trade is being
            // taken. Cheap on its own, and it is the filter that stops every
            // other reading above from being applied at the worst price.
            if ($zone === 'discount' || $zone === 'premium') {
                $vote('smc_premium_discount', $zone === 'discount' ? +1 : -1, sprintf(
                    'Price is at %.0f%% of the %.6g-%.6g dealing range - a %s',
                    (float)$smc['zone_pct'] * 100,
                    $smc['range']['low'], $smc['range']['high'],
                    $zone === 'discount' ? 'discount, where longs are cheap'
                                         : 'premium, where shorts are cheap'));
            }
        }

        // ---- Master-trader layer -------------------------------------------

        // Higher-timeframe confluence, read down the whole ladder rather than
        // one rung up. Looking a single timeframe up catches a 5m long fired
        // into a falling 1h and misses a 5m long fired into a falling *daily*
        // while the 1h happens to be bouncing - which is the more expensive
        // mistake of the two. Weighted so the higher frames outrank the lower
        // ones, and voted in proportion to how much of the ladder agrees.
        $higherMap = ['1m' => '15m', '3m' => '30m', '5m' => '1h', '15m' => '1h', '30m' => '4h',
                      '1h' => '4h', '2h' => '8h', '4h' => '1d', '6h' => '1d', '8h' => '1d',
                      '12h' => '1d', '1d' => '1w', '3d' => '1w', '1w' => '1mo'];
        $mtfDir = 0;
        $mtf = ['bias' => 0.0, 'frames' => [], 'weight' => 0.0, 'top' => null];
        if ($this->md !== null) {
            try {
                $mtf = Mtf::bias($this->md, $symbol, $tf, $this->asOfMs);
            } catch (\Throwable $e) {
                // ladder unavailable - the gate below treats that as no
                // information rather than as agreement
            }
        }
        // WHICH WAY THE LADDER LEANS IS A FACT. WHETHER TO VOTE ON IT IS A POLICY.
        //
        // These were one statement, and the fact was computed inside the guard
        // that decides the policy - so the moment the self-learning pass
        // quarantined mtf_confluence for poor expectancy, $mtfDir stopped
        // being set at all.
        //
        // Quarantine's job is to stop a rule VOTING. It silently did more than
        // that: the A+ grade requires the ladder to agree with the confluence
        // direction, so benching one rule switched off the site's top grade
        // entirely and permanently. Measured on this install afterwards -
        // mtfDir was 0 on 48 of 48 pair/timeframes, and no signal has been
        // graded A+ since. Nothing said so; the grade simply stopped existing.
        //
        // The ladder leaning up is true whether or not the site currently
        // trusts that rule's vote, so the observation is taken first and the
        // vote is cast second, under the guard where it belongs.
        $mtfBias = (float)$mtf['bias'];
        // Below this the ladder is genuinely split, and saying so is more
        // honest than reading a direction into noise.
        $minBias = max(0.0, min(1.0, (float)$this->cfg('mtf_min_bias', '0.25')));
        if ($mtf['frames'] && abs($mtfBias) >= $minBias) {
            $mtfDir = $mtfBias > 0 ? 1 : -1;
        }
        if ($mtfDir !== 0 && isset($this->rules['mtf_confluence'])) {
            $vote('mtf_confluence', $mtfDir, sprintf(
                'Higher timeframes lean %s (%+.2f across %s)',
                $mtfDir > 0 ? 'up' : 'down', $mtfBias, Mtf::describe($mtf)
            ), abs($mtfBias));
        }

        // Recursive higher-timeframe pass: re-run the WHOLE knowledge base one
        // timeframe up and vote with its verdict. The check above compares a
        // single moving average; two independent passes of the full rule set
        // agreeing is a categorically stronger piece of evidence. Depth-capped
        // at one level and short-cached, so the cost is bounded.
        if ($this->htfDepth < 1 && $this->md !== null && isset($higherMap[$tf])
            && isset($this->rules['htf_engine'])
            && $this->cfg('htf_engine_enabled', '1') === '1') {
            try {
                $htf = $higherMap[$tf];
                $ckey = 'htf:' . $symbol . ':' . $htf . ':' . ($this->asOfMs ?? 0);
                $hv = $this->dry ? null : Cache::get($ckey);
                if ($hv === null) {
                    $sub = (new self())->withMarketData($this->md)->withHtfDepth($this->htfDepth + 1);
                    if ($this->dry) {
                        $sub->dry();
                    }
                    if ($this->asOfMs !== null) {
                        $sub->asOf($this->asOfMs);
                    }
                    $hCandles = $this->asOfMs !== null
                        ? $this->md->candlesAsOf($symbol, $htf, $this->asOfMs)
                        : $this->md->candles($symbol, $htf);
                    $sub->suppressStore();
                    $hres = $sub->analyse($symbol, $htf, $hCandles);
                    $hv = ['signal' => $hres['signal'], 'score' => $hres['score'], 'grade' => $hres['grade']];
                    if (!$this->dry) {
                        Cache::set($ckey, $hv, 120);
                    }
                }
                if (is_array($hv) && in_array($hv['signal'] ?? '', ['BUY', 'SELL'], true)) {
                    $hdir = $hv['signal'] === 'BUY' ? 1 : -1;
                    if ($mtfDir === 0) {
                        $mtfDir = $hdir;
                    }
                    $vote('htf_engine', $hdir,
                        sprintf('Full analysis on the %s timeframe also reads %s (score %+.2f, grade %s)',
                            $htf, $hv['signal'], (float)$hv['score'], (string)($hv['grade'] ?? '?')));
                }
            } catch (\Throwable $e) {
                // higher-timeframe pass unavailable - skip silently
            }
        }

        // RSI divergence against the last two swing points (within 40 bars).
        $recent = fn(array $idx) => array_values(array_filter($idx, fn($x) => $x >= $n - 40));
        $rsl = $recent($sl);
        $rsh = $recent($sh);
        if (count($rsl) >= 2) {
            $a = $rsl[count($rsl) - 2];
            $b = $rsl[count($rsl) - 1];
            if ($rsi[$a] !== null && $rsi[$b] !== null
                && $lows[$b] < $lows[$a] && $rsi[$b] > $rsi[$a] + 1) {
                $vote('rsi_divergence_bull', +1,
                    sprintf('Price made a lower low (%.6g→%.6g) while RSI made a higher low (%.1f→%.1f)',
                        $lows[$a], $lows[$b], $rsi[$a], $rsi[$b]));
            }
        }
        if (count($rsh) >= 2) {
            $a = $rsh[count($rsh) - 2];
            $b = $rsh[count($rsh) - 1];
            if ($rsi[$a] !== null && $rsi[$b] !== null
                && $highs[$b] > $highs[$a] && $rsi[$b] < $rsi[$a] - 1) {
                $vote('rsi_divergence_bear', -1,
                    sprintf('Price made a higher high (%.6g→%.6g) while RSI made a lower high (%.1f→%.1f)',
                        $highs[$a], $highs[$b], $rsi[$a], $rsi[$b]));
            }
        }

        // MACD-HISTOGRAM AND OBV DIVERGENCE, THE SAME TEST ON TWO OTHER WITNESSES.
        //
        // The RSI version above has been carrying divergence on its own. The
        // pattern it looks for - price making a new extreme while the thing
        // driving it does not - is not an RSI property, and reading it from
        // one oscillator means one indicator's quirks decide whether the site
        // sees a divergence at all.
        //
        // The histogram is the better momentum witness of the two: it is the
        // distance between MACD and its signal line, so it turns while the
        // MACD line itself is still making new extremes, and a lower high on
        // it is momentum decelerating rather than merely being high.
        //
        // OBV is a different KIND of evidence, which is the point of adding it
        // rather than a third oscillator. Price making a new low while
        // cumulative volume does not is distribution failing to follow the
        // price - a volume statement, not a momentum one - so it can agree
        // with the momentum reading without simply repeating it, and the
        // category caps that stop five oscillators counting five times treat
        // it as the separate vote it is.
        //
        // Same swing points, same 40-bar window, same shape of test as RSI.
        $divPair = function (array $pivots) use ($n): ?array {
            $r = array_values(array_filter($pivots, static fn ($x) => $x >= $n - 40));
            return count($r) >= 2 ? [$r[count($r) - 2], $r[count($r) - 1]] : null;
        };
        $hist = $macd['hist'] ?? [];

        if (($p = $divPair($sl)) !== null) {
            [$a, $b] = $p;
            // A histogram divergence only means anything while the histogram
            // is on the side it is diverging from: a "higher low" between two
            // positive readings is just an uptrend breathing.
            if (isset($hist[$a], $hist[$b]) && $hist[$a] !== null && $hist[$b] !== null
                && $lows[$b] < $lows[$a] && $hist[$b] > $hist[$a] && $hist[$a] < 0) {
                $vote('macd_hist_divergence_bull', +1,
                    sprintf('Price made a lower low (%.6g→%.6g) while the MACD histogram made a '
                        . 'higher low (%.4g→%.4g) - selling pressure decelerating into the low',
                        $lows[$a], $lows[$b], $hist[$a], $hist[$b]));
            }
            if (isset($obv[$a], $obv[$b]) && $obv[$a] !== null && $obv[$b] !== null
                && $lows[$b] < $lows[$a] && $obv[$b] > $obv[$a]) {
                $vote('obv_divergence_bull', +1,
                    sprintf('Price made a lower low (%.6g→%.6g) while on-balance volume rose - '
                        . 'the new low was made without the selling volume to back it',
                        $lows[$a], $lows[$b]));
            }
        }
        if (($p = $divPair($sh)) !== null) {
            [$a, $b] = $p;
            if (isset($hist[$a], $hist[$b]) && $hist[$a] !== null && $hist[$b] !== null
                && $highs[$b] > $highs[$a] && $hist[$b] < $hist[$a] && $hist[$a] > 0) {
                $vote('macd_hist_divergence_bear', -1,
                    sprintf('Price made a higher high (%.6g→%.6g) while the MACD histogram made a '
                        . 'lower high (%.4g→%.4g) - the push is decelerating',
                        $highs[$a], $highs[$b], $hist[$a], $hist[$b]));
            }
            if (isset($obv[$a], $obv[$b]) && $obv[$a] !== null && $obv[$b] !== null
                && $highs[$b] > $highs[$a] && $obv[$b] < $obv[$a]) {
                $vote('obv_divergence_bear', -1,
                    sprintf('Price made a higher high (%.6g→%.6g) while on-balance volume fell - '
                        . 'the rally is being sold into rather than bought',
                        $highs[$a], $highs[$b]));
            }
        }

        // Fibonacci golden zone of the last impulse leg (swing low <-> swing high).
        if ($sh && $sl && $atrNow !== null) {
            $lastHigh = end($sh);
            $lastLow = end($sl);
            if ($lastLow < $lastHigh) {
                // Up-leg: retracement buying zone between 50% and 61.8%.
                $H = $highs[$lastHigh];
                $L = $lows[$lastLow];
                $range = $H - $L;
                if ($range > 2 * $atrNow) {
                    $zTop = $H - 0.5 * $range;
                    $zBot = $H - 0.618 * $range;
                    if ($price <= $zTop + 0.15 * $atrNow && $price >= $zBot - 0.15 * $atrNow) {
                        $vote('fib_golden_zone', +1,
                            sprintf('Pullback into the 50-61.8%% golden zone (%.6g - %.6g) of the up-leg', $zBot, $zTop));
                    }
                }
            } elseif ($lastHigh < $lastLow) {
                // Down-leg: retracement selling zone.
                $H = $highs[$lastHigh];
                $L = $lows[$lastLow];
                $range = $H - $L;
                if ($range > 2 * $atrNow) {
                    $zBot = $L + 0.5 * $range;
                    $zTop = $L + 0.618 * $range;
                    if ($price >= $zBot - 0.15 * $atrNow && $price <= $zTop + 0.15 * $atrNow) {
                        $vote('fib_golden_zone', -1,
                            sprintf('Bounce into the 50-61.8%% golden zone (%.6g - %.6g) of the down-leg', $zBot, $zTop));
                    }
                }
            }
        }

        // BTC regime filter for altcoins: read Bitcoin's own trend on the
        // same timeframe (cached candles - no extra API cost).
        // The Bitcoin tide lifts altcoins; it says nothing about an equity or
        // a currency pair, so the rule sits out for non-crypto instruments.
        //
        // AND ONLY FOR THE COINS THAT ACTUALLY FOLLOW IT. This fired for every
        // crypto that was not itself Bitcoin, at full weight, on the assumption
        // that "altcoin" means "moves with BTC". Plenty do not. A gold-backed
        // token like PAXG or XAUT tracks gold; a stablecoin pair tracks
        // nothing; and a coin in its own news cycle can spend a week ignoring
        // the market entirely. Telling all of them "the tide is with altcoin
        // longs because Bitcoin is above its long EMA" is not a weak signal,
        // it is a wrong one - and it was pushing the score at full strength.
        //
        // So the vote is scaled by how closely this coin has actually moved
        // with Bitcoin lately: correlation of the two return series over the
        // same candles the analysis already loaded. A coin that moves with BTC
        // gets the whole vote. One that half-follows gets half of it. One that
        // ignores BTC gets no vote at all rather than a confident wrong one.
        // And a coin that moves INVERSELY gets the vote inverted, which is the
        // same rule honestly applied rather than a special case.
        if ($this->md !== null && isset($this->rules['btc_regime'])
            && MarketData::isCrypto($symbol) && !str_starts_with($symbol, 'BTC')) {
            try {
                $btc = $this->closedOnly($this->htfCandles('BTCUSDT', $tf), $tf);
                $bc = array_column($btc, 'c');
                $bj = count($bc) - 1;
                if ($bj >= 60) {
                    $bEma200 = Indicators::ema($bc, 200);
                    $bEma50 = Indicators::ema($bc, 50);
                    $ref = $bEma200[$bj] ?? $bEma50[$bj];
                    if ($ref !== null) {
                        $btcBull = $bc[$bj] >= $ref;
                        $weigh = $this->cfg('btc_corr_enabled', '1') === '1';
                        $corr = $weigh
                            ? self::correlation($closes, $bc,
                                max(30, (int)$this->cfg('btc_corr_bars', '120')))
                            : null;
                        if (!$weigh || $corr === null) {
                            // Switched off, or unmeasurable - a new listing, a
                            // gap in the history. Unmeasurable is NOT the same
                            // as uncorrelated, so fall back to the old blanket
                            // vote rather than silently dropping a rule. And
                            // say nothing about correlation in either case:
                            // claiming "+1.00 with BTC" because the feature is
                            // off would be inventing a measurement.
                            $corr = 1.0;
                            $how = '';
                        } else {
                            $floor = max(0.0, min(0.9, (float)$this->cfg('btc_corr_min', '0.2')));
                            if (abs($corr) < $floor) {
                                $how = null;      // does not follow BTC - no vote
                            } else {
                                $how = sprintf(' (this coin has moved %+.2f with BTC over the last %d candles)',
                                    $corr, max(30, (int)$this->cfg('btc_corr_bars', '120')));
                            }
                        }
                        if ($how !== null) {
                            // Negative correlation flips the direction: if this
                            // coin reliably moves against Bitcoin, Bitcoin
                            // being strong is a headwind, not a tailwind.
                            $dir = ($btcBull ? +1 : -1) * ($corr < 0 ? -1 : 1);
                            $vote('btc_regime', $dir,
                                'Bitcoin itself is ' . ($btcBull ? 'above' : 'below')
                                . ' its long EMA on this timeframe - the tide is '
                                . ($dir > 0 ? 'with' : 'against') . ' this coin' . $how,
                                min(1.0, abs($corr)));
                        }
                    }
                }
            } catch (\Throwable $e) {
                // BTC data unavailable - skip
            }
        }

        // ---- News sentiment (automatic, from stored headlines) -------------
        // Skipped in dry/backtest mode: historical headlines are unknown.
        $senti = null;
        if (!$this->dry && $this->cfg('news_enabled', '1') === '1'
            && (isset($this->rules['news_sentiment_coin']) || isset($this->rules['news_sentiment_market']))) {
            $stmt = Database::pdo()->prepare('SELECT label FROM symbols WHERE symbol = ?');
            $stmt->execute([$symbol]);
            $label = (string)($stmt->fetchColumn() ?: $symbol);
            $stmt->closeCursor();
            $senti = Sentiment::analyse($symbol, $label);

            if ($senti['coin']['count'] >= 2 && abs($senti['coin']['score']) >= 0.3) {
                $vote('news_sentiment_coin', $senti['coin']['score'] > 0 ? +1 : -1,
                    sprintf('%d recent headlines about this coin lean %s (sentiment %+.2f)',
                        $senti['coin']['count'],
                        $senti['coin']['score'] > 0 ? 'positive' : 'negative',
                        $senti['coin']['score']));
            }
            if ($senti['market']['count'] >= 5 && abs($senti['market']['score']) >= 0.25) {
                $vote('news_sentiment_market', $senti['market']['score'] > 0 ? +1 : -1,
                    sprintf('Overall news backdrop across %d headlines is %s (sentiment %+.2f)',
                        $senti['market']['count'],
                        $senti['market']['score'] > 0 ? 'risk-on' : 'risk-off',
                        $senti['market']['score']));
            }
        }

        // ---- Market positioning: funding rate (contrarian) -----------------
        // Perpetual-futures funding shows the crowded side. Free public
        // endpoint, cached, fail-silent; skipped in backtests (no history).
        if (!$this->dry && $this->md !== null && isset($this->rules['funding_extreme'])
            && $this->cfg('funding_enabled', '1') === '1'
            && MarketData::isCrypto($symbol) && str_ends_with($symbol, 'USDT')) {
            $rate = $this->md->fundingRate($symbol);
            $limit = max(0.005, (float)$this->cfg('funding_extreme_pct', '0.05')) / 100;
            if ($rate !== null && abs($rate) >= $limit) {
                $vote('funding_extreme', $rate > 0 ? -1 : +1,
                    sprintf('Funding rate %+.4f%% per 8h - %s is crowded, squeeze risk favors the other side',
                        $rate * 100, $rate > 0 ? 'the long side' : 'the short side'));
            }
        }

        // ---- Market positioning: Fear & Greed index (contrarian) -----------
        if (!$this->dry && isset($this->rules['fear_greed'])
            && $this->cfg('fng_enabled', '1') === '1') {
            $fng = self::fearGreed();
            if ($fng !== null) {
                if ($fng <= 20) {
                    $vote('fear_greed', +1, "Crypto Fear & Greed at $fng (extreme fear) - historically an accumulation zone");
                } elseif ($fng >= 80) {
                    $vote('fear_greed', -1, "Crypto Fear & Greed at $fng (extreme greed) - froth, crowd is all-in");
                }
            }
            // The DIRECTION sentiment is travelling, which the level cannot
            // say. 25 on the way up from 12 is capitulation being bought; 25
            // on the way down from 45 is fear still arriving. Same number,
            // opposite trade, and the level alone reads them identically.
            if (isset($this->rules['fng_trend'])) {
                $delta = self::fearGreedTrend();
                if ($delta !== null && abs($delta) >= 10) {
                    $vote('fng_trend', $delta > 0 ? +1 : -1, sprintf(
                        'Fear & Greed has moved %+d over three days - sentiment is %s while price %s',
                        $delta, $delta > 0 ? 'thawing' : 'souring',
                        $delta > 0 ? 'is still being sold' : 'is still being bought'));
                }
            }
        }

        // ---- Taker flow: who is crossing the spread ------------------------
        // Positioning says what people hold; this says who is paying up right
        // now. Read as confirmation, not as a call - aggressive buying into a
        // falling chart is a squeeze, not a trend.
        if (!$this->dry && isset($this->rules['taker_flow']) && $this->md !== null
            && $this->cfg('taker_enabled', '1') === '1') {
            try {
                $tr = $this->md->takerRatio($symbol, $tf);
            } catch (\Throwable $e) {
                $tr = null;
            }
            $edge = max(1.01, (float)$this->cfg('taker_extreme', '1.25'));
            if ($tr !== null && ($tr >= $edge || $tr <= 1 / $edge)) {
                $up = $tr >= $edge;
                $vote('taker_flow', $up ? +1 : -1, sprintf(
                    'Taker buy/sell ratio %.2f - aggressive %s are crossing the spread, not waiting',
                    $tr, $up ? 'buyers' : 'sellers'));
            }
        }

        // ---- Market breadth ------------------------------------------------
        // One coin rallying while the board bleeds is a different market from
        // one where three quarters of it is green, and no single-pair
        // indicator can tell them apart. Computed from stored candles, so it
        // costs no request.
        if (!$this->dry && isset($this->rules['market_breadth']) && $this->md !== null
            && $this->cfg('breadth_enabled', '1') === '1') {
            try {
                $br = $this->md->breadth($tf);
            } catch (\Throwable $e) {
                $br = null;
            }
            $hi = max(50.0, min(95.0, (float)$this->cfg('breadth_bull', '65')));
            $lo = max(5.0, min(50.0, (float)$this->cfg('breadth_bear', '35')));
            if ($br !== null && ($br >= $hi || $br <= $lo)) {
                $up = $br >= $hi;
                $vote('market_breadth', $up ? +1 : -1, sprintf(
                    '%.0f%% of tracked coins are above their own 20-bar average - the move is %s',
                    $br, $up ? 'market-wide, not just this pair' : 'broad weakness, not just this pair'));
            }
        }

        // ---- Admin-authored custom rules -----------------------------------
        // The knowledge base was tunable but not extensible: adding a new idea
        // meant editing this file. Custom rules are declarative conditions
        // stored alongside the built-ins and evaluated here - no eval(), just
        // comparisons over the indicator values already computed above.
        $snapshot = static function (int $k) use ($closes, $rsi, $adx, $ema20, $ema50, $ema200, $vwap,
            $atr, $macd, $stoch, $cci, $mfi, $willr, $chop, $bb, $vProf, $support, $resistance, $bwPct): array {
            return [
                'price' => $closes[$k] ?? null,
                'rsi' => $rsi[$k] ?? null,
                'adx' => $adx['adx'][$k] ?? null,
                'ema20' => $ema20[$k] ?? null,
                'ema50' => $ema50[$k] ?? null,
                'ema200' => $ema200[$k] ?? null,
                'vwap' => $vwap[$k] ?? null,
                'atr' => $atr[$k] ?? null,
                'macd' => $macd['macd'][$k] ?? null,
                'macd_signal' => $macd['signal'][$k] ?? null,
                'stoch_k' => $stoch['k'][$k] ?? null,
                'cci' => $cci[$k] ?? null,
                'mfi' => $mfi[$k] ?? null,
                'willr' => $willr[$k] ?? null,
                'chop' => $chop[$k] ?? null,
                'bw_pct' => $bwPct,
                'bb_upper' => $bb['upper'][$k] ?? null,
                'bb_lower' => $bb['lower'][$k] ?? null,
                'poc' => $vProf['poc'] ?? null,
                'vah' => $vProf['vah'] ?? null,
                'val' => $vProf['val'] ?? null,
                'support' => $support,
                'resistance' => $resistance,
            ];
        };
        try {
            $customFired = CustomRules::evaluate($this->rules, $snapshot($i), $snapshot($i - 1));
            foreach ($customFired as $ckey => $cdir) {
                $spec = json_decode((string)($this->rules[$ckey]['expr'] ?? ''), true);
                $vote($ckey, $cdir, 'Custom rule: ' . CustomRules::describe((array)($spec['clauses'] ?? [])));
            }
        } catch (\Throwable $e) {
            // a malformed custom rule must never break the whole analysis
        }

        // ---- Confluence bonus + setup grade --------------------------------
        // Count independent evidence categories voting each way. Agreement
        // across 3+ categories is how a veteran recognises an A-setup.
        $catNet = [];
        foreach ($reasons as $r) {
            $cat = $this->rules[$r['key']]['category'] ?? 'other';
            if ($cat === 'meta') {
                continue;
            }
            $catNet[$cat] = ($catNet[$cat] ?? 0) + ($r['side'] === 'bullish' ? 1 : -1);
        }
        $bullCats = count(array_filter($catNet, fn($v) => $v > 0));
        $bearCats = count(array_filter($catNet, fn($v) => $v < 0));
        $confDir = 0;
        if ($bullCats >= 3 && $bullCats > $bearCats) {
            $confDir = 1;
            $vote('confluence_bonus', +1, "$bullCats independent evidence categories agree bullish");
        } elseif ($bearCats >= 3 && $bearCats > $bullCats) {
            $confDir = -1;
            $vote('confluence_bonus', -1, "$bearCats independent evidence categories agree bearish");
        }
        $alignedCats = $confDir > 0 ? $bullCats : ($confDir < 0 ? $bearCats : max($bullCats, $bearCats));
        $adxStrong = $adx['adx'][$i] !== null && $adx['adx'][$i] >= 25;
        $mtfAgrees = $confDir !== 0 && $mtfDir === $confDir;
        $grade = 'C';
        if ($alignedCats >= 4 && $mtfAgrees && $adxStrong) {
            $grade = 'A+';
        } elseif ($alignedCats >= 4 || ($alignedCats >= 3 && $mtfAgrees)) {
            $grade = 'A';
        } elseif ($alignedCats >= 3) {
            $grade = 'B';
        }

        // ---- Score: correlation-capped category aggregation -----------------
        //
        // Summing every rule counted the same evidence many times over. RSI,
        // Stochastic, Williams %R, CCI and MFI are one oscillator reading in
        // five costumes; in an oversold market all five fire and contribute
        // ~5.9 of weight for what a human would call a single observation. The
        // trend family behaves the same way, by construction.
        //
        // Each category's net vote is therefore clamped to its cap before the
        // categories are summed, so agreement ACROSS independent evidence
        // types is what moves the score - which is what the confluence bonus
        // was always trying to approximate.
        $catBreakdown = [];
        if ($this->modeNow() === 'category' && $this->categories !== []) {
            foreach ($catVotes as $cat => $net) {
                $meta = $this->categories[$cat] ?? null;
                $cap = $meta !== null ? (float)$meta['cap'] : (float)$this->cfg('category_cap_default', '2.5');
                $cw  = $meta !== null ? (float)$meta['weight'] : 1.0;
                $capped = max(-$cap, min($cap, $net)) * $cw;
                $score += $capped;
                $catBreakdown[$cat] = [
                    'raw'    => round($net, 3),
                    'capped' => round($capped, 3),
                    'cap'    => $cap,
                    'clipped'=> abs($net) > $cap,
                ];
            }
        } else {
            $score = $rawScore;
            foreach ($catVotes as $cat => $net) {
                $catBreakdown[$cat] = ['raw' => round($net, 3), 'capped' => round($net, 3),
                                       'cap' => null, 'clipped' => false];
            }
        }

        // ---- High-impact event caution window ------------------------------
        // A clean technical setup minutes before a Fed decision / CPI print is
        // a coin flip. Inside the window the score must clear thresholds
        // raised by 50%, and the setup grade is capped at B. Uses the
        // economic calendar already stored on this server.
        $buyT = $this->buyThreshold;
        $sellT = $this->sellThreshold;
        // Regime first: what a setup has to prove depends on the market it is
        // being taken in. Whipsaw demands a great deal more than a grind.
        if ($regPolicy['threshold'] != 1.0) {
            $buyT *= $regPolicy['threshold'];
            $sellT *= $regPolicy['threshold'];
            $reasons[] = [
                'rule'   => 'Market regime',
                'key'    => 'regime',
                'side'   => $score >= 0 ? 'bearish' : 'bullish',
                'weight' => 0.0,
                'detail' => sprintf('%s (%.0f%% certain). %s Thresholds %s %.0f%%.',
                    Regime::label($regime['name']), $regime['certainty'] * 100,
                    $regPolicy['why'] ?? '',
                    $regPolicy['threshold'] > 1 ? 'raised' : 'lowered',
                    abs($regPolicy['threshold'] - 1) * 100),
            ];
        }
        $cautionEvent = null;
        if (!$this->dry && $this->cfg('event_caution_enabled', '1') === '1') {
            $hrs = max(0, min(48, (int)$this->cfg('event_caution_hours', '2')));
            if ($hrs > 0) {
                $evStmt = Database::pdo()->prepare(
                    "SELECT title, event_time FROM calendar_events
                     WHERE impact = 'High' AND event_time BETWEEN ? AND ?
                     ORDER BY event_time LIMIT 1"
                );
                $evStmt->execute([time(), time() + $hrs * 3600]);
                $ev = $evStmt->fetch();
                $evStmt->closeCursor();
                // LIMIT 1 fetched once is not a finished statement - see the
                // note on PRAGMA busy_timeout in Database.php. Left open, this
                // holds a read transaction until $evStmt falls out of scope,
                // and the saveState() write at the end of this same request is
                // then a read->write upgrade, which SQLite refuses WITHOUT
                // consulting the busy handler. Measured: 30 of 40 concurrent
                // analyses failed with "database is locked" while cron wrote.
                $evStmt->closeCursor();
                if ($ev) {
                    $cautionEvent = (string)$ev['title'];
                    $buyT *= 1.5;
                    $sellT *= 1.5;
                    $mins = (int)round(((int)$ev['event_time'] - time()) / 60);
                    $reasons[] = [
                        'rule'   => 'High-impact event caution',
                        'key'    => 'event_caution',
                        'side'   => $score >= 0 ? 'bearish' : 'bullish',
                        'weight' => 0.0,
                        'detail' => "\"$cautionEvent\" in ~{$mins} min - thresholds raised 50%, grade capped at B until it passes",
                    ];
                }
            }
        }

        // ---- Flash-move circuit breaker ------------------------------------
        // When cron sees a violent market-wide move, every pair trades on
        // reflex for a while and the rule set is reading a chart that is
        // reacting to something it cannot see. Thresholds up 50%, grade
        // capped, and the reason says so on the card rather than the setup
        // quietly disappearing.
        //
        // Regime detection handles the ordinary case - it scales the bar by
        // how the instrument itself is behaving. This is for the case regime
        // cannot reach: a shock that has just happened and has not finished
        // arriving in the indicators yet.
        // ON-CHAIN SETTLEMENT, AS A CAUTION AND NOTHING MORE.
        //
        // Free chain data can say that unusual value is moving. It cannot say
        // whose, or which way - that needs labelled addresses, which is the
        // product the paid providers sell. So this raises the bar rather than
        // taking a side, which is the strongest claim the data supports.
        //
        // Same family as the volatility breaker below and deliberately weaker:
        // a 25% raise against that one's 50%, because a busy chain is a softer
        // signal than a price shock that has already happened.
        //
        // Never in dry mode. The backtester would fetch TODAY's chain reading
        // and apply it to a bar from three weeks ago, which is not a caution,
        // it is a leak of the future into the past.
        $chainCaution = false;
        if (!$this->dry && $this->cfg('onchain_caution', '0') === '1') {
            try {
                $chain = OnChain::reading();
                if (is_array($chain) && !empty($chain['surge'])) {
                    $chainCaution = true;
                    $buyT *= 1.25;
                    $sellT *= 1.25;
                    $reasons[] = [
                        'rule'   => 'On-chain settlement surge',
                        'key'    => 'onchain_caution',
                        'side'   => $score >= 0 ? 'bearish' : 'bullish',
                        'weight' => 0.0,
                        'detail' => sprintf(
                            'Bitcoin settled %.2fx its 30-day median value on-chain in the last '
                            . 'day. Size is moving; the chain does not say whose or which way, so '
                            . 'this raises the bar by 25%% rather than taking a side.',
                            (float)$chain['ratio']),
                    ];
                }
            } catch (\Throwable $e) {
                // A chain feed that is down changes nothing. That is the point
                // of it being a caution rather than a vote.
            }
        }

        $circuitOn = false;
        if (!$this->dry && $this->cfg('panic_enabled', '1') === '1'
            && time() < (int)$this->cfg('panic_until', '0')) {
            $circuitOn = true;
            $buyT *= 1.5;
            $sellT *= 1.5;
            $mins = (int)ceil(((int)$this->cfg('panic_until', '0') - time()) / 60);
            $reasons[] = [
                'rule'   => 'Volatility circuit breaker',
                'key'    => 'volatility_circuit',
                'side'   => $score >= 0 ? 'bearish' : 'bullish',
                'weight' => 0.0,
                'detail' => 'Violent market-wide move detected - thresholds raised 50% and grade '
                          . "capped for ~{$mins} more min while volatility settles",
            ];
        }

        // ---- Multi-timeframe alignment: asymmetric thresholds ---------------
        //
        // The ladder's opinion is already one vote among fifty-four. That is
        // not enough on its own: a long fired into a daily downtrend is not a
        // slightly worse long, it is a different trade, and enough short-term
        // momentum can outvote the ladder.
        //
        // But a veto is the wrong instrument. Plenty of this engine's rules
        // are mean-reverting by design - oversold RSI, a lower-band touch, a
        // golden-zone pullback - and an oversold bounce inside a downtrend is
        // a real trade, not a mistake. Banning the whole class throws away
        // profitable setups along with the reckless ones.
        //
        // So the ladder raises the bar rather than closing the door: going
        // against it costs extra score, in proportion to how much of the
        // ladder disagrees. Weak setups against the trend disappear; strong
        // ones survive and get taken. The outright veto is kept for the one
        // case that deserves it - a trend that is clean on every rung, which
        // is never worth fading on a hunch.
        $mtfPenalty = 0.0;
        if ($mtf['frames'] && $this->cfg('mtf_gate_enabled', '1') === '1' && $mtfBias != 0.0) {
            $from = max(0.05, min(1.0, (float)$this->cfg('mtf_gate_bias', '0.35')));
            if (abs($mtfBias) >= $from) {
                // 0 at the trigger point, rising to the full setting when the
                // ladder is unanimous.
                $span = max(0.01, 1.0 - $from);
                $mtfPenalty = min(1.5, (float)$this->cfg('mtf_penalty', '0.6'))
                            * (abs($mtfBias) - $from) / $span;
                if ($mtfBias < 0) {
                    $buyT *= 1.0 + $mtfPenalty;        // longs cost more
                } else {
                    $sellT *= 1.0 + $mtfPenalty;       // sellT is negative
                }
                $reasons[] = [
                    'rule'   => 'Higher-timeframe alignment',
                    'key'    => 'mtf_alignment',
                    'side'   => $mtfBias < 0 ? 'bearish' : 'bullish',
                    'weight' => 0.0,
                    'detail' => sprintf(
                        'The frames above lean %s (%+.2f: %s), so a %s here has to clear a threshold '
                        . 'raised %.0f%% - going with the higher timeframes is cheap, going against '
                        . 'them has to be earned',
                        $mtfBias < 0 ? 'down' : 'up', $mtfBias, Mtf::describe($mtf),
                        $mtfBias < 0 ? 'BUY' : 'SELL', $mtfPenalty * 100
                    ),
                ];
            }
        }

        // ---- Verdict -------------------------------------------------------
        if ($score >= $buyT) {
            $signal = 'BUY';
        } elseif ($score <= $sellT) {
            $signal = 'SELL';
        } else {
            $signal = 'NEUTRAL';
        }

        // WHAT IT WANTED, BEFORE ANY GATE RAN.
        //
        // Ten different checks below can stand a verdict down, and once one
        // does, the fact that the engine ever wanted to trade is gone. Kept
        // here so a setup the engine turned down can be recorded and settled -
        // see the shadow block near the end. Captured after hysteresis so a
        // held-open verdict counts as wanted, which it is.
        // Hysteresis: an active BUY/SELL holds until the score decays below a
        // fraction of its entry threshold, so 2.1 -> 1.9 -> 2.2 wobbles do not
        // flip the verdict (and re-fire alerts) three times.
        $band = max(0.0, min(0.95, (float)$this->cfg('flip_exit_band', '0.5')));
        if ($signal === 'NEUTRAL' && $band > 0) {
            $prev = $this->dry ? $this->btPrev : $this->lastVerdict($symbol, $tf);
            if ($prev === 'BUY' && $score >= $buyT * $band) {
                $signal = 'BUY';
                $reasons[] = ['rule' => 'Signal hysteresis', 'key' => 'hysteresis', 'side' => 'bullish', 'weight' => 0.0,
                    'detail' => sprintf('Holding the active BUY: score %.2f is inside the exit band (flips off below %.2f)', $score, $buyT * $band)];
            } elseif ($prev === 'SELL' && $score <= $sellT * $band) {
                $signal = 'SELL';
                $reasons[] = ['rule' => 'Signal hysteresis', 'key' => 'hysteresis', 'side' => 'bearish', 'weight' => 0.0,
                    'detail' => sprintf('Holding the active SELL: score %.2f is inside the exit band (flips off above %.2f)', $score, $sellT * $band)];
            }
        }

        // WHAT IT WANTED, BEFORE ANY GATE RAN.
        //
        // Ten checks below can stand a verdict down, and once one does, the
        // fact that the engine ever wanted to trade is gone. Kept here - after
        // hysteresis, so a held-open verdict counts as wanted, which it is -
        // so a setup the engine turned down can still be recorded and settled.
        // See the shadow block further down.
        $wantedBefore = $signal;
        $reasonsBefore = count($reasons);

        // The gates below are the FINAL word on the verdict. They deliberately
        // run after hysteresis: hysteresis exists to smooth score wobble around
        // the threshold, not to override a filter that has decided this setup
        // should not be traded at all.

        // Minimum confluence: a verdict must be backed by at least this many
        // independent evidence categories. With capping in place a single
        // category can no longer reach the threshold alone, but this makes the
        // requirement explicit rather than a side effect of the numbers.
        $minCats = max(1, (int)($this->profile['min_categories']
            ?? $this->cfg('min_aligned_categories', '2')));
        // A chopping market needs more independent agreement than a trending
        // one before a directional call is worth making. Capped so the regime
        // can tighten the requirement but never make it unmeetable.
        $minCats = min(5, $minCats + (int)$regPolicy['categories']);
        if ($signal !== 'NEUTRAL' && $minCats > 1) {
            $agreeing = $signal === 'BUY' ? $bullCats : $bearCats;
            if ($agreeing < $minCats) {
                $reasons[] = ['rule' => 'Confluence requirement', 'key' => 'min_categories',
                    'side' => $signal === 'BUY' ? 'bearish' : 'bullish', 'weight' => 0.0,
                    'detail' => "Only $agreeing evidence category(ies) support this - $minCats required. "
                              . 'One indicator is an opinion; agreement across independent evidence is the edge.'];
                $signal = 'NEUTRAL';
            }
        }

        // Never fade a clean trend.
        //
        // The raised threshold above handles the ordinary case. This is the
        // one it cannot: every rung of the ladder pointing the same way. A
        // setup strong enough to clear even a penalised threshold against
        // that is not a brave contrarian call, it is a short-term signal
        // arguing with a market that has already decided - so it is stood
        // down outright.
        //
        // The single way past it is a reversal thesis this install has
        // actually measured: a turn-calling rule with enough closed trades
        // behind it to show a positive expectancy. On a fresh install nothing
        // qualifies and the block holds, which is the correct default rather
        // than a limitation.
        $mtfOverride = null;
        if ($signal !== 'NEUTRAL' && $mtf['frames'] && $mtfPenalty > 0) {
            $wantDir = $signal === 'BUY' ? 1 : -1;
            $hard = max(0.5, min(1.0, (float)$this->cfg('mtf_gate_hard', '0.85')));
            if ($mtfBias * $wantDir < 0 && abs($mtfBias) >= $hard) {
                if (!$this->dry) {
                    $mtfOverride = Mtf::validatedReversal(
                        $reasons, $signal, $tf,
                        max(5, (int)$this->cfg('mtf_reversal_min_n', '25')),
                        (float)$this->cfg('mtf_reversal_min_r', '0.15')
                    );
                }
                if ($mtfOverride !== null) {
                    $reasons[] = ['rule' => 'Validated counter-trend setup', 'key' => 'mtf_reversal',
                        'side' => $signal === 'BUY' ? 'bullish' : 'bearish', 'weight' => 0.0,
                        'detail' => sprintf(
                            'Every higher timeframe points the other way (%+.2f), but "%s" has averaged '
                            . '%+.2fR across %d verified %s trades on this install (%.0f%% hit rate), so '
                            . 'the reversal is taken on measured evidence rather than on the pattern alone',
                            $mtfBias, $mtfOverride['name'], $mtfOverride['avg_r'],
                            $mtfOverride['n'], $tf, $mtfOverride['winrate']
                        )];
                } else {
                    $reasons[] = ['rule' => 'Higher-timeframe alignment', 'key' => 'mtf_gate',
                        'side' => $signal === 'BUY' ? 'bearish' : 'bullish', 'weight' => 0.0,
                        'detail' => sprintf(
                            'Every timeframe above leans %s (%+.2f: %s). A %s here fades a trend that is '
                            . 'clean on every frame, and no reversal setup with a measured edge on this '
                            . 'install supports it, so it is stood down.',
                            $mtfBias > 0 ? 'up' : 'down', $mtfBias, Mtf::describe($mtf), $signal
                        )];
                    $signal = 'NEUTRAL';
                }
            }
        }

        // Session filter: hours whose measured expectancy is clearly negative.
        //
        // Crypto trades around the clock but follow-through does not - thin
        // books between sessions resolve setups differently. The buckets were
        // already measured and displayed; this makes acting on them an opt-in
        // choice rather than something the admin has to infer and hard-code.
        if ($signal !== 'NEUTRAL' && !$this->dry
            && $this->cfg('session_filter_enabled', '0') === '1') {
            $tb = json_decode($this->cfg('time_buckets', ''), true);
            $hour = (int)gmdate('G');
            $b = $tb['hour'][$hour] ?? null;
            $minN = max(10, (int)$this->cfg('session_filter_min_n', '20'));
            $floor = (float)$this->cfg('session_filter_min_r', '-0.15');
            if (is_array($b) && (int)($b['n'] ?? 0) >= $minN
                && $b['avg_r'] !== null && (float)$b['avg_r'] < $floor) {
                $reasons[] = ['rule' => 'Session filter', 'key' => 'session_filter',
                    'side' => $signal === 'BUY' ? 'bearish' : 'bullish', 'weight' => 0.0,
                    'detail' => sprintf('Signals opened in the %02d:00 UTC hour have averaged %+.2fR '
                        . 'across %d verified trades on this install - below the %.2fR floor, so this '
                        . 'setup is stood down', $hour, (float)$b['avg_r'], (int)$b['n'], $floor)];
                $signal = 'NEUTRAL';
            }
        }

        // Chop gate: the ADX regime filter only dampens weights, so a
        // consolidating market could still produce a confident breakout call.
        // The Choppiness Index says plainly when price is going nowhere, and
        // breakouts taken there fail at a far higher rate.
        //
        // A+ is exempt by default (chop_gate_a_plus_exempt) on the reasoning
        // that a setup strong enough to earn the top grade should survive a
        // choppy read - a reasonable-sounding rule that was never once
        // checked against what this install's own A+ setups actually did in
        // chop. It can be, now: Engine lab shows real settled A+ signals
        // published in chop against real settled A+ signals published
        // outside it (Outcomes::chopExemptionStats()), so turning this off is
        // a decision made on this install's evidence rather than on the
        // reasoning alone.
        $chopNow = $chop[$i] ?? null;
        $chopLimit = (float)$this->cfg('chop_limit', '61.8');
        $chopAPlusExempt = $this->cfg('chop_gate_a_plus_exempt', '1') === '1';
        if ($signal !== 'NEUTRAL' && $this->cfg('chop_gate_enabled', '1') === '1'
            && $chopNow !== null && $chopNow >= $chopLimit
            && !($chopAPlusExempt && $grade === 'A+')) {
            $reasons[] = ['rule' => 'Chop filter', 'key' => 'chop_gate',
                'side' => $signal === 'BUY' ? 'bearish' : 'bullish', 'weight' => 0.0,
                'detail' => sprintf('Choppiness index %.1f (>= %.1f) - the market is consolidating, '
                    . 'so directional setups are stood down until it resolves', $chopNow, $chopLimit)];
            $signal = 'NEUTRAL';
        }

        // Liquidity gate: a two-ATR target is not reachable on a pair that
        // turns over a few tens of thousands of dollars a day without moving
        // the market yourself. The engine used to signal on those happily.
        $thinNote = null;
        if ($signal !== 'NEUTRAL' && !$this->dry && $this->md !== null) {
            $minVol = (float)$this->cfg('min_quote_volume', '250000');
            if ($minVol > 0) {
                $qv = $this->md->quoteVolume24h($symbol);
                if ($qv !== null && $qv < $minVol) {
                    $thinNote = sprintf('24h turnover is only $%s - below the $%s liquidity floor, '
                        . 'targets are not reachable without slippage',
                        number_format($qv), number_format($minVol));
                    $reasons[] = ['rule' => 'Liquidity filter', 'key' => 'liquidity_gate',
                        'side' => $signal === 'BUY' ? 'bearish' : 'bullish', 'weight' => 0.0,
                        'detail' => $thinNote];
                    $signal = 'NEUTRAL';
                }
            }
        }

        // Cooldown after a stop-out: the market just proved this direction
        // wrong on this pair/timeframe - stand down for a few bars instead of
        // instantly re-issuing the same call (no revenge trades).
        $cooldownBars = max(0, min(100, (int)$this->cfg('cooldown_bars', '4')));
        if (!$this->dry && $signal !== 'NEUTRAL' && $cooldownBars > 0) {
            $tfSec = MarketData::TF_SECONDS[$tf] ?? 3600;
            $cd = Database::pdo()->prepare(
                "SELECT `signal`, closed_at FROM signals
                 WHERE symbol = ? AND tf = ? AND outcome = 'invalid'
                 ORDER BY closed_at DESC LIMIT 1"
            );
            $cd->execute([$symbol, $tf]);
            $stopped = $cd->fetch();
            $cd->closeCursor();   // same read->write upgrade trap as above
            if ($stopped && $stopped['signal'] === $signal
                && (int)$stopped['closed_at'] >= time() - $cooldownBars * $tfSec) {
                $barsLeft = (int)ceil(((int)$stopped['closed_at'] + $cooldownBars * $tfSec - time()) / $tfSec);
                $reasons[] = ['rule' => 'Stop-out cooldown', 'key' => 'cooldown',
                    'side' => $signal === 'BUY' ? 'bearish' : 'bullish', 'weight' => 0.0,
                    'detail' => "A $signal on this pair was just stopped out - standing down for ~$barsLeft more candle(s) instead of re-entering the same call"];
                $signal = 'NEUTRAL';
            }
        }


        if (($cautionEvent !== null || $circuitOn) && ($grade === 'A' || $grade === 'A+')) {
            $grade = 'B';
        }

        // ---- Extension filter ----------------------------------------------
        //
        // "Do not chase" is something every trader says and almost no system
        // encodes. Stated mechanically: when price is already a long way from
        // its own mean, the move being joined has mostly happened, and what is
        // left is the part that gives back. The rule set argues the opposite
        // by construction - trend rules fire hardest exactly when a move is
        // extended - so without this the engine is most confident at the
        // worst moment to start.
        //
        // Only initiation is blocked, and only in the direction of the
        // stretch: buying into a market that has run up is chasing, while
        // selling into it is a mean-reversion trade the rule set is entitled
        // to make.
        if ($signal !== 'NEUTRAL' && $atrNow !== null && $atrNow > 0
            && $this->cfg('extension_gate_enabled', '1') === '1'
            && isset($ema20[$i]) && $ema20[$i] !== null) {
            $stretch = ($price - (float)$ema20[$i]) / $atrNow;
            $limit = max(0.5, (float)$this->cfg('extension_max_atr', '2.5'));
            $withStretch = ($signal === 'BUY' && $stretch > 0) || ($signal === 'SELL' && $stretch < 0);
            if ($withStretch && abs($stretch) >= $limit) {
                $reasons[] = [
                    'rule'   => 'Extension filter',
                    'key'    => 'extension_gate',
                    'side'   => $signal === 'BUY' ? 'bearish' : 'bullish',
                    'weight' => 0.0,
                    'detail' => sprintf(
                        'Price is %.1f ATR %s its 20-EMA - the move this setup wants to join has '
                        . 'largely happened, and entering %.1f ATR late puts the stop under the '
                        . 'part that gives back. Above the %.1f ATR limit, no new entry.',
                        abs($stretch), $stretch > 0 ? 'above' : 'below', abs($stretch), $limit),
                ];
                $signal = 'NEUTRAL';
            }
        }

        // ---- Shadow: the setup the engine turned down -----------------------
        //
        // Two ways a setup ends up here, and both are things the engine cannot
        // otherwise see:
        //
        //   a gate vetoed it   - the score cleared the threshold and a filter
        //                        stood it down. Was the filter right? A chop
        //                        gate that keeps blocking winners is a setting
        //                        to loosen, and nothing in a record made only
        //                        of published signals would ever say so.
        //   a near miss        - the score never reached the threshold but got
        //                        close. Is the threshold set where the edge
        //                        actually is?
        //
        // blocked_by is the key of the reason appended immediately before the
        // stand-down, which is exactly the gate that did it: once a gate turns
        // the verdict NEUTRAL every later gate skips itself, so the last
        // reason added is always the one that decided.
        $shadowDir = '';
        $shadowWhy = '';
        if ($signal === 'NEUTRAL' && $this->cfg('shadow_enabled', '1') === '1') {
            if ($wantedBefore !== 'NEUTRAL') {
                $shadowDir = $wantedBefore;
                $last = count($reasons) > $reasonsBefore ? end($reasons) : null;
                $shadowWhy = is_array($last) ? (string)($last['key'] ?? 'gate') : 'gate';
            } else {
                // Near miss: how close to the threshold is worth recording.
                $nearBand = max(0.1, min(0.99, (float)$this->cfg('shadow_near_band', '0.6')));
                if ($score >= $buyT * $nearBand) {
                    $shadowDir = 'BUY';
                    $shadowWhy = 'threshold';
                } elseif ($score <= $sellT * $nearBand) {
                    $shadowDir = 'SELL';
                    $shadowWhy = 'threshold';
                }
            }
        }

        // ---- Trade levels: entry / stop loss / take profits ----------------
        // ATR-based with structure awareness; every multiplier is
        // admin-customizable (Settings > Trade levels). Educational only.
        $levels = null;
        // SIDE, not verdict.
        //
        // A setup the engine turned down still needs the levels it WOULD have
        // published, or its shadow cannot be settled in R and is worth
        // nothing to the learning. Built by the same code with the same
        // multipliers, because a shadow measured on different levels than a
        // real signal would have used answers a question nobody asked.
        $side = $signal !== 'NEUTRAL' ? $signal : $shadowDir;
        if ($this->cfg('levels_enabled', '1') === '1'
            && $side !== '' && $atrNow !== null && $atrNow > 0) {
            // sl_atr_mult is the only multiplier of this pair that still sets a
            // real price: it fixes the stop distance, and every published
            // target is built from THAT distance (see the "ratio IS the
            // ladder" note further down). tp1_atr_mult survives only as the
            // threshold for the chase guard below - "has price already run
            // most of the way to where a first target would sit" - which is
            // an early-warning read, not a price the site publishes. TP2/TP3
            // multipliers were dropped from here entirely: nothing downstream
            // ever read them for anything, live or backtested (confirmed by
            // grep - the old $tp2M/$tp3M reached no other line in this file),
            // so keeping them was two numbers an operator could tune with no
            // effect anywhere, which is worse than not offering them.
            $slM  = max(0.1, (float)$this->cfg('sl_atr_mult', '1.5'));
            $tp1M = max(0.1, (float)$this->cfg('tp1_atr_mult', '1.0'));
            if ($this->levelOverride !== null) {
                // An explicit test geometry - the A/B tool or the level-search
                // grid - always wins. It exists specifically to hold sl/tp1
                // fixed across every symbol in a replay, and letting a coin's
                // own override slip back in here would measure a different
                // geometry on that coin than the one being compared.
                $slM  = max(0.1, (float)$this->levelOverride['sl']);
                $tp1M = max(0.1, (float)($this->levelOverride['tp1'] ?? $tp1M));
            } else {
                // The site's learned per-timeframe stop, when the optimiser
                // has found a better one for this timeframe than the global
                // default - but never over a per-coin override the operator
                // set deliberately. levelsFor() only knows the timeframe, not
                // the symbol, so applying it unconditionally silently
                // discarded a coin's own "Stop loss (ATR multiple)" override
                // the moment its timeframe had a learned value on file - the
                // symbol page kept showing the coin's number as active while
                // the engine used the timeframe's instead. The optimiser's
                // stored tp1 travels along for the chase-guard threshold and
                // for levelsFor()'s storage shape, but - same reason as above
                // - it does not change what gets published.
                $learned = Backtest::levelsFor($tf);
                if ($learned !== null && !array_key_exists('sl_atr_mult', $this->settingOverride)) {
                    $slM  = max(0.1, (float)$learned['sl']);
                    $tp1M = max(0.1, (float)($learned['tp1'] ?? $tp1M));
                }
            }
            // Regime sizing. A stop that survives a grind gets hit on the way
            // to being right in a volatile trend, and targets that a trending
            // market reaches are fantasy in a whipsawing one. Applied after
            // the learned per-timeframe multiplier, because that describes
            // the timeframe and this describes today.
            $slM  *= $regPolicy['stop'];
            $tp1M *= $regPolicy['target'];
            $dir = $side === 'BUY' ? 1 : -1;
            $entry = $price;
            $sl = $entry - $dir * $slM * $atrNow;
            // Structure-aware stop: park it just beyond the nearby swing level.
            if ($side === 'BUY' && $support !== null && $support < $entry
                && ($entry - $support) <= 2 * $slM * $atrNow) {
                $sl = min($sl, $support - 0.25 * $atrNow);
            }
            if ($side === 'SELL' && $resistance !== null && $resistance > $entry
                && ($resistance - $entry) <= 2 * $slM * $atrNow) {
                $sl = max($sl, $resistance + 0.25 * $atrNow);
            }
            // Cascade-aware stop. A stop resting just short of a pool of
            // forced liquidations is the worst place to keep one: the cascade
            // fills through it on its way to the level, and the reversal that
            // follows happens without us. If a strong cluster sits just
            // beyond the stop, step around it - but only while the cluster is
            // close enough that doing so does not rewrite the trade's risk.
            $liqStopUsed = null;
            $liqMinW = max(0.0, min(1.0, (float)$this->cfg('liq_stop_min_weight', '0.5')));
            if ($liq !== null) {
                $reach = 0.6 * $atrNow;
                foreach ($liq as $cluster) {
                    if ($cluster['weight'] < $liqMinW) {
                        continue;
                    }
                    $cp = (float)$cluster['price'];
                    if ($side === 'BUY' && $cluster['side'] === 'long'
                        && $cp < $sl && ($sl - $cp) <= $reach) {
                        $sl = $cp - 0.25 * $atrNow;
                        $liqStopUsed = $cp;
                        break;
                    }
                    if ($side === 'SELL' && $cluster['side'] === 'short'
                        && $cp > $sl && ($cp - $sl) <= $reach) {
                        $sl = $cp + 0.25 * $atrNow;
                        $liqStopUsed = $cp;
                        break;
                    }
                }
            }
            $risk = abs($entry - $sl);

            // Can this trade pay for itself?
            //
            // A stop is a distance, and trading costs are a distance too. When
            // the stop is tighter than the round trip, the fees are worth more
            // than everything being risked and no sequence of price moves
            // makes the trade profitable - a measured example from this
            // install: a 3m setup with the stop 0.069% away and a 0.1% round
            // trip, where costs came to 145% of the risk. It was published
            // showing "1 : 1.3" and settled at -2.45R.
            //
            // This is structural, not bad luck, and it is why fast-timeframe
            // signals were losing money while the backtest looked healthy.
            // Costs are expressed in R and the setup is stood down when they
            // eat more of the risk than an operator is willing to give away.
            $costPct = max(0.0, (float)$this->cfg('round_trip_cost_pct', '0.1')) / 100;
            $costR = $risk > 0 && $entry > 0 ? $entry * $costPct / $risk : 0.0;
            $maxCostR = max(0.0, (float)$this->cfg('max_cost_r', '0.33'));
            if ($maxCostR > 0 && $costR > $maxCostR) {
                $reasons[] = [
                    'rule'   => 'Cost floor',
                    'key'    => 'cost_floor',
                    'side'   => $side === 'BUY' ? 'bearish' : 'bullish',
                    'weight' => 0.0,
                    'detail' => sprintf(
                        'The stop is %.3f%% away but a round trip costs %.2f%%, so fees would take '
                        . '%.0f%% of everything risked. Above the %.0f%% ceiling this setup cannot '
                        . 'pay for itself, whatever price does, so it is not published.',
                        $risk / $entry * 100, $costPct * 100, $costR * 100, $maxCostR * 100),
                ];
                if ($signal !== 'NEUTRAL') {
                    // Refused by a gate that only runs once the plan exists.
                    // That is still a setup the engine wanted, so it becomes a
                    // shadow with the very plan it was about to publish.
                    $shadowDir = $signal;
                    $shadowWhy = 'cost_floor';
                    $signal = 'NEUTRAL';
                } else {
                    $shadowDir = '';   // a shadow that fails this is not evidence
                }
            }

            // Can price physically get there in the time allowed?
            //
            // Every target is a multiple of ATR and every time stop is a bar
            // count, and until now nothing checked that the two were
            // compatible. A target three ATR away on an instrument that
            // typically travels one and a half inside the window is not
            // ambitious, it is a trade that expires by construction - and it
            // settles as "went nowhere", which reads like a neutral outcome
            // rather than a target that was never reachable.
            //
            // Measured against this instrument's own recent history, in the
            // direction being traded, over windows the same length as the time
            // stop. Not enough history to say means the gate stands aside: a
            // guard that fires on ignorance is worse than no guard.
            // A SCORE BAND THIS INSTALL HAS MEASURED AS LOSING MONEY.
            //
            // THE THRESHOLD WAS A FLOOR AND NOTHING WAS A CEILING.
            //
            // buy_threshold says how much agreement is needed before a setup
            // is worth publishing. It says nothing about too much. The engine
            // already measures its own win rate per |score| band and already
            // uses that measurement - but only to LOWER THE CONFIDENCE NUMBER
            // PRINTED BESIDE THE CALL. It would take a band it had measured at
            // 30% and publish those setups anyway, as its strongest ones,
            // because they scored highest.
            //
            // That is how raising the threshold can make a site worse. Asking
            // for a higher score does not ask for a better trade unless score
            // and outcome move together, and they need not: a very high score
            // means every indicator agrees, which is most often true when the
            // move is mature and the entry is late. On the install this was
            // written against, the middle band ran 65% and the top band 31%,
            // so tightening the floor filtered TOWARDS the losing end.
            //
            // Off by default, and it cannot act on a hunch: a band has to have
            // at least score_band_min_n settled signals, and it is judged on
            // the Wilson lower bound, so a thin unlucky run does not silence a
            // whole band. A refusal here becomes a shadow like any other, so
            // "what refusing cost you" reports whether this gate was right -
            // if the band recovers, the operator can see it and switch back.
            if ($side !== '' && $signal !== 'NEUTRAL'
                && $this->cfg('score_band_gate', '0') === '1') {
                $bandCalib = json_decode($this->cfg('confidence_calib', '{}'), true);
                $bandMinN = max(20, (int)$this->cfg('score_band_min_n', '30'));
                $bandFloor = max(0.0, min(100.0, (float)$this->cfg('score_band_min_winrate', '45')));
                // Expectancy the band has to be below as well. 0 means "only
                // bands that actually lose money qualify to be stood down".
                $bandMaxR = (float)$this->cfg('score_band_max_r', '0');
                foreach ((array)($bandCalib['buckets'] ?? []) as $b) {
                    if ((int)($b['n'] ?? 0) < $bandMinN
                        || abs($score) < (float)($b['lo'] ?? 0)
                        || abs($score) >= (float)($b['hi'] ?? 0)) {
                        continue;
                    }
                    // TWO CONDITIONS, AND ONE OF THEM IS MONEY.
                    //
                    // The first draft of this judged the band on its Wilson
                    // lower bound against an absolute floor. That is far too
                    // harsh at the sample sizes a real install has: measured
                    // here, a band winning 65% over 20 trades has a lower
                    // bound of 43.3%, so a 45% floor would have stood down the
                    // BEST band on the site. A guard that refuses good setups
                    // is worse than no guard.
                    //
                    // So the band has to fail on both counts: it loses money
                    // on average, and it wins less often than the floor. Two
                    // weak-ish signals that agree, rather than one conservative
                    // statistic used past what it can carry.
                    $bandRate = $b['winrate'] ?? null;
                    $bandR = $b['avg_r'] ?? null;
                    if ($bandRate === null || $bandR === null
                        || (float)$bandR >= $bandMaxR || (float)$bandRate >= $bandFloor) {
                        break;
                    }
                    $reasons[] = [
                        'rule'   => 'Score band record',
                        'key'    => 'score_band',
                        'side'   => $side === 'BUY' ? 'bearish' : 'bullish',
                        'weight' => 0.0,
                        'detail' => sprintf(
                            'A score of %.2f puts this in the %.1f-%.1f band. Over %d settled '
                            . 'signals on this install that band has won %.0f%% of the time and '
                            . 'averaged %+.2fR - it wins less often than the %.0f%% floor AND it '
                            . 'loses money. A stronger score has not meant a better trade here, so '
                            . 'the setup is not published.',
                            abs($score), (float)$b['lo'], (float)$b['hi'], (int)$b['n'],
                            (float)$bandRate, (float)$bandR, $bandFloor),
                    ];
                    $shadowDir = $signal;
                    $shadowWhy = 'score_band';
                    $signal = 'NEUTRAL';
                    break;
                }
            }

            $minReach = max(0.0, min(1.0, (float)$this->cfg('target_min_reach', '0.35')));
            if ($side !== '' && $minReach > 0) {
                $tsBarsChk = max(1, (int)round(
                    (int)$this->cfg('time_stop_bars', '24') * $regPolicy['time_stop']));
                // ONE R, WHICH IS THE SMALLEST TARGET ANY SIGNAL CARRIES.
                //
                // This used to measure the second ATR target, which is no
                // longer a published number: the targets stand at one, two and
                // three times the risk now, and the type below says which of
                // them the setup is expected to reach. What still has to be
                // true of every call, before any of that, is that the coin can
                // cover its own stop distance in the time it is given - a
                // setup that cannot make 1R is not a trade at any ratio.
                $reach = Indicators::reachProbability(
                    $highs, $lows, $closes, $slM * $atrNow, $tsBarsChk, $side === 'BUY');
                if ($reach !== null && $reach < $minReach) {
                    $reasons[] = [
                        'rule'   => 'Target reachability',
                        'key'    => 'target_reach',
                        'side'   => $side === 'BUY' ? 'bearish' : 'bullish',
                        'weight' => 0.0,
                        'detail' => sprintf(
                            'The first target - one times the risk - sits %.2f ATR away and the '
                            . 'time stop is %d candles. On this pair, price has covered that ground '
                            . 'inside a window that long in only %.0f%% of the last %d - below the '
                            . '%.0f%% floor, so the setup is not published as a trade that could '
                            . 'reach even its smallest target.',
                            $slM, $tsBarsChk, $reach * 100, min(300, count($closes) - $tsBarsChk - 1),
                            $minReach * 100),
                    ];
                    if ($signal !== 'NEUTRAL') {
                        $shadowDir = $signal;
                        $shadowWhy = 'target_reach';
                        $signal = 'NEUTRAL';
                    } else {
                        $shadowDir = '';
                    }
                }
            }
        }

        // The plan itself, for a verdict that survived every gate above - and
        // for a setup that did not, because a refused setup without the plan it
        // would have published cannot be settled in R and teaches nothing. The
        // two gates just above stand a verdict down only AFTER the stop
        // distance is known, so this runs for their shadows as well.
        $planSide = $signal !== 'NEUTRAL' ? $signal : $shadowDir;
        if ($levels === null && $planSide !== ''
            && $this->cfg('levels_enabled', '1') === '1' && $atrNow !== null && $atrNow > 0) {
            // Entry ZONE, not a single price. The signal fires at the close of
            // a bar but the reader acts minutes later; publishing one exact
            // number implies a fill nobody actually got, and every published
            // R was measured from it. The zone states the tolerance honestly.
            $zoneM = max(0.0, (float)$this->cfg('entry_zone_atr', '0.25'));

            // Round by MAGNITUDE, not to a fixed six decimal places.
            //
            // Six was fine for the coins this was written against and silently
            // destroyed the sub-cent ones. A pair at 0.00002412 rounds entry
            // and a stop of 0.00002365 to the same 0.000024, so the published
            // risk is zero and the stop sits exactly on the entry - it is hit
            // by the first tick, on every signal, for ever. Below 0.0000005 it
            // is worse than that: the entry itself rounds to 0. That is the
            // "stopped out with nothing happening" on every memecoin.
            //
            // Keeping a constant number of SIGNIFICANT digits instead gives
            // BTC two decimals and PEPE twelve, which is what each of them
            // actually needs.
            // The exchange's own tick beats any rule of thumb when it is
            // known. SHIB quotes to eight places, so publishing twelve is four
            // digits of a price nobody can enter - the number looks precise
            // and is partly noise. Cached for a day per symbol, and the
            // magnitude rule stands in whenever it is unavailable: an offline
            // exchangeInfo must not cost the plan its levels.
            $dec = self::priceDecimals($entry);
            if ($this->md !== null) {
                try {
                    $tick = $this->md->tickSize($symbol);
                    if ($tick !== null && $tick > 0) {
                        $tickDec = max(0, min(14, (int)round(-log10($tick))));
                        // Only ever ADD precision beyond the tick when the tick
                        // is too coarse to separate entry from stop - which is
                        // the whole failure being fixed here, and no reason to
                        // reintroduce it from the other direction.
                        $dec = abs($entry - $sl) >= $tick ? $tickDec : max($tickDec, $dec);
                    }
                } catch (\Throwable $e) {
                    // exchange unreachable - the magnitude rule is fine
                }
            }
            $unit = 10 ** -$dec;
            $r = static fn(float $v): float => round($v, $dec);

            // Rounding must never put the stop on the wrong side of the entry,
            // nor a target on top of it. One unit is the smallest distance the
            // published numbers can express; anything less is not a level, it
            // is the same price written twice.
            $slR = $r($sl);
            if ($dir > 0 && $slR >= $r($entry)) {
                $slR = $r($entry) - $unit;
            } elseif ($dir < 0 && $slR <= $r($entry)) {
                $slR = $r($entry) + $unit;
            }
            // Risk is measured from the numbers that were PUBLISHED, not from
            // the unrounded ones. The reader trades what they were shown, and
            // an R computed off a different pair of prices would flatter every
            // result on the low-priced pairs by exactly the rounding error.
            $risk = abs($r($entry) - $slR);

            // THREE TYPES OF SIGNAL: 1:1, 1:2 AND 1:3.
            //
            // The targets used to be set in ATR - 1.0, 2.0 and 3.5 of it -
            // against a stop of 1.5, which makes every plan on the site 1 :
            // 1.33 whatever it says about itself, and no amount of demanding
            // 1 : 3 can produce one: measured over six coins on 1h, a 1 : 3
            // floor applied to that ladder refused 300 signals out of 300.
            // A ratio the ladder cannot express is not a standard, it is an
            // outage.
            //
            // So the ratio IS the ladder. The targets stand at exactly one,
            // two and three times the risk being taken, which makes the plan
            // readable without arithmetic - target 2 pays 2R, always - and
            // makes the R the outcome walker books the same number the reader
            // was shown. The stop is untouched: it is placed by structure and
            // by the ATR, and moving it to flatter a ratio would be inventing
            // the risk away rather than earning the reward.
            //
            // Measured off the ROUNDED risk, for the same reason the ratio is:
            // the reader trades the numbers they were given.
            $tpR = static function (float $mult) use ($entry, $dir, $risk, $r, $unit): float {
                $v = $r($entry + $dir * $mult * $risk);
                if ($dir > 0 && $v <= $r($entry)) {
                    return $r($entry) + $unit;
                }
                if ($dir < 0 && $v >= $r($entry)) {
                    return $r($entry) - $unit;
                }
                return $v;
            };

            // WHICH OF THE THREE THIS ONE IS.
            //
            // Every setup gets the same three targets; they do not all deserve
            // the same expectations of them. The type is the furthest tier the
            // trade can plausibly reach before its own deadline, judged on two
            // things and nothing else:
            //
            //   - how far this coin typically travels in the bars the setup
            //     has left, which is the ATR scaled by the square root of the
            //     time stop - price wanders, it does not march, so N bars buys
            //     sqrt(N) ATRs of distance, not N of them;
            //   - how much room there is before the structure overhead: the
            //     nearest resistance above a long, support below a short. A
            //     3R target on the far side of a level price has turned at
            //     four times is a number, not a plan.
            //
            // The tier is published on the signal, so "1:3 only" is something
            // a reader can actually ask for, and min_rr below is the operator
            // asking for it site-wide.
            $rrBars = max(1, (int)round((int)$this->cfg('time_stop_bars', '24') * $regPolicy['time_stop']));
            // THE TIER THAT MAKES THE MOST MONEY, NOT THE FURTHEST ONE THAT
            // CLEARS A THRESHOLD.
            //
            // Picking the highest tier above a fixed probability floor was the
            // obvious rule and it is the wrong one: measured across twelve
            // coins on 1h and 4h, a 35% floor typed every single setup 1:1,
            // because 3R on a 1.5 ATR stop is 4.5 ATR of travel and almost
            // nothing covers that inside a 24-candle deadline. A rule whose
            // answer is always the same answer is not choosing anything.
            //
            // Expected value chooses properly. Reaching the target pays that
            // many R; failing costs the stop, which is 1R. So a tier is worth
            //     p x tier - (1 - p)
            // and the best of the three is the one to plan for - a coin that
            // gets to 2R four times in ten is worth more at 1:2 than at 1:1,
            // and one that stalls is honestly a 1:1. Ties go to the nearer
            // target, which is the one price reaches sooner and holds less.
            //
            // p comes from this pair's own recent history through the same
            // measurement the reachability gate above uses - not a guess about
            // how far price wanders, which would have been a second opinion
            // about something already measured.
            // EACH TYPE IS A DIFFERENT TRADE, SO EACH GETS ITS OWN DEADLINE.
            //
            // Judged over one shared window every tier came out 1:1 across
            // twelve coins and two timeframes, and the reason is arithmetic
            // rather than market: 3R on a 1.5 ATR stop is 4.5 ATR of travel,
            // and almost nothing covers that in twenty-four candles. Holding
            // the deadline fixed while moving the target three times further
            // out is not comparing three plans, it is asking the same question
            // three times and being surprised the answer does not change.
            //
            // A 1:3 is a trade you sit in. So tier T is measured over T times
            // the base window and, if it is chosen, KEEPS that window as its
            // time stop - the deadline that goes out with the plan and that
            // the outcome walker settles against, so the trade is judged on
            // the horizon it was typed for.
            $rrTier = 1;
            $rrProb = null;
            $rrBest = null;
            $rrHold = $rrBars;
            foreach ([1, 2, 3] as $tier) {
                if ($risk <= 0) {
                    break;
                }
                $bars = $rrBars * $tier;
                $p = Indicators::reachProbability(
                    $highs, $lows, $closes, $tier * $risk, $bars, $side === 'BUY');
                if ($p === null) {
                    continue;
                }
                $ev = $p * $tier - (1 - $p);
                if ($rrBest === null || $ev > $rrBest + 1e-9) {
                    $rrBest = $ev;
                    $rrTier = $tier;
                    $rrProb = round($p, 3);
                    $rrHold = $bars;
                }
            }

            $levels = [
                'entry'     => $r($entry),
                'entry_low' => $r($entry - $zoneM * $atrNow),
                'entry_high'=> $r($entry + $zoneM * $atrNow),
                'stop_loss' => $slR,
                'tp1'       => $tpR(1.0),
                'tp2'       => $tpR(2.0),
                'tp3'       => $tpR(3.0),
                // Which of the three types this signal is, and the reward it
                // therefore advertises. Both derived, never stored twice.
                'rr_type'   => '1:' . $rrTier,
                'rr_tier'   => $rrTier,
                // How often this pair has actually covered that distance in a
                // window this long. Published with the type, because a type
                // without its evidence is a label.
                'rr_reach'  => $rrProb,
                // What that tier is worth per trade at that hit rate, in R.
                'rr_ev'     => $rrBest === null ? null : round($rrBest, 3),
                'target'    => $tpR((float)$rrTier),
                // Reward measured from the PUBLISHED target, for the same
                // reason risk is: the ratio has to describe the plan the
                // reader was actually given.
                'rr'        => $risk > 0 ? round(abs($tpR((float)$rrTier) - $r($entry)) / $risk, 2) : null,
                // The same ratio after trading costs, which is the one that
                // decides whether the trade makes money. Published gross, a
                // 3m setup with a 0.07% stop shows "1 : 1.3" while a 0.1%
                // round trip is eating 145% of the risk behind it.
                'rr_net'    => $risk > 0 && $entry > 0
                    ? round(abs($tpR((float)$rrTier) - $r($entry)) / $risk - $costR, 2) : null,
                'cost_r'    => round($costR, 2),
                'risk_pct'  => $entry > 0 ? round($risk / $entry * 100, 4) : null,
                // Same magnitude rule: at six decimals both of these read 0
                // on a sub-cent coin, which is how a risk of "0" reached the
                // position-size calculator.
                'risk_per_unit' => $r($risk),
                'atr'       => round($atrNow, self::priceDecimals($atrNow)),
                'exit_rules'=> $this->exitRules($tf, $signal),
                // How long this setup has to work. Buried in the exit rules as
                // "24 candles" it answered a question nobody asks: on 5m that
                // is two hours and on 1d over three weeks, and the reader
                // wants the deadline, not the arithmetic.
                //
                // Candle 't' is an open time in MILLISECONDS (MarketData), and
                // the signal fires at the bar's close, so the clock starts one
                // whole candle after the open.
                // The regime also decides how long a setup deserves: a grind
                // takes its time, a whipsaw resolves or it was never there.
                // The window the type was chosen for - see the tier block
                // above. A 1:3 that is closed on a 1:1's clock is not a 1:3.
                'expires_at'   => intdiv((int)($candles[$i]['t'] ?? time() * 1000), 1000)
                                + (1 + ($tsBars = max(1, $rrHold)))
                                  * self::tfMinutes($tf) * 60,
                'expires_bars' => $tsBars,
                'expires_in'   => self::humanSpan($tsBars * self::tfMinutes($tf)),
            ];
            if ($liqStopUsed !== null) {
                $levels['liq_stop'] = round($liqStopUsed, self::priceDecimals($liqStopUsed));
            }
            // The magnet: the nearest pool of forced buying (for a long) or
            // forced selling (for a short) ahead of the trade. Price reaches
            // for these because the orders there are not optional, which
            // makes the closest one a more honest target than a round number.
            if ($liq !== null) {
                $want = $planSide === 'BUY' ? 'short' : 'long';
                $best = null;
                foreach ($liq as $cluster) {
                    $cp = (float)$cluster['price'];
                    if ($dir * ($cp - $entry) <= 0 || $cluster['weight'] < 0.35
                        || $cluster['side'] !== $want) {
                        continue;
                    }
                    if ($best === null || $dir * ($cp - $best['price']) < 0) {
                        $best = ['price' => $cp, 'weight' => $cluster['weight']];
                    }
                }
                if ($best !== null) {
                    $levels['liq_magnet'] = round((float)$best['price'], self::priceDecimals((float)$best['price']));
                    $levels['liq_magnet_r'] = $risk > 0
                        ? round(abs($best['price'] - $entry) / $risk, 2) : null;
                }
            }
            // Chase guard: if price has already travelled most of the way to
            // TP1 since the signal bar closed, the edge is gone - say so
            // instead of letting a reader buy the top of the move.
            $live = $closesRaw[count($closesRaw) - 1] ?? $price;
            $travelled = $dir * ($live - $entry);
            if ($travelled > 0.6 * $tp1M * $atrNow) {
                $levels['stale'] = true;
                $levels['stale_note'] = sprintf(
                    'Price has already moved %.1f%% of the way to TP1 since this setup triggered - '
                    . 'the entry is no longer valid at these levels.',
                    min(100, $travelled / max(1e-9, $tp1M * $atrNow) * 100));
            }
        }

        // Confidence, in three tiers of increasing honesty.
        //
        // The old floor was |score| rescaled by the total available weight and
        // a constant picked to make it look like a percentage. It was not a
        // probability and could never be wrong, because it claimed nothing
        // falsifiable - while a reader seeing "82%" fairly assumes four in
        // five of these work out.
        //
        // Now the floor is a six-dimension quality read (evidence, breadth,
        // trend alignment, structure, regime fit, cost of entry), combined
        // geometrically so a single broken dimension cannot be papered over by
        // a strong one. Where enough verified outcomes exist it is mapped onto
        // the measured win rate of comparable past signals; the learned model
        // still overrides both once it beats the base rate out of sample. The
        // basis is reported alongside, because "measured" and "estimated" are
        // different claims.
        $confDims = [];
        $confQuality = 0.0;
        $confidence = 0.0;
        $confBasis = 'quality';
        $confN = 0;
        // Execution conditions at this instant. Read here rather than at the
        // end because the cost dimension needs them, and only for tradable
        // verdicts - a NEUTRAL nobody can act on does not need to know what
        // the book looked like.
        $atrPct = $atrNow !== null && $price > 0 ? round($atrNow / $price * 100, 4) : null;
        $spreadPct = null;
        if ($this->md !== null && $signal !== 'NEUTRAL'
            && Database::setting('record_spread', '1') === '1') {
            try {
                $spreadPct = $this->md->spreadPct($symbol);
            } catch (\Throwable $e) {
                // Telemetry is never worth a failed signal.
            }
        }
        try {
            $c = Confidence::evaluate([
                'signal' => $signal,
                'score' => $score,
                'reasons' => $reasons,
                'categories' => $catBreakdown,
                'bull_categories' => $bullCats,
                'bear_categories' => $bearCats,
                'mtf_bias' => $mtfBias,
                'mtf_frames' => $mtf['frames'] ?? [],
                'smc' => $smc,
                'adx' => $adx['adx'][$i] ?? null,
                'chop' => $chop[$i] ?? null,
                'levels' => $levels ?? [],
                'spread_pct' => $spreadPct,
                'round_trip_cost_pct' => (float)$this->cfg('round_trip_cost_pct', '0.1'),
            ]);
            $confidence = (float)$c['confidence'];
            $confBasis = (string)$c['basis'];
            $confN = (int)$c['n'];
            $confDims = $c['dimensions'];
            $confQuality = (float)$c['quality'];
        } catch (\Throwable $e) {
            // Never let the confidence read break a signal: fall back to the
            // old rescaling, clearly labelled as the heuristic it is.
            $confidence = $maxScore > 0 ? round(min(1.0, abs($score) / ($maxScore * 0.35)) * 100, 1) : 0.0;
            $confBasis = 'model';
        }

        // Legacy |score| calibration, kept as a second opinion for installs
        // whose history predates the quality buckets: it still describes real
        // outcomes, just along one dimension instead of six.
        if (!$this->dry && $confBasis !== 'measured'
            && $this->cfg('calibrated_confidence', '1') === '1') {
            $calib = json_decode($this->cfg('confidence_calib', '{}'), true);
            // A bucket needs real evidence before its number is shown as
            // "measured" - the old floor of 8 samples let a 5-of-8 bucket
            // publish 62% with a confidence interval thirty points wide.
            $minN = max(10, (int)$this->cfg('calib_min_samples', '30'));
            foreach ((array)($calib['buckets'] ?? []) as $b) {
                if (($b['n'] ?? 0) >= $minN && abs($score) >= $b['lo'] && abs($score) < $b['hi']
                    && ($b['winrate'] ?? null) !== null) {
                    // Report the Wilson lower bound: the win rate the sample
                    // actually supports, which converges on the point estimate
                    // as evidence accumulates.
                    $useWilson = $this->cfg('confidence_conservative', '1') === '1'
                        && ($b['wilson'] ?? null) !== null;
                    $confidence = max(5.0, min(95.0, (float)($useWilson ? $b['wilson'] : $b['winrate'])));
                    $confBasis = 'measured';
                    $confN = (int)$b['n'];
                    break;
                }
            }
        }
        // Learned model: a probability fitted on this install's own verified
        // outcomes beats any rescaling of the score, because it reads the
        // whole fired-rule vector rather than its sum. It only speaks once it
        // has enough samples AND beats the base rate out of sample.
        if (!$this->dry && $signal !== 'NEUTRAL') {
            try {
                $p = LearnedModel::predict($reasons, $signal, [
                    'tf' => $tf,
                    'grade' => $grade,
                    'adx' => $adx['adx'][$i] ?? null,
                    'hour' => (int)gmdate('G'),
                    // Volatility class - the coin-awareness the model gets for
                    // four coefficients instead of a table per pair.
                    'atr_pct' => $atrPct,
                ]);
                if ($p !== null) {
                    $confidence = round(max(5.0, min(95.0, $p * 100)), 1);
                    $confBasis = 'model_learned';
                }
            } catch (\Throwable $e) {
                // keep the calibrated/heuristic confidence
            }
        }

        $indicators = [
            'price'      => $price,
            'rsi'        => $rsiNow !== null ? round($rsiNow, 2) : null,
            'macd'       => $macd['macd'][$i] !== null ? round($macd['macd'][$i], 6) : null,
            'macd_signal'=> $macd['signal'][$i] !== null ? round($macd['signal'][$i], 6) : null,
            'ema20'      => $ema20[$i] !== null ? round($ema20[$i], 6) : null,
            'ema50'      => $ema50[$i] !== null ? round($ema50[$i], 6) : null,
            'ema200'     => $ema200[$i] !== null ? round($ema200[$i], 6) : null,
            'bb_upper'   => $bb['upper'][$i] !== null ? round($bb['upper'][$i], 6) : null,
            'bb_lower'   => $bb['lower'][$i] !== null ? round($bb['lower'][$i], 6) : null,
            'stoch_k'    => $stoch['k'][$i] !== null ? round($stoch['k'][$i], 2) : null,
            'atr'        => $atrNow !== null ? round($atrNow, 6) : null,
            'support'    => $support !== null ? round($support, 6) : null,
            'resistance' => $resistance !== null ? round($resistance, 6) : null,
            'adx'        => $adx['adx'][$i] !== null ? round($adx['adx'][$i], 1) : null,
            'willr'      => $willr[$i] !== null ? round($willr[$i], 1) : null,
            'cci'        => $cci[$i] !== null ? round($cci[$i], 1) : null,
            'mfi'        => $mfi[$i] !== null ? round($mfi[$i], 1) : null,
            'supertrend' => $sTrend['trend'][$i],
            'sentiment_coin'   => $senti !== null ? $senti['coin']['score'] : null,
            'sentiment_market' => $senti !== null ? $senti['market']['score'] : null,
            'vwap'       => ($vwap[$i] ?? null) !== null ? round($vwap[$i], 6) : null,
            'poc'        => $vProf !== null ? round($vProf['poc'], 6) : null,
            'vah'        => $vProf !== null ? round($vProf['vah'], 6) : null,
            'val'        => $vProf !== null ? round($vProf['val'], 6) : null,
            'chop'       => $chopNow !== null ? round($chopNow, 1) : null,
            'bw_pct'     => $bwPct,
            'liq_clusters' => $liq,
            // The full ladder, not just its net bias: a reader deciding
            // whether to take a 5m long deserves to see that the daily is
            // down and the 1h is up, rather than one averaged number.
            'mtf'        => $mtf,
            'mtf_bias'   => round($mtfBias, 3),
            'smc'        => $smc,
            'regime'     => $regime,
            'levels'     => $levels,
            'grade'      => $grade,
            'event_caution' => $cautionEvent,
            'circuit_breaker' => $circuitOn,
            'onchain_caution' => $chainCaution,
        ];

        $preflight = [];
        // ---- the last look before anybody is told ---------------------------
        //
        // Not another opinion about the market - the gates above are that.
        // This asks whether the DATA was sound and whether the PLAN holds
        // together, which are questions with right answers and which nothing
        // was asking. A member's own manual trade is checked for a stop on the
        // wrong side before it can be opened; the engine's own plan was not.
        //
        // The clock check is skipped in dry mode: the backtester pins "now" to
        // the simulated bar, so a freshness test there would call every
        // replayed signal stale and quietly empty out the tool that measures
        // this engine.
        if ($signal !== 'NEUTRAL' && $this->cfg('preflight_enabled', '1') === '1') {
            $faults = Preflight::check($signal, $levels, $candles, $tf, !$this->dry);
            if ($faults) {
                foreach ($faults as $f) {
                    $reasons[] = [
                        'rule'   => 'Pre-flight check',
                        'key'    => (string)$f['key'],
                        'side'   => $signal === 'BUY' ? 'bearish' : 'bullish',
                        'weight' => 0.0,
                        'detail' => (string)$f['message'],
                    ];
                }
                $preflight = $faults;
                if (Preflight::blocked($faults)) {
                    $shadowDir = $signal;
                    $shadowWhy = Preflight::firstBlock($faults);
                    $signal = 'NEUTRAL';
                }
            }
        }

        $result = [
            'symbol'     => $symbol,
            'tf'         => $tf,
            'signal'     => $signal,
            // WHEN THIS CALL IS FROM.
            //
            // The chart panel showed "updated 14:32:05", which is when the
            // BROWSER last polled - a fact about the page, printed where a
            // fact about the signal belongs. A reader deciding whether to take
            // a setup wants to know it was read on the candle that closed five
            // minutes ago, not that their tab refreshed a second ago, and the
            // two are as far apart as the timeframe is long.
            //
            // Candle 't' is an open time in milliseconds and the engine reads
            // on the close, so the call is one whole candle after the open -
            // the same arithmetic expires_at uses, from the same place.
            'signal_at'  => intdiv((int)($candles[$i]['t'] ?? 0), 1000)
                            + self::tfMinutes($tf) * 60,
            'preflight'  => $preflight,
            'score'      => round($score, 2),
            'raw_score'  => round($rawScore, 2),
            'scoring'    => $this->modeNow(),
            'categories' => $catBreakdown,
            'bull_categories' => $bullCats,
            'bear_categories' => $bearCats,
            // The UI shows the score against the thresholds it must clear, and
            // how many rules stayed silent - a bare number conveys neither.
            'buy_threshold'  => round($buyT, 2),
            'sell_threshold' => round($sellT, 2),
            'rules_evaluated' => count($this->rules),
            'confidence' => $confidence,
            'confidence_basis' => $confBasis,
            'confidence_n' => $confN,
            // The six axes behind the number, so a reader can see WHY a setup
            // is rated the way it is instead of being handed a percentage and
            // asked to trust it.
            'confidence_dims' => $confDims,
            'confidence_quality' => round($confQuality, 4),
            'grade'      => $grade,
            'price'      => $price,
            'levels'     => $levels,
            'reasons'    => $reasons,
            'indicators' => $indicators,
            'generated'  => time(),
            'disclaimer' => $this->cfg('site_notice'),
        ];

        // The setup it turned down, when there was one. Carried on the result
        // rather than written from in here, because analyse() is called by the
        // backtester and by preview screens too and none of those should be
        // writing rows - the caller that stores signals stores this.
        // THE PLAN OF A REFUSED SETUP IS NOT A PUBLISHED PLAN.
        //
        // Levels are now built for a shadow too, and a NEUTRAL result carrying
        // entry/stop/targets would put a trade plan on the chart for a call the
        // engine explicitly declined to make. The plan goes to the shadow; the
        // result keeps the null it has always had.
        $shadowPlan = $levels;
        if ($signal === 'NEUTRAL') {
            // On the RESULT, not the local: it was assembled above, so nulling
            // the variable here would change nothing and the plan would have
            // gone out on the chart of a call that was never made.
            $result['levels'] = null;
            if (isset($result['indicators']['levels'])) {
                $result['indicators']['levels'] = null;
            }
        }
        if ($shadowDir !== '' && $shadowPlan !== null && $signal === 'NEUTRAL'
            && $this->cfg('shadow_enabled', '1') === '1') {
            $result['shadow'] = [
                'signal'     => $shadowDir,
                'blocked_by' => $shadowWhy,
                'levels'     => $shadowPlan,
            ];
        }

        // Read above, where the cost dimension of the confidence needed them.
        $result['atr_pct'] = $atrPct;
        $result['spread_pct'] = $spreadPct;

        if (!$this->dry && !$this->noStore) {
            $this->store($result);
        }
        return $result;
    }

    /**
     * Latest stored verdict for a pair/timeframe (hysteresis anchor). Read
     * from the state row, not the ledger: the ledger no longer carries
     * NEUTRAL, so anchoring to it would leave hysteresis clinging to the last
     * directional call long after the pair went quiet.
     */
    private function lastVerdict(string $symbol, string $tf): ?string
    {
        $stmt = Database::pdo()->prepare(
            'SELECT `signal` FROM signal_state WHERE symbol = ? AND tf = ?'
        );
        $stmt->execute([$symbol, $tf]);
        $v = $stmt->fetchColumn();
        return $v === false ? null : (string)$v;
    }

    /**
     * Crypto Fear & Greed index (alternative.me, free, no key), 0-100.
     * Cached for an hour; null when unreachable - the rule simply skips.
     */
    public static function fearGreed(): ?int
    {
        $v = Cache::remember('fng', 3600, static function (): ?int {
            try {
                $ch = curl_init('https://api.alternative.me/fng/?limit=1');
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 6,
                    CURLOPT_CONNECTTIMEOUT => 4,
                    CURLOPT_USERAGENT => 'SignalMasterAi/1.0',
                ]);
                $body = curl_exec($ch);
                curl_close($ch);
                $data = is_string($body) ? json_decode($body, true) : null;
                $raw = $data['data'][0]['value'] ?? null;
                if (is_numeric($raw)) {
                    return max(0, min(100, (int)$raw));
                }
            } catch (\Throwable $e) {
                // unreachable - the miss is cached too (see $nullTtl below)
            }
            return null;
        }, 600);
        return $v === null ? null : (int)$v;
    }

    /**
     * Change in the Fear & Greed index over the last three days.
     *
     * The level says where sentiment is; this says which way it is going, and
     * the two disagree often enough to matter. Same free endpoint, one call a
     * day's worth of data, cached for six hours because the index itself only
     * updates once a day.
     */
    public static function fearGreedTrend(): ?int
    {
        $v = Cache::remember('fngtrend', 21600, static function (): ?int {
            try {
                $ch = curl_init('https://api.alternative.me/fng/?limit=4');
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 6,
                    CURLOPT_CONNECTTIMEOUT => 4,
                    CURLOPT_USERAGENT => 'SignalMasterAi/1.0',
                ]);
                $body = curl_exec($ch);
                curl_close($ch);
                $data = is_string($body) ? json_decode($body, true) : null;
                $rows = $data['data'] ?? null;
                if (is_array($rows) && count($rows) >= 4
                    && is_numeric($rows[0]['value'] ?? null) && is_numeric($rows[3]['value'] ?? null)) {
                    // Index 0 is today, 3 is three days back.
                    return (int)$rows[0]['value'] - (int)$rows[3]['value'];
                }
            } catch (\Throwable $e) {
                // unreachable - the miss is cached too
            }
            return null;
        }, 3600);
        return $v === null ? null : (int)$v;
    }

    /**
     * Trade-management exit rules shown with every plan. When neither SL nor
     * a target is reached, these tell the reader when to stand down.
     */
    /** Minutes in one candle of a timeframe. */
    /**
     * How many decimal places a price at this magnitude needs.
     *
     * Every published level used to be rounded to six places, which is a
     * decision that only looks right if you never test it below a cent. It
     * gives BTC six digits it does not need and PEPE none of the ones it does:
     * at 0.00000123 the entry, the stop and all three targets collapse onto
     * the same number, so the plan says "buy here, and get out here" about one
     * price. Under 0.0000005 the entry rounds to zero outright.
     *
     * Constant SIGNIFICANT digits is the rule that works at both ends. Six of
     * them puts BTC at two decimals, ADA at six and PEPE at twelve, which is
     * what each instrument's own tick actually is, near enough. The floor of
     * two keeps a four-figure price from being rounded to whole units; the
     * ceiling of fourteen stays inside what a double can represent honestly.
     */
    public static function priceDecimals(?float $reference): int
    {
        $ref = abs((float)$reference);
        if ($ref <= 0.0 || !is_finite($ref)) {
            return 8;
        }
        return max(2, min(14, 6 - (int)floor(log10($ref))));
    }

    public static function tfMinutes(string $tf): int
    {
        return ['1m' => 1, '3m' => 3, '5m' => 5, '15m' => 15, '30m' => 30, '1h' => 60,
                '2h' => 120, '6h' => 360, '8h' => 480, '12h' => 720, '3d' => 4320,
                '4h' => 240, '1d' => 1440, '1w' => 10080, '1mo' => 43200][$tf] ?? 60;
    }

    /** "2 hours", "3.5 days" - the same wording everywhere a span is shown. */
    public static function humanSpan(int $minutes): string
    {
        if ($minutes >= 10080) {
            return round($minutes / 10080, 1) . ' weeks';
        }
        if ($minutes >= 1440) {
            return round($minutes / 1440, 1) . ' days';
        }
        if ($minutes >= 60) {
            return round($minutes / 60, 1) . ' hours';
        }
        return $minutes . ' minutes';
    }

    private function exitRules(string $tf, string $signal): array
    {
        $rules = ['Exit immediately if the signal flips to the opposite side - the original setup is invalidated.'];
        if ($this->cfg('breakeven_after_tp1', '1') === '1') {
            // Say what share comes off, because that is the number every
            // published R is measured against. "Let the rest run" without it
            // describes two different trades - and the verifier used to score
            // whichever of them had paid more.
            $pct = max(0, min(100, (int)$this->cfg('tp1_partial_pct', '50')));
            $rules[] = $pct >= 100
                ? 'Close the whole position at TP1.'
                : ($pct <= 0
                    ? 'At TP1, move the stop loss to entry (break-even) and hold the full position for TP2.'
                    : "At TP1, take $pct% off and move the stop loss on the rest to entry (break-even), "
                      . 'so the remainder runs risk-free towards TP2.');
        }
        $bars = max(1, (int)$this->cfg('time_stop_bars', '24'));
        $human = self::humanSpan($bars * self::tfMinutes($tf));
        $rules[] = "Time stop: if neither the stop loss nor TP1 is hit within $bars candles (~$human), "
                 . 'close the trade - a setup that goes nowhere has already failed.';
        return $rules;
    }

    /**
     * Two records, two jobs.
     *
     * signal_state holds the CURRENT verdict for the pair - one row per
     * symbol/timeframe, overwritten every scan, NEUTRAL included. That is what
     * the scanner board, the hysteresis anchor and the alert dispatchers read,
     * so a pair that goes quiet still reads as quiet everywhere.
     *
     * The signals table is the LEDGER of actionable calls: a row is written
     * only when the verdict flips into a genuinely new BUY or SELL. NEUTRAL
     * verdicts are no longer written there (store_neutral = 1 restores the old
     * behaviour), because a "no trade here" is not a call - it was drowning
     * the history, the admin list and every count taken off the table in rows
     * nobody could act on. Nothing is lost: the verdict still exists, in the
     * one place that needs to know it.
     */
    /**
     * Record a setup the engine declined, with the plan it would have
     * published, so it can be settled against real price action later.
     *
     * Deduplicated the same way a real signal is: one open shadow per pair,
     * timeframe, direction and reason. Without that, a gate that blocks the
     * same setup on every scan would write a row every few minutes and the
     * learning would count one refused idea a hundred times.
     */
    private function storeShadow(array $r): void
    {
        $sh = $r['shadow'];
        $levels = $sh['levels'] ?? null;
        if (!is_array($levels) || !isset($levels['stop_loss'], $levels['tp1'])) {
            return;
        }
        $pdo = Database::pdo();
        $dup = $pdo->prepare(
            "SELECT 1 FROM shadow_signals
              WHERE symbol = ? AND tf = ? AND `signal` = ? AND blocked_by = ? AND outcome = ''
              LIMIT 1"
        );
        $dup->execute([$r['symbol'], $r['tf'], $sh['signal'], $sh['blocked_by']]);
        $exists = $dup->fetchColumn() !== false;
        $dup->closeCursor();
        if ($exists) {
            return;
        }
        try {
            $pdo->prepare(
                'INSERT INTO shadow_signals (symbol, tf, `signal`, blocked_by, score, price,
                        atr_pct, reasons, indicators, created_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?)'
            )->execute([
                $r['symbol'], $r['tf'], $sh['signal'], (string)$sh['blocked_by'],
                (float)$r['score'], (float)$r['price'], $r['atr_pct'] ?? null,
                json_encode($r['reasons']),
                json_encode(['levels' => $levels, 'grade' => $r['grade'] ?? null,
                             'adx' => $r['indicators']['adx'] ?? null]),
                (int)$r['generated'],
            ]);
        } catch (\Throwable $e) {
            // A database that predates the table must not cost a signal.
        }
    }

    private function store(array $r): void
    {
        $pdo  = Database::pdo();
        $side = (string)$r['signal'];
        $now  = (int)$r['generated'];

        // The setup that was turned down goes to its own table, and nothing
        // else about the rest of this method changes: a shadow is not a
        // signal, has no state row, fires no alert and appears on no page.
        if (isset($r['shadow']) && is_array($r['shadow'])) {
            $this->storeShadow($r);
        }

        $stateStmt = $pdo->prepare(
            'SELECT `signal`, signal_id, changed_at FROM signal_state WHERE symbol = ? AND tf = ?'
        );
        $stateStmt->execute([$r['symbol'], $r['tf']]);
        $state = $stateStmt->fetch() ?: null;
        $stateStmt->closeCursor();      // see saveState() - a held read blocks the write
        $prevVerdict = $state ? (string)$state['signal'] : null;

        // One live trade per pair, timeframe and direction.
        //
        // Store-on-flip only compares against the previous VERDICT, so
        // SELL -> NEUTRAL -> SELL wrote a second SELL while the first was
        // still unresolved. This install had three SELLs open on BTCUSDT 1h
        // at once: one idea, published three times, verified three times, and
        // counted three times in the track record. A reader following all
        // three would be three times the size they thought they were.
        //
        // A genuinely different setup is still allowed through: if price has
        // moved further than the whole risk of the open plan, the entry, the
        // stop and the targets describe a different trade at a different
        // location, which is exactly the case the reader means by "another
        // signal". Anything closer than that is the same idea restated - and
        // the state row keeps pointing at the call that is already published,
        // so its levels never drift.
        $governing = 0;
        $sameIdea  = false;
        if ($side !== 'NEUTRAL') {
            $openStmt = $pdo->prepare(
                "SELECT id, indicators FROM signals
                 WHERE symbol = ? AND tf = ? AND `signal` = ? AND outcome = ''
                 ORDER BY created_at DESC LIMIT 1"
            );
            $openStmt->execute([$r['symbol'], $r['tf'], $side]);
            $openRow = $openStmt->fetch();
            $openStmt->closeCursor();
            // Let go of the read.
            //
            // A LIMIT 1 query fetched once is NOT finished as far as SQLite is
            // concerned - the statement has one more step to take before it
            // knows there are no more rows, so it holds a read transaction
            // open for as long as the variable lives. This one lived until the
            // end of store(), which is past the UPDATE in saveState() below.
            // A write attempted while the same connection holds a read is a
            // read-to-write upgrade, and if any other connection has committed
            // since the read began SQLite answers SQLITE_BUSY and - this is
            // the part that made it baffling - does NOT consult the busy
            // handler, so the ten-second busy_timeout set on the connection
            // never applied. The visible symptom was five of eight concurrent
            // chart polls returning "database is locked" from an ordinary
            // UPDATE with no transaction anywhere near it.
            $openStmt->closeCursor();
            if ($openRow) {
                $governing = (int)$openRow['id'];
                $sameIdea  = true;
                $prev = json_decode((string)$openRow['indicators'], true);
                $prevLv = is_array($prev) ? ($prev['levels'] ?? null) : null;
                $newLv = $r['indicators']['levels'] ?? null;
                if (is_array($prevLv) && is_array($newLv)
                    && isset($prevLv['entry'], $prevLv['stop_loss'], $newLv['entry'])) {
                    $risk = abs((float)$prevLv['entry'] - (float)$prevLv['stop_loss']);
                    $moved = abs((float)$newLv['entry'] - (float)$prevLv['entry']);
                    $factor = max(0.1, (float)$this->cfg('reissue_min_risk', '1.0'));
                    if ($risk > 0 && $moved >= $risk * $factor) {
                        $sameIdea = false;      // different trade, different place
                    }
                }
                // Levels missing on either side: cannot compare, so assume the
                // same idea rather than publish a possible duplicate.
            }
        }

        // The same guard, for a call that has already CLOSED.
        //
        // Above only looks at trades still open, so the moment one settles the
        // identical plan can be published again - and in a range it is, within
        // the hour, at the same entry off the same level with the same stop
        // and the same targets. The reader gets the same trade mailed to them
        // three times before lunch and stops reading any of them.
        //
        // A fingerprint of the published plan settles it exactly: same
        // direction, same entry, same stop, same three targets is the same
        // call. It is deliberately a WINDOW and not forever, because price
        // genuinely does return to a level - a week later that really is a new
        // trade, and refusing to publish it would be a worse bug than the one
        // being fixed.
        // THE WINDOW HAS TO BE MEASURED IN THE PAIR'S OWN BARS, not in hours.
        //
        // A flat 24 hours is 96 candles on a 15m chart and one candle on a
        // daily. So the guard was strict where it barely mattered and, on the
        // frames where a repeated plan is most obviously the same trade, it was
        // switched off in all but name: a daily BUY that closed and set up
        // again at the identical entry, stop and targets two days later was
        // published and mailed as a new call, because two days is more than
        // twenty-four hours. That is exactly the case a reader means by "you
        // have already sent me this".
        //
        // So the window is the longer of the two: the flat hours, and the
        // lifetime of the setup itself on this timeframe. Once the previous
        // plan has outlived its own time stop, the same levels appearing again
        // really are a new trade - which is the other half of the rule, and the
        // reason this is a window rather than a permanent block.
        //
        // Zero hours still means off. An operator who turned this off must not
        // have it brought back by a timeframe calculation.
        $dupHours = max(0, (int)$this->cfg('dupe_window_hours', '24'));
        $dupSecs  = $dupHours * 3600;
        if ($dupSecs > 0) {
            $barSecs = MarketData::TF_SECONDS[$r['tf']] ?? 3600;
            $dupSecs = max($dupSecs, max(1, (int)$this->cfg('time_stop_bars', '24')) * $barSecs);
        }
        $planHash = self::planHash($side, $r['indicators']['levels'] ?? null,
                                   (float)$r['price'], $r['atr_pct'] ?? null);
        if (!$sameIdea && $side !== 'NEUTRAL' && $dupSecs > 0 && $planHash !== '') {
            $dupStmt = $pdo->prepare(
                'SELECT id FROM signals
                 WHERE symbol = ? AND tf = ? AND plan_hash = ? AND created_at > ?
                 ORDER BY created_at DESC LIMIT 1'
            );
            $dupStmt->execute([$r['symbol'], $r['tf'], $planHash, $now - $dupSecs]);
            $dupHit = $dupStmt->fetchColumn();
            $dupStmt->closeCursor();     // same reason as above
            if ($dupHit !== false) {
                // Counted, because a guard that works silently and a guard that
                // is broken look identical from the admin panel - and this one
                // spent its whole life until now unable to say whether it had
                // ever stopped anything.
                try {
                    Database::setSetting('dupe_blocked_total',
                        (string)((int)Database::setting('dupe_blocked_total', '0') + 1));
                    Database::setSetting('dupe_blocked_last', (string)$now);
                } catch (\Throwable $e) {
                    // never let bookkeeping cost a scan
                }
                // Not a call. Nothing is written and the current verdict is
                // left exactly as it was, so the scanner, the track record,
                // the feed and every alert channel see no change - which is
                // what "do not publish it again" has to mean in all four
                // places at once.
                return;
            }
        }

        // What makes a call worth publishing is not that the verdict changed
        // since the last scan - it is that there is no live call saying the
        // same thing. Requiring a flip as well meant a pair that stayed
        // bullish could never issue a second setup once the first had closed:
        // it had to pass through NEUTRAL or SELL first, so a coin in a steady
        // uptrend went silent after one trade. The open-trade test above is
        // the real guard, and it is the one the reader means - same entry,
        // stop and targets is the same signal; a plan a full risk away is
        // another one.
        $write = !$sameIdea;
        if ($side === 'NEUTRAL') {
            $write = $prevVerdict !== $side && $this->cfg('store_neutral', '0') === '1';
        }
        $signalId = $side === 'NEUTRAL' ? 0 : $governing;

        if ($write) {
            $signalId = $this->insertSignal($r) ?: $signalId;
        }
        $this->saveState($r, $side, $signalId, $prevVerdict === $side && $state
            ? (int)$state['changed_at'] : $now, $now);
    }

    /**
     * Fingerprint of a published plan: direction, entry, stop, all targets.
     *
     * Only the numbers the reader acts on. Score, confidence and grade move
     * on every scan and would make every restatement look new, which is the
     * opposite of the question being asked - "have I already been told to buy
     * here, with this stop, for these targets?"
     *
     * A plan with no levels gets a coarser fingerprint - direction, and where
     * on the chart it is - rather than no fingerprint at all.
     *
     * It used to return empty, on the reasoning that direction alone would
     * suppress every second signal on an install with levels switched off.
     * True, and the consequence was that those installs got NO duplicate
     * protection whatsoever: the same BUY on the same pair restated every time
     * the verdict wobbled, mailed every time, forever. A level-less alert says
     * "this pair flipped to BUY at this price", so two of them at the same
     * price ARE the same message.
     *
     * "The same place" is measured the way the open-trade guard measures it,
     * half an ATR rather than a full risk, because without a stop there is no
     * risk to measure. Price is bucketed rather than compared, so that two
     * scans a few ticks apart land on the same fingerprint. With no ATR
     * either there is nothing left to distinguish them by, and direction alone
     * is the honest answer.
     */
    private static function planHash(string $side, $levels, float $price = 0.0, $atrPct = null): string
    {
        if (is_array($levels) && isset($levels['entry'], $levels['stop_loss'])) {
            $parts = [$side];
            foreach (['entry', 'stop_loss', 'tp1', 'tp2', 'tp3'] as $k) {
                $parts[] = isset($levels[$k]) ? (string)(float)$levels[$k] : '';
            }
            return substr(md5(implode('|', $parts)), 0, 32);
        }
        if ($price <= 0) {
            return '';
        }
        // Bucketed on the LOG of the price, not the price.
        //
        // ATR is a percentage, so the band it describes grows with price -
        // and dividing a price by a band derived from that same price gives
        // roughly 1/band whatever the price is. Written the obvious way, BTC
        // at 100 and BTC at 130 landed in the same bucket, and the guard
        // suppressed a genuinely different call thirty percent away. Log
        // price turns a constant percentage into a constant distance, which
        // is what a bucket needs.
        $frac = $atrPct !== null && (float)$atrPct > 0
            ? max(0.0005, (float)$atrPct / 100 * 0.5)
            : 0.0;
        $bucket = $frac > 0 ? (string)(int)floor(log($price) / $frac) : 'x';
        return substr(md5('nolevels|' . $side . '|' . $bucket), 0, 32);
    }

    /** Write one ledger row; returns its id (0 if the id is unavailable). */
    private function insertSignal(array $r): int
    {
        $pdo = Database::pdo();
        $grade = (string)($r['indicators']['grade'] ?? '');
        // Same arguments as the check above, or a row would be stored under a
        // fingerprint the guard never looks for - which is how a duplicate
        // guard ends up protecting nothing on exactly the installs that need
        // it most.
        $planHash = self::planHash((string)$r['signal'], $r['indicators']['levels'] ?? null,
                                   (float)$r['price'], $r['atr_pct'] ?? null);
        $vals = [
            $r['symbol'], $r['tf'], $r['signal'], $r['score'], $r['confidence'], $r['price'],
            json_encode($r['reasons']), json_encode($r['indicators']), $r['generated'],
        ];
        // The feature vector and the execution conditions are written with the
        // signal, not reconstructed later: retention prunes the candles this
        // setup was read from, and indicator settings get retuned, so a vector
        // rebuilt months from now would describe a setup that never existed.
        // Falls back to the original insert on a database that predates the
        // columns, because a missing telemetry column must not cost a signal.
        // The public reference, minted here because here is where a signal
        // becomes one. Retried on the vanishingly unlikely collision rather
        // than assumed unique - the index would reject it and the whole insert
        // would be lost with it, which is a poor trade for a six-character
        // string that costs nothing to draw again.
        $ref = SignalRef::make();
        for ($try = 0; $try < 5; $try++) {
            $chk = $pdo->prepare('SELECT 1 FROM signals WHERE ref = ? LIMIT 1');
            $chk->execute([$ref]);
            $taken = (bool)$chk->fetchColumn();
            $chk->closeCursor();
            if (!$taken) {
                break;
            }
            $ref = SignalRef::make();
        }
        try {
            $pdo->prepare(
                'INSERT INTO signals (symbol, tf, `signal`, score, confidence, price, reasons,
                    indicators, created_at, atr_pct, spread_pct, features, grade, plan_hash, ref)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            )->execute(array_merge($vals, [
                $r['atr_pct'] ?? null,
                $r['spread_pct'] ?? null,
                json_encode(FeatureVector::build($r, $r['spread_pct'] ?? null)),
                $grade,
                $planHash,
                $ref,
            ]));
        } catch (\Throwable $e) {
            $pdo->prepare(
                'INSERT INTO signals (symbol, tf, `signal`, score, confidence, price, reasons, indicators, created_at)
                 VALUES (?,?,?,?,?,?,?,?,?)'
            )->execute($vals);
        }
        return (int)$pdo->lastInsertId();
    }

    /**
     * Upsert the current verdict. UPDATE first, INSERT only if the pair has
     * never been seen - portable across SQLite and MySQL without either
     * dialect's ON CONFLICT syntax. Two scans landing in the same second can
     * race the INSERT; the unique key catches it and the loser is a no-op,
     * which is correct because both wrote the same verdict.
     */
    private function saveState(array $r, string $side, int $signalId, int $changedAt, int $now): void
    {
        $pdo = Database::pdo();
        $args = [$side, (float)$r['score'], (float)$r['confidence'], (float)$r['price'],
            json_encode($r['indicators']), $signalId, $changedAt, $now,
            (string)($r['indicators']['grade'] ?? '')];
        $upd = $pdo->prepare(
            'UPDATE signal_state SET `signal` = ?, score = ?, confidence = ?, price = ?,
                indicators = ?, signal_id = ?, changed_at = ?, updated_at = ?, grade = ?
             WHERE symbol = ? AND tf = ?'
        );
        $upd->execute(array_merge($args, [$r['symbol'], $r['tf']]));
        if ($upd->rowCount() > 0) {
            return;
        }
        try {
            $pdo->prepare(
                'INSERT INTO signal_state (`signal`, score, confidence, price, indicators,
                    signal_id, changed_at, updated_at, grade, symbol, tf)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?)'
            )->execute(array_merge($args, [$r['symbol'], $r['tf']]));
        } catch (\Throwable $e) {
            // Already there - another scan of the same pair won the race.
        }
    }
}
