<?php
declare(strict_types=1);

namespace SignalMasterAi;

/**
 * The credentials, and keeping them out of anything that leaves the server.
 *
 * Every API key, webhook secret and password this site holds lives in one
 * table: settings. Three separate questions are asked about those rows, and
 * before this class each had its own answer:
 *
 *   - the settings form asked "should this field be write-only?" with one
 *     regular expression,
 *   - the audit log asked "should this change be recorded as (hidden)?" with a
 *     different list and a different regular expression,
 *   - nothing at all asked "should this be readable in a database dump?".
 *
 * Two predicates that mean the same thing will disagree eventually, and the
 * way you find out is a key printed on a page. They already did: a key ending
 * in `_key` was hidden by both, and the Twelve Data key was rendered in full,
 * in plain text, in a `type="text"` input - because that field was written by
 * hand and never consulted either. So there is now ONE list, and the form, the
 * audit log and the storage layer all read it.
 *
 * ---------------------------------------------------------------- at rest
 *
 * The list also decides what gets encrypted before it touches the database.
 *
 * What that is worth depends entirely on the threat, and it is worth being
 * exact rather than reassuring:
 *
 *   It DOES protect against every way the database leaves the machine without
 *   the filesystem coming with it - the backup you download and email to
 *   yourself, a MySQL server shared with other tenants, stolen database
 *   credentials, a future SQL injection, a restored copy on a laptop. In all
 *   of those the attacker gets rows and no key, and the rows are useless.
 *
 *   It does NOT protect against anyone who can read the data directory. They
 *   have the key file and the database side by side. On shared hosting that is
 *   the same person who could read the plaintext before, so nothing is lost -
 *   but nothing is gained either, and claiming otherwise would be a lie told
 *   to an operator making decisions about real money.
 *
 * The key is read from the SMA_SECRET_KEY environment variable when the host
 * offers one - that is strictly better, because it is not in the directory the
 * database is in - and otherwise from data/secret-key.php, which is generated
 * on first use. A PHP file rather than a text file: if the deny rules are ever
 * missing, a .php fetched over HTTP executes and returns nothing, while a .txt
 * hands over its contents.
 *
 * Failure is always soft. No key, no OpenSSL, an unreadable ciphertext: the
 * value is passed through unchanged and the site keeps working on plaintext,
 * exactly as it did before this class existed. A trading site that cannot take
 * a payment because a key file was lost in an upload is a worse outcome than
 * the one this protects against.
 */
class Secrets
{
    /**
     * Settings whose value is a live credential.
     *
     * ONE list. The settings form makes these write-only, the audit log
     * records them as changed without recording to what, and the storage layer
     * encrypts them. Adding a credential means adding it here, once.
     *
     * Deliberately NOT in this list:
     *
     *   cron_token             - the operator has to paste it into a cron URL,
     *                            so the panel must be able to show it. It
     *                            grants one thing: running the job that runs
     *                            itself anyway.
     *   cryptomus_merchant_uuid- an account identifier, not a credential.
     *                            Cryptomus signs with the API key; the UUID
     *                            alone authorises nothing.
     *   vapid_private_pem      - generated, never typed, and never rendered by
     *                            any form. Encrypting it would break push for
     *                            every install whose key file goes missing, to
     *                            protect a key that only signs push messages
     *                            to this site's own subscribers.
     */
    public const KEYS = [
        'ai_api_key',
        'cryptomus_api_key',
        'gw_btcpay_api_key',
        'gw_btcpay_webhook_secret',
        'gw_coinbase_api_key',
        'gw_coinbase_webhook_secret',
        'gw_coingate_api_key',
        'gw_nowpayments_api_key',
        'gw_nowpayments_ipn_secret',
        'smtp_pass',
        'tg_bot_token',
        'twelvedata_key',
    ];

    /** Marks a stored value as ciphertext. Versioned so v2 can coexist. */
    private const PREFIX = 'enc:v1:';

