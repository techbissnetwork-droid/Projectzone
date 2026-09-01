<?php
declare(strict_types=1);

require __DIR__ . '/_admin.php';

\SignalMasterAi\Auth::logout();
header('Location: login.php');
exit;
