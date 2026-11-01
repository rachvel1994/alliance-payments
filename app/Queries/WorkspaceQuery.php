<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\InstallmentStatus;
use App\Models\Apartment;
use App\Models\Contract;
use App\Models\Installment;
use App\Models\PaymentAllocation;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final class WorkspaceQuery
{
    public function contracts(array $filters): array
    {
        $query = Contract::query()->with(['client', 'apartment.project']);
        if (isset($filters['client_id'])) {
            $query->where('client_id', $filters['client_id']);
        }
        if (isset($filters['project_id'])) {
            $query->whereHas('apartment', fn (Builder $apartment) => $apartment->where('project_id', $filters['project_id']));
        }
        if (($term = $filters['q'] ?? '') !== '') {
            $query->where(fn (Builder $query) => $query
                ->whereHas('client', fn (Builder $client) => $client
                    ->whereLike('name', '%'.$term.'%')->orWhereLike('personal_number', '%'.$term.'%'))
                ->orWhereHas('apartment', fn (Builder $apartment) => $apartment
                    ->whereLike('number', '%'.$term.'%')
                    ->orWhereHas('project', fn (Builder $project) => $project->whereLike('name', '%'.$term.'%'))));
        }

        return $this->page($query, 'contracts', $filters, fn (Contract $contract): array => [
            'id' => $contract->id,
            'client' => $contract->client->name,
            'personal_number' => $contract->client->personal_number,
            'project' => $contract->apartment->project->name,
            'apartment' => $contract->apartment->number,
            'date' => $contract->contract_date->toDateString(),
            'price_GEL' => Money::formatMinor($contract->price_minor),
            '_url' => $this->href('/contracts/'.$contract->id, ['as_of' => $this->date($filters)]),
        ], ['id' => 'id', 'date' => 'contract_date', 'price' => 'price_minor'], 'id');
    }

    public function contract(Contract $contract, array $filters): array
    {
        $date = $this->date($filters);
        $contract->load(['client', 'apartment.project']);
        $allocations = $this->active($date)->with('payment')
            ->whereHas('installment', fn (Builder $query) => $query->where('contract_id', $contract->id))
            ->orderBy('id')->get()->groupBy('installment_id');
        $paidTotal = (int) $allocations->flatten(1)->sum('amount_minor');

        return $this->page(Installment::query()->where('contract_id', $contract->id), 'contract', $filters,
            function (Installment $installment) use ($allocations, $date): array {
                $paid = $allocations->get($installment->id, collect());
                $paidMinor = (int) $paid->sum('amount_minor');
                $remaining = $installment->amount_minor - $paidMinor;
                $dueDate = $installment->due_date->toDateString();
                $status = match (true) {
                    $remaining === 0 => InstallmentStatus::Paid,
                    $dueDate < $date => InstallmentStatus::Overdue,
                    $paidMinor > 0 => InstallmentStatus::Partial,
                    default => InstallmentStatus::Pending,
                };

                return [
                    'id' => $installment->id,
                    'sequence' => $installment->sequence,
                    'due_date' => $dueDate,
                    'amount_GEL' => Money::formatMinor($installment->amount_minor),
                    'paid_GEL' => Money::formatMinor($paidMinor),
                    'remaining_GEL' => Money::formatMinor($remaining),
                    'status' => $status->value,
                    '_links' => $paid->map(fn (PaymentAllocation $allocation): array => [
                        'label' => $allocation->payment->bank_reference,
                        'url' => $this->href('/payments/'.$allocation->payment_id, ['as_of' => $date]),
                    ])->all(),
                ];
            }, ['id' => 'id', 'date' => 'due_date', 'amount' => 'amount_minor'], 'due_date', [
                'contract' => $contract->id,
                'client' => $contract->client->name,
                'project' => $contract->apartment->project->name,
                'apartment' => $contract->apartment->number,
                'price_GEL' => Money::formatMinor($contract->price_minor),
                'paid_GEL' => Money::formatMinor($paidTotal),
                'remaining_GEL' => Money::formatMinor($contract->price_minor - $paidTotal),
                'as_of' => $date,
            ]);
    }

    public function apartments(array $filters): array
    {
        $query = Apartment::query()->with('project');
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (isset($filters['project_id'])) {
            $query->where('project_id', $filters['project_id']);
        }
        if (($term = $filters['q'] ?? '') !== '') {
            $query->where(fn (Builder $query) => $query
                ->whereLike('number', '%'.$term.'%')
                ->orWhereHas('project', fn (Builder $project) => $project
                    ->whereLike('name', '%'.$term.'%')->orWhereLike('city', '%'.$term.'%')));
        }

        return $this->page($query, 'apartments', $filters, fn (Apartment $apartment): array => [
            'id' => $apartment->id,
            'project' => $apartment->project->name,
            'city' => $apartment->project->city,
            'number' => $apartment->number,
            'floor' => $apartment->floor,
            'area' => $apartment->area,
            'price_GEL' => Money::formatMinor($apartment->price_minor),
            'status' => $apartment->status,
        ], ['id' => 'id', 'price' => 'price_minor'], 'id');
    }

    private function date(array $filters): string
    {
        return $filters['as_of'] ?? now()->toDateString();
    }

    private function active(string $date): Builder
    {
        return PaymentAllocation::query()
            ->whereHas('allocationRun', fn (Builder $run) => $run->whereHas('client', fn (Builder $client) => $client
                ->whereColumn('clients.current_allocation_run_id', 'allocation_runs.id')))
            ->whereHas('payment', fn (Builder $payment) => $payment->whereDate('paid_at', '<=', $date));
    }

    private function page(
        Builder $query,
        string $section,
        array $filters,
        callable $map,
        array $sorts,
        string $default,
        array $summary = [],
        ?string $importStatus = null,
    ): array {
        $sort = $sorts[$filters['sort'] ?? ''] ?? $default;
        $paginator = $query->orderBy($sort, ($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc')
            ->orderBy($query->getModel()->qualifyColumn('id'))
            ->paginate(20, ['*'], 'page', (int) ($filters['page'] ?? 1))->appends($filters);
        $paginator->setCollection($paginator->getCollection()->map($map));

        return $this->props($section, $filters, $paginator, $summary, $importStatus);
    }

    private function props(
        string $section,
        array $filters,
        LengthAwarePaginator $paginator,
        array $summary = [],
        ?string $importStatus = null,
    ): array {
        return [
            'section' => $section,
            'filters' => [...$filters, 'as_of' => $this->date($filters)],
            'rows' => $paginator->items(),
            'links' => $paginator->linkCollection()->all(),
            'total' => $paginator->total(),
            'summary' => $summary,
            'importStatus' => $importStatus,
        ];
    }

    private function href(string $path, array $query = []): string
    {
        return url($path).($query === [] ? '' : '?'.http_build_query($query));
    }
}
