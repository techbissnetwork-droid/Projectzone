<?php
declare(strict_types=1);

namespace SignalMasterAi;

/**
 * Is every file this application needs actually on this server?
 *
 * An upload that stops halfway is the most common way one of these installs
 * breaks, and it is close to the worst-behaved failure it has. PHP does not
 * refuse to run: every page that does not touch the absent class works
 * perfectly, so the site looks fine. What stops is whatever that one file did.
 *
 * A real case, and the reason this class exists: five files did not make it up
 * - Mailer, Telegram, Outcomes, Paper, Broadcast. The site served pages, the
 * admin panel opened, signals appeared on the chart. No alert email went out,
 * no Telegram message, and no trade was ever settled, for weeks. The error log
 * had 225 lines of "a watched pair has never been analysed", which describes
 * the symptom perfectly and names none of the five files.
 *
 * So: check the whole set, up front, and say which ones are missing.
 *
 * THE LIST IS NOT WRITTEN DOWN ANYWHERE. It is read out of the code on each
 * check, by finding every SignalMasterAi class the application mentions. A
 * hand-maintained manifest is a second thing to keep in step with the first,
 * and the day it falls behind is the day this check says "all present" about
 * a file that has been required since the release after the list was written.
 * Derived from the code, it cannot drift: a class that nothing refers to is
 * not needed, and one that something refers to is found by the same search
 * that PHP's autoloader would do.
 */
class Integrity
{
    /** Directories scanned for class references, relative to the app root. */
    private const SCAN = ['', 'admin', 'src'];

    /**
     * Classes that are missing from src/, with the files that ask for them.
     *
     * Returns [] when everything is present - which is the answer on a healthy
     * install, so the caller can treat a non-empty array as "say something".
     *
     * @return array<string, list<string>> class name => files referencing it
     */
    public static function missingClasses(): array
    {
        $root = dirname(__DIR__);
        $present = [];
        foreach (glob($root . '/src/*.php') ?: [] as $f) {
            $present[basename($f, '.php')] = true;
        }

        $missing = [];
        foreach (self::phpFiles($root) as $file) {
            $src = @file_get_contents($file);
            if ($src === false) {
                continue;
            }
            foreach (self::referencedClasses($src) as $class) {
                if (isset($present[$class])) {
                    continue;
                }
                $rel = ltrim(substr($file, strlen($root)), '/\\');
                $missing[$class][] = $rel;
            }
        }
        foreach ($missing as $class => $files) {
            $missing[$class] = array_values(array_unique($files));
            sort($missing[$class]);
        }
        ksort($missing);
        return $missing;
    }

    /**
     * The same answer, cached, for the dashboard.
     *
     * The scan reads every PHP file in the install - fine as a deliberate
     * check, wasteful on every page load of a panel somebody leaves open. Six
     * hours, because the thing it detects is a bad upload, and a bad upload
     * does not repair itself between two page loads. `$force` is what the
     * "check again" button passes after the operator has re-uploaded.
     *
     * @return array<string, list<string>>
     */
    public static function missingClassesCached(bool $force = false): array
    {
        if (!$force) {
            $hit = Cache::get('integrity_missing');
            if (is_array($hit)) {
                return $hit;
            }
        }
        $missing = self::missingClasses();
        Cache::set('integrity_missing', $missing, 6 * 3600);
        return $missing;
    }

    /**
     * One line for an operator, or '' when there is nothing wrong.
     *
     * Names the files. "Some files are missing" sends somebody to compare two
     * directory listings by eye; "src/Mailer.php, src/Paper.php" is a thing
     * they can drag into an FTP window.
     */
    public static function summary(bool $force = false): string
    {
        $missing = self::missingClassesCached($force);
        if (!$missing) {
            return '';
        }
        $files = [];
        foreach (array_keys($missing) as $class) {
            $files[] = 'src/' . $class . '.php';
        }
        return implode(', ', $files);
    }

    /**
     * Which parts of the site stop working, in the operator's terms.
     *
     * A missing class name means nothing to somebody who did not write this.
     * "Alert emails" does. Anything not on this list still gets reported by
     * filename; the map is for the ones with consequences worth spelling out.
     *
     * @param array<string, list<string>> $missing
     * @return list<string>
     */
    public static function consequences(array $missing): array
    {
        $map = [
            'Mailer'       => 'No email at all - alerts, verification codes and password resets',
            'EmailAlerts'  => 'No signal alert emails',
            'MemberVerify' => 'Nobody can finish registering (no verification codes)',
            'Telegram'     => 'No Telegram delivery',
            'WebPush'      => 'No browser push notifications',
            'Outcomes'     => 'No trade is ever settled, so the performance page and the self-learning both stall',
            'Paper'        => 'The paper portfolio stops tracking',
            'Broadcast'    => 'Nothing is posted to your public channels',
            'Payments'     => 'Nobody can pay - checkout is dead',
            'Gateways'     => 'The non-Cryptomus payment gateways stop taking callbacks',
            'SignalEngine' => 'No signals are produced at all',
            'MarketData'   => 'No prices or candles can be fetched',
            'Referrals'    => 'Referral credit is not paid',
        ];
        $out = [];
        foreach (array_keys($missing) as $class) {
            if (isset($map[$class])) {
                $out[] = $map[$class];
            }
        }
        return $out;
    }

