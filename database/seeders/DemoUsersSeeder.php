<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

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
            if (! is_string($data['password']) || $data['password'] === '') {
                throw new RuntimeException(
                    "დემო მომხმარებლის ({$data['role']}) პაროლი მითითებული არ არის."
                );
            }

            $user = User::query()->firstOrNew(['email' => $data['email']]);
            $user->name = $data['name'];
            $user->email_verified_at ??= now();

            if (! $user->exists || ! Hash::check($data['password'], $user->password)) {
                $user->password = Hash::make($data['password']);
            }

            $user->save();

            $user->syncRoles([$data['role']]);
        }
    }
}
