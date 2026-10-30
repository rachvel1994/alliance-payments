<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Project;
use Illuminate\Database\Seeder;

class ProjectsSeeder extends Seeder
{
    public function run(): void
    {
        Project::query()->upsert(
            $this->projects(),
            ['name', 'city'],
            ['name', 'city'],
        );
    }

    private function projects(): array
    {
        return [
            [
                'name' => 'Sea Tower',
                'city' => 'Batumi',
            ],
            [
                'name' => 'Green Hill',
                'city' => 'Batumi',
            ],
        ];
    }
}
