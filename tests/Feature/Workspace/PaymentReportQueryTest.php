<?php

declare(strict_types=1);

use App\Actions\Payments\RecordPayment;
use App\Data\PaymentData;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Installment;
use App\Models\Payment;
use App\Queries\WorkspaceQuery;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function reportingSchedule(array $rows): array
{
    $client = Client::factory()->create();
    $contract = Contract::factory()->for($client)->create(['price_minor' => array_sum(array_column($rows, 1))]);
    foreach ($rows as $index => [$date, $amount]) {
        Installment::factory()->for($contract)->create([
            'sequence' => $index + 1, 'due_date' => $date, 'amount_minor' => $amount,
        ]);
    }

    return [$client, $contract];
}

function reportingPayment(Client $client, string $amount, string $date, string $reference): Payment
{
    return app(RecordPayment::class)->handle(PaymentData::fromArray([
        'paid_at' => $date, 'amount' => $amount, 'personal_number' => $client->personal_number,
        'payer_name' => $client->name, 'bank_reference' => $reference,
    ]))->payment;
}

it('searches payments by payer reference personal number client and zero', function (string $term): void {
    $client = Client::factory()->create(['name' => 'Client Match', 'personal_number' => '01005098765']);
    $target = Payment::factory()->for($client)->create(['payer_name' => 'Payer Smith', 'bank_reference' => 'BANK-0']);
    Payment::factory()->create(['payer_name' => 'Other', 'personal_number' => '11111111111', 'bank_reference' => 'OTHER']);

    $result = app(WorkspaceQuery::class)->payments(['q' => $term]);

    expect($result['total'])->toBe(1)->and($result['rows'][0]['id'])->toBe($target->id);
})->with(['payer' => ['SMITH'], 'reference' => ['bank-0'], 'personal' => ['0100509'], 'client' => ['client match'], 'zero' => ['0']]);

it('does not let payment search bypass the client filter', function (): void {
    $first = Client::factory()->create();
    Payment::factory()->for($first)->create(['payer_name' => 'First', 'bank_reference' => 'FIRST']);
    Payment::factory()->create(['payer_name' => 'Lookup Target', 'bank_reference' => 'TARGET']);

    expect(app(WorkspaceQuery::class)->payments(['client_id' => $first->id, 'q' => 'target'])['total'])->toBe(0);
});

it('reports unmatched money without inventing allocations', function (): void {
    $payment = Payment::factory()->unmatched()->create(['amount_minor' => 12345, 'paid_at' => '2026-02-01']);

    $result = app(WorkspaceQuery::class)->payment($payment, ['as_of' => '2026-02-15']);

    expect($result['total'])->toBe(0)
        ->and($result['summary']['client_match'])->toBe('unmatched')
        ->and($result['summary']['allocated_GEL'])->toBe('0.00')
        ->and($result['summary']['unallocated_GEL'])->toBe('123.45');
});

it('keeps surplus and future payments visible while applying the reporting cutoff', function (): void {
    [$client] = reportingSchedule([['2026-01-01', 10000]]);
    $payment = reportingPayment($client, '150.00', '2026-02-01', 'SURPLUS');
    $query = app(WorkspaceQuery::class);

    $earlier = $query->payment($payment, ['as_of' => '2026-01-15']);
    $later = $query->payment($payment, ['as_of' => '2026-02-15']);

    expect($earlier['rows'])->toBe([])
        ->and($earlier['summary']['included_as_of'])->toBe('future')
        ->and($earlier['summary']['unallocated_GEL'])->toBe('150.00')
        ->and($later['summary']['allocated_GEL'])->toBe('100.00')
        ->and($later['summary']['unallocated_GEL'])->toBe('50.00');
});

