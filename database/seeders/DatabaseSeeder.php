<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        DB::transaction(fn () => $this->call([
            RolesAndPermissionsSeeder::class,
            DemoUsersSeeder::class,
            ClientsSeeder::class,
            ProjectsSeeder::class,
            ApartmentsSeeder::class,
            ContractsSeeder::class,
            InstallmentsSeeder::class,
            PaymentsSeeder::class,
            PaymentAllocationsSeeder::class,
        ]));
    }
}
