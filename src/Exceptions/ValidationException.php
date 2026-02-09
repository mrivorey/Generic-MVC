<?php

declare(strict_types=1);

namespace App\Exceptions;

class ValidationException extends \RuntimeException
{
    public function __construct(
        private array $errors,
        private string $redirectUrl = '',
        private array $oldInput = [],
    ) {
        parent::__construct('Validation failed');
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getRedirectUrl(): string
    {
        return $this->redirectUrl;
    }

    public function getOldInput(): array
    {
        return $this->oldInput;
    }
}
