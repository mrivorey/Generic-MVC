<?php

namespace App\Middleware;

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
            exit;
        }
    }

    public static function setAuthenticated(string $username): void
    {
        $_SESSION['authenticated'] = true;
        $_SESSION['username'] = $username;
        $_SESSION['login_time'] = time();

        // Regenerate session ID to prevent session fixation
        session_regenerate_id(true);
    }

    public static function checkRememberToken(): void
    {
        $rememberToken = new RememberToken();
        $cookieName = $rememberToken->getCookieName();

        if (!isset($_COOKIE[$cookieName])) {
            return;
        }

        $rawToken = $_COOKIE[$cookieName];
        $username = $rememberToken->validate($rawToken);

        if ($username !== null) {
            self::setAuthenticated($username);
        } else {
            self::clearRememberCookie();
        }
    }

    public static function setRememberToken(string $username): void
    {
        $rememberToken = new RememberToken();
        $rawToken = $rememberToken->create($username);

        setcookie(
            $rememberToken->getCookieName(),
            $rawToken,
            [
                'expires' => time() + $rememberToken->getLifetime(),
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Lax',
            ]
        );
    }

    public static function clearRememberCookie(): void
    {
        $rememberToken = new RememberToken();
        $cookieName = $rememberToken->getCookieName();

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
        $rememberToken = new RememberToken();
        $cookieName = $rememberToken->getCookieName();

        if (isset($_COOKIE[$cookieName])) {
            $rememberToken->delete($_COOKIE[$cookieName]);
        }

        self::clearRememberCookie();
    }

    public static function logout(): void
    {
        self::deleteRememberToken();

        $_SESSION = [];

        // Delete session cookie
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
