<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Apartment;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Installment;
use App\Models\Payment;
use App\Models\PaymentImport;
use App\Models\Project;
use App\Support\Money;
use Brick\Math\BigDecimal;
use Brick\Math\BigInteger;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final readonly class OverviewQuery
{
    public function __construct(private PaymentCoverage $coverage) {}

    public function handle(array $filters, array $permissions): array
    {
        $date = $filters['as_of'] ?? now()->toDateString();
        $can = fn (string $permission): bool => in_array($permission, $permissions, true);
        $finance = $can('contracts.view') && $can('payments.view');

        return [
            'asOf' => $date,
            'portfolio' => [
                'clients' => $can('clients.view') ? Client::query()->count() : null,
                'contracts' => $can('contracts.view') ? Contract::query()->whereDate('contract_date', '<=', $date)->count() : null,
                'projects' => $can('apartments.view') ? Project::query()->count() : null,
                'apartments' => $can('apartments.view') ? Apartment::query()->count() : null,
                'free' => $can('apartments.view') ? Apartment::query()->where('status', 'free')->count() : null,
                'sold' => $can('apartments.view') ? Apartment::query()->where('status', 'sold')->count() : null,
            ],
            'payments' => $can('payments.view') ? $this->payments($date, $can('clients.view')) : null,
            'financial' => $finance ? $this->financial($date, $can('clients.view')) : null,
            'monthly' => $this->monthly($date, $can('payments.view'), $can('contracts.view')),
            'projects' => $can('apartments.view') ? Project::query()
                ->withCount(['apartments', 'apartments as free' => fn (Builder $q) => $q->where('status', 'free'),
                    'apartments as sold' => fn (Builder $q) => $q->where('status', 'sold')])
                ->orderByDesc('apartments_count')->orderBy('id')->limit(10)->get()
                ->map(fn (Project $p): array => ['name' => $p->name, 'city' => $p->city,
                    'free' => $p->free, 'sold' => $p->sold, 'url' => '/apartments?project_id='.$p->id])->all() : [],
            'imports' => $can('imports.view') ? $this->imports() : null,
        ];
    }

    private function payments(string $date, bool $showClients): array
    {
        $query = Payment::query()->whereDate('paid_at', '<=', $date);
        $received = (clone $query)->sum('amount_minor');
        $allocated = $this->coverage->allocations($date)->sum('amount_minor');
        $unmatched = (clone $query)->whereNull('client_id');

        return [
            'count' => (clone $query)->count(),
            'received_GEL' => Money::formatMinor($received),
            'allocated_GEL' => Money::formatMinor($allocated),
            'unallocated_GEL' => $this->difference($received, $allocated),
            'unmatched_count' => (clone $unmatched)->count(),
            'unmatched_GEL' => Money::formatMinor((clone $unmatched)->sum('amount_minor')),
            'matched_count' => (clone $query)->whereNotNull('client_id')->count(),
            'recent' => (clone $query)->with('client:id,name')->orderByDesc('paid_at')->orderByDesc('id')->limit(5)->get()
                ->map(fn (Payment $p): array => ['reference' => $p->bank_reference,
                    'payer' => $showClients ? ($p->client?->name ?? $p->payer_name) : $p->payer_name,
                    'date' => $p->paid_at->toDateString(), 'amount_GEL' => Money::formatMinor($p->amount_minor),
                    'matched' => $p->client_id !== null, 'url' => '/payments/'.$p->id.'?as_of='.$date])->all(),
        ];
    }

    private function financial(string $date, bool $showClients): array
    {
        $installments = $this->coverage->installments($date)
            ->whereHas('contract', fn (Builder $q) => $q->whereDate('contract_date', '<=', $date));
        $remaining = 'installments.amount_minor - COALESCE(payment_coverage.paid_minor, 0)';
        $overdue = (clone $installments)->whereDate('due_date', '<', $date)->whereRaw($remaining.' > 0');
        $price = Contract::query()->whereDate('contract_date', '<=', $date)->sum('price_minor');
        $paid = $this->coverage->allocations($date)->whereHas('installment.contract', fn (Builder $q) => $q
            ->whereDate('contract_date', '<=', $date))->sum('amount_minor');
        $overdueAmount = (clone $overdue)->toBase()->sum(DB::raw($remaining));

        $states = (clone $installments)->reorder()->select([])->selectRaw("CASE
            WHEN COALESCE(payment_coverage.paid_minor, 0) >= installments.amount_minor THEN 'paid'
            WHEN installments.due_date < ? THEN 'overdue'
            WHEN COALESCE(payment_coverage.paid_minor, 0) > 0 THEN 'partial'
            ELSE 'pending' END AS coverage_status, COUNT(*) AS total", [$date])
            ->groupBy('coverage_status')->pluck('total', 'coverage_status')->all();

        $debtors = $showClients ? (clone $overdue)->join('contracts', 'contracts.id', '=', 'installments.contract_id')
            ->reorder()->select('contracts.client_id')->selectRaw('SUM('.$remaining.') AS overdue_minor')
            ->groupBy('contracts.client_id')->orderByDesc('overdue_minor')->orderBy('contracts.client_id')->limit(5)->toBase()->get() : collect();
        $clients = Client::query()->whereIn('id', $debtors->pluck('client_id'))->get()->keyBy('id');

        return [
            'contract_value_GEL' => Money::formatMinor($price),
            'paid_GEL' => Money::formatMinor($paid),
            'paid_percent' => BigInteger::of($price)->isZero() ? '0.0' : (string) BigDecimal::of($paid)
                ->multipliedBy(100)->dividedBy($price, 1, RoundingMode::HalfUp),
            'remaining_GEL' => $this->difference($price, $paid),
            'overdue_GEL' => Money::formatMinor($overdueAmount),
            'overdue_installments' => (clone $overdue)->count(),
            'overdue_clients' => $showClients ? (clone $overdue)->join('contracts', 'contracts.id', '=', 'installments.contract_id')
                ->distinct()->count('contracts.client_id') : null,
            'states' => collect(['paid', 'overdue', 'partial', 'pending'])->map(fn (string $status): array => [
                'status' => $status, 'count' => (int) ($states[$status] ?? 0),
            ])->all(),
            'debtors' => $debtors->map(fn (object $row): array => [
                'name' => $clients[$row->client_id]->name,
                'amount_GEL' => Money::formatMinor($row->overdue_minor),
                'url' => '/overdue?client_id='.$row->client_id.'&as_of='.$date,
            ])->all(),
            'upcoming' => (clone $installments)->whereDate('due_date', '>=', $date)
                ->whereDate('due_date', '<=', CarbonImmutable::parse($date)->addDays(30)->toDateString())
                ->whereRaw($remaining.' > 0')->with('contract.apartment.project')->orderBy('due_date')->orderBy('installments.id')->limit(5)->get()
                ->map(fn (Installment $i): array => [
                    'name' => $i->contract->apartment->project->name.' — ბინა '.$i->contract->apartment->number,
                    'date' => $i->due_date->toDateString(),
                    'amount_GEL' => $this->difference($i->amount_minor, $i->paid_minor),
                    'url' => '/contracts/'.$i->contract_id.'?as_of='.$date,
                ])->all(),
        ];
    }

    private function monthly(string $date, bool $payments, bool $contracts): array
    {
        if (! $payments && ! $contracts) {
            return [];
        }
        $end = CarbonImmutable::parse($date);
        $start = $end->startOfMonth()->subMonths(11);
        $aggregate = fn (Builder $query, string $field): array => $query
            ->whereDate($field, '>=', $start->toDateString())->whereDate($field, '<=', $date)
            ->selectRaw('SUBSTR(CAST('.$field.' AS VARCHAR), 1, 7) AS month, SUM(amount_minor) AS amount')
            ->groupByRaw('SUBSTR(CAST('.$field.' AS VARCHAR), 1, 7)')->pluck('amount', 'month')->all();
        $received = $payments ? $aggregate(Payment::query(), 'paid_at') : [];
        $scheduled = $contracts ? $aggregate(Installment::query()->whereHas('contract', fn (Builder $q) => $q
            ->whereDate('contract_date', '<=', $date)), 'due_date') : [];

        return collect(range(0, 11))->map(function (int $offset) use ($start, $received, $scheduled, $payments, $contracts): array {
            $month = $start->addMonths($offset)->format('Y-m');

            return ['month' => $month, 'received_GEL' => $payments ? Money::formatMinor($received[$month] ?? 0) : null,
                'scheduled_GEL' => $contracts ? Money::formatMinor($scheduled[$month] ?? 0) : null];
        })->all();
    }

    private function imports(): array
    {
        $totals = PaymentImport::query()->selectRaw('COUNT(*) AS files, COALESCE(SUM(imported_rows), 0) AS imported,
            COALESCE(SUM(duplicate_rows), 0) AS duplicates, COALESCE(SUM(rejected_rows), 0) AS rejected')->toBase()->first();

        return [
            'files' => (int) $totals->files, 'imported' => (int) $totals->imported,
            'duplicates' => (int) $totals->duplicates, 'rejected' => (int) $totals->rejected,
            'statuses' => PaymentImport::query()->select('status')->selectRaw('COUNT(*) AS total')->groupBy('status')->pluck('total', 'status')->all(),
            'recent' => PaymentImport::query()->orderByDesc('id')->limit(4)->get()->map(fn (PaymentImport $i): array => [
                'filename' => $i->filename, 'status' => $i->status->value,
                'imported' => $i->imported_rows, 'rejected' => $i->rejected_rows, 'url' => '/imports/'.$i->id,
            ])->all(),
        ];
    }

    private function difference(int|string $amount, int|string $paid): string
    {
        return Money::formatMinor((string) BigInteger::of($amount)->minus($paid));
    }
}
