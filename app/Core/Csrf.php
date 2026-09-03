<?php
declare(strict_types=1);

namespace App\Core;

final class Csrf
{
    public const FIELD = '_token';

    public function __construct(private Session $session)
    {
    }

    public function token(): string
    {
        $token = $this->session->get('__csrf');
        if (!is_string($token) || strlen($token) !== 64) {
            $token = bin2hex(random_bytes(32));
            $this->session->put('__csrf', $token);
        }
        return $token;
    }

    public function field(): string
    {
        return '<input type="hidden" name="' . self::FIELD . '" value="' . htmlspecialchars($this->token(), ENT_QUOTES) . '">';
    }

    public function verify(Request $request): bool
    {
        if (in_array($request->method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return true;
        }
        $supplied = (string) ($request->input(self::FIELD) ?? $request->server['HTTP_X_CSRF_TOKEN'] ?? '');
        $expected = $this->session->get('__csrf');
        return is_string($expected) && $supplied !== '' && hash_equals($expected, $supplied);
    }
}
