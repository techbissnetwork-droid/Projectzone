<?php
declare(strict_types=1);

return [
    // "log" writes to storage/logs/mail.log; "mail" also hands off to PHP mail().
    'driver' => 'log',
    'from_address' => 'hello@techbiss.com',
    'from_name' => 'TECHBISS',
    'sales_inbox' => 'sales@techbiss.com',
    'support_inbox' => 'support@techbiss.com',
];
