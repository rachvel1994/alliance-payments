<?php

declare(strict_types=1);

use App\Actions\Payments\RecordPayment;
use App\Data\PaymentData;
use App\Enums\InstallmentStatus;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Installment;
use App\Queries\WorkspaceQuery;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function contractQueryPayment(Client $client, string $amount, string $date = '2026-02-01', string $reference = 'CONTRACT-001'): void
{
    app(RecordPayment::class)->handle(PaymentData::fromArray([
        'paid_at' => $date, 'amount' => $amount, 'personal_number' => $client->personal_number,
        'payer_name' => $client->name, 'bank_reference' => $reference,
    ]));
}

it('filters contract lists by client and preserves the reporting date in detail links', function (): void {
    $client = Client::factory()->create();
    $contract = Contract::factory()->for($client)->create(['price_minor' => 942857]);
    Contract::factory()->create();

    $result = app(WorkspaceQuery::class)->contracts(['client_id' => $client->id, 'as_of' => '2026-02-15']);

    expect($result['total'])->toBe(1)
        ->and($result['rows'][0]['id'])->toBe($contract->id)
        ->and($result['rows'][0]['price_GEL'])->toBe('9428.57')
        ->and($result['rows'][0]['_url'])->toEndWith('/contracts/'.$contract->id.'?as_of=2026-02-15');
});

it('keeps contract search inside the requested client filter', function (): void {
    $first = Client::factory()->create(['name' => 'First', 'personal_number' => '11111111111']);
    Contract::factory()->for($first)->create();
    $other = Client::factory()->create(['name' => 'Search Target', 'personal_number' => '22222222222']);
    Contract::factory()->for($other)->create();

    expect(app(WorkspaceQuery::class)->contracts(['client_id' => $first->id, 'q' => 'target'])['total'])->toBe(0);
});

it('searches client names personal numbers apartment numbers and projects', function (string $field): void {
    $client = Client::factory()->create(['name' => 'გიორგი Smith', 'personal_number' => '01005098765']);
    $contract = Contract::factory()->for($client)->create();
    $contract->apartment->update(['number' => 'LOOKUP-01']);
    $contract->apartment->project->update(['name' => 'Search Project']);
    $otherClient = Client::factory()->create(['name' => 'სხვა კლიენტი', 'personal_number' => '11111111111']);
    $other = Contract::factory()->for($otherClient)->create();
    $other->apartment->update(['number' => 'OTHER-1']);
    $other->apartment->project->update(['name' => 'Other Project']);
    $term = match ($field) {
        'name' => 'SMITH', 'personal' => '0100509', 'apartment' => 'lookup', 'project' => 'search project',
    };

    $result = app(WorkspaceQuery::class)->contracts(['q' => $term]);

    expect($result['total'])->toBe(1)->and($result['rows'][0]['id'])->toBe($contract->id);
})->with(['name', 'personal', 'apartment', 'project']);

it('derives installment states at the requested reporting date', function (int $paid, string $due, InstallmentStatus $status): void {
    $client = Client::factory()->create();
    $contract = Contract::factory()->for($client)->create(['price_minor' => 10000]);
    Installment::factory()->for($contract)->create(['sequence' => 1, 'due_date' => $due, 'amount_minor' => 10000]);
    if ($paid > 0) {
        contractQueryPayment($client, (string) $paid);
    }

    $result = app(WorkspaceQuery::class)->contract($contract, ['as_of' => '2026-02-15']);

    expect($result['rows'][0]['status'])->toBe($status->value)
        ->and($result['rows'][0]['paid_GEL'])->toBe(number_format($paid, 2, '.', ''))
        ->and($result['rows'][0]['remaining_GEL'])->toBe(number_format(100 - $paid, 2, '.', ''));
})->with([
    'fully paid' => [100, '2026-01-01', InstallmentStatus::Paid],
    'partially paid overdue' => [50, '2026-01-01', InstallmentStatus::Overdue],
    'partially prepaid' => [50, '2026-03-01', InstallmentStatus::Partial],
    'unpaid future' => [0, '2026-03-01', InstallmentStatus::Pending],
    'due today' => [0, '2026-02-15', InstallmentStatus::Pending],
]);

it('uses only the active allocation run and excludes future payments', function (): void {
    $client = Client::factory()->create();
    $contract = Contract::factory()->for($client)->create(['price_minor' => 15000]);
    Installment::factory()->for($contract)->create(['sequence' => 1, 'due_date' => '2026-01-01', 'amount_minor' => 6000]);
    Installment::factory()->for($contract)->create(['sequence' => 2, 'due_date' => '2026-03-01', 'amount_minor' => 9000]);
    contractQueryPayment($client, '110.00');
    contractQueryPayment($client, '40.00', '2026-03-02', 'FUTURE');

    $earlier = app(WorkspaceQuery::class)->contract($contract, ['as_of' => '2026-02-15']);
    $later = app(WorkspaceQuery::class)->contract($contract, ['as_of' => '2026-03-03']);

    expect($earlier['summary']['paid_GEL'])->toBe('110.00')
        ->and($earlier['summary']['remaining_GEL'])->toBe('40.00')
        ->and($earlier['rows'][0]['paid_GEL'])->toBe('60.00')
        ->and($earlier['rows'][1]['paid_GEL'])->toBe('50.00')
        ->and($earlier['rows'][1]['_links'][0]['url'])->toContain('as_of=2026-02-15')
        ->and($later['summary']['paid_GEL'])->toBe('150.00');
    $this->assertDatabaseCount('allocation_runs', 2);
});

it('respects global FIFO across a clients separate contracts', function (): void {
    $client = Client::factory()->create();
    $first = Contract::factory()->for($client)->create(['price_minor' => 9000]);
    $second = Contract::factory()->for($client)->create(['price_minor' => 6000]);
    Installment::factory()->for($first)->create(['sequence' => 1, 'due_date' => '2026-02-01', 'amount_minor' => 9000]);
    Installment::factory()->for($second)->create(['sequence' => 1, 'due_date' => '2026-01-01', 'amount_minor' => 6000]);
    contractQueryPayment($client, '110.00');

    $result = app(WorkspaceQuery::class)->contract($first, ['as_of' => '2026-02-15']);

    expect($result['total'])->toBe(1)
        ->and($result['summary']['paid_GEL'])->toBe('50.00')
        ->and($result['summary']['remaining_GEL'])->toBe('40.00');
});

it('paginates a contract schedule with a stable order for equal dates', function (): void {
    $contract = Contract::factory()->create(['price_minor' => 2100]);
    $installments = Installment::factory()->count(21)->for($contract)
        ->sequence(fn (Sequence $sequence): array => ['sequence' => $sequence->index + 1])
        ->create(['due_date' => '2026-02-01', 'amount_minor' => 100]);

    $result = app(WorkspaceQuery::class)->contract($contract, ['page' => 2]);

    expect($result['total'])->toBe(21)
        ->and($result['rows'])->toHaveCount(1)
        ->and($result['rows'][0]['id'])->toBe($installments->last()->id);
});
