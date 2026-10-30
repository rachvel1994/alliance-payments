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

        $permissions = [
            'clients.view',
            'apartments.view',
            'contracts.view',
            'payments.view',
            'imports.view',
            'payments.import',
            'payments.reallocate',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $registrar->forgetCachedPermissions();

        $editor = Role::findOrCreate('editor', 'web');
        $viewer = Role::findOrCreate('viewer', 'web');

        $editor->syncPermissions(
            Permission::query()
                ->where('guard_name', 'web')
                ->whereIn('name', $permissions)
                ->get()
        );

        $viewer->syncPermissions(
            Permission::query()
                ->where('guard_name', 'web')
                ->whereIn('name', [
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
