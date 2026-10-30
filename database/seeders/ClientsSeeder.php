<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Client;
use Illuminate\Database\Seeder;

class ClientsSeeder extends Seeder
{
    public function run(): void
    {
        Client::query()->upsert(
            $this->clients(),
            ['personal_number'],
            ['name', 'phone'],
        );
    }

    private function clients(): array
    {
        return [
            [
                'name' => 'ნინო კახიძე',
                'personal_number' => '01001012345',
                'phone' => '+995 555 10 10 10',
            ],
            [
                'name' => 'გიორგი ბერიძე',
                'personal_number' => '01005098765',
                'phone' => '+995 555 20 20 20',
            ],
            [
                'name' => 'მარიამ ჯაფარიძე',
                'personal_number' => '61001055555',
                'phone' => '+995 555 30 30 30',
            ],
            [
                'name' => 'დავით ხალვაში',
                'personal_number' => '01024011111',
                'phone' => '+995 555 40 40 40',
            ],
            [
                'name' => 'თამარ წულუკიძე',
                'personal_number' => '33001077777',
                'phone' => '+995 555 50 50 50',
            ],
            [
                'name' => 'ლევან მახარაძე',
                'personal_number' => '01001099999',
                'phone' => '+995 555 60 60 60',
            ],
        ];
    }
}
