<?php
declare(strict_types=1);

namespace SignalMasterAi;

/**
 * Moving the private files out of the web root, and knowing whether they are.
 *
 * THE PROBLEM THIS EXISTS FOR.
 *
 * data/ ships inside the web root with an .htaccess denying it. nginx does not
 * read .htaccess at all, and Apache ignores it unless AllowOverride is on, so
 * on a large share of real hosting that deny rule is decoration: the SQLite
 * database - every member email, every password hash, API tokens, payment
 * records - is downloadable by anybody who asks for the URL. The dashboard
 * check finds this by fetching the site's own URL, because the rule EXISTING
 * and the rule WORKING are different facts.
 *
 * Telling the operator to edit their server configuration is a real answer and
 * it is the one the panel gave. It is also an answer a lot of people on shared
 * hosting cannot act on, and the site is exposed for as long as they cannot. So
 * the application can do it itself: put the files where no server config will
 * ever serve them, which is anywhere above the document root.
 *
 * HOW THE MOVE IS DONE, AND WHY IN THAT ORDER.
 *
 * rename() first, always. A copy-then-delete leaves the database readable at
 * the old URL for the whole length of the copy, and leaves it there for ever if
 * the delete fails - which is the exact problem being fixed, made permanent. A
 * rename on the same filesystem is atomic: one instant the files are inside the
 * web root, the next they are not, and there is no window and no leftover.
 *
 * Copy-and-delete is the fallback for a target on another filesystem, and it
 * verifies the copy before removing anything.
 *
 * If the pointer file cannot be written after the move, the move is UNDONE
 * rather than reported: an install whose data has been moved and which cannot
 * be told where is an install that does not start.
 */
class DataDir
{
    /** The file that records a moved location. One absolute path. */
    public static function pointerFile(): string
    {
        return SMA_ROOT . '/data-path.php';
    }

    /** Is the data directory currently inside the web root? */
    public static function insideWebRoot(): bool
    {
        $data = realpath(SMA_DATA_DIR) ?: SMA_DATA_DIR;
        $root = realpath(SMA_ROOT) ?: SMA_ROOT;
        return str_starts_with(rtrim($data, '/') . '/', rtrim($root, '/') . '/');
    }

    /**
     * Where to suggest moving it.
     *
     * Beside the web root rather than inside anything: a sibling of the site
     * folder is above the document root on every layout this runs on, and
     * naming it after the site keeps two installs on one account apart.
     */
    public static function suggestTarget(): string
    {
        $root = rtrim(realpath(SMA_ROOT) ?: SMA_ROOT, '/');
        return dirname($root) . '/' . basename($root) . '-data';
    }

    /**
     * Can we move to this path? Returns [ok, reason].
     *
     * Checked before anything is touched, and checked again inside move(),
     * because the answer can change between a page load and a form post.
     *
     * @return array{0:bool,1:string}
     */
    public static function check(string $target): array
    {
        $target = rtrim(trim($target), '/');
        if ($target === '' || $target[0] !== '/') {
            return [false, 'Give a full path, starting with /.'];
        }
        $root = rtrim(realpath(SMA_ROOT) ?: SMA_ROOT, '/');
        // The whole point is to leave the web root. A target inside it - or the
        // web root itself - would pass every other check and fix nothing.
        if (str_starts_with($target . '/', $root . '/')) {
            return [false, 'That is inside the site folder, so the web server could still serve it. '
                         . 'Choose a directory above it.'];
        }
        if (file_exists($target)) {
            // An existing directory is only safe if it is empty: writing into
            // somebody's home directory or another install's data would merge
            // two sets of files with no way to tell them apart afterwards.
            if (!is_dir($target)) {
                return [false, 'A file already exists at that path.'];
            }
            $kids = @scandir($target);
            if ($kids === false) {
                return [false, 'That directory exists but cannot be read.'];
            }
            if (array_diff($kids, ['.', '..'])) {
                return [false, 'That directory already has something in it. Choose an empty or new path.'];
            }
        }
        $parent = dirname($target);
        if (!is_dir($parent)) {
            return [false, 'The folder above it (' . $parent . ') does not exist.'];
        }
        if (!is_writable($parent)) {
            return [false, 'PHP cannot write into ' . $parent . '. Most hosts allow the folder that '
                         . 'contains your site; if this one does not, the server config route is the '
                         . 'only one left.'];
        }
        if (!is_dir(SMA_DATA_DIR)) {
            return [false, 'The current data directory is missing.'];
        }
        if (!is_writable(dirname(SMA_DATA_DIR)) || !is_writable(SMA_DATA_DIR)) {
            return [false, 'PHP cannot move the current data directory - it is not writable.'];
        }
        if (!is_writable(SMA_ROOT)) {
            return [false, 'PHP cannot write ' . basename(self::pointerFile()) . ' into the site '
                         . 'folder, and without it the site cannot find its data after the move.'];
        }
        return [true, ''];
    }

