<?php
require_once __DIR__ . '/../includes/db.php';

// Only allowed while the site has never successfully completed install —
// once install.lock exists this is a permanent no-op, so a stray or
// rediscovered /install/ folder can never be used to wipe a live site's
// configuration.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !file_exists(INSTALL_LOCK_PATH)) {
    if (csrf_check((string)($_POST['csrf'] ?? '')) && file_exists(CONFIG_PATH)) {
        @unlink(CONFIG_PATH);
    }
}

header('Location: ./');
