<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Core\Flash;
use App\Core\Validator;
use App\Models\User;
use App\Models\Role;

class UserController extends BaseController
{
    public function index(): string
    {
        $users = User::all('id ASC');
        $roles = Role::all('name ASC');

        // Build map of user_id => [role, role, ...]
        $pdo = \App\Core\Database::getConnection();
        $stmt = $pdo->query(
            'SELECT ur.user_id, r.id AS role_id, r.name, r.slug
             FROM user_roles ur
             JOIN roles r ON r.id = ur.role_id
             ORDER BY r.name'
        );
        $userRolesMap = [];
        while ($row = $stmt->fetch()) {
            $userRolesMap[$row['user_id']][] = $row;
        }

        return $this->view('admin/users/index', [
            'users' => $users,
            'userRolesMap' => $userRolesMap,
            'authenticated' => $this->isAuthenticated(),
            'username' => $this->getSessionUser(),
        ]);
    }

    public function create(): string
    {
        $roles = Role::all('name ASC');

        return $this->view('admin/users/create', [
            'roles' => $roles,
            'authenticated' => $this->isAuthenticated(),
            'username' => $this->getSessionUser(),
        ]);
    }

    public function store(): string
    {
        $validator = Validator::make($_POST, [
            'username' => 'required|string|min:3|max:255|unique:users,username',
            'email' => 'required|email|max:255|unique:users,email',
            'name' => 'required|string|max:255',
            'password' => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required',
        ])->validate();

        if ($validator->fails()) {
            foreach ($validator->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    Flash::set('error', $message);
                }
            }
            Flash::setOldInput($_POST);
            $this->redirect('/admin/users/create');
            return '';
        }

        $data = $validator->validated();
        $password = $data['password'];
        unset($data['password'], $data['password_confirmation']);
        $data['password_hash'] = password_hash($password, PASSWORD_ARGON2ID);
        $data['is_active'] = isset($_POST['is_active']) ? 1 : 0;

        $userId = User::create($data);

        $roleIds = array_filter(array_map('intval', $_POST['role_ids'] ?? []));
        User::syncRoles($userId, $roleIds);

        Flash::set('success', 'User created successfully.');
        $this->redirect('/admin/users');
        return '';
    }

    public function edit(string $id): string
    {
        $user = User::find((int) $id);
        if (!$user) {
            Flash::set('error', 'User not found.');
            $this->redirect('/admin/users');
            return '';
        }

        $roles = Role::all('name ASC');
        $userRoles = User::roles((int) $user['id']);
        $userRoleIds = array_column($userRoles, 'id');

        return $this->view('admin/users/edit', [
            'user' => $user,
            'roles' => $roles,
            'userRoleIds' => $userRoleIds,
            'authenticated' => $this->isAuthenticated(),
            'username' => $this->getSessionUser(),
        ]);
    }

    public function update(string $id): string
    {
        $userId = (int) $id;
        $user = User::find($userId);
        if (!$user) {
            Flash::set('error', 'User not found.');
            $this->redirect('/admin/users');
            return '';
        }

        $validator = Validator::make($_POST, [
            'username' => "required|string|min:3|max:255|unique:users,username,{$userId}",
            'email' => "required|email|max:255|unique:users,email,{$userId}",
            'name' => 'required|string|max:255',
        ])->validate();

        if ($validator->fails()) {
            foreach ($validator->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    Flash::set('error', $message);
                }
            }
            Flash::setOldInput($_POST);
            $this->redirect("/admin/users/{$userId}/edit");
            return '';
        }

        $data = $validator->validated();
        $data['is_active'] = isset($_POST['is_active']) ? 1 : 0;

        $newPassword = $_POST['password'] ?? '';
        if (!empty($newPassword)) {
            if (strlen($newPassword) < 8) {
                Flash::set('error', 'Password must be at least 8 characters.');
                Flash::setOldInput($_POST);
                $this->redirect("/admin/users/{$userId}/edit");
                return '';
            }
            $data['password_hash'] = password_hash($newPassword, PASSWORD_ARGON2ID);
        }

        User::update($userId, $data);

        $roleIds = array_filter(array_map('intval', $_POST['role_ids'] ?? []));
        User::syncRoles($userId, $roleIds);

        Flash::set('success', 'User updated successfully.');
        $this->redirect('/admin/users');
        return '';
    }

    public function destroy(string $id): string
    {
        $userId = (int) $id;

        if ($userId === (int) ($_SESSION['user_id'] ?? 0)) {
            Flash::set('error', 'You cannot delete your own account.');
            $this->redirect('/admin/users');
            return '';
        }

        $user = User::find($userId);
        if (!$user) {
            Flash::set('error', 'User not found.');
            $this->redirect('/admin/users');
            return '';
        }

        User::delete($userId);

        Flash::set('success', 'User deleted successfully.');
        $this->redirect('/admin/users');
        return '';
    }
}
