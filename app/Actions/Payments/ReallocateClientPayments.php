<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Contracts\AllocationStrategy;
use App\Models\AllocationRun;
use App\Models\Client;
use App\Models\Installment;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

final readonly class ReallocateClientPayments
{
    public function __construct(private AllocationStrategy $strategy) {}

    public function handle(
        int $clientId,
        ?int $userId = null,
        string $reason = 'manual',
    ): AllocationRun {
        return DB::transaction(function () use ($clientId, $userId, $reason): AllocationRun {
            $client = Client::query()->lockForUpdate()->findOrFail($clientId);

            $payments = $client->payments()->get(['id', 'paid_at', 'amount_minor'])
                ->map(fn (Payment $payment): array => [
                    'id' => $payment->id,
                    'paid_at' => $payment->paid_at->toDateString(),
                    'amount_minor' => $payment->amount_minor,
                ])->all();

            $installments = Installment::query()
                ->whereIn('contract_id', $client->contracts()->select('id'))
                ->get(['id', 'due_date', 'amount_minor'])
                ->map(fn (Installment $installment): array => [
                    'id' => $installment->id,
                    'due_date' => $installment->due_date->toDateString(),
                    'amount_minor' => $installment->amount_minor,
                ])->all();

            $run = $client->allocationRuns()->create([
                'user_id' => $userId,
                'reason' => $reason,
            ]);

            $run->allocations()->createMany($this->strategy->allocate($payments, $installments));
            $run->update(['completed_at' => now()]);
            $client->update(['current_allocation_run_id' => $run->id]);

            return $run;
        }, 3);
    }
}
