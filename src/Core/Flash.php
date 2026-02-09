<?php

namespace App\Core;

class Flash
{
    public static function set(string $type, string $message): void
    {
        $_SESSION['_flash'][$type][] = $message;
    }

    public static function get(string $type): array
    {
        $messages = $_SESSION['_flash'][$type] ?? [];
        unset($_SESSION['_flash'][$type]);
        return $messages;
    }

    public static function all(): array
    {
        $messages = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $messages;
    }

    public static function has(string $type): bool
    {
        return !empty($_SESSION['_flash'][$type]);
    }

    public static function setOldInput(array $data): void
    {
        $_SESSION['_old_input'] = $data;
    }

    public static function old(string $key, string $default = ''): string
    {
        $value = $_SESSION['_old_input'][$key] ?? $default;
        return $value;
    }

    public static function clearOldInput(): void
    {
        unset($_SESSION['_old_input']);
    }
}
