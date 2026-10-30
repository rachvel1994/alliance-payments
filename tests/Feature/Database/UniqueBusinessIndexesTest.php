<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use Database\Seeders\ProjectsSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UniqueBusinessIndexesTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_seeding_does_not_create_duplicate_projects(): void
    {
        $this->seed(ProjectsSeeder::class);
        $this->seed(ProjectsSeeder::class);

        $this->assertDatabaseCount('projects', 2);
    }

    public function test_duplicate_project_name_and_city_are_rejected(): void
    {
        DB::table('projects')->insert(['name' => 'Sea Tower', 'city' => 'Batumi']);

        $this->expectException(UniqueConstraintViolationException::class);

        DB::table('projects')->insert(['name' => 'Sea Tower', 'city' => 'Batumi']);
    }

    public function test_project_names_can_repeat_in_different_cities(): void
    {
        DB::table('projects')->insert([
            ['name' => 'Sea Tower', 'city' => 'Batumi'],
            ['name' => 'Sea Tower', 'city' => 'Tbilisi'],
            ['name' => 'Green Hill', 'city' => 'Batumi'],
        ]);

        $this->assertDatabaseCount('projects', 3);
    }

    public function test_clients_can_have_no_current_allocation_run(): void
    {
        DB::table('clients')->insert([
            ['name' => 'First client', 'personal_number' => '01001012345'],
            ['name' => 'Second client', 'personal_number' => '01005098765'],
        ]);

        $this->assertSame(
            2,
            DB::table('clients')->whereNull('current_allocation_run_id')->count(),
        );
    }

    public function test_a_current_allocation_run_cannot_be_shared_by_clients(): void
    {
        $firstClientId = DB::table('clients')->insertGetId([
            'name' => 'First client', 'personal_number' => '01001012345',
        ]);
        $secondClientId = DB::table('clients')->insertGetId([
            'name' => 'Second client', 'personal_number' => '01005098765',
        ]);
        $runId = DB::table('allocation_runs')->insertGetId([
            'client_id' => $firstClientId, 'reason' => 'seed',
        ]);
        DB::table('clients')->where('id', $firstClientId)
            ->update(['current_allocation_run_id' => $runId]);

        $this->expectException(UniqueConstraintViolationException::class);

        DB::table('clients')->where('id', $secondClientId)
            ->update(['current_allocation_run_id' => $runId]);
    }

    public function test_multiple_allocation_runs_preserve_client_history(): void
    {
        $clientId = DB::table('clients')->insertGetId([
            'name' => 'Client', 'personal_number' => '01001012345',
        ]);
        $createdAt = '2026-10-30 12:00:00';

        DB::table('allocation_runs')->insert([
            ['client_id' => $clientId, 'reason' => 'manual', 'created_at' => $createdAt],
            ['client_id' => $clientId, 'reason' => 'manual', 'created_at' => $createdAt],
        ]);

        $this->assertDatabaseCount('allocation_runs', 2);
    }

    public function test_repeated_file_hashes_preserve_import_history(): void
    {
        $import = ['filename' => 'payments.xlsx', 'file_hash' => str_repeat('a', 64)];

        DB::table('payment_imports')->insert([$import, $import]);

        $this->assertDatabaseCount('payment_imports', 2);
    }
}
