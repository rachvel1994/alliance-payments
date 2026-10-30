<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DemoUsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [
            [
                'name' => 'Editor',
                'email' => 'editor@example.test',
                'role' => 'editor',
                'password' => env('DEMO_EDITOR_PASSWORD'),
            ],
            [
                'name' => 'Viewer',
                'email' => 'viewer@example.test',
                'role' => 'viewer',
                'password' => env('DEMO_VIEWER_PASSWORD'),
            ],
        ];

        foreach ($users as $data) {
            if (!is_string($data['password']) || $data['password'] === '') {
                throw new RuntimeException(
                    "Missing demo password for {$data['role']}"
                );
            }

            $user = User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => Hash::make($data['password']),
                    'email_verified_at' => now(),
                ]
            );

            $user->syncRoles([$data['role']]);
        }
    }
}
