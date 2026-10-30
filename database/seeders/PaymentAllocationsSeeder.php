<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Installment;
use App\Models\PaymentAllocation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PaymentAllocationsSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->personalNumbers() as $personalNumber) {
            DB::transaction(function () use ($personalNumber): void {
                $client = Client::query()
                    ->where('personal_number', $personalNumber)
                    ->lockForUpdate()
                    ->sole();

                $allocations = $this->allocations($client);
                $currentRun = $client->allocationRuns()
                    ->whereKey($client->current_allocation_run_id)
                    ->whereNotNull('completed_at')
                    ->first();

                if ($currentRun !== null) {
                    $currentAllocations = $currentRun->allocations()
                        ->orderBy('payment_id')
                        ->orderBy('installment_id')
                        ->get()
                        ->map(fn (PaymentAllocation $allocation): array => [
                            'payment_id' => (int) $allocation->payment_id,
                            'installment_id' => (int) $allocation->installment_id,
                            'amount_minor' => (int) $allocation->amount_minor,
                        ])
                        ->all();

                    if ($currentAllocations === $allocations) {
                        return;
                    }
                }

                $run = $client->allocationRuns()->create(['reason' => 'seed']);
                $run->allocations()->createMany($allocations);
                $run->update(['completed_at' => now()]);
                $client->update(['current_allocation_run_id' => $run->getKey()]);
            });
        }
    }

    private function allocations(Client $client): array
    {
        $installments = Installment::query()
            ->whereHas('contract', fn (Builder $query): Builder => $query->where('client_id', $client->getKey()))
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();

        $remaining = $installments
            ->map(fn (Installment $installment): int => (int) $installment->amount_minor)
            ->all();
        $index = 0;
        $allocations = [];

        foreach ($client->payments()->orderBy('paid_at')->orderBy('id')->get() as $payment) {
            $available = (int) $payment->amount_minor;

            while ($available > 0 && isset($remaining[$index])) {
                if ($remaining[$index] === 0) {
                    $index++;

                    continue;
                }

                $amount = min($available, $remaining[$index]);
                $allocations[] = [
                    'payment_id' => (int) $payment->getKey(),
                    'installment_id' => (int) $installments[$index]->getKey(),
                    'amount_minor' => $amount,
                ];
                $available -= $amount;
                $remaining[$index] -= $amount;
            }
        }

        usort($allocations, fn (array $left, array $right): int => [$left['payment_id'], $left['installment_id']] <=> [$right['payment_id'], $right['installment_id']]
        );

        return $allocations;
    }

    private function personalNumbers(): array
    {
        return [
            '01001012345',
            '01005098765',
            '61001055555',
            '01024011111',
            '33001077777',
            '01001099999',
        ];
    }
}