    private const CIPHER = 'aes-256-gcm';

    /** Is this setting a credential? The single answer to that question. */
    public static function isSecret(string $key): bool
    {
        if (in_array($key, self::KEYS, true)) {
            return true;
        }
        // Per-member webhook signing secrets are created at runtime, one per
        // member, so they cannot be listed above. They are secrets by the same
        // argument as everything that is.
        return str_starts_with($key, 'webhook_secret_');
    }

    /** Has this value already been encrypted? */
    public static function isCiphertext(string $value): bool
    {
        return str_starts_with($value, self::PREFIX);
    }

    /**
     * The 32-byte key, or null when this install has none and cannot make one.
     *
     * Environment first: a host that offers one has put it somewhere the
     * database is not, which is the whole point. Otherwise the generated file,
     * created on first use with the tightest permissions the host allows.
     */
    public static function key(): ?string
    {
        static $key = false;
        if ($key !== false) {
            return $key;
        }
        $env = getenv('SMA_SECRET_KEY');
        if (is_string($env) && strlen($env) >= 32) {
            return $key = substr(hash('sha256', $env, true), 0, 32);
        }
        $path = self::keyFile();
        if ($path === null) {
            return $key = null;
        }
        if (is_file($path)) {
            $hex = @include $path;
            if (is_string($hex) && strlen($hex) === 64 && ctype_xdigit($hex)) {
                return $key = (string)hex2bin($hex);
            }
            // Present but unreadable or corrupt. Do NOT overwrite it: the
            // existing ciphertext was written with whatever it holds, and
            // replacing it turns a recoverable problem into a permanent one.
            return $key = null;
        }
        return $key = self::createKey($path);
    }

    /** Where the generated key lives, or null when there is no data dir. */
    private static function keyFile(): ?string
    {
        return defined('SMA_DATA_DIR') ? SMA_DATA_DIR . '/secret-key.php' : null;
    }

    /** Generate and persist a key. Returns null if it could not be written. */
    private static function createKey(string $path): ?string
    {
        if (!function_exists('openssl_encrypt') || !is_dir(dirname($path))) {
            return null;
        }
        try {
            $raw = random_bytes(32);
        } catch (\Throwable $e) {
            return null;
        }
        $body = "<?php\n"
            . "// SignalMasterAi credential key. Generated once, never edited.\n"
            . "//\n"
            . "// BACK THIS UP WITH YOUR DATABASE. The API keys and passwords in the\n"
            . "// settings table are encrypted with it; a database restored without this\n"
            . "// file keeps working, but every credential has to be entered again.\n"
            . "return '" . bin2hex($raw) . "';\n";
        // Written to a temporary name and moved, so a half-written file is
        // never what the next request reads.
        $tmp = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (@file_put_contents($tmp, $body, LOCK_EX) === false) {
            return null;
        }
        @chmod($tmp, 0600);
        if (!@rename($tmp, $path)) {
            @unlink($tmp);
            return null;
        }
        return $raw;
    }

    /**
     * Encrypt for storage. Returns the input unchanged when it cannot - an
     * empty value, no key, no OpenSSL, or something already encrypted.
     */
    public static function encrypt(string $plain): string
    {
        if ($plain === '' || self::isCiphertext($plain) || !function_exists('openssl_encrypt')) {
            return $plain;
        }
        $key = self::key();
        if ($key === null) {
            return $plain;
        }
        try {
            $nonce = random_bytes(12);
        } catch (\Throwable $e) {
            return $plain;
        }
        $tag = '';
        $ct = openssl_encrypt($plain, self::CIPHER, $key, OPENSSL_RAW_DATA, $nonce, $tag);
        if ($ct === false || $tag === '') {
            return $plain;
        }
        return self::PREFIX . base64_encode($nonce . $tag . $ct);
    }

