<?php

declare(strict_types=1);

namespace App\Core;

use App\Exceptions\HttpException;
use App\Exceptions\ValidationException;

class ErrorHandler
{
    private static bool $debug = false;
    private static string $viewsPath = '';
    private static int $baseObLevel = 0;
    private static mixed $previousExceptionHandler = null;
    private static bool $registered = false;

    public static function register(array $config): void
    {
        self::configure($config);

        $prev = set_exception_handler([self::class, 'handleException']);
        self::$previousExceptionHandler = $prev;
        set_error_handler([self::class, 'handleError']);
        register_shutdown_function([self::class, 'handleShutdown']);
        self::$registered = true;
    }

    public static function configure(array $config): void
    {
        self::$debug = $config['debug'] ?? false;
        self::$viewsPath = ($config['paths']['views'] ?? '') . '/errors';
        self::$baseObLevel = ob_get_level();
    }

    public static function handleError(int $severity, string $message, string $file, int $line): bool
    {
        if (!(error_reporting() & $severity)) {
            return false;
        }

        throw new \ErrorException($message, 0, $severity, $file, $line);
    }

    public static function handleShutdown(): void
    {
        $error = error_get_last();
        if ($error === null) {
            return;
        }

        $fatalTypes = [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE];
        if (in_array($error['type'], $fatalTypes, true)) {
            $exception = new \ErrorException(
                $error['message'],
                0,
                $error['type'],
                $error['file'],
                $error['line']
            );
            self::handleException($exception);
        }
    }

    public static function handleException(\Throwable $e): void
    {
        // Clear output buffers above our baseline
        while (ob_get_level() > self::$baseObLevel) {
            ob_end_clean();
        }

        // Handle ValidationException: redirect with errors
        if ($e instanceof ValidationException) {
            self::handleValidationException($e);
            return;
        }

        // Determine status code
        $code = ($e instanceof HttpException) ? $e->getStatusCode() : 500;

        // Set HTTP status and headers
        if (!headers_sent()) {
            http_response_code($code);
            if ($e instanceof HttpException) {
                foreach ($e->getHeaders() as $name => $value) {
                    header("{$name}: {$value}");
                }
            }
        }

        // Log the exception
        self::logException($e, $code);

        // Render response
        $isApi = str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/');

        if ($isApi) {
            self::renderJson($e, $code);
        } else {
            self::renderHtml($e, $code);
        }
    }

    public static function reset(): void
    {
        if (self::$registered) {
            restore_exception_handler();
            restore_error_handler();
            self::$registered = false;
        }
        self::$debug = false;
        self::$viewsPath = '';
        self::$baseObLevel = 0;
        self::$previousExceptionHandler = null;
    }

    public static function isDebug(): bool
    {
        return self::$debug;
    }

    private static function handleValidationException(ValidationException $e): void
    {
        $_SESSION['_validation_errors'] = $e->getErrors();

        $oldInput = $e->getOldInput();
        if (!empty($oldInput)) {
            Flash::setOldInput($oldInput);
        }

        $redirect = $e->getRedirectUrl() ?: ($_SERVER['HTTP_REFERER'] ?? '/');

        if (!headers_sent()) {
            header("Location: {$redirect}");
        }
        ExitTrap::exit();
    }

    private static function logException(\Throwable $e, int $code): void
    {
        $context = [
            'exception' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'url' => $_SERVER['REQUEST_URI'] ?? '',
            'method' => $_SERVER['REQUEST_METHOD'] ?? '',
        ];

        $channel = Logger::channel('error');
        $message = $e->getMessage();

        if ($code === 404) {
            $channel->notice($message, $context);
        } elseif ($code === 403) {
            $channel->warning($message, $context);
        } else {
            $channel->error($message, $context);
        }
    }

    private static function renderJson(\Throwable $e, int $code): void
    {
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }

        $response = [
            'error' => true,
            'message' => ($code >= 500 && !self::$debug)
                ? 'Internal server error'
                : $e->getMessage(),
        ];

        if (self::$debug) {
            $response['debug'] = [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ];
        }

        echo json_encode($response, JSON_UNESCAPED_SLASHES);
    }

    private static function renderHtml(\Throwable $e, int $code): void
    {
        if (self::$debug) {
            self::renderView('dev', ['exception' => $e, 'code' => $code]);
            return;
        }

        // Try specific error page, then generic, then inline fallback
        if (self::renderView((string) $code, ['code' => $code])) {
            return;
        }

        if (self::renderView('generic', ['code' => $code])) {
            return;
        }

        // Inline fallback if no view files exist
        echo "<!DOCTYPE html><html><head><title>Error {$code}</title></head>"
           . "<body><h1>Error {$code}</h1><p>An error occurred.</p></body></html>";
    }

    private static function renderView(string $name, array $data): bool
    {
        $path = self::$viewsPath . "/{$name}.php";

        if (!file_exists($path)) {
            return false;
        }

        extract($data);
        include $path;
        return true;
    }
}
