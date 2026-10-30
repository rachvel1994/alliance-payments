<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

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

        $editor = Role::findOrCreate('editor', 'web');
        $viewer = Role::findOrCreate('viewer', 'web');

        $editor->syncPermissions($permissions);

        $viewer->syncPermissions([
            'clients.view',
            'apartments.view',
            'contracts.view',
            'payments.view',
            'imports.view',
        ]);
    }
}
