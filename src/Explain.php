<?php
declare(strict_types=1);

namespace SignalMasterAi;

/**
 * Plain-English summary of a signal.
 *
 * The site is called SignalMasterAi and contained no AI at all - the "AI" was
 * a weighted sum of hand-written rules. This adds an optional language-model
 * layer that turns the fired-rule list into two sentences a person can read,
 * and answers questions about the current setup.
 *
 * It is deliberately optional and deliberately last in the pipeline:
 *
 *   - No API key configured means no calls, no errors, and the site behaves
 *     exactly as before.
 *   - The model never decides anything. It receives the verdict, the rules
 *     that fired and the levels, and is asked to describe them. Letting a
 *     language model influence the signal would make the track record
 *     unreproducible and the audit trail a fiction.
 *   - Output is cached per (symbol, timeframe, verdict), so a page that
 *     refreshes every 60 seconds does not bill per refresh.
 */
class Explain
{
    public static function enabled(): bool
    {
        return Database::setting('ai_enabled', '0') === '1'
            && Database::setting('ai_api_key') !== '';
    }

    /**
     * Short narrative for a signal payload. Returns null when disabled or
     * unavailable - callers render the rule list as usual.
     */
    public static function forSignal(array $signal): ?string
    {
        if (!self::enabled() || empty($signal['symbol'])) {
            return null;
        }
        // The verdict and grade are the cache identity: the same setup on the
        // same bar produces the same explanation.
        // The ladder and the regime are part of the identity too: the same
        // score in a grinding uptrend and in a whipsaw against a falling daily
        // are different setups and deserve different summaries.
        //
        // The plan is part of the identity too. $signal is analysed live on
        // every call (the comment below the prompt says so), so entry/stop/
        // target can drift bar to bar while signal/grade/score(rounded to
        // 1dp)/mtf_bias(rounded to 1dp)/regime stay identical for the whole
        // life of the setup - Cache::remember() returns the stored text on a
        // hit without ever re-reading $signal['levels'], so without this the
        // first request's prices get quoted verbatim for up to ai_cache_ttl
        // even as the levels widget on the same page has already moved on to
        // different numbers, or the cache is shared with an unrelated later
        // setup that happens to land in the same score/grade/regime bucket.
        // Rounded to the same precision the page displays, so a cache entry
        // is not fragmented by float noise that no reader could see anyway.
        $ind = (array)($signal['indicators'] ?? []);
        $lv = (array)($signal['levels'] ?? []);
        $roundLvl = static fn($v) => $v === null ? '' : round((float)$v, 6);
        $key = 'ai:' . $signal['symbol'] . ':' . ($signal['tf'] ?? '') . ':'
             . ($signal['signal'] ?? '') . ':' . ($signal['grade'] ?? '') . ':'
             . round((float)($signal['score'] ?? 0), 1) . ':'
             . round((float)($ind['mtf_bias'] ?? 0), 1) . ':'
             . (string)($ind['regime']['name'] ?? '') . ':'
             . $roundLvl($lv['entry'] ?? null) . ':' . $roundLvl($lv['stop_loss'] ?? null) . ':'
             . $roundLvl($lv['tp1'] ?? null);
        $ttl = max(300, (int)Database::setting('ai_cache_ttl', '3600'));

        return Cache::remember($key, $ttl, static function () use ($signal): ?string {
            $reasons = [];
            foreach (array_slice((array)($signal['reasons'] ?? []), 0, 14) as $r) {
                if (!empty($r['rule'])) {
                    $reasons[] = ($r['side'] ?? '') . ': ' . $r['rule'] . ' — ' . ($r['detail'] ?? '');
                }
            }
            $levels = $signal['levels'] ?? null;
            // The context that decides whether a setup is worth taking, which
            // the fired-rule list cannot express. Without it a summary can
            // describe bullish momentum and never mention that every timeframe
            // above is falling - which is the part a reader most needs.
            $context = self::context($signal);
            $prompt = "Summarise this automated technical-analysis result for a retail trader in "
                . "two or three short sentences.\n\n"
                . "Pair: {$signal['symbol']} on the {$signal['tf']} timeframe\n"
                . "Verdict: {$signal['signal']} (setup grade " . ($signal['grade'] ?? '?')
                . ", score " . ($signal['score'] ?? '?') . ")\n"
                . self::confidenceLine($signal)
                . ($context !== '' ? $context : '')
                . "Signals that fired:\n- " . implode("\n- ", $reasons) . "\n"
                . ($levels ? 'Plan: entry ' . View::price($levels['entry'] ?? null) . ', stop ' . View::price($levels['stop_loss'] ?? null) . ', '
                    . "first target {$levels['tp1']}\n" : '')
                . "\nRules: describe what the indicators are showing and the main risk to the setup. "
                . "If the higher timeframes disagree with the verdict, say so - that is the most "
                . "important thing on the page. Mention the confidence figure and, briefly, whether it "
                . "is measured from this site's own past results or still an estimate - a reader "
                . "should know which one they are looking at. "
                . "Do not add analysis of your own, do not predict a price, do not give advice, and "
                . "do not use the words 'buy' or 'sell' as a recommendation. Plain language, no "
                . "bullet points, no preamble.";

            $text = self::complete($prompt, 220);
            return $text !== null ? trim($text) : null;
        }, 600);
    }

