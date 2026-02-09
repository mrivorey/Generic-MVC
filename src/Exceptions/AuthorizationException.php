<?php

declare(strict_types=1);

namespace App\Exceptions;

class AuthorizationException extends HttpException
{
    public function __construct(string $message = 'Access denied', ?\Throwable $previous = null)
    {
        parent::__construct(403, $message, [], $previous);
    }
}
