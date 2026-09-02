<?php
declare(strict_types=1);

final class Flash
{
    public static function add(string $type, string $message): void
    {
        $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
    }

    public static function ok(string $m): void  { self::add('ok', $m); }
    public static function err(string $m): void { self::add('err', $m); }

    /** @return array<int,array{type:string,message:string}> */
    public static function take(): array
    {
        $f = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $f;
    }
}