    /**
     * Decrypt a stored value.
     *
     * Anything without the marker is returned as it is - that is every value
     * written before this existed, and an install that upgrades must not lose
     * its gateway keys on the way. A marked value that will not decrypt
     * returns '' rather than gibberish: an empty API key is a gateway that
     * says "not configured", which is recoverable by typing it in again; a
     * corrupt one is a request signed with rubbish.
     */
    public static function decrypt(string $stored): string
    {
        if (!self::isCiphertext($stored)) {
            return $stored;
        }
        if (!function_exists('openssl_decrypt')) {
            return '';
        }
        $key = self::key();
        if ($key === null) {
            return '';
        }
        $raw = base64_decode(substr($stored, strlen(self::PREFIX)), true);
        if ($raw === false || strlen($raw) < 29) {
            return '';
        }
        $plain = openssl_decrypt(
            substr($raw, 28), self::CIPHER, $key, OPENSSL_RAW_DATA,
            substr($raw, 0, 12), substr($raw, 12, 16)
        );
        return $plain === false ? '' : $plain;
    }

    /**
     * Encrypt any credential still sitting in the database as plaintext.
     *
     * Runs from the migration and from maintenance. Writes through raw SQL
     * rather than setSetting(), because setSetting() encrypts on the way in
     * and reading back through the cache would compare a decrypted value with
     * a stored one - the sort of loop that quietly double-encrypts.
     *
     * @return array{encrypted:int,skipped:int,key:bool}
     */
    public static function migrate(): array
    {
        $out = ['encrypted' => 0, 'skipped' => 0, 'key' => self::key() !== null];
        if (!$out['key']) {
            return $out;
        }
        $pdo = Database::pdo();
        $upd = $pdo->prepare('UPDATE settings SET svalue = ? WHERE skey = ? AND svalue = ?');
        foreach ($pdo->query('SELECT skey, svalue FROM settings') as $row) {
            $k = (string)$row['skey'];
            $v = (string)$row['svalue'];
            if ($v === '' || !self::isSecret($k)) {
                continue;
            }
            if (self::isCiphertext($v)) {
                $out['skipped']++;
                continue;
            }
            $enc = self::encrypt($v);
            if ($enc === $v) {
                continue;                       // encryption unavailable
            }
            // Conditional on the old value, so a concurrent write during the
            // migration is left alone rather than overwritten with a stale
            // encryption of a value that is no longer current.
            $upd->execute([$enc, $k, $v]);
            if ($upd->rowCount() > 0) {
                $out['encrypted']++;
            }
        }
        Database::flushSettingCache();
        return $out;
    }

    /**
     * What the admin panel needs to say about all this, in one call.
     *
     * `unreadable` is the one that matters and the reason this counts rather
     * than trusting the marker: ciphertext written with a key that is no
     * longer there still LOOKS encrypted. Without this the panel would report
     * "12 of 12 encrypted" about twelve credentials the site can no longer
     * use, and the first anyone would know is a payment that does not open.
     *
     * @return array{available:bool,source:string,total:int,encrypted:int,plain:int,unreadable:int}
     */
    public static function status(): array
    {
        $env = getenv('SMA_SECRET_KEY');
        $source = (is_string($env) && strlen($env) >= 32)
            ? 'environment'
            : (self::key() !== null ? 'data/secret-key.php' : 'none');
        $total = $enc = $plain = $bad = 0;
        foreach (Database::pdo()->query('SELECT skey, svalue FROM settings') as $row) {
            $k = (string)$row['skey'];
            $v = (string)$row['svalue'];
            if ($v === '' || !self::isSecret($k)) {
                continue;
            }
            $total++;
            if (!self::isCiphertext($v)) {
                $plain++;
                continue;
            }
            $enc++;
            if (self::decrypt($v) === '') {
                $bad++;
            }
        }
        return [
            'available'  => self::key() !== null,
            'source'     => $source,
            'total'      => $total,
            'encrypted'  => $enc,
            'plain'      => $plain,
            'unreadable' => $bad,
        ];
    }
}
