// JeePHP package: RolesO
if (!function_exists('assign_role')) {
    function assign_role(int $userId, string $roleName): void
    {
        if (table_connect('auth_users')->where('id', $userId)->first() === null) {
            throw new \\InvalidArgumentException('Unknown AuthO user ID: ' . $userId);
        }
        $role = table_connect('roles')->where('name', $roleName)->first();
        if ($role === null) {
            throw new \InvalidArgumentException('Unknown role: ' . $roleName);
        }
        if (table_connect('user_roles')->where('user_id', $userId)->where('role_id', $role['id'])->first() === null) {
            table_connect('user_roles')->insert(['user_id' => $userId, 'role_id' => $role['id']]);
        }
    }
}

if (!function_exists('grant_permission')) {
    function grant_permission(string $roleName, string $permissionName): void
    {
        $role = table_connect('roles')->where('name', $roleName)->first();
        $permission = table_connect('permissions')->where('name', $permissionName)->first();
        if ($role === null || $permission === null) {
            throw new \InvalidArgumentException('Create the role and permission before granting it.');
        }
        if (table_connect('role_permissions')->where('role_id', $role['id'])->where('permission_id', $permission['id'])->first() === null) {
            table_connect('role_permissions')->insert(['role_id' => $role['id'], 'permission_id' => $permission['id']]);
        }
    }
}

if (!function_exists('user_has_role')) {
    function user_has_role(int $userId, string $roleName): bool
    {
        $sql = 'SELECT 1 FROM `user_roles` ur INNER JOIN `roles` r ON r.`id` = ur.`role_id` WHERE ur.`user_id` = :user_id AND r.`name` = :role LIMIT 1';
        $query = \JeePHP\Database\Database::connection()->prepare($sql);
        $query->execute(['user_id' => $userId, 'role' => $roleName]);
        return $query->fetchColumn() !== false;
    }
}

if (!function_exists('user_can')) {
    function user_can(string $permission, ?int $userId = null): bool
    {
        $userId ??= is_numeric(session('auth_user_id')) ? (int) session('auth_user_id') : 0;
        if ($userId < 1) {
            return false;
        }
        $sql = 'SELECT 1 FROM `user_roles` ur INNER JOIN `role_permissions` rp ON rp.`role_id` = ur.`role_id` INNER JOIN `permissions` p ON p.`id` = rp.`permission_id` WHERE ur.`user_id` = :user_id AND p.`name` = :permission LIMIT 1';
        $query = \JeePHP\Database\Database::connection()->prepare($sql);
        $query->execute(['user_id' => $userId, 'permission' => $permission]);
        return $query->fetchColumn() !== false;
    }
}
