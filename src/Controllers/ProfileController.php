<?php

namespace App\Controllers;

use App\Core\Flash;
use App\Core\Validator;
use App\Models\User;
use App\Models\Role;
use App\Models\RememberToken;
use App\Models\ApiKey;
use App\Middleware\AuthMiddleware;

class ProfileController extends BaseController
{
    public function show(): string
    {
        $userData = AuthMiddleware::user();
        $user = User::find($userData['id']);
        $roles = User::roles($user['id']);

        return $this->view('profile/show', [
            'user' => $user,
            'roles' => $roles,
            'authenticated' => $this->isAuthenticated(),
            'username' => $this->getSessionUser(),
        ]);
    }

    public function showChangePassword(): string
    {
        return $this->view('auth/change-password', [
            'authenticated' => $this->isAuthenticated(),
            'username' => $this->getSessionUser(),
        ]);
    }

    public function changePassword(): string
    {
        $validator = Validator::make($_POST, [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
            'new_password_confirmation' => 'required',
        ])->validate();

        if ($validator->fails()) {
            foreach ($validator->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    Flash::set('error', $message);
                }
            }
            $this->redirect('/change-password');
            return '';
        }

        $data = $validator->validated();
        $userData = AuthMiddleware::user();

        if (User::updatePassword($userData['id'], $data['current_password'], $data['new_password'])) {
            RememberToken::clearForUser($userData['id']);

            Flash::set('success', 'Password changed successfully. All remembered sessions have been logged out.');
            $this->redirect('/change-password');
        }

        Flash::set('error', 'Current password is incorrect');
        $this->redirect('/change-password');

        return '';
    }

    public function apiKeys(): string
    {
        $userData = AuthMiddleware::user();
        $keys = ApiKey::forUser($userData['id']);

        // Check for newly generated key in session
        $newKey = $_SESSION['_new_api_key'] ?? null;
        unset($_SESSION['_new_api_key']);

        return $this->view('profile/api-keys', [
            'keys' => $keys,
            'newKey' => $newKey,
            'authenticated' => $this->isAuthenticated(),
            'username' => $this->getSessionUser(),
        ]);
    }

    public function generateApiKey(): string
    {
        $name = trim($_POST['name'] ?? '');
        if (empty($name)) {
            Flash::set('error', 'API key name is required.');
            $this->redirect('/profile/api-keys');
            return '';
        }

        $userData = AuthMiddleware::user();
        $rawKey = ApiKey::generate($userData['id'], $name);

        $_SESSION['_new_api_key'] = $rawKey;
        Flash::set('success', 'API key generated. Copy it now — it will not be shown again.');
        $this->redirect('/profile/api-keys');

        return '';
    }

    public function revokeApiKey(string $id): string
    {
        $userData = AuthMiddleware::user();
        ApiKey::revoke((int) $id, $userData['id']);

        Flash::set('success', 'API key revoked.');
        $this->redirect('/profile/api-keys');

        return '';
    }
}
