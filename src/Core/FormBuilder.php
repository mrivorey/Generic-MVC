<?php

namespace App\Core;

use App\Middleware\CsrfMiddleware;

class FormBuilder
{
    private static ?array $validationErrors = null;

    public static function open(array $attributes = []): string
    {
        // Load validation errors once per request
        if (self::$validationErrors === null) {
            self::$validationErrors = $_SESSION['_validation_errors'] ?? [];
            unset($_SESSION['_validation_errors']);
        }

        $method = strtoupper($attributes['method'] ?? 'POST');
        $action = $attributes['action'] ?? '';
        $class = $attributes['class'] ?? '';
        $id = $attributes['id'] ?? '';
        $enctype = $attributes['enctype'] ?? '';

        // Real HTTP method is POST for PUT/PATCH/DELETE
        $formMethod = in_array($method, ['PUT', 'PATCH', 'DELETE']) ? 'POST' : $method;

        $html = '<form method="' . htmlspecialchars($formMethod) . '"';
        if ($action) {
            $html .= ' action="' . htmlspecialchars($action) . '"';
        }
        if ($class) {
            $html .= ' class="' . htmlspecialchars($class) . '"';
        }
        if ($id) {
            $html .= ' id="' . htmlspecialchars($id) . '"';
        }
        if ($enctype) {
            $html .= ' enctype="' . htmlspecialchars($enctype) . '"';
        }
        $html .= '>';

        // CSRF token
        if ($formMethod !== 'GET') {
            $html .= CsrfMiddleware::field();
        }

        // Method spoofing
        if (in_array($method, ['PUT', 'PATCH', 'DELETE'])) {
            $html .= '<input type="hidden" name="_method" value="' . $method . '">';
        }

        return $html;
    }

    public static function close(): string
    {
        // Clear old input after form renders
        Flash::clearOldInput();
        return '</form>';
    }

    public static function text(string $name, array $options = []): string
    {
        return self::input('text', $name, $options);
    }

    public static function email(string $name, array $options = []): string
    {
        return self::input('email', $name, $options);
    }

    public static function password(string $name, array $options = []): string
    {
        $options['noOldInput'] = true;
        return self::input('password', $name, $options);
    }

    public static function number(string $name, array $options = []): string
    {
        return self::input('number', $name, $options);
    }

    public static function hidden(string $name, string $value = ''): string
    {
        return '<input type="hidden" name="' . htmlspecialchars($name) . '" value="' . htmlspecialchars($value) . '">';
    }

    public static function textarea(string $name, array $options = []): string
    {
        $label = $options['label'] ?? self::humanize($name);
        $value = self::getValue($name, $options);
        $id = $options['id'] ?? $name;
        $required = !empty($options['required']) ? ' required' : '';
        $rows = $options['rows'] ?? 3;
        $help = $options['help'] ?? '';
        $class = 'form-control';
        $errorHtml = '';

        if (self::hasError($name)) {
            $class .= ' is-invalid';
            $errorHtml = self::errorFeedback($name);
        }

        $html = '<div class="mb-3">';
        $html .= '<label for="' . htmlspecialchars($id) . '" class="form-label">' . htmlspecialchars($label) . '</label>';
        $html .= '<textarea class="' . $class . '" id="' . htmlspecialchars($id) . '" name="' . htmlspecialchars($name) . '" rows="' . $rows . '"' . $required . '>';
        $html .= htmlspecialchars($value);
        $html .= '</textarea>';
        if ($help) {
            $html .= '<div class="form-text">' . htmlspecialchars($help) . '</div>';
        }
        $html .= $errorHtml;
        $html .= '</div>';

        return $html;
    }

    public static function select(string $name, array $optionsList, array $options = []): string
    {
        $label = $options['label'] ?? self::humanize($name);
        $selected = self::getValue($name, $options);
        $id = $options['id'] ?? $name;
        $required = !empty($options['required']) ? ' required' : '';
        $placeholder = $options['placeholder'] ?? '';
        $help = $options['help'] ?? '';
        $class = 'form-select';
        $errorHtml = '';

        if (self::hasError($name)) {
            $class .= ' is-invalid';
            $errorHtml = self::errorFeedback($name);
        }

        $html = '<div class="mb-3">';
        $html .= '<label for="' . htmlspecialchars($id) . '" class="form-label">' . htmlspecialchars($label) . '</label>';
        $html .= '<select class="' . $class . '" id="' . htmlspecialchars($id) . '" name="' . htmlspecialchars($name) . '"' . $required . '>';

        if ($placeholder) {
            $html .= '<option value="">' . htmlspecialchars($placeholder) . '</option>';
        }

        foreach ($optionsList as $val => $text) {
            $sel = ((string) $val === (string) $selected) ? ' selected' : '';
            $html .= '<option value="' . htmlspecialchars($val) . '"' . $sel . '>' . htmlspecialchars($text) . '</option>';
        }

        $html .= '</select>';
        if ($help) {
            $html .= '<div class="form-text">' . htmlspecialchars($help) . '</div>';
        }
        $html .= $errorHtml;
        $html .= '</div>';

        return $html;
    }

