<?php

namespace Database\Seeders;

use App\Models\Project;
use Illuminate\Database\Seeder;

class ProjectsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Project::query()->insertOrIgnore($this->projects());
    }

    private function projects(): array
    {
        return [
            [
                'name' => 'Sea Tower',
                'city' => 'Batumi'
            ],
            [
                'name' => 'Green Hill',
                'city' => 'Batumi'
            ]
        ];
    }
}
