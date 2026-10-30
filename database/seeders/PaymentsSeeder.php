<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Payment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PaymentsSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $payments = $this->payments();
            $clients = Client::query()
                ->whereIn('personal_number', array_column($payments, 'personal_number'))
                ->pluck('id', 'personal_number');

            foreach ($payments as $index => $payment) {
                $payments[$index]['client_id'] = $clients->get($payment['personal_number']);
            }

            Payment::query()->upsert(
                $payments,
                ['bank_reference'],
                ['client_id', 'paid_at', 'amount_minor', 'personal_number', 'payer_name', 'purpose'],
            );
        });
    }

    private function payments(): array
    {
        return [
            [
                'paid_at' => '2026-01-15',
                'amount_minor' => 2242500,
                'personal_number' => '61001055555',
                'payer_name' => 'მარიამ ჯაფარიძე',
                'purpose' => 'ბინის საწყისი შენატანი',
                'bank_reference' => 'BG260115-1120',
            ],
            [
                'paid_at' => '2026-01-31',
                'amount_minor' => 942857,
                'personal_number' => '61001055555',
                'payer_name' => 'მარიამ ჯაფარიძე',
                'purpose' => 'განვადება',
                'bank_reference' => 'BG260131-0088',
            ],
            [
                'paid_at' => '2026-02-05',
                'amount_minor' => 4000000,
                'personal_number' => '01005098765',
                'payer_name' => 'გიორგი ბერიძე',
                'purpose' => 'A-302 საწყისი შენატანი',
                'bank_reference' => 'TB260205-0017',
            ],
            [
                'paid_at' => '2026-02-14',
                'amount_minor' => 5000000,
                'personal_number' => '01024011111',
                'payer_name' => 'დავით ხალვაში',
                'purpose' => 'ბინა B-302',
                'bank_reference' => 'TB260214-0301',
            ],
            [
                'paid_at' => '2026-03-01',
                'amount_minor' => 942857,
                'personal_number' => '61001055555',
                'payer_name' => 'მ. ჯაფარიძე',
                'purpose' => 'განვადება',
                'bank_reference' => 'BG260301-0410',
            ],
            [
                'paid_at' => '2026-03-05',
                'amount_minor' => 1200000,
                'personal_number' => '01005098765',
                'payer_name' => 'გიორგი ბერიძე',
                'purpose' => 'განვადება A-302',
                'bank_reference' => 'TB260305-0022',
            ],
            [
                'paid_at' => '2026-03-10',
                'amount_minor' => 2000000,
                'personal_number' => '01001012345',
                'payer_name' => 'ნინო კახიძე',
                'purpose' => 'ბინის საწყისი შენატანი',
                'bank_reference' => 'LB260310-7741',
            ],
            [
                'paid_at' => '2026-03-14',
                'amount_minor' => 3000000,
                'personal_number' => '01024011111',
                'payer_name' => 'დავით ხალვაში',
                'purpose' => 'ბინა B-302',
                'bank_reference' => 'TB260314-0309',
            ],
            [
                'paid_at' => '2026-04-02',
                'amount_minor' => 942857,
                'personal_number' => '61001055555',
                'payer_name' => 'მარიამ ჯაფარიძე',
                'purpose' => 'განვადება',
                'bank_reference' => 'BG260402-0533',
            ],
            [
                'paid_at' => '2026-04-05',
                'amount_minor' => 1200000,
                'personal_number' => '01005098765',
                'payer_name' => 'გიორგი ბერიძე',
                'purpose' => 'განვადება A-302',
                'bank_reference' => 'TB260405-0031',
            ],
            [
                'paid_at' => '2026-04-12',
                'amount_minor' => 600000,
                'personal_number' => '01001012345',
                'payer_name' => 'ნინო კახიძე',
                'purpose' => 'განვადება',
                'bank_reference' => 'LB260412-7802',
            ],
            [
                'paid_at' => '2026-04-14',
                'amount_minor' => 3000000,
                'personal_number' => '01024011111',
                'payer_name' => 'დავით ხალვაში',
                'purpose' => 'ბინა B-302, ბოლო',
                'bank_reference' => 'TB260414-0315',
            ],
            [
                'paid_at' => '2026-05-05',
                'amount_minor' => 1200000,
                'personal_number' => '01005098765',
                'payer_name' => 'გიორგი ბერიძე',
                'purpose' => 'განვადება A-302',
                'bank_reference' => 'TB260505-0040',
            ],
            [
                'paid_at' => '2026-05-10',
                'amount_minor' => 600000,
                'personal_number' => '01001012345',
                'payer_name' => 'ნინო კახიძე',
                'purpose' => 'განვადება',
                'bank_reference' => 'LB260510-7855',
            ],
            [
                'paid_at' => '2026-05-20',
                'amount_minor' => 1842500,
                'personal_number' => '01005098765',
                'payer_name' => 'გიორგი ბერიძე',
                'purpose' => 'B-202 საწყისი შენატანი',
                'bank_reference' => 'TB260520-0048',
            ],
            [
                'paid_at' => '2026-05-20',
                'amount_minor' => 1500000,
                'personal_number' => '61001055555',
                'payer_name' => 'მარიამ ჯაფარიძე',
                'purpose' => 'განვადება',
                'bank_reference' => 'BG260520-0790',
            ],
            [
                'paid_at' => '2026-06-10',
                'amount_minor' => 2200000,
                'personal_number' => '01005098765',
                'payer_name' => 'გ. ბერიძე',
                'purpose' => 'განვადება',
                'bank_reference' => 'TB260610-0057',
            ],
            [
                'paid_at' => '2026-06-20',
                'amount_minor' => 600000,
                'personal_number' => '01001012345',
                'payer_name' => 'ნინო კახიძე',
                'purpose' => 'განვადება',
                'bank_reference' => 'LB260620-7901',
            ],
            [
                'paid_at' => '2026-07-05',
                'amount_minor' => 400000,
                'personal_number' => '61001055555',
                'payer_name' => 'მარიამ ჯაფარიძე',
                'purpose' => 'განვადება, ნაწილი',
                'bank_reference' => 'BG260705-0912',
            ],
            [
                'paid_at' => '2026-07-10',
                'amount_minor' => 600000,
                'personal_number' => '01001012345',
                'payer_name' => 'ნინო კახიძე',
                'purpose' => 'განვადება',
                'bank_reference' => 'LB260710-7960',
            ],
            [
                'paid_at' => '2026-07-15',
                'amount_minor' => 1500000,
                'personal_number' => '01005098765',
                'payer_name' => 'გიორგი ბერიძე',
                'purpose' => 'განვადება',
                'bank_reference' => 'TB260715-0066',
            ],
            [
                'paid_at' => '2026-09-01',
                'amount_minor' => 1000000,
                'personal_number' => '01005098765',
                'payer_name' => 'გიორგი ბერიძე',
                'purpose' => 'განვადება',
                'bank_reference' => 'TB260901-0074',
            ],
            [
                'paid_at' => '2026-09-01',
                'amount_minor' => 3200000,
                'personal_number' => '33001077777',
                'payer_name' => 'თამარ წულუკიძე',
                'purpose' => 'A-501 საწყისი შენატანი',
                'bank_reference' => 'BG260901-1303',
            ],
            [
                'paid_at' => '2026-10-01',
                'amount_minor' => 1000000,
                'personal_number' => '33001077777',
                'payer_name' => 'თამარ წულუკიძე',
                'purpose' => 'განვადება',
                'bank_reference' => 'BG261001-1377',
            ],
            [
                'paid_at' => '2026-10-25',
                'amount_minor' => 2500000,
                'personal_number' => '33001077777',
                'payer_name' => 'თამარ წულუკიძე',
                'purpose' => 'განვადება',
                'bank_reference' => 'BG261025-1402',
            ],
            [
                'paid_at' => '2026-10-28',
                'amount_minor' => 500000,
                'personal_number' => '01024011111',
                'payer_name' => 'დავით ხალვაში',
                'purpose' => 'ბინა',
                'bank_reference' => 'TB261028-0190',
            ],
            [
                'paid_at' => '2026-10-30',
                'amount_minor' => 700000,
                'personal_number' => '99999999999',
                'payer_name' => 'ანა ონილი',
                'purpose' => 'ბინა',
                'bank_reference' => 'LB261030-8120',
            ],
            [
                'paid_at' => '2026-11-03',
                'amount_minor' => 3000000,
                'personal_number' => '33001077777',
                'payer_name' => 'თამარ წულუკიძე',
                'purpose' => 'განვადება',
                'bank_reference' => 'BG261103-1419',
            ],
        ];
    }
}
