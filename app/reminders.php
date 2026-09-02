<?php
/**
 * Renewal reminders.
 *
 * The whole promise of the business is that nothing lapses quietly, so this is
 * the part that actually keeps it. cron.php calls run_reminders() on a
 * schedule; every reminder that goes out is recorded, so the same one is never
 * sent twice however often cron runs.
 */

/** How many days before a renewal each reminder goes out. */
function reminder_stages(): array
{
    $raw    = setting('reminders.days', '45,14,3');
    $stages = [];
    foreach (explode(',', $raw) as $d) {
        $d = (int) trim($d);
        if ($d > 0) {
            $stages[] = $d;
        }
    }
    rsort($stages);
    return $stages ?: [45, 14, 3];
}

/** Everything that could be reminded about, flattened into one list. */
function reminder_candidates(): array
{
    $out = [];
    foreach (db_all('SELECT * FROM projects') as $p) {
        if ($p['status'] === 'ended') {
            continue;
        }
        $items = [
            ['domain',  'Domain',     $p['domain_expires_on'],  $p['domain_registrar']],
            ['hosting', 'Hosting',    $p['hosting_expires_on'], $p['hosting_provider']],
            ['ssl',     'SSL',        $p['ssl_expires_on'],     $p['ssl_provider']],
            ['email',   'Email',      $p['email_expires_on'],   $p['email_provider']],
            ['care',    'Care plan',  $p['care_renews_on'],     $p['care_plan']],
        ];
        foreach ($items as [$kind, $label, $date, $provider]) {
            if (!$date) {
                continue;
            }
            $days = days_until($date);
            if ($days === null) {
                continue;
            }
            $out[] = [
                'project'  => $p,
                'kind'     => $kind,
                'label'    => $label,
                'date'     => $date,
                'provider' => $provider,
                'days'     => $days,
            ];
        }
    }
    return $out;
}

/**
 * The reminder stage a due date currently falls in, or null.
 * With stages 45/14/3, something due in 20 days is in the 45 stage; in 10 days
 * it moves to the 14 stage and gets a second reminder. Overdue uses stage 0.
 */
function reminder_stage_for(int $days, array $stages): ?int
{
    if ($days < 0) {
        return 0;
    }
    $hit = null;
    foreach ($stages as $stage) {
        if ($days <= $stage) {
            $hit = $stage;
        }
    }
    return $hit;
}

function reminder_already_sent(int $projectId, string $kind, string $due, int $stage): bool
{
    return db_count(
        'SELECT COUNT(*) FROM reminders_sent
         WHERE project_id = ? AND kind = ? AND due_on = ? AND stage = ?',
        [$projectId, $kind, $due, $stage]
    ) > 0;
}

function reminder_record(int $projectId, string $kind, string $due, int $stage, string $to): void
{
    db_insert('reminders_sent', [
        'project_id' => $projectId,
        'kind'       => $kind,
        'due_on'     => $due,
        'stage'      => $stage,
        'sent_to'    => $to,
        'created_at' => now(),
    ]);
}

function reminder_subject(array $c): string
{
    $p = $c['project'];
    if ($c['days'] < 0) {
        return sprintf('OVERDUE: %s renewal for %s', $c['label'], $p['name']);
    }
    return sprintf('%s renewal in %d days — %s', $c['label'], $c['days'], $p['name']);
}

function reminder_body_internal(array $c): string
{
    $p = $c['project'];
    $when = $c['days'] < 0
        ? sprintf('was due %s (%d days ago)', date_human($c['date']), abs($c['days']))
        : sprintf('is due %s (%d days away)', date_human($c['date']), $c['days']);

    return "A renewal needs your attention.\n\n"
         . "Project:  {$p['name']}\n"
         . "Client:   " . ($p['owner_name'] ?: '—') . " <" . ($p['owner_email'] ?: '—') . ">\n"
         . "Domain:   " . ($p['domain'] ?: '—') . "\n\n"
         . "{$c['label']} {$when}.\n"
         . "Provider: " . ($c['provider'] ?: 'not recorded') . "\n\n"
         . "Open the project: " . url('admin/project-edit.php?id=' . $p['id']) . "\n";
}

function reminder_body_client(array $c): string
{
    $p = $c['project'];
    $when = $c['days'] < 0
        ? 'has just passed its renewal date'
        : sprintf('renews in %d days, on %s', $c['days'], date_human($c['date']));

    return "Hello" . ($p['owner_name'] ? ' ' . explode(' ', $p['owner_name'])[0] : '') . ",\n\n"
         . "A quick note that the {$c['label']} for {$p['name']} {$when}.\n\n"
         . "You do not need to do anything — we handle renewals for you. This is just so\n"
         . "there are no surprises on your card or your invoice.\n\n"
         . "You can see every renewal date for your project here:\n"
         . url('client/project.php') . "\n\n"
         . "Any questions, reply to this or raise it in your portal.\n\n"
         . "— " . setting('site.name', 'TECHBISS') . "\n";
}

/**
 * Send whatever is due. Returns a summary for the caller to print or log.
 * Safe to run as often as you like.
 */
function run_reminders(bool $dryRun = false): array
{
    $stages   = reminder_stages();
    $notify   = setting('reminders.notify_client', '0') === '1';
    $officeTo = mail_to();

    $summary = ['checked' => 0, 'due' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0, 'lines' => []];

    foreach (reminder_candidates() as $c) {
        $summary['checked']++;
        $stage = reminder_stage_for($c['days'], $stages);
        if ($stage === null) {
            continue;   /* too far out to bother anyone about */
        }
        $summary['due']++;

        $p   = $c['project'];
        $key = sprintf('%s %s due %s (stage %d)', $p['name'], $c['label'], $c['date'], $stage);

        if (reminder_already_sent((int) $p['id'], $c['kind'], $c['date'], $stage)) {
            $summary['skipped']++;
            continue;
        }
        if ($dryRun) {
            $summary['lines'][] = 'WOULD SEND: ' . $key;
            continue;
        }

        $ok = send_mail($officeTo, reminder_subject($c), reminder_body_internal($c));

        /* The client copy is optional and never blocks the internal one. */
        if ($notify && $c['days'] >= 0 && valid_email((string) $p['owner_email'])) {
            send_mail(
                $p['owner_email'],
                sprintf('%s renewal for %s', $c['label'], $p['name']),
                reminder_body_client($c)
            );
        }

        if ($ok) {
            reminder_record((int) $p['id'], $c['kind'], $c['date'], $stage, $officeTo);
            $summary['sent']++;
            $summary['lines'][] = 'sent: ' . $key;
        } else {
            /* Not recorded, so the next run tries again. */
            $summary['failed']++;
            $summary['lines'][] = 'FAILED: ' . $key;
        }
    }

    setting_set('reminders.last_run', now());
    return $summary;
}
