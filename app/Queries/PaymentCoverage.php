<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Installment;
use App\Models\PaymentAllocation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;

final class PaymentCoverage
{
    public function allocations(string $date): Builder
    {
        return PaymentAllocation::query()
            ->whereHas('allocationRun', fn (Builder $run) => $run->whereHas('client', fn (Builder $client) => $client
                ->whereColumn('clients.current_allocation_run_id', 'allocation_runs.id')))
            ->whereHas('payment', fn (Builder $payment) => $payment->whereDate('paid_at', '<=', $date));
    }

    public function installments(string $date): Builder
    {
        $paid = $this->allocations($date)->select('installment_id')
            ->selectRaw('SUM(amount_minor) AS paid_minor')->groupBy('installment_id');

        return Installment::query()
            ->leftJoinSub($paid, 'payment_coverage', fn (JoinClause $join) => $join
                ->on('installments.id', '=', 'payment_coverage.installment_id'))
            ->select('installments.*')->selectRaw('COALESCE(payment_coverage.paid_minor, 0) AS paid_minor');
    }
}
