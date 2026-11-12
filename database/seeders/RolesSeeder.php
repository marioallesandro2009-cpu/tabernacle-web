<?php

namespace Database\Seeders;

use App\Support\Permissions;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (array_keys(Permissions::ALL) as $name) {
            Permission::findOrCreate($name, 'web');
        }
        foreach (Permissions::ROLES as $role => $perms) {
            $r = Role::findOrCreate($role, 'web');
            $r->syncPermissions($perms === ['*'] ? array_keys(Permissions::ALL) : $perms);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
