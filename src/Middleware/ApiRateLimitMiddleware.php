<?php

namespace App\Middleware;

use App\Core\Database;
use App\Core\ExitTrap;

class ApiRateLimitMiddleware
{
    private static int $maxRequests = 60;
    private static int $windowSeconds = 60;

    public static function check(): void
    {
        $apiUser = $_REQUEST['_api_user'] ?? null;
        if (!$apiUser) {
            return;
        }

        $key = 'api:' . $apiUser['key_id'];
        $now = time();
        $windowStart = $now - self::$windowSeconds;

        $pdo = Database::getConnection();

        // Clean old entries
        $pdo->prepare('DELETE FROM rate_limits WHERE ip_address = ? AND UNIX_TIMESTAMP(updated_at) < ?')
            ->execute([$key, $windowStart]);

        // Get current count
        $stmt = $pdo->prepare('SELECT attempts FROM rate_limits WHERE ip_address = ?');
        $stmt->execute([$key]);
        $row = $stmt->fetch();

        $attempts = $row ? (json_decode($row['attempts'], true) ?: []) : [];
        $attempts = array_filter($attempts, fn($t) => $t > $windowStart);
        $count = count($attempts);

        if ($count >= self::$maxRequests) {
            $retryAfter = self::$windowSeconds - ($now - min($attempts));
            http_response_code(429);
            header('Content-Type: application/json');
            header("Retry-After: {$retryAfter}");
            echo json_encode([
                'error' => true,
                'message' => 'Rate limit exceeded.',
                'retry_after' => $retryAfter,
            ]);
            ExitTrap::exit();
        }

        $attempts[] = $now;
        $attemptsJson = json_encode(array_values($attempts));

        $pdo->prepare(
            'INSERT INTO rate_limits (ip_address, attempts) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE attempts = VALUES(attempts)'
        )->execute([$key, $attemptsJson]);
    }
}