    /**
     * Answer a member's question about a specific setup. The stored analysis
     * is the only context, so the model cannot invent a different chart.
     */
    public static function ask(array $signal, string $question): ?string
    {
        if (!self::enabled()) {
            return null;
        }
        $question = mb_substr(trim($question), 0, 300);
        if ($question === '') {
            return null;
        }
        $reasons = [];
        foreach (array_slice((array)($signal['reasons'] ?? []), 0, 16) as $r) {
            $reasons[] = ($r['side'] ?? '') . ': ' . ($r['rule'] ?? '') . ' — ' . ($r['detail'] ?? '');
        }
        $inds = $signal['indicators'] ?? [];
        $context = "Pair {$signal['symbol']} ({$signal['tf']}). Verdict {$signal['signal']}, "
            . "grade " . ($signal['grade'] ?? '?') . ", score " . ($signal['score'] ?? '?') . ".\n"
            . self::confidenceLine($signal)
            . "Indicators: RSI " . ($inds['rsi'] ?? 'n/a') . ", ADX " . ($inds['adx'] ?? 'n/a')
            . ", ATR " . ($inds['atr'] ?? 'n/a') . ", price " . ($inds['price'] ?? 'n/a') . ".\n"
            // Same context the summary gets. A member asking "why is this only
            // a C?" is usually asking about exactly this, and without it the
            // model can only guess from the rule list.
            . self::context($signal)
            . "Rules that fired:\n- " . implode("\n- ", $reasons);

        return self::complete(
            "You explain an automated technical-analysis result. Use ONLY the context below; if the "
            . "answer is not in it, say so plainly.\n\n$context\n\nQuestion: $question\n\n"
            . "Answer in at most four sentences. Educational explanation only - never advice, never "
            . "a price prediction, never a recommendation to trade.", 300);
    }

    /**
     * The confidence figure and how it was arrived at, as one prompt line.
     *
     * THE SUMMARY TALKED ABOUT GRADE AND SCORE AND NEVER ONCE MENTIONED
     * CONFIDENCE OR WHERE IT CAME FROM.
     *
     * Confidence is the one number on the page with an honest basis attached
     * to it - Confidence::evaluate() and SignalEngine.php both work hard to
     * report whether it is a measured win rate or still an estimate, and
     * confidence_basis exists specifically so a reader can tell the two
     * apart. None of that reached the plain-English summary, which is the one
     * place most members actually read: it was built from score and grade and
     * the fired rules only, so the two most work-intensive numbers on the
     * page - confidence and its basis - were the two most likely to be
     * skipped by the description of it. This gives the model both, and the
     * prompt's own instructions ask it to say which kind of number it is.
     *
     * Returns '' when the signal predates confidence_basis being stored, so
     * older cached prompts are unaffected.
     */
    private static function confidenceLine(array $signal): string
    {
        if (!isset($signal['confidence'])) {
            return '';
        }
        $basis = (string)($signal['confidence_basis'] ?? '');
        $n = (int)($signal['confidence_n'] ?? 0);
        $how = match (true) {
            $basis === 'measured' && $n > 0 =>
                "measured from {$n} of this install's own past signals of similar strength",
            $basis === 'model_learned' =>
                "the site's own trained prediction model, fitted on its verified outcomes",
            default => 'an estimate - not yet backed by enough verified outcomes to call it measured',
        };
        return 'Confidence: ' . $signal['confidence'] . '% (' . $how . ")\n";
    }

