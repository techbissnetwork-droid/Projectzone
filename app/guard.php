<?php
declare(strict_types=1);

/** Page guards. Include after bootstrap on any protected page. */

function require_login(): array
{
    $u = Auth::user();
    if (!$u) {
        $_SESSION['after_login'] = $_SERVER['REQUEST_URI'] ?? null;
        Flash::err('Please sign in to continue.');
        redirect('login.php');
    }
    return $u;
}

function require_admin(): array
{
    $u = Auth::user();
    if (!$u) {
        $_SESSION['after_login'] = $_SERVER['REQUEST_URI'] ?? null;
        Flash::err('Please sign in to continue.');
        redirect('staff-login.php');
    }
    if ($u['role'] !== 'admin') {
        http_response_code(403);
        exit('You do not have access to this area.');
    }
    return $u;
}

function require_client(): array
{
    $u = require_login();
    if ($u['role'] === 'admin') {
        redirect('admin/');
    }
    return $u;
}

/** Deny unless the row belongs to this client (admins pass). */
function require_owner(?array $row, string $column = 'user_id'): array
{
    if (!$row) {
        http_response_code(404);
        exit('Not found.');
    }
    if (!Auth::isAdmin() && (int)($row[$column] ?? 0) !== Auth::id()) {
        http_response_code(403);
        exit('You do not have access to this record.');
    }
    return $row;
}