    public static function checkbox(string $name, array $options = []): string
    {
        $label = $options['label'] ?? self::humanize($name);
        $id = $options['id'] ?? $name;
        $checked = $options['checked'] ?? false;
        $value = $options['value'] ?? '1';
        $class = 'form-check-input';
        $errorHtml = '';

        // Check old input
        $oldValue = Flash::old($name);
        if ($oldValue !== '') {
            $checked = true;
        }

        if (self::hasError($name)) {
            $class .= ' is-invalid';
            $errorHtml = self::errorFeedback($name);
        }

        $checkedAttr = $checked ? ' checked' : '';

        $html = '<div class="mb-3 form-check">';
        $html .= '<input type="checkbox" class="' . $class . '" id="' . htmlspecialchars($id) . '" name="' . htmlspecialchars($name) . '" value="' . htmlspecialchars($value) . '"' . $checkedAttr . '>';
        $html .= '<label class="form-check-label" for="' . htmlspecialchars($id) . '">' . htmlspecialchars($label) . '</label>';
        $html .= $errorHtml;
        $html .= '</div>';

        return $html;
    }

    public static function radio(string $name, string $value, array $options = []): string
    {
        $label = $options['label'] ?? $value;
        $id = $options['id'] ?? $name . '_' . $value;
        $checked = $options['checked'] ?? false;
        $class = 'form-check-input';

        $oldValue = Flash::old($name);
        if ($oldValue !== '' && $oldValue === $value) {
            $checked = true;
        }

        $checkedAttr = $checked ? ' checked' : '';

        $html = '<div class="form-check">';
        $html .= '<input type="radio" class="' . $class . '" id="' . htmlspecialchars($id) . '" name="' . htmlspecialchars($name) . '" value="' . htmlspecialchars($value) . '"' . $checkedAttr . '>';
        $html .= '<label class="form-check-label" for="' . htmlspecialchars($id) . '">' . htmlspecialchars($label) . '</label>';
        $html .= '</div>';

        return $html;
    }

    public static function submit(string $text = 'Submit', array $options = []): string
    {
        $class = $options['class'] ?? 'btn btn-primary';
        return '<button type="submit" class="' . htmlspecialchars($class) . '">' . htmlspecialchars($text) . '</button>';
    }

    public static function old(string $key, string $default = ''): string
    {
        return Flash::old($key, $default);
    }

    public static function hasError(string $field): bool
    {
        return !empty(self::$validationErrors[$field]);
    }

    public static function error(string $field): string
    {
        $errors = self::$validationErrors[$field] ?? [];
        return implode(' ', $errors);
    }

    private static function input(string $type, string $name, array $options): string
    {
        $label = $options['label'] ?? self::humanize($name);
        $id = $options['id'] ?? $name;
        $required = !empty($options['required']) ? ' required' : '';
        $help = $options['help'] ?? '';
        $autocomplete = $options['autocomplete'] ?? '';
        $minlength = isset($options['minlength']) ? ' minlength="' . (int) $options['minlength'] . '"' : '';
        $maxlength = isset($options['maxlength']) ? ' maxlength="' . (int) $options['maxlength'] . '"' : '';
        $autofocus = !empty($options['autofocus']) ? ' autofocus' : '';
        $placeholder = isset($options['placeholder']) ? ' placeholder="' . htmlspecialchars($options['placeholder']) . '"' : '';
        $class = 'form-control';
        $errorHtml = '';

        $value = '';
        if (empty($options['noOldInput'])) {
            $value = self::getValue($name, $options);
        }

        if (self::hasError($name)) {
            $class .= ' is-invalid';
            $errorHtml = self::errorFeedback($name);
        }

        $autocompleteAttr = $autocomplete ? ' autocomplete="' . htmlspecialchars($autocomplete) . '"' : '';

        $html = '<div class="mb-3">';
        $html .= '<label for="' . htmlspecialchars($id) . '" class="form-label">' . htmlspecialchars($label) . '</label>';
        $html .= '<input type="' . $type . '" class="' . $class . '" id="' . htmlspecialchars($id) . '" name="' . htmlspecialchars($name) . '" value="' . htmlspecialchars($value) . '"' . $required . $minlength . $maxlength . $autofocus . $autocompleteAttr . $placeholder . '>';
        if ($help) {
            $html .= '<div class="form-text">' . htmlspecialchars($help) . '</div>';
        }
        $html .= $errorHtml;
        $html .= '</div>';

        return $html;
    }

    private static function getValue(string $name, array $options): string
    {
        // Priority: Flash::old() > explicit value > empty
        $old = Flash::old($name);
        if ($old !== '') {
            return $old;
        }
        return (string) ($options['value'] ?? '');
    }

    private static function errorFeedback(string $field): string
    {
        $errors = self::$validationErrors[$field] ?? [];
        if (empty($errors)) {
            return '';
        }
        return '<div class="invalid-feedback">' . htmlspecialchars(implode(' ', $errors)) . '</div>';
    }

    public static function resetState(): void
    {
        self::$validationErrors = null;
    }

    private static function humanize(string $name): string
    {
        return ucfirst(str_replace(['_', '-'], ' ', $name));
    }
}
