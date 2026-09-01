<?php
declare(strict_types=1);

namespace SignalMasterAi;

/**
 * Lexicon-based news sentiment scoring over the headlines already stored on
 * the server. Two aggregates feed the signal engine automatically:
 *   - coin sentiment: headlines mentioning the analysed coin
 *   - market sentiment: all recent headlines (risk-on / risk-off backdrop)
 *
 * Scores are in [-1, +1]. Recent headlines weigh more than older ones.
 */
class Sentiment
{
    private const BULLISH = [
        'surge', 'surges', 'soar', 'soars', 'rally', 'rallies', 'jump', 'jumps',
        'gain', 'gains', 'record high', 'all-time high', 'ath', 'breakout',
        'bullish', 'adoption', 'approval', 'approves', 'approved', 'etf inflow',
        'inflow', 'inflows', 'accumulate', 'accumulation', 'partnership',
        'integration', 'launches', 'launch', 'upgrade', 'milestone', 'boost',
        'boosts', 'rebound', 'rebounds', 'recovery', 'recovers', 'outperform',
        'buying', 'buys', 'institutional', 'funding', 'raises', 'investment',
        'green', 'optimism', 'optimistic', 'momentum', 'climbs', 'climb',
        'strength', 'support holds', 'upside', 'growth', 'expands', 'expansion',
    ];

    private const BEARISH = [
        'crash', 'crashes', 'plunge', 'plunges', 'plummet', 'plummets', 'dump',
        'dumps', 'selloff', 'sell-off', 'tumble', 'tumbles', 'slump', 'slumps',
        'drop', 'drops', 'fall', 'falls', 'bearish', 'hack', 'hacked', 'exploit',
        'exploited', 'breach', 'stolen', 'scam', 'fraud', 'lawsuit', 'sues',
        'sued', 'ban', 'bans', 'banned', 'crackdown', 'outflow', 'outflows',
        'liquidation', 'liquidations', 'liquidated', 'bankruptcy', 'bankrupt',
        'insolvency', 'default', 'fear', 'panic', 'warning', 'warns', 'risk-off',
        'downgrade', 'decline', 'declines', 'losses', 'weakness', 'collapse',
        'collapses', 'delist', 'delisting', 'shutdown', 'red', 'correction',
        'capitulation', 'downside', 'sinks', 'sink', 'slides', 'slide',
    ];

