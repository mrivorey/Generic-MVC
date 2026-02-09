<?php

namespace App\Middleware;

use App\Core\ExitTrap;
use App\Exceptions\AuthorizationException;
use App\Models\User;
use App\Models\Role;
use App\Models\RememberToken;

class AuthMiddleware
{
    public static function check(): bool
    {
        return isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true;
    }

    public static function requireAuth(): void
    {
        if (!self::check()) {
            header('Location: /login');
            ExitTrap::exit();
        }
    }

    public static function requireRole(string ...$allowedRoles): void
    {
        self::requireAuth();
        $userRoles = $_SESSION['user_roles'] ?? [];
        if (empty(array_intersect($userRoles, $allowedRoles))) {
            throw new AuthorizationException('You do not have permission to access this page.');
        }
    }

    public static function requirePermission(string $slug): void
    {
        self::requireAuth();
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId || !User::hasPermission($userId, $slug)) {
            throw new AuthorizationException('You do not have permission to perform this action.');
        }
    }

    public static function setAuthenticated(array $user): void
    {
        $_SESSION['authenticated'] = true;
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];

        $roles = User::roles($user['id']);
        $_SESSION['user_roles'] = array_column($roles, 'slug');
        $_SESSION['user_name'] = $user['name'] ?? $user['username'];
        $_SESSION['login_time'] = time();

        session_regenerate_id(true);
    }

    public static function user(): ?array
    {
        if (!self::check()) {
            return null;
        }
        return [
            'id' => $_SESSION['user_id'] ?? null,
            'username' => $_SESSION['username'] ?? null,
            'roles' => $_SESSION['user_roles'] ?? [],
            'name' => $_SESSION['user_name'] ?? null,
        ];
    }

    public static function checkRememberToken(): void
    {
        $cookieName = RememberToken::getCookieName();

        if (!isset($_COOKIE[$cookieName])) {
            return;
        }

        $rawToken = $_COOKIE[$cookieName];
        $result = RememberToken::validate($rawToken);

        if ($result !== null) {
            $user = User::find($result['user_id']);
            if ($user && $user['is_active']) {
                self::setAuthenticated($user);
            } else {
                self::clearRememberCookie();
            }
        } else {
            self::clearRememberCookie();
        }
    }

    public static function setRememberToken(int $userId): void
    {
        $rawToken = RememberToken::createToken($userId);

        setcookie(
            RememberToken::getCookieName(),
            $rawToken,
            [
                'expires' => time() + RememberToken::getLifetime(),
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Lax',
            ]
        );
    }

    public static function clearRememberCookie(): void
    {
        $cookieName = RememberToken::getCookieName();

        setcookie(
            $cookieName,
            '',
            [
                'expires' => time() - 42000,
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Lax',
            ]
        );
    }

    public static function deleteRememberToken(): void
    {
        $cookieName = RememberToken::getCookieName();

        if (isset($_COOKIE[$cookieName])) {
            RememberToken::deleteToken($_COOKIE[$cookieName]);
        }

        self::clearRememberCookie();
    }

    public static function logout(): void
    {
        self::deleteRememberToken();

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
    }
}
