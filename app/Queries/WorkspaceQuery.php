<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\ImportStatus;
use App\Enums\InstallmentStatus;
use App\Models\Apartment;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Installment;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\PaymentImport;
use App\Models\PaymentImportRow;
use App\Models\Project;
use App\Support\Money;
use App\Support\ReportingPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class WorkspaceQuery
{
    public function __construct(private readonly PaymentCoverage $coverage) {}

    public function imports(array $filters): array
    {
        $query = PaymentImport::query()->with('user:id,name');
        if (($term = $filters['q'] ?? '') !== '') {
            $query->whereLike('filename', '%'.$term.'%');
        }
        if (isset($filters['import_status'])) {
            $query->where('status', $filters['import_status']);
        }
        $filters['direction'] ??= 'desc';
        $active = (clone $query)->whereIn('status', [ImportStatus::Queued, ImportStatus::Processing])->exists();

        return $this->page($query, 'imports', $filters, fn (PaymentImport $import): array => [
            'id' => $import->id,
            'filename' => $this->link($import->filename, '/imports/'.$import->id, 'imports.view'),
            'user' => $import->user?->name ?? '—',
            'status' => $import->status->value,
            'total' => $import->total_rows,
            'imported' => $import->imported_rows,
            'duplicates' => $import->duplicate_rows,
            'rejected' => $import->rejected_rows,
            '_url' => $this->href('/imports/'.$import->id),
        ], ['id' => 'id', 'date' => 'created_at'], 'id', [], $active ? ImportStatus::Processing->value : null);
    }

    public function import(PaymentImport $import, array $filters): array
    {
        $import->refresh()->load('user:id,name');

        return $this->page($import->rows()->getQuery(), 'import', $filters, function (PaymentImportRow $row): array {
            $reference = $row->raw_data['bank_reference'] ?? null;

            return [
                'id' => $row->id,
                'row' => $row->row_number,
                'status' => $row->status->value,
                'bank_reference' => $row->payment_id !== null && is_scalar($reference)
                    ? $this->link((string) $reference, '/payments/'.$row->payment_id, 'payments.view')
                    : (is_scalar($reference) ? (string) $reference : null),
                'message' => $row->message,
                '_url' => $row->payment_id === null ? null : $this->href('/payments/'.$row->payment_id),
            ];
        }, ['id' => 'row_number'], 'row_number', [
            'filename' => $import->filename,
            'user' => $import->user?->name ?? '—',
            'status' => $import->status->value,
            'total' => $import->total_rows,
            'imported' => $import->imported_rows,
            'duplicates' => $import->duplicate_rows,
            'rejected' => $import->rejected_rows,
            'error' => $import->error_message,
        ], $import->status->value);
    }

    public function payments(array $filters): array
    {
        $period = ReportingPeriod::fromFilters($filters);
        $query = $period->apply(Payment::query()->with('client'), 'paid_at');
        if (isset($filters['client_match'])) {
            $filters['client_match'] === 'unmatched' ? $query->whereNull('client_id') : $query->whereNotNull('client_id');
        }
        if (isset($filters['client_id'])) {
            $query->where('client_id', $filters['client_id']);
        }
        if (($term = $filters['q'] ?? '') !== '') {
            $query->where(fn (Builder $query) => $query
                ->whereLike('payer_name', '%'.$term.'%')
                ->orWhereLike('personal_number', '%'.$term.'%')
                ->orWhereLike('bank_reference', '%'.$term.'%')
                ->orWhereHas('client', fn (Builder $client) => $client->whereLike('name', '%'.$term.'%')));
        }

        return $this->page($query, 'payments', $filters, fn (Payment $payment): array => [
            'id' => $payment->id,
            'date' => $payment->paid_at->toDateString(),
            'amount_GEL' => Money::formatMinor($payment->amount_minor),
            'personal_number' => $payment->personal_number,
            'payer' => $payment->payer_name,
            'client' => $payment->client === null ? '—' : $this->clientLink($payment->client),
            'client_match' => $payment->client_id === null ? 'unmatched' : 'matched',
            'bank_reference' => $this->link($payment->bank_reference, '/payments/'.$payment->id, 'payments.view', query: ReportingPeriod::fromFilters($filters)->parameters()),
            '_url' => $this->href('/payments/'.$payment->id, ReportingPeriod::fromFilters($filters)->parameters()),
        ], ['id' => 'id', 'date' => 'paid_at', 'amount' => 'amount_minor'], 'paid_at');
    }

    public function payment(Payment $payment, array $filters): array
    {
        $date = $this->date($filters);
        $payment->load('client');
        $query = $this->active($date)->where('payment_id', $payment->id)
            ->with('installment.contract.apartment.project');
        $allocatedMinor = (int) (clone $query)->sum('amount_minor');
        if (isset($filters['date_from'])) {
            $query->whereHas('installment', fn (Builder $installments) => ReportingPeriod::fromFilters($filters)->apply($installments, 'due_date'));
        }

        return $this->page($query, 'payment', $filters, fn (PaymentAllocation $allocation): array => [
            'id' => $allocation->id,
            'contract' => $this->contractLink($allocation->installment->contract, $date, $filters['date_from'] ?? null),
            'project' => $this->projectLink($allocation->installment->contract->apartment->project),
            'apartment' => $allocation->installment->contract->apartment->number,
            'due_date' => $allocation->installment->due_date->toDateString(),
            'allocated_GEL' => Money::formatMinor($allocation->amount_minor),
            '_url' => $this->href('/contracts/'.$allocation->installment->contract_id, ReportingPeriod::fromFilters($filters)->parameters()),
        ], ['id' => 'id', 'amount' => 'amount_minor'], 'id', [
            'bank_reference' => $payment->bank_reference,
            'payer' => $payment->payer_name,
            'personal_number' => $payment->personal_number,
            'client' => $payment->client === null ? '—' : $this->clientLink($payment->client),
            'date' => $payment->paid_at->toDateString(),
            'purpose' => $payment->purpose,
            'amount_GEL' => Money::formatMinor($payment->amount_minor),
            'allocated_GEL' => Money::formatMinor($allocatedMinor),
            'unallocated_GEL' => Money::formatMinor($payment->amount_minor - $allocatedMinor),
            'client_match' => $payment->client_id === null ? 'unmatched' : 'matched',
            'included_as_of' => $payment->paid_at->toDateString() <= $date ? 'yes' : 'future',
            'as_of' => $date,
        ]);
    }

    public function overdue(array $filters): array
    {
        $date = $this->date($filters);
        $remaining = 'installments.amount_minor - COALESCE(payment_coverage.paid_minor, 0)';
        $query = $this->coverage->installments($date)
            ->whereDate('installments.due_date', '<', $date)
            ->whereRaw($remaining.' > 0')
            ->with(['contract.client', 'contract.apartment.project']);
        ReportingPeriod::fromFilters($filters)->apply($query, 'installments.due_date');
        if (isset($filters['client_id'])) {
            $query->whereHas('contract', fn (Builder $contract) => $contract->where('client_id', $filters['client_id']));
        }
        if (isset($filters['project_id'])) {
            $query->whereHas('contract.apartment', fn (Builder $apartment) => $apartment->where('project_id', $filters['project_id']));
        }
        if (($term = $filters['q'] ?? '') !== '') {
            $query->where(fn (Builder $query) => $query
                ->whereHas('contract.client', fn (Builder $client) => $client
                    ->whereLike('name', '%'.$term.'%')->orWhereLike('personal_number', '%'.$term.'%'))
                ->orWhereHas('contract.apartment', fn (Builder $apartment) => $apartment
                    ->whereLike('number', '%'.$term.'%')
                    ->orWhereHas('project', fn (Builder $project) => $project->whereLike('name', '%'.$term.'%'))));
        }

        // დავალიანების ფილტრაცია, ჯამი და პაგინაცია ბაზაშივე სრულდება.
        $totalMinor = (clone $query)->sum(DB::raw($remaining));

        return $this->page($query, 'overdue', $filters, fn (Installment $installment): array => [
            'id' => $installment->id,
            'client' => $this->clientLink($installment->contract->client),
            'personal_number' => $installment->contract->client->personal_number,
            'contract' => $this->contractLink($installment->contract, $date, $filters['date_from'] ?? null),
            'project' => $this->projectLink($installment->contract->apartment->project),
            'apartment' => $installment->contract->apartment->number,
            'due_date' => $installment->due_date->toDateString(),
            'overdue_GEL' => Money::formatMinor($installment->amount_minor - (int) $installment->paid_minor),
            '_url' => $this->href('/contracts/'.$installment->contract_id, ReportingPeriod::fromFilters($filters)->parameters()),
        ], ['id' => 'installments.id', 'date' => 'installments.due_date', 'amount' => DB::raw($remaining)], 'installments.due_date', [
            'as_of' => $date,
            'overdue_GEL' => Money::formatMinor((string) $totalMinor),
        ]);
    }

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
            'contract' => $this->contractLink($contract, $this->date($filters), $filters['date_from'] ?? null),
            'client' => $this->clientLink($contract->client),
            'personal_number' => $contract->client->personal_number,
            'project' => $this->projectLink($contract->apartment->project),
            'apartment' => $contract->apartment->number,
            'date' => $contract->contract_date->toDateString(),
            'price_GEL' => Money::formatMinor($contract->price_minor),
            '_url' => $this->href('/contracts/'.$contract->id, ReportingPeriod::fromFilters($filters)->parameters()),
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

        $installments = Installment::query()->where('contract_id', $contract->id);
        if (isset($filters['date_from'])) {
            ReportingPeriod::fromFilters($filters)->apply($installments, 'due_date');
        }

        return $this->page($installments, 'contract', $filters,
            function (Installment $installment) use ($allocations, $date, $filters): array {
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
                        'url' => $this->href('/payments/'.$allocation->payment_id, ReportingPeriod::fromFilters($filters)->parameters()),
                        'description' => Money::formatMinor($allocation->amount_minor).' ₾ · '.$allocation->payment->paid_at->toDateString(),
                        'permission' => 'payments.view',
                    ])->all(),
                ];
            }, ['id' => 'id', 'date' => 'due_date', 'amount' => 'amount_minor'], 'due_date', [
                'contract' => $this->contractLink($contract, $date, $filters['date_from'] ?? null),
                'client' => $this->clientLink($contract->client),
                'personal_number' => $contract->client->personal_number,
                'phone' => $contract->client->phone,
                'date' => $contract->contract_date->toDateString(),
                'project' => $this->projectLink($contract->apartment->project),
                'apartment' => $this->link('ბინა '.$contract->apartment->number, '/apartments', 'apartments.view',
                    $contract->apartment->area.' მ² · სართული '.$contract->apartment->floor,
                    ['project_id' => $contract->apartment->project_id, 'q' => $contract->apartment->number]),
                'price_GEL' => Money::formatMinor($contract->price_minor),
                'paid_GEL' => Money::formatMinor($paidTotal),
                'remaining_GEL' => Money::formatMinor($contract->price_minor - $paidTotal),
                'as_of' => $date,
            ]);
    }

    public function apartments(array $filters): array
    {
        $query = Apartment::query()->with(['project', 'contract.apartment.project'])->withExists('contract');
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
            'project' => $this->projectLink($apartment->project),
            'city' => $apartment->project->city,
            'number' => $apartment->number,
            'floor' => $apartment->floor,
            'area' => $apartment->area,
            'price_GEL' => Money::formatMinor($apartment->price_minor),
            'status' => $apartment->status,
            'contract' => $apartment->contract === null ? null : $this->contractLink($apartment->contract, $this->date($filters), $filters['date_from'] ?? null),
            '_edit_url' => $apartment->status === 'free' && ! $apartment->contract_exists
                ? $this->href('/apartments/'.$apartment->id.'/edit') : null,
        ], ['id' => 'id', 'price' => 'price_minor'], 'id');
    }

    private function date(array $filters): string
    {
        return $filters['as_of'] ?? now()->toDateString();
    }

    private function active(string $date): Builder
    {
        return $this->coverage->allocations($date);
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

    private function contractLink(Contract $contract, string $date, ?string $from = null): array
    {
        $apartment = $contract->apartment;

        return $this->link($apartment->project->name.' — ბინა '.$apartment->number,
            '/contracts/'.$contract->id, 'contracts.view',
            $apartment->project->city.' · '.$contract->contract_date->toDateString(), (new ReportingPeriod($from, $date))->parameters());
    }

    private function clientLink(Client $client): array
    {
        return $this->link($client->name, '/clients', 'clients.view', $client->personal_number, ['q' => $client->personal_number]);
    }

    private function projectLink(Project $project): array
    {
        return $this->link($project->name, '/apartments', 'apartments.view', $project->city, ['project_id' => $project->id]);
    }

    private function link(string $label, string $path, string $permission, ?string $description = null, array $query = []): array
    {
        return ['label' => $label, 'url' => $this->href($path, $query), 'description' => $description, 'permission' => $permission];
    }
}
