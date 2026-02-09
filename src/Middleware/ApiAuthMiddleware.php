<?php

namespace App\Middleware;

use App\Core\ExitTrap;
use App\Models\ApiKey;

class ApiAuthMiddleware
{
    public static function verify(): void
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        if (!preg_match('/^Bearer\s+(app_.+)$/', $header, $matches)) {
            self::unauthorized('Missing or invalid Authorization header.');
        }

        $rawKey = $matches[1];
        $result = ApiKey::validateKey($rawKey);

        if (!$result) {
            self::unauthorized('Invalid API key.');
        }

        // Store API user data for controllers
        $_REQUEST['_api_user'] = $result;
    }

    private static function unauthorized(string $message): void
    {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode([
            'error' => true,
            'message' => $message,
        ]);
        ExitTrap::exit();
    }
}
