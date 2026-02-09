<?php

namespace App\Core;

class Logger
{
    private static ?array $config = null;

    /** @var array<string, LogChannel> */
    private static array $channels = [];

    private static bool $cleanupRanThisRequest = false;

    private const LEVELS = [
        'debug'     => 100,
        'info'      => 200,
        'notice'    => 300,
        'warning'   => 400,
        'error'     => 500,
        'critical'  => 600,
        'alert'     => 700,
        'emergency' => 800,
    ];

    public static function channel(string $name): LogChannel
    {
        if (!isset(self::$channels[$name])) {
            self::$channels[$name] = new LogChannel($name);
        }

        return self::$channels[$name];
    }

    public static function emergency(string $message, array $context = []): void
    {
        self::writeLog('emergency', $message, $context);
    }

    public static function alert(string $message, array $context = []): void
    {
        self::writeLog('alert', $message, $context);
    }

    public static function critical(string $message, array $context = []): void
    {
        self::writeLog('critical', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::writeLog('error', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::writeLog('warning', $message, $context);
    }

    public static function notice(string $message, array $context = []): void
    {
        self::writeLog('notice', $message, $context);
    }

    public static function info(string $message, array $context = []): void
    {
        self::writeLog('info', $message, $context);
    }

    public static function debug(string $message, array $context = []): void
    {
        self::writeLog('debug', $message, $context);
    }

    public static function writeLog(string $level, string $message, array $context = [], ?string $channel = null): void
    {
        $config = self::loadConfig();
        $channel ??= $config['default_channel'];

        if (!self::shouldLog($level, $channel)) {
            return;
        }

        $line = self::formatLine($level, $message, $context, $channel, $config['timezone']);

        FileSystem::append(self::formatLogPath($channel, $config), $line);

        self::maybeCleanup($channel, $config);
    }

    public static function setConfig(array $config): void
    {
        self::$config = $config;
    }

    public static function reset(): void
    {
        self::$config = null;
        self::$channels = [];
        self::$cleanupRanThisRequest = false;
    }

    private static function formatLogPath(string $channel, array $config): string
    {
        $rotation = $config['rotation'] ?? 'single';

        if ($rotation === 'daily') {
            $date = (new \DateTimeImmutable('now', new \DateTimeZone($config['timezone'] ?? 'UTC')))->format('Y-m-d');
            return "logs/{$channel}-{$date}.log";
        }

        return "logs/{$channel}.log";
    }

    private static function maybeCleanup(string $channel, array $config): void
    {
        if (self::$cleanupRanThisRequest) {
            return;
        }

        self::$cleanupRanThisRequest = true;

        $rotation = $config['rotation'] ?? 'single';

        if ($rotation !== 'daily') {
            return;
        }

        $maxFiles = $config['max_files'] ?? 14;
        $logsDir = FileSystem::getStorageRoot() . '/logs';
        $files = @scandir($logsDir);

        if ($files === false) {
            return;
        }

        $pattern = '/^' . preg_quote($channel, '/') . '-(\d{4}-\d{2}-\d{2})\.log$/';
        $cutoff = (new \DateTimeImmutable('now', new \DateTimeZone($config['timezone'] ?? 'UTC')))
            ->modify("-{$maxFiles} days")
            ->format('Y-m-d');

        foreach ($files as $file) {
            if (preg_match($pattern, $file, $matches)) {
                if ($matches[1] < $cutoff) {
                    @unlink($logsDir . '/' . $file);
                }
            }
        }
    }

    private static function shouldLog(string $level, string $channel): bool
    {
        $config = self::loadConfig();
        $levelWeight = self::LEVELS[$level] ?? 0;

        $minLevel = $config['channels'][$channel]['min_level'] ?? $config['min_level'];
        $minWeight = self::LEVELS[$minLevel] ?? 0;

        return $levelWeight >= $minWeight;
    }

    private static function formatLine(string $level, string $message, array $context, string $channel, string $timezone): string
    {
        $timestamp = (new \DateTimeImmutable('now', new \DateTimeZone($timezone)))->format('Y-m-d H:i:s');
        $upperLevel = strtoupper($level);

        $line = "[{$timestamp}] {$channel}.{$upperLevel}: {$message}";

        if ($context !== []) {
            $line .= ' ' . json_encode($context, JSON_UNESCAPED_SLASHES);
        }

        return $line . "\n";
    }

    private static function loadConfig(): array
    {
        if (self::$config !== null) {
            return self::$config;
        }

        $appConfig = require dirname(__DIR__, 2) . '/config/app.php';
        $logging = $appConfig['logging'] ?? [];

        self::$config = [
            'default_channel' => $logging['default_channel'] ?? 'app',
            'min_level' => $logging['min_level'] ?? 'debug',
            'channels' => $logging['channels'] ?? [],
            'timezone' => $appConfig['timezone'] ?? 'UTC',
            'rotation' => $logging['rotation'] ?? 'single',
            'max_files' => $logging['max_files'] ?? 14,
        ];

        return self::$config;
    }
}
