<?php
declare(strict_types=1);

namespace SignalMasterAi;

/**
 * Ways to pick a set of coins without naming them one at a time.
 *
 * Importing was all-or-nothing: every pair against a quote asset, four hundred
 * of them, and then an evening spent unticking. What an operator actually
 * wants is "the top fifty by volume" or "the majors" or "the memecoins", and
 * none of those is something an exchange endpoint answers.
 *
 * Ranking comes from the exchange's own 24h volume - see
 * MarketData::rankedSymbols(). Categories are a list kept here, and that is a
 * deliberate choice rather than a shortcut:
 *
 *   - Nothing on a spot exchange publishes a sector. The obvious sources are
 *     third-party APIs that rate-limit, change shape, and stop being free.
 *   - A wrong category is a cosmetic mistake, not a trading one. Nothing in
 *     the engine reads these; they only decide which rows an import creates,
 *     and every one of them is a coin the operator can untick afterwards.
 *   - It works with no key, no quota and no outbound call, which is the same
 *     bargain the rest of this app makes.
 *
 * Base assets only, so the same list works against USDT, USDC, FDUSD or a
 * BTC-quoted book without being written three times.
 */
class CoinGroups
{
    /**
     * key => [label, description, base assets]
     *
     * Deliberately conservative. A coin missing from a category is an operator
     * adding one ticker by hand; a coin wrongly IN one is a pair they did not
     * ask for appearing on their site.
     */
    public const GROUPS = [
        'majors' => ['Majors', 'The two anyone benchmarks against, plus the largest alt L1s.',
            ['BTC', 'ETH', 'BNB', 'SOL', 'XRP', 'ADA', 'AVAX', 'DOT', 'TRX', 'TON', 'LTC', 'BCH']],
        'l1' => ['Layer 1', 'Base-layer chains with their own consensus.',
            ['BTC', 'ETH', 'BNB', 'SOL', 'ADA', 'AVAX', 'DOT', 'ATOM', 'NEAR', 'APT', 'SUI', 'SEI',
             'TIA', 'INJ', 'ALGO', 'EGLD', 'HBAR', 'ICP', 'FTM', 'KAS', 'TRX', 'TON', 'XLM', 'XTZ']],
        'l2' => ['Layer 2 & scaling', 'Rollups and scaling networks settling to another chain.',
            ['MATIC', 'ARB', 'OP', 'IMX', 'STRK', 'MANTA', 'METIS', 'ZK', 'BLAST', 'SKL', 'LRC']],
        'defi' => ['DeFi', 'Exchanges, lending, derivatives and liquid staking.',
            ['UNI', 'AAVE', 'MKR', 'LDO', 'CRV', 'SNX', 'COMP', 'SUSHI', 'CAKE', 'DYDX', '1INCH',
             'BAL', 'YFI', 'RUNE', 'PENDLE', 'ENA', 'ETHFI', 'JUP', 'RAY', 'GMX']],
        'meme' => ['Memecoins', 'Community tokens. Thin books and violent moves - read the '
                 . 'track record for these separately before publishing them.',
            ['DOGE', 'SHIB', 'PEPE', 'WIF', 'BONK', 'FLOKI', 'MEME', 'BOME', 'ORDI', 'TURBO',
             'BRETT', 'MEW', 'POPCAT', 'NEIRO']],
        'ai' => ['AI & compute', 'Machine-learning and decentralised compute projects.',
            ['FET', 'RNDR', 'RENDER', 'TAO', 'AGIX', 'OCEAN', 'AKT', 'ARKM', 'WLD', 'GRT', 'NMR', 'PHB']],
        'gaming' => ['Gaming & metaverse', 'Game economies, virtual worlds and NFT infrastructure.',
            ['AXS', 'SAND', 'MANA', 'GALA', 'ENJ', 'IMX', 'APE', 'PIXEL', 'BIGTIME', 'YGG', 'MAGIC', 'RON']],
        'infra' => ['Infrastructure & oracles', 'Data feeds, storage, bridges and node networks.',
            ['LINK', 'FIL', 'AR', 'STORJ', 'GRT', 'API3', 'BAND', 'QNT', 'AXL', 'CELR', 'POND', 'ANKR']],
        'privacy' => ['Privacy', 'Privacy-preserving chains and protocols.',
            ['XMR', 'ZEC', 'DASH', 'SCRT', 'ROSE', 'KEEP']],
        // A list like this always lags the exchange - new stablecoins list
        // every few months, and one that is not here reaches the preview as a
        // row priced 1.00 with a 0.0% move, which is why the preview names
        // every coin instead of importing straight away. RLUSD arrived in the
        // live top twelve by volume while this was being tested; that is the
        // exact failure this list cannot be relied on to prevent alone.
        'stables_x' => ['Exclude stablecoins', 'Not an import - the assets any other pick skips.',
            ['USDT', 'USDC', 'FDUSD', 'TUSD', 'BUSD', 'DAI', 'USDP', 'EURI', 'AEUR', 'USDE',
             'USD1', 'USDD', 'PYUSD', 'GUSD', 'LUSD', 'FRAX', 'USDS', 'XUSD', 'EURT', 'EURS',
             'RLUSD', 'USDG', 'USDF', 'USDX', 'AUSD', 'USDB', 'USDY', 'EURC', 'USDL', 'USDR']],
    ];

    /** The picks an operator can choose from, minus the internal exclusion set. */
    public static function selectable(): array
    {
        $out = self::GROUPS;
        unset($out['stables_x']);
        return $out;
    }

    /** Base assets in one group, or [] for an unknown key. */
    public static function assets(string $key): array
    {
        return self::GROUPS[$key][2] ?? [];
    }

    /**
     * Is this base asset a stablecoin?
     *
     * Every import skips them whatever else it is doing. A stablecoin against
     * a stablecoin is a flat line, and an engine reading one produces signals
     * about rounding error.
     */
    public static function isStable(string $base): bool
    {
        return in_array(strtoupper($base), self::GROUPS['stables_x'][2], true);
    }
}
