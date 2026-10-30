<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Apartment;
use App\Models\Project;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ApartmentsSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $apartments = [];

            foreach ($this->apartments() as $apartment) {
                $project = Project::query()
                    ->where('name', $apartment['project_name'])
                    ->where('city', $apartment['project_city'])
                    ->sole();

                $apartments[] = [
                    'project_id' => $project->getKey(),
                    'number' => $apartment['number'],
                    'floor' => $apartment['floor'],
                    'area' => $apartment['area'],
                    'price_minor' => $apartment['price_minor'],
                    'status' => $apartment['status'],
                ];
            }

            Apartment::query()->upsert(
                $apartments,
                ['project_id', 'number'],
                ['floor', 'area', 'price_minor', 'status'],
            );
        });
    }

    private function apartments(): array
    {
        return [
            [
                'project_name' => 'Sea Tower',
                'project_city' => 'Batumi',
                'number' => 'A-301',
                'floor' => 3,
                'area' => '55.40',
                'price_minor' => 8864000,
                'status' => 'sold',
            ],
            [
                'project_name' => 'Sea Tower',
                'project_city' => 'Batumi',
                'number' => 'A-302',
                'floor' => 3,
                'area' => '72.10',
                'price_minor' => 11536000,
                'status' => 'sold',
            ],
            [
                'project_name' => 'Sea Tower',
                'project_city' => 'Batumi',
                'number' => 'A-401',
                'floor' => 4,
                'area' => '55.40',
                'price_minor' => 9000000,
                'status' => 'sold',
            ],
            [
                'project_name' => 'Sea Tower',
                'project_city' => 'Batumi',
                'number' => 'A-402',
                'floor' => 4,
                'area' => '72.10',
                'price_minor' => 11800000,
                'status' => 'free',
            ],
            [
                'project_name' => 'Sea Tower',
                'project_city' => 'Batumi',
                'number' => 'A-501',
                'floor' => 5,
                'area' => '55.40',
                'price_minor' => 9200000,
                'status' => 'sold',
            ],
            [
                'project_name' => 'Sea Tower',
                'project_city' => 'Batumi',
                'number' => 'A-502',
                'floor' => 5,
                'area' => '72.10',
                'price_minor' => 12100000,
                'status' => 'free',
            ],
            [
                'project_name' => 'Green Hill',
                'project_city' => 'Batumi',
                'number' => 'B-101',
                'floor' => 1,
                'area' => '48.00',
                'price_minor' => 6240000,
                'status' => 'free',
            ],
            [
                'project_name' => 'Green Hill',
                'project_city' => 'Batumi',
                'number' => 'B-102',
                'floor' => 1,
                'area' => '48.00',
                'price_minor' => 6240000,
                'status' => 'free',
            ],
            [
                'project_name' => 'Green Hill',
                'project_city' => 'Batumi',
                'number' => 'B-201',
                'floor' => 2,
                'area' => '65.50',
                'price_minor' => 8842500,
                'status' => 'sold',
            ],
            [
                'project_name' => 'Green Hill',
                'project_city' => 'Batumi',
                'number' => 'B-202',
                'floor' => 2,
                'area' => '65.50',
                'price_minor' => 8842500,
                'status' => 'sold',
            ],
            [
                'project_name' => 'Green Hill',
                'project_city' => 'Batumi',
                'number' => 'B-301',
                'floor' => 3,
                'area' => '65.50',
                'price_minor' => 9000000,
                'status' => 'free',
            ],
            [
                'project_name' => 'Green Hill',
                'project_city' => 'Batumi',
                'number' => 'B-302',
                'floor' => 3,
                'area' => '80.00',
                'price_minor' => 11200000,
                'status' => 'sold',
            ],
        ];
    }
}
