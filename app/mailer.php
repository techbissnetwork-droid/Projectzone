<?php
/**
 * Sends the enquiry notification with PHP's mail(), which is what shared and
 * cPanel hosting provides. The From address must be on your own domain or the
 * host will reject the message.
 */

function send_enquiry_mail(array $enquiry): bool
{
    $cfg  = config('mail');
    $to   = $cfg['to'] ?? '';
    $from = $cfg['from'] ?? '';
    if ($to === '' || $from === '' || !function_exists('mail')) {
        return false;
    }

    $subject = 'New enquiry — ' . $enquiry['name'];

    $body = implode("\n", [
        'A new enquiry came in from the website.',
        '',
        'Name:     ' . $enquiry['name'],
        'Email:    ' . $enquiry['email'],
        'Business: ' . ($enquiry['company'] ?: '—'),
        'Phone:    ' . ($enquiry['phone'] ?: '—'),
        'Needs:    ' . ($enquiry['service'] ?: '—'),
        'Budget:   ' . ($enquiry['budget'] ?: '—'),
        '',
        'Message:',
        $enquiry['message'],
        '',
        '—',
        'Received ' . $enquiry['created_at'] . ' from ' . $enquiry['ip'],
    ]);

    // Header injection guard: a newline in either field would let a crafted
    // name or address append arbitrary headers.
    $name = str_replace(["\r", "\n"], ' ', $cfg['from_name'] ?? 'Website');
    $replyTo = filter_var($enquiry['email'], FILTER_VALIDATE_EMAIL) ? $enquiry['email'] : $from;

    $headers = implode("\r\n", [
        'From: ' . sprintf('%s <%s>', $name, $from),
        'Reply-To: ' . $replyTo,
        'Content-Type: text/plain; charset=UTF-8',
        'X-Mailer: TECHBISS site',
    ]);

    $subject = str_replace(["\r", "\n"], ' ', $subject);

    return @mail($to, $subject, $body, $headers, '-f' . $from);
}
