<?php
declare(strict_types=1);

namespace SignalMasterAi;

/**
 * Keeping the database off the public web.
 *
 * The data directory sits inside the document root, because that is the only
 * layout that installs by unzipping into public_html - which is how most of
 * these installs happen. The root .htaccess denies it, and on Apache with
 * AllowOverride enabled that is the end of the story.
 *
 * It is not the end of the story on nginx, which does not read .htaccess at
 * all, or on an Apache configured with AllowOverride None. There,
 * `GET /data/signalmasterai.sqlite` returns the file: every member email,
 * every bcrypt hash, every API token, the SMTP password and the payment
 * records, in one request, to anyone who guesses the path. It is not a
 * vulnerability in any line of PHP - it is a deployment that silently did not
 * apply the one rule protecting all of it, and nothing in the app noticed.
 *
 * Three layers, because no single one of them covers every host:
 *
 *   1. A deny file inside data/ as well as at the root, so an install whose
 *      root .htaccess was lost or overwritten is still covered on Apache and
 *      on IIS.
 *   2. A random component in the filename for new installs. An attacker
 *      cannot fetch what they cannot name, and this is the only layer that
 *      works on a server the app cannot configure.
 *   3. A live check that asks the site for its own database over HTTP and
 *      says so, loudly and in the admin panel, when the answer is 200. This
 *      is the layer that matters on nginx: the app cannot fix that config,
 *      but it can refuse to let the operator not know.
 */
class DataGuard
{
    /**
     * Apache 2.4 and 2.2, plus a directory-index stop.
     *
     * Deny only. The interpreter-off directives are deliberately NOT here -
     * see NOEXEC below for why putting them in this directory would be a way
     * to leak the database password.
     */
    private const HTACCESS = "# Written by SignalMasterAi. This directory holds the database,\n"
        . "# uploaded files and the local config. None of it is a public document.\n"
        . "<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n"
        . "<IfModule !mod_authz_core.c>\n  Order allow,deny\n  Deny from all\n</IfModule>\n"
        . "Options -Indexes\n";

    /**
     * The same deny, plus the interpreter turned off. For the uploads
     * directory and nowhere else.
     *
     * Denying access should make the handler rules redundant, and on a server
     * that honours the deny they are. They are here for the server that does
     * not - and because the two failures compose: this directory takes
     * uploads, and a .php file dropped in it EXECUTES. Verified: a probe file
     * under data/uploads/ ran and returned its output.
     *
     * Nothing can put one there today. The upload validator takes the
     * extension from getimagesize() and never from the filename, so the path
     * to code execution is closed at the moment it matters. But that is one
     * mistake away - a future import, an unzip feature, one handler that
     * trusts a filename - and the difference between a data leak and a shell
     * is exactly these lines. Images are served through pm-image.php with
     * readfile(), so nothing here ever needs to be fetched directly.
     *
     * Why only here: these lines make the server hand a .php file over as
     * plain text instead of running it. The parent directory contains
     * config.local.php, which is where the database host, username and
     * password live. The deny blocks are wrapped in <IfModule>, so on a server
     * with neither mod_authz_core nor mod_access_compat they are skipped in
     * silence - while AddType, being mod_mime, still applies. That
     * combination would turn the one file holding the credentials into a
     * public text document. Nothing in uploads/ is ever a legitimate script,
     * so the rule is safe there and only there.
     */
    private const NOEXEC = self::HTACCESS
        . "# Belt and braces: never hand anything in here to an interpreter.\n"
        . "<IfModule mod_php.c>\n  php_flag engine off\n</IfModule>\n"
        . "<IfModule mod_php7.c>\n  php_flag engine off\n</IfModule>\n"
        . "RemoveHandler .php .phtml .php3 .php4 .php5 .php7 .php8 .pl .py .cgi\n"
        . "AddType text/plain .php .phtml .php3 .php4 .php5 .php7 .php8 .pl .py .cgi\n";

    /** IIS, which reads neither .htaccess nor any of the above. */
    private const WEBCONFIG = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
        . "<configuration><system.webServer><security><requestFiltering>\n"
        . "  <hiddenSegments><add segment=\".\" /></hiddenSegments>\n"
        . "</requestFiltering></security></system.webServer></configuration>\n";

