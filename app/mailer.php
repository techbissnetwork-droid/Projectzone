<?php
/**
 * Mail. Uses PHP's mail() because that is what shared hosting gives you.
 *
 * Nothing depends on mail succeeding — every enquiry, order and ticket is
 * written to the database first, and the admin dashboard reports anything
 * that could not be sent.
 */

function mail_from(): string
{
    $cfg = config();
    return $cfg['mail_from'] ?? ('website@' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
}

function mail_to(): string
{
    $cfg = config();
    return $cfg['mail_to'] ?? setting('site.email', 'hello@techbiss.com');
}

/**
 * Send a plain-text email. Returns true when the server accepted it.
 * Never throws — callers treat failure as "note it and move on".
 */
function send_mail(string $to, string $subject, string $body, ?string $replyTo = null): bool
{
    if (!valid_email($to)) {
        return false;
    }
    $from    = mail_from();
    $headers = [
        'From: TECHBISS <' . $from . '>',
        'Content-Type: text/plain; charset=UTF-8',
        'MIME-Version: 1.0',
        'X-Mailer: TECHBISS',
    ];
    if ($replyTo && valid_email($replyTo)) {
        $headers[] = 'Reply-To: ' . $replyTo;
    }

    $subject = str_replace(["\r", "\n"], '', $subject);
    $body    = wordwrap(str_replace("\r\n", "\n", $body), 76, "\n", false);

    try {
        return @mail($to, $subject, $body, implode("\r\n", $headers), '-f' . $from);
    } catch (Throwable $e) {
        error_log('[techbiss] mail failed: ' . $e->getMessage());
        return false;
    }
}

/** Tell the office about a new enquiry. */
function mail_enquiry(array $e): bool
{
    $body = "New enquiry from the website.\n\n"
          . "Name:    {$e['name']}\n"
          . "Email:   {$e['email']}\n"
          . "Phone:   " . ($e['phone'] ?: '—') . "\n"
          . "Company: " . ($e['company'] ?: '—') . "\n"
          . "Service: " . ($e['service'] ?: '—') . "\n"
          . "Budget:  " . ($e['budget'] ?: '—') . "\n\n"
          . "Message:\n{$e['message']}\n\n"
          . "— Read and reply in the admin area: " . url('admin/enquiries.php') . "\n";
    return send_mail(mail_to(), 'Website enquiry — ' . $e['name'], $body, $e['email']);
}

/** Tell the office about a marketplace order. */
function mail_order(array $o, ?array $product): bool
{
    $body = "New marketplace order.\n\n"
          . "Reference: {$o['reference']}\n"
          . "Project:   " . ($product['title'] ?? 'Unknown') . "\n"
          . "Amount:    " . money($o['amount']) . "\n"
          . "Setup:     " . ($o['wants_setup'] ? 'Yes — wants us to set it up' : 'No') . "\n\n"
          . "Buyer:     {$o['buyer_name']}\n"
          . "Email:     {$o['buyer_email']}\n"
          . "Phone:     " . ($o['buyer_phone'] ?: '—') . "\n"
          . "Company:   " . ($o['buyer_company'] ?: '—') . "\n\n"
          . "Notes:\n" . ($o['notes'] ?: '—') . "\n\n"
          . "— Manage it here: " . url('admin/orders.php') . "\n";
    return send_mail(mail_to(), 'Marketplace order ' . $o['reference'], $body, $o['buyer_email']);
}

/** Confirm an order to the person who placed it. */
function mail_order_receipt(array $o, ?array $product): bool
{
    $body = "Thanks — we have your order.\n\n"
          . "Reference: {$o['reference']}\n"
          . "Project:   " . ($product['title'] ?? '') . "\n"
          . "Amount:    " . money($o['amount']) . "\n\n"
          . "Nothing has been charged yet. We will reply within one business day with payment\n"
          . "details and, if you asked for it, a date for setting the site up on your own\n"
          . "domain and hosting.\n\n"
          . "— TECHBISS\n";
    return send_mail($o['buyer_email'], 'Your order ' . $o['reference'] . ' — TECHBISS', $body);
}

/** Send a new client their portal login. */
function mail_client_welcome(array $user, string $tempPassword, ?array $project): bool
{
    $body = "Your TECHBISS account is ready.\n\n"
          . "Sign in here: " . url('client/login.php') . "\n"
          . "Email:        {$user['email']}\n"
          . "Password:     {$tempPassword}\n\n"
          . "Please change that password the first time you sign in.\n\n";
    if ($project) {
        $body .= "Your project: {$project['name']}\n";
        if ($project['domain']) {
            $body .= "Domain:       {$project['domain']}\n";
        }
        $body .= "\nInside the portal you can see your domain, hosting, SSL and email renewal\n"
               . "dates, raise a support or maintenance request, and message us directly.\n\n";
    }
    $body .= "— TECHBISS\n";
    return send_mail($user['email'], 'Your TECHBISS account', $body);
}

/** Tell the other side that a ticket has a new reply. */
function mail_ticket_reply(array $ticket, string $body, bool $toClient, ?array $client): bool
{
    if ($toClient) {
        if (!$client) {
            return false;
        }
        $text = "We replied to your request \"{$ticket['subject']}\".\n\n"
              . $body . "\n\n"
              . "Read it and reply here: " . url('client/ticket.php?id=' . $ticket['id']) . "\n";
        return send_mail($client['email'], 'Re: ' . $ticket['subject'] . ' [' . $ticket['reference'] . ']', $text);
    }
    $text = "New client message on {$ticket['reference']} — {$ticket['subject']}\n\n"
          . $body . "\n\n"
          . url('admin/ticket.php?id=' . $ticket['id']) . "\n";
    return send_mail(mail_to(), '[' . $ticket['reference'] . '] ' . $ticket['subject'], $text);
}
