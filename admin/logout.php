<?php
require_once __DIR__ . '/../includes/db.php';

/**
 * A deploy that replaces some files but not others leaves this page calling
 * into an includes/ folder from an older release, which PHP answers with a
 * blank 500 and no clue. Say what actually happened instead. asset_version()
 * is the canary: it lives in includes/db.php and arrived with this release.
 */
if (!function_exists('asset_version')) {
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    exit('<!doctype html><html><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>Update incomplete</title></head>'
        . '<body style="font:16px/1.6 system-ui,sans-serif;max-width:640px;margin:12vh auto;padding:0 24px;color:#2c2318;">'
        . '<h1 style="font-size:1.4rem;">This update was uploaded incomplete</h1>'
        . '<p>The <code>includes/</code> folder on this server is from an older release than the rest of the files, '
        . 'so the site is calling functions that do not exist yet.</p>'
        . '<p><b>Fix:</b> upload the release again and let it overwrite <em>every</em> folder — '
        . '<code>includes/</code>, <code>assets/</code>, <code>admin/</code>, <code>api/</code> and <code>install/</code>. '
        . 'Some file managers silently skip folders that already exist, which is how this happens.</p>'
        . '<p style="color:#786a5b;font-size:.9rem;">Nothing is broken in the database — this is only about which files are on disk.</p>'
        . '</body></html>');
}

unset($_SESSION['staff_id']);
session_regenerate_id(true);

header('Location: login.php');