    /** Every .php file in the app, skipping the data directory. */
    private static function phpFiles(string $root): array
    {
        $files = [];
        foreach (self::SCAN as $dir) {
            $path = $root . ($dir === '' ? '' : '/' . $dir);
            foreach (glob($path . '/*.php') ?: [] as $f) {
                $files[] = $f;
            }
        }
        return $files;
    }

    /**
     * Class names this source refers to, read with PHP's own tokenizer.
     *
     * Tokenised rather than pattern-matched, and the difference is not
     * theoretical - the first version of this method reported src/Foo.php as
     * missing from a complete install, because Foo:: appears in the comment
     * eight lines above explaining the method. A check that cries wolf about a
     * file that was never meant to exist teaches the operator to ignore it,
     * which costs more than not having the check.
     *
     * The tokenizer hands back comments and string literals as their own token
     * types, so skipping them is not a heuristic. What is left is code.
     *
     * Three spellings are collected, because the code uses all three: a `use`
     * import, a qualified SignalMasterAi\Name, and - inside src/, where every
     * file already sits in the namespace - a bare Name::. Missing that third
     * one would pass an install with no SignalEngine.php, since nearly
     * everything that calls it is in the namespace too.
     *
     * @return list<string>
     */
    private static function referencedClasses(string $src): array
    {
        $tokens = @token_get_all($src);
        if (!is_array($tokens)) {
            return [];
        }
        // ALIASES ARE NOT FILES.
        //
        // "use SignalMasterAi\\Database as D;" then "D::pdo()" is ordinary PHP,
        // and the scan below reads that D:: as a class reference and demands
        // src/D.php. It then renders the panel-wide "files are missing" page,
        // telling the operator to re-upload src/ - which cannot help, because
        // the file it names was never meant to exist. Any stray snippet in the
        // install folder that uses an alias locks the admin panel out with
        // advice that does nothing. Observed while adding the trial: a
        // throwaway script using this exact idiom took the whole panel down.
        //
        // The alias's real target is already counted from the qualified name
        // in the use statement itself, so dropping the alias loses nothing.
        $aliases = [];
        $n = count($tokens);
        for ($i = 0; $i < $n; $i++) {
            if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_AS) {
                continue;
            }
            for ($j = $i + 1; $j < $n; $j++) {
                if (is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
                    continue;
                }
                if (is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
                    $aliases[$tokens[$j][1]] = true;
                }
                break;
            }
        }

        $found = [];
        $count = count($tokens);
        for ($i = 0; $i < $count; $i++) {
            $t = $tokens[$i];
            if (!is_array($t)) {
                continue;
            }
            // Qualified names arrive whole: "SignalMasterAi\Mailer".
            if ($t[0] === T_NAME_QUALIFIED || $t[0] === T_NAME_FULLY_QUALIFIED) {
                $parts = explode('\\', trim($t[1], '\\'));
                if (count($parts) === 2 && $parts[0] === 'SignalMasterAi') {
                    $found[] = $parts[1];
                }
                continue;
            }
            // A bare name is only a class reference when a :: follows it.
            if ($t[0] === T_STRING) {
                for ($j = $i + 1; $j < $count; $j++) {
                    $n = $tokens[$j];
                    if (is_array($n) && $n[0] === T_WHITESPACE) {
                        continue;
                    }
                    if (is_array($n) && $n[0] === T_DOUBLE_COLON && $t[1][0] === strtoupper($t[1][0])
                        && !isset($aliases[$t[1]])) {
                        $found[] = $t[1];
                    }
                    break;
                }
            }
        }
        // PHP's own classes are not ours to ship, so their absence is not a
        // failed upload. Anything else capitalised and called statically is.
        $notOurs = ['PDO', 'PDOStatement', 'PDOException', 'DateTime', 'DateTimeImmutable',
            'DateTimeZone', 'DateInterval', 'Exception', 'RuntimeException', 'LogicException',
            'InvalidArgumentException', 'Throwable', 'Error', 'TypeError', 'ValueError',
            'JsonException', 'Closure', 'Generator', 'ArrayObject', 'SplQueue', 'SplFileObject',
            'ZipArchive', 'SimpleXMLElement', 'DOMDocument', 'IntlDateFormatter', 'NumberFormatter',
            'Normalizer', 'Collator', 'Random', 'Stringable', 'Countable', 'Iterator',
            'IteratorAggregate', 'ArrayAccess', 'JsonSerializable', 'Traversable', 'Serializable'];
        return array_values(array_diff(array_unique($found), $notOurs));
    }
}
