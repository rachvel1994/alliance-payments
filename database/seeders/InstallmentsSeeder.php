<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Installment;
use App\Models\Project;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InstallmentsSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $installments = [];

            foreach ($this->installments() as $group) {
                $contract = Project::query()
                    ->where('name', $group['project_name'])
                    ->where('city', $group['project_city'])
                    ->sole()->apartments()
                    ->where('number', $group['apartment_number'])
                    ->sole()->contract()->sole();

                if (array_sum(array_column($group['schedule'], 'amount_minor')) !== (int) $contract->price_minor) {
                    throw new RuntimeException("Invalid installment total for contract #{$contract->getKey()}.");
                }

                foreach ($group['schedule'] as $installment) {
                    $installments[] = [
                        'contract_id' => $contract->getKey(),
                        'sequence' => $installment['sequence'],
                        'due_date' => $installment['due_date'],
                        'amount_minor' => $installment['amount_minor'],
                    ];
                }
            }

            Installment::query()->upsert(
                $installments,
                ['contract_id', 'sequence'],
                ['due_date', 'amount_minor'],
            );
        });
    }

    private function installments(): array
    {
        return [
            [
                'project_name' => 'Sea Tower',
                'project_city' => 'Batumi',
                'apartment_number' => 'A-301',
                'schedule' => [
                    [
                        'sequence' => 1,
                        'due_date' => '2026-03-10',
                        'amount_minor' => 2000000,
                    ],
                    [
                        'sequence' => 2,
                        'due_date' => '2026-04-10',
                        'amount_minor' => 600000,
                    ],
                    [
                        'sequence' => 3,
                        'due_date' => '2026-05-10',
                        'amount_minor' => 600000,
                    ],
                    [
                        'sequence' => 4,
                        'due_date' => '2026-06-10',
                        'amount_minor' => 600000,
                    ],
                    [
                        'sequence' => 5,
                        'due_date' => '2026-07-10',
                        'amount_minor' => 600000,
                    ],
                    [
                        'sequence' => 6,
                        'due_date' => '2026-08-10',
                        'amount_minor' => 600000,
                    ],
                    [
                        'sequence' => 7,
                        'due_date' => '2026-09-10',
                        'amount_minor' => 600000,
                    ],
                    [
                        'sequence' => 8,
                        'due_date' => '2026-10-10',
                        'amount_minor' => 600000,
                    ],
                    [
                        'sequence' => 9,
                        'due_date' => '2026-11-10',
                        'amount_minor' => 600000,
                    ],
                    [
                        'sequence' => 10,
                        'due_date' => '2026-12-10',
                        'amount_minor' => 600000,
                    ],
                    [
                        'sequence' => 11,
                        'due_date' => '2027-01-10',
                        'amount_minor' => 600000,
                    ],
                    [
                        'sequence' => 12,
                        'due_date' => '2027-02-10',
                        'amount_minor' => 600000,
                    ],
                ],
            ],
            [
                'project_name' => 'Sea Tower',
                'project_city' => 'Batumi',
                'apartment_number' => 'A-302',
                'schedule' => [
                    [
                        'sequence' => 1,
                        'due_date' => '2026-02-05',
                        'amount_minor' => 4000000,
                    ],
                    [
                        'sequence' => 2,
                        'due_date' => '2026-03-05',
                        'amount_minor' => 1200000,
                    ],
                    [
                        'sequence' => 3,
                        'due_date' => '2026-04-05',
                        'amount_minor' => 1200000,
                    ],
                    [
                        'sequence' => 4,
                        'due_date' => '2026-05-05',
                        'amount_minor' => 1200000,
                    ],
                    [
                        'sequence' => 5,
                        'due_date' => '2026-06-05',
                        'amount_minor' => 1200000,
                    ],
                    [
                        'sequence' => 6,
                        'due_date' => '2026-07-05',
                        'amount_minor' => 1200000,
                    ],
                    [
                        'sequence' => 7,
                        'due_date' => '2026-08-05',
                        'amount_minor' => 1200000,
                    ],
                ],
            ],
            [
                'project_name' => 'Green Hill',
                'project_city' => 'Batumi',
                'apartment_number' => 'B-202',
                'schedule' => [
                    [
                        'sequence' => 1,
                        'due_date' => '2026-05-20',
                        'amount_minor' => 1842500,
                    ],
                    [
                        'sequence' => 2,
                        'due_date' => '2026-06-05',
                        'amount_minor' => 1000000,
                    ],
                    [
                        'sequence' => 3,
                        'due_date' => '2026-07-05',
                        'amount_minor' => 1000000,
                    ],
                    [
                        'sequence' => 4,
                        'due_date' => '2026-08-05',
                        'amount_minor' => 1000000,
                    ],
                    [
                        'sequence' => 5,
                        'due_date' => '2026-09-05',
                        'amount_minor' => 1000000,
                    ],
                    [
                        'sequence' => 6,
                        'due_date' => '2026-10-05',
                        'amount_minor' => 1000000,
                    ],
                    [
                        'sequence' => 7,
                        'due_date' => '2026-11-05',
                        'amount_minor' => 1000000,
                    ],
                    [
                        'sequence' => 8,
                        'due_date' => '2026-12-05',
                        'amount_minor' => 1000000,
                    ],
                ],
            ],
            [
                'project_name' => 'Green Hill',
                'project_city' => 'Batumi',
                'apartment_number' => 'B-201',
                'schedule' => [
                    [
                        'sequence' => 1,
                        'due_date' => '2026-01-15',
                        'amount_minor' => 2242500,
                    ],
                    [
                        'sequence' => 2,
                        'due_date' => '2026-01-31',
                        'amount_minor' => 942857,
                    ],
                    [
                        'sequence' => 3,
                        'due_date' => '2026-02-28',
                        'amount_minor' => 942857,
                    ],
                    [
                        'sequence' => 4,
                        'due_date' => '2026-03-31',
                        'amount_minor' => 942857,
                    ],
                    [
                        'sequence' => 5,
                        'due_date' => '2026-04-30',
                        'amount_minor' => 942857,
                    ],
                    [
                        'sequence' => 6,
                        'due_date' => '2026-05-31',
                        'amount_minor' => 942857,
                    ],
                    [
                        'sequence' => 7,
                        'due_date' => '2026-06-30',
                        'amount_minor' => 942857,
                    ],
                    [
                        'sequence' => 8,
                        'due_date' => '2026-07-31',
                        'amount_minor' => 942858,
                    ],
                ],
            ],
            [
                'project_name' => 'Green Hill',
                'project_city' => 'Batumi',
                'apartment_number' => 'B-302',
                'schedule' => [
                    [
                        'sequence' => 1,
                        'due_date' => '2026-02-14',
                        'amount_minor' => 5000000,
                    ],
                    [
                        'sequence' => 2,
                        'due_date' => '2026-03-14',
                        'amount_minor' => 3000000,
                    ],
                    [
                        'sequence' => 3,
                        'due_date' => '2026-04-14',
                        'amount_minor' => 3000000,
                    ],
                ],
            ],
            [
                'project_name' => 'Sea Tower',
                'project_city' => 'Batumi',
                'apartment_number' => 'A-501',
                'schedule' => [
                    [
                        'sequence' => 1,
                        'due_date' => '2026-09-01',
                        'amount_minor' => 3200000,
                    ],
                    [
                        'sequence' => 2,
                        'due_date' => '2026-10-01',
                        'amount_minor' => 1000000,
                    ],
                    [
                        'sequence' => 3,
                        'due_date' => '2026-11-01',
                        'amount_minor' => 1000000,
                    ],
                    [
                        'sequence' => 4,
                        'due_date' => '2026-12-01',
                        'amount_minor' => 1000000,
                    ],
                    [
                        'sequence' => 5,
                        'due_date' => '2027-01-01',
                        'amount_minor' => 1000000,
                    ],
                    [
                        'sequence' => 6,
                        'due_date' => '2027-02-01',
                        'amount_minor' => 1000000,
                    ],
                    [
                        'sequence' => 7,
                        'due_date' => '2027-03-01',
                        'amount_minor' => 1000000,
                    ],
                ],
            ],
            [
                'project_name' => 'Sea Tower',
                'project_city' => 'Batumi',
                'apartment_number' => 'A-401',
                'schedule' => [
                    [
                        'sequence' => 1,
                        'due_date' => '2026-06-15',
                        'amount_minor' => 3000000,
                    ],
                    [
                        'sequence' => 2,
                        'due_date' => '2026-07-15',
                        'amount_minor' => 1000000,
                    ],
                    [
                        'sequence' => 3,
                        'due_date' => '2026-08-15',
                        'amount_minor' => 1000000,
                    ],
                    [
                        'sequence' => 4,
                        'due_date' => '2026-09-15',
                        'amount_minor' => 1000000,
                    ],
                    [
                        'sequence' => 5,
                        'due_date' => '2026-10-15',
                        'amount_minor' => 1000000,
                    ],
                    [
                        'sequence' => 6,
                        'due_date' => '2026-11-15',
                        'amount_minor' => 1000000,
                    ],
                    [
                        'sequence' => 7,
                        'due_date' => '2026-12-15',
                        'amount_minor' => 1000000,
                    ],
                ],
            ],
        ];
    }
}
