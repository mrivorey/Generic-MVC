<?php

namespace App\Controllers;

abstract class BaseController
{
    protected array $config;

    public function __construct()
    {
        $this->config = require dirname(__DIR__, 2) . '/config/app.php';
    }

    protected function view(string $template, array $data = []): string
    {
        $viewsPath = $this->config['paths']['views'];
        $templatePath = $viewsPath . '/' . $template . '.php';

        if (!file_exists($templatePath)) {
            throw new \RuntimeException("View template not found: {$template}");
        }

        // Extract data to make variables available in template
        extract($data);

        // Start output buffering
        ob_start();

        // Include the template
        include $templatePath;

        // Return the buffered content
        return ob_get_clean();
    }

    protected function json(array $data, int $statusCode = 200): string
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        return json_encode($data);
    }

    protected function redirect(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }

    protected function isAuthenticated(): bool
    {
        return isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true;
    }

    protected function getSessionUser(): ?string
    {
        return $_SESSION['username'] ?? null;
    }

    protected function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
