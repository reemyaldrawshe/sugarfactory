<?php

namespace App\Services\Admin;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionService
{
    /**
     * Get all roles with their permissions
     */
    public function getRolesWithPermissions($request)
    {
        if($request->has('permissions') == 1)
            return Role::with('permissions')->get()->map(function ($role) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'guard_name' => $role->guard_name,
                    'permissions' => $role->permissions->map(function ($permission) {
                        return [
                            'id' => $permission->id,
                            'name' => $permission->name,
                            'guard_name' => $permission->guard_name,
                        ];
                    }),
                    'users_count' => $role->users()->count(),
                    'created_at' => $role->created_at,
                    'updated_at' => $role->updated_at,
                ];
            });
        else
            return Role::query()->get()->map(function ($role) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'guard_name' => $role->guard_name,
                    'users_count' => $role->users()->count(),
                    'created_at' => $role->created_at,
                    'updated_at' => $role->updated_at,
                ];
            });
    }

    /**
     * Get all permissions
     */
    public function getAllPermissions()
    {
        return Permission::all()->map(function ($permission) {
            return [
                'id' => $permission->id,
                'name' => $permission->name,
                'guard_name' => $permission->guard_name,
                'created_at' => $permission->created_at,
                'updated_at' => $permission->updated_at,
            ];
        });
    }

    /**
     * Create a new role
     */
    public function createRole(array $data): Role
    {
        $role = Role::create([
            'name' => $data['name'],
            'guard_name' => $data['guard_name'] ?? 'web',
        ]);

        if (isset($data['permissions']) && is_array($data['permissions'])) {
            $role->syncPermissions($data['permissions']);
        }

        return $role->load('permissions');
    }

    /**
     * Update an existing role
     */
    public function updateRole(Role $role, array $data): Role
    {
        $role->update([
            'name' => $data['name'] ?? $role->name,
            'guard_name' => $data['guard_name'] ?? $role->guard_name,
        ]);

        if (isset($data['permissions']) && is_array($data['permissions'])) {
            $role->syncPermissions($data['permissions']);
        }

        return $role->load('permissions');
    }

    /**
     * Delete a role (only if no users have it)
     */
    public function deleteRole(Role $role): array
    {
        $usersCount = $role->users()->count();

        if ($usersCount > 0) {
            throw new \Exception("Cannot delete role '{$role->name}' because {$usersCount} user(s) are currently assigned to it.");
        }

        $roleName = $role->name;
        $role->delete();

        return [
            'success' => true,
            'message' => "Role '{$roleName}' deleted successfully",
            'deleted_role' => $roleName
        ];
    }

    /**
     * Check if a role can be deleted
     */
    public function canDeleteRole(Role $role): array
    {
        $usersCount = $role->users()->count();

        return [
            'can_delete' => $usersCount === 0,
            'users_count' => $usersCount,
            'message' => $usersCount > 0
                ? "Cannot delete role '{$role->name}' because {$usersCount} user(s) are currently assigned to it."
                : "Role '{$role->name}' can be deleted safely."
        ];
    }

    /**
     * Get roles statistics
     */
    public function getRolesStatistics(): array
    {
        $totalRoles = Role::count();
        $totalPermissions = Permission::count();
        $rolesWithUsers = Role::whereHas('users')->count();
        $rolesWithoutUsers = $totalRoles - $rolesWithUsers;

        return [
            'total_roles' => $totalRoles,
            'total_permissions' => $totalPermissions,
            'roles_with_users' => $rolesWithUsers,
            'roles_without_users' => $rolesWithoutUsers,
            'deletable_roles' => $rolesWithoutUsers
        ];
    }
}

