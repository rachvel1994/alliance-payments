<?php

declare(strict_types=1);

use App\Actions\Payments\ReallocateClientPayments;
use App\Actions\Payments\RecordPayment;
use App\Contracts\AllocationStrategy;
use App\Data\PaymentData;
use App\Data\RecordedPayment;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Installment;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payments\FifoAllocationStrategy;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function paymentActionData(?Client $client = null, array $overrides = []): PaymentData
{
    return PaymentData::fromArray(array_replace([
        'paid_at' => '2026-02-01',
        'amount' => '100.00',
        'personal_number' => $client?->personal_number,
        'payer_name' => $client?->name ?? 'გადამხდელი',
        'purpose' => 'განვადება',
        'bank_reference' => 'ACTION-PAYMENT-001',
    ], $overrides));
}

function paymentActionInstallment(Client $client, int $amountMinor = 10000, string $dueDate = '2026-01-01'): Installment
{
    $contract = Contract::factory()->for($client)->create([
        'contract_date' => '2026-01-01',
        'price_minor' => $amountMinor,
    ]);

    return Installment::factory()->for($contract)->create([
        'sequence' => 1,
        'due_date' => $dueDate,
        'amount_minor' => $amountMinor,
    ]);
}

it('resolves FIFO from the container and records a matched payment with its actor', function (): void {
    expect(app(AllocationStrategy::class))->toBeInstanceOf(FifoAllocationStrategy::class);

    $client = Client::factory()->create(['personal_number' => '01005098765']);
    $installment = paymentActionInstallment($client);
    $user = User::factory()->create();
    $result = app(RecordPayment::class)->handle(paymentActionData($client), userId: $user->id);
    $run = $client->fresh()->currentAllocationRun;

    expect($result)->toBeInstanceOf(RecordedPayment::class)
        ->and($result->created)->toBeTrue()
        ->and($result->payment->client_id)->toBe($client->id)
        ->and($result->payment->personal_number)->toBe('01005098765')
        ->and($result->payment->paid_at->toDateString())->toBe('2026-02-01')
        ->and($result->payment->amount_minor)->toBe(10000)
        ->and($run->user_id)->toBe($user->id)
        ->and($run->reason)->toBe('payment_import')
        ->and($run->completed_at)->not->toBeNull()
        ->and($run->allocations()->firstOrFail()->installment_id)->toBe($installment->id)
        ->and((int) $run->allocations()->sum('amount_minor'))->toBe(10000);

    $this->assertDatabaseCount('payments', 1);
    $this->assertDatabaseCount('allocation_runs', 1);
    $this->assertDatabaseCount('payment_allocations', 1);
});

it('returns an identical payment without creating another payment or allocation run', function (): void {
    $client = Client::factory()->create();
    paymentActionInstallment($client);
    $data = paymentActionData($client);
    $action = app(RecordPayment::class);
    $first = $action->handle($data);
    $runId = $client->fresh()->current_allocation_run_id;
    $stored = $first->payment->fresh()->getAttributes();

    $duplicate = $action->handle($data);

    expect($duplicate->created)->toBeFalse()
        ->and($duplicate->payment->id)->toBe($first->payment->id)
        ->and($duplicate->payment->fresh()->getAttributes())->toBe($stored)
        ->and($client->fresh()->current_allocation_run_id)->toBe($runId);

    $this->assertDatabaseCount('payments', 1);
    $this->assertDatabaseCount('allocation_runs', 1);
});

it('compares equivalent database date representations without a false conflict', function (): void {
    $client = Client::factory()->create();
    $data = paymentActionData($client);
    $action = app(RecordPayment::class);
    $first = $action->handle($data);
    DB::table('payments')->where('id', $first->payment->id)->update(['paid_at' => '2026-02-01']);

    $duplicate = $action->handle($data);

    expect($duplicate->created)->toBeFalse()
        ->and($duplicate->payment->id)->toBe($first->payment->id);

    $this->assertDatabaseCount('payments', 1);
    $this->assertDatabaseCount('allocation_runs', 1);
});

