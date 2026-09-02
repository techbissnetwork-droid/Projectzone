<?php
/**
 * The scheduled job. Point cPanel → Cron Jobs at it once a day:
 *
 *   /usr/local/bin/php /home/ACCOUNT/public_html/cron.php --token=YOUR_TOKEN
 *
 * or, if your host only offers URL-based cron:
 *
 *   curl -s "https://yourdomain.com/cron.php?token=YOUR_TOKEN"
 *
 * The token is in the admin area under Settings → Renewal reminders. Without a
 * matching token this does nothing at all, so the URL is safe to leave public.
 */

require_once __DIR__ . '/app/bootstrap.php';

$cli = PHP_SAPI === 'cli';

/* --- work out the token, from the query string or the command line ----- */
$given = get('token');
if ($cli) {
    foreach ($argv ?? [] as $arg) {
        if (str_starts_with($arg, '--token=')) {
            $given = substr($arg, 8);
        }
    }
}
$dryRun = get('dry') === '1' || in_array('--dry-run', $argv ?? [], true);

/* --- refuse politely if not set up ------------------------------------- */
if (!is_installed()) {
    cron_out($cli, 503, "This site is not installed yet.\n");
}

$expected = setting('cron.token', '');
if ($expected === '') {
    cron_out($cli, 503,
        "No cron token is set. Open Settings → Renewal reminders in the admin area and\n"
        . "generate one, then put it in your cron command.\n");
}
if (!is_string($given) || $given === '' || !hash_equals($expected, $given)) {
    /* Deliberately vague, and the same response either way. */
    cron_out($cli, 403, "Not authorised.\n");
}

if (setting('reminders.enabled', '1') !== '1') {
    cron_out($cli, 200, "Renewal reminders are switched off in Settings. Nothing done.\n");
}

/* --- do the work -------------------------------------------------------- */
$started = microtime(true);
$summary = run_reminders($dryRun);

$report = sprintf(
    "%sRenewal reminders — %s\n"
    . "  renewal dates checked : %d\n"
    . "  inside a reminder window: %d\n"
    . "  already sent, skipped : %d\n"
    . "  sent now              : %d\n"
    . "  failed to send        : %d\n"
    . "  took                  : %.2fs\n",
    $dryRun ? "DRY RUN — nothing was sent\n" : '',
    date('Y-m-d H:i'),
    $summary['checked'], $summary['due'], $summary['skipped'],
    $summary['sent'], $summary['failed'], microtime(true) - $started
);

foreach ($summary['lines'] as $line) {
    $report .= '  ' . $line . "\n";
}

if ($summary['failed'] > 0) {
    $report .= "\nAnything that failed was NOT recorded, so the next run will try it again.\n"
             . "If it keeps failing, check the 'send mail from' address is a mailbox on your\n"
             . "own domain.\n";
}

log_activity(sprintf('Cron: %d reminder(s) sent, %d failed', $summary['sent'], $summary['failed']),
    'system', 0, 'Cron');

cron_out($cli, 200, $report);


function cron_out(bool $cli, int $status, string $text): void
{
    if (!$cli) {
        http_response_code($status);
        header('Content-Type: text/plain; charset=UTF-8');
    }
    echo $text;
    exit($cli && $status >= 400 ? 1 : 0);
}
