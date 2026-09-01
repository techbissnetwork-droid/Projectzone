<?php
declare(strict_types=1);

namespace Techbiss\Core;

use Techbiss\Repo\CustomerRepo;

/**
 * Session state for a client signed into the portal via emailed one-time
 * code. Deliberately separate from Auth (the admin side) — a customer is
 * never granted an admin permission and vice versa, they just happen to
 * share the same session store.
 */
final class CustomerAuth
{
    private static ?array $customer = null;

    public static function login(int $customerId): void
    {
        Session::regenerate();
        Session::set('customer_id', $customerId);
        Session::set('customer_fingerprint', self::fingerprint());
        self::$customer = null;
    }

    /**
     * Forgets only the portal keys — never Session::destroy(), which would
     * also sign out an administrator sharing the same browser session.
     */
    public static function logout(): void
    {
        Session::forget('customer_id');
        Session::forget('customer_fingerprint');
        self::$customer = null;
    }

    public static function check(): bool
    {
        return self::customer() !== null;
    }

    public static function customer(): ?array
    {
        if (self::$customer !== null) {
            return self::$customer;
        }
        $id = Session::get('customer_id');
        if (!is_int($id) && !is_numeric($id)) {
            return null;
        }
        if (Session::get('customer_fingerprint') !== self::fingerprint()) {
            Session::forget('customer_id');
            return null;
        }
        $row = (new CustomerRepo())->find((int) $id);
        if ($row === null) {
            Session::forget('customer_id');
            return null;
        }
        return self::$customer = $row;
    }

    public static function id(): int
    {
        $c = self::customer();
        return $c === null ? 0 : (int) $c['id'];
    }

    private static function fingerprint(): string
    {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        return hash('sha256', (string) $ua . '|' . (string) App::config('security.app_key', ''));
    }
}
