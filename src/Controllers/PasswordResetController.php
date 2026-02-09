<?php

namespace App\Controllers;

use App\Core\Flash;
use App\Core\Mailer;
use App\Core\Validator;
use App\Models\User;
use App\Models\PasswordResetToken;
use App\Models\RememberToken;

class PasswordResetController extends BaseController
{
    public function showForgotForm(): string
    {
        return $this->view('auth/forgot-password');
    }

    public function sendResetLink(): string
    {
        $validator = Validator::make($_POST, [
            'email' => 'required|email',
        ])->validate();

        if ($validator->fails()) {
            foreach ($validator->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    Flash::set('error', $message);
                }
            }
            $this->redirect('/forgot-password');
            return '';
        }

        $email = $validator->validated()['email'];
        $user = User::findBy('email', $email);

        // Always show same message — no user enumeration
        if ($user && $user['is_active']) {
            $rawToken = PasswordResetToken::createToken($user['id']);
            $resetUrl = $this->config['url'] . '/reset-password?token=' . $rawToken;

            Mailer::sendTemplate($email, 'Reset Your Password', 'emails/password-reset', [
                'resetUrl' => $resetUrl,
                'userName' => $user['name'] ?: $user['username'],
                'expiryMinutes' => (int)($this->config['password_reset']['token_lifetime'] / 60),
            ]);
        }

        Flash::set('success', 'If an account with that email exists, a password reset link has been sent.');
        $this->redirect('/forgot-password');

        return '';
    }

    public function showResetForm(): string
    {
        $token = $_GET['token'] ?? '';

        if ($token === '' || !PasswordResetToken::validate($token)) {
            Flash::set('error', 'This password reset link is invalid or has expired.');
            $this->redirect('/forgot-password');
            return '';
        }

        return $this->view('auth/reset-password', [
            'token' => $token,
        ]);
    }

    public function resetPassword(): string
    {
        $token = $_POST['token'] ?? '';

        $tokenData = PasswordResetToken::validate($token);
        if (!$tokenData) {
            Flash::set('error', 'This password reset link is invalid or has expired.');
            $this->redirect('/forgot-password');
            return '';
        }

        $validator = Validator::make($_POST, [
            'password' => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required',
        ])->validate();

        if ($validator->fails()) {
            foreach ($validator->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    Flash::set('error', $message);
                }
            }
            $this->redirect('/reset-password?token=' . urlencode($token));
            return '';
        }

        $data = $validator->validated();

        User::setPassword($tokenData['user_id'], $data['password']);
        PasswordResetToken::deleteToken($token);
        RememberToken::clearForUser($tokenData['user_id']);

        Flash::set('success', 'Your password has been reset. You can now log in.');
        $this->redirect('/login');

        return '';
    }
}
