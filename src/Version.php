<?php
declare(strict_types=1);

namespace SignalMasterAi;

/**
 * Which build is actually on the server.
 *
 * Written because a fix and a report of the same fault can only be told apart
 * if both sides know what is running. "Still broken" after an upload means one
 * of two very different things - the fix is wrong, or the fix is not there -
 * and there was nothing on this site that answered which.
 *
 * BUILD is bumped by hand at release. The file date beside it is the honest
 * cross-check: it is the mtime of the class that changes in almost every
 * release, so an upload that dropped half the files shows up as a stamp that
 * disagrees with itself.
 */
class Version
{
    public const BUILD = '2026-08-13.89';

    /** Human-readable build line for the admin panel. */
    public static function stamp(): string
    {
        $t = @filemtime(__DIR__ . '/Database.php') ?: 0;
        return self::BUILD . ($t > 0 ? ' (files uploaded ' . gmdate('j M Y H:i', $t) . ' UTC)' : '');
    }
}