it('rejects conflicting bank references in Georgian without overwriting stored data', function (string $field, mixed $value): void {
    $client = Client::factory()->create();
    paymentActionInstallment($client);
    $action = app(RecordPayment::class);
    $first = $action->handle(paymentActionData($client));
    $stored = $first->payment->fresh()->getAttributes();
    $runId = $client->fresh()->current_allocation_run_id;

    try {
        $action->handle(paymentActionData($client, [$field => $value]));
        $this->fail('განსხვავებული მონაცემებით საბანკო ნომრის გამეორება უნდა უარყოფილიყო.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('bank_reference')
            ->and($exception->errors()['bank_reference'][0])->toMatch('/[ა-ჰ]/u');
    }

    expect($first->payment->fresh()->getAttributes())->toBe($stored)
        ->and($client->fresh()->current_allocation_run_id)->toBe($runId);

    $this->assertDatabaseCount('payments', 1);
    $this->assertDatabaseCount('allocation_runs', 1);
    $this->assertDatabaseCount('payment_allocations', 1);
})->with([
    'amount' => ['amount', '150.00'],
    'date' => ['paid_at', '2026-02-02'],
    'personal number' => ['personal_number', '99999999999'],
    'payer name' => ['payer_name', 'სხვა გადამხდელი'],
    'purpose' => ['purpose', 'სხვა დანიშნულება'],
]);

it('retains payments with missing or unknown personal numbers without allocations', function (?string $personalNumber): void {
    Client::factory()->create(['personal_number' => '01005098765']);
    $result = app(RecordPayment::class)->handle(paymentActionData(overrides: [
        'personal_number' => $personalNumber,
    ]));

    expect($result->created)->toBeTrue()
        ->and($result->payment->client_id)->toBeNull()
        ->and($result->payment->personal_number)->toBe($personalNumber)
        ->and($result->payment->amount_minor)->toBe(10000);

    $this->assertDatabaseCount('clients', 1);
    $this->assertDatabaseCount('allocation_runs', 0);
    $this->assertDatabaseCount('payment_allocations', 0);
})->with([null, '99999999999']);

it('can record a matched payment while deferring allocation', function (): void {
    $client = Client::factory()->create();
    paymentActionInstallment($client);
    $result = app(RecordPayment::class)->handle(paymentActionData($client), allocate: false);

    expect($result->created)->toBeTrue()
        ->and($result->payment->client_id)->toBe($client->id)
        ->and($client->fresh()->current_allocation_run_id)->toBeNull();

    $this->assertDatabaseCount('payments', 1);
    $this->assertDatabaseCount('allocation_runs', 0);
});

it('treats different bank references as separate payments', function (): void {
    $client = Client::factory()->create();
    paymentActionInstallment($client);
    $action = app(RecordPayment::class);
    $first = $action->handle(paymentActionData($client));
    $second = $action->handle(paymentActionData($client, ['bank_reference' => 'SECOND-PAYMENT']));

    expect($first->created)->toBeTrue()
        ->and($second->created)->toBeTrue()
        ->and($second->payment->id)->not->toBe($first->payment->id)
        ->and((int) $client->fresh()->currentAllocationRun->allocations()->sum('amount_minor'))->toBe(10000);

    $this->assertDatabaseCount('payments', 2);
    $this->assertDatabaseCount('allocation_runs', 2);
});

it('does not silently reassign an existing unmatched duplicate after a client is added', function (): void {
    $data = paymentActionData(overrides: ['personal_number' => '01005098765']);
    $action = app(RecordPayment::class);
    $first = $action->handle($data);
    Client::factory()->create(['personal_number' => '01005098765']);

    $duplicate = $action->handle($data);

    expect($duplicate->created)->toBeFalse()
        ->and($duplicate->payment->id)->toBe($first->payment->id)
        ->and($duplicate->payment->fresh()->client_id)->toBeNull();

    $this->assertDatabaseCount('payments', 1);
    $this->assertDatabaseCount('allocation_runs', 0);
});

it('recalculates the complete history when a backdated payment is recorded', function (): void {
    $client = Client::factory()->create();
    $contract = Contract::factory()->for($client)->create(['price_minor' => 10000]);
    $firstInstallment = Installment::factory()->for($contract)->create([
        'sequence' => 1, 'due_date' => '2026-01-01', 'amount_minor' => 5000,
    ]);
    $secondInstallment = Installment::factory()->for($contract)->create([
        'sequence' => 2, 'due_date' => '2026-02-01', 'amount_minor' => 5000,
    ]);
    $action = app(RecordPayment::class);
    $later = $action->handle(paymentActionData($client, ['amount' => '60.00']));
    $previousRun = $client->fresh()->currentAllocationRun;
    $earlier = $action->handle(paymentActionData($client, [
        'paid_at' => '2026-01-01', 'amount' => '40.00', 'bank_reference' => 'BACKDATED-PAYMENT',
    ]));
    $run = $client->fresh()->currentAllocationRun;

    expect($run->id)->not->toBe($previousRun->id)
        ->and((int) $run->allocations()->sum('amount_minor'))->toBe(10000)
        ->and((int) $run->allocations()->where('payment_id', $earlier->payment->id)->sum('amount_minor'))->toBe(4000)
        ->and((int) $run->allocations()->where('payment_id', $later->payment->id)
            ->where('installment_id', $firstInstallment->id)->sum('amount_minor'))->toBe(1000)
        ->and((int) $run->allocations()->where('installment_id', $secondInstallment->id)->sum('amount_minor'))->toBe(5000)
        ->and((int) $previousRun->allocations()->sum('amount_minor'))->toBe(6000);
});

it('rolls back a new payment and run if automatic allocation fails', function (): void {
    $client = Client::factory()->create();
    paymentActionInstallment($client);
    app(RecordPayment::class)->handle(paymentActionData($client));
    $runId = $client->fresh()->current_allocation_run_id;
    $strategy = Mockery::mock(AllocationStrategy::class);
    $strategy->shouldReceive('allocate')->once()->andThrow(new RuntimeException('განაწილება ვერ შესრულდა.'));
    $this->app->instance(AllocationStrategy::class, $strategy);

    expect(fn () => app(RecordPayment::class)->handle(paymentActionData($client, [
        'bank_reference' => 'FAILED-PAYMENT',
    ])))->toThrow(RuntimeException::class);

    expect($client->fresh()->current_allocation_run_id)->toBe($runId);
    $this->assertDatabaseMissing('payments', ['bank_reference' => 'FAILED-PAYMENT']);
    $this->assertDatabaseCount('payments', 1);
    $this->assertDatabaseCount('allocation_runs', 1);
    $this->assertDatabaseCount('payment_allocations', 1);
});

it('reallocates only the selected client across all of their contracts', function (): void {
    $client = Client::factory()->create();
    $otherClient = Client::factory()->create();
    $laterInstallment = paymentActionInstallment($client, 9000, '2026-02-01');
    $earlierInstallment = paymentActionInstallment($client, 6000);
    paymentActionInstallment($otherClient, 50000, '2025-01-01');
    $payment = Payment::factory()->for($client)->create(['paid_at' => '2026-02-01', 'amount_minor' => 11000]);
    Payment::factory()->for($otherClient)->create(['amount_minor' => 50000]);
    $user = User::factory()->create();

    $run = app(ReallocateClientPayments::class)->handle($client->id, $user->id);

    expect($run->client_id)->toBe($client->id)
        ->and($run->user_id)->toBe($user->id)
        ->and($run->reason)->toBe('manual')
        ->and($run->completed_at)->not->toBeNull()
        ->and($client->fresh()->current_allocation_run_id)->toBe($run->id)
        ->and($otherClient->fresh()->current_allocation_run_id)->toBeNull()
        ->and($run->allocations()->orderBy('id')->get(['payment_id', 'installment_id', 'amount_minor'])->toArray())->toBe([
            ['payment_id' => $payment->id, 'installment_id' => $earlierInstallment->id, 'amount_minor' => 6000],
            ['payment_id' => $payment->id, 'installment_id' => $laterInstallment->id, 'amount_minor' => 5000],
        ]);
});

it('appends manual allocation history while switching the active run', function (): void {
    $client = Client::factory()->create();
    paymentActionInstallment($client);
    Payment::factory()->for($client)->create(['amount_minor' => 15000]);
    $action = app(ReallocateClientPayments::class);
    $first = $action->handle($client->id);
    $history = $first->allocations()->get()->toArray();
    $second = $action->handle($client->id, reason: 'manual_retry');

    expect($second->id)->not->toBe($first->id)
        ->and($second->reason)->toBe('manual_retry')
        ->and($client->fresh()->current_allocation_run_id)->toBe($second->id)
        ->and((int) $second->allocations()->sum('amount_minor'))->toBe(10000)
        ->and($first->allocations()->get()->toArray())->toBe($history)
        ->and(Payment::query()->firstOrFail()->amount_minor)->toBe(15000);

    $this->assertDatabaseCount('allocation_runs', 2);
    $this->assertDatabaseCount('payment_allocations', 2);
});

it('completes an empty run when a client has no installments', function (): void {
    $client = Client::factory()->create();
    Payment::factory()->for($client)->create();

    $run = app(ReallocateClientPayments::class)->handle($client->id);

    expect($run->completed_at)->not->toBeNull()
        ->and($client->fresh()->current_allocation_run_id)->toBe($run->id)
        ->and($run->allocations()->count())->toBe(0);
});

it('does not create an allocation run for a missing client', function (): void {
    expect(fn () => app(ReallocateClientPayments::class)->handle(999999))->toThrow(ModelNotFoundException::class);

    $this->assertDatabaseCount('allocation_runs', 0);
});

it('rolls back partially saved allocations without changing existing history', function (): void {
    $client = Client::factory()->create();
    $installment = paymentActionInstallment($client);
    $payment = Payment::factory()->for($client)->create(['amount_minor' => 10000]);
    $previous = app(ReallocateClientPayments::class)->handle($client->id);
    $history = $previous->allocations()->get()->toArray();
    $strategy = Mockery::mock(AllocationStrategy::class);
    $strategy->shouldReceive('allocate')->once()->andReturn([
        ['payment_id' => $payment->id, 'installment_id' => $installment->id, 'amount_minor' => 5000],
        ['payment_id' => $payment->id, 'installment_id' => PHP_INT_MAX, 'amount_minor' => 1],
    ]);

    expect(fn () => (new ReallocateClientPayments($strategy))->handle($client->id))->toThrow(QueryException::class);

    expect($client->fresh()->current_allocation_run_id)->toBe($previous->id)
        ->and($previous->allocations()->get()->toArray())->toBe($history);

    $this->assertDatabaseCount('allocation_runs', 1);
    $this->assertDatabaseCount('payment_allocations', 1);
});
