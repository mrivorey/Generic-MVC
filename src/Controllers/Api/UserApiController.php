<?php

namespace App\Controllers\Api;

use App\Models\User;

class UserApiController extends ApiController
{
    public function me(): string
    {
        $apiUser = $this->apiUser();
        $user = User::find($apiUser['user_id']);

        if (!$user) {
            return $this->error('User not found.', 'not_found', 404);
        }

        $roles = User::roles($user['id']);

        return $this->success([
            'id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'name' => $user['name'],
            'roles' => array_column($roles, 'name'),
            'last_login_at' => $user['last_login_at'],
            'created_at' => $user['created_at'],
        ]);
    }
}
