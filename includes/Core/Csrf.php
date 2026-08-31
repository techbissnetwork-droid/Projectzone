<?php
declare(strict_types=1);

namespace Techbiss\Core;

final class Csrf
{
    private const KEY = '__csrf';

    public static function token(): string
    {
        $token = Session::get(self::KEY);
        if (!is_string($token) || strlen($token) !== 64) {
            $token = Str::randomToken(32);
            Session::set(self::KEY, $token);
        }
        return $token;
    }

    public static function field(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8') . '">';
    }

    public static function check(?string $token): bool
    {
        $stored = Session::get(self::KEY);
        return is_string($stored) && is_string($token) && $token !== '' && hash_equals($stored, $token);
    }

    /** Verify a POST request or abort with 419. */
    public static function verify(Request $request): void
    {
        $token = $request->post('csrf_token') ?? $request->header('X-CSRF-Token');
        if (!self::check(is_string($token) ? $token : null)) {
            http_response_code(419);
            if ($request->wantsJson()) {
                header('Content-Type: application/json');
                echo json_encode(['ok' => false, 'message' => 'Your session expired. Please reload the page and try again.']);
            } else {
                echo '<!doctype html><meta charset="utf-8"><title>Session expired</title>'
                    . '<body style="font:16px/1.6 system-ui;padding:48px;max-width:44rem;margin:auto">'
                    . '<h1>Session expired</h1><p>Your session expired or the request could not be verified. '
                    . 'Please go back, reload the page and submit the form again.</p></body>';
            }
            exit;
        }
    }
}
