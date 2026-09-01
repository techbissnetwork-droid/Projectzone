<?php
declare(strict_types=1);

namespace SignalMasterAi;

/**
 * What the Bitcoin chain itself is doing, from feeds that cost nothing.
 *
 * WHAT THIS CAN AND CANNOT TELL YOU, SAID FIRST.
 *
 * "Whale movement" and "exchange movement" as sold by the data industry mean
 * one specific thing: coins moving from a known large holder's wallet INTO a
 * known exchange deposit address (a seller getting ready), or back OUT of one
 * (a buyer taking custody). That direction is the entire value of the signal,
 * and it depends on knowing WHOSE address is WHOSE.
 *
 * Address labelling is the product Glassnode, CryptoQuant, Nansen and Arkham
 * sell. There is no free, keyless source for it, and inventing one from public
 * data means guessing - a guess that would be published to paying members as
 * though it were an observation.
 *
 * So this class does not claim a direction, and NOTHING HERE CASTS A
 * DIRECTIONAL VOTE. What free chain data genuinely supports is a reading of
 * how much value is settling on-chain right now against its own recent normal.
 * A large jump means size is moving. It does not say whose, or which way, and
 * a rule that pretended otherwise would be the most confident wrong thing on
 * the site.
 *
 * What it is used for instead: a caution input, of the same family as the
 * volatility circuit breaker. Unusual settlement volume precedes movement
 * often enough to be worth raising the bar for, and raising the bar is a claim
 * this data can actually support.
 *
 * BITCOIN ONLY, AND THAT IS NOT A LIMITATION TO PAPER OVER. There is no free
 * multi-chain equivalent, and the engine already treats BTC as the tide that
 * moves everything (see the btc_regime rule), so a market-wide reading taken
 * from BTC is coherent rather than a stand-in for the coin being traded.
 *
 * IT CANNOT BE BACKTESTED. These feeds serve current values, not a history the
 * replay can walk. Its worth has to be judged from the live record - which is
 * why it ships off, and why the shadow book is the place to look.
 */
class OnChain
{
    /** Free and keyless. Both are read-only public endpoints. */
    public const SOURCES = [
        'volume' => 'https://api.blockchain.info/charts/estimated-transaction-volume-usd'
                    . '?timespan=30days&format=json',
        'mempool' => 'https://mempool.space/api/mempool',
    ];

    /** How long a reading stays fresh. These move in hours, not seconds. */
    private const TTL = 1800;

    /**
     * The current reading, or null when nothing could be fetched.
     *
     * @return array{
     *   usd:float, median:float, ratio:float, mempool:int, at:int, surge:bool
     * }|null
     */
    public static function reading(): ?array
    {
        if (Database::setting('onchain_enabled', '0') !== '1') {
            return null;
        }
        $raw = Cache::remember('onchain:btc', self::TTL, static function (): ?array {
            $vals = self::series();
            if ($vals === null || count($vals) < 8) {
                return null;
            }
            $today = (float)end($vals);
            // Median of the prior days rather than a mean: one enormous day
            // drags an average up and then makes itself look normal.
            $prior = array_slice($vals, 0, count($vals) - 1);
            sort($prior);
            $n = count($prior);
            $median = $n % 2 === 1
                ? (float)$prior[intdiv($n, 2)]
                : ((float)$prior[$n / 2 - 1] + (float)$prior[$n / 2]) / 2;
            if ($median <= 0 || $today <= 0) {
                return null;
            }
            return [
                'usd' => round($today, 2),
                'median' => round($median, 2),
                'ratio' => round($today / $median, 3),
                'mempool' => self::mempoolCount(),
                'at' => time(),
            ];
        }, self::TTL);

        // THE THRESHOLD IS APPLIED OUTSIDE THE CACHE, ON PURPOSE.
        //
        // Computed inside it, a change to onchain_surge_ratio did nothing for
        // half an hour: the operator moves the dial, nothing happens, and
        // there is no way to tell a setting that is wrong from one that has
        // not landed yet. This codebase has already been bitten by a cached
        // report outliving the shape of the thing it described.
        //
        // What is expensive is the fetch. Comparing two numbers is not, so it
        // happens every call against the live setting.
        if ($raw === null) {
            return null;
        }
        $trigger = max(1.1, (float)Database::setting('onchain_surge_ratio', '1.8'));
        $raw['surge'] = (float)$raw['ratio'] >= $trigger;
        return $raw;
    }

    /**
     * Daily settled value in USD for the last 30 days.
     *
     * @return array<int,float>|null
     */
    private static function series(): ?array
    {
        $raw = self::fetch((string)(Database::setting('onchain_volume_url', '') ?: self::SOURCES['volume']));
        if ($raw === null) {
            return null;
        }
        $d = json_decode($raw, true);
        if (!is_array($d) || !isset($d['values']) || !is_array($d['values'])) {
            return null;
        }
        $out = [];
        foreach ($d['values'] as $row) {
            if (isset($row['y']) && is_numeric($row['y']) && (float)$row['y'] > 0) {
                $out[] = (float)$row['y'];
            }
        }
        return $out ?: null;
    }

    /** Unconfirmed transactions waiting. Congestion, not direction. */
    private static function mempoolCount(): int
    {
        $raw = self::fetch((string)(Database::setting('onchain_mempool_url', '') ?: self::SOURCES['mempool']));
        if ($raw === null) {
            return 0;
        }
        $d = json_decode($raw, true);
        return is_array($d) ? (int)($d['count'] ?? 0) : 0;
    }

    /**
     * One HTTP read, failing soft.
     *
     * A chain feed that is slow or down must never hold up a cron run that has
     * pairs to scan and alerts to send - the whole point of this being a
     * caution input rather than a vote is that its absence changes nothing.
     */
    private static function fetch(string $url): ?string
    {
        if (!preg_match('#^https?://#', $url)) {
            return null;
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_USERAGENT => 'SignalMasterAi/1.0',
        ]);
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return ($code === 200 && is_string($body) && $body !== '') ? $body : null;
    }

    /**
     * Health of each source, for the panel. Same shape as the futures check,
     * because "is this feed alive" is one question and deserves one answer.
     *
     * @return array<int,array{name:string,url:string,ok:bool,note:string}>
     */
    public static function health(): array
    {
        $urls = [
            'Settled value (blockchain.info)' =>
                (string)(Database::setting('onchain_volume_url', '') ?: self::SOURCES['volume']),
            'Mempool (mempool.space)' =>
                (string)(Database::setting('onchain_mempool_url', '') ?: self::SOURCES['mempool']),
        ];
        $out = [];
        foreach ($urls as $name => $url) {
            $body = self::fetch($url);
            $out[] = [
                'name' => $name,
                'url' => $url,
                'ok' => $body !== null,
                'note' => $body === null ? 'No usable reply' : 'Answering',
            ];
        }
        return $out;
    }

    /**
     * A sentence for a human, or null when there is nothing to say.
     *
     * Deliberately describes the observation and stops. "Large value is
     * settling on-chain" is true; "whales are selling" is not something this
     * data knows.
     */
    public static function describe(): ?string
    {
        $r = self::reading();
        if ($r === null) {
            return null;
        }
        return sprintf(
            'Bitcoin settled $%s on-chain in the last day, %.2fx its 30-day median%s.',
            number_format($r['usd'] / 1e9, 2) . 'bn',
            $r['ratio'],
            $r['surge'] ? ' - unusually high, so size is moving, though the chain does not say '
                . 'whose or which way' : ''
        );
    }
}
