<?php

namespace App\Core;

class ExitException extends \RuntimeException
{
    private int $exitCode;

    public function __construct(int $code = 0)
    {
        $this->exitCode = $code;
        parent::__construct("Exit called with code {$code}", $code);
    }

    public function getExitCode(): int
    {
        return $this->exitCode;
    }
}
