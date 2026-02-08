<?php

namespace App\Controllers;

use App\Models\User;
use App\Models\RememberToken;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\RateLimitMiddleware;

class AuthController extends BaseController
{
    public function showLogin(): string
    {
        // If already logged in, redirect to dashboard
        if (AuthMiddleware::check()) {
            $this->redirect('/');
        }

        return $this->view('auth/login', [
            'error' => $_SESSION['login_error'] ?? null,
        ]);

        // Clear any stored error
        unset($_SESSION['login_error']);
    }

    public function login(): string
    {
        // Check rate limit before processing
        $rateCheck = RateLimitMiddleware::check();
        if (!$rateCheck['allowed']) {
            $minutes = ceil($rateCheck['retry_after'] / 60);
            $_SESSION['rate_limit_error'] = "Too many login attempts. Try again in {$minutes} minute(s).";
            $this->redirect('/login');
        }

        CsrfMiddleware::verify();

        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $rememberMe = isset($_POST['remember_me']);

        $user = new User();

        if ($user->authenticate($username, $password)) {
            RateLimitMiddleware::clear();
            AuthMiddleware::setAuthenticated($username);
            CsrfMiddleware::regenerate();

            if ($rememberMe) {
                AuthMiddleware::setRememberToken($username);
            }

            $this->redirect('/');
        }

        RateLimitMiddleware::recordAttempt();
        $_SESSION['login_error'] = 'Invalid username or password';
        $this->redirect('/login');

        return '';
    }

    public function logout(): void
    {
        CsrfMiddleware::clear();
        AuthMiddleware::logout();
        $this->redirect('/login');
    }

    public function showChangePassword(): string
    {
        AuthMiddleware::requireAuth();

        return $this->view('auth/change-password', [
            'error' => $_SESSION['password_error'] ?? null,
            'success' => $_SESSION['password_success'] ?? null,
            'authenticated' => $this->isAuthenticated(),
            'username' => $this->getSessionUser(),
        ]);

        // Clear any stored messages
        unset($_SESSION['password_error'], $_SESSION['password_success']);
    }

    public function changePassword(): string
    {
        AuthMiddleware::requireAuth();
        CsrfMiddleware::verify();

        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        // Validate inputs
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $_SESSION['password_error'] = 'All fields are required';
            $this->redirect('/change-password');
        }

        if ($newPassword !== $confirmPassword) {
            $_SESSION['password_error'] = 'New passwords do not match';
            $this->redirect('/change-password');
        }

        if (strlen($newPassword) < 8) {
            $_SESSION['password_error'] = 'Password must be at least 8 characters';
            $this->redirect('/change-password');
        }

        $user = new User();

        if ($user->updatePassword($currentPassword, $newPassword)) {
            $rememberToken = new RememberToken();
            $rememberToken->clearAll();

            $_SESSION['password_success'] = 'Password changed successfully. All remembered sessions have been logged out.';
            $this->redirect('/change-password');
        }

        $_SESSION['password_error'] = 'Current password is incorrect';
        $this->redirect('/change-password');

        return '';
    }
}