    /**
     * Drop the deny files into the data directory if they are missing.
     *
     * Cheap enough to call on every boot - three is_file() checks - and it
     * self-heals an install that was restored from a backup that skipped
     * dotfiles, which is exactly how the root .htaccess goes missing.
     */
    public static function harden(): void
    {
        $dir = defined('SMA_DATA_DIR') ? SMA_DATA_DIR : null;
        if ($dir === null || !is_dir($dir)) {
            return;
        }
        // The uploads directory gets its own copy, with the stronger rule. A
        // parent's rules cover it on Apache, but only while the parent's file
        // is the one being read - and this is the directory that takes files
        // from the public.
        $dirs = [$dir => self::HTACCESS];
        if (defined('SMA_UPLOAD_DIR') && is_dir(SMA_UPLOAD_DIR)) {
            $dirs[SMA_UPLOAD_DIR] = self::NOEXEC;
        }
        foreach ($dirs as $d => $deny) {
            $files = [
                '.htaccess'  => $deny,
                'web.config' => self::WEBCONFIG,
                // Some hosts serve a directory listing before they consult any
                // of the above. An empty index answers that first.
                'index.html' => '',
            ];
            foreach ($files as $name => $body) {
                $path = $d . '/' . $name;
                if (!is_file($path)) {
                    @file_put_contents($path, $body);
                }
            }
        }
        self::repairDataDeny($dir);
    }

    /**
     * Take the interpreter-off rules back out of the data directory.
     *
     * A previous release wrote them there. They belong in uploads/ only - the
     * reason is on NOEXEC - and harden() will not correct it on its own,
     * because it only writes a deny file that is missing and this one is
     * present. So an install that took that release keeps the bad rule
     * forever unless something goes and removes it.
     *
     * Only a file this application wrote is touched, recognised by the header
     * it writes and by the absence of anything else. An operator who has
     * edited their own rules into it gets left alone; the alternative is
     * silently discarding somebody's server configuration.
     */
    private static function repairDataDeny(string $dir): void
    {
        $path = $dir . '/.htaccess';
        $cur = @file_get_contents($path);
        if ($cur === false
            || !str_starts_with($cur, '# Written by SignalMasterAi.')
            || !str_contains($cur, 'AddType text/plain')) {
            return;
        }
        // Ours, and carrying the rule that has to go. Anything beyond what the
        // two known versions contain means it was edited afterwards.
        if ($cur !== self::NOEXEC) {
            return;
        }
        @file_put_contents($path, self::HTACCESS);
    }

