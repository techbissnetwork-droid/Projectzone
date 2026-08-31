<?php
declare(strict_types=1);

namespace Techbiss\Controllers\Admin;

use Techbiss\Core\App;
use Techbiss\Core\Auth;
use Techbiss\Core\Csrf;
use Techbiss\Core\Request;
use Techbiss\Core\Session;
use Techbiss\Core\View;
use Techbiss\Repo\LeadRepo;
use Techbiss\Repo\PurchaseRepo;

abstract class BaseAdminController
{
    protected View $view;

    public function __construct()
    {
        $this->view = new View(App::root() . '/admin/views', App::root() . '/admin/views/layout.php');
        $this->view->shareMany([
            'settings'    => App::settings(),
            'user'        => Auth::user(),
            'flash'       => App::flashMessages(),
            'currentPath' => App::currentPath(),
            'badges'      => $this->sidebarBadges(),
        ]);
    }

    /** Stop the request unless the signed-in user holds the permission. */
    protected function authorize(string $permission): void
    {
        if (!Auth::can($permission)) {
            $this->denied($permission);
        }
    }

    protected function authorizeAny(array $permissions): void
    {
        if (!Auth::canAny($permissions)) {
            $this->denied(implode(' or ', $permissions));
        }
    }

    private function denied(string $permission): never
    {
        http_response_code(403);
        $this->view->render('403', ['permission' => $permission, 'title' => 'Access denied']);
        exit;
    }

    protected function verify(Request $request): void
    {
        Csrf::verify($request);
    }

    /** Counts shown as pills in the sidebar. */
    private function sidebarBadges(): array
    {
        try {
            $db = \Techbiss\Core\Database::instance();
            return [
                'messages'  => $db->int("SELECT COUNT(*) FROM contact_messages WHERE status = 'new'"),
                'quotes'    => $db->int("SELECT COUNT(*) FROM quote_requests WHERE status = 'new'"),
                'purchases' => $db->int("SELECT COUNT(*) FROM package_purchases WHERE payment_status = 'pending'"),
            ];
        } catch (\Throwable) {
            return ['messages' => 0, 'quotes' => 0, 'purchases' => 0];
        }
    }

    protected function back(string $fallback = '/admin'): never
    {
        $ref = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        if ($ref !== '') {
            $host = parse_url($ref, PHP_URL_HOST);
            $self = parse_url(App::siteUrl(), PHP_URL_HOST);
            if ($host === null || $host === $self) {
                redirect($ref);
            }
        }
        redirect($fallback);
    }

    protected function fail(Request $request, array $errors, string $message, string $redirectTo): never
    {
        if ($request->wantsJson()) {
            json_response(['ok' => false, 'message' => $message, 'errors' => $errors], 422);
        }
        Session::flashInput($request->all());
        Session::flashErrors($errors);
        flash('error', $message);
        redirect($redirectTo);
    }

    protected function ok(string $message, string $redirectTo): never
    {
        flash('success', $message);
        redirect($redirectTo);
    }

    /** Stream an array of rows as a CSV download. */
    protected function csv(string $filename, array $rows): never
    {
        $filename = preg_replace('/[^A-Za-z0-9._-]/', '', $filename) ?: 'export.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // BOM so Excel reads UTF-8 correctly
        if ($rows === []) {
            fputcsv($out, ['No records']);
        } else {
            fputcsv($out, array_keys($rows[0]));
            foreach ($rows as $row) {
                // Neutralise spreadsheet formula injection in exported values.
                $safe = array_map(static function ($v): string {
                    $v = (string) $v;
                    return preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v;
                }, $row);
                fputcsv($out, $safe);
            }
        }
        fclose($out);
        exit;
    }

    protected function perPage(Request $request, int $default = 20): int
    {
        $n = $request->queryInt('per', $default);
        return in_array($n, [10, 20, 50, 100], true) ? $n : $default;
    }
}