    /**
     * The situational context behind a verdict, as plain lines for a prompt.
     *
     * The fired-rule list says what the indicators noticed. It cannot say that
     * every timeframe above is falling, that the market is whipsawing, or that
     * price is sitting in a premium - and those are the things that decide
     * whether a setup is worth taking. A summary written without them can be
     * accurate about the indicators and misleading about the trade.
     *
     * Returns '' when none of it is available, so older stored signals and
     * installs with the layers switched off simply get the original prompt.
     */
    private static function context(array $signal): string
    {
        $ind = (array)($signal['indicators'] ?? []);
        $lines = [];

        $frames = (array)($ind['mtf']['frames'] ?? []);
        if ($frames) {
            $parts = [];
            foreach ($frames as $tf => $f) {
                $parts[] = $tf . ' ' . (($f['dir'] ?? 0) > 0 ? 'up' : (($f['dir'] ?? 0) < 0 ? 'down' : 'flat'));
            }
            $bias = (float)($ind['mtf_bias'] ?? 0);
            $lines[] = 'Higher timeframes: ' . implode(', ', $parts)
                . ' (net ' . ($bias > 0.1 ? 'bullish' : ($bias < -0.1 ? 'bearish' : 'undecided')) . ')';
        }

        $reg = (array)($ind['regime'] ?? []);
        if (!empty($reg['name']) && $reg['name'] !== 'unknown') {
            $lines[] = 'Market regime: ' . strtolower(Regime::label((string)$reg['name']));
        }

        $smc = (array)($ind['smc'] ?? []);
        if (!empty($smc['zone'])) {
            $lines[] = 'Price sits in the ' . $smc['zone'] . ' half of its current range';
        }

        $dims = (array)($signal['confidence_dims'] ?? []);
        if ($dims) {
            // Name the weakest axis: it is what is holding the setup back, and
            // it is the honest answer to "why is this rated so low".
            asort($dims);
            $weakest = (string)array_key_first($dims);
            $labels = Confidence::labels();
            $lines[] = 'Weakest part of the setup: ' . strtolower($labels[$weakest] ?? $weakest);
        }

        return $lines ? implode("\n", $lines) . "\n" : '';
    }

    /**
     * One completion call. Supports the OpenAI-compatible chat schema, which
     * most providers (and local runners) expose, so the operator is not locked
     * to one vendor.
     */
    private static function complete(string $prompt, int $maxTokens): ?string
    {
        $endpoint = rtrim(Database::setting('ai_endpoint', 'https://api.openai.com/v1/chat/completions'), '/');
        $key = Database::setting('ai_api_key');
        $model = Database::setting('ai_model', 'gpt-4o-mini');
        if ($key === '' || !preg_match('#^https://#', $endpoint)) {
            return null;
        }
        $body = json_encode([
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' =>
                    'You describe automated chart analysis in plain language for an educational '
                    . 'website. You never give financial advice, never predict prices, and never '
                    . 'tell anyone to trade. You only restate and explain what the analysis found.'],
                ['role' => 'user', 'content' => $prompt],
            ],
            'max_tokens' => $maxTokens,
            'temperature' => 0.3,
        ]);

        try {
            $ch = curl_init($endpoint);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $key,
                ],
            ]);
            $raw = curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);
            if ($raw === false || $code < 200 || $code >= 300) {
                Cache::increment('ai_fail', 3600);
                return null;
            }
            $d = json_decode((string)$raw, true);
            $text = $d['choices'][0]['message']['content'] ?? null;
            return is_string($text) && trim($text) !== '' ? $text : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Recent upstream failures, surfaced in the admin. */
    public static function recentFailures(): int
    {
        return (int)(Cache::get('ai_fail', 0) ?: 0);
    }
}
