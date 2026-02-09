<?php

declare(strict_types=1);

namespace App\Exceptions;

class HttpException extends \RuntimeException
{
    public function __construct(
        private int $statusCode = 500,
        string $message = '',
        private array $headers = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }
}
