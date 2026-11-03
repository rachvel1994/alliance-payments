<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $registrar = app(PermissionRegistrar::class);

        $registrar->forgetCachedPermissions();

        $permissions = array_keys(config('access.permissions'));

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $registrar->forgetCachedPermissions();

        $editor = Role::findOrCreate('editor', 'web');
        $viewer = Role::findOrCreate('viewer', 'web');
        $admin = Role::findOrCreate('admin', 'web');

        $admin->syncPermissions(Permission::query()->where('guard_name', 'web')->whereIn('name', $permissions)->get());

        $editor->syncPermissions(
            Permission::query()
                ->where('guard_name', 'web')
                ->whereIn('name', array_diff($permissions, ['users.manage']))
                ->get()
        );

        $viewer->syncPermissions(
            Permission::query()
                ->where('guard_name', 'web')
                ->whereIn('name', [
                    'overview.view',
                    'clients.view',
                    'apartments.view',
                    'contracts.view',
                    'payments.view',
                    'imports.view',
                ])
                ->get()
        );

        $registrar->forgetCachedPermissions();
    }
}