    /**
     * @return array{coin: array{score: float, count: int}, market: array{score: float, count: int}}
     */
    public static function analyse(string $symbol, string $label, int $windowHours = 48): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT title, summary, published_at FROM news_items
             WHERE published_at >= ? ORDER BY published_at DESC LIMIT 120'
        );
        $stmt->execute([time() - $windowHours * 3600]);
        $items = self::dedupe($stmt->fetchAll());

        $needleRe = self::coinRegex($symbol, $label);
        $now = time();

        $mScore = 0.0; $mWeight = 0.0; $mCount = 0;
        $cScore = 0.0; $cWeight = 0.0; $cCount = 0;

        foreach ($items as $it) {
            $text = mb_strtolower($it['title'] . ' ' . $it['summary']);
            $s = self::scoreText($text);
            // Recency weight: 1.0 now -> ~0.33 at the window edge
            $age = max(0, $now - (int)$it['published_at']);
            $w = 1.0 / (1.0 + 2.0 * $age / ($windowHours * 3600));

            $mScore += $s * $w;
            $mWeight += $w;
            $mCount++;

            if ($needleRe !== null && preg_match($needleRe, $text)) {
                $cScore += $s * $w;
                $cWeight += $w;
                $cCount++;
            }
        }

        return [
            'coin'   => ['score' => $cWeight > 0 ? round(max(-1, min(1, $cScore / $cWeight)), 3) : 0.0, 'count' => $cCount],
            'market' => ['score' => $mWeight > 0 ? round(max(-1, min(1, $mScore / $mWeight)), 3) : 0.0, 'count' => $mCount],
        ];
    }

    private static ?string $bullRe = null;
    private static ?string $bearRe = null;

    /**
     * Collapse the same story reported by several outlets.
     *
     * Four feeds are seeded by default and they all cover the same events, so
     * one ETF approval arrived as four independent bullish votes and the
     * market-sentiment aggregate simply counted whatever was most syndicated.
     * Headlines are reduced to a bag of significant words and compared by
     * Jaccard overlap; near-duplicates keep only the earliest copy.
     */
    private static function dedupe(array $items, float $threshold = 0.6): array
    {
        $n = count($items);
        if ($n < 2) {
            return $items;
        }
        $sets = [];
        foreach ($items as $i => $it) {
            $sets[$i] = self::wordSet((string)$it['title']);
        }

        // Union-find over all pairs rather than greedy "compare to the ones
        // already kept". Greedy is order-dependent: with three rewordings of
        // one story, A~B and B~C can both clear the threshold while A~C does
        // not, so whether they collapse depends on which arrived first. The
        // same four headlines then produced two clusters or three depending on
        // row order alone.
        $parent = range(0, $n - 1);
        $find = static function (int $x) use (&$parent, &$find): int {
            while ($parent[$x] !== $x) {
                $parent[$x] = $parent[$parent[$x]];
                $x = $parent[$x];
            }
            return $x;
        };
        for ($i = 0; $i < $n; $i++) {
            if (count($sets[$i]) < 3) {
                continue;   // too short to compare meaningfully
            }
            for ($j = $i + 1; $j < $n; $j++) {
                if (count($sets[$j]) < 3) {
                    continue;
                }
                $inter = count(array_intersect_key($sets[$i], $sets[$j]));
                if ($inter === 0) {
                    continue;
                }
                $union = count($sets[$i] + $sets[$j]);
                if ($union > 0 && $inter / $union >= $threshold) {
                    $a = $find($i);
                    $b = $find($j);
                    if ($a !== $b) {
                        $parent[$a] = $b;
                    }
                }
            }
        }
        // One representative per cluster - the first occurrence, which is the
        // most recent given the caller's ordering.
        $seen = [];
        $kept = [];
        foreach ($items as $i => $it) {
            $root = $find($i);
            if (!isset($seen[$root])) {
                $seen[$root] = true;
                $kept[] = $it;
            }
        }
        return $kept;
    }

    /** Significant words of a headline, as a set keyed by word. */
    private static function wordSet(string $title): array
    {
        static $stop = ['the' => 1, 'a' => 1, 'an' => 1, 'and' => 1, 'or' => 1, 'of' => 1, 'to' => 1,
            'in' => 1, 'on' => 1, 'for' => 1, 'as' => 1, 'is' => 1, 'are' => 1, 'was' => 1, 'were' => 1,
            'be' => 1, 'by' => 1, 'at' => 1, 'it' => 1, 'its' => 1, 'with' => 1, 'from' => 1,
            'after' => 1, 'over' => 1, 'says' => 1, 'said' => 1, 'new' => 1, 'this' => 1, 'that' => 1];
        $out = [];
        foreach (preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($title)) ?: [] as $w) {
            if (mb_strlen($w) >= 3 && !isset($stop[$w])) {
                $out[$w] = true;
            }
        }
        return $out;
    }

    /**
     * Per-headline score in [-1, +1]. Whole-word matching only: without the
     * \b boundaries, "ban" fired inside "bank", "red" inside "hundred" and
     * "ath" inside "marathon" - phantom sentiment that skewed signals.
     */
    private static function scoreText(string $text): float
    {
        self::$bullRe ??= '/\b(?:' . implode('|', array_map('preg_quote', self::BULLISH)) . ')\b/u';
        self::$bearRe ??= '/\b(?:' . implode('|', array_map('preg_quote', self::BEARISH)) . ')\b/u';

        // Negation flips the sense of a term. "ETF approval denied" and "no
        // rally in sight" were both counted as bullish, because matching
        // ignored everything before the word. A short window of preceding
        // words is enough to catch the common newspaper constructions without
        // pretending this is real parsing.
        $bull = 0;
        $bear = 0;
        foreach ([[self::$bullRe, 1], [self::$bearRe, -1]] as [$re, $dir]) {
            if (!preg_match_all($re, $text, $m, PREG_OFFSET_CAPTURE)) {
                continue;
            }
            foreach ($m[0] as [$word, $offset]) {
                // Negators appear on either side in headline English: "no
                // rally in sight" puts it before, "ETF approval denied" puts
                // it after. Checking only one side left half of them counted
                // with exactly the wrong sign.
                $neg = '(?:not|no|never|without|denies|denied|deny|rejects|rejected|reject|'
                     . 'fails|failed|fail|halts|halted|halt|delays|delayed|delay|'
                     . 'unlikely|doubts?|dismisses|dismissed|refuses|refused|blocks|blocked)';
                $before = mb_substr($text, max(0, $offset - 28), min(28, $offset));
                $after = mb_substr($text, $offset + strlen($word), 24);
                $flipped = (bool)preg_match('/\b' . $neg . '\b[^.]{0,18}$/u', $before)
                        || (bool)preg_match('/^[^.]{0,12}\b' . $neg . '\b/u', $after);
                $effective = $flipped ? -$dir : $dir;
                if ($effective > 0) {
                    $bull++;
                } else {
                    $bear++;
                }
            }
        }
        if ($bull === 0 && $bear === 0) {
            return 0.0;
        }
        return max(-1.0, min(1.0, ($bull - $bear) / max(1, $bull + $bear)));
    }

    /**
     * Whole-word regex that identifies the coin in a headline ("BTC", "btc:",
     * "$btc", start of headline...). The old space-padded needle (" btc ")
     * missed mentions at the start of a title or followed by punctuation.
     */
    private static function coinRegex(string $symbol, string $label): ?string
    {
        $terms = [];
        // Base asset from the pair, e.g. BTCUSDT -> btc
        $base = preg_replace('/(USDT|USDC|FDUSD|BUSD|TUSD|BTC|ETH|BNB|EUR|TRY|DAI)$/', '', strtoupper($symbol));
        if ($base !== '' && strlen($base) >= 2) {
            $terms[] = preg_quote(strtolower($base), '/');
        }
        // Coin name from the label, e.g. "Bitcoin / USDT" -> bitcoin
        $name = mb_strtolower(trim(explode('/', $label)[0] ?? ''));
        if ($name !== '' && $name !== mb_strtolower((string)$base) && mb_strlen($name) >= 3) {
            $terms[] = preg_quote($name, '/');
        }
        return $terms ? '/(?<![a-z0-9])(?:' . implode('|', array_unique($terms)) . ')(?![a-z0-9])/u' : null;
    }
}
