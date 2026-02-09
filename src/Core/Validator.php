<?php

namespace App\Core;

class Validator
{
    private array $data;
    private array $rules;
    private array $errors = [];
    private array $validated = [];

    private function __construct(array $data, array $rules)
    {
        $this->data = $data;
        $this->rules = $rules;
    }

    public static function make(array $data, array $rules): self
    {
        return new self($data, $rules);
    }

    public function validate(): self
    {
        foreach ($this->rules as $field => $ruleSet) {
            $rules = is_string($ruleSet) ? explode('|', $ruleSet) : $ruleSet;
            $value = $this->data[$field] ?? null;
            $label = $this->fieldLabel($field);

            foreach ($rules as $rule) {
                $params = [];
                if (str_contains($rule, ':')) {
                    [$rule, $paramStr] = explode(':', $rule, 2);
                    $params = explode(',', $paramStr);
                }

                $method = 'validate' . ucfirst($rule);
                if (method_exists($this, $method)) {
                    $error = $this->$method($field, $value, $label, $params);
                    if ($error !== null) {
                        $this->errors[$field][] = $error;
                    }
                }
            }

            if (!isset($this->errors[$field])) {
                $this->validated[$field] = $value;
            }
        }

        $_SESSION['_validation_errors'] = $this->errors;

        return $this;
    }

    public function fails(): bool
    {
        return !empty($this->errors);
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function validated(): array
    {
        return $this->validated;
    }

    private function fieldLabel(string $field): string
    {
        return ucfirst(str_replace(['_', '-'], ' ', $field));
    }

    private function validateRequired(string $field, mixed $value, string $label, array $params): ?string
    {
        if ($value === null || $value === '' || $value === []) {
            return "{$label} is required.";
        }
        return null;
    }

    private function validateString(string $field, mixed $value, string $label, array $params): ?string
    {
        if ($value !== null && $value !== '' && !is_string($value)) {
            return "{$label} must be a string.";
        }
        return null;
    }

    private function validateEmail(string $field, mixed $value, string $label, array $params): ?string
    {
        if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return "{$label} must be a valid email address.";
        }
        return null;
    }

    private function validateMin(string $field, mixed $value, string $label, array $params): ?string
    {
        $min = (int) ($params[0] ?? 0);
        if ($value !== null && $value !== '' && is_string($value) && strlen($value) < $min) {
            return "{$label} must be at least {$min} characters.";
        }
        return null;
    }

    private function validateMax(string $field, mixed $value, string $label, array $params): ?string
    {
        $max = (int) ($params[0] ?? 0);
        if ($value !== null && $value !== '' && is_string($value) && strlen($value) > $max) {
            return "{$label} must not exceed {$max} characters.";
        }
        return null;
    }

    private function validateNumeric(string $field, mixed $value, string $label, array $params): ?string
    {
        if ($value !== null && $value !== '' && !is_numeric($value)) {
            return "{$label} must be a number.";
        }
        return null;
    }

    private function validateInteger(string $field, mixed $value, string $label, array $params): ?string
    {
        if ($value !== null && $value !== '' && filter_var($value, FILTER_VALIDATE_INT) === false) {
            return "{$label} must be an integer.";
        }
        return null;
    }

    private function validateConfirmed(string $field, mixed $value, string $label, array $params): ?string
    {
        $confirmField = $field . '_confirmation';
        $confirmValue = $this->data[$confirmField] ?? null;
        if ($value !== null && $value !== '' && $value !== $confirmValue) {
            return "{$label} confirmation does not match.";
        }
        return null;
    }

    private function validateUnique(string $field, mixed $value, string $label, array $params): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $table = $params[0] ?? '';
        $column = $params[1] ?? $field;
        $exceptId = $params[2] ?? null;

        $pdo = Database::getConnection();
        $sql = "SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` = ?";
        $bindings = [$value];

        if ($exceptId) {
            $sql .= " AND id != ?";
            $bindings[] = $exceptId;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($bindings);

        if ((int) $stmt->fetchColumn() > 0) {
            return "{$label} has already been taken.";
        }
        return null;
    }

    private function validateIn(string $field, mixed $value, string $label, array $params): ?string
    {
        if ($value !== null && $value !== '' && !in_array($value, $params, true)) {
            return "{$label} must be one of: " . implode(', ', $params) . ".";
        }
        return null;
    }
}
