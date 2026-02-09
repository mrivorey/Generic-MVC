<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Core\Flash;
use App\Core\Validator;
use App\Models\Role;
use App\Models\Permission;

class RoleController extends BaseController
{
    public function index(): string
    {
        $roles = Role::all('name ASC');

        // Count users per role
        $pdo = \App\Core\Database::getConnection();
        $stmt = $pdo->query('SELECT role_id, COUNT(*) as cnt FROM user_roles GROUP BY role_id');
        $counts = [];
        while ($row = $stmt->fetch()) {
            $counts[$row['role_id']] = (int) $row['cnt'];
        }

        return $this->view('admin/roles/index', [
            'roles' => $roles,
            'userCounts' => $counts,
            'authenticated' => $this->isAuthenticated(),
            'username' => $this->getSessionUser(),
        ]);
    }

    public function create(): string
    {
        $permissions = Permission::all('name ASC');

        return $this->view('admin/roles/create', [
            'permissions' => $permissions,
            'authenticated' => $this->isAuthenticated(),
            'username' => $this->getSessionUser(),
        ]);
    }

    public function store(): string
    {
        $validator = Validator::make($_POST, [
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:roles,slug',
            'description' => 'string|max:1000',
        ])->validate();

        if ($validator->fails()) {
            foreach ($validator->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    Flash::set('error', $message);
                }
            }
            Flash::setOldInput($_POST);
            $this->redirect('/admin/roles/create');
            return '';
        }

        $data = $validator->validated();
        $roleId = Role::create($data);

        $permissionIds = array_filter(array_map('intval', $_POST['permissions'] ?? []));
        if (!empty($permissionIds)) {
            Role::syncPermissions($roleId, $permissionIds);
        }

        Flash::set('success', 'Role created successfully.');
        $this->redirect('/admin/roles');
        return '';
    }

    public function edit(string $id): string
    {
        $role = Role::find((int) $id);
        if (!$role) {
            Flash::set('error', 'Role not found.');
            $this->redirect('/admin/roles');
            return '';
        }

        $permissions = Permission::all('name ASC');
        $rolePermissions = Role::permissions((int) $role['id']);
        $rolePermissionIds = array_column($rolePermissions, 'id');

        return $this->view('admin/roles/edit', [
            'role' => $role,
            'permissions' => $permissions,
            'rolePermissionIds' => $rolePermissionIds,
            'authenticated' => $this->isAuthenticated(),
            'username' => $this->getSessionUser(),
        ]);
    }

    public function update(string $id): string
    {
        $roleId = (int) $id;
        $role = Role::find($roleId);
        if (!$role) {
            Flash::set('error', 'Role not found.');
            $this->redirect('/admin/roles');
            return '';
        }

        $validator = Validator::make($_POST, [
            'name' => 'required|string|max:255',
            'slug' => "required|string|max:255|unique:roles,slug,{$roleId}",
            'description' => 'string|max:1000',
        ])->validate();

        if ($validator->fails()) {
            foreach ($validator->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    Flash::set('error', $message);
                }
            }
            Flash::setOldInput($_POST);
            $this->redirect("/admin/roles/{$roleId}/edit");
            return '';
        }

        $data = $validator->validated();
        Role::update($roleId, $data);

        $permissionIds = array_filter(array_map('intval', $_POST['permissions'] ?? []));
        Role::syncPermissions($roleId, $permissionIds);

        Flash::set('success', 'Role updated successfully.');
        $this->redirect('/admin/roles');
        return '';
    }

    public function destroy(string $id): string
    {
        $roleId = (int) $id;
        $role = Role::find($roleId);

        if (!$role) {
            Flash::set('error', 'Role not found.');
            $this->redirect('/admin/roles');
            return '';
        }

        if ($role['slug'] === 'admin') {
            Flash::set('error', 'The admin role cannot be deleted.');
            $this->redirect('/admin/roles');
            return '';
        }

        Role::delete($roleId);

        Flash::set('success', 'Role deleted successfully.');
        $this->redirect('/admin/roles');
        return '';
    }
}
