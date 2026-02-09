<?php

namespace App\Core;

class LogChannel
{
    public function __construct(private string $name) {}

    public function emergency(string $message, array $context = []): void
    {
        Logger::writeLog('emergency', $message, $context, $this->name);
    }

    public function alert(string $message, array $context = []): void
    {
        Logger::writeLog('alert', $message, $context, $this->name);
    }

    public function critical(string $message, array $context = []): void
    {
        Logger::writeLog('critical', $message, $context, $this->name);
    }

    public function error(string $message, array $context = []): void
    {
        Logger::writeLog('error', $message, $context, $this->name);
    }

    public function warning(string $message, array $context = []): void
    {
        Logger::writeLog('warning', $message, $context, $this->name);
    }

    public function notice(string $message, array $context = []): void
    {
        Logger::writeLog('notice', $message, $context, $this->name);
    }

    public function info(string $message, array $context = []): void
    {
        Logger::writeLog('info', $message, $context, $this->name);
    }

    public function debug(string $message, array $context = []): void
    {
        Logger::writeLog('debug', $message, $context, $this->name);
    }
}
