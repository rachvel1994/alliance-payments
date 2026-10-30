<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Contract;
use App\Models\Project;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ContractsSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $contracts = [];

            foreach ($this->contracts() as $contract) {
                $client = Client::query()->where('personal_number', $contract['personal_number'])->sole();
                $apartment = Project::query()
                    ->where('name', $contract['project_name'])
                    ->where('city', $contract['project_city'])
                    ->sole()->apartments()
                    ->where('number', $contract['apartment_number'])
                    ->sole();

                $contracts[] = [
                    'apartment_id' => $apartment->getKey(),
                    'client_id' => $client->getKey(),
                    'contract_date' => $contract['contract_date'],
                    'price_minor' => $contract['price_minor'],
                ];
            }

            Contract::query()->upsert(
                $contracts,
                ['apartment_id'],
                ['client_id', 'contract_date', 'price_minor'],
            );
        });
    }

    private function contracts(): array
    {
        return [
            [
                'project_name' => 'Sea Tower',
                'project_city' => 'Batumi',
                'apartment_number' => 'A-301',
                'personal_number' => '01001012345',
                'contract_date' => '2026-03-10',
                'price_minor' => 8600000,
            ],
            [
                'project_name' => 'Sea Tower',
                'project_city' => 'Batumi',
                'apartment_number' => 'A-302',
                'personal_number' => '01005098765',
                'contract_date' => '2026-02-05',
                'price_minor' => 11200000,
            ],
            [
                'project_name' => 'Green Hill',
                'project_city' => 'Batumi',
                'apartment_number' => 'B-202',
                'personal_number' => '01005098765',
                'contract_date' => '2026-05-20',
                'price_minor' => 8842500,
            ],
            [
                'project_name' => 'Green Hill',
                'project_city' => 'Batumi',
                'apartment_number' => 'B-201',
                'personal_number' => '61001055555',
                'contract_date' => '2026-01-15',
                'price_minor' => 8842500,
            ],
            [
                'project_name' => 'Green Hill',
                'project_city' => 'Batumi',
                'apartment_number' => 'B-302',
                'personal_number' => '01024011111',
                'contract_date' => '2026-02-14',
                'price_minor' => 11000000,
            ],
            [
                'project_name' => 'Sea Tower',
                'project_city' => 'Batumi',
                'apartment_number' => 'A-501',
                'personal_number' => '33001077777',
                'contract_date' => '2026-09-01',
                'price_minor' => 9200000,
            ],
            [
                'project_name' => 'Sea Tower',
                'project_city' => 'Batumi',
                'apartment_number' => 'A-401',
                'personal_number' => '01001099999',
                'contract_date' => '2026-06-15',
                'price_minor' => 9000000,
            ],
        ];
    }
}
