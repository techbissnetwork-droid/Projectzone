<?php
declare(strict_types=1);

namespace SignalMasterAi;

/**
 * Admin-authored rules.
 *
 * The knowledge base was editable but not extensible: an admin could change a
 * rule's weight or switch it off, but adding "RSI below 25 while price is
 * under VWAP" meant editing SignalEngine.php. This evaluates simple
 * declarative conditions stored alongside the built-in rules, so the rule set
 * can grow without touching code.
 *
 * A condition is a list of clauses joined by AND:
 *
 *     rsi < 25
 *     price < vwap
 *     adx >= 25
 *
 * Left side is an indicator name, right side is a number or another indicator.
 * That is deliberately narrow - it is a rule builder, not an expression
 * language, and nothing here is eval()'d.
 */
class CustomRules
{
    /** Indicators a custom rule may reference. */
    public const FIELDS = [
        'price' => 'Last close', 'rsi' => 'RSI(14)', 'adx' => 'ADX(14)',
        'ema20' => 'EMA 20', 'ema50' => 'EMA 50', 'ema200' => 'EMA 200',
        'vwap' => 'VWAP(20)', 'atr' => 'ATR(14)', 'macd' => 'MACD line',
        'macd_signal' => 'MACD signal', 'stoch_k' => 'Stochastic %K',
        'cci' => 'CCI(20)', 'mfi' => 'MFI(14)', 'willr' => 'Williams %R',
        'chop' => 'Choppiness index', 'bw_pct' => 'Bollinger width percentile',
        'poc' => 'Volume profile POC', 'vah' => 'Value area high', 'val' => 'Value area low',
        'support' => 'Swing support', 'resistance' => 'Swing resistance',
        'bb_upper' => 'Bollinger upper', 'bb_lower' => 'Bollinger lower',
    ];

    public const OPERATORS = ['<', '<=', '>', '>=', 'crosses_above', 'crosses_below'];

    /**
     * Parse a stored condition string into clauses.
     * Returns [] when the text contains nothing usable.
     */
    public static function parse(string $expr): array
    {
        $out = [];
        foreach (preg_split('/\R+/', trim($expr)) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (!preg_match('/^([a-z0-9_]+)\s*(crosses_above|crosses_below|<=|>=|<|>)\s*(-?[0-9.]+|[a-z0-9_]+)$/i', $line, $m)) {
                continue;
            }
            $field = strtolower($m[1]);
            $op = strtolower($m[2]);
            $rhs = strtolower($m[3]);
            if (!isset(self::FIELDS[$field]) || (!is_numeric($rhs) && !isset(self::FIELDS[$rhs]))) {
                continue;
            }
            // A CLAUSE COMPARING A FIELD WITH ITSELF IS NOT A CONDITION.
            //
            // "rsi >= rsi" parses cleanly and is true on every bar that ever
            // existed, so the rule becomes a permanent full-weight vote that
            // nobody can see is unconditional - it looks like a rule, it reads
            // like a rule in the description, and it never says no. The
            // reverse ("rsi > rsi") is the same mistake wearing the other
            // face: a rule that can never fire, quietly contributing nothing
            // while the operator believes it is working.
            if ($field === $rhs) {
                continue;
            }
            $out[] = ['field' => $field, 'op' => $op, 'rhs' => $rhs];
        }
        return $out;
    }

    /** Human-readable description of a parsed condition. */
    public static function describe(array $clauses): string
    {
        $parts = [];
        foreach ($clauses as $c) {
            $rhs = is_numeric($c['rhs']) ? $c['rhs'] : (self::FIELDS[$c['rhs']] ?? $c['rhs']);
            $op = ['crosses_above' => 'crosses above', 'crosses_below' => 'crosses below'][$c['op']] ?? $c['op'];
            $parts[] = (self::FIELDS[$c['field']] ?? $c['field']) . ' ' . $op . ' ' . $rhs;
        }
        return implode(' and ', $parts);
    }

    /**
     * Evaluate every custom rule against the current and previous bar values.
     * Returns [rule_key => direction] for the rules whose conditions all hold.
     *
     * $now and $prev are flat maps of indicator name => value.
     */
    public static function evaluate(array $rules, array $now, array $prev): array
    {
        $fired = [];
        foreach ($rules as $key => $rule) {
            $expr = (string)($rule['expr'] ?? '');
            if ($expr === '') {
                continue;   // built-in rule, evaluated in PHP
            }
            $spec = json_decode($expr, true);
            if (!is_array($spec) || empty($spec['clauses'])) {
                continue;
            }
            $dir = ($spec['direction'] ?? 'bullish') === 'bearish' ? -1 : 1;
            if (self::matches((array)$spec['clauses'], $now, $prev)) {
                $fired[$key] = $dir;
            }
        }
        return $fired;
    }

    private static function matches(array $clauses, array $now, array $prev): bool
    {
        if (!$clauses) {
            return false;
        }
        foreach ($clauses as $c) {
            $l = $now[$c['field']] ?? null;
            if ($l === null) {
                return false;   // an unavailable indicator never fires a rule
            }
            $r = is_numeric($c['rhs']) ? (float)$c['rhs'] : ($now[$c['rhs']] ?? null);
            if ($r === null) {
                return false;
            }
            $l = (float)$l;
            $r = (float)$r;
            $ok = match ($c['op']) {
                '<'  => $l < $r,
                '<=' => $l <= $r,
                '>'  => $l > $r,
                '>=' => $l >= $r,
                'crosses_above', 'crosses_below' => self::crossed($c, $now, $prev),
                default => false,
            };
            if (!$ok) {
                return false;
            }
        }
        return true;
    }

    /** A cross needs the previous bar, so it is checked separately. */
    private static function crossed(array $c, array $now, array $prev): bool
    {
        $lNow = $now[$c['field']] ?? null;
        $lPrev = $prev[$c['field']] ?? null;
        if ($lNow === null || $lPrev === null) {
            return false;
        }
        if (is_numeric($c['rhs'])) {
            $rNow = $rPrev = (float)$c['rhs'];
        } else {
            $rNow = $now[$c['rhs']] ?? null;
            $rPrev = $prev[$c['rhs']] ?? null;
            if ($rNow === null || $rPrev === null) {
                return false;
            }
        }
        return $c['op'] === 'crosses_above'
            ? ((float)$lPrev <= (float)$rPrev && (float)$lNow > (float)$rNow)
            : ((float)$lPrev >= (float)$rPrev && (float)$lNow < (float)$rNow);
    }
}
