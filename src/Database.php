<?php
declare(strict_types=1);

namespace SignalMasterAi;

use PDO;

/**
 * PDO wrapper + schema bootstrap + first-run seed data.
 * The "knowledge base" (technical-analysis rules and their weights) lives in
 * the ta_knowledge table so the admin can tune the engine without code changes.
 */
class Database
{
    private static ?PDO $pdo = null;
    private static array $config = [];

    /**
     * In-memory settings cache.
     *
     * Settings are read constantly - SignalEngine::analyse() alone touches
     * twenty of them, and the full-market scan multiplies that by every
     * coin x timeframe. One SELECT per read was the single hottest query in
     * the app; the whole table is a few kilobytes, so it is loaded once per
     * request and kept in sync by setSetting().
     */
    private static array $settings = [];
    private static bool $settingsLoaded = false;

    public static function boot(array $config): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }
        self::$config = $config;

        if ($config['db']['driver'] === 'mysql') {
            $m = $config['db']['mysql'];
            $dsn = "mysql:host={$m['host']};port={$m['port']};dbname={$m['database']};charset={$m['charset']}";
            // FOUND_ROWS: make UPDATE rowCount() report matched rows (like
            // SQLite) instead of 0 when values are unchanged - the
            // update-or-insert patterns rely on that.
            $pdo = new PDO($dsn, $m['username'], $m['password'], [
                PDO::MYSQL_ATTR_FOUND_ROWS => true,
            ]);
        } else {
            if (!is_dir(SMA_DATA_DIR)) {
                mkdir(SMA_DATA_DIR, 0775, true);
            }
            $pdo = new PDO('sqlite:' . $config['db']['sqlite']);
            $pdo->exec('PRAGMA journal_mode = WAL');
            $pdo->exec('PRAGMA foreign_keys = ON');
            // Wait for a writer instead of failing at it.
            //
            // WAL lets readers and one writer work at once, but a SECOND
            // writer gets SQLITE_BUSY immediately - and with no timeout that
            // surfaces as "database is locked" rather than as the half-second
            // wait it actually is. That is fine while one cron job writes;
            // the moment an operator runs several to scan faster, or a visitor
            // lands mid-run, it is a thrown query. Ten seconds is far longer
            // than any write here takes and far shorter than any page budget.
            $pdo->exec('PRAGMA busy_timeout = 10000');
            // AND THE ONE THING THIS TIMEOUT CANNOT SAVE YOU FROM - read it
            // before adding a query anywhere in this application.
            //
            // A SELECT is not finished when you have read the row you wanted.
            // "SELECT ... WHERE id = ?" fetched once still has a step to take
            // before SQLite knows there are no more rows, so the statement
            // stays active and holds a READ TRANSACTION open for as long as
            // the PDOStatement variable lives. Any write attempted on the same
            // connection while that read is open is a read-to-write upgrade,
            // and if any other connection has committed since the read began,
            // SQLite answers SQLITE_BUSY and DOES NOT CALL THE BUSY HANDLER -
            // deliberately, because waiting there can deadlock. The timeout
            // above is simply never consulted.
            //
            // It looks impossible when it happens. The query that throws is an
            // ordinary UPDATE with no transaction anywhere near it, the
            // timeout is set correctly, and it only fails when two people use
            // the site at once. Measured here: eight concurrent chart polls
            // returned twenty-six "database is locked" errors out of forty,
            // each failing in 0.15-0.42 seconds - nowhere near ten seconds,
            // which is what proves the handler was never asked.
            //
            // So: after fetching a single row or column, call closeCursor().
            // Six places on the analysis path alone were holding reads open -
            // signal_state, symbols twice, fetch_log, settings and the cache -
            // and any one of them was enough to break every write after it.
            // fetchAll() is safe; it reads to the end. A fetch() that returns
            // false is safe; it reached the end. Everything else needs the
            // cursor closed.
        }

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        self::$pdo = $pdo;

        self::migrate($pdo);
        self::seed($pdo, $config);

        return $pdo;
    }

    public static function pdo(): PDO
    {
        if (!self::$pdo) {
            throw new \RuntimeException('Database::boot() must be called first');
        }
        return self::$pdo;
    }

    /**
     * CREATE INDEX helper: MySQL 8 lacks "IF NOT EXISTS" for indexes, so a
     * duplicate-index error on re-boot is expected and ignored.
     */
    private static function index(PDO $pdo, string $sql): void
    {
        try {
            $pdo->exec($sql);
        } catch (\Throwable $e) {
            try {
                $pdo->exec(str_replace('IF NOT EXISTS ', '', $sql));
            } catch (\Throwable $e2) {
                // index already exists - fine
            }
        }
    }

    /**
     * Bring the schema up to date - ONCE PER DEPLOY, not once per request.
     *
     * This ran in full on every single request: ninety-one CREATE TABLE IF NOT
     * EXISTS / CREATE INDEX IF NOT EXISTS / ALTER TABLE probe statements before
     * a page could begin. Harmless-looking, because each one is a no-op when
     * the object already exists, and it was two real faults at once.
     *
     * The first is speed: ninety-one statements of schema work on every page
     * view, every API poll and every cron run, on a shared host.
     *
     * The second is why this was found. DDL takes the write lock and bumps
     * SQLite's schema cookie. Under WAL a second connection colliding with
     * that gets SQLITE_BUSY, and for a schema conflict the busy handler is NOT
     * consulted - so busy_timeout, set correctly to ten seconds right where
     * the connection is opened, never applied. Eight concurrent requests to
     * api.php?action=signal returned five "database is locked" 500s, which on
     * the chart page is a chart that stops updating for no visible reason.
     * The failing statement was an ordinary UPDATE with no transaction around
     * it, which is what made it look impossible.
     *
     * Guarded on the file's own mtime and size rather than a hand-maintained
     * version number, because a hand-maintained number is a number somebody
     * eventually forgets to change - and then their schema change silently
     * never runs. Re-uploading this file re-runs the migration once, which is
     * exactly the deployment model this application has.
     */
    private static function schemaStamp(): string
    {
        $f = __FILE__;
        return (string)(@filemtime($f) ?: 0) . ':' . (string)(@filesize($f) ?: 0);
    }

    private static function migrate(PDO $pdo): void
    {
        $stamp = self::schemaStamp();
        try {
            $stmt = $pdo->query("SELECT svalue FROM settings WHERE skey = 'schema_stamp'");
            if ($stmt !== false && (string)$stmt->fetchColumn() === $stamp) {
                return;                 // schema already matches this file
            }
        } catch (\Throwable $e) {
            // No settings table yet: a first boot, so everything below runs.
        }

        $isSqlite = self::$config['db']['driver'] !== 'mysql';
        $pk  = $isSqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY';
        $now = $isSqlite ? "(datetime('now'))" : 'CURRENT_TIMESTAMP';

        $pdo->exec("CREATE TABLE IF NOT EXISTS users (
            id $pk,
            username      VARCHAR(64) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            created_at    TIMESTAMP DEFAULT $now
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS symbols (
            id $pk,
            symbol     VARCHAR(32) NOT NULL UNIQUE,
            label      VARCHAR(64) NOT NULL,
            enabled    INTEGER NOT NULL DEFAULT 1,
            tier       VARCHAR(12) NOT NULL DEFAULT 'public',
            created_at TIMESTAMP DEFAULT $now
        )");
        // Older installs: add the tier column in place.
        try {
            $pdo->query('SELECT tier FROM symbols LIMIT 1');
        } catch (\Throwable $e) {
            $pdo->exec("ALTER TABLE symbols ADD COLUMN tier VARCHAR(12) NOT NULL DEFAULT 'public'");
        }
        // Per-symbol engine overrides as JSON. BTC on the daily and a meme
        // coin on 15m were sharing one set of thresholds; a single global
        // configuration cannot suit both.
        try {
            $pdo->query('SELECT engine_json FROM symbols LIMIT 1');
        } catch (\Throwable $e) {
            $pdo->exec("ALTER TABLE symbols ADD COLUMN engine_json TEXT NOT NULL DEFAULT ''");
        }
        // Is this coin part of the background cron scan?
        //
        // "enabled" answers a different question - whether the coin exists on
        // the site at all - so the only way to keep a coin listed but out of
        // the scan rotation was to delete it. On a ten-minute cron every coin
        // in the rotation costs a slice of every cycle, so an operator who
        // lists sixty coins but only trades twelve was spending four fifths of
        // the budget on pairs nobody reads. Defaults to 1: an upgrade changes
        // nothing until somebody unticks a coin.
        try {
            $pdo->query('SELECT scan FROM symbols LIMIT 1');
        } catch (\Throwable $e) {
            $pdo->exec('ALTER TABLE symbols ADD COLUMN scan INTEGER NOT NULL DEFAULT 1');
        }
        // Why the scan gave up on a coin, and how many runs in a row it has
        // failed. An imported watchlist of several hundred pairs always rots:
        // coins get delisted, quote assets are retired, tickers change. The
        // exchange answers HTTP 400 for every one of them, forever, and each
        // dead coin costs a slot in every cycle that a live coin could have
        // had. Counted here so the rotation can drop them by itself.
        try {
            $pdo->query('SELECT scan_fails FROM symbols LIMIT 1');
        } catch (\Throwable $e) {
            $pdo->exec('ALTER TABLE symbols ADD COLUMN scan_fails INTEGER NOT NULL DEFAULT 0');
            $pdo->exec("ALTER TABLE symbols ADD COLUMN scan_note VARCHAR(190) NOT NULL DEFAULT ''");
        }
        // Coins that get looked at every single run, not once per rotation.
        //
        // A rotation treats every coin as equally important, so on a long
        // watchlist Bitcoin is queued behind whatever memecoin happens to sit
        // in front of it and gets read as rarely as the tail does. The pairs an
        // operator actually publishes deserve the fast lane; the rest can wait
        // their turn. 1 = every run, 0 = normal rotation.
        try {
            $pdo->query('SELECT priority FROM symbols LIMIT 1');
        } catch (\Throwable $e) {
            $pdo->exec('ALTER TABLE symbols ADD COLUMN priority INTEGER NOT NULL DEFAULT 0');
        }
        // Where a coin is allowed to appear - see the Visibility class.
        //
        // "Enabled" was the only lever, and it is all-or-nothing: a coin was
        // either on every surface of the site or on none of them. There is no
        // way to run a pair for its chart while keeping it out of the scanner,
        // or to publish a coin nobody may follow with real position sizing.
        // Stored as a comma-delimited list of the surfaces to HIDE it on, with
        // sentinel commas so it can be matched from SQL in both engines, and
        // empty meaning "everywhere" - so every existing coin keeps behaving
        // exactly as it does today.
        try {
            $pdo->query('SELECT hide_on FROM symbols LIMIT 1');
        } catch (\Throwable $e) {
            $pdo->exec("ALTER TABLE symbols ADD COLUMN hide_on VARCHAR(160) NOT NULL DEFAULT ''");
        }
        // Which timeframes this coin is read on.
        //
        // The timeframe list was one site-wide setting, so every coin was
        // analysed and offered on all of them whether that made sense or not.
        // A thin altcoin has no meaningful 5m structure and a major is worth
        // watching on every frame, and until now those two had to be treated
        // identically - which also means the thin one costs a scan slot per
        // timeframe on every rotation for readings nobody should act on.
        // Empty means "whatever the site allows", so nothing changes for a
        // coin nobody has set.
        try {
            $pdo->query('SELECT tfs FROM symbols LIMIT 1');
        } catch (\Throwable $e) {
            $pdo->exec("ALTER TABLE symbols ADD COLUMN tfs VARCHAR(120) NOT NULL DEFAULT ''");
        }

        // Site members (visitors who register). Separate from the admin
        // 'users' table. tier: free | paid. paid_until: unix ts when the paid
        // plan expires (0 = unlimited, for manually-granted lifetime access).
        $pdo->exec("CREATE TABLE IF NOT EXISTS members (
            id $pk,
            email         VARCHAR(190) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            tier          VARCHAR(12) NOT NULL DEFAULT 'free',
            paid_until    BIGINT NOT NULL DEFAULT 0,
            created_at    BIGINT NOT NULL
        )");
        try {
            $pdo->query('SELECT paid_until FROM members LIMIT 1');
        } catch (\Throwable $e) {
            $pdo->exec('ALTER TABLE members ADD COLUMN paid_until BIGINT NOT NULL DEFAULT 0');
        }
        // When this member last closed the upgrade prompt. On members rather
        // than member_prefs because it is not a preference - the member never
        // chose it and never sees it in their settings - and a row in
        // member_prefs exists only once somebody has opened that screen, so
        // storing it there would mean the prompt could not remember being
        // dismissed by exactly the people most likely to dismiss it.
        try {
            $pdo->query('SELECT upsell_dismissed FROM members LIMIT 1');
        } catch (\Throwable $e) {
            $pdo->exec('ALTER TABLE members ADD COLUMN upsell_dismissed BIGINT NOT NULL DEFAULT 0');
        }
        // When this member last closed a discount campaign's popup. Separate
        // from upsell_dismissed on purpose: closing "here is what Premium
        // adds" is not a statement about a half-price weekend three months
        // later, and one column for both would let a dismissal of either
        // silence the other.
        try {
            $pdo->query('SELECT promo_dismissed FROM members LIMIT 1');
        } catch (\Throwable $e) {
            $pdo->exec('ALTER TABLE members ADD COLUMN promo_dismissed BIGINT NOT NULL DEFAULT 0');
        }
        // Telegram linking + password reset (token stored hashed).
        try {
            $pdo->query('SELECT tg_chat_id FROM members LIMIT 1');
        } catch (\Throwable $e) {
            $pdo->exec('ALTER TABLE members ADD COLUMN tg_chat_id BIGINT NOT NULL DEFAULT 0');
            $pdo->exec("ALTER TABLE members ADD COLUMN tg_link_code VARCHAR(32) NOT NULL DEFAULT ''");
            $pdo->exec("ALTER TABLE members ADD COLUMN reset_token VARCHAR(64) NOT NULL DEFAULT ''");
            $pdo->exec('ALTER TABLE members ADD COLUMN reset_expires BIGINT NOT NULL DEFAULT 0');
        }
        // Premium signals API tokens.
        try {
            $pdo->query('SELECT api_token FROM members LIMIT 1');
        } catch (\Throwable $e) {
            $pdo->exec("ALTER TABLE members ADD COLUMN api_token VARCHAR(64) NOT NULL DEFAULT ''");
        }
        // Referral codes: members bring members, credited in premium days so
        // nothing leaves the existing subscription machinery.
        try {
            $pdo->query('SELECT ref_code FROM members LIMIT 1');
        } catch (\Throwable $e) {
            $pdo->exec("ALTER TABLE members ADD COLUMN ref_code VARCHAR(16) NOT NULL DEFAULT ''");
            $pdo->exec('ALTER TABLE members ADD COLUMN referred_by INTEGER NOT NULL DEFAULT 0');
            $pdo->exec('ALTER TABLE members ADD COLUMN referral_paid INTEGER NOT NULL DEFAULT 0');
        }

        // Email verification at registration. `verified` defaults to 1 so the
        // ALTER marks every account that existed before this feature as
        // already good - they registered under the old rules and locking them
        // out retroactively would be a bug, not a security improvement. New
        // rows are written with an explicit 0 when a code is required.
        try {
            $pdo->query('SELECT verified FROM members LIMIT 1');
        } catch (\Throwable $e) {
            $pdo->exec('ALTER TABLE members ADD COLUMN verified INTEGER NOT NULL DEFAULT 1');
            $pdo->exec("ALTER TABLE members ADD COLUMN otp_hash VARCHAR(64) NOT NULL DEFAULT ''");
            $pdo->exec('ALTER TABLE members ADD COLUMN otp_expires BIGINT NOT NULL DEFAULT 0');
            $pdo->exec('ALTER TABLE members ADD COLUMN otp_tries INTEGER NOT NULL DEFAULT 0');
            $pdo->exec('ALTER TABLE members ADD COLUMN otp_sent BIGINT NOT NULL DEFAULT 0');
        }

        // Changing a password ends every other session.
        //
        // "Someone got into my account" is answered by changing the password,
        // and that answer is worth nothing if the intruder's session cookie
        // still works - PHP sessions live in their own files and know nothing
        // about the password that opened them. This column is the generation
        // number: the session records what it was at login, and a session
        // holding a stale one is dead.
        //
        // Defaulting to 0 means nobody is logged out by the upgrade itself.
        // Every existing session adopts the current value on its next request
        // and is enforced from then on, so the protection starts at the first
        // password change rather than at deploy.
        try {
            $pdo->query('SELECT pw_changed FROM members LIMIT 1');
        } catch (\Throwable $e) {
            $pdo->exec('ALTER TABLE members ADD COLUMN pw_changed BIGINT NOT NULL DEFAULT 0');
        }
        try {
            $pdo->query('SELECT pw_changed FROM users LIMIT 1');
        } catch (\Throwable $e) {
            $pdo->exec('ALTER TABLE users ADD COLUMN pw_changed BIGINT NOT NULL DEFAULT 0');
        }

        // Subscription plans - fully admin-customizable (name/duration/price).
        $pdo->exec("CREATE TABLE IF NOT EXISTS plans (
            id $pk,
            name      VARCHAR(64) NOT NULL,
            days      INTEGER NOT NULL,
            price_usd DOUBLE PRECISION NOT NULL,
            enabled   INTEGER NOT NULL DEFAULT 1,
            sort      INTEGER NOT NULL DEFAULT 0
        )");

        // Payment methods. kind 'cryptomus' rows are automatic gateway options
        // (one per crypto currency); kind 'manual' rows carry admin-written
        // instructions (wallet address / bank details / UPI number / link)
        // and require a proof-of-payment submission reviewed by the admin.
        $pdo->exec("CREATE TABLE IF NOT EXISTS payment_methods (
            id $pk,
            name     VARCHAR(64) NOT NULL,
            kind     VARCHAR(16) NOT NULL DEFAULT 'manual',
            currency VARCHAR(16) NOT NULL DEFAULT '',
            details  TEXT NOT NULL DEFAULT '',
            image    VARCHAR(190) NOT NULL DEFAULT '',
            enabled  INTEGER NOT NULL DEFAULT 1,
            sort     INTEGER NOT NULL DEFAULT 0
        )");
        try {
            $pdo->query('SELECT image FROM payment_methods LIMIT 1');
        } catch (\Throwable $e) {
            $pdo->exec("ALTER TABLE payment_methods ADD COLUMN image VARCHAR(190) NOT NULL DEFAULT ''");
        }
        // What the buyer must submit on a manual payment: a proof image and/or
        // a custom text answer (ask_text is the question label; '' = don't ask).
        try {
            $pdo->query('SELECT ask_image FROM payment_methods LIMIT 1');
        } catch (\Throwable $e) {
            $pdo->exec('ALTER TABLE payment_methods ADD COLUMN ask_image INTEGER NOT NULL DEFAULT 1');
            $pdo->exec("ALTER TABLE payment_methods ADD COLUMN ask_text VARCHAR(120) NOT NULL DEFAULT ''");
        }

        // Percent-off coupon codes for plans (admin-created).
        $pdo->exec("CREATE TABLE IF NOT EXISTS coupons (
            id $pk,
            code        VARCHAR(32) NOT NULL UNIQUE,
            percent_off INTEGER NOT NULL,
            max_uses    INTEGER NOT NULL DEFAULT 0,
            used_count  INTEGER NOT NULL DEFAULT 0,
            expires_at  BIGINT NOT NULL DEFAULT 0,
            enabled     INTEGER NOT NULL DEFAULT 1
        )");

        // Activation keys: premium paid for once and handed over as a code.
        // Separate from coupons on purpose - a coupon is a discount on a
        // payment somebody is still going to make, a key IS the payment. See
        // src/Redeem.php.
        $pdo->exec("CREATE TABLE IF NOT EXISTS redeem_codes (
            id $pk,
            code       VARCHAR(32) NOT NULL UNIQUE,
            plan_id    INTEGER NOT NULL DEFAULT 0,
            plan_name  VARCHAR(64) NOT NULL DEFAULT '',
            days       INTEGER NOT NULL DEFAULT 30,
            max_uses   INTEGER NOT NULL DEFAULT 1,
            used_count INTEGER NOT NULL DEFAULT 0,
            expires_at BIGINT NOT NULL DEFAULT 0,
            enabled    INTEGER NOT NULL DEFAULT 1,
            note       VARCHAR(190) NOT NULL DEFAULT '',
            batch      VARCHAR(32) NOT NULL DEFAULT '',
            created_at BIGINT NOT NULL DEFAULT 0
        )");
        self::index($pdo, 'CREATE INDEX IF NOT EXISTS idx_redeem_batch ON redeem_codes (batch)');
        // Who redeemed what, and which payment it became. The row that answers
        // "this key says used, by whom?" - without it a disputed key is one
        // person's word, and a multi-use key could be re-run by the same
        // account for as long as it had seats left.
        $pdo->exec("CREATE TABLE IF NOT EXISTS redeem_uses (
            id $pk,
            code_id    INTEGER NOT NULL,
            member_id  INTEGER NOT NULL,
            payment_id INTEGER NOT NULL DEFAULT 0,
            created_at BIGINT NOT NULL DEFAULT 0
        )");
        // UNIQUE, and that is the whole enforcement of "one member, one use".
        //
        // Redeem::apply checked redeem_uses and then inserted into it, which is
        // two statements with a gap in the middle. Proved exploitable on a
        // multi-worker server: six simultaneous requests from six sessions of
        // ONE account against a ten-seat reseller key came back with two
        // redemptions, sixty days and two seats gone. A member with a shared
        // key could drain it. The index makes the database refuse the second
        // row whatever the application believed a microsecond earlier.
        //
        // Deduped first: an install that already ran the racy version can hold
        // duplicate rows, and CREATE UNIQUE INDEX on top of those fails and
        // leaves the table unprotected in silence.
        try {
            $pdo->exec('DELETE FROM redeem_uses WHERE id NOT IN
                        (SELECT MIN(id) FROM redeem_uses GROUP BY code_id, member_id)');
        } catch (\Throwable $e) {
            // nothing to dedupe on a fresh install
        }
        // A NEW NAME, because CREATE UNIQUE INDEX IF NOT EXISTS on a name that
        // already exists as a plain index is a silent no-op. The first release
        // of this table shipped idx_redeem_uses non-unique, so upgrading it in
        // place would have left every one of those installs unprotected while
        // reporting success. Caught by reading the index back off the test
        // database rather than trusting the statement.
        foreach (['DROP INDEX idx_redeem_uses',
                  'DROP INDEX idx_redeem_uses ON redeem_uses'] as $drop) {
            try {
                $pdo->exec($drop);   // sqlite form, then the mysql form
                break;
            } catch (\Throwable $e) {
                // not present, or the other dialect - try the next
            }
        }
        self::index($pdo, 'CREATE UNIQUE INDEX IF NOT EXISTS idx_redeem_uses_u ON redeem_uses (code_id, member_id)');

        // Payment records / order history.
        $pdo->exec("CREATE TABLE IF NOT EXISTS payments (
            id $pk,
            member_id   INTEGER NOT NULL,
            plan_id     INTEGER NOT NULL,
            plan_name   VARCHAR(64) NOT NULL,
            plan_days   INTEGER NOT NULL,
            method_name VARCHAR(64) NOT NULL,
            kind        VARCHAR(16) NOT NULL,
            amount_usd  DOUBLE PRECISION NOT NULL,
            currency    VARCHAR(16) NOT NULL DEFAULT '',
            status      VARCHAR(20) NOT NULL DEFAULT 'pending',
            gateway_uuid VARCHAR(64) NOT NULL DEFAULT '',
            gateway_ref  VARCHAR(64) NOT NULL DEFAULT '',
            gateway_url  VARCHAR(500) NOT NULL DEFAULT '',
            proof_image VARCHAR(190) NOT NULL DEFAULT '',
            note        VARCHAR(500) NOT NULL DEFAULT '',
            coupon      VARCHAR(32) NOT NULL DEFAULT '',
            created_at  BIGINT NOT NULL,
            updated_at  BIGINT NOT NULL
        )");
        try {
            $pdo->query('SELECT coupon FROM payments LIMIT 1');
        } catch (\Throwable $e) {
            $pdo->exec("ALTER TABLE payments ADD COLUMN coupon VARCHAR(32) NOT NULL DEFAULT ''");
        }
        // gateway_uuid holds the reference the gateway gave us when the invoice
        // was created. NOWPayments needs a second one: its invoice id cannot be
        // looked up with an API key (that route wants a JWT), but the payment id
        // it sends in the IPN can - so it is kept here and used to re-check.
        try {
            $pdo->query('SELECT gateway_ref FROM payments LIMIT 1');
        } catch (\Throwable $e) {
            $pdo->exec("ALTER TABLE payments ADD COLUMN gateway_ref VARCHAR(64) NOT NULL DEFAULT ''");
        }
        self::index($pdo, 'CREATE INDEX IF NOT EXISTS idx_payments_member ON payments (member_id, created_at)');
        // A BTCPay webhook finds its payment by the gateway's own invoice id,
        // and that lookup was reading every payment ever taken - on the one
        // request that must be fast and must not fail, because the gateway
        // retries a slow one and gives up on a timeout.
        self::index($pdo, 'CREATE INDEX IF NOT EXISTS idx_payments_uuid ON payments (kind, gateway_uuid)');
        // Admin > Payments counts and filters by status on every load.
        self::index($pdo, 'CREATE INDEX IF NOT EXISTS idx_payments_status ON payments (status, created_at)');
        // Every authenticated call to the JSON feed looks a member up by this
        // token, and it was a full scan of the members table each time - the
        // one table that grows with the success of the business.
        self::index($pdo, 'CREATE INDEX IF NOT EXISTS idx_members_token ON members (api_token)');
        // The other three one-row lookups into the same growing table: a
        // password reset link, a referral code (read on every registration and
        // every referral page), and the Telegram linking code. Each was a full
        // scan; each is a single row.
        self::index($pdo, 'CREATE INDEX IF NOT EXISTS idx_members_reset ON members (reset_token)');
        self::index($pdo, 'CREATE INDEX IF NOT EXISTS idx_members_ref ON members (ref_code)');
        self::index($pdo, 'CREATE INDEX IF NOT EXISTS idx_members_referred ON members (referred_by)');
        self::index($pdo, 'CREATE INDEX IF NOT EXISTS idx_members_tglink ON members (tg_link_code)');

        // Per-member email alert preferences (bullish/bearish flip emails).
        $pdo->exec("CREATE TABLE IF NOT EXISTS member_alerts (
            member_id INTEGER PRIMARY KEY,
            enabled   INTEGER NOT NULL DEFAULT 0,
            pairs     TEXT NOT NULL DEFAULT '',
            last_sent TEXT NOT NULL DEFAULT '{}'
        )");
        // Telegram flips need their own last-sent state so the email and
        // Telegram dispatchers never race each other's change detection.
        try {
            $pdo->query('SELECT tg_last_sent FROM member_alerts LIMIT 1');
        } catch (\Throwable $e) {
            $pdo->exec("ALTER TABLE member_alerts ADD COLUMN tg_last_sent TEXT NOT NULL DEFAULT '{}'");
        }

        // Per-member configuration: strategy profile, risk sizing, alert
        // filters, timezone and outbound webhook. Previously these were either
        // site-wide admin settings (one risk appetite for everybody) or absent.
        $pdo->exec("CREATE TABLE IF NOT EXISTS member_prefs (
            member_id      INTEGER PRIMARY KEY,
            profile        VARCHAR(20) NOT NULL DEFAULT 'balanced',
            timezone       VARCHAR(64) NOT NULL DEFAULT '',
            account_size   DOUBLE PRECISION NOT NULL DEFAULT 0,
            risk_pct       DOUBLE PRECISION NOT NULL DEFAULT 1,
            leverage       DOUBLE PRECISION NOT NULL DEFAULT 1,
            min_grade      VARCHAR(4) NOT NULL DEFAULT 'any',
            min_confidence DOUBLE PRECISION NOT NULL DEFAULT 0,
            directions     VARCHAR(8) NOT NULL DEFAULT 'both',
            quiet_from     INTEGER NOT NULL DEFAULT -1,
            quiet_to       INTEGER NOT NULL DEFAULT -1,
            max_alerts_day INTEGER NOT NULL DEFAULT 0,
            webhook_url    VARCHAR(400) NOT NULL DEFAULT '',
            rule_weights   TEXT NOT NULL DEFAULT '{}',
            disabled_categories TEXT NOT NULL DEFAULT '',
            alert_types    VARCHAR(12) NOT NULL DEFAULT '',
            -- Receipts for the member's own paper trades. '1' by default,
            -- including for every account that exists on upgrade: a trade you
            -- opened yourself telling you it closed is the receipt, not
            -- marketing, and defaulting it off would mean nobody ever sees it.
            trade_email    VARCHAR(1) NOT NULL DEFAULT '1',
            updated_at     BIGINT NOT NULL DEFAULT 0
        )");
        try {
            $pdo->query('SELECT trade_email FROM member_prefs LIMIT 1');
        } catch (\Throwable $e) {
            try {
                $pdo->exec("ALTER TABLE member_prefs ADD COLUMN trade_email VARCHAR(1) NOT NULL DEFAULT '1'");
            } catch (\Throwable $e2) {
                // without the column every member simply keeps the default
            }
        }
        // Which of the three signal types a member wants to hear about, as a
        // comma list of tiers. EMPTY MEANS ALL OF THEM, which is what every
        // existing account gets on upgrade: a new filter that silently starts
        // withholding alerts somebody was already receiving is a bug wearing a
        // feature's clothes.
        try {
            $pdo->query('SELECT alert_types FROM member_prefs LIMIT 1');
        } catch (\Throwable $e) {
            $pdo->exec("ALTER TABLE member_prefs ADD COLUMN alert_types VARCHAR(12) NOT NULL DEFAULT ''");
        }

        // THE TRIAL LEDGER, WHICH OUTLIVES THE ACCOUNTS IT DESCRIBES.
        //
        // "Has this person had a trial" cannot be a column on members: this
        // site lets a member delete their own account, and that would answer
        // "no" again. So claims live here, keyed on things that survive the
        // deletion - and nothing identifying is kept, because a table whose
        // job is to be retained for years should not also be a list of email
        // addresses and IPs. All three keys are per-install HMACs; see Trial.
        //
        // email_key is UNIQUE and that is not decoration: it is what decides
        // the race when two registrations for one address arrive at once.
        $pdo->exec("CREATE TABLE IF NOT EXISTS trials (
            id         $pk,
            member_id  INTEGER NOT NULL,
            email_key  VARCHAR(64) NOT NULL,
            ip_key     VARCHAR(64) NOT NULL DEFAULT '',
            device_key VARCHAR(64) NOT NULL DEFAULT '',
            days       INTEGER NOT NULL DEFAULT 0,
            started_at BIGINT NOT NULL DEFAULT 0,
            ends_at    BIGINT NOT NULL DEFAULT 0
        )");
        $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_trials_email ON trials (email_key)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_trials_ip ON trials (ip_key, started_at)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_trials_device ON trials (device_key)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_trials_member ON trials (member_id)');

        // Named watchlists, server-side. The watched-pair list used to live in
        // browser localStorage, so it vanished on a second device even though
        // the server was already storing the same pairs for alert delivery.
        $pdo->exec("CREATE TABLE IF NOT EXISTS watchlists (
            id $pk,
            member_id  INTEGER NOT NULL,
            name       VARCHAR(40) NOT NULL,
            pairs      TEXT NOT NULL DEFAULT '',
            is_active  INTEGER NOT NULL DEFAULT 0,
            updated_at BIGINT NOT NULL DEFAULT 0
        )");
        self::index($pdo, 'CREATE INDEX IF NOT EXISTS idx_watchlists_member ON watchlists (member_id)');

        // THE MANUAL CHART'S TABLE, DROPPED.
        //
        // member_drawings held a member's own trendlines, levels, zones and
        // fibs - one row per member per pair per timeframe - for a second
        // chart where they drew their own setup instead of reading the
        // engine's. That chart has been removed, so this is dropped rather
        // than left behind: a table nothing reads still gets backed up, still
        // gets copied on every migration, and still shows up in a data export
        // as personal data the site has no reason to hold.
        //
        // IF EXISTS, and swallowed, because an install that never had it must
        // not fail its migration over a table it does not have.
        try {
            $pdo->exec('DROP TABLE IF EXISTS member_drawings');
        } catch (\Throwable $e) {
            // a database that will not drop it is not a reason to refuse to boot
        }

        // WHAT THE ENGINE HAS LEARNED ABOUT EACH COIN, AS DATA RATHER THAN AS
        // A SETTING.
        //
        // These multipliers lived in one JSON blob in the settings table, and
        // every fault that arrangement has, it had:
        //
        //   - A 48KB budget, enforced by DELETING COINS from the end of the
        //     list until the string fit. An install past that size was quietly
        //     un-learning its least-evidenced coins on every tuning run.
        //   - Read-modify-write for a single coin, so deleting one coin's
        //     learning rewrote every other coin's at the same time.
        //   - No way to ask "what did this coin learn" without decoding the
        //     whole blob, and no way to age a single rule out.
        //   - Signals are the evidence and this is the conclusion drawn from
        //     them; keeping the conclusion inside the settings row meant the
        //     two could not be cleared, backed up or reasoned about apart.
        //
        // One row per (coin, rule) removes all four. It stays in the same
        // database - a second connection would mean two configs, two backups
        // and no transaction spanning them, on hosting where the whole point
        // is that there is one file to upload - but it is now its own table
        // with its own lifecycle, which is the separation that was wanted.
        $pdo->exec("CREATE TABLE IF NOT EXISTS symbol_learning (
            id $pk,
            symbol     VARCHAR(32) NOT NULL,
            rule_key   VARCHAR(64) NOT NULL,
            mult       DOUBLE PRECISION NOT NULL DEFAULT 1,
            samples    INTEGER NOT NULL DEFAULT 0,
            updated_at BIGINT NOT NULL DEFAULT 0
        )");
        self::index($pdo, 'CREATE UNIQUE INDEX IF NOT EXISTS idx_symlearn ON symbol_learning (symbol, rule_key)');
        self::index($pdo, 'CREATE INDEX IF NOT EXISTS idx_symlearn_sym ON symbol_learning (symbol)');

        // Paper-traded positions opened from signals, and the member's own
        // journal entries against real trades.
        $pdo->exec("CREATE TABLE IF NOT EXISTS paper_trades (
            id $pk,
            member_id  INTEGER NOT NULL,
            signal_id  INTEGER NOT NULL DEFAULT 0,
            symbol     VARCHAR(32) NOT NULL,
            tf         VARCHAR(8) NOT NULL,
            side       VARCHAR(8) NOT NULL,
            entry      DOUBLE PRECISION NOT NULL,
            stop_loss  DOUBLE PRECISION NOT NULL,
            tp1        DOUBLE PRECISION NOT NULL DEFAULT 0,
            tp2        DOUBLE PRECISION NOT NULL DEFAULT 0,
            tp3        DOUBLE PRECISION NOT NULL DEFAULT 0,
            units      DOUBLE PRECISION NOT NULL DEFAULT 0,
            -- Balance committed to this position, and at what leverage. Size
            -- is chosen per trade in the order ticket, so the wallet cannot
            -- work out what is tied up from a single account-wide setting.
            margin     DOUBLE PRECISION NOT NULL DEFAULT 0,
            leverage   DOUBLE PRECISION NOT NULL DEFAULT 1,
            status     VARCHAR(12) NOT NULL DEFAULT 'open',
            outcome_r  DOUBLE PRECISION NOT NULL DEFAULT 0,
            pnl        DOUBLE PRECISION NOT NULL DEFAULT 0,
            note       VARCHAR(500) NOT NULL DEFAULT '',
            source     VARCHAR(12) NOT NULL DEFAULT 'paper',
            opened_at  BIGINT NOT NULL,
            closed_at  BIGINT NOT NULL DEFAULT 0
        )");
        self::index($pdo, 'CREATE INDEX IF NOT EXISTS idx_paper_member ON paper_trades (member_id, status)');
        // Older installs sized every position from one account-wide risk
        // setting, so there was nothing to record. Existing open positions
        // keep a zero margin: they were opened before the wallet existed and
        // should not retroactively lock funds that were never committed.
        try {
            $pdo->query('SELECT margin FROM paper_trades LIMIT 1');
        } catch (\Throwable $e) {
            $pdo->exec('ALTER TABLE paper_trades ADD COLUMN margin DOUBLE PRECISION NOT NULL DEFAULT 0');
            $pdo->exec('ALTER TABLE paper_trades ADD COLUMN leverage DOUBLE PRECISION NOT NULL DEFAULT 1');
        }
        // Execution telemetry, recorded when the position opens: how far the
        // fill drifted from the signal price, and what the market cost to
        // trade at that moment. Excursions are filled in when the trade
        // settles. See the signals table for the same fields on the signal.
        try {
            $pdo->query('SELECT slippage_pct FROM paper_trades LIMIT 1');
        } catch (\Throwable $e) {
            foreach (['slippage_pct', 'spread_pct', 'atr_pct', 'mae_r', 'mfe_r'] as $col) {
                $pdo->exec("ALTER TABLE paper_trades ADD COLUMN $col DOUBLE PRECISION NULL");
            }
            foreach (['mae_sec', 'mfe_sec'] as $col) {
                $pdo->exec("ALTER TABLE paper_trades ADD COLUMN $col INTEGER NULL");
            }
        }
        // The deadline the signal published, carried onto the position. The
        // regime read gives a grinding trend longer than a whipsaw, and a
        // member told "expires in four days" whose trade the settler closed
        // after two was shown one trade and given another.
        try {
            $pdo->query('SELECT expires_bars FROM paper_trades LIMIT 1');
        } catch (\Throwable $e) {
            $pdo->exec('ALTER TABLE paper_trades ADD COLUMN expires_bars INTEGER NULL');
        }
        // The price the position actually left the market at.
        //
        // A closed trade recorded what it was worth - R and cash - but not the
        // two numbers a trader reads first: in at what, out at what. The exit
        // was computed and thrown away, by the manual close and by the
        // settler alike, so the record could never be checked against a chart.
        // NULL on rows closed before this existed, which reads as "not
        // recorded" rather than as a price of zero.
        // Positions liquidated before Paper::book existed kept a capped loss
        // beside an uncapped R, and outcome_r is what a member's expectancy and
        // profit factor are built from. Runs with the rest of the migration -
        // once per schema change rather than on every request - and is a no-op
        // on an install that has none.
        try {
            Paper::recapLiquidations();
        } catch (\Throwable $e) {
            // a history that will not rewrite is not a reason to refuse to boot
        }

        // WHAT HAS ALREADY BEEN EMAILED ABOUT THIS TRADE.
        //
        // Two stamps rather than one flag, because a position generates two
        // messages at two different times and the second must not be blocked
        // by the first having gone.
        //
        // The stamps live on the row rather than in a queue for one reason: a
        // send that fails leaves the column null and the next cron pass tries
        // again, and a send that succeeds can never be repeated even if the
        // pass is interrupted between the mail and the update. A queue would
        // need the same two facts and a table to hold them.
        foreach (['open_mailed_at', 'close_mailed_at'] as $mailCol) {
            try {
                $pdo->query("SELECT $mailCol FROM paper_trades LIMIT 1");
            } catch (\Throwable $e) {
                try {
                    $pdo->exec("ALTER TABLE paper_trades ADD COLUMN $mailCol BIGINT NULL");
                } catch (\Throwable $e2) {
                    // an install that will not take the column simply never mails
                }
            }
        }

        try {
            $pdo->query('SELECT exit_price FROM paper_trades LIMIT 1');
        } catch (\Throwable $e) {
            $pdo->exec('ALTER TABLE paper_trades ADD COLUMN exit_price DOUBLE PRECISION NULL');
        }
        // The price the SIGNAL published, kept beside the price the member
        // actually filled at. Taking a setup at market forty minutes after it
        // was found is a different trade from the one the engine described,
        // and with only one price on the row there was no way to see that -
        // the difference was buried in slippage_pct as a percentage nobody
        // reads. NULL on a journal entry, which has no signal behind it.
        // Which target closes the position, when the member wants to name one.
        //
        // Every paper trade was settled by the site's own exit plan - part off
        // at target 1, the rest run to target 2 - which is the right default
        // and the wrong answer for anyone who trades their own way. A reader
        // who always takes the first target, or always holds for the third,
        // had no way to say so, and their portfolio measured a plan they were
        // not following.
        //
        //   3, 2, 1  the whole position leaves at that target
        //   0        the site's plan: part at target 1, the rest to target 2
        //  -1        no target at all - only the stop loss or the time stop
        //
        // New positions default to 3, the furthest target the signal
        // publishes, which is the plan that makes publishing a third target
        // mean anything. Rows written before this existed keep whatever they
        // were settled under; the default only decides what happens next.
        try {
            $pdo->query('SELECT exit_target FROM paper_trades LIMIT 1');
        } catch (\Throwable $e) {
            $pdo->exec('ALTER TABLE paper_trades ADD COLUMN exit_target INTEGER NOT NULL DEFAULT 3');
        }
        try {
            $pdo->query('SELECT signal_price FROM paper_trades LIMIT 1');
        } catch (\Throwable $e) {
            $pdo->exec('ALTER TABLE paper_trades ADD COLUMN signal_price DOUBLE PRECISION NULL');
            // Existing rows are recovered rather than lost: slippage is the
            // signed gap between the two prices, so the published one divides
            // back out of the fill. Rows with no slippage recorded - journal
            // entries, and positions opened before the telemetry existed -
            // stay NULL, because a guess would read as a fact.
            try {
                $pdo->exec(
                    'UPDATE paper_trades
                        SET signal_price = entry / (1 + (CASE WHEN side = \'BUY\' THEN 1 ELSE -1 END)
                                                        * slippage_pct / 100.0)
                      WHERE slippage_pct IS NOT NULL AND ABS(slippage_pct) < 90 AND entry > 0'
                );
            } catch (\Throwable $e) {
                // Recovery is a convenience; never let it fail the upgrade.
            }
        }

        // Web Push subscriptions (browser-closed notifications).
        $pdo->exec("CREATE TABLE IF NOT EXISTS push_subs (
            id $pk,
            endpoint_hash VARCHAR(64) NOT NULL UNIQUE,
            endpoint   TEXT NOT NULL,
            p256dh     VARCHAR(255) NOT NULL,
            auth       VARCHAR(64) NOT NULL,
            member_id  INTEGER NOT NULL DEFAULT 0,
            pairs      TEXT NOT NULL DEFAULT '',
            last_sent  TEXT NOT NULL DEFAULT '{}',
            created_at BIGINT NOT NULL
        )");

        // Raw OHLCV candles cached from the market data API.
        $pdo->exec("CREATE TABLE IF NOT EXISTS candles (
            id $pk,
            symbol     VARCHAR(32)  NOT NULL,
            tf         VARCHAR(8)   NOT NULL,
            open_time  BIGINT       NOT NULL,
            open       DOUBLE PRECISION NOT NULL,
            high       DOUBLE PRECISION NOT NULL,
            low        DOUBLE PRECISION NOT NULL,
            close      DOUBLE PRECISION NOT NULL,
            volume     DOUBLE PRECISION NOT NULL,
            CONSTRAINT uq_candle UNIQUE (symbol, tf, open_time)
        )");
        // No second index here on purpose.
        //
        // idx_candles_lookup used to be created on (symbol, tf, open_time) -
        // the same three columns, in the same order, as the unique constraint
        // above. A unique constraint IS an index, so every candle written was
        // maintaining two identical B-trees and every candle stored was paying
        // for both. On a watchlist of a few hundred coins that is tens of
        // megabytes of duplicate index and a slower write for nothing. It is
        // dropped from existing installs below; no query loses a path, because
        // uq_candle serves every one the old index did.
        foreach (['ALTER TABLE candles DROP INDEX idx_candles_lookup',
                  'DROP INDEX IF EXISTS idx_candles_lookup'] as $drop) {
            try {
                $pdo->exec($drop);
                break;
            } catch (\Throwable $e) {
                // Wrong dialect, or already gone - try the next form.
            }
        }

        // Tracks freshness of each cached symbol/timeframe series.
        $pdo->exec("CREATE TABLE IF NOT EXISTS fetch_log (
            id $pk,
            symbol     VARCHAR(32) NOT NULL,
            tf         VARCHAR(8)  NOT NULL,
            fetched_at BIGINT      NOT NULL,
            candles    INTEGER     NOT NULL,
            CONSTRAINT uq_fetch UNIQUE (symbol, tf)
        )");

        // Every generated signal is stored for history/audit.
        // NB: `signal` is a reserved word in MySQL/MariaDB - always backtick it
        // (SQLite accepts backticks too).
        $pdo->exec("CREATE TABLE IF NOT EXISTS signals (
            id $pk,
            symbol      VARCHAR(32) NOT NULL,
            tf          VARCHAR(8)  NOT NULL,
            `signal`    VARCHAR(12) NOT NULL,
            score       DOUBLE PRECISION NOT NULL,
            confidence  DOUBLE PRECISION NOT NULL,
            price       DOUBLE PRECISION NOT NULL,
            reasons     TEXT NOT NULL,
            indicators  TEXT NOT NULL,
            created_at  BIGINT NOT NULL
        )");
        self::index($pdo, 'CREATE INDEX IF NOT EXISTS idx_signals_symbol ON signals (symbol, tf, created_at)');
        // Newest-first with no filter - the admin signal list, its export and
        // the dashboard's last-ten. Without this the database sorts the whole
        // ledger to hand back ten rows, and that table is the one that grows
        // every scan of every pair.
        self::index($pdo, 'CREATE INDEX IF NOT EXISTS idx_signals_created ON signals (created_at)');
        // Per-coin learning reads one coin's settled history at a time, newest
        // first, once per coin per night. Without this the database found the
        // rows by symbol and then sorted them in a temporary tree, for every
        // coin, every run.
        self::index($pdo, 'CREATE INDEX IF NOT EXISTS idx_signals_sym_time ON signals (symbol, created_at)');

        // SETUPS THE ENGINE DID NOT TAKE.
        //
        // The engine only ever learned from trades it chose to fire, so it
        // could refine its judgement but never question it: a threshold set
        // too high, or a gate that stands down winners, is invisible to a
        // record made only of what got through. These are the ones it turned
        // down - a near miss on score, or a verdict a gate vetoed - carrying
        // the levels it WOULD have published, so they can be settled against
        // real price action exactly like a real signal.
        //
        // A SEPARATE TABLE, deliberately. The alternative is a flag on
        // signals, and then every member-facing query on the site needs to
        // remember to exclude it - dozens of places, forever, where one
        // forgotten AND shadow = 0 shows a customer a call the engine never
        // made. Nothing public reads this table, so nothing public can leak
        // it, and that is a property of the design rather than of my care.
        $pdo->exec("CREATE TABLE IF NOT EXISTS shadow_signals (
            id $pk,
            symbol      VARCHAR(32) NOT NULL,
            tf          VARCHAR(8) NOT NULL,
            `signal`    VARCHAR(8) NOT NULL,
            blocked_by  VARCHAR(40) NOT NULL DEFAULT '',
            score       DOUBLE PRECISION NOT NULL DEFAULT 0,
            price       DOUBLE PRECISION NOT NULL DEFAULT 0,
            atr_pct     DOUBLE PRECISION NULL,
            reasons     TEXT NOT NULL,
            indicators  TEXT NOT NULL,
            created_at  BIGINT NOT NULL,
            outcome     VARCHAR(16) NOT NULL DEFAULT '',
            outcome_note VARCHAR(64) NOT NULL DEFAULT '',
            closed_at   BIGINT NOT NULL DEFAULT 0,
            outcome_r   DOUBLE PRECISION NOT NULL DEFAULT 0
        )");
        self::index($pdo, 'CREATE INDEX IF NOT EXISTS idx_shadow_open ON shadow_signals (outcome, created_at)');
        self::index($pdo, 'CREATE INDEX IF NOT EXISTS idx_shadow_blocked ON shadow_signals (blocked_by, outcome)');
        // Grade as a column, not only inside the indicators JSON.
        //
        // It was written into that blob and read back out in PHP, which is
        // fine for one signal and useless for a question like "show only the
        // B and better ones" across a whole table - every row has to be
        // decoded before it can be discarded. Publishing by grade needs it in
        // the WHERE clause, so it lives in both places: the blob stays the
        // record of what the engine said, this is the index.
        // A PUBLIC REFERENCE FOR A SIGNAL, WHICH THE ROW ID IS NOT.
        //
        // signals.id is sequential, so quoting it in an alert, an email or a
        // support reply publishes the site's own volume: "#209" tells every
        // reader how many calls have ever been made, and two screenshots a
        // week apart tell them the rate. It is also unreadable aloud and easy
        // to mistype.
        //
        // ref is short, random and drawn from an alphabet with no 0/O/1/I, so
        // it survives being read off a phone screen into a support message.
        // Unique, because its whole job is to identify one call.
        try {
            $pdo->query('SELECT ref FROM signals LIMIT 1');
        } catch (\Throwable $e) {
            $pdo->exec("ALTER TABLE signals ADD COLUMN ref VARCHAR(16) NOT NULL DEFAULT ''");
        }
        // BACKFILL BEFORE THE INDEX, NOT AFTER.
        //
        // A unique index cannot be built over a column where every existing
        // row is the same empty string, and self::index() swallows the failure
        // - so indexing first leaves the site with a ref column that is not
        // actually unique and nothing saying so. Existing rows are given one
        // first; only then does the constraint go on.
        SignalRef::backfill($pdo);
        self::index($pdo, 'CREATE UNIQUE INDEX IF NOT EXISTS idx_signals_ref ON signals (ref)');
        try {
            $pdo->query('SELECT grade FROM signals LIMIT 1');
        } catch (\Throwable $e) {
            $pdo->exec("ALTER TABLE signals ADD COLUMN grade VARCHAR(2) NOT NULL DEFAULT ''");
            self::backfillGrades($pdo, 'signals', 'id');
        }
        self::index($pdo, 'CREATE INDEX IF NOT EXISTS idx_signals_grade ON signals (grade, created_at)');
        // THE TWO QUERIES THIS TABLE EXISTS FOR.
        //
        // The track record asks for outcome IN ('confirmed','invalid') newest
        // settled first; the dashboard and the live rows ask for outcome = ''
        // newest opened first. Neither had an index: EXPLAIN QUERY PLAN
        // answered "SCAN signals | USE TEMP B-TREE FOR ORDER BY" for both -
        // read every row in the ledger, then build a throwaway sort tree, on
        // every view of the busiest public page on the site.
        //
        // Harmless at seventy rows, which is why it survived. The ledger keeps
        // a year by default and grows with every verdict change on every coin
        // on every timeframe, so this is a page that gets slower for exactly
        // as long as the site is successful.
        self::index($pdo, 'CREATE INDEX IF NOT EXISTS idx_signals_settled ON signals (outcome, closed_at)');
        self::index($pdo, 'CREATE INDEX IF NOT EXISTS idx_signals_open ON signals (outcome, created_at)');
        // Fingerprint of the published plan: direction plus entry, stop and
        // every target. Two calls with the same fingerprint on the same pair
        // and timeframe are the same call, however many times the verdict
        // wobbled through NEUTRAL and back in between.
        try {
            $pdo->query('SELECT plan_hash FROM signals LIMIT 1');
        } catch (\Throwable $e) {
            $pdo->exec("ALTER TABLE signals ADD COLUMN plan_hash VARCHAR(32) NOT NULL DEFAULT ''");
        }
        self::index($pdo, 'CREATE INDEX IF NOT EXISTS idx_signals_plan
                           ON signals (symbol, tf, plan_hash, created_at)');
        // Outcome tracking: '' = open/pending, 'confirmed' (TP1 before SL),
        // 'invalid' (SL first or time stop), 'none' (no levels to verify).
        try {
            $pdo->query('SELECT outcome FROM signals LIMIT 1');
        } catch (\Throwable $e) {
            $pdo->exec("ALTER TABLE signals ADD COLUMN outcome VARCHAR(16) NOT NULL DEFAULT ''");
            $pdo->exec("ALTER TABLE signals ADD COLUMN outcome_note VARCHAR(60) NOT NULL DEFAULT ''");
            $pdo->exec('ALTER TABLE signals ADD COLUMN closed_at BIGINT NOT NULL DEFAULT 0');
        }
        // R-multiple achieved by each evaluated signal (profit measured in
        // units of initial risk; -1 = full stop-loss). Powers expectancy-based
        // rule tuning instead of raw hit rates.
        try {
            $pdo->query('SELECT outcome_r FROM signals LIMIT 1');
        } catch (\Throwable $e) {
            $pdo->exec('ALTER TABLE signals ADD COLUMN outcome_r DOUBLE PRECISION NOT NULL DEFAULT 0');
        }
        // Trade telemetry. A win/loss flag says a setup worked; it does not say
        // whether it worked comfortably or survived a near-stop first, how long
        // it took, or what the market cost to trade at the time. Without those,
        // no amount of learning can tell a robust edge from a lucky one.
        //
        //   mae_r / mfe_r   worst and best excursion reached, in units of the
        //                   initial risk (MAE -0.9 = the stop was nearly hit)
        //   mae_sec/mfe_sec seconds from signal to each excursion
        //   bars_held       bars the setup stayed open before it settled
        //   atr_pct         volatility at entry, ATR as a percentage of price
        //   spread_pct      top-of-book spread at entry, percentage of mid
        //   features        the full feature vector, so a future model can be
        //                   trained on inputs richer than the fired-rule set
        //                   without re-deriving them from stale candles
        try {
            $pdo->query('SELECT mae_r FROM signals LIMIT 1');
        } catch (\Throwable $e) {
            foreach (['mae_r', 'mfe_r', 'atr_pct', 'spread_pct'] as $col) {
                $pdo->exec("ALTER TABLE signals ADD COLUMN $col DOUBLE PRECISION NULL");
            }
            foreach (['mae_sec', 'mfe_sec', 'bars_held'] as $col) {
                $pdo->exec("ALTER TABLE signals ADD COLUMN $col INTEGER NULL");
            }
            $pdo->exec('ALTER TABLE signals ADD COLUMN features TEXT NULL');
        }

        // Current verdict per pair/timeframe - one row, overwritten in place.
        //
        // The signals table used to carry two jobs at once: it was the ledger
        // of actionable calls AND the place everything looked to answer "what
        // is this pair saying right now". That second job is why a NEUTRAL was
        // written every time a pair went quiet, and NEUTRAL rows outnumbered
        // BUY/SELL rows many times over - noise in the history, in the admin
        // list and in every count taken off the table.
        //
        // Splitting the two jobs lets the ledger hold only real calls while
        // the board, the hysteresis anchor and the alert dispatchers read the
        // live verdict from here. signal_id points back at the ledger row that
        // governs the pair, so the published entry/stop/targets stay frozen at
        // the values they were issued with instead of drifting every scan.
        $pdo->exec("CREATE TABLE IF NOT EXISTS signal_state (
            id $pk,
            symbol      VARCHAR(32) NOT NULL,
            tf          VARCHAR(8)  NOT NULL,
            `signal`    VARCHAR(12) NOT NULL,
            score       DOUBLE PRECISION NOT NULL DEFAULT 0,
            confidence  DOUBLE PRECISION NOT NULL DEFAULT 0,
            price       DOUBLE PRECISION NOT NULL DEFAULT 0,
            indicators  TEXT NOT NULL,
            signal_id   BIGINT NOT NULL DEFAULT 0,
            changed_at  BIGINT NOT NULL DEFAULT 0,
            updated_at  BIGINT NOT NULL DEFAULT 0,
            CONSTRAINT uq_signal_state UNIQUE (symbol, tf)
        )");
        self::index($pdo, 'CREATE INDEX IF NOT EXISTS idx_state_tf ON signal_state (tf, changed_at)');
        try {
            $pdo->query('SELECT grade FROM signal_state LIMIT 1');
        } catch (\Throwable $e) {
            $pdo->exec("ALTER TABLE signal_state ADD COLUMN grade VARCHAR(2) NOT NULL DEFAULT ''");
            self::backfillGrades($pdo, 'signal_state', 'id');
        }

        // Backfill once, from the newest stored row per pair/timeframe, so an
        // existing install has a full board the moment it upgrades instead of
        // an empty scanner until every pair has been scanned again.
        if ((int)$pdo->query('SELECT COUNT(*) FROM signal_state')->fetchColumn() === 0) {
            $latest = $pdo->query(
                "SELECT s.id, s.symbol, s.tf, s.`signal`, s.score, s.confidence, s.price,
                        s.indicators, s.created_at
                 FROM signals s
                 INNER JOIN (SELECT symbol, tf, MAX(created_at) mc FROM signals GROUP BY symbol, tf) x
                   ON x.symbol = s.symbol AND x.tf = s.tf AND x.mc = s.created_at"
            )->fetchAll();
            $ins = $pdo->prepare(
                'INSERT INTO signal_state (symbol, tf, `signal`, score, confidence, price,
                    indicators, signal_id, changed_at, updated_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?)'
            );
            $seen = [];
            foreach ($latest as $row) {
                $key = $row['symbol'] . '|' . $row['tf'];
                if (isset($seen[$key])) {
                    continue;              // ties on created_at
                }
                $seen[$key] = true;
                try {
                    $ins->execute([
                        $row['symbol'], $row['tf'], $row['signal'], (float)$row['score'],
                        (float)$row['confidence'], (float)$row['price'], (string)$row['indicators'],
                        $row['signal'] === 'NEUTRAL' ? 0 : (int)$row['id'],
                        (int)$row['created_at'], (int)$row['created_at'],
                    ]);
                } catch (\Throwable $e) {
                    // A duplicate pair cannot cost the whole migration.
                }
            }
        }

        // Who changed what, and when - see the Audit class for why this is
        // not optional on an engine that retunes its own weights.
        $pdo->exec("CREATE TABLE IF NOT EXISTS admin_log (
            id $pk,
            at         BIGINT NOT NULL,
            actor      VARCHAR(60) NOT NULL DEFAULT '',
            action     VARCHAR(40) NOT NULL DEFAULT '',
            target     VARCHAR(120) NOT NULL DEFAULT '',
            before_val VARCHAR(400) NOT NULL DEFAULT '',
            after_val  VARCHAR(400) NOT NULL DEFAULT '',
            ip         VARCHAR(45) NOT NULL DEFAULT ''
        )");
        self::index($pdo, 'CREATE INDEX IF NOT EXISTS idx_admin_log_at ON admin_log (at)');

        // What went wrong, deduplicated - see the ErrorLog class. One row per
        // distinct problem, not per occurrence: a delisted coin failing every
        // ten minutes is one thing to fix, and a transcript of it would hide
        // the other four. No index on message on purpose - this table holds
        // distinct problems, so it stays small enough to scan, and a 300-char
        // index would hit MySQL key-length limits on older row formats.
        $pdo->exec("CREATE TABLE IF NOT EXISTS error_log (
            id $pk,
            area     VARCHAR(20)  NOT NULL DEFAULT 'system',
            message  VARCHAR(300) NOT NULL DEFAULT '',
            context  VARCHAR(300) NOT NULL DEFAULT '',
            seen     INTEGER NOT NULL DEFAULT 1,
            first_at BIGINT NOT NULL DEFAULT 0,
            last_at  BIGINT NOT NULL DEFAULT 0,
            resolved INTEGER NOT NULL DEFAULT 0
        )");
        self::index($pdo, 'CREATE INDEX IF NOT EXISTS idx_error_log_last ON error_log (last_at)');

        // Alerts waiting to be sent together.
        //
        // Batching within one cron run already turned a dozen simultaneous
        // flips into one email, but flips do not arrive politely: a coin turns
        // at 12:00 and another at 12:10, and a ten-minute cron makes that two
        // emails an hour apart. Holding them for a window collects a morning's
        // worth into one message. A row per held signal rather than a blob on
        // the member, so nothing has to fit in a column and pruning is a
        // DELETE rather than a rewrite.
        $pdo->exec("CREATE TABLE IF NOT EXISTS alert_queue (
            id $pk,
            member_id INTEGER NOT NULL,
            channel   VARCHAR(10) NOT NULL DEFAULT 'email',
            symbol    VARCHAR(40) NOT NULL DEFAULT '',
            tf        VARCHAR(10) NOT NULL DEFAULT '',
            payload   TEXT NOT NULL,
            at        BIGINT NOT NULL DEFAULT 0
        )");
        self::index($pdo, 'CREATE INDEX IF NOT EXISTS idx_alert_queue_m ON alert_queue (member_id, channel, at)');

        // Every email this site tried to send, and what happened.
        //
        // A failed send used to reach error_log() and nowhere else, which on
        // shared hosting means nowhere at all - and "did my member actually
        // get that alert" had no answer short of asking them. One row per
        // attempt, with the reason when it did not go.
        $pdo->exec("CREATE TABLE IF NOT EXISTS email_log (
            id $pk,
            at        BIGINT NOT NULL DEFAULT 0,
            member_id INTEGER NOT NULL DEFAULT 0,
            recipient VARCHAR(190) NOT NULL DEFAULT '',
            kind      VARCHAR(20)  NOT NULL DEFAULT 'other',
            subject   VARCHAR(190) NOT NULL DEFAULT '',
            ok        INTEGER NOT NULL DEFAULT 0,
            reason    VARCHAR(300) NOT NULL DEFAULT '',
            context   VARCHAR(400) NOT NULL DEFAULT ''
        )");
        self::index($pdo, 'CREATE INDEX IF NOT EXISTS idx_email_log_at ON email_log (at)');

        // The tunable knowledge base powering the signal engine.
        $pdo->exec("CREATE TABLE IF NOT EXISTS ta_knowledge (
            id $pk,
            rule_key    VARCHAR(64) NOT NULL UNIQUE,
            name        VARCHAR(128) NOT NULL,
            category    VARCHAR(32)  NOT NULL,
            description TEXT NOT NULL,
            weight      DOUBLE PRECISION NOT NULL DEFAULT 1.0,
            enabled     INTEGER NOT NULL DEFAULT 1
        )");
        // Baseline weight: the tuner adjusts weight RELATIVE to this, instead
        // of compounding off its own last output (which ratcheted rules into
        // the clamps and never let them recover).
        try {
            $pdo->query('SELECT weight_base FROM ta_knowledge LIMIT 1');
        } catch (\Throwable $e) {
            $pdo->exec('ALTER TABLE ta_knowledge ADD COLUMN weight_base DOUBLE PRECISION NOT NULL DEFAULT 0');
            $pdo->exec('UPDATE ta_knowledge SET weight_base = weight WHERE weight_base = 0');
        }
        // Custom rules built in the admin rule builder carry an expression;
        // built-in rules leave it empty and are evaluated in PHP.
        try {
            $pdo->query('SELECT expr FROM ta_knowledge LIMIT 1');
        } catch (\Throwable $e) {
            $pdo->exec("ALTER TABLE ta_knowledge ADD COLUMN expr TEXT NOT NULL DEFAULT ''");
        }

        // Evidence categories. Rules inside one category are strongly
        // correlated - RSI, Stochastic, Williams %R, CCI and MFI are the same
        // oscillator information wearing different hats, and in an oversold
        // market all five fire at once. Summing them counted one piece of
        // evidence five times. `cap` bounds the net contribution a category
        // can make; `weight` scales it afterwards.
        $pdo->exec("CREATE TABLE IF NOT EXISTS ta_categories (
            category VARCHAR(32) PRIMARY KEY,
            label    VARCHAR(64) NOT NULL,
            cap      DOUBLE PRECISION NOT NULL DEFAULT 2.5,
            weight   DOUBLE PRECISION NOT NULL DEFAULT 1.0,
            enabled  INTEGER NOT NULL DEFAULT 1
        )");

        // Free-form key/value app settings editable from the admin panel.
        $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
            skey   VARCHAR(64) PRIMARY KEY,
            svalue TEXT NOT NULL
        )");

        // Confidence changed scale when it stopped being |score| rescaled by
        // the total available rule weight and became a calibrated read of six
        // dimensions. The old number was systematically tiny - a published BUY
        // routinely carried a "confidence" in the teens, because the divisor
        // was the sum of every rule in the base whether it fired or not.
        //
        // Members' alert thresholds were set against that scale by trial and
        // error, so leaving them untouched would silently make every one of
        // them far less selective and flood the people who had tuned theirs
        // most carefully. Scaling by the measured ratio between the two scales
        // (~2.3x at the actionable end) preserves roughly the selectivity each
        // member chose. It is an estimate, not a measurement of their intent,
        // so it runs once and only where a threshold was actually set.
        if (self::setting('conf_scale_migrated', '') !== '1') {
            try {
                $pdo->exec('UPDATE member_prefs SET min_confidence = MIN(95, min_confidence * 2.3)
                            WHERE min_confidence > 0');
            } catch (\Throwable $e) {
                // Pre-dates the table; nothing to rescale.
            }
            self::setSetting('conf_scale_migrated', '1');
        }

        // Expiring cache: tickers, funding rates, open interest, Fear & Greed,
        // API feed payloads and rate-limit counters. These used to be written
        // into `settings`, where they mixed volatile per-symbol junk into the
        // configuration table and grew with the watchlist.
        $pdo->exec("CREATE TABLE IF NOT EXISTS cache (
            ckey       VARCHAR(160) PRIMARY KEY,
            cvalue     TEXT NOT NULL,
            expires_at BIGINT NOT NULL
        )");
        self::index($pdo, 'CREATE INDEX IF NOT EXISTS idx_cache_expires ON cache (expires_at)');

        // Trusted news sources (RSS feeds / economic-calendar XML), admin-managed.
        $pdo->exec("CREATE TABLE IF NOT EXISTS news_sources (
            id $pk,
            name       VARCHAR(64) NOT NULL,
            kind       VARCHAR(16) NOT NULL DEFAULT 'rss',
            url        VARCHAR(255) NOT NULL UNIQUE,
            enabled    INTEGER NOT NULL DEFAULT 1,
            fetched_at BIGINT NOT NULL DEFAULT 0
        )");

        // Headlines stored on the server. Only title/link/short excerpt with
        // source attribution are kept - readers are sent to the publisher.
        $pdo->exec("CREATE TABLE IF NOT EXISTS news_items (
            id $pk,
            source_name  VARCHAR(64) NOT NULL,
            title        VARCHAR(300) NOT NULL,
            url          VARCHAR(500) NOT NULL,
            url_hash     VARCHAR(40) NOT NULL UNIQUE,
            summary      TEXT NOT NULL,
            published_at BIGINT NOT NULL,
            fetched_at   BIGINT NOT NULL
        )");
        self::index($pdo, 'CREATE INDEX IF NOT EXISTS idx_news_published ON news_items (published_at)');

        // Upcoming economic-calendar events (refreshed wholesale).
        $pdo->exec("CREATE TABLE IF NOT EXISTS calendar_events (
            id $pk,
            title      VARCHAR(200) NOT NULL,
            country    VARCHAR(12) NOT NULL,
            impact     VARCHAR(16) NOT NULL,
            event_time BIGINT NOT NULL,
            forecast   VARCHAR(40) NOT NULL DEFAULT '',
            previous   VARCHAR(40) NOT NULL DEFAULT '',
            url        VARCHAR(500) NOT NULL DEFAULT ''
        )");
        self::index($pdo, 'CREATE INDEX IF NOT EXISTS idx_events_time ON calendar_events (event_time)');

        // THE PALETTE MIGRATION, AND WHY IT CHECKS BEFORE IT WRITES.
        //
        // Changing a default only helps an install that has never stored the
        // setting. Every existing install has the old colours sitting in the
        // settings table from its own first boot, and stored beats default -
        // so without this, upgrading gets the new stylesheet and repaints it
        // back to GitHub blue on the first request.
        //
        // Only values still EQUAL to what the old palette shipped are moved.
        // An operator who deliberately picked their own brand colour set it to
        // something else, and this must not take that away from them: a
        // migration that overwrites a deliberate choice is a bug report, not
        // an upgrade. Matched case-insensitively because the sanitiser
        // lowercases on save and the seed did not.
        foreach (View::BRAND_LEGACY as $key => $legacy) {
            try {
                $stmt = $pdo->prepare('SELECT svalue FROM settings WHERE skey = ?');
                $stmt->execute([$key]);
                $stored = $stmt->fetchColumn();
                if ($stored === false || strcasecmp((string)$stored, $legacy) !== 0) {
                    continue;           // never set, or set to something chosen
                }
                $pdo->prepare('UPDATE settings SET svalue = ? WHERE skey = ?')
                    ->execute([View::BRAND_DEFAULTS[$key], $key]);
            } catch (\Throwable $e) {
                // Cosmetic. A failure here leaves the old colour in place and
                // must not stop the rest of the migration.
            }
        }

        // Stamped last, so a migration that dies part way through is retried
        // on the next request rather than being recorded as done.
        try {
            $pdo->prepare('DELETE FROM settings WHERE skey = ?')->execute(['schema_stamp']);
            $pdo->prepare('INSERT INTO settings (skey, svalue) VALUES (?, ?)')
                ->execute(['schema_stamp', $stamp]);
        } catch (\Throwable $e) {
            // Cannot record it: the only cost is running this again next time.
        }
    }

    /**
     * Every runtime setting and the value it ships with.
     *
     * This lived inline in the seeder, so the only code that could know a
     * default was the code that wrote it once, on a fresh install. Two
     * hundred and forty settings and no way to answer "what have I changed" -
     * which is why the admin screen could only ever offer all of them at once,
     * and offering all of them is the same as offering none.
     */
    public static function defaultSettings(array $config): array
    {
        return [
            'site_name'        => $config['app_name'],
            'site_tagline'     => $config['app_tagline'],
            'api_base_url'     => $config['market']['base_url'],
            // Multiple market-data endpoints (one per line, priority order).
            // api_mode: 'auto' = failover through the list; 'single' = first only.
            'api_mode'         => 'auto',
            'api_endpoints'    => "https://data-api.binance.vision\nhttps://api.binance.com\nhttps://api1.binance.com\nhttps://api2.binance.com",
            'cache_ttl'        => (string)$config['market']['cache_ttl'],
            // Divides the bar length to give each timeframe its own refresh
            // interval, with cache_ttl as the floor. 4 means a 4h series is
            // refetched hourly instead of every 5 minutes. See
            // MarketData::ttlFor() for why this is the scan's biggest cost.
            'candle_ttl_divisor' => '4',
            // Parallel candle fetching. 8 sockets open at once turns a hundred
            // serial round trips into about thirteen rounds of waiting; the
            // prefetch cap bounds how many requests one run may issue.
            'fetch_concurrency'  => '8',
            'fetch_prefetch_max' => '120',
            // Skip a rotation job whose candle has not closed since the last
            // look - see the note beside the picking loop in cron.php.
            'fullscan_bar_gate'  => '1',
            'candle_limit'     => (string)$config['market']['candle_limit'],
            // Storage retention. Candles beyond this per symbol/timeframe are
            // pruned nightly (0 = keep forever); resolved signals older than
            // the window are dropped from the audit trail.
            'candle_retention_bars' => '1500',
            'signal_retention_days' => '365',
            // How far back the self-tuning looks. These are the numbers that
            // were hard-coded before they were settings, so nothing changes
            // for an existing install until an operator moves them.
            //
            // learn_window_days is the safety net for growth: the row counts
            // are COUNTS, so the same 500 is three months on a small
            // watchlist and a couple of days on a large one. 0 keeps the old
            // behaviour (no time limit); set it and the narrower of the two
            // wins.
            'learn_window_rows' => '500',
            'learn_model_rows'  => '3000',
            'learn_window_days' => '0',
            // Data quality: detect discontinuities in cached series and repair
            // them from the exchange instead of computing across the hole.
            'auto_backfill_gaps' => '1',
            // Public API throttling. `rate_limit_scale` multiplies every
            // per-action budget for operators who need more headroom.
            'rate_limit_enabled' => '1',
            // Login throttle. Separate from the API limiter above, because an
            // operator loosening API budgets has not asked to leave the
            // password form open to a dictionary.
            'admin_idle_minutes' => '720',
            'hsts_enabled'       => '1',
            'login_attempt_limit' => '8',
            'login_attempt_window' => '900',
            'rate_limit_scale'   => '1.0',
            // Only trust X-Forwarded-For when a reverse proxy sets it, or a
            // client could rotate the header and dodge the limiter.
            'trust_proxy'        => '0',
            'default_symbol'   => $config['market']['default_symbol'],
            'default_interval' => $config['market']['default_interval'],
            'buy_threshold'    => '2.0',
            'sell_threshold'   => '-2.0',
            // Scoring mode. 'category' caps each evidence category's net vote
            // before summing, so five correlated oscillators can no longer
            // count as five independent observations. 'sum' restores the
            // original behaviour. Capping changes the scale of the score, so
            // category mode carries its own thresholds.
            'scoring_mode'     => 'category',
            'cat_buy_threshold'  => '3.0',
            'cat_sell_threshold' => '-3.0',
            'category_cap_default' => '2.5',
            // A verdict must be supported by at least this many independent
            // evidence categories.
            'min_aligned_categories' => '2',
            // Chop gate: stand down when the Choppiness Index says price is
            // going nowhere (the ADX filter only dampens weights).
            'chop_gate_enabled' => '1',
            'chop_limit'        => '61.8',
            // A+ setups are exempt from the chop gate by default - the reasoning
            // was that a setup strong enough to earn the top grade should
            // survive a choppy read. That was never measured against this
            // install's own settled A+ signals until Outcomes::chopExemptionStats()
            // existed to check; turn this off if the evidence in Engine lab
            // says the exemption is costing more than it is earning.
            'chop_gate_a_plus_exempt' => '1',
            // Recursive higher-timeframe pass: re-run the full rule set one
            // timeframe up and vote with its verdict.
            'htf_engine_enabled' => '1',
            // Entry tolerance published with every plan, in ATR.
            'entry_zone_atr'   => '0.25',
            // Estimated liquidation clusters: keeps stops out of the path of
            // a cascade and names the magnet a move is reaching for.
            'liq_clusters_enabled' => '1',
            'liq_lookback_bars'    => '240',
            'liq_leverages'        => '10,25,50,100',
            'liq_stop_min_weight'  => '0.5',
            'news_enabled'     => '1',
            'news_cache_ttl'   => '300',
            // Wall-clock budget for one refreshIfStale() sweep. Every enabled
            // source gets its own 12s/6s curl timeout with no combined cap
            // before this existed, so a handful of slow or unreachable feeds
            // could burn 30-60+ seconds sequentially - on cron.php that ran
            // BEFORE the signal scan's own time budget even started counting,
            // so a host with a modest max_execution_time (or a plain wall-clock
            // limit on the cron job itself) could be killed here and never
            // reach the scan loop at all: every run "ran", nothing was ever
            // analysed, and nothing in the error log said why. On api.php's
            // on-demand lazy refresh the same unbounded loop sat in the
            // request path of an ordinary page view. A source not reached
            // within the budget is simply due again next call - nothing is
            // lost, only deferred, exactly like the scan loop below.
            'news_fetch_max_seconds' => '20',
            'news_max_items'   => '30',
            'news_retention_days' => '30',
            'news_poll_seconds'=> '90',
            'signal_auto_seconds' => '60',
            'alerts_enabled'   => '1',
            // Bulk "watch all my coins" alerts: minimum tier + pair cap.
            'bulk_watch_enabled' => '1',
            'bulk_watch_tier'  => 'paid',   // paid | free | any
            // Max watched PAIRS for that tier - 0 is no limit, and that is the
            // shipped default now. 60 sounds generous and is not: a bulk watch
            // is coins times timeframes, so three ticked frames turned it into
            // twenty coins, silently. An operator who wants a ceiling can set
            // one; starting with a low one that nobody chose only teaches them
            // the feature is broken.
            'bulk_watch_max'   => '0',
            // Watchlist size per audience, in PAIRS (coin x timeframe).
            //
            // The free and guest figures are 12 because that is the number
            // that was hard-coded in api.php before this was settable - a new
            // install behaves exactly as it did, and an operator who wants a
            // different split now has somewhere to say so. Premium inherits
            // bulk_watch_max, which is 0 (no limit): what people are paying
            // for should not have a ceiling nobody chose.
            // "Watch the coins that are working" - one tap, ranked from the
            // verified record. The sample floor is 10 because a coin with
            // three settled trades and three wins is a 100% win rate that
            // means nothing, and without a floor those coins take every top
            // slot: the feature would recommend exactly the coins there is
            // least reason to trust.
            'top_watch_enabled' => '1',
            'top_watch_tier'   => 'paid',   // paid | free | any
            'top_watch_count'  => '10',
            'top_watch_min'    => '10',
            // The upgrade prompt. Shown to free members only, once when the
            // account is new and again after upsell_popup_days, and not again
            // for upsell_popup_repeat days after it is closed. The repeat
            // window is a fortnight because the failure mode of a prompt is
            // not "too few people saw it", it is "everyone learned to close
            // it without reading" - and that damage does not undo.
            'upsell_popup_enabled' => '1',
            'upsell_popup_new'  => '1',
            'upsell_popup_days' => '3',
            'upsell_popup_repeat' => '14',
            // Discount campaign. Off until an operator configures one - a
            // promo that ships on by default would advertise a coupon nobody
            // created. The percentage and expiry are NOT here: they live on
            // the coupon row, so the banner and the checkout cannot disagree.
            // Feature gates. The keys that already existed
            // (member_backtest_tier, ai_ask_tier, bulk_watch_tier,
            // top_watch_tier) keep their own defaults elsewhere in this list
            // so an operator's existing choice survives; only the new gates
            // are declared here.
            // Whether a member may tighten the site's minimum grade for
            // their own alerts. Off: the operator's floor is the only rule.
            // It has never been possible to LOOSEN it - see
            // MemberPrefs::alertAllowed().
            'member_grade_filter' => '0',
            // Whether members may run the engine on their own thresholds.
            // This one can LOOSEN what they see on the chart, so it is off
            // until an operator decides otherwise. Never affects alerts or
            // anything stored - see MemberPrefs::engineProfile().
            'member_engine_profile' => '0',
            'gate_scanner'    => 'paid',
            'gate_portfolio'  => 'free',
            'gate_mtf'        => 'paid',
            'gate_telegram'   => 'paid',
            'gate_api_feed'   => 'paid',
            // Whether members may type an activation key to turn on premium.
            // On by default: an operator who never generates a key has no keys
            // to redeem, so the form simply never appears - see Redeem.
            'redeem_enabled'  => '1',
            'promo_enabled'   => '0',
            'promo_coupon'    => '',
            'promo_headline'  => '',
            'promo_body'      => '',
            'promo_starts'    => '0',
            'promo_ends'      => '0',
            'promo_audience'  => 'free',  // all | free | paid | expired | new
            'promo_new_days'  => '7',
            'promo_emails'    => '',
            'promo_random_pct' => '100',
            'promo_banner'    => '1',
            'promo_popup'     => '1',
            'promo_timer'     => '1',
            'promo_repeat_hours' => '48',
            // Trade levels (entry/SL/TP) - ATR multipliers, admin-tunable.
            'levels_enabled'   => '1',
            // MEASURED, NOT CHOSEN. See mig_geometry_wide_stop below.
            //
            // These were 1.5 and 1.0 - round numbers nobody had tested, and
            // they set the entire risk/reward profile of every trade the site
            // publishes. Searched across 32 geometries and six coins on two
            // timeframes, both independently picked stop 2.5 / TP1 1.5:
            //
            //   1h   +0.0603R over 272 trades, 72.8% win, payoff 0.45 (need 0.37)
            //   4h   +0.0698R over 287 trades, 70.7% win, payoff 0.51 (need 0.41)
            //
            // The old pair did not place in the top eight on either. Its
            // nearest relative on 1h, 1.5/1.5/2, came in NEGATIVE.
            //
            // A wider stop with a further first target is the whole fix for
            // the payoff problem: this engine was winning two trades in three
            // and losing money because it banked small and lost full. Fewer
            // stop-outs on noise, and a first target far enough away to be
            // worth reaching.
            'sl_atr_mult'      => '2.5',
            'tp1_atr_mult'     => '1.5',
            'tp2_atr_mult'     => '2.0',
            'tp3_atr_mult'     => '3.5',
            'time_stop_bars'   => '24',
            'breakeven_after_tp1' => '1',
            // How much of the position comes off at TP1. This is what every
            // published R is measured against, so the plan on the signal card
            // and the plan in the settlement walker are the same number:
            //   50  take half at TP1, let the rest run to TP2 (default)
            //   0   hold everything for TP2
            //   100 take it all at TP1 and ignore TP2
            'tp1_partial_pct'  => '50',
            'tp2_partial_pct'  => '30',
            // Accuracy: ADX regime filter + minimum grade for push/email alerts
            'regime_filter_enabled' => '1',
            // Accuracy: analyse closed candles only (no repaint from the
            // still-forming bar).
            'closed_candle_only' => '1',
            // Self-learning: daily automatic rule-weight tuning from verified
            // outcomes (manual button in Knowledge base still works).
            'autotune_enabled' => '1',
            'autotune_min_samples' => '10',
            // Calibrated confidence: show the measured historical win rate of
            // similar-strength signals instead of the model heuristic.
            'calibrated_confidence' => '1',
            'confidence_calib' => '{}',
            // Real win rate per letter grade (A+/A/B/C), built alongside the
            // score/quality calibrations above so grade - the thing the board
            // sorts by - is checked against outcomes too, not just asserted
            // from the rules that produced it. See Outcomes::buildGradeCalibration().
            'grade_calib' => '{}',
            // A calibration bucket needs real evidence before its number is
            // published as "measured", and the figure shown is the Wilson
            // lower bound rather than the raw sample proportion.
            'calib_min_samples' => '30',
            'confidence_conservative' => '1',
            // Trading costs subtracted from every measured R. Published
            // results used to be gross, flattering the engine by a fixed
            // amount per trade.
            'round_trip_cost_pct' => '0.1',
            // The most trading costs may take of a setup's risk before it is
            // refused. A stop is a distance and so is a round trip: when the
            // stop is the tighter of the two, fees are worth more than
            // everything being risked and no price move makes the trade pay.
            // Measured on a real 3m signal here - stop 0.069% away, 0.1%
            // round trip, costs at 145% of risk, published as "1 : 1.3" and
            // settled at -2.45R. Set to 0 to publish them anyway.
            'max_cost_r' => '0.33',
            // Two refusals a veteran makes and an indicator never does.
            //
            //   target_min_reach  the target must be somewhere this
            //                     instrument actually goes inside the time
            //                     stop, measured on its own history. 0 off.
            //   extension_max_atr do not START a trade this far from the
            //                     20-EMA in the direction of the stretch -
            //                     the move being joined has mostly happened.
            'target_min_reach' => '0.35',
            //   score_band_gate  refuse a |score| band this install's own
            //                    settled record says loses money. The score
            //                    threshold is a floor and there was no
            //                    ceiling, so a band measured at 30% was still
            //                    published - as the strongest calls on the
            //                    site, because it scored highest. Off until
            //                    an operator looks at their own band table:
            //                    it is a real refusal, not a display change.
            'score_band_gate' => '0',
            'score_band_min_n' => '30',
            'score_band_min_winrate' => '45',
            'score_band_max_r' => '0',
            //   preflight  the last look before a call is published: is the
            //              data sound, and does the plan hold together. Not
            //              another market opinion - the gates above are that.
            //              Data quality warns; an incoherent plan (stop on the
            //              wrong side, targets out of order, no target at all)
            //              is refused, because there is no reading of that
            //              worth sending to a paying member.
            'preflight_enabled' => '1',
            'preflight_max_stale_bars' => '3',
            'preflight_max_gap_pct' => '2',
            //   smc_structure_max_age  how long an order block, breaker or
            //              equal-highs shelf stays live, in bars. BOS, CHoCH,
            //              sweeps and gaps were all bounded; these three were
            //              not, so a level from three hundred bars ago still
            //              cast a full-weight vote.
            'smc_structure_max_age' => '60',
            //   preflight_max_travelled_pct  refuse a call when price has
            //              already covered this much of the way to its first
            //              target. 0 is off: it is a real refusal, and an
            //              operator should turn it on knowing the trade-off -
            //              fewer signals, and the ones left still have their
            //              advertised reward in front of them.
            'preflight_max_travelled_pct' => '0',
            //   min_rr  the lowest of the three signal types this site
            //              publishes: 1 for 1:1 and better (all of them), 2 to
            //              drop the 1:1s, 3 for 1:3 only. Targets always stand
            //              at one, two and three times the risk; the type is
            //              the furthest of those the pair's own record says it
            //              reaches inside the time stop. Every other gate asks
            //              whether a setup is likely to work - this one asks
            //              how much it pays if it does.
            'min_rr' => '1',
            //   trial_*  the free trial a new account gets. Off by default:
            //              an upgrade must never start giving premium away on
            //              a site that did not ask for it. days is the length,
            //              and the three limits are what stop one person
            //              taking it repeatedly - see Trial.
            'trial_enabled' => '0',
            'trial_days' => '3',
            'trial_max_per_ip' => '1',
            'trial_ip_days' => '30',
            'trial_device_check' => '1',
            //   onchain_*  Bitcoin chain settlement, from free keyless feeds.
            //              NOT a directional signal and deliberately not a
            //              vote: telling an exchange deposit from a withdrawal
            //              needs labelled addresses, which is the thing the
            //              paid providers sell. What free data supports is
            //              "unusual value is moving", which is a caution, so
            //              that is all it is used for. Off by default and it
            //              cannot be backtested - these feeds serve today, not
            //              a history to replay.
            'onchain_enabled' => '0',
            'onchain_surge_ratio' => '1.8',
            'onchain_caution' => '0',
            'onchain_volume_url' => '',
            'onchain_mempool_url' => '',
            'extension_gate_enabled' => '1',
            'extension_max_atr' => '2.5',
            // Resolve candles that span both stop and target by replaying a
            // finer timeframe instead of always assuming the stop came first.
            'subcandle_resolution' => '1',
            // Record the top-of-book spread with every actionable signal. One
            // extra cached request per symbol; turn it off on a host where
            // that is too expensive and the field is simply left empty.
            'record_spread' => '1',
            // Multi-timeframe ladder. The engine reads every frame above the
            // one being analysed and weights the higher ones more heavily.
            //   mtf_min_bias   ladder must lean this hard before it votes
            //   mtf_gate_bias  the ladder starts charging counter-trend calls
            //                  extra score once it leans this hard
            //   mtf_penalty    how much extra, at a unanimous ladder (0.6 =
            //                  the threshold is 60% higher against the trend)
            //   mtf_gate_hard  a ladder this clean is never faded at all
            //   mtf_reversal_* the evidence a counter-trend setup must show
            'mtf_gate_enabled' => '1',
            'mtf_min_bias' => '0.25',
            'mtf_gate_bias' => '0.35',
            'mtf_penalty' => '0.6',
            'mtf_gate_hard' => '0.85',
            'mtf_reversal_min_n' => '25',
            'mtf_reversal_min_r' => '0.15',
            // Smart-money structures. Read as context, never as calls:
            //   smc_fresh_bars  a break older than this is history
            //   smc_near_atr    how close price must be to a block or breaker
            //                   before it is treated as being at it
            'smc_enabled' => '1',
            'smc_fresh_bars' => '6',
            'smc_near_atr' => '0.5',
            // Two-axis regime detection: whether price is going anywhere, and
            // how hard it is moving while it does. Drives thresholds, stop and
            // target distances, required confirmations and the time stop, all
            // scaled down towards no change when the reading is borderline.
            'regime_detect_enabled' => '1',
            // The self-tuning loop: propose changes from the measured record,
            // validate them against history, apply one at a time, then judge
            // each against what actually happened and roll back failures.
            //   adapt_min_live  verified signals needed after a change before
            //                   its drift check is allowed to have an opinion
            'adapt_enabled' => '1',
            'adapt_min_live' => '40',
            'adapt_tf' => '1h',
            'adapt_report' => '',
            'adapt_history' => '',
            // Validate weight tuning on a hold-out slice before keeping it.
            'walk_forward_enabled' => '1',
            'autotune_report' => '',
            // Logistic model fitted on this install's verified outcomes.
            'learned_model_enabled' => '1',
            'learned_model' => '',
            'learned_model_report' => '',
            // How hard the model's raw probability is pulled back toward the
            // base rate, scaled by how many out-of-sample trades validated it
            // (see LearnedModel::predict()). The calibrated-confidence and
            // legacy-score tiers both publish a Wilson lower bound so a thin
            // sample cannot boast; the model tier used to override both of them
            // with an unshrunk point estimate. Same k-shape as sym_learn_k:
            // at k trades of validation the model is trusted half-way.
            'learned_model_shrink_k' => '150',
            'time_buckets' => '',
            // Opt-in: suppress signals in hours whose measured expectancy on
            // this install is clearly negative. Off by default because it
            // needs a real sample before it means anything.
            'session_filter_enabled' => '0',
            'session_filter_min_n' => '20',
            'session_filter_min_r' => '-0.15',
            // Weekly automatic backtest re-calibration (cron).
            // Backtests now attach a market-data source so the higher-timeframe
            // and BTC-regime rules participate (served as of each simulated
            // bar). Without it those rules never fired and every backtest
            // measured a different engine from the live one.
            'backtest_use_mtf' => '1',
            // Learned per-timeframe ATR multipliers from the level optimiser.
            'tf_levels_enabled' => '1',
            'tf_levels' => '{}',
            'level_opt_report' => '',
            'ab_report' => '',
            'auto_backtest_enabled' => '1',
            'auto_backtest_tfs' => '1h,4h,1d',
            'auto_backtest_last' => '0',
            // Telegram alerts: admin pastes a @BotFather token; members link
            // their chat with one tap. Uses the same watched pairs as email.
            // Daily email digest for alert members (cron; hour is UTC).
            'digest_enabled' => '0',
            'digest_hour' => '8',
            'digest_last' => '',
            // Signals feed API for premium members (token-authenticated).
            'api_feed_enabled' => '1',
            // Full-market background scan: cron rotates through every enabled
            // coin on these timeframes, a capped batch per run, even with no
            // watchers - fills signal history / track record for all coins.
            //
            // On by default, because the scanner presents stored verdicts as
            // the current state of the market. With the scan off, a pair is
            // only re-read when somebody opens its chart, so the board keeps
            // showing a call from hours ago while the chart shows something
            // else - the same pair answering two ways at the same moment.
            // Batched, so the cost per cron run stays bounded whatever the
            // watchlist size.
            'fullscan_enabled' => '1',
            'fullscan_tfs' => '15m,1h,4h',
            'fullscan_batch' => '10',
            'fullscan_pos' => '0',
            // How often the host actually runs cron.php, for turning "10 coins
            // per run" into "the whole list every 45 minutes" - the number an
            // operator is really choosing. 0 means use the interval cron.php
            // measures between its own runs, which is right without anyone
            // being asked and stays right when the schedule changes.
            'cron_interval_min' => '0',
            // Seconds a single run may spend on the background scan. 0 works
            // it out from the host's max_execution_time. This, not a batch
            // ceiling, is what keeps a run inside what the server allows.
            'fullscan_max_seconds' => '0',
            // Runs in a row a coin may fail before it is taken out of the scan
            // rotation. An imported watchlist rots - coins are delisted, quote
            // assets retired - and each dead one costs a slot in every cycle.
            // 0 keeps failing coins in the rotation forever.
            'scan_autodisable_fails' => '3',
            // Bars kept for a coin that is NOT in the scan rotation. The deep
            // window is for the backtester; a coin nothing backtests only ever
            // needs what the engine reads to draw a chart and score it.
            'candle_retention_idle_bars' => '320',
            // How long the change log is kept. 0 keeps everything.
            'audit_retention_days' => '180',
            // How long a problem stays in the error log after its last
            // occurrence. Short on purpose: this list is a to-do, not a
            // history, and a fault nobody has seen for a month is either
            // fixed or gone. 0 keeps everything.
            'error_retention_days' => '30',
            // How long the record of sent mail is kept.
            'email_log_days'   => '30',
            // NEUTRAL verdicts are the engine saying "no trade here". They are
            // kept in signal_state, where the board reads them; writing them
            // to the signals ledger as well buried the actionable calls. Set
            // to 1 only if an audit needs every quiet period on record.
            'store_neutral' => '0',
            // Track-record tiers: the settled log is the public record, the
            // live plans and the per-coin ranking are the product. Gate
            // carries the same defaults; seeded so the settings table
            // agrees with the code rather than being silently empty.
            'perf_trades_tier' => 'any',
            'perf_coins_tier'  => 'paid',
            'live_levels_tier' => 'paid',
            // Tiered limits: taken FROM Limits::LIMITS, not copied beside it.
            //
            // These were written out here as well, and watch_max_* a third
            // time further up this same array. Limits::forTier() reads the
            // setting with its own 'def' as the fallback - so the seeded row
            // won every time and the table that calls itself the definition
            // was decoration. Raising a default there changed nothing, which
            // is the worst kind of duplicate: one that looks authoritative and
            // is not. Spread in, so there is one place to change and no way
            // for the two to disagree.
            ...self::limitDefaults(),
            'tg_enabled' => '0',
            'tg_bot_token' => '',
            'tg_bot_username' => '',
            'tg_updates_offset' => '0',
            // Hysteresis: hold an active BUY/SELL until the score falls below
            // this fraction of the entry threshold (0 = flip instantly).
            'flip_exit_band' => '0.5',
            // Cooldown: after a stop-loss hit, skip same-direction signals on
            // that pair/timeframe for this many bars.
            'cooldown_bars' => '4',
            // How far price must travel before the SAME direction on the same
            // pair and timeframe counts as a new trade rather than the open one
            // restated, measured in units of the open plan's own risk. 1.0
            // means a full stop-width away. Guards against one idea being
            // published, verified and counted several times over.
            'reissue_min_risk' => '1.0',
            // Caution window around high-impact calendar events: signals need
            // a stronger score and the setup grade is capped at B.
            'event_caution_enabled' => '1',
            'event_caution_hours' => '2',
            // Flash-move circuit breaker. Cron watches BTC's last closed hour;
            // a move this size arms a caution window during which every pair
            // needs a stronger score and cannot be graded A. panic_until is
            // the armed-until timestamp, written by cron, not by hand.
            'panic_enabled' => '1',
            'panic_move_pct' => '4',
            'panic_hold_min' => '90',
            'panic_until' => '0',
            // Rule quarantine: a rule with persistently negative expectancy
            // stops voting for a probation period instead of merely having its
            // weight tuned down, then is released to earn its place back.
            // Taker flow and market breadth. Both are confirmation reads: they
            // say whether the rest of the market agrees with what this chart
            // is doing, which no single-pair indicator can see.
            // The market-context panel on the chart page: the same reads the
            // engine scores, shown to the reader so a verdict is not a number
            // out of nowhere.
            'context_panel_enabled' => '1',
            // One verdict per timeframe on the chart page.
            'mtf_strip_enabled' => '1',
            'taker_enabled' => '1',
            'taker_extreme' => '1.25',
            'breadth_enabled' => '1',
            'breadth_bull' => '65',
            'breadth_bear' => '35',
            // How much the Bitcoin-regime rule should count for a coin that
            // does not actually move with Bitcoin. See SignalEngine: the vote
            // is scaled by the measured correlation, and skipped below the
            // floor rather than fired at full strength on a gold-backed token.
            'btc_corr_enabled' => '1',
            'btc_corr_min' => '0.2',
            'btc_corr_bars' => '120',
            // Performance watchdog: when recent expectancy turns negative the
            // weekly recalibration is brought forward instead of waiting.
            'watchdog_enabled' => '1',
            'watchdog_window' => '30',
            'watchdog_floor_r' => '-0.05',
            'watchdog_last' => '0',
            'quarantine_enabled' => '1',
            'quarantined_rules' => '[]',
            'quarantine_days' => '14',
            'quarantine_min_samples' => '30',
            'quarantine_min_r' => '-0.1',
            // Market-positioning inputs (both free, no API key).
            'funding_enabled' => '1',
            'funding_api_url' => 'https://fapi.binance.com',
            // Extra futures hosts (one per line) tried in order when the
            // primary is geo-blocked - it answers HTTP 451 in several regions,
            // which silently disables funding, open interest and long/short.
            'futures_endpoints' => '',
            // Non-crypto instruments. The engine reads OHLCV and knows nothing
            // about what produced it, so equities, FX and indices need only a
            // data adapter. Symbols are namespaced (stooq:aapl.us) and the
            // crypto-only rules sit out automatically.
            'stooq_endpoint' => 'https://stooq.com',
            'twelvedata_endpoint' => 'https://api.twelvedata.com',
            'twelvedata_key' => '',
            // Member-facing backtester.
            'member_backtest_enabled' => '1',
            'member_backtest_tier' => 'paid',
            // Open interest confirmation: price up on rising OI is new money;
            // price up on falling OI is short covering. Threshold is a percent
            // change over the recent window.
            'oi_enabled' => '1',
            'oi_change_pct' => '1.5',
            // Liquidity gate: skip signals on pairs thinner than this 24h quote
            // volume, where the take-profit is not reachable without slippage.
            'min_quote_volume' => '250000',
            // Order-book imbalance rule threshold (0-1).
            'book_enabled' => '1',
            'book_imbalance' => '0.25',
            'funding_extreme_pct' => '0.05',
            'fng_enabled' => '1',
            // Per-timeframe rule multipliers learned from outcomes/backtests
            // (JSON: {tf: {rule_key: mult}}); cleared from the Knowledge base.
            'tf_rule_mult' => '{}',
            // How much a timeframe's own per-rule record is allowed to speak,
            // same n/(n+k) shrinkage sym_learn_k already uses for per-coin
            // multipliers (see Outcomes::evidenceWeight()). Per-timeframe
            // multipliers used to jump straight to the full measured value the
            // moment a rule cleared the sample floor - a cliff, not a curve -
            // while the per-coin multipliers right below already had this.
            'tf_learn_k' => '40',
            // Per-coin learning, shrunk toward the global weight. sym_learn_k
            // is the half-way point: at k trades a coin's own record carries
            // half the vote. Higher = more cautious.
            // Where the per-coin learned multipliers used to live. Kept so an
            // install upgrading from before symbol_learning has something for
            // the migration to read; blanked once it has run, and written by
            // nothing since. See SymbolLearning.
            'sym_rule_mult' => '{}',
            'sym_learn_enabled' => '1',
            // auto = blended by evidence, coin = the coin's own record whole,
            // global = shared weights only. See Outcomes::symLearnMode().
            'sym_learn_mode' => 'auto',
            'sym_learn_k' => '40',
            'sym_learn_report' => '',
            // Shadow signals: setups the engine turned down, settled anyway so
            // the learning can see what it passed on. shadow_near_band is how
            // close to the threshold a score must come to be worth recording.
            'shadow_enabled'     => '1',
            'shadow_near_band'   => '0.6',
            'shadow_learn'       => '1',
            'shadow_retention_days' => '120',
            'alert_min_grade'  => 'A',     // any | B | A | A+ - A and A+ by default
            // Alert on the first sighting of a newly-watched pair that already
            // has a live call, instead of silently recording it as "seen".
            'alert_on_subscribe' => '1',
            // Hours an identical plan is treated as the same call rather than
            // a new one. 0 turns the guard off.
            'dupe_window_hours' => '24',
            // Which blocks of the public track record are published. The list
            // of trades is the record; the rest is commentary on it.
            'perf_show_stats'      => '1',
            'perf_show_breakdowns' => '1',
            'perf_show_trades'     => '1',
            'perf_page_size'       => '50',
            'perf_wide_table'      => '1',
            // Profit factor and the Heat/Best/Bars columns. On by default:
            // every one of these is already recorded on the signal, so
            // publishing them costs nothing, and Heat is the figure that says
            // whether a trade was holdable rather than merely profitable in
            // hindsight.
            //
            // perf_layout and perf_default_pane used to sit here, choosing
            // between a tabbed page and one long one. The track record is one
            // page again - see the note at the top of performance.php - so
            // both are gone. Rows left behind in an existing settings table
            // are simply never read.
            'perf_detail'          => '1',
            // perf_coin_min, perf_coin_thin and perf_coin_page used to sit
            // here, sizing the per-coin table on the track record page. That
            // table moved to the scanner (see performance.php and
            // scanner.php) and does not paginate or mark thin rows the way
            // the old one did, so all three are gone. Rows left behind in an
            // existing settings table are simply never read.
            // 0 = every row that passes the filters. Both of these tables are
            // bounded by real things - the coin list, and the retention window
            // on the ledger - so a made-up ceiling only ever hid rows.
            'scanner_rows'         => '0',
            'admin_signals_per'    => '25',
            // How many signals get their full plan in one batched email before
            // the rest are listed compactly. Guards Gmail's ~102KB clip.
            'alert_email_max_detail' => '25',
            // The publish floor: what the SITE shows, as opposed to what it
            // interrupts people for. 'any' so an upgrade hides nothing.
            'show_min_grade'   => 'A',     // any | B | A | A+ - A and A+ by default
            // Several watched pairs flipping in one run is one email, not one
            // per pair. A member watching all their coins can trip a dozen at
            // once, and a dozen messages in a minute is indistinguishable from
            // spam - to the reader and to their mail provider.
            // Both www and non-www serve the site. 'www' or 'nonwww' picks a
            // single canonical spelling once the operator knows which one
            // their certificate covers - a 301 to a host with no certificate
            // is worse than no 301 at all.
            'canonical_host'   => 'both',
            // /charts instead of /charts.php. On by default: the shipped
            // .htaccess handles it on any ordinary Apache install, and the
            // filter refuses to run once the self-test has POSITIVELY found
            // that this server does not rewrite - so the failure mode is
            // "links keep their .php", not "every link 404s".
            'clean_urls'       => '1',
            'alert_email_batch' => '1',
            'alert_tg_batch'   => '1',
            // Minutes to hold new alerts so they can go out together. 0 sends
            // each cron run, which already bundles whatever flipped in that
            // run; a window bundles across runs as well, which is the only way
            // "all my coins in one email" can be true when the coins do not
            // flip at the same minute.
            'alert_bundle_min' => '0',
            // Member-controlled outbound webhooks on signal flips.
            'webhooks_enabled' => '1',
            // Optional language-model layer. Off by default and never part of
            // the decision: it describes the analysis, it does not influence
            // it, or the track record would stop being reproducible.
            'ai_enabled'   => '0',
            'ai_endpoint'  => 'https://api.openai.com/v1/chat/completions',
            'ai_api_key'   => '',
            'ai_model'     => 'gpt-4o-mini',
            'ai_cache_ttl' => '3600',
            'ai_ask_tier'  => 'paid',   // who may ask follow-up questions
            // Public broadcast channels (discovery, not just notification).
            'broadcast_enabled' => '0',
            'broadcast_tg_chat' => '',
            'broadcast_discord_url' => '',
            'broadcast_min_grade' => 'A',
            // Referrals: members get a code, credited on a first payment.
            'referral_enabled' => '0',
            'referral_days'    => '7',
            // Paper portfolio: settle open positions from cron.
            'paper_enabled' => '1',
            // Receipts for paper trades: opened, and closed with the money.
            // Costs one email per trade per member, so it is the operator's
            // dial as well as the member's.
            'trade_email_enabled' => '1',
            // The ceiling and the ladder for simulated leverage. Read in one
            // place, Paper::maxLeverage() and Paper::leverageLadder(), rather
            // than being a 125 hardcoded in two files and a list of <option>s
            // in a third.
            'paper_max_leverage' => '125',
            'paper_leverages' => \SignalMasterAi\Paper::LEVERAGE_LADDER_DEFAULT,
            'registration_enabled' => '1',
            // Registration email verification. On by default - an address
            // nobody proved is an alert that never arrives and a complaint
            // that lands on this install's sending domain. It stands itself
            // down automatically while no mailer is configured.
            'email_otp'      => '1',
            'otp_ttl_min'    => '15',
            'otp_tries'      => '5',
            'otp_resend_sec' => '60',
            // Branding & SEO (all editable in Admin > Settings).
            'custom_logo'      => '',   // uploaded logo filename; '' = default
            'seo_title'        => '',   // '' = "site name - tagline"
            'meta_description' => '',   // '' = tagline-based
            'meta_keywords'    => '',
            // Trust & legal (Admin > Settings > Legal). The track-record page
            // shows every verified signal - wins AND losses - publicly.
            'performance_page_enabled' => '1',
            'terms_content' => "1. This website provides automated, educational chart analysis and trading signals. Nothing on this site is financial, investment or trading advice, and no outcome is guaranteed.\n\n2. Signals are generated by software from public market data. They can be wrong. You alone are responsible for any trading decision you make.\n\n3. Paid plans unlock additional coins and features for the purchased period. Access ends when the period expires. Payments for digital access are non-refundable once access has been granted, unless required by law.\n\n4. You may not resell, scrape or redistribute the signals or content of this site without written permission.\n\n5. Accounts used for abuse (automated scraping, payment fraud, sharing credentials) may be suspended without refund.\n\n6. This service is provided \"as is\" without warranties of any kind. To the maximum extent permitted by law, the operator is not liable for any losses arising from use of this site.",
            'privacy_content' => "What we store: your email address and a securely hashed password when you register; your chosen alert preferences (coins and timeframes); payment records for plans you purchase (plan, amount, status - card/wallet details are handled by the payment provider, never by this site); and, if you enable push notifications, the anonymous push subscription your browser issues.\n\nWhat we do NOT do: we do not sell or share your personal data, we do not run third-party advertising or tracking scripts, and we do not store card numbers or wallet private keys.\n\nCookies: only functional session cookies (login) and local browser storage for your own preferences (favourites, alert list).\n\nEmails: sent only for what you enabled (signal alerts) - disable them any time in the alerts box.\n\nDeletion: contact the site operator to have your account and its data removed.",
            'risk_content' => "Trading cryptocurrencies involves substantial risk of loss and is not suitable for everyone. Prices are extremely volatile and can move against you faster than any signal can update.\n\nThe signals on this site are produced automatically by software analysing historical and live market data. Past performance - including the published track record - does NOT predict future results.\n\nNever trade with money you cannot afford to lose. Consider your experience, objectives and financial situation, and seek advice from a licensed professional before making investment decisions.\n\nBy using this site you accept that all trading decisions are yours alone and the operator accepts no liability for trading losses.",
            // Landing page (admin-controllable; disable to serve charts directly)
            'landing_enabled'  => '1',
            'hero_title'       => 'AI-Powered Signals, Smarter Trading',
            'hero_subtitle'    => 'AI reads market trends, momentum and volatility, then turns them into live BUY/SELL signals with a full trade plan — entry, stop loss and profit targets. It learns from every result, so it keeps getting sharper over time.',
            'hero_cta'         => 'View live signals',
            'cron_token'       => bin2hex(random_bytes(16)),
            // Automatic payment gateways (Admin > Billing).
            'cryptomus_merchant_uuid' => '',
            'cryptomus_api_key'       => '',
            'gw_coingate_enabled'     => '0',
            'gw_coingate_api_key'     => '',
            'gw_nowpayments_enabled'  => '0',
            'gw_nowpayments_api_key'  => '',
            'gw_nowpayments_ipn_secret' => '',
            'gw_coinbase_enabled'     => '0',
            'gw_coinbase_api_key'     => '',
            'gw_coinbase_webhook_secret' => '',
            'gw_btcpay_enabled'       => '0',
            'gw_btcpay_host'          => '',
            'gw_btcpay_store_id'      => '',
            'gw_btcpay_api_key'       => '',
            'gw_btcpay_webhook_secret'=> '',
            // Off by default: a BTCPay address inside this server's own network
            // is refused unless the operator says that is really where it is.
            'gw_btcpay_allow_private' => '0',
            'last_dispatch'           => '0',
            // Email / SMTP (installer step; editable in Admin > Settings).
            'smtp_mode'       => 'off',      // off | smtp | phpmail
            'smtp_host'       => '',
            'smtp_port'       => '587',
            'smtp_security'   => 'tls',      // tls | ssl | none
            'smtp_user'       => '',
            'smtp_pass'       => '',
            'smtp_from_email' => '',
            'smtp_from_name'  => '',
            // Appearance - all themeable from Admin > Settings.
            //
            // These are the palette's own values, taken from one constant so
            // the seed cannot drift from the stylesheet again. They were the
            // previous palette's, and because the head writes them into a
            // <style> AFTER style.css, every install repainted the design
            // system back to the colours it had replaced.
            'accent_color'     => View::BRAND_DEFAULTS['accent_color'],
            'up_color'         => View::BRAND_DEFAULTS['up_color'],
            'down_color'       => View::BRAND_DEFAULTS['down_color'],
            // Load Instrument Serif / Inter Tight / JetBrains Mono from
            // Google Fonts. Off = the fallback chain in style.css, which is
            // chosen to be correct on its own, and no external request.
            'webfonts'         => '1',
            // 5m is off by default now, and 1m/3m never were. Measured on
            // this install's own replayed history, after costs: 3m -0.133R,
            // 5m -0.162R, 15m -0.092R, 1h +0.080R. The fast frames win MORE
            // often - 73% on 5m - and still lose money, because the wins are
            // small, the losses are full size and the stop sits close enough
            // to the fee cost that the arithmetic cannot work. An admin who
            // wants them back can tick them in Settings > Market data.
            'enabled_intervals'=> '15m,1h,4h,1d,1w',
            'footer_text'      => 'delivers automatic BUY / SELL signals from live chart analysis - with entry, stop-loss and take-profit levels, real-time alerts and a verified track record. For education and research.',
            'site_notice'      => 'Automated analysis of live market data. Not financial advice — trade only what you can afford to lose.',
        ];
    }

    private static function seed(PDO $pdo, array $config): void
    {
        // Admin user, with a password nobody else already knows.
        //
        // This used to seed admin/admin123 from config.php - a constant
        // shipped in every copy of this application. The installer sets a real
        // password, so a site installed through the wizard was never at risk;
        // but this runs on every boot whenever the users table is empty, so a
        // deployment that skipped the wizard, a restored backup that lost the
        // row, or an operator clearing the table for any reason silently got
        // an administrator account whose credentials are published in the
        // source of the product they are running.
        //
        // A random password cannot be guessed, and writing it beside the
        // database is the one place the operator can reach without the login
        // it is protecting. The file is deleted the moment the password is
        // changed; until then the admin panel says it exists, and names it -
        // DataGuard gives it a random suffix, because a credential at a
        // predictable URL is only as safe as the .htaccess this whole class
        // exists because it cannot assume.
        $count = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        if ($count === 0) {
            $pw = bin2hex(random_bytes(9));
            $pdo->prepare('INSERT INTO users (username, password_hash) VALUES (?, ?)')
                ->execute([$config['admin']['default_user'], password_hash($pw, PASSWORD_DEFAULT)]);
            $note = DataGuard::writeFirstLogin($config['admin']['default_user'], $pw);
            self::setSetting('admin_first_login_file', (string)$note);
            self::setSetting('admin_first_login', '1');
        }

        // Default watchlist: top-30 pairs by market cap. Admin > Symbols can
        // import every pair the exchange trades with one click.
        //
        // Seeded once and remembered, rather than "seed whenever the table is
        // empty". An admin who deletes the whole coin list has chosen an empty
        // list, and the next page load putting all thirty back is the install
        // arguing with them. The marker is set either way, so an install that
        // already has coins is never re-seeded on upgrade.
        $count = (int)$pdo->query('SELECT COUNT(*) FROM symbols')->fetchColumn();
        $seeded = self::setting('symbols_seeded', '') === '1';
        if ($count === 0 && !$seeded) {
            $defaults = [
                ['BTCUSDT', 'Bitcoin / USDT'],    ['ETHUSDT', 'Ethereum / USDT'],
                ['BNBUSDT', 'BNB / USDT'],        ['SOLUSDT', 'Solana / USDT'],
                ['XRPUSDT', 'XRP / USDT'],        ['ADAUSDT', 'Cardano / USDT'],
                ['DOGEUSDT', 'Dogecoin / USDT'],  ['TONUSDT', 'Toncoin / USDT'],
                ['TRXUSDT', 'TRON / USDT'],       ['AVAXUSDT', 'Avalanche / USDT'],
                ['LINKUSDT', 'Chainlink / USDT'], ['DOTUSDT', 'Polkadot / USDT'],
                ['POLUSDT', 'Polygon / USDT'],    ['LTCUSDT', 'Litecoin / USDT'],
                ['SHIBUSDT', 'Shiba Inu / USDT'], ['BCHUSDT', 'Bitcoin Cash / USDT'],
                ['UNIUSDT', 'Uniswap / USDT'],    ['XLMUSDT', 'Stellar / USDT'],
                ['NEARUSDT', 'NEAR / USDT'],      ['APTUSDT', 'Aptos / USDT'],
                ['ICPUSDT', 'Internet Computer / USDT'], ['FILUSDT', 'Filecoin / USDT'],
                ['ARBUSDT', 'Arbitrum / USDT'],   ['OPUSDT', 'Optimism / USDT'],
                ['INJUSDT', 'Injective / USDT'],  ['ATOMUSDT', 'Cosmos / USDT'],
                ['ETCUSDT', 'Ethereum Classic / USDT'], ['HBARUSDT', 'Hedera / USDT'],
                ['SUIUSDT', 'Sui / USDT'],        ['PEPEUSDT', 'Pepe / USDT'],
            ];
            $stmt = $pdo->prepare('INSERT INTO symbols (symbol, label) VALUES (?, ?)');
            foreach ($defaults as $d) {
                $stmt->execute($d);
            }
        }
        if (!$seeded) {
            self::setSetting('symbols_seeded', '1');
        }

        // Knowledge base - the distilled rule set the engine scores against.
        // Insert-if-missing so upgrades gain new rules without touching the
        // weights the admin has already tuned.
        {
            $rules = self::seedRules();
            $isSqlite = $config['db']['driver'] !== 'mysql';
            $stmt = $pdo->prepare(
                ($isSqlite ? 'INSERT OR IGNORE' : 'INSERT IGNORE') .
                ' INTO ta_knowledge (rule_key, name, category, description, weight, weight_base) VALUES (?, ?, ?, ?, ?, ?)'
            );
            foreach ($rules as $r) {
                $stmt->execute([$r[0], $r[1], $r[2], $r[3], $r[4], $r[4]]);
            }

            // The confluence rule now reads the whole ladder rather than one
            // timeframe up, so its description no longer describes what it
            // does. Rewritten only where it still matches the old seed text -
            // an operator who has edited their own copy keeps it.
            $pdo->prepare('UPDATE ta_knowledge SET description = ? WHERE rule_key = ? AND description = ?')
                ->execute([
                    self::seedRuleDescription('mtf_confluence'),
                    'mtf_confluence',
                    'Elite traders never fight the higher timeframe: a 1h setup aligned with the 4h '
                    . 'trend (price above the 200-EMA with a rising 50-EMA) has a structurally higher '
                    . 'win rate. Signals against the higher timeframe are discounted.',
                ]);

            // Evidence categories with their correlation caps.
            $cats = [
                ['momentum',    'Momentum oscillators', 2.5],
                ['trend',       'Trend & regime',       3.0],
                ['volatility',  'Volatility',           1.5],
                ['pattern',     'Candlestick patterns', 2.0],
                ['volume',      'Volume & flow',        2.0],
                ['structure',   'Market structure',     2.5],
                ['sentiment',   'News sentiment',       1.5],
                ['positioning', 'Positioning & crowding', 2.0],
                ['meta',        'Confluence',           1.5],
            ];
            $cstmt = $pdo->prepare(
                ($isSqlite ? 'INSERT OR IGNORE' : 'INSERT IGNORE') .
                ' INTO ta_categories (category, label, cap, weight) VALUES (?, ?, ?, 1.0)'
            );
            foreach ($cats as $c) {
                $cstmt->execute($c);
            }
        }

        // Trusted news sources - fully automated fetching; admin can add/remove.
        $count = (int)$pdo->query('SELECT COUNT(*) FROM news_sources')->fetchColumn();
        if ($count === 0) {
            $sources = [
                ['CoinDesk',         'rss',         'https://www.coindesk.com/arc/outboundfeeds/rss'],
                ['Cointelegraph',    'rss',         'https://cointelegraph.com/rss'],
                ['Decrypt',          'rss',         'https://decrypt.co/feed'],
                ['Bitcoin Magazine', 'rss',         'https://bitcoinmagazine.com/feed'],
                ['Economic calendar (ForexFactory)', 'ff_calendar', 'https://nfs.faireconomy.media/ff_calendar_thisweek.xml'],
            ];
            $stmt = $pdo->prepare('INSERT INTO news_sources (name, kind, url) VALUES (?, ?, ?)');
            foreach ($sources as $src) {
                $stmt->execute($src);
            }
        }

        // Subscription plans - seeded once, fully editable in Admin > Billing.
        $count = (int)$pdo->query('SELECT COUNT(*) FROM plans')->fetchColumn();
        if ($count === 0) {
            $stmt = $pdo->prepare('INSERT INTO plans (name, days, price_usd, sort) VALUES (?, ?, ?, ?)');
            $stmt->execute(['1 Week', 7, 9.99, 1]);
            $stmt->execute(['1 Month', 30, 24.99, 2]);
            $stmt->execute(['1 Year', 365, 199.99, 3]);
        }

        // Payment methods - one disabled manual example only. Automatic options
        // appear at checkout by themselves once a gateway is configured; admins
        // add Cryptomus per-currency rows from Plans & gateways when needed.
        $count = (int)$pdo->query('SELECT COUNT(*) FROM payment_methods')->fetchColumn();
        if ($count === 0) {
            $pdo->prepare('INSERT INTO payment_methods (name, kind, currency, details, enabled, sort) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute(['Manual transfer (edit me)', 'manual', '',
                "Send the exact amount to:\nWallet/Account: PUT-YOUR-ADDRESS-OR-NUMBER-HERE\nThen upload a screenshot of the payment below.", 0, 1]);
        }
        // One-time cleanup for existing installs: drop the old placeholder
        // "automatic" currency rows that were seeded before any gateway was
        // configured. Only untouched, never-used rows are removed.
        if (self::setting('mig_seed_gateways_removed') === '') {
            $chk = $pdo->prepare('SELECT COUNT(*) FROM payments WHERE method_name = ?');
            $del = $pdo->prepare(
                "DELETE FROM payment_methods WHERE name = ? AND kind = 'cryptomus' AND currency = ? AND details = '' AND image = ''"
            );
            $seeded = [['USDT (Tether)', 'USDT'], ['Bitcoin', 'BTC'], ['Ethereum', 'ETH'],
                       ['Litecoin', 'LTC'], ['TRON', 'TRX']];
            foreach ($seeded as [$n, $c]) {
                $chk->execute([$n]);
                if ((int)$chk->fetchColumn() === 0) {
                    $del->execute([$n, $c]);
                }
            }
            self::setSetting('mig_seed_gateways_removed', '1');
        }

        // Launch polish on existing installs: replace the old developer-style
        // footer default with user-facing copy - only if never customised.
        if (self::setting('footer_text') === 'analyses charts with a server-stored technical-analysis knowledge base (momentum, trend, volatility, candlestick patterns and market structure). All data and every generated signal are stored on this server.') {
            self::setSetting('footer_text', 'delivers automatic BUY / SELL signals from live chart analysis - with entry, stop-loss and take-profit levels, real-time alerts and a verified track record. For education and research.');
        }

        // The hero advertised a hard-coded rule count that drifts every time
        // the knowledge base grows - it said 43 while the engine ran 54. Only
        // the untouched default is corrected; custom copy is never rewritten.
        if (self::setting('hero_subtitle') === 'Automatic BUY / SELL signals from 43 technical-analysis rules, news sentiment and multi-timeframe confluence — with entry, stop loss, take-profit targets and a market-verified track record.') {
            self::setSetting('hero_subtitle', 'Automatic BUY / SELL signals from a full technical-analysis rule set, news sentiment and multi-timeframe confluence — with entry, stop loss, take-profit targets and a market-verified track record.');
        }

        // THE HERO ITSELF WAS STILL THE GENERIC LINE.
        // Every other section of this page - the feature cards, the "how
        // this is different" table, the FAQ - was specific and confident.
        // The very first thing anyone reads was "AI-powered chart analysis
        // & trade signals", which says nothing this product doesn't share
        // with every competitor. Only the untouched default is corrected;
        // an operator's own hero copy is never rewritten out from under them.
        if (self::setting('hero_title') === 'AI-powered chart analysis & trade signals') {
            self::setSetting('hero_title', 'Every signal shows its working');
        }
        if (self::setting('hero_subtitle') === 'Automatic BUY / SELL signals from a full technical-analysis rule set, news sentiment and multi-timeframe confluence — with entry, stop loss, take-profit targets and a market-verified track record.') {
            self::setSetting('hero_subtitle', 'Every call ships its trade plan and the exact rules that produced it — correlation-capped, graded A+ to C, and checked against what the market actually did.');
        }
        if (self::setting('site_tagline') === 'AI-assisted chart analysis & trade signals') {
            self::setSetting('site_tagline', 'Evidence-first trade signals');
        }

        // THE PENDULUM SWINGS BACK, ON PURPOSE THIS TIME.
        //
        // The line above moved away from saying "AI" at all, toward copy that
        // proved itself instead of naming its method. That was the right call
        // against generic, one-line AI-hype copy - but it also stopped telling
        // a visitor the one thing that makes this different from a person
        // posting calls in a group chat: nobody is picking these, software is,
        // and the software gets better on its own from every result (see
        // Adapt.php and Outcomes::applyWeightTuning() - this is a real,
        // running feature, not a claim). Hiding that behind "evidence-first"
        // asked a reader to take the differentiator on faith. Said plainly and
        // in plain words instead: AI-generated, and it keeps learning. Only
        // the untouched shipped default is corrected here; an operator's own
        // rewrite of any of these three is never touched.
        if (self::setting('hero_title') === 'Every signal shows its working') {
            self::setSetting('hero_title', 'AI signals that get smarter');
        }
        if (self::setting('hero_subtitle') === 'Every call ships its trade plan and the exact rules that produced it — correlation-capped, graded A+ to C, and checked against what the market actually did.') {
            self::setSetting('hero_subtitle', 'AI reads every chart and builds the trade plan — entry, stop loss and profit targets. It learns from real results every day, wins and losses both, so it keeps getting better.');
        }
        if (self::setting('site_tagline') === 'Evidence-first trade signals') {
            self::setSetting('site_tagline', 'AI signals, always learning');
        }
        // A later, in-between round of copy already moved past the two hero
        // strings above without ever recording what it moved TO here, so an
        // install sitting on that round matched neither exact check above.
        // Caught by name instead: this is index.php's own long-standing
        // inline fallback (see $heroTitle there), which is how a value only
        // ever produced by that fallback ends up stored verbatim.
        if (self::setting('hero_title') === 'Every signal, graded and proven') {
            self::setSetting('hero_title', 'AI signals that get smarter');
        }
        if (self::setting('hero_subtitle') === 'Every call comes with a complete trade plan and the exact rules behind it — graded A+ to C, and checked automatically against what actually happened in the market.') {
            self::setSetting('hero_subtitle', 'AI reads every chart and builds the trade plan — entry, stop loss and profit targets. It learns from real results every day, wins and losses both, so it keeps getting better.');
        }

        // One more pass at the same headline, from the operator's own
        // reference copy rather than a guess at what "simple" meant. Folded
        // into one flowing line instead of the two short sentences it arrived
        // as, so it still fits the single <h1> the landing page renders (see
        // $heroLead/$heroLast in index.php, which brasses the last word) -
        // the meaning carries over, the sentence break doesn't. The subtitle
        // gains the specifics ("entry, stop loss and profit targets") and the
        // self-learning line the reference paragraph didn't mention, because
        // this codebase says what a signal DOES rather than that it is
        // "intelligent" - see the FAQ answers on this same page for the same
        // rule applied everywhere else. Only the untouched shipped default is
        // replaced; a rewrite of either field is never touched.
        if (self::setting('hero_title') === 'AI signals that get smarter') {
            self::setSetting('hero_title', 'AI-Powered Signals, Smarter Trading');
        }
        if (self::setting('hero_subtitle') === 'AI reads every chart and builds the trade plan — entry, stop loss and profit targets. It learns from real results every day, wins and losses both, so it keeps getting better.') {
            self::setSetting('hero_subtitle', 'AI reads market trends, momentum and volatility, then turns them into live BUY/SELL signals with a full trade plan — entry, stop loss and profit targets. It learns from every result, so it keeps getting sharper over time.');
        }
        // The tagline drives what a search result and a shared link actually
        // show - the <title> tag (see $seoTitle in index.php: "$siteName —
        // $tagline"), the meta description when no hero subtitle or override
        // is set, and the Organization entry in this page's own JSON-LD (see
        // View::head()). None of that is visible on the page itself, so
        // leaving it on the OLD hero wording after the hero moved on on would
        // have quietly split what a visitor reads on-page from what a search
        // engine or a shared link shows about the same site.
        if (self::setting('site_tagline') === 'AI signals, always learning') {
            self::setSetting('site_tagline', 'AI-Powered Signals, Smarter Trading');
        }

        // Wording fix on existing installs: only if the notice is still the
        // old untouched default (custom admin text is never overwritten).
        // The risk line, said once and said plainly.
        //
        // Some version of "for educational purposes only - NOT financial
        // advice" appeared twenty-seven times across the site, three of them
        // inside a single alert email. Repetition does not add legal weight;
        // it reads as a site apologising for itself, and a reader who is told
        // five times on one page that none of this is advice starts wondering
        // what they are paying for. It is now one sentence, carried by this
        // setting, shown once per surface - and it still says both of the
        // things that matter: automated, and not advice.
        //
        // Only the shipped wording is replaced. An operator who wrote their
        // own keeps it, because their lawyer may have chosen those words.
        $shipped = [
            'Signals are generated automatically from historical chart data for educational purposes only. This is NOT financial advice.',
            'Signals are generated automatically from live market data (completed candles) for educational purposes only. This is NOT financial advice.',
        ];
        if (in_array(self::setting('site_notice'), $shipped, true)) {
            self::setSetting('site_notice', 'Automated analysis of live market data. Not financial advice — trade only what you can afford to lose.');
        }

        // One-time reclassification: old time-stop outcomes were stored as
        // 'invalid' (a loss). They are no-trades - move them to 'expired' so
        // winrates and rule tuning only count real SL/TP results.
        if (self::setting('mig_expired_outcomes') === '') {
            $pdo->exec("UPDATE signals SET outcome = 'expired'
                        WHERE outcome = 'invalid' AND outcome_note = 'Time stop expired'");
            self::setSetting('mig_expired_outcomes', '1');
        }

        // One-time repair: put back the coins the old strike rule threw away.
        //
        // Until now every fetch failure counted the same, so three rate-limited
        // runs or three timeouts dropped a live coin out of the scan - and
        // nothing ever puts one back, because a coin with scan = 0 is never
        // read again to discover it recovered. The watchlist eroded quietly and
        // the operator found out from the gaps in their own track record. On
        // the install this was written against, eleven of the twelve dropped
        // coins had been lost to one unreachable endpoint; exactly one was a
        // genuinely bad symbol.
        //
        // So: anything dropped WITHOUT the "no such symbol" tag goes back in
        // with a clean record. That tag only exists from this release, so this
        // restores every coin dropped by the old rule, which is the intent -
        // a coin that really is gone will fail again on its next read and be
        // dropped again within a few runs, this time for the right reason.
        //
        // Deliberately does not touch scan = 0 with an empty note: that is an
        // operator who unticked the coin by hand, and their choice is not a bug
        // to be repaired.
        if (self::setting('mig_rescue_transient_drops') === '') {
            try {
                $n = $pdo->exec("UPDATE symbols SET scan = 1, scan_fails = 0, scan_note = ''
                                 WHERE scan = 0 AND scan_note != ''
                                   AND scan_note NOT LIKE '%" . MarketData::PERMANENT_TAG . "%'");
                if ((int)$n > 0) {
                    self::setSetting('scan_rescued_count', (string)(int)$n);
                }
            } catch (\Throwable $e) {
                // A repair that cannot run must not stop the upgrade.
            }
            self::setSetting('mig_rescue_transient_drops', '1');
        }

        // The per-coin learned multipliers move out of the settings row and
        // into their own table. Runs once; see SymbolLearning for why the blob
        // had to go, and why this is a table rather than a second database.
        if (self::setting('mig_symbol_learning') === '') {
            try {
                $moved = SymbolLearning::migrateFromSetting();
                if ($moved > 0) {
                    self::setSetting('mig_symbol_learning_moved', (string)$moved);
                }
            } catch (\Throwable $e) {
                // The tuner rebuilds these from the signals on its next run, so
                // a migration that cannot complete costs a cycle, not the data.
            }
            self::setSetting('mig_symbol_learning', '1');
        }

        // THE MEASURED GEOMETRY, FOR INSTALLS THAT ALREADY EXIST.
        //
        // Changing a default only helps a fresh install: an existing one has
        // the old value stored and would keep it forever. This moves the stop
        // and first-target multipliers to the searched pair - but ONLY if they
        // are still bit-for-bit the untouched shipped values. An operator who
        // has tuned either one has made a decision, and a migration that
        // overwrites it is a migration that loses somebody's work.
        //
        // Every change goes through the audit log, so reverting is reading a
        // list rather than remembering one.
        if (self::setting('mig_geometry_wide_stop') === '') {
            $sl = self::setting('sl_atr_mult', '');
            $tp1 = self::setting('tp1_atr_mult', '');
            if ($sl === '1.5' && $tp1 === '1.0') {
                self::setSetting('sl_atr_mult', '2.5');
                self::setSetting('tp1_atr_mult', '1.5');
                try {
                    Audit::log('setting.change', 'sl_atr_mult', '1.5', '2.5');
                    Audit::log('setting.change', 'tp1_atr_mult', '1.0', '1.5');
                } catch (\Throwable $e) {
                    // The settings are what matter; a missing audit row must
                    // not roll back a change that has already been written.
                }
                self::setSetting('mig_geometry_wide_stop_applied', '1');
            }
            self::setSetting('mig_geometry_wide_stop', '1');
        }

        // 15m WANTS A DIFFERENT SHAPE FROM 1h AND 4h, SO IT GETS ITS OWN.
        //
        // The same 32-geometry search across six coins, run on 15m:
        //
        //   sl 2.0 tp1 1.5 tp2 3.0   +0.0424R over 131 trades, 73.3% win,
        //                            payoff 0.42 against 0.36 needed
        //
        // The only positive candidate of the thirty-two, and it beats what 15m
        // was inheriting from the globals (2.5/1.5/2.0, measured at -0.0311R)
        // by 0.073R a trade. A TIGHTER stop and a FURTHER second target than
        // the slower frames want - which is why per-timeframe levels exist,
        // and why applying the 1h answer everywhere would have been wrong.
        //
        // Honest about the sample: 131 decided trades against 233-306 for the
        // runners-up. A further second target means more trades expire rather
        // than resolve, so this is the thinnest row in the top four. It clears
        // the floor, it is the only positive option, and it is one line in the
        // change log to undo.
        //
        // Only written when 15m has no entry of its own. An operator or the
        // optimiser may have set one, and that is a decision, not a gap.
        if (self::setting('mig_geometry_15m') === '') {
            try {
                $lv = json_decode(self::setting('tf_levels', '{}'), true) ?: [];
                if (!isset($lv['15m'])) {
                    $lv['15m'] = ['sl' => 2.0, 'tp1' => 1.5, 'tp2' => 3.0];
                    self::setSetting('tf_levels', json_encode($lv));
                    Audit::log('setting.change', 'tf_levels[15m]', '(none)',
                        'sl 2.0 / tp1 1.5 / tp2 3.0 - searched, +0.0424R on 131 trades');
                    self::setSetting('mig_geometry_15m_applied', '1');
                }
            } catch (\Throwable $e) {
                // A geometry that fails to write leaves 15m on the globals,
                // which is where it already was. Nothing is half-applied.
            }
            self::setSetting('mig_geometry_15m', '1');
        }

        // Runtime settings - every one of these is editable in Admin > Settings.
        $defaults = self::defaultSettings($config);
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM settings WHERE skey = ?');
        $ins  = $pdo->prepare('INSERT INTO settings (skey, svalue) VALUES (?, ?)');
        foreach ($defaults as $k => $v) {
            $stmt->execute([$k]);
            $have = (int)$stmt->fetchColumn();
            $stmt->closeCursor();       // see pdo() - a held read blocks the insert below
            if ($have === 0) {
                $ins->execute([$k, $v]);
            }
        }
        // Defaults were inserted straight through PDO, bypassing setSetting().
        self::flushSettingCache();

        // Publish A and A+ only, on installs that never chose otherwise.
        //
        // The floors shipped as "any", so a C-grade call - one weak category,
        // no higher-timeframe agreement - was shown and alerted exactly like
        // an A+. That is not a presentation choice, it is what the record is
        // made of: every C that resolves is counted, and the published
        // expectancy is the average of the good calls and the noise together.
        //
        // Only an untouched "any" is raised, once, and each change is written
        // to the change log so it is not a thing that quietly happened. An
        // operator who deliberately wants every grade sets it back in
        // Settings > Alerts and Settings > Signals and it stays.
        if (self::setting('mig_grade_floor_a') === '') {
            self::setSetting('mig_grade_floor_a', '1');
            foreach (['show_min_grade', 'alert_min_grade'] as $gk) {
                if (self::setting($gk, 'any') === 'any') {
                    self::setSetting($gk, 'A');
                    try {
                        Audit::log('setting.change', $gk, 'any', 'A', Audit::ACTOR_ENGINE);
                    } catch (\Throwable $e) {
                        // The change matters more than the note about it.
                    }
                }
            }
        }

        // Lift the old bulk-watch ceiling on installs that never chose it.
        //
        // 60 was seeded at install and counts PAIRS, so "watch all my coins"
        // with three timeframes ticked delivered twenty coins and stopped -
        // and the number was never a decision anyone made, it was just what
        // shipped. Only an untouched 60 is lifted, once, and it is written to
        // the change log so it is not a thing that quietly happened. An
        // operator who deliberately wants 60 can set it and it stays.
        if (self::setting('mig_bulk_watch_uncap') === '') {
            self::setSetting('mig_bulk_watch_uncap', '1');
            if (self::setting('bulk_watch_max', '0') === '60') {
                self::setSetting('bulk_watch_max', '0');
                try {
                    Audit::log('setting.change', 'bulk_watch_max', '60', '0 (no limit)',
                        Audit::ACTOR_ENGINE);
                } catch (\Throwable $e) {
                }
            }
        }

        // One-time migration of the old settings-table caches into `cache`.
        if (self::setting('mig_cache_table') === '') {
            // NB: the escape character must not be a backslash. MySQL and MariaDB
            // treat a backslash inside a string literal as an escape, so
            // ESCAPE '\\' leaves the literal unterminated and the statement
            // fails with a 1064 syntax error - which broke installation
            // outright on those servers. '!' is portable.
            $pdo->exec("DELETE FROM settings WHERE skey LIKE 'tick!_%' ESCAPE '!'");
            $pdo->exec("DELETE FROM settings WHERE skey LIKE 'fund!_%' ESCAPE '!'");
            $pdo->exec("DELETE FROM settings WHERE skey LIKE 'regip!_%' ESCAPE '!'");
            $pdo->exec("DELETE FROM settings WHERE skey IN ('fng_cache', 'feed_cache')");
            self::flushSettingCache();
            self::setSetting('mig_cache_table', '1');
        }

        // Encrypt the credentials that are already in the table.
        //
        // Not marked done unless a key was actually available, so a host where
        // the key file could not be written retries on the next boot instead
        // of leaving the keys in plaintext forever with a flag saying they are
        // not. Cheap to re-run: it only touches rows that are still plain.
        if (self::setting('mig_secrets_encrypted') === '') {
            $rep = Secrets::migrate();
            if ($rep['key']) {
                self::setSetting('mig_secrets_encrypted', '1');
            }
        }
    }

    /**
     * The seeded knowledge base: [rule_key, name, category, description, weight].
     *
     * Lives in its own method so callers that need to describe the rule set -
     * the installer, for one - can count it instead of hard-coding a number
     * that silently goes stale every time a rule is added.
     */
    /** The shipped description for one rule key, or '' if it is not seeded. */
    public static function seedRuleDescription(string $key): string
    {
        foreach (self::seedRules() as $r) {
            if ($r[0] === $key) {
                return (string)$r[3];
            }
        }
        return '';
    }

    public static function seedRules(): array
    {
        return [
            ['rsi_oversold', 'RSI oversold reversal', 'momentum',
             'RSI(14) below 30 signals an oversold market where selling pressure is often exhausted; bounces frequently start from this zone, and a hook back above 30 strengthens the case.', 1.5],
            ['rsi_overbought', 'RSI overbought exhaustion', 'momentum',
             'RSI(14) above 70 signals an overbought market prone to pullbacks; a hook back below 70 after an extended run often precedes a corrective leg.', 1.5],
            ['macd_bull_cross', 'MACD bullish crossover', 'momentum',
             'MACD line crossing above its signal line shows momentum shifting to the upside; crossovers below the zero line that then reclaim it are the strongest variant.', 1.5],
            ['macd_bear_cross', 'MACD bearish crossover', 'momentum',
             'MACD line crossing below its signal line shows momentum rolling over; most reliable after an extended uptrend or at a lower-high on the histogram.', 1.5],
            ['ema_trend_bull', 'EMA 20/50 bullish alignment', 'trend',
             'EMA20 above EMA50 with price above both defines a healthy uptrend. Trading in the direction of stacked EMAs has a materially better hit-rate than fading them.', 1.2],
            ['ema_trend_bear', 'EMA 20/50 bearish alignment', 'trend',
             'EMA20 below EMA50 with price below both defines a downtrend; rallies into the EMA cluster tend to get sold.', 1.2],
            ['ema200_regime', 'EMA200 regime filter', 'trend',
             'Price above the 200-period EMA marks a bullish regime, below it a bearish regime. Counter-regime signals are discounted; with-regime signals are reinforced.', 1.0],
            ['bb_lower_touch', 'Bollinger lower-band tag', 'volatility',
             'A close at or below the lower Bollinger Band (20, 2) is a statistical stretch; mean-reversion back toward the middle band is the base case, especially in ranging markets.', 1.0],
            ['bb_upper_touch', 'Bollinger upper-band tag', 'volatility',
             'A close at or above the upper Bollinger Band (20, 2) marks an overextended move that frequently pauses or reverts toward the mean, unless a strong trend is riding the band.', 1.0],
            ['stoch_oversold_cross', 'Stochastic oversold bull cross', 'momentum',
             '%K crossing above %D while both are under 20 is a classic timing trigger for longs, best taken in the direction of the larger trend.', 1.0],
            ['stoch_overbought_cross', 'Stochastic overbought bear cross', 'momentum',
             '%K crossing below %D while both are above 80 is a classic timing trigger for shorts or profit-taking.', 1.0],
            ['bullish_engulfing', 'Bullish engulfing candle', 'pattern',
             'A green body fully engulfing the prior red body after a decline shows buyers absorbing supply in one bar - a high-signal reversal print at support.', 1.3],
            ['bearish_engulfing', 'Bearish engulfing candle', 'pattern',
             'A red body fully engulfing the prior green body after an advance shows distribution - a high-signal reversal print at resistance.', 1.3],
            ['hammer', 'Hammer at lows', 'pattern',
             'A long lower wick (2x+ the body) closing near the high after a decline shows rejection of lower prices; strongest when the wick probes a known support level.', 1.0],
            ['shooting_star', 'Shooting star at highs', 'pattern',
             'A long upper wick (2x+ the body) closing near the low after an advance shows rejection of higher prices; strongest at resistance or the upper Bollinger band.', 1.0],
            ['volume_confirmation', 'Volume confirmation', 'volume',
             'A directional candle on volume well above its 20-bar average carries conviction; moves without volume are treated as suspect and receive no boost.', 0.8],
            ['higher_highs_lows', 'Higher highs & higher lows', 'structure',
             'A sequence of higher swing highs and higher swing lows is the definition of an uptrend; structure outranks any single oscillator reading.', 1.2],
            ['lower_highs_lows', 'Lower highs & lower lows', 'structure',
             'A sequence of lower swing highs and lower swing lows defines a downtrend; oscillator "oversold" readings inside this structure are discounted.', 1.2],
            ['near_support', 'Price at major support', 'structure',
             'Price within 1 ATR of a recent significant swing low offers an asymmetric long location: risk is defined just below the level.', 1.0],
            ['near_resistance', 'Price at major resistance', 'structure',
             'Price within 1 ATR of a recent significant swing high is a poor chase-long location and a favoured area for shorts/profit-taking.', 1.0],
            // --- extended indicator suite ---
            ['adx_trend', 'ADX trend strength filter', 'trend',
             'ADX(14) above 25 confirms a genuine trend; direction comes from +DI vs -DI dominance. Signals taken with a strong ADX trend outperform choppy-market signals.', 1.2],
            ['ichimoku_cloud', 'Ichimoku cloud position', 'trend',
             'Price above the Kumo cloud is bullish regime, below it bearish. The cloud combines 9/26/52-period equilibrium levels and is a widely-watched regime filter.', 1.2],
            ['ichimoku_tk_cross_bull', 'Ichimoku TK bullish cross', 'momentum',
             'Tenkan-sen crossing above Kijun-sen is a classic momentum trigger, strongest when it happens above the cloud.', 1.0],
            ['ichimoku_tk_cross_bear', 'Ichimoku TK bearish cross', 'momentum',
             'Tenkan-sen crossing below Kijun-sen signals fading momentum, strongest when it happens below the cloud.', 1.0],
            ['willr_oversold', 'Williams %R oversold', 'momentum',
             'Williams %R(14) below -80 marks an oversold stretch; a bounce back above -80 is the trigger many system traders use.', 0.8],
            ['willr_overbought', 'Williams %R overbought', 'momentum',
             'Williams %R(14) above -20 marks an overbought stretch prone to pullbacks.', 0.8],
            ['cci_oversold', 'CCI oversold extreme', 'momentum',
             'CCI(20) below -100 signals an extreme deviation below the mean; mean-reversion or trend-exhaustion setups build here.', 0.8],
            ['cci_overbought', 'CCI overbought extreme', 'momentum',
             'CCI(20) above +100 signals an extreme deviation above the mean, often preceding a pause or pullback.', 0.8],
            ['mfi_oversold', 'MFI oversold (smart money inflow zone)', 'volume',
             'Money Flow Index below 20 shows selling exhaustion in volume-weighted terms - historically a zone where accumulation starts.', 1.0],
            ['mfi_overbought', 'MFI overbought (distribution zone)', 'volume',
             'Money Flow Index above 80 shows buying exhaustion in volume-weighted terms - a distribution-prone zone.', 1.0],
            ['obv_trend', 'OBV accumulation/distribution', 'volume',
             'On-Balance Volume above its own 20-bar average shows net accumulation; below it, net distribution. Volume leads price.', 0.8],
            ['psar_flip_bull', 'Parabolic SAR bullish flip', 'trend',
             'SAR flipping below price marks a fresh uptrend leg with a built-in trailing stop level.', 1.0],
            ['psar_flip_bear', 'Parabolic SAR bearish flip', 'trend',
             'SAR flipping above price marks a fresh downtrend leg.', 1.0],
            ['supertrend_dir', 'SuperTrend direction', 'trend',
             'SuperTrend(10, 3) direction is a robust ATR-based trend filter widely used for entries and trailing exits.', 1.3],
            ['donchian_breakout_bull', 'Donchian 20-bar breakout up', 'structure',
             'A close above the prior 20-bar high is a breakout - the entry signal behind classic trend-following systems.', 1.2],
            ['donchian_breakout_bear', 'Donchian 20-bar breakdown', 'structure',
             'A close below the prior 20-bar low is a breakdown from range support.', 1.2],
            // --- master-trader layer ---
            ['mtf_confluence', 'Higher-timeframe confluence', 'trend',
             'Every timeframe above this one is read and weighted, the higher frames counting for more: a daily downtrend outranks a 15m bounce. Each frame is scored on price against its 200-EMA, its 50-EMA slope, EMA stacking and swing structure, and the rule votes in proportion to how much of the ladder agrees. Going with that bias is free; going against it has to clear a raised threshold.', 1.5],
            ['rsi_divergence_bull', 'Bullish RSI divergence', 'momentum',
             'Price printing a lower low while RSI prints a higher low means downside momentum is drying up while price makes its final push - one of the highest-quality reversal tells professionals wait for.', 1.4],
            ['rsi_divergence_bear', 'Bearish RSI divergence', 'momentum',
             'Price printing a higher high while RSI prints a lower high exposes a rally running on fumes - distribution beneath the surface.', 1.4],
            ['macd_hist_divergence_bull', 'Bullish MACD-histogram divergence', 'momentum',
             'The histogram is the gap between MACD and its signal line, so it turns before the MACD line does. Price printing a lower low while the histogram prints a higher low - from below zero, where it means something - is selling pressure decelerating into the low rather than merely being low.', 1.3],
            ['macd_hist_divergence_bear', 'Bearish MACD-histogram divergence', 'momentum',
             'Price printing a higher high while the MACD histogram prints a lower high, with the histogram still above zero, is a push losing force while it is still making progress. It leads the MACD cross rather than confirming it afterwards.', 1.3],
            ['obv_divergence_bull', 'Bullish on-balance-volume divergence', 'volume',
             'A new price low that on-balance volume does not confirm is a low made without the selling volume to justify it. This is a volume statement rather than a momentum one, which is exactly why it is worth having beside the oscillator divergences instead of a third oscillator.', 1.2],
            ['obv_divergence_bear', 'Bearish on-balance-volume divergence', 'volume',
             'A new price high that on-balance volume does not confirm means the rally is being sold into while it climbs - distribution under the surface, visible in volume before it is visible in price.', 1.2],
            ['fib_golden_zone', 'Fibonacci golden-zone retracement', 'structure',
             'Pullbacks into the 50-61.8% retracement of the last impulse leg (the "golden zone") are where trend-following professionals reload. A tag of the zone in the direction of the prevailing swing is a high-quality entry location.', 1.1],
            ['confluence_bonus', 'Multi-category confluence', 'meta',
             'One indicator is an opinion; agreement across independent evidence types (trend + momentum + volume + structure + patterns) is an edge. When three or more categories vote the same way, the setup earns a conviction bonus - the closest thing to how a veteran reads a chart at a glance.', 1.0],
            ['btc_regime', 'Bitcoin regime filter (altcoins)', 'trend',
             'Altcoins rarely sustain rallies while Bitcoin is in a downtrend - BTC is the tide that lifts or sinks every boat. For non-BTC pairs, the engine reads BTC\'s own trend on the same timeframe and votes with it.', 1.0],
            // --- news sentiment ---
            ['news_sentiment_coin', 'News sentiment: this coin', 'sentiment',
             'Aggregated sentiment of recent stored headlines that mention this specific coin. Strong positive or negative coverage shifts short-term flows.', 1.5],
            ['news_sentiment_market', 'News sentiment: overall market', 'sentiment',
             'Aggregated sentiment of all recent market headlines. A strongly risk-on or risk-off news backdrop lifts or sinks most coins together.', 0.8],
            // --- market-positioning layer ---
            ['funding_extreme', 'Funding rate extreme (contrarian)', 'sentiment',
             'Perpetual-futures funding shows which side of the boat is crowded. Heavily positive funding means longs pay shorts - the crowd is long and squeezes get likely; heavily negative funding marks capitulation shorts. The engine fades funding extremes.', 1.0],
            ['fear_greed', 'Fear & Greed index extreme (contrarian)', 'sentiment',
             'The crypto Fear & Greed index at extreme fear (<= 20) historically marks accumulation zones; extreme greed (>= 80) marks froth. A veteran is greedy when others are fearful - the engine votes against the extremes.', 0.9],

            // Rules added after the original 46. Same insert-if-missing
            // contract: an upgrade gains them without disturbing tuned weights.
                ['vwap_position', 'VWAP position', 'trend',
                 'Price above the rolling VWAP means buyers are paying up relative to the volume-weighted average; below it, sellers are hitting bids into the average. VWAP is the benchmark desks are measured against, which is what makes it self-fulfilling.', 1.1],
                ['vwap_reclaim', 'Anchored VWAP reclaim', 'structure',
                 'Price reclaiming the VWAP anchored at the last significant swing flips control of the move back to the side that lost it. A failed reclaim is one of the cleanest continuation tells.', 1.2],
                ['volume_profile_edge', 'Volume profile value area', 'structure',
                 'The Point of Control and Value Area mark where business was actually done. Price entering the value area from outside tends to rotate to the POC; rejection at the value-area edge is a high-quality fade location - far better evidence than a raw swing high.', 1.3],
                // Smart-money concepts. All in the structure category on
                // purpose: its correlation cap is below the buy threshold, so
                // these can shade a verdict and can never produce one alone.
                // Every one of them is voted conditionally - see SignalEngine.
                ['smc_bos', 'Break of structure', 'structure',
                 'A close beyond the last swing in the direction structure was already pointing. Continuation, confirmed by a close rather than a wick - a wick through a level is a liquidity raid, and reading it as a break is the most common way this gets misapplied. Only counted while the break is recent.', 1.2],
                ['smc_choch', 'Change of character', 'structure',
                 'The first close that breaks structure the OTHER way. The point at which a trend stops behaving like one, and the earliest structural warning of a reversal available. Only counted while it is new; a change of character twenty bars old is just the current trend.', 1.3],
                ['smc_liquidity_sweep', 'Liquidity sweep', 'structure',
                 'Price traded beyond an obvious high or low - where stops rest - and closed straight back inside. That is the market taking the liquidity and rejecting the level, which is the opposite of accepting it. Weighted by how deep the raid went; a token overshoot took nobody out.', 1.3],
                ['smc_order_block', 'Order block mitigation', 'structure',
                 'The last opposing candle before the move that broke structure is where the orders causing the break sat. Price returning to it is the only moment the level is worth anything - an unvisited block a hundred bars back is scenery. Only voted when price is actually at it AND on the right side of the range.', 1.2],
                ['smc_breaker', 'Breaker block', 'structure',
                 'An order block price closed clean through. The level failed, and failed levels tend to hold from the other side - what was support becomes resistance. Only voted when price has come back to it.', 1.0],
                ['smc_fvg', 'Fair value gap', 'structure',
                 'Three bars where the first and third do not overlap leave a band price moved through without trading. Price fills most of them eventually, which makes an open gap both a target and a place a move stalls. Only voted while price is inside the imbalance - the one moment it is a decision rather than a destination.', 1.0],
                ['smc_liquidity_target', 'Resting liquidity target', 'structure',
                 'Two or more swings at the same price are a pool of stops, and price is drawn to them because those orders are not optional. Note the direction: a shelf of equal highs overhead argues UP, not down - it is a magnet, not a ceiling.', 0.9],
                ['smc_premium_discount', 'Premium / discount location', 'structure',
                 'Where price sits in the current dealing range, low to high. Buying in the bottom third is buying cheap and selling in the top third is selling expensive; doing the reverse is paying up for the same idea. Cheap evidence on its own, and the filter that stops every other structural read being taken at the worst possible price.', 0.9],
                ['oi_confirmation', 'Open interest confirmation', 'positioning',
                 'Rising price on rising open interest is new money taking risk - a trend with fuel. Rising price on falling open interest is short covering, a move with nothing behind it that reverses once the squeeze is done. The single most useful free confirmation filter in crypto.', 1.4],
                ['taker_flow', 'Taker flow (who is crossing the spread)', 'positioning',
                 'Long/short ratio says what people are holding; the taker buy/sell ratio says who is paying up right now. Above 1, aggressive buyers are lifting offers rather than waiting at the bid. It leads the other positioning reads because it is flow rather than inventory - and it is confirmation, never a call on its own: aggressive buying into a falling chart is a squeeze, not a trend.', 1.1],
                ['market_breadth', 'Market breadth', 'positioning',
                 'The share of tracked coins trading above their own 20-bar average. One coin rallying while the board bleeds is a different market from one where three quarters of it is green, and no single-pair indicator can tell those apart. Computed from candles already stored, so it costs no request.', 1.0],
                ['fng_trend', 'Fear & Greed direction', 'sentiment',
                 'Which way sentiment is travelling, not where it is. 25 on the way up from 12 is capitulation being bought; 25 on the way down from 45 is fear still arriving. The level reads those identically and they are opposite trades.', 0.8],
                ['long_short_extreme', 'Long/short ratio extreme (contrarian)', 'positioning',
                 'When the top-trader long/short account ratio reaches an extreme, the crowded side becomes the fuel for the move against it. A cleaner crowding read than funding alone, which also reflects basis and carry.', 1.0],
                ['book_imbalance', 'Order-book imbalance', 'positioning',
                 'Resting size on the bid versus the offer within the top of the book. Persistent imbalance shows where real liquidity is willing to transact, rather than where price has already been.', 0.8],
                ['bb_squeeze', 'Volatility squeeze', 'volatility',
                 'Bollinger bandwidth in the bottom fifth of its recent range marks a coiled market. Squeezes resolve into expansion; the direction is taken from the prevailing structure rather than from the squeeze itself.', 1.0],
                ['htf_engine', 'Higher-timeframe engine agreement', 'trend',
                 'The full analysis re-run on the timeframe above, scored the same way. Agreement between two independent passes of the entire knowledge base is materially stronger evidence than comparing a single moving average, which is all the original higher-timeframe check did.', 1.6],
        ];
    }

    /** Load the whole settings table into memory (once per request). */
    private static function loadSettings(): void
    {
        if (self::$settingsLoaded) {
            return;
        }
        self::$settings = [];
        foreach (self::pdo()->query('SELECT skey, svalue FROM settings') as $row) {
            $k = (string)$row['skey'];
            $v = (string)$row['svalue'];
            // Credentials are stored encrypted and decrypted exactly here, so
            // every caller above this line - the engine, the gateways, the
            // mailer - reads the plain value and knows nothing about it. A
            // value written before encryption existed carries no marker and
            // comes back untouched. See Secrets.
            self::$settings[$k] = ($v !== '' && Secrets::isSecret($k)) ? Secrets::decrypt($v) : $v;
        }
        self::$settingsLoaded = true;
    }

    /** Drop the in-memory settings cache (after bulk inserts / imports). */
    /**
     * Copy the grade out of each row's indicators JSON into the new column.
     *
     * Runs once, inside the migration that adds the column, and in batches so
     * an install with a large ledger does not load it all into memory at
     * once. A row whose blob carries no grade keeps '' - that is a signal
     * recorded before grading existed, and inventing a grade for it would put
     * a number on the site's history that the engine never produced.
     */
    private static function backfillGrades(PDO $pdo, string $table, string $idCol): void
    {
        $valid = ['C' => 1, 'B' => 1, 'A' => 1, 'A+' => 1];
        $upd = $pdo->prepare("UPDATE $table SET grade = ? WHERE $idCol = ?");
        $lastId = 0;
        while (true) {
            $sel = $pdo->prepare(
                "SELECT $idCol AS rid, indicators FROM $table WHERE $idCol > ? ORDER BY $idCol LIMIT 500"
            );
            $sel->execute([$lastId]);
            $rows = $sel->fetchAll();
            if (!$rows) {
                return;
            }
            foreach ($rows as $r) {
                $lastId = (int)$r['rid'];
                $g = json_decode((string)$r['indicators'], true);
                $g = is_array($g) ? (string)($g['grade'] ?? '') : '';
                if (isset($valid[$g])) {
                    $upd->execute([$g, $lastId]);
                }
            }
        }
    }

    /**
     * The tiered limits, flattened from the one table that defines them.
     *
     * Lives here only to be spread into the seed list; Limits::LIMITS is the
     * definition and this is a projection of it.
     *
     * @return array<string,string>
     */
    private static function limitDefaults(): array
    {
        $out = [];
        foreach (Limits::LIMITS as $l) {
            foreach ($l['keys'] as $tier => $settingKey) {
                $out[$settingKey] = (string)($l['def'][$tier] ?? 0);
            }
        }
        return $out;
    }

    public static function flushSettingCache(): void
    {
        self::$settings = [];
        self::$settingsLoaded = false;
    }

    /**
     * Close the connection and let go of everything cached behind it.
     *
     * Exists for one caller and one moment: the installer renaming a SQLite
     * database to an unguessable name. SQLite in WAL mode keeps recent writes
     * in a sidecar file named after the database, and rename() moves only the
     * database - so everything still in the -wal at that instant is orphaned
     * along with it.
     *
     * Proved on a clean install: the seeded administrator went into the -wal,
     * the rename left a five megabyte -wal behind with no database next to it,
     * the next boot found an empty users table and seeded a SECOND
     * administrator - and the install ended with two FIRST-LOGIN notes, the
     * first of which held a password that no longer worked.
     *
     * A checkpoint flushes the sidecar into the database; dropping the handle
     * lets SQLite delete it. After this the next pdo() call reopens from
     * whatever config the caller has since written.
     */
    public static function close(): void
    {
        if (self::$pdo !== null) {
            try {
                self::$pdo->exec('PRAGMA wal_checkpoint(TRUNCATE)');
            } catch (\Throwable $e) {
                // not sqlite, or nothing to checkpoint
            }
        }
        self::$pdo = null;
        self::flushSettingCache();
    }

    public static function setting(string $key, string $default = ''): string
    {
        self::loadSettings();
        return self::$settings[$key] ?? $default;
    }

    /** Every setting as an associative array (admin screens, exports). */
    public static function settingsAll(): array
    {
        self::loadSettings();
        return self::$settings;
    }

    public static function setSetting(string $key, string $value): void
    {
        $pdo = self::pdo();
        $sql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
            ? 'INSERT INTO settings (skey, svalue) VALUES (?, ?)
               ON CONFLICT(skey) DO UPDATE SET svalue = excluded.svalue'
            : 'INSERT INTO settings (skey, svalue) VALUES (?, ?)
               ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)';
        // Encrypted going in, plain in the cache: the caller handed us a plain
        // value and must read the same one back within this request.
        $stored = ($value !== '' && Secrets::isSecret($key)) ? Secrets::encrypt($value) : $value;
        $pdo->prepare($sql)->execute([$key, $stored]);
        self::loadSettings();
        self::$settings[$key] = $value;
    }

    /**
     * Compare-and-swap on a setting: writes $new only when the stored value is
     * still $expected, and reports whether this caller won the race.
     *
     * Used as a lightweight distributed lock. Alert dispatch used to be guarded
     * by "read timestamp, compare, write timestamp", which two concurrent
     * requests could both pass - so both ran the full push/email/Telegram
     * sweep. A single conditional UPDATE makes exactly one of them the winner.
     */
    public static function casSetting(string $key, string $expected, string $new): bool
    {
        // Never a credential. This compares the STORED value, and an encrypted
        // one differs every time it is written even for the same plaintext, so
        // the compare could never match and the caller would spin forever
        // believing it lost a race. Every real caller is a lock or a
        // timestamp; this says so out loud rather than letting a future one
        // find out the slow way.
        if (Secrets::isSecret($key)) {
            throw new \LogicException('casSetting() cannot be used on a credential: ' . $key);
        }
        $pdo = self::pdo();
        $stmt = $pdo->prepare('UPDATE settings SET svalue = ? WHERE skey = ? AND svalue = ?');
        $stmt->execute([$new, $key, $expected]);
        $won = $stmt->rowCount() > 0;
        if ($won) {
            self::loadSettings();
            self::$settings[$key] = $new;
        }
        return $won;
    }

    /**
     * Claim a periodic job: succeeds at most once per $intervalSeconds across
     * all concurrent requests and cron runs. Returns false when another caller
     * already holds the slot or the interval has not elapsed.
     */
    public static function claimJob(string $key, int $intervalSeconds): bool
    {
        $now = time();
        $last = self::setting($key, '0');
        if ($now - (int)$last < $intervalSeconds) {
            return false;
        }
        // The row may not exist yet on a fresh install.
        if (self::pdo()->query('SELECT COUNT(*) FROM settings WHERE skey = ' . self::pdo()->quote($key))->fetchColumn() == 0) {
            try {
                self::pdo()->prepare('INSERT INTO settings (skey, svalue) VALUES (?, ?)')->execute([$key, '0']);
                self::flushSettingCache();
                $last = '0';
            } catch (\Throwable $e) {
                $last = self::setting($key, '0');
            }
        }
        return self::casSetting($key, $last, (string)$now);
    }
}
