<?php
declare(strict_types=1);

/**
 * TECHBISS — renewal reminder.
 *
 * The admin dashboard already shows domain, hosting, SSL and maintenance
 * renewals coming due, but only to whoever happens to open it. This is the
 * same check run from the command line so it can email the notification
 * address proactively — nothing is sent, and nothing is printed, when
 * nothing is due.
 *
 * Meant to run daily from cron, e.g.:
 *   0 8 * * * php /path/to/tools/check-renewals.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

define('TB_ROOT', dirname(__DIR__));
require TB_ROOT . '/includes/bootstrap.php';

use Techbiss\Core\App;
use Techbiss\Repo\ClientProjectRepo;

$rows = (new ClientProjectRepo())->dueSoon(30);
if ($rows === []) {
    exit(0);
}

$to = App::settings()->get('notification_email') ?: App::settings()->get('contact_email');
if ($to === '') {
    fwrite(STDERR, count($rows) . " renewal(s) due, but no notification email is set in Settings — nothing sent.\n");
    exit(1);
}

$lines = ['The following are due within 30 days, or already overdue:', ''];
foreach ($rows as $row) {
    $overdue = strtotime((string) $row['due_date']) < strtotime('today');
    $lines[] = sprintf(
        '- %s — %s, due %s%s (client: %s)',
        $row['name'],
        $row['due_type'],
        date('j M Y', strtotime((string) $row['due_date'])),
        $overdue ? ' — OVERDUE' : '',
        $row['customer_name'] ?: 'none on file'
    );
}
// App::siteUrl() detects the address from the request, which does not exist
// here — only trust it when the site's own config names an address outright.
$siteUrl = trim((string) App::config('site.url', ''));
if ($siteUrl !== '') {
    $lines[] = '';
    $lines[] = 'Open the dashboard for contact details: ' . rtrim($siteUrl, '/') . '/admin/client_projects';
}

$subject = count($rows) . ' renewal' . (count($rows) === 1 ? '' : 's') . ' due soon';
$sent    = App::mailer()->send($to, $subject, implode("\n", $lines));

echo $sent
    ? 'Sent to ' . $to . ' — ' . count($rows) . " item(s).\n"
    : "Could not send — check the mail driver in config/config.php.\n";
exit($sent ? 0 : 1);