it('shows only current allocations for the selected payment and preserves cross-links', function (): void {
    [$client, $contract] = reportingSchedule([['2026-01-01', 6000], ['2026-02-01', 9000]]);
    $payment = reportingPayment($client, '110.00', '2026-02-01', 'FIRST');
    reportingPayment($client, '20.00', '2026-02-02', 'SECOND');

    $result = app(WorkspaceQuery::class)->payment($payment, ['as_of' => '2026-02-15']);

    expect($result['total'])->toBe(2)
        ->and($result['summary']['allocated_GEL'])->toBe('110.00')
        ->and($result['summary']['unallocated_GEL'])->toBe('0.00')
        ->and(array_column($result['rows'], 'allocated_GEL'))->toBe(['60.00', '50.00'])
        ->and($result['rows'][0]['_url'])->toEndWith('/contracts/'.$contract->id.'?as_of=2026-02-15');
});

it('ignores future payments and installments due exactly on the reporting day', function (): void {
    [$client] = reportingSchedule([['2026-01-01', 6000], ['2026-02-15', 9000]]);
    reportingPayment($client, '150.00', '2026-03-01', 'FUTURE');
    $query = app(WorkspaceQuery::class);

    $earlier = $query->overdue(['as_of' => '2026-02-15']);
    $later = $query->overdue(['as_of' => '2026-03-02']);

    expect($earlier['total'])->toBe(1)
        ->and($earlier['rows'][0]['overdue_GEL'])->toBe('60.00')
        ->and($earlier['summary']['overdue_GEL'])->toBe('60.00')
        ->and($later['total'])->toBe(0)
        ->and($later['summary']['overdue_GEL'])->toBe('0.00');
});

it('does not double count old allocation runs in the overdue aggregate', function (): void {
    [$client] = reportingSchedule([['2026-01-01', 6000], ['2026-02-01', 9000]]);
    reportingPayment($client, '40.00', '2026-01-02', 'OLDER');
    reportingPayment($client, '50.00', '2026-02-02', 'NEWER');
    $query = app(WorkspaceQuery::class);

    $earlier = $query->overdue(['as_of' => '2026-01-15']);
    $later = $query->overdue(['as_of' => '2026-02-15']);

    expect($earlier['total'])->toBe(1)
        ->and($earlier['summary']['overdue_GEL'])->toBe('20.00')
        ->and($later['total'])->toBe(1)
        ->and($later['summary']['overdue_GEL'])->toBe('60.00');
});

it('computes the overdue total across all matching rows before pagination', function (): void {
    $contract = Contract::factory()->create(['price_minor' => 2100]);
    Installment::factory()->count(21)->for($contract)
        ->sequence(fn (Sequence $sequence): array => ['sequence' => $sequence->index + 1])
        ->create(['due_date' => '2026-01-01', 'amount_minor' => 100]);

    $result = app(WorkspaceQuery::class)->overdue(['as_of' => '2026-02-15', 'page' => 2]);

    expect($result['total'])->toBe(21)
        ->and($result['rows'])->toHaveCount(1)
        ->and($result['rows'][0]['overdue_GEL'])->toBe('1.00')
        ->and($result['summary']['overdue_GEL'])->toBe('21.00');
});

it('keeps overdue search inside the selected client and project', function (): void {
    [$first, $firstContract] = reportingSchedule([['2026-01-01', 10000]]);
    [$other] = reportingSchedule([['2026-01-01', 5000]]);
    $other->update(['name' => 'Lookup Target']);
    $query = app(WorkspaceQuery::class);

    $filtered = $query->overdue(['as_of' => '2026-02-15', 'project_id' => $firstContract->apartment->project_id]);
    $empty = $query->overdue(['as_of' => '2026-02-15', 'client_id' => $first->id, 'q' => 'lookup']);

    expect($filtered['total'])->toBe(1)
        ->and($filtered['summary']['overdue_GEL'])->toBe('100.00')
        ->and($empty['total'])->toBe(0)
        ->and($empty['summary']['overdue_GEL'])->toBe('0.00');
});

it('returns an empty overdue report with a zero balance', function (): void {
    $result = app(WorkspaceQuery::class)->overdue(['as_of' => '2026-02-15']);

    expect($result['total'])->toBe(0)
        ->and($result['rows'])->toBe([])
        ->and($result['summary']['overdue_GEL'])->toBe('0.00');
});
