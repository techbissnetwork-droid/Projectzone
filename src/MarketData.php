<?php
declare(strict_types=1);

namespace SignalMasterAi;

use PDO;

/**
 * Fetches OHLCV candles from the free Binance public REST API and stores
 * every candle in the local database. The site always serves from the DB,
 * refreshing from the API only when the cached series is older than the
 * configured TTL - so the server accumulates its own market-data history
 * and keeps working (from cache) even if the API is unreachable.
 */
class MarketData
{

    /**
     * Prefix on a fetch error meaning "every endpoint says this coin does not
     * exist". A tag rather than a second return value because the message
     * travels through an exception, a catch and two concatenations before
     * anything acts on it, and re-deriving the verdict from prose at the far
     * end is how it would come to disagree with itself.
     */
    public const PERMANENT_TAG = '[no-such-symbol]';

    private PDO $pdo;
    private string $baseUrl;
    private int $cacheTtl;
    private int $candleLimit;
    private array $config;

    public function __construct(array $config)
    {
        $this->pdo = Database::pdo();
        $this->config = $config;
        $this->baseUrl = rtrim(sma_setting('api_base_url', $config['market']['base_url']), '/');
        $this->cacheTtl = max(30, (int)sma_setting('cache_ttl', (string)$config['market']['cache_ttl']));
        $this->candleLimit = min(1000, max(100, (int)sma_setting('candle_limit', (string)$config['market']['candle_limit'])));
    }

    /**
     * How long a cached series for THIS timeframe stays fresh.
     *
     * cache_ttl was one number for every timeframe, and the default is 300
     * seconds. Applied to a daily series that means refetching it 288 times a
     * day to learn what changes once. 4h was refetched 48 times per bar, 1h
     * twelve times. None of those requests can return anything the cache did
     * not already hold, except a few more ticks on the bar still forming.
     *
     * That is the reason the scan is slow, and it multiplies: multi-timeframe
     * confluence pulls 1h, 4h and 1d for every coin it analyses, so each coin
     * costs three round trips that are almost always wasted, on top of its
     * own. On a rotation of a few hundred pairs it is the whole budget.
     *
     * A bar cannot change more often than it closes, so the interval scales
     * with the bar. The configured value stays the FLOOR - nothing is ever
     * refreshed less eagerly than the operator asked for on the fast frames,
     * and the divisor keeps a partly-formed bar reasonably current:
     *
     *   15m  max(300, 225)   = 300s   unchanged
     *   1h   max(300, 900)   = 15 min      12x fewer requests
     *   4h   max(300, 3600)  = 1 hour      48x
     *   1d   max(300, 21600) = 6 hours    288x
     *
     * The live price a member sees does not come from here - the ticker
     * endpoint serves that - so a daily candle body being up to six hours old
     * costs a chart nothing a daily chart would notice.
     */
    private function ttlFor(string $tf): int
    {
        $bar = self::TF_SECONDS[$tf] ?? 3600;
        $div = max(1, min(60, (int)sma_setting('candle_ttl_divisor', '4')));
        return max($this->cacheTtl, intdiv($bar, $div));
    }

    /**
     * The market-data endpoint list, fully admin-managed (Settings > Market
     * data). Modes:
     *   'auto'   -> every configured endpoint in priority order, with the
     *               built-in public mirrors appended as a safety net;
     *   'single' -> exactly the first endpoint, no automatic switching.
     */
    public static function endpoints(array $config): array
    {
        $urls = [];
        $primary = rtrim(Database::setting('api_base_url', $config['market']['base_url']), '/');
        if (preg_match('#^https?://#', $primary)) {
            $urls[] = $primary;
        }
        // A LEADING # MEANS OFF, NOT GONE.
        //
        // The list was a textarea, so switching a provider off meant deleting
        // its line and typing it back in later from memory. Commented out, it
        // survives in the setting, keeps its place in the priority order, and
        // the panel can offer it as a checkbox - which is what an operator
        // means by "disable this one" as opposed to "remove it".
        foreach (preg_split('/\R+/', Database::setting('api_endpoints', '')) ?: [] as $line) {
            $line = rtrim(trim($line), '/');
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (preg_match('#^https?://#', $line)) {
                $urls[] = $line;
            }
        }
        if (Database::setting('api_mode', 'auto') === 'single') {
            return [array_values(array_unique($urls))[0] ?? 'https://data-api.binance.vision'];
        }
        $urls[] = 'https://data-api.binance.vision';
        $urls[] = 'https://api.binance.com';
        return array_slice(array_values(array_unique($urls)), 0, 10);
    }

    /**
     * Return candles for a symbol/timeframe as a list of
     * ['t' => openTimeMs, 'o','h','l','c','v'] rows, oldest first.
     * Refreshes from the API when stale; falls back to cache on failure.
     */
    public function candles(string $symbol, string $tf): array
    {
        // Crypto pairs are alphanumeric (BTCUSDT); non-crypto instruments are
        // namespaced by provider (stooq:aapl.us) so the two cannot collide.
        $symbol = str_contains($symbol, ':')
            ? strtolower((string)preg_replace('/[^A-Za-z0-9:._-]/', '', $symbol))
            : strtoupper((string)preg_replace('/[^A-Za-z0-9]/', '', $symbol));
        if ($symbol === '' || !preg_match('/^[0-9]{1,2}(mo|m|h|d|w)$/', $tf)) {
            throw new \InvalidArgumentException('Invalid symbol or interval');
        }

        $stmt = $this->pdo->prepare('SELECT fetched_at FROM fetch_log WHERE symbol = ? AND tf = ?');
        $stmt->execute([$symbol, $tf]);
        $fetchedAt = $stmt->fetchColumn();
        $stmt->closeCursor();           // see Database::pdo() - a held read blocks every later write
        $stale = $fetchedAt === false || (time() - (int)$fetchedAt) > $this->ttlFor($tf);

        $fetchError = null;
        if ($stale) {
            try {
                $this->refresh($symbol, $tf);
            } catch (\Throwable $e) {
                $fetchError = $e->getMessage();
            }
        }

        $rows = $this->fromDb($symbol, $tf);
        if (!$rows && $fetchError !== null) {
            throw new \RuntimeException("No cached data and API fetch failed: $fetchError");
        }
        // Data-quality audit on refresh: a series with holes silently produces
        // wrong indicators, so record it and (optionally) repair it.
        if ($stale && $rows) {
            $this->auditContinuity($symbol, $tf, $rows);
        }
        return $rows;
    }

    /**
     * Record - and optionally repair - discontinuities in a refreshed series.
     * The quality flag is surfaced on the status page and in the admin health
     * panel so a broken feed cannot stay invisible.
     */
    private function auditContinuity(string $symbol, string $tf, array $rows): void
    {
        $report = self::gapReport($rows, $tf);
        $key = 'gaps:' . $symbol . ':' . $tf;
        if ($report['gaps'] === 0) {
            Cache::set($key, 0, 86400);
            return;
        }
        Cache::set($key, $report['missing'], 86400);
        if (Database::setting('auto_backfill_gaps', '1') === '1') {
            try {
                $this->healGaps($symbol, $tf, 2);
            } catch (\Throwable $e) {
                // repair is best-effort; the flag stays raised either way
            }
        }
    }

