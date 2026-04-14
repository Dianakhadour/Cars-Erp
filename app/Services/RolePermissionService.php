<?php

namespace App\Services;

use App\Models\Role;
use App\Models\Permission;

class RolePermissionService
{
    /**
     * Define which roles can access which permissions
     */
    public static function getRolePermissionsMap(): array
    {
        return [
            'super-admin' => [
                // Company permissions
                'company.create', 'company.view', 'company.edit', 'company.delete',
                // Branch permissions  
                'branch.create', 'branch.view', 'branch.edit', 'branch.delete',
                // Reporting permissions
                'report.branch.view', 'report.branch.multi', 'report.branch.all',
                // User management
                'user.manage', 'role.manage',
            ],
            'company-admin' => [
                // Company permissions
                'company.create', 'company.view', 'company.edit', 
                // Branch permissions
                'branch.create', 'branch.view', 'branch.edit', 'branch.delete',
                // Reporting permissions
                'report.branch.view', 'report.branch.multi', 'report.branch.all',
                // Limited user management
                'user.manage',
            ],
            'branch-manager' => [
                // Company permissions (view only)
                'company.view',
                // Branch permissions (own branch only)
                'branch.view', 'branch.edit',
                // Reporting permissions (own branch only)
                'report.branch.view',
            ],
            'accountant' => [
                // Company permissions (view only)
                'company.view',
                // Branch permissions (view only)
                'branch.view',
                // Reporting permissions (multi-branch)
                'report.branch.view', 'report.branch.multi',
            ],
            'viewer' => [
                // Read-only access
                'company.view',
                'branch.view',
                'report.branch.view',
            ],
        ];
    }

    /**
     * Assign permissions to roles
     */
    public static function assignPermissionsToRoles(): void
    {
        $permissionMap = self::getRolePermissionsMap();

        foreach ($permissionMap as $roleSlug => $permissionSlugs) {
            $role = Role::where('slug', $roleSlug)->first();
            
            if ($role) {
                $permissions = Permission::whereIn('slug', $permissionSlugs)->get();
                $role->permissions()->sync($permissions->pluck('id'));
            }
        }
    }

    /**
     * Check if user has permission
     */
    public static function userHasPermission($user, string $permission): bool
    {
        return $user->roles()
            ->whereHas('permissions', function ($query) use ($permission) {
                $query->where('slug', $permission);
            })
            ->exists();
    }

    /**
     * Get user's allowed permissions
     */
    public static function getUserPermissions($user): array
    {
        return $user->roles()
            ->with('permissions')
            ->get()
            ->pluck('permissions')
            ->flatten()
            ->pluck('slug')
            ->unique()
            ->toArray();
    }
}