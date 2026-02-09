<?php

namespace App\Controllers;

use App\Core\Flash;
use App\Core\Validator;
use App\Models\User;
use App\Models\RememberToken;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\RateLimitMiddleware;

class AuthController extends BaseController
{
    public function showLogin(): string
    {
        if (AuthMiddleware::check()) {
            $this->redirect('/');
        }

        return $this->view('auth/login');
    }

    public function login(): string
    {
        $rateCheck = RateLimitMiddleware::check();
        if (!$rateCheck['allowed']) {
            $minutes = ceil($rateCheck['retry_after'] / 60);
            Flash::set('error', "Too many login attempts. Try again in {$minutes} minute(s).");
            $this->redirect('/login');
            return '';
        }

        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $rememberMe = isset($_POST['remember_me']);

        $user = User::authenticate($username, $password);

        if ($user) {
            RateLimitMiddleware::clear();
            AuthMiddleware::setAuthenticated($user);
            CsrfMiddleware::regenerate();

            if ($rememberMe) {
                AuthMiddleware::setRememberToken($user['id']);
            }

            $this->redirect('/');
        }

        RateLimitMiddleware::recordAttempt();
        Flash::set('error', 'Invalid username or password');
        $this->redirect('/login');

        return '';
    }

    public function logout(): void
    {
        CsrfMiddleware::clear();
        AuthMiddleware::logout();
        $this->redirect('/login');
    }
}