    /**
     * List every actively trading pair on the exchange, optionally filtered
     * by quote asset (e.g. USDT). Returns ['symbol' => ..., 'base' => ...,
     * 'quote' => ...] rows. Used by Admin > Symbols "import all".
     */
    public function exchangeSymbols(string $quote = 'USDT'): array
    {
        $hosts = self::endpoints($this->config);
        $lastError = 'no hosts configured';
        foreach ($hosts as $host) {
            $ch = curl_init($host . '/api/v3/exchangeInfo');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_USERAGENT      => 'SignalMasterAi/1.0',
                CURLOPT_ENCODING       => '',
            ]);
            $body = curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $err  = curl_error($ch);
            curl_close($ch);
            if ($body === false || $code !== 200) {
                $lastError = "$host: " . ($body === false ? $err : "HTTP $code");
                continue;
            }
            $data = json_decode($body, true);
            if (!is_array($data) || !isset($data['symbols'])) {
                $lastError = "$host: invalid JSON";
                continue;
            }
            $out = [];
            foreach ($data['symbols'] as $s) {
                if (($s['status'] ?? '') !== 'TRADING') {
                    continue;
                }
                if ($quote !== '' && ($s['quoteAsset'] ?? '') !== $quote) {
                    continue;
                }
                $out[] = [
                    'symbol' => (string)$s['symbol'],
                    'base'   => (string)$s['baseAsset'],
                    'quote'  => (string)$s['quoteAsset'],
                ];
            }
            return $out;
        }
        throw new \RuntimeException("exchangeInfo failed: $lastError");
    }

    public const TF_SECONDS = ['1m' => 60, '3m' => 180, '5m' => 300, '15m' => 900, '30m' => 1800,
        '1h' => 3600, '2h' => 7200, '4h' => 14400, '6h' => 21600, '8h' => 28800,
        '12h' => 43200, '1d' => 86400, '3d' => 259200, '1w' => 604800, '1mo' => 2592000];

    /**
     * Candles as they existed at a point in time - nothing after $asOfMs is
     * returned.
     *
     * The backtester runs the engine on historical bars, but rules like
     * higher-timeframe confluence and the BTC regime filter reach out to other
     * series through MarketData. Served from the live table those lookups
     * would see the future, silently inflating every backtest. This method is
     * what makes those rules testable honestly.
     */
    public function candlesAsOf(string $symbol, string $tf, int $asOfMs): array
    {
        $symbol = strtoupper((string)preg_replace('/[^A-Za-z0-9]/', '', $symbol));
        if ($symbol === '' || !isset(self::TF_SECONDS[$tf])) {
            return [];
        }
        $stmt = $this->pdo->prepare(
            'SELECT open_time, open, high, low, close, volume FROM candles
             WHERE symbol = ? AND tf = ? AND open_time <= ? ORDER BY open_time DESC LIMIT ?'
        );
        $stmt->bindValue(1, $symbol);
        $stmt->bindValue(2, $tf);
        $stmt->bindValue(3, $asOfMs, PDO::PARAM_INT);
        $stmt->bindValue(4, $this->candleLimit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map(static fn($r) => [
            't' => (int)$r['open_time'],
            'o' => (float)$r['open'],
            'h' => (float)$r['high'],
            'l' => (float)$r['low'],
            'c' => (float)$r['close'],
            'v' => (float)$r['volume'],
        ], array_reverse($stmt->fetchAll()));
    }

    /**
     * Exchange clock, cached hourly, expressed as an offset from local time.
     *
     * "Is this candle still forming?" was answered against the web server's
     * own clock. A server drifting by a few minutes - common on cheap shared
     * hosting - keeps a forming bar or drops a closed one, which silently
     * corrupts every rule for that pass. Anchoring on the exchange's own
     * serverTime removes an entire class of invisible error.
     */
    public function clockOffsetMs(): int
    {
        $offset = Cache::remember('clock_offset', 3600, function (): ?int {
            foreach (array_slice(self::endpoints($this->config), 0, 2) as $host) {
                try {
                    if (self::provider($host) !== 'binance') {
                        continue;
                    }
                    $localBefore = (int)round(microtime(true) * 1000);
                    $d = $this->http($host . '/api/v3/time');
                    $localAfter = (int)round(microtime(true) * 1000);
                    if (isset($d['serverTime']) && is_numeric($d['serverTime'])) {
                        // Compensate for round-trip latency (midpoint estimate).
                        return (int)$d['serverTime'] - intdiv($localBefore + $localAfter, 2);
                    }
                } catch (\Throwable $e) {
                    // fall through to the next host
                }
            }
            return null;
        }, 600);
        // A wildly implausible offset means something is wrong upstream; a
        // drifting local clock is still better than a garbage correction.
        $offset = (int)($offset ?? 0);
        return abs($offset) > 86400000 ? 0 : $offset;
    }

    /** Exchange-anchored "now" in milliseconds. */
    public function nowMs(): int
    {
        return (int)round(microtime(true) * 1000) + $this->clockOffsetMs();
    }

    /**
     * Continuity audit of a stored series: reports missing bars.
     *
     * Nothing verified that cached candles were contiguous. If the API was
     * down, rate-limited, or a mirror returned a partial page, indicators
     * computed straight across the hole and every rule was quietly wrong for
     * that pair - with no symptom anywhere in the UI.
     *
     * Returns ['gaps' => n, 'missing' => bars, 'ranges' => [[fromMs,toMs],...]].
     */
    public static function gapReport(array $candles, string $tf): array
    {
        $step = (self::TF_SECONDS[$tf] ?? 3600) * 1000;
        $gaps = 0;
        $missing = 0;
        $ranges = [];
        for ($i = 1, $n = count($candles); $i < $n; $i++) {
            $delta = (int)$candles[$i]['t'] - (int)$candles[$i - 1]['t'];
            if ($delta > $step) {
                $lost = intdiv($delta, $step) - 1;
                if ($lost > 0) {
                    $gaps++;
                    $missing += $lost;
                    if (count($ranges) < 12) {
                        $ranges[] = [(int)$candles[$i - 1]['t'] + $step, (int)$candles[$i]['t']];
                    }
                }
            }
        }
        return ['gaps' => $gaps, 'missing' => $missing, 'ranges' => $ranges];
    }

    /**
     * Fetch a specific historical window (Binance-compatible hosts only) to
     * repair the holes gapReport() found.
     */
    public function backfill(string $symbol, string $tf, int $startMs, int $endMs): int
    {
        foreach (self::endpoints($this->config) as $host) {
            if (self::provider($host) !== 'binance') {
                continue;
            }
            try {
                $data = $this->http($host . '/api/v3/klines?' . http_build_query([
                    'symbol' => $symbol,
                    'interval' => $tf === '1mo' ? '1M' : $tf,
                    'startTime' => $startMs,
                    'endTime' => $endMs,
                    'limit' => 1000,
                ]));
                $rows = [];
                foreach ($data as $r) {
                    if (is_array($r) && count($r) >= 6) {
                        $rows[] = [(int)$r[0], (float)$r[1], (float)$r[2], (float)$r[3], (float)$r[4], (float)$r[5]];
                    }
                }
                if ($rows) {
                    return $this->storeRows($symbol, $tf, $rows, false);
                }
            } catch (\Throwable $e) {
                // try the next mirror
            }
        }
        return 0;
    }

    /**
     * Repair every detected gap in a series, newest first, bounded so one
     * cron tick can never spend forever backfilling a badly damaged pair.
     */
    public function healGaps(string $symbol, string $tf, int $maxRanges = 3): array
    {
        $report = self::gapReport($this->fromDb($symbol, $tf), $tf);
        if ($report['gaps'] === 0) {
            return ['gaps' => 0, 'filled' => 0];
        }
        $filled = 0;
        foreach (array_slice(array_reverse($report['ranges']), 0, $maxRanges) as [$from, $to]) {
            $filled += $this->backfill($symbol, $tf, $from, $to);
        }
        return ['gaps' => $report['gaps'], 'filled' => $filled];
    }

    /**
     * 24h quote volume - the liquidity gate.
     *
     * The engine happily signalled on coins with a few tens of thousands of
     * dollars of daily turnover, where a take-profit two ATR away simply is
     * not reachable without moving the market yourself. Cached for 30 minutes.
     */
    /**
     * Every pair on the exchange with its 24h quote volume, biggest first.
     *
     * One request for the whole book rather than one per coin: importing "the
     * top 100" by asking about four hundred coins individually is four hundred
     * requests and a rate-limit ban.
     *
     * Volume, not market cap. They rank differently and the difference matters
     * here - market cap is a claim about supply, and what decides whether this
     * engine can read a coin is whether anyone is trading it. A large-cap coin
     * with no volume on this exchange produces gappy candles and unreliable
     * levels; a mid-cap with heavy volume produces clean ones. Volume is also
     * the exchange's own number, so it needs no third party and cannot go
     * stale behind an API key.
     *
     * @return array<int,array{symbol:string,base:string,quote:string,volume:float}>
     */
    public function rankedSymbols(string $quote = 'USDT'): array
    {
        $lastError = 'no hosts configured';
        foreach (self::endpoints($this->config) as $host) {
            if (self::provider($host) !== 'binance') {
                continue;                     // this shape is Binance-compatible only
            }
            try {
                $rows = $this->http($host . '/api/v3/ticker/24hr');
            } catch (\Throwable $e) {
                $lastError = $host . ': ' . $e->getMessage();
                continue;
            }
            $out = [];
            foreach ($rows as $r) {
                $sym = strtoupper((string)($r['symbol'] ?? ''));
                if ($sym === '' || ($quote !== '' && !str_ends_with($sym, $quote))) {
                    continue;
                }
                // Leveraged tokens and the exchange's own wrappers are not
                // coins an engine should be reading: they decay against their
                // own index and the chart says nothing about the asset.
                if (preg_match('/(UP|DOWN|BULL|BEAR)' . preg_quote($quote, '/') . '$/', $sym)) {
                    continue;
                }
                $vol = (float)($r['quoteVolume'] ?? 0);
                if ($vol <= 0) {
                    continue;                 // never traded, or delisted in place
                }
                // Price and 24h move come free in the same payload. They are
                // not used for ranking - they are what an operator reads when
                // deciding whether a row is worth publishing.
                $out[] = [
                    'symbol' => $sym,
                    'base'   => substr($sym, 0, -strlen($quote)),
                    'quote'  => $quote,
                    'volume' => $vol,
                    'price'  => (float)($r['lastPrice'] ?? 0),
                    'change' => (float)($r['priceChangePercent'] ?? 0),
                ];
            }
            if ($out) {
                usort($out, static fn($a, $b) => $b['volume'] <=> $a['volume']);
                return $out;
            }
            $lastError = $host . ': no ' . $quote . ' pairs in the ticker';
        }
        throw new \RuntimeException('24h ticker unavailable: ' . $lastError);
    }

    public function quoteVolume24h(string $symbol): ?float
    {
        if (!self::isCrypto($symbol)) {
            return null;   // liquidity gate does not apply off-exchange
        }
        $v = Cache::remember('vol24:' . $symbol, 1800, function () use ($symbol): ?float {
            foreach (self::endpoints($this->config) as $host) {
                try {
                    switch (self::provider($host)) {
                        case 'okx':
                        case 'kucoin':
                        case 'bybit':
                            continue 2;   // only the Binance-compatible shape here
                        default:
                            $d = $this->http($host . '/api/v3/ticker/24hr?symbol=' . urlencode($symbol));
                            if (isset($d['quoteVolume']) && is_numeric($d['quoteVolume'])) {
                                return (float)$d['quoteVolume'];
                            }
                    }
                } catch (\Throwable $e) {
                    // next host
                }
            }
            return null;
        }, 900);
        return $v === null ? null : (float)$v;
    }

    /**
     * Futures data hosts, in priority order.
     *
     * Binance's futures host is geo-restricted in more places than the spot
     * market-data host (it answers HTTP 451 outright), so open interest,
     * funding and the long/short ratio silently vanish for those operators.
     * The list is admin-editable: point it at a regional mirror or your own
     * proxy and the positioning rules come back to life.
     */
    public static function futuresEndpoints(): array
    {
        $urls = [];
        $primary = rtrim(Database::setting('funding_api_url', 'https://fapi.binance.com'), '/');
        if (preg_match('#^https?://#', $primary)) {
            $urls[] = $primary;
        }
        foreach (preg_split('/\R+/', Database::setting('futures_endpoints', '')) ?: [] as $line) {
            $line = rtrim(trim($line), '/');
            if (preg_match('#^https?://#', $line)) {
                $urls[] = $line;
            }
        }
        return array_slice(array_values(array_unique($urls)), 0, 5);
    }

    /**
     * The rules that go silent when no futures host answers.
     *
     * Named here so the panel can say which votes are missing rather than
     * leaving an operator to work out why a rule they can see in the rule list
     * has never once appeared in a signal.
     */
    public const FUTURES_RULES = [
        'oi_confirmation'    => 'Open-interest confirmation',
        'funding_extreme'    => 'Funding-rate contrarian',
        'long_short_extreme' => 'Long/short crowd extreme',
        'taker_flow'         => 'Aggressive buy/sell flow',
    ];

    /**
     * Is the futures feed actually reachable?
     *
     * THE FAILURE THAT SAID NOTHING.
     *
     * futuresGet() catches everything and returns null, so an unreachable host
     * looks exactly like a coin with no perpetual: the rule quietly does not
     * vote, and no screen anywhere says why. Measured on the machine this was
     * written on, fapi.binance.com answers
     *
     *     HTTP 451 - Service unavailable from a restricted location
     *
     * a geo-block - and the four rules above had fired ZERO times across a
     * thousand stored signals. Four rules in the rule list, tunable, described
     * in the knowledge base, and structurally incapable of ever firing.
     *
     * @return array<int,array{host:string,ok:bool,code:int,ms:int,note:string}>
     */
    public static function futuresHealth(): array
    {
        $out = [];
        foreach (self::futuresEndpoints() as $host) {
            $t0 = microtime(true);
            $ch = curl_init($host . '/fapi/v1/time');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 8,
                CURLOPT_CONNECTTIMEOUT => 5,
            ]);
            $body = (string)curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $err = curl_error($ch);
            curl_close($ch);
            $note = '';
            if ($code === 451 || $code === 403) {
                // The single most common cause, and the one an operator would
                // never guess from a silent rule.
                $note = 'Blocked for this server location. Binance geo-restricts its '
                    . 'futures API; a mirror or a proxy in an allowed region is the fix.';
            } elseif ($code === 429) {
                $note = 'Rate limited.';
            } elseif ($err !== '') {
                $note = $err;
            } elseif ($code !== 200) {
                $note = 'Unexpected reply: ' . substr($body, 0, 80);
            }
            $out[] = [
                'host' => $host,
                'ok' => $code === 200,
                'code' => $code,
                'ms' => (int)round((microtime(true) - $t0) * 1000),
                'note' => $note,
            ];
        }
        return $out;
    }

    /** Try each futures host until one answers; null when all fail. */
    private function futuresGet(string $path): ?array
    {
        foreach (self::futuresEndpoints() as $host) {
            try {
                $d = $this->http($host . $path);
                if (is_array($d) && !isset($d['code'])) {
                    return $d;
                }
            } catch (\Throwable $e) {
                // geo-block, rate limit or no perp for this symbol - try next
            }
        }
        return null;
    }

    /**
     * Open-interest change over the recent window, as a percentage.
     *
     * This is the confirmation filter crypto actually needs: price up with
     * open interest up is new money taking risk (a real trend), price up with
     * open interest falling is short covering (a move with nothing behind it).
     * Free Binance futures endpoint; null for spot-only coins.
     */
    public function openInterestChange(string $symbol, string $tf): ?float
    {
        // Binance's OI history has no sub-5m period - 1m/3m read the 5m series.
        $period = ['1m' => '5m', '3m' => '5m', '5m' => '5m', '15m' => '15m', '30m' => '30m',
                   '1h' => '1h', '4h' => '4h', '1d' => '1d'][$tf] ?? '1h';
        return Cache::remember('oi:' . $symbol . ':' . $period, 900, function () use ($symbol, $period): ?float {
            $d = $this->futuresGet('/futures/data/openInterestHist?' . http_build_query([
                'symbol' => $symbol, 'period' => $period, 'limit' => 12,
            ]));
            if (!is_array($d) || count($d) < 4) {
                return null;
            }
            $first = (float)($d[0]['sumOpenInterest'] ?? 0);
            $last  = (float)($d[count($d) - 1]['sumOpenInterest'] ?? 0);
            if ($first <= 0 || $last <= 0) {
                return null;
            }
            return round(($last - $first) / $first * 100, 3);
        }, 900);
    }

    /**
     * Taker buy/sell volume ratio: who is crossing the spread.
     *
     * Long/short ratio says what positions people are holding; this says who
     * is actually paying up to get in right now. Above 1 means aggressive
     * buyers are lifting offers, below 1 means sellers are hitting bids. It
     * leads the positioning reads because it is flow rather than inventory.
     * Free Binance futures endpoint; null for spot-only coins.
     */
    public function takerRatio(string $symbol, string $tf = '1h'): ?float
    {
        $period = ['1m' => '5m', '3m' => '5m', '5m' => '5m', '15m' => '15m', '30m' => '30m',
                   '1h' => '1h', '4h' => '4h', '1d' => '1d'][$tf] ?? '1h';
        return Cache::remember('taker:' . $symbol . ':' . $period, 900, function () use ($symbol, $period): ?float {
            $d = $this->futuresGet('/futures/data/takerlongshortRatio?' . http_build_query([
                'symbol' => $symbol, 'period' => $period, 'limit' => 1,
            ]));
            $r = $d[0]['buySellRatio'] ?? null;
            return is_numeric($r) ? round((float)$r, 4) : null;
        }, 900);
    }

    /**
     * Market breadth: the share of enabled coins trading above their own
     * 20-bar average, 0..100.
     *
     * Computed entirely from candles already on disk - no request, no key -
     * so it costs nothing on shared hosting. A single coin rallying while
     * everything else bleeds is a different market from one where three
     * quarters of the board is green, and no single-pair indicator can see
     * the difference.
     */
    public function breadth(string $tf = '1h'): ?float
    {
        return Cache::remember('breadth:' . $tf, 900, function () use ($tf): ?float {
            $syms = $this->pdo->query('SELECT symbol FROM symbols WHERE enabled = 1 LIMIT 40')
                ->fetchAll(\PDO::FETCH_COLUMN);
            $above = 0;
            $seen = 0;
            $stmt = $this->pdo->prepare(
                'SELECT close FROM candles WHERE symbol = ? AND tf = ? ORDER BY open_time DESC LIMIT 21');
            foreach ($syms as $sym) {
                $stmt->execute([$sym, $tf]);
                $c = array_map('floatval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
                if (count($c) < 21) {
                    continue;   // not enough history stored for this pair yet
                }
                $last = $c[0];
                $avg = array_sum(array_slice($c, 1, 20)) / 20;
                if ($avg <= 0) {
                    continue;
                }
                $seen++;
                if ($last > $avg) {
                    $above++;
                }
            }
            // Under ten readable pairs this is noise dressed as a statistic.
            return $seen >= 10 ? round($above / $seen * 100, 1) : null;
        }, 900);
    }

    /**
     * Top-trader long/short account ratio. A cleaner crowding read than
     * funding alone, because funding also reflects basis and carry trades.
     */
    public function longShortRatio(string $symbol): ?float
    {
        return Cache::remember('lsr:' . $symbol, 900, function () use ($symbol): ?float {
            $d = $this->futuresGet('/futures/data/globalLongShortAccountRatio?' . http_build_query([
                'symbol' => $symbol, 'period' => '1h', 'limit' => 1,
            ]));
            $r = $d[0]['longShortRatio'] ?? null;
            return is_numeric($r) ? round((float)$r, 4) : null;
        }, 900);
    }

    /**
     * Order-book imbalance in [-1, +1]: (bids - asks) / (bids + asks) over the
     * top of book. Positive means resting bid support, negative means a wall
     * of offers overhead. Short TTL - depth changes second to second.
     */
    public function bookImbalance(string $symbol, int $limit = 100): ?float
    {
        if (!self::isCrypto($symbol)) {
            return null;
        }
        return Cache::remember('book:' . $symbol, 30, function () use ($symbol, $limit): ?float {
            foreach (self::endpoints($this->config) as $host) {
                if (self::provider($host) !== 'binance') {
                    continue;
                }
                try {
                    $d = $this->http($host . '/api/v3/depth?symbol=' . urlencode($symbol) . '&limit=' . $limit);
                    $bids = 0.0;
                    $asks = 0.0;
                    foreach ((array)($d['bids'] ?? []) as $b) {
                        $bids += (float)$b[0] * (float)$b[1];
                    }
                    foreach ((array)($d['asks'] ?? []) as $a) {
                        $asks += (float)$a[0] * (float)$a[1];
                    }
                    $tot = $bids + $asks;
                    return $tot > 0 ? round(($bids - $asks) / $tot, 4) : null;
                } catch (\Throwable $e) {
                    // next host
                }
            }
            return null;
        }, 60);
    }

    /**
     * Price tick size for a symbol, so the UI can format levels at the
     * precision the exchange actually quotes instead of a blanket 2 decimals.
     * Cached for a day; falls back to a sensible guess from the price.
     */
    public function tickSize(string $symbol): ?float
    {
        if (!self::isCrypto($symbol)) {
            return null;   // client falls back to magnitude-based precision
        }
        $t = Cache::remember('tick_size:' . $symbol, 86400, function () use ($symbol): ?float {
            foreach (self::endpoints($this->config) as $host) {
                if (self::provider($host) !== 'binance') {
                    continue;
                }
                try {
                    $d = $this->http($host . '/api/v3/exchangeInfo?symbol=' . urlencode($symbol));
                    foreach ((array)($d['symbols'] ?? []) as $s) {
                        foreach ((array)($s['filters'] ?? []) as $f) {
                            if (($f['filterType'] ?? '') === 'PRICE_FILTER' && isset($f['tickSize'])) {
                                return (float)$f['tickSize'];
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    // next host
                }
            }
            return null;
        }, 3600);
        return $t === null ? null : (float)$t;
    }

    private function fromDb(string $symbol, string $tf): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT open_time, open, high, low, close, volume FROM candles
             WHERE symbol = ? AND tf = ? ORDER BY open_time DESC LIMIT ?'
        );
        $stmt->bindValue(1, $symbol);
        $stmt->bindValue(2, $tf);
        $stmt->bindValue(3, $this->candleLimit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = array_reverse($stmt->fetchAll());
        return array_map(static fn($r) => [
            't' => (int)$r['open_time'],
            'o' => (float)$r['open'],
            'h' => (float)$r['high'],
            'l' => (float)$r['low'],
            'c' => (float)$r['close'],
            'v' => (float)$r['volume'],
        ], $rows);
    }

    /**
     * The exchange's own words for why it refused, trimmed to fit an error.
     *
     * "Provider returned HTTP 400" is the same string whether the coin does
     * not exist or the request was rate limited, and the difference decides
     * whether a coin gets dropped from the scan - so it must not be thrown
     * away. Binance answers a bad symbol with {"code":-1121,"msg":"Invalid
     * symbol."}; that sentence is the whole answer and it was being discarded
     * one line before anything could read it.
     */
    private static function why($body): string
    {
        $txt = is_string($body) ? trim($body) : '';
        if ($txt === '') {
            return '';
        }
        $j = json_decode($txt, true);
        if (is_array($j)) {
            foreach (['msg', 'message', 'retMsg', 'error', 'Message'] as $k) {
                if (!empty($j[$k]) && is_string($j[$k])) {
                    $txt = $j[$k];
                    break;
                }
            }
        }
        $txt = trim(preg_replace('/\s+/', ' ', strip_tags($txt)) ?? '');
        return $txt === '' ? '' : ' - ' . mb_substr($txt, 0, 120);
    }

    /**
     * Does this failure mean the coin is NOT THERE, as opposed to not
     * answering right now?
     *
     * The distinction is the whole difference between a watchlist that prunes
     * itself and one that empties itself. A rate limit, a timeout, a 5xx, a
     * geo-block - every one of those is the exchange being unavailable to us,
     * and the coin is still perfectly real. Only an explicit "no such symbol"
     * says otherwise.
     *
     * Deliberately conservative: anything not recognised as permanent is
     * treated as temporary. The cost of getting that wrong in this direction
     * is a dead coin staying in the rotation and wasting a slot; in the other
     * direction it is a live coin silently dropped and never scanned again,
     * which nobody notices until a member asks why it stopped alerting.
     */
    public static function failureIsPermanent(string $msg): bool
    {
        // Punctuation folded to spaces before matching. Providers decorate
        // these sentences - TwelveData answers "**symbol** not found", which
        // does not contain the substring "symbol not found" and so read as a
        // temporary failure while saying the opposite in plain English.
        $m = strtolower($msg);
        $m = trim((string)preg_replace('/\s+/', ' ', (string)preg_replace('/[^a-z0-9]+/', ' ', $m)));
        // Explicitly temporary wins even if the text also mentions a symbol.
        foreach (['timed out', 'timeout', 'could not resolve', 'connection',
                  'rate limit', 'too many', 'http 429', 'http 418', 'http 403',
                  'http 451', 'http 500', 'http 502', 'http 503', 'http 504',
                  'ssl', 'temporarily', 'try again', 'invalid json', 'no candles',
                  'no hosts configured', 'empty reply', 'reset by peer'] as $t) {
            if (str_contains($m, $t)) {
                return false;
            }
        }
        foreach (['invalid symbol', 'unknown symbol', 'symbol not found',
                  'does not exist', 'not exist', 'no such', 'instrument id',
                  'invalid instrument', 'unsupported symbol', 'delisted',
                  'not supported', 'invalid pair'] as $t) {
            if (str_contains($m, $t)) {
                return true;
            }
        }
        return false;
    }

    private function refresh(string $symbol, string $tf): void
    {
        // Admin-managed endpoint list: auto-failover or selected-only.
        $hosts = $this->endpointsFor($symbol);
        $lastError = 'no hosts configured';
        // A coin is only "not on the exchange" when EVERY endpoint says so.
        // One provider not listing a pair the others do is a gap in that
        // provider, not a dead coin - and with auto-failover configured it is
        // not even a problem, because the next host served it.
        $tried = 0;
        $permanent = 0;
        foreach ($hosts as $host) {
            try {
                $this->fetchFrom($host, $symbol, $tf);
                return;
            } catch (\Throwable $e) {
                $lastError = "$host: {$e->getMessage()}";
                $tried++;
                if (self::failureIsPermanent($e->getMessage())) {
                    $permanent++;
                }
            }
        }
        // Tag the message so the caller can tell the two apart without
        // re-deriving it from prose that has already been concatenated twice.
        if ($tried > 0 && $permanent === $tried) {
            $lastError = self::PERMANENT_TAG . ' ' . $lastError;
        }
        // Every configured endpoint refused. Callers fall back to whatever is
        // already in the database and carry on, which is right for the page
        // and invisible to the operator - so it is written down here, at the
        // one point that knows the fetch failed rather than merely returned
        // stale candles.
        ErrorLog::record(ErrorLog::MARKET, 'Candle fetch failed - ' . $lastError, $symbol . ' ' . $tf);
        throw new \RuntimeException($lastError);
    }

    /**
     * Every provider this install can talk to, and how to check one is alive.
     *
     * key => [label, health path, what a 200 there actually proves]
     *
     * THE HEALTH PATH IS PER PROVIDER, AND IT WAS NOT.
     *
     * The endpoint test pinged `/api/v3/ping` - Binance's path - at every URL
     * in the list, whatever it was. An OKX, Bybit or KuCoin endpoint has no
     * such route, so it answered 404 and the test reported FAILED on a
     * provider that was working perfectly. An operator who added OKX as a
     * backup was told it was broken, and the honest response to that is to
     * remove it, which leaves the install with fewer working feeds than it
     * had. A test that fails on correct configuration is worse than no test.
     */
    public const PROVIDERS = [
        'binance' => ['Binance-compatible', '/api/v3/ping',
            'mirrors, MEXC and any /api/v3 proxy answer this'],
        'okx'     => ['OKX', '/api/v5/public/time', ''],
        'bybit'   => ['Bybit', '/v5/market/time', ''],
        'kucoin'  => ['KuCoin', '/api/v1/timestamp', ''],
        // The two non-crypto venues have no unauthenticated health route, so
        // the check is the host answering at all. Said plainly where it is
        // shown rather than dressed up as a working-feed test.
        'stooq'      => ['Stooq (stocks)', '/', 'host reachable only - no free health route'],
        'twelvedata' => ['Twelve Data (FX, stocks)', '/', 'host reachable only - the feed itself needs the API key'],
    ];

    /** The health path for whatever provider a URL turns out to be. */
    public static function pingPath(string $hostUrl): string
    {
        return self::PROVIDERS[self::provider($hostUrl)][1] ?? '/api/v3/ping';
    }

    /** Human label for the provider a URL resolves to. */
    public static function providerLabel(string $hostUrl): string
    {
        $k = self::provider($hostUrl);
        return self::PROVIDERS[$k][0] ?? $k;
    }

    /**
     * Provider auto-detection by host. 'binance' also covers any
     * Binance-compatible /api/v3 endpoint (mirrors, MEXC, custom proxies).
     */
    public static function provider(string $hostUrl): string
    {
        $h = (string)(parse_url($hostUrl, PHP_URL_HOST) ?? $hostUrl);
        if (str_contains($h, 'okx')) {
            return 'okx';
        }
        if (str_contains($h, 'bybit')) {
            return 'bybit';
        }
        if (str_contains($h, 'kucoin')) {
            return 'kucoin';
        }
        // Non-crypto venues. The engine itself is asset-agnostic - it reads
        // OHLCV and knows nothing about what produced it - so supporting
        // equities, FX and indices is purely a data-layer adapter. Only the
        // crypto-specific rules (funding, open interest, BTC regime) sit out.
        if (str_contains($h, 'stooq')) {
            return 'stooq';
        }
        if (str_contains($h, 'twelvedata')) {
            return 'twelvedata';
        }
        return 'binance';
    }

    /**
     * Hosts to try for a given symbol.
     *
     * A namespaced instrument (stooq:aapl.us) routes to its own provider;
     * everything else uses the crypto endpoint list. Without this, a
     * non-crypto symbol would be asked of a Binance mirror and simply fail.
     */
    private function endpointsFor(string $symbol): array
    {
        $ns = str_contains($symbol, ':') ? strtolower(explode(':', $symbol, 2)[0]) : '';
        return match ($ns) {
            'stooq' => [rtrim(Database::setting('stooq_endpoint', 'https://stooq.com'), '/')],
            'td', 'twelvedata' => [rtrim(Database::setting('twelvedata_endpoint', 'https://api.twelvedata.com'), '/')],
            default => self::endpoints($this->config),
        };
    }

    /** Provider implied by a namespaced symbol, or null for crypto pairs. */
    public static function providerForSymbol(string $symbol): ?string
    {
        if (!str_contains($symbol, ':')) {
            return null;
        }
        return match (strtolower(explode(':', $symbol, 2)[0])) {
            'stooq' => 'stooq',
            'td', 'twelvedata' => 'twelvedata',
            default => null,
        };
    }

    /** Does this symbol trade on a crypto venue? Gates the crypto-only rules. */
    public static function isCrypto(string $symbol): bool
    {
        return !str_contains($symbol, ':');
    }

    /** Split BTCUSDT into [BTC, USDT] for providers using dashed pairs. */
    public static function splitPair(string $symbol): array
    {
        foreach (['USDT', 'USDC', 'FDUSD', 'TUSD', 'BUSD', 'DAI', 'BTC', 'ETH', 'BNB', 'EUR', 'TRY', 'GBP'] as $q) {
            if (str_ends_with($symbol, $q) && strlen($symbol) > strlen($q)) {
                return [substr($symbol, 0, -strlen($q)), $q];
            }
        }
        return [$symbol, 'USDT'];
    }

    /** Raw response body, for providers that serve CSV rather than JSON. */
    private function httpRaw(string $url): string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_USERAGENT      => 'SignalMasterAi/1.0',
            CURLOPT_ENCODING       => '',
        ]);
        $body = curl_exec($ch);
        $err  = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($body === false) {
            throw new \RuntimeException("Request failed: $err");
        }
        if ($code !== 200) {
            throw new \RuntimeException("Provider returned HTTP $code" . self::why($body));
        }
        return (string)$body;
    }

    private function http(string $url): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_USERAGENT      => 'SignalMasterAi/1.0',
            CURLOPT_ENCODING       => '',
        ]);
        $body = curl_exec($ch);
        $err  = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($body === false) {
            throw new \RuntimeException("API request failed: $err");
        }
        if ($code !== 200) {
            throw new \RuntimeException("API returned HTTP $code" . self::why($body));
        }
        $data = json_decode($body, true);
        if (!is_array($data)) {
            throw new \RuntimeException('API returned invalid JSON');
        }
        return $data;
    }

    /**
     * Fetch klines from one host in its native format and normalise to
     * rows of [openTimeMs, open, high, low, close, volume], oldest first.
     */
    /**
     * Warm the cache for many pairs at once, over concurrent connections.
     *
     * The scan was strictly serial: one pair's request had to come back before
     * the next one was even opened. That is the wrong shape for this work,
     * because almost none of the time is spent computing - it is spent waiting
     * on a socket, and a machine can wait on fifty sockets as easily as one.
     * With the per-timeframe TTL keeping the higher frames out of the way, a
     * rotation's remaining cost is one request per due pair, and those have no
     * reason to queue behind each other.
     *
     * Only pairs that are actually stale are requested; the rest are already
     * good and cost nothing here. Each response is parsed and stored as it
     * arrives, so a slow provider delays only its own pair.
     *
     * Deliberately NOT a full replacement for refresh(): this uses the first
     * healthy endpoint per symbol and does not walk the failover list. A pair
     * that fails here is simply left stale, and the ordinary serial path picks
     * it up with its full retry behaviour the moment something asks for it.
     * Parallel is an optimisation, so it is allowed to give up; correctness
     * stays with the path that was always there.
     *
     * @param list<array{0:string,1:string}> $pairs [symbol, tf] pairs
     * @return array{requested:int,ok:int,failed:int,skipped:int,seconds:float}
     */
    public function prefetch(array $pairs, ?int $concurrency = null): array
    {
        $out = ['requested' => 0, 'ok' => 0, 'failed' => 0, 'skipped' => 0, 'seconds' => 0.0];
        if (!$pairs || !function_exists('curl_multi_init')) {
            return $out;
        }
        $conc = $concurrency ?? (int)sma_setting('fetch_concurrency', '8');
        $conc = max(1, min(50, $conc));
        $started = microtime(true);

        // Which of these actually need the network. One query rather than one
        // per pair: on a 200-pair rotation that is 199 round trips to SQLite
        // saved before a single one to the exchange is opened.
        $fresh = [];
        $stmt = $this->pdo->query('SELECT symbol, tf, fetched_at FROM fetch_log');
        foreach ($stmt as $r) {
            $fresh[$r['symbol'] . '|' . $r['tf']] = (int)$r['fetched_at'];
        }
        $stmt->closeCursor();

        $jobs = [];
        $seen = [];
        foreach ($pairs as [$symbol, $tf]) {
            $key = $symbol . '|' . $tf;
            if (isset($seen[$key])) {
                continue;               // the same pair asked for twice
            }
            $seen[$key] = true;
            if (isset($fresh[$key]) && (time() - $fresh[$key]) <= $this->ttlFor($tf)) {
                $out['skipped']++;
                continue;
            }
            try {
                $host = $this->endpointsFor($symbol)[0] ?? null;
                if ($host === null) {
                    $out['failed']++;
                    continue;
                }
                $req = $this->klinesRequest($host, $symbol, $tf);
            } catch (\Throwable $e) {
                // A provider that cannot serve this timeframe at all. Counted
                // as failed and left to the serial path to report properly.
                $out['failed']++;
                continue;
            }
            $jobs[] = ['symbol' => $symbol, 'tf' => $tf] + $req;
        }
        $out['requested'] = count($jobs);
        if (!$jobs) {
            $out['seconds'] = round(microtime(true) - $started, 3);
            return $out;
        }

        $mh = curl_multi_init();
        $live = [];       // curl handle id => job
        $queue = $jobs;
        $add = function () use (&$queue, &$live, $mh, $conc): void {
            while (count($live) < $conc && $queue) {
                $job = array_shift($queue);
                $ch = curl_init($job['url']);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT        => 20,
                    CURLOPT_CONNECTTIMEOUT => 8,
                    CURLOPT_USERAGENT      => 'SignalMasterAi/1.0',
                    CURLOPT_ENCODING       => '',
                ]);
                curl_multi_add_handle($mh, $ch);
                $live[(int)$ch] = $job;
            }
        };
        $add();

        do {
            $status = curl_multi_exec($mh, $running);
            if ($running) {
                // Blocks until something happens on one of the sockets rather
                // than spinning the CPU while every request is in flight.
                curl_multi_select($mh, 1.0);
            }
            while ($done = curl_multi_info_read($mh)) {
                $ch = $done['handle'];
                $job = $live[(int)$ch] ?? null;
                unset($live[(int)$ch]);
                $body = curl_multi_getcontent($ch);
                $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
                curl_multi_remove_handle($mh, $ch);
                curl_close($ch);
                if ($job === null) {
                    continue;
                }
                try {
                    if ($done['result'] !== CURLE_OK || $code !== 200 || $body === false) {
                        throw new \RuntimeException($code !== 200 && $code !== 0
                            ? "HTTP $code" : curl_strerror($done['result']));
                    }
                    $payload = $job['raw'] ? (string)$body : json_decode((string)$body, true);
                    if (!$job['raw'] && !is_array($payload)) {
                        throw new \RuntimeException('invalid JSON');
                    }
                    $rows = $this->parseKlines($job['provider'], $payload, $job['symbol'], $job['tf']);
                    $this->storeRows($job['symbol'], $job['tf'], $rows, true);
                    $out['ok']++;
                } catch (\Throwable $e) {
                    // Left stale on purpose - see the note on this method.
                    $out['failed']++;
                }
                $add();
            }
        } while ($running > 0 || $live || $queue);

        curl_multi_close($mh);
        $out['seconds'] = round(microtime(true) - $started, 3);
        return $out;
    }

    private function providerKlines(string $host, string $symbol, string $tf): array
    {
        $req = $this->klinesRequest($host, $symbol, $tf);
        $body = $req['raw'] ? $this->httpRaw($req['url']) : $this->http($req['url']);
        return $this->parseKlines($req['provider'], $body, $symbol, $tf);
    }

    /**
     * Where this pair's candles live, without fetching them.
     *
     * Split out from the parsing so that one request can be issued by
     * curl_exec for a single pair, or by curl_multi alongside fifty others,
     * with exactly one copy of the per-provider URL rules either way. A second
     * copy would be a second thing to keep in step, and the first symptom of
     * it drifting would be a provider that silently returns the wrong bars.
     *
     * Throws when the provider cannot serve this timeframe at all, at
     * request-building time - before any network work is queued for it.
     *
     * @return array{url:string, raw:bool, provider:string}
     */
    private function klinesRequest(string $host, string $symbol, string $tf): array
    {
        // A namespaced symbol names its own provider. Detecting from the
        // hostname alone breaks the moment an operator points the endpoint at
        // a mirror, a proxy or a local cache - the URL stops containing the
        // vendor's name while the data shape is unchanged.
        $provider = self::providerForSymbol($symbol) ?? self::provider($host);
        [$base, $quote] = self::splitPair($symbol);

        switch ($provider) {
            case 'okx': {
                $bar = ['1m' => '1m', '3m' => '3m', '5m' => '5m', '15m' => '15m', '30m' => '30m',
                        '1h' => '1H', '2h' => '2H', '4h' => '4H', '6h' => '6H', '12h' => '12H',
                        '1d' => '1D', '3d' => '3D', '1w' => '1W', '1mo' => '1M'][$tf] ?? '1H';
                $url = $host . '/api/v5/market/candles?' . http_build_query([
                    'instId' => "$base-$quote", 'bar' => $bar, 'limit' => min(300, $this->candleLimit),
                ]);
                break;
            }
            case 'bybit': {
                $iv = ['1m' => '1', '3m' => '3', '5m' => '5', '15m' => '15', '30m' => '30',
                       '1h' => '60', '2h' => '120', '4h' => '240', '6h' => '360', '12h' => '720',
                       '1d' => 'D', '3d' => '3D', '1w' => 'W', '1mo' => 'M'][$tf] ?? '60';
                $url = $host . '/v5/market/kline?' . http_build_query([
                    'category' => 'spot', 'symbol' => $symbol, 'interval' => $iv,
                    'limit' => min(1000, $this->candleLimit),
                ]);
                break;
            }
            case 'kucoin': {
                $type = ['1m' => '1min', '3m' => '3min', '5m' => '5min', '15m' => '15min', '30m' => '30min',
                         '1h' => '1hour', '2h' => '2hour', '4h' => '4hour', '6h' => '6hour',
                         '8h' => '8hour', '12h' => '12hour', '1d' => '1day', '3d' => '3day',
                         '1w' => '1week', '1mo' => '1month'][$tf] ?? '1hour';
                $secs = self::TF_SECONDS[$tf] ?? 3600;
                $url = $host . '/api/v1/market/candles?' . http_build_query([
                    'type' => $type, 'symbol' => "$base-$quote",
                    'startAt' => time() - $secs * $this->candleLimit, 'endAt' => time(),
                ]);
                break;
            }
            case 'stooq': {
                // Stooq serves free daily/hourly CSV for equities, FX, indices
                // and commodities with no key. Symbols are namespaced as
                // "stooq:aapl.us" so they cannot collide with a crypto pair.
                $ticker = strtolower(explode(':', $symbol, 2)[1] ?? $symbol);
                $interval = in_array($tf, ['1d', '1w', '1mo'], true)
                    ? ['1d' => 'd', '1w' => 'w', '1mo' => 'm'][$tf] : 'd';
                return ['url' => $host . '/q/d/l/?' . http_build_query(['s' => $ticker, 'i' => $interval]),
                        'raw' => true, 'provider' => $provider];
            }
            case 'twelvedata': {
                // Optional keyed provider for intraday non-crypto data, which
                // no free no-key source covers reliably.
                // Twelve Data has no 3-minute series. Falling back to another
                // interval would hand back bars that are labelled 3m and are
                // not - refuse instead, so the caller sees why.
                $ivMap = ['1m' => '1min', '5m' => '5min', '15m' => '15min', '30m' => '30min',
                          '1h' => '1h', '4h' => '4h', '1d' => '1day', '1w' => '1week', '1mo' => '1month'];
                if (!isset($ivMap[$tf])) {
                    throw new \RuntimeException('TwelveData has no ' . $tf . ' interval');
                }
                $url = $host . '/time_series?' . http_build_query([
                    'symbol' => explode(':', $symbol, 2)[1] ?? $symbol,
                    'interval' => $ivMap[$tf],
                    'outputsize' => min(5000, $this->candleLimit),
                    'apikey' => Database::setting('twelvedata_key'),
                ]);
                break;
            }
            default: {   // binance-compatible
                $url = $host . '/api/v3/klines?' . http_build_query([
                    'symbol' => $symbol, 'interval' => $tf === '1mo' ? '1M' : $tf,
                    'limit' => $this->candleLimit,
                ]);
            }
        }
        return ['url' => $url, 'raw' => false, 'provider' => $provider];
    }

    /**
     * Normalise one provider's response into [openTimeMs, o, h, l, c, v] rows,
     * oldest first. $body is the decoded JSON, or the raw string for the CSV
     * providers.
     *
     * @return list<array{0:int,1:float,2:float,3:float,4:float,5:float}>
     */
    private function parseKlines(string $provider, $body, string $symbol, string $tf): array
    {
        $rows = [];
        switch ($provider) {
            case 'okx':
                if (($body['code'] ?? '') !== '0' || !is_array($body['data'] ?? null)) {
                    throw new \RuntimeException('OKX: ' . ($body['msg'] ?? 'bad response'));
                }
                foreach (array_reverse($body['data']) as $r) {   // newest first -> reverse
                    $rows[] = [(int)$r[0], (float)$r[1], (float)$r[2], (float)$r[3], (float)$r[4], (float)$r[5]];
                }
                break;
            case 'bybit':
                if ((int)($body['retCode'] ?? -1) !== 0 || !is_array($body['result']['list'] ?? null)) {
                    throw new \RuntimeException('Bybit: ' . ($body['retMsg'] ?? 'bad response'));
                }
                foreach (array_reverse($body['result']['list']) as $r) {
                    $rows[] = [(int)$r[0], (float)$r[1], (float)$r[2], (float)$r[3], (float)$r[4], (float)$r[5]];
                }
                break;
            case 'kucoin':
                if (($body['code'] ?? '') !== '200000' || !is_array($body['data'] ?? null)) {
                    throw new \RuntimeException('KuCoin: ' . ($body['msg'] ?? 'bad response'));
                }
                // KuCoin row: [ts(sec), open, close, high, low, volume, turnover], newest first
                foreach (array_reverse($body['data']) as $r) {
                    $rows[] = [((int)$r[0]) * 1000, (float)$r[1], (float)$r[3], (float)$r[4], (float)$r[2], (float)$r[5]];
                }
                break;
            case 'stooq':
                foreach (preg_split('/\R/', (string)$body) ?: [] as $n => $line) {
                    if ($n === 0 || trim($line) === '') {
                        continue;   // header
                    }
                    $col = explode(',', $line);
                    if (count($col) < 5) {
                        continue;
                    }
                    $ts = strtotime($col[0] . ' 00:00:00 UTC');
                    if ($ts === false) {
                        continue;
                    }
                    $rows[] = [$ts * 1000, (float)$col[1], (float)$col[2],
                               (float)$col[3], (float)$col[4], (float)($col[5] ?? 0)];
                }
                break;
            case 'twelvedata':
                if (($body['status'] ?? '') === 'error') {
                    throw new \RuntimeException('TwelveData: ' . ($body['message'] ?? 'error'));
                }
                foreach (array_reverse((array)($body['values'] ?? [])) as $v) {
                    $ts = strtotime((string)($v['datetime'] ?? ''));
                    if ($ts === false) {
                        continue;
                    }
                    $rows[] = [$ts * 1000, (float)$v['open'], (float)$v['high'],
                               (float)$v['low'], (float)$v['close'], (float)($v['volume'] ?? 0)];
                }
                break;
            default:   // binance-compatible
                foreach ((array)$body as $r) {
                    if (is_array($r) && count($r) >= 6) {
                        $rows[] = [(int)$r[0], (float)$r[1], (float)$r[2], (float)$r[3], (float)$r[4], (float)$r[5]];
                    }
                }
        }
        if (!$rows) {
            throw new \RuntimeException(ucfirst($provider) . ' returned no candles');
        }
        return $rows;
    }

    /**
     * Current perpetual-futures funding rate for a USDT pair (decimal per 8h,
     * e.g. 0.0001 = 0.01%). Free Binance futures public endpoint, cached for
     * 15 minutes; null when unavailable (spot-only coin, geo-block...).
     */
    public function fundingRate(string $symbol): ?float
    {
        $v = Cache::remember('fund:' . $symbol, 900, function () use ($symbol): ?float {
            $d = $this->futuresGet('/fapi/v1/premiumIndex?symbol=' . urlencode($symbol));
            return isset($d['lastFundingRate']) && is_numeric($d['lastFundingRate'])
                ? (float)$d['lastFundingRate'] : null;
        }, 900);
        return $v === null ? null : (float)$v;
    }

    /** Live last price from the first endpoint that answers. */
    public function lastPrice(string $symbol): float
    {
        // Non-crypto providers have no cheap ticker endpoint - the newest
        // stored close is the honest answer rather than an extra round trip.
        if (!self::isCrypto($symbol)) {
            $stmt = $this->pdo->prepare(
                'SELECT close FROM candles WHERE symbol = ? ORDER BY open_time DESC LIMIT 1'
            );
            $stmt->execute([$symbol]);
            $p = (float)($stmt->fetchColumn() ?: 0);
            if ($p > 0) {
                return $p;
            }
            throw new \RuntimeException('No stored price for ' . $symbol);
        }
        [$base, $quote] = self::splitPair($symbol);
        $lastError = 'no hosts';
        foreach (self::endpoints($this->config) as $host) {
            try {
                switch (self::provider($host)) {
                    case 'okx':
                        $d = $this->http($host . '/api/v5/market/ticker?instId=' . urlencode("$base-$quote"));
                        $p = (float)($d['data'][0]['last'] ?? 0);
                        break;
                    case 'bybit':
                        $d = $this->http($host . '/v5/market/tickers?category=spot&symbol=' . urlencode($symbol));
                        $p = (float)($d['result']['list'][0]['lastPrice'] ?? 0);
                        break;
                    case 'kucoin':
                        $d = $this->http($host . '/api/v1/market/orderbook/level1?symbol=' . urlencode("$base-$quote"));
                        $p = (float)($d['data']['price'] ?? 0);
                        break;
                    default:
                        $d = $this->http($host . '/api/v3/ticker/price?symbol=' . urlencode($symbol));
                        $p = (float)($d['price'] ?? 0);
                }
                if ($p > 0) {
                    return $p;
                }
                $lastError = "$host: zero price";
            } catch (\Throwable $e) {
                $lastError = $host . ': ' . $e->getMessage();
            }
        }
        ErrorLog::record(ErrorLog::MARKET, 'Live price fetch failed - ' . $lastError, $symbol);
        throw new \RuntimeException("Ticker unavailable: $lastError");
    }

    /**
     * Top-of-book bid/ask spread as a percentage of the mid price.
     *
     * Spread is a real cost that never showed up anywhere in the engine: a
     * setup risking 0.4% on a pair quoting a 0.15% spread is a different trade
     * from the same setup on a pair quoting 0.01%, and until now both were
     * scored and reported identically. Recorded at signal time so the learner
     * can find out whether wide-spread setups actually pay.
     *
     * Cached for 30s (a spread does not move meaningfully faster than that,
     * and signal generation must not add a round trip per symbol per view).
     * Null when unavailable - non-crypto symbols have no free book endpoint,
     * and a missing spread must not be mistaken for a zero one.
     */
    public function spreadPct(string $symbol): ?float
    {
        if (!self::isCrypto($symbol)) {
            return null;
        }
        [$base, $quote] = self::splitPair($symbol);
        $v = Cache::remember('spread:' . $symbol, 30, function () use ($symbol, $base, $quote): ?float {
            foreach (self::endpoints($this->config) as $host) {
                try {
                    switch (self::provider($host)) {
                        case 'okx':
                            $d = $this->http($host . '/api/v5/market/ticker?instId=' . urlencode("$base-$quote"));
                            $bid = (float)($d['data'][0]['bidPx'] ?? 0);
                            $ask = (float)($d['data'][0]['askPx'] ?? 0);
                            break;
                        case 'bybit':
                            $d = $this->http($host . '/v5/market/tickers?category=spot&symbol=' . urlencode($symbol));
                            $bid = (float)($d['result']['list'][0]['bid1Price'] ?? 0);
                            $ask = (float)($d['result']['list'][0]['ask1Price'] ?? 0);
                            break;
                        case 'kucoin':
                            $d = $this->http($host . '/api/v1/market/orderbook/level1?symbol=' . urlencode("$base-$quote"));
                            $bid = (float)($d['data']['bestBid'] ?? 0);
                            $ask = (float)($d['data']['bestAsk'] ?? 0);
                            break;
                        default:
                            $d = $this->http($host . '/api/v3/ticker/bookTicker?symbol=' . urlencode($symbol));
                            $bid = (float)($d['bidPrice'] ?? 0);
                            $ask = (float)($d['askPrice'] ?? 0);
                    }
                    if ($bid > 0 && $ask > 0 && $ask >= $bid) {
                        return round(($ask - $bid) / (($ask + $bid) / 2) * 100, 5);
                    }
                } catch (\Throwable $e) {
                    // Try the next endpoint; spread is optional telemetry and
                    // must never break signal generation.
                }
            }
            return null;
        }, 30);
        return $v === null ? null : (float)$v;
    }

    private function fetchFrom(string $host, string $symbol, string $tf): void
    {
        $data = $this->providerKlines($host, $symbol, $tf);
        $this->storeRows($symbol, $tf, $data, true);
    }

    /**
     * Upsert normalised kline rows. $stampFetch marks the series fresh; a
     * gap backfill leaves the freshness stamp alone so it does not pretend to
     * be a full refresh of the head of the series.
     */
    private function storeRows(string $symbol, string $tf, array $data, bool $stampFetch): int
    {
        $this->pdo->beginTransaction();
        try {
            $isSqlite = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
            $sql = $isSqlite
                ? 'INSERT INTO candles (symbol, tf, open_time, open, high, low, close, volume)
                   VALUES (?,?,?,?,?,?,?,?)
                   ON CONFLICT(symbol, tf, open_time) DO UPDATE SET
                     open=excluded.open, high=excluded.high, low=excluded.low,
                     close=excluded.close, volume=excluded.volume'
                : 'INSERT INTO candles (symbol, tf, open_time, open, high, low, close, volume)
                   VALUES (?,?,?,?,?,?,?,?)
                   ON DUPLICATE KEY UPDATE
                     open=VALUES(open), high=VALUES(high), low=VALUES(low),
                     close=VALUES(close), volume=VALUES(volume)';
            $stmt = $this->pdo->prepare($sql);
            $count = 0;
            foreach ($data as $row) {
                if (!is_array($row) || count($row) < 6) {
                    continue;
                }
                $stmt->execute([
                    $symbol, $tf, (int)$row[0],
                    (float)$row[1], (float)$row[2], (float)$row[3], (float)$row[4], (float)$row[5],
                ]);
                $count++;
            }

            if ($stampFetch) {
                $upd = $this->pdo->prepare('UPDATE fetch_log SET fetched_at = ?, candles = ? WHERE symbol = ? AND tf = ?');
                $upd->execute([time(), $count, $symbol, $tf]);
                if ($upd->rowCount() === 0) {
                    $this->pdo->prepare('INSERT INTO fetch_log (symbol, tf, fetched_at, candles) VALUES (?,?,?,?)')
                        ->execute([$symbol, $tf, time(), $count]);
                }
            }
            $this->pdo->commit();
            return $count;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
