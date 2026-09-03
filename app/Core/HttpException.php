<?php
declare(strict_types=1);

namespace App\Core;

final class HttpException extends \RuntimeException
{
    public function __construct(private int $statusCode, string $message = '')
    {
        parent::__construct($message !== '' ? $message : self::defaultMessage($statusCode));
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public static function notFound(string $message = 'Page Not Found'): self
    {
        return new self(404, $message);
    }

    public static function forbidden(string $message = 'You do not have access to that.'): self
    {
        return new self(403, $message);
    }

    private static function defaultMessage(int $status): string
    {
        return match ($status) {
            400 => 'Bad Request',
            401 => 'Sign-in required',
            403 => 'Forbidden',
            404 => 'Page Not Found',
            419 => 'Your session expired. Please try again.',
            429 => 'Too Many Requests',
            default => 'Unexpected Error',
        };
    }
}
