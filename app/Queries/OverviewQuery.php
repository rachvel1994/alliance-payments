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
use App\Support\ReportingPeriod;
use Brick\Math\BigDecimal;
use Brick\Math\BigInteger;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final readonly class OverviewQuery
{
    private const REMAINING_AMOUNT = 'installments.amount_minor - COALESCE(payment_coverage.paid_minor, 0)';

    public function __construct(private PaymentCoverage $coverage) {}

    public function handle(array $filters, array $permissions): array
    {
        $period = ReportingPeriod::fromFilters($filters);
        $date = $period->to;
        $showClients = in_array('clients.view', $permissions, true);
        $showContracts = in_array('contracts.view', $permissions, true);
        $showPayments = in_array('payments.view', $permissions, true);
        $showApartments = in_array('apartments.view', $permissions, true);
        $showImports = in_array('imports.view', $permissions, true);

        return [
            'asOf' => $date,
            'dateFrom' => $period->from,
            'portfolio' => [
                'clients' => $showClients ? Client::query()->count() : null,
                'contracts' => $showContracts ? Contract::query()->whereDate('contract_date', '<=', $date)->count() : null,
                'projects' => $showApartments ? Project::query()->count() : null,
                'apartments' => $showApartments ? Apartment::query()->count() : null,
                'free' => $showApartments ? Apartment::query()->where('status', 'free')->count() : null,
                'sold' => $showApartments ? Apartment::query()->where('status', 'sold')->count() : null,
            ],
            'payments' => $showPayments ? $this->payments($period, $showClients) : null,
            'financial' => $showContracts && $showPayments ? $this->financial($period, $showClients) : null,
            'monthly' => $this->monthly($period, $showPayments, $showContracts),
            'projects' => $showApartments ? $this->projects() : [],
            'imports' => $showImports ? $this->imports() : null,
        ];
    }

    private function projects(): array
    {
        return Project::query()
            ->withCount([
                'apartments',
                'apartments as free' => fn (Builder $apartments) => $apartments->where('status', 'free'),
                'apartments as sold' => fn (Builder $apartments) => $apartments->where('status', 'sold'),
            ])
            ->orderByDesc('apartments_count')->orderBy('id')->limit(10)->get()
            ->map(fn (Project $project): array => [
                'name' => $project->name,
                'city' => $project->city,
                'free' => $project->free,
                'sold' => $project->sold,
                'url' => '/apartments?project_id='.$project->id,
            ])->all();
    }

    private function payments(ReportingPeriod $period, bool $showClients): array
    {
        $date = $period->to;
        $payments = $period->apply(Payment::query(), 'paid_at');
        $received = (clone $payments)->sum('amount_minor');
        $allocated = $this->coverage->allocations($date)
            ->whereHas('payment', fn (Builder $payments) => $period->apply($payments, 'paid_at'))->sum('amount_minor');
        $unmatched = (clone $payments)->whereNull('client_id');
        $recent = (clone $payments)->with('client:id,name')
            ->orderByDesc('paid_at')->orderByDesc('id')->limit(5)->get();

        return [
            'count' => (clone $payments)->count(),
            'received_GEL' => Money::formatMinor($received),
            'allocated_GEL' => Money::formatMinor($allocated),
            'unallocated_GEL' => $this->difference($received, $allocated),
            'unmatched_count' => (clone $unmatched)->count(),
            'unmatched_GEL' => Money::formatMinor((clone $unmatched)->sum('amount_minor')),
            'matched_count' => (clone $payments)->whereNotNull('client_id')->count(),
            'recent' => $recent->map(fn (Payment $payment): array => [
                'reference' => $payment->bank_reference,
                'payer' => $showClients ? ($payment->client?->name ?? $payment->payer_name) : $payment->payer_name,
                'date' => $payment->paid_at->toDateString(),
                'amount_GEL' => Money::formatMinor($payment->amount_minor),
                'matched' => $payment->client_id !== null,
                'url' => '/payments/'.$payment->id.'?'.http_build_query($period->parameters()),
            ])->all(),
        ];
    }

    private function financial(ReportingPeriod $period, bool $showClients): array
    {
        $date = $period->to;
        $installments = $this->coverage->installments($date)
            ->whereHas('contract', fn (Builder $contracts) => $contracts->whereDate('contract_date', '<=', $date));
        $selectedInstallments = $period->from === null ? clone $installments : $period->apply(clone $installments, 'due_date');
        $overdue = (clone $selectedInstallments)->whereDate('due_date', '<', $date)->whereRaw(self::REMAINING_AMOUNT.' > 0');
        $price = Contract::query()->whereDate('contract_date', '<=', $date)->sum('price_minor');
        $paid = $this->coverage->allocations($date)
            ->whereHas('installment.contract', fn (Builder $contracts) => $contracts->whereDate('contract_date', '<=', $date))
            ->sum('amount_minor');
        $overdueAmount = (clone $overdue)->toBase()->sum(DB::raw(self::REMAINING_AMOUNT));
        $paidPercent = BigInteger::of($price)->isZero()
            ? '0.0'
            : (string) BigDecimal::of($paid)->multipliedBy(100)->dividedBy($price, 1, RoundingMode::HalfUp);

        return [
            'contract_value_GEL' => Money::formatMinor($price),
            'paid_GEL' => Money::formatMinor($paid),
            'paid_percent' => $paidPercent,
            'remaining_GEL' => $this->difference($price, $paid),
            'overdue_GEL' => Money::formatMinor($overdueAmount),
            'overdue_installments' => (clone $overdue)->count(),
            'overdue_clients' => $showClients ? (clone $overdue)
                ->join('contracts', 'contracts.id', '=', 'installments.contract_id')
                ->distinct()->count('contracts.client_id') : null,
            'states' => $this->installmentStates(clone $selectedInstallments, $date),
            'debtors' => $showClients ? $this->debtors(clone $overdue, $period) : [],
            'upcoming' => $this->upcomingInstallments(clone $installments, $date),
        ];
    }

    private function installmentStates(Builder $installments, string $date): array
    {
        $states = $installments->reorder()->select([])
            ->selectRaw(<<<'SQL'
                CASE
                    WHEN COALESCE(payment_coverage.paid_minor, 0) >= installments.amount_minor THEN 'paid'
                    WHEN installments.due_date < ? THEN 'overdue'
                    WHEN COALESCE(payment_coverage.paid_minor, 0) > 0 THEN 'partial'
                    ELSE 'pending'
                END AS coverage_status, COUNT(*) AS total
                SQL, [$date])
            ->groupBy('coverage_status')->pluck('total', 'coverage_status')->all();

        return collect(['paid', 'overdue', 'partial', 'pending'])
            ->map(fn (string $status): array => [
                'status' => $status,
                'count' => (int) ($states[$status] ?? 0),
            ])->all();
    }

    private function debtors(Builder $overdue, ReportingPeriod $period): array
    {
        $debtors = $overdue->join('contracts', 'contracts.id', '=', 'installments.contract_id')
            ->reorder()->select('contracts.client_id')
            ->selectRaw('SUM('.self::REMAINING_AMOUNT.') AS overdue_minor')
            ->groupBy('contracts.client_id')
            ->orderByDesc('overdue_minor')->orderBy('contracts.client_id')->limit(5)->toBase()->get();
        $clients = Client::query()->whereIn('id', $debtors->pluck('client_id'))->get()->keyBy('id');

        return $debtors->map(fn (object $debtor): array => [
            'name' => $clients[$debtor->client_id]->name,
            'amount_GEL' => Money::formatMinor($debtor->overdue_minor),
            'url' => '/overdue?'.http_build_query(['client_id' => $debtor->client_id, ...$period->parameters()]),
        ])->all();
    }

    private function upcomingInstallments(Builder $installments, string $date): array
    {
        $lastDate = CarbonImmutable::parse($date)->addDays(30)->toDateString();

        return $installments->whereDate('due_date', '>=', $date)->whereDate('due_date', '<=', $lastDate)
            ->whereRaw(self::REMAINING_AMOUNT.' > 0')
            ->with('contract.apartment.project')
            ->orderBy('due_date')->orderBy('installments.id')->limit(5)->get()
            ->map(function (Installment $installment) use ($date): array {
                $apartment = $installment->contract->apartment;

                return [
                    'name' => $apartment->project->name.' — ბინა '.$apartment->number,
                    'date' => $installment->due_date->toDateString(),
                    'amount_GEL' => $this->difference($installment->amount_minor, $installment->paid_minor),
                    'url' => '/contracts/'.$installment->contract_id.'?as_of='.$date,
                ];
            })->all();
    }

    private function monthly(ReportingPeriod $period, bool $showPayments, bool $showContracts): array
    {
        $date = $period->to;
        if (! $showPayments && ! $showContracts) {
            return [];
        }

        $start = $period->from === null
            ? CarbonImmutable::parse($date)->startOfMonth()->subMonths(11)
            : CarbonImmutable::parse($period->from)->startOfMonth();
        $firstDate = $period->from ?? $start->toDateString();
        $received = $showPayments
            ? $this->monthlyTotals(Payment::query(), 'paid_at', $firstDate, $date)
            : [];
        $installments = Installment::query()
            ->whereHas('contract', fn (Builder $contracts) => $contracts->whereDate('contract_date', '<=', $date));
        $scheduled = $showContracts
            ? $this->monthlyTotals($installments, 'due_date', $firstDate, $date)
            : [];

        $months = (int) $start->diffInMonths(CarbonImmutable::parse($date)->startOfMonth());

        return collect(range(0, $months))->map(function (int $offset) use ($start, $received, $scheduled, $showPayments, $showContracts): array {
            $month = $start->addMonths($offset)->format('Y-m');

            return [
                'month' => $month,
                'received_GEL' => $showPayments ? Money::formatMinor($received[$month] ?? 0) : null,
                'scheduled_GEL' => $showContracts ? Money::formatMinor($scheduled[$month] ?? 0) : null,
            ];
        })->all();
    }

    private function monthlyTotals(Builder $query, string $dateColumn, string $start, string $end): array
    {
        $month = 'SUBSTR(CAST('.$dateColumn.' AS VARCHAR), 1, 7)';

        return $query->whereDate($dateColumn, '>=', $start)->whereDate($dateColumn, '<=', $end)
            ->selectRaw($month.' AS month, SUM(amount_minor) AS amount')
            ->groupByRaw($month)->pluck('amount', 'month')->all();
    }

    private function imports(): array
    {
        $totals = PaymentImport::query()->selectRaw(<<<'SQL'
            COUNT(*) AS files,
            COALESCE(SUM(imported_rows), 0) AS imported,
            COALESCE(SUM(duplicate_rows), 0) AS duplicates,
            COALESCE(SUM(rejected_rows), 0) AS rejected
            SQL)->toBase()->first();

        return [
            'files' => (int) $totals->files,
            'imported' => (int) $totals->imported,
            'duplicates' => (int) $totals->duplicates,
            'rejected' => (int) $totals->rejected,
            'statuses' => PaymentImport::query()->select('status')->selectRaw('COUNT(*) AS total')
                ->groupBy('status')->pluck('total', 'status')->all(),
            'recent' => PaymentImport::query()->orderByDesc('id')->limit(4)->get()
                ->map(fn (PaymentImport $import): array => [
                    'filename' => $import->filename,
                    'status' => $import->status->value,
                    'imported' => $import->imported_rows,
                    'rejected' => $import->rejected_rows,
                    'url' => '/imports/'.$import->id,
                ])->all(),
        ];
    }

    private function difference(int|string $amount, int|string $paid): string
    {
        return Money::formatMinor((string) BigInteger::of($amount)->minus($paid));
    }
}
