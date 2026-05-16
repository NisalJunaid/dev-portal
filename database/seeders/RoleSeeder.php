<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Seed roles and navigation permissions.
     */
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'view tickets',
            'view bugs',
            'update bugs',
            'view features',
            'view sprints',
            'view timeline',
            'view reports',
            'view clients',
            'manage clients',
            'view software',
            'manage software',
            'view settings',
            'manage global workspace',
            'manage own client',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $roles = [
            'super_admin' => $permissions,
            'kiel_manager' => $permissions,
            'developer' => ['view tickets', 'view bugs', 'update bugs', 'view features', 'view sprints', 'view timeline', 'view reports', 'view clients', 'manage clients', 'view software', 'manage software'],
            'client_admin' => ['view tickets', 'view bugs', 'view features', 'view sprints', 'view timeline', 'view reports', 'view software', 'view settings', 'manage own client'],
            'client_user' => ['view tickets', 'view bugs', 'view features', 'view sprints', 'view timeline', 'view reports', 'view software'],
        ];

        foreach ($roles as $roleName => $rolePermissions) {
            Role::findOrCreate($roleName, 'web')->syncPermissions($rolePermissions);
        }
    }
}