    /**
     * Do the move. Returns [ok, message, newPath].
     *
     * @return array{0:bool,1:string,2:string}
     */
    public static function move(string $target): array
    {
        $target = rtrim(trim($target), '/');
        [$ok, $why] = self::check($target);
        if (!$ok) {
            return [false, $why, ''];
        }
        $from = rtrim(realpath(SMA_DATA_DIR) ?: SMA_DATA_DIR, '/');

        // Everything that must still be there afterwards, counted before.
        $before = self::inventory($from);

        $moved = false;
        if (@rename($from, $target)) {
            $moved = true;
        } else {
            // Different filesystem. Copy, verify, and only then remove - in
            // that order, because a delete before the verify is how a failed
            // move becomes a lost database.
            if (!self::copyTree($from, $target)) {
                self::removeTree($target);
                return [false, 'Could not copy the files. Nothing has been changed.', ''];
            }
            $after = self::inventory($target);
            if ($after['files'] !== $before['files'] || $after['bytes'] !== $before['bytes']) {
                self::removeTree($target);
                return [false, sprintf(
                    'The copy did not match the original (%d files / %s bytes against %d / %s), '
                    . 'so nothing has been changed.',
                    $after['files'], number_format($after['bytes']),
                    $before['files'], number_format($before['bytes'])), ''];
            }
            if (!self::removeTree($from)) {
                // The copy is good but the original is still there - and still
                // downloadable, which is the thing being fixed. Undo, so the
                // operator is left in the state they started in rather than
                // with two copies and no idea which one is live.
                self::removeTree($target);
                return [false, 'The files were copied but the originals could not be deleted, which '
                             . 'would have left the database readable at the old address. The copy '
                             . 'has been removed and nothing has been changed.', ''];
            }
            $moved = true;
        }
        if (!$moved) {
            return [false, 'The move did not happen. Nothing has been changed.', ''];
        }

        // Tell the site where its data went. If this fails the site cannot
        // start, so put everything back rather than leave it broken.
        $php = "<?php\n"
             . "// Written by the admin panel: where SignalMasterAi keeps its private files.\n"
             . "// One absolute path. Delete this file to go back to the shipped data/ folder\n"
             . "// (move the files back first, or the site will start with an empty database).\n"
             . 'return ' . var_export($target, true) . ";\n";
        if (@file_put_contents(self::pointerFile(), $php) === false) {
            @rename($target, $from);
            return [false, 'The files moved but ' . basename(self::pointerFile()) . ' could not be '
                         . 'written, so the site would not have been able to find them. Everything '
                         . 'has been put back.', ''];
        }
        @chmod(self::pointerFile(), 0644);

        // Read it back the way the next request will, rather than trusting the
        // write. A pointer that does not load is the same as no pointer.
        $readBack = @include self::pointerFile();
        if (!is_string($readBack) || rtrim($readBack, '/') !== $target || !is_dir($target)) {
            @unlink(self::pointerFile());
            @rename($target, $from);
            return [false, 'The pointer file did not read back correctly, so everything has been '
                         . 'put back exactly as it was.', ''];
        }

        return [true, sprintf('Moved %d file(s), %s, to %s. The web server has no route to them '
                            . 'there, whatever its configuration says.',
                            $before['files'], self::bytes($before['bytes']), $target), $target];
    }

    /** Files and total size under a directory. @return array{files:int,bytes:int} */
    public static function inventory(string $dir): array
    {
        $files = 0;
        $bytes = 0;
        if (!is_dir($dir)) {
            return ['files' => 0, 'bytes' => 0];
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST);
        foreach ($it as $f) {
            if ($f->isFile()) {
                $files++;
                $bytes += (int)$f->getSize();
            }
        }
        return ['files' => $files, 'bytes' => $bytes];
    }

    private static function copyTree(string $from, string $to): bool
    {
        if (!is_dir($to) && !@mkdir($to, 0775, true) && !is_dir($to)) {
            return false;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($from, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST);
        foreach ($it as $item) {
            $dest = $to . '/' . $it->getSubPathname();
            if ($item->isDir()) {
                if (!is_dir($dest) && !@mkdir($dest, 0775, true) && !is_dir($dest)) {
                    return false;
                }
            } elseif (!@copy($item->getPathname(), $dest)) {
                return false;
            }
        }
        return true;
    }

    private static function removeTree(string $dir): bool
    {
        if (!is_dir($dir)) {
            return true;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $item) {
            $done = $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
            if (!$done) {
                return false;
            }
        }
        return @rmdir($dir);
    }

    private static function bytes(int $n): string
    {
        foreach ([['GB', 1073741824], ['MB', 1048576], ['KB', 1024]] as [$unit, $div]) {
            if ($n >= $div) {
                return round($n / $div, 1) . ' ' . $unit;
            }
        }
        return $n . ' bytes';
    }
}
