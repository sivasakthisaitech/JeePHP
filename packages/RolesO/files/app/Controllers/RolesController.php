<?php
declare(strict_types=1);

namespace App\Controllers;

use JeePHP\Http\Request;

final class RolesController
{
    public function index(): void
    {
        $this->requireManager();
        load_view('roles/index', [
            'roles' => table_fetch('roles'),
            'permissions' => table_fetch('permissions'),
            'notice' => session('roles_notice'),
        ]);
        session('roles_notice', null);
    }

    public function storeRole(Request $request): void
    {
        $this->requireManager();
        $name = strtolower(trim((string) $request->input('name', '')));
        if (!preg_match('/^[a-z][a-z0-9._-]{1,63}$/', $name)) {
            http_response_code(422);
            session('roles_notice', 'Role name must be 2–64 lowercase letters, numbers, dots, underscores, or hyphens.');
            redirect('roles');
        }
        if (table_connect('roles')->where('name', $name)->first() === null) {
            table_connect('roles')->insert(['name' => $name]);
        }
        session('roles_notice', 'Role saved.');
        redirect('roles');
    }

    public function storePermission(Request $request): void
    {
        $this->requireManager();
        $name = strtolower(trim((string) $request->input('name', '')));
        if (!preg_match('/^[a-z][a-z0-9._-]{1,95}$/', $name)) {
            http_response_code(422);
            session('roles_notice', 'Permission name must be 2–96 lowercase letters, numbers, dots, underscores, or hyphens.');
            redirect('roles');
        }
        if (table_connect('permissions')->where('name', $name)->first() === null) {
            table_connect('permissions')->insert(['name' => $name]);
        }
        session('roles_notice', 'Permission saved.');
        redirect('roles');
    }

    public function grant(Request $request): void
    {
        $this->requireManager();
        $roleId = filter_var($request->input('role_id'), FILTER_VALIDATE_INT);
        $permissionId = filter_var($request->input('permission_id'), FILTER_VALIDATE_INT);
        if ($roleId === false || $permissionId === false) {
            http_response_code(422);
            session('roles_notice', 'Choose a valid role and permission.');
            redirect('roles');
        }
        $role = table_connect('roles')->where('id', $roleId)->first();
        $permission = table_connect('permissions')->where('id', $permissionId)->first();
        if ($role !== null && $permission !== null) {
            grant_permission((string) $role['name'], (string) $permission['name']);
            session('roles_notice', 'Permission assigned to role.');
        }
        redirect('roles');
    }

    private function requireManager(): void
    {
        if (!function_exists('auth_check') || !auth_check() || !user_can('roles.manage')) {
            http_response_code(403);
            echo '403 - You need the roles.manage permission.';
            exit;
        }
    }
}
