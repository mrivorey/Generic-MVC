<?php

namespace App\Core;

class ExitTrap
{
    private static bool $testing = false;

    public static function enableTestMode(): void
    {
        self::$testing = true;
    }

    public static function disableTestMode(): void
    {
        self::$testing = false;
    }

    public static function exit(int $code = 0): never
    {
        if (self::$testing) {
            throw new ExitException($code);
        }
        exit($code);
    }
}