    /**
     * Is the database actually reachable over HTTP right now?
     *
     * Asks the site for its own file. A 200 with a body that starts "SQLite
     * format 3" is proof, not a guess - which matters, because the operator
     * is about to be told something alarming and it needs to be true.
     *
     * Returns null when it cannot tell (no site URL, no curl, MySQL rather
     * than SQLite), true when the file came back, false when it did not.
     * Cached for an hour: it is one request, but not one worth making on
     * every dashboard load.
     */
    /**
     * ITS OWN CACHE KEY, NOT ONE SHARED WITH A DIFFERENT ANSWER.
     *
     * This and Maintenance::dataExposed both wrote to 'data_exposed', and they
     * store different things: a string here, an array there. Each therefore
     * missed on the other's value and re-ran its live probe on every load -
     * two 8-second cURL requests on every render of the settings page.
     *
     * And worse than slow. After the settings page had written its array, the
     * read below saw a non-null value, cast it, and `(bool)[...]` on a
     * non-empty array is TRUE - so the dashboard announced that the database
     * was downloadable from the public web because a different check had run
     * first. A security warning that fires on its own bookkeeping is worse
     * than no warning, because the operator learns to close it.
     *
     * The value is type-checked as well as re-keyed: a stale entry of the old
     * shape must read as "no cache", not as an alarm.
     */
    public static function databaseExposed(bool $force = false): ?bool
    {
        if (!$force) {
            $cached = Cache::get('data_exposed_db');
            if (is_string($cached)) {
                return $cached === '' ? null : $cached === '1';
            }
        }
        $path = self::publicPath();
        $base = rtrim((string)Database::setting('site_url'), '/');
        if ($path === null || $base === '' || !function_exists('curl_init')) {
            Cache::set('data_exposed_db', '', 3600);
            return null;
        }
        $ch = curl_init($base . '/' . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 6,
            CURLOPT_FOLLOWLOCATION => false,
            // 64 bytes is plenty to recognise the header and far too little to
            // be worth anyone's bandwidth.
            CURLOPT_RANGE => '0-63',
        ]);
        $body = (string)curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $exposed = ($code === 200 || $code === 206) && str_starts_with($body, 'SQLite format 3');
        Cache::set('data_exposed_db', $exposed ? '1' : '0', 3600);
        if ($exposed) {
            ErrorLog::record(
                ErrorLog::SYSTEM,
                'The database file can be downloaded from the public web - member emails, password '
                . 'hashes, API tokens and payment records are all readable by anyone with the URL',
                'The web server is ignoring .htaccess (nginx always does). Deny /data/ in the server '
                . 'config, or move the data directory above the document root.'
            );
        }
        return $exposed;
    }

    /**
     * Does PHP execute inside the uploads directory?
     *
     * Writes a probe that prints a number only PHP can produce, asks the site
     * for it, and deletes it either way. Nothing can plant a .php file there
     * today - the upload validator takes the extension from getimagesize() -
     * so this is not a live hole; it is the difference between the next
     * mistake being a bug and the next mistake being a shell, and that is
     * worth knowing about before it happens rather than after.
     *
     * The probe is harmless by construction: it prints one arithmetic result
     * and is removed in a finally, including when the request throws.
     */
    public static function uploadsExecute(bool $force = false): ?bool
    {
        if (!$force) {
            $cached = Cache::get('uploads_exec');
            if ($cached !== null) {
                return $cached === '' ? null : (bool)$cached;
            }
        }
        $base = rtrim((string)Database::setting('site_url'), '/');
        $dir  = defined('SMA_UPLOAD_DIR') ? SMA_UPLOAD_DIR : null;
        if ($base === '' || $dir === null || !function_exists('curl_init')) {
            Cache::set('uploads_exec', '', 3600);
            return null;
        }
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            Cache::set('uploads_exec', '', 3600);
            return null;
        }
        $name  = 'probe-' . bin2hex(random_bytes(6)) . '.php';
        $token = bin2hex(random_bytes(6));
        $path  = $dir . '/' . $name;
        if (@file_put_contents($path, '<' . '?php echo "SMA-EXEC-' . $token . '";') === false) {
            Cache::set('uploads_exec', '', 3600);
            return null;
        }
        try {
            $rel = self::relativeTo($path);
            if ($rel === null) {
                Cache::set('uploads_exec', '', 3600);
                return null;         // uploads live outside the docroot already
            }
            $ch = curl_init($base . '/' . $rel);
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 6,
                                    CURLOPT_FOLLOWLOCATION => false]);
            $body = (string)curl_exec($ch);
            curl_close($ch);
            $runs = str_contains($body, 'SMA-EXEC-' . $token)
                    && !str_contains($body, '<' . '?php');
            Cache::set('uploads_exec', $runs ? '1' : '0', 3600);
            if ($runs) {
                ErrorLog::record(
                    ErrorLog::SYSTEM,
                    'PHP runs inside the uploads directory - any file that ever lands there with a '
                    . '.php name would execute on this server',
                    'Nothing can put one there today, but this turns a future upload bug into '
                    . 'remote code execution. Deny script handling under the data directory in the '
                    . 'web server config.'
                );
            }
            return $runs;
        } finally {
            @unlink($path);
        }
    }

    /** A path relative to the document root, or null if it is outside it. */
    private static function relativeTo(string $file): ?string
    {
        if (!defined('SMA_ROOT')) {
            return null;
        }
        $root = realpath(SMA_ROOT);
        $real = realpath($file);
        if ($root === false || $real === false || !str_starts_with($real, $root . DIRECTORY_SEPARATOR)) {
            return null;
        }
        return str_replace('\\', '/', substr($real, strlen($root) + 1));
    }

    /**
     * The database path relative to the document root, or null when the file
     * is not under it at all - which is the layout that has no problem to
     * report in the first place.
     */
    public static function publicPath(): ?string
    {
        if (!defined('SMA_ROOT')) {
            return null;
        }
        $db = self::databaseFile();
        if ($db === null) {
            return null;
        }
        $root = realpath(SMA_ROOT);
        $real = realpath($db);
        if ($root === false || $real === false || !str_starts_with($real, $root . DIRECTORY_SEPARATOR)) {
            return null;                 // outside the docroot: already safe
        }
        return str_replace('\\', '/', substr($real, strlen($root) + 1));
    }

    /**
     * Absolute path of the SQLite file, or null on MySQL.
     *
     * Read from the constants rather than from $config: bootstrap.php returns
     * the array to its caller instead of publishing it globally, so a class
     * reaching for $GLOBALS finds nothing - which is how this check quietly
     * answered "cannot tell" on the exact installs it exists to protect.
     */
    public static function databaseFile(): ?string
    {
        if (!defined('SMA_DATA_DIR')) {
            return null;
        }
        try {
            if (Database::pdo()->getAttribute(\PDO::ATTR_DRIVER_NAME) !== 'sqlite') {
                return null;
            }
        } catch (\Throwable $e) {
            return null;
        }
        // The local override may point the file somewhere else entirely, so
        // ask the connection rather than assuming the default name.
        try {
            foreach (Database::pdo()->query('PRAGMA database_list') as $row) {
                if (($row['name'] ?? '') === 'main' && !empty($row['file'])) {
                    return (string)$row['file'];
                }
            }
        } catch (\Throwable $e) {
            // fall through to the conventional location
        }
        $file = SMA_DATA_DIR . '/signalmasterai.sqlite';
        return is_file($file) ? $file : null;
    }

    /**
     * A filename nobody can guess, for a fresh install.
     *
     * The only layer that helps on a server the app cannot configure. Applied
     * at install time only: renaming the database of a running site is a way
     * to break it, and an install that already works is better served by the
     * warning above than by a clever rename.
     */
    public static function freshName(): string
    {
        return 'signalmasterai-' . bin2hex(random_bytes(6)) . '.sqlite';
    }

    /**
     * Write the generated administrator password where the operator can read
     * it and nobody else can.
     *
     * The name carries a random component for the same reason the database
     * does. A file called FIRST-LOGIN.txt in a known directory is protected by
     * exactly the one layer this class exists because it cannot trust: on
     * nginx, or Apache with AllowOverride None, `GET /data/FIRST-LOGIN.txt`
     * hands over a working administrator login to whoever asks. The window is
     * short - it closes at the first password change - but it is precisely the
     * window in which a fresh site sits unattended.
     *
     * The operator loses nothing. They reached this directory over FTP or a
     * file manager to install the app; a random suffix is one glance in a
     * listing. An attacker over HTTP has to guess twelve hex characters.
     *
     * Returns the basename written, or null if the directory was not writable.
     */
    public static function writeFirstLogin(string $user, string $password): ?string
    {
        if (!defined('SMA_DATA_DIR') || !is_dir(SMA_DATA_DIR)) {
            return null;
        }
        self::harden();          // deny files first, so the note is never the
                                 // first thing to land in an unprotected dir
        $name = 'FIRST-LOGIN-' . bin2hex(random_bytes(6)) . '.txt';
        $path = SMA_DATA_DIR . '/' . $name;
        $body = "SignalMasterAi created an administrator because none existed.\n\n"
              . "  user:     " . $user . "\n"
              . "  password: " . $password . "\n\n"
              . "Log in, change the password, and this file deletes itself.\n";
        if (@file_put_contents($path, $body) === false) {
            return null;
        }
        @chmod($path, 0600);
        return $name;
    }

    /**
     * Absolute path of the generated-password note, or null when there isn't
     * one. Falls back to the old fixed name so an install created before the
     * random suffix still finds - and still deletes - its own file.
     */
    public static function firstLoginFile(): ?string
    {
        if (!defined('SMA_DATA_DIR')) {
            return null;
        }
        $names = [];
        $stored = (string)Database::setting('admin_first_login_file', '');
        if ($stored !== '') {
            $names[] = basename($stored);
        }
        $names[] = 'FIRST-LOGIN.txt';
        foreach ($names as $n) {
            $p = SMA_DATA_DIR . '/' . $n;
            if (is_file($p)) {
                return $p;
            }
        }
        return null;
    }

    /**
     * Remove the note once the password it holds is no longer the password.
     * Clears the stored name too, so nothing keeps pointing at a file that is
     * gone.
     */
    public static function clearFirstLogin(): void
    {
        $p = self::firstLoginFile();
        if ($p !== null) {
            @unlink($p);
        }
        Database::setSetting('admin_first_login_file', '');
    }
}
