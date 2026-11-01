<?php

declare(strict_types=1);

use App\Actions\Imports\ProcessPaymentImport;
use App\Enums\ImportRowStatus;
use App\Enums\ImportStatus;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Installment;
use App\Models\Payment;
use App\Models\PaymentImport;
use App\Models\PaymentImportRow;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function processingImport(?User $user = null): PaymentImport
{
    return PaymentImport::query()->create([
        'filename' => 'payments.xlsx', 'file_hash' => str_repeat('a', 64),
        'status' => ImportStatus::Queued, 'user_id' => $user?->id,
    ]);
}

function processingPaymentRow(array $overrides = []): array
{
    return array_replace([
        'paid_at' => '2026-02-01', 'amount' => '110.00',
        'personal_number' => '01005098765', 'payer_name' => 'გიორგი ბერიძე',
        'purpose' => null, 'bank_reference' => 'IMPORT-001',
    ], $overrides);
}

function processingClient(): Client
{
    $client = Client::factory()->create(['personal_number' => '01005098765']);
    $contract = Contract::factory()->for($client)->create(['price_minor' => 10000]);
    Installment::factory()->for($contract)->create([
        'sequence' => 1, 'due_date' => '2026-01-01', 'amount_minor' => 10000,
    ]);

    return $client;
}

it('records a payment and allocation with the importing user and row number', function (): void {
    $client = processingClient();
    $user = User::factory()->create();
    $import = processingImport($user);
    $raw = processingPaymentRow();

    app(ProcessPaymentImport::class)->handle($import, [$raw]);

    $import->refresh();
    $row = $import->rows()->firstOrFail();
    $run = $client->fresh()->currentAllocationRun;
    expect($import->status)->toBe(ImportStatus::Completed)
        ->and($import->total_rows)->toBe(1)
        ->and($import->imported_rows)->toBe(1)
        ->and($import->duplicate_rows)->toBe(0)
        ->and($import->rejected_rows)->toBe(0)
        ->and($import->completed_at)->not->toBeNull()
        ->and($row->status)->toBe(ImportRowStatus::Imported)
        ->and($row->row_number)->toBe(2)
        ->and($row->raw_data)->toBe($raw)
        ->and($row->payment->amount_minor)->toBe(11000)
        ->and($row->message)->toBeNull()
        ->and($run->user_id)->toBe($user->id)
        ->and((int) $run->allocations()->sum('amount_minor'))->toBe(10000);
});

it('continues after validation errors and identifies duplicates and conflicts', function (): void {
    $client = processingClient();
    $import = processingImport();
    $valid = processingPaymentRow();
    app(ProcessPaymentImport::class)->handle($import, [
        $valid,
        $valid,
        processingPaymentRow(['amount' => '120.00']),
        processingPaymentRow(['amount' => '-10', 'bank_reference' => 'NEGATIVE']),
        processingPaymentRow(['personal_number' => '12345', 'bank_reference' => 'INVALID-PERSONAL']),
        processingPaymentRow(['amount' => '70.00', 'bank_reference' => 'VALID-AFTER-ERRORS']),
    ]);

    $import->refresh();
    $rows = $import->rows()->orderBy('row_number')->get();
    expect($import->status)->toBe(ImportStatus::CompletedWithErrors)
        ->and($import->total_rows)->toBe(6)
        ->and($import->imported_rows)->toBe(2)
        ->and($import->duplicate_rows)->toBe(1)
        ->and($import->rejected_rows)->toBe(3)
        ->and($rows->pluck('row_number')->all())->toBe([2, 3, 4, 5, 6, 7])
        ->and($rows[1]->status)->toBe(ImportRowStatus::Duplicate)
        ->and($rows[1]->payment_id)->toBe($rows[0]->payment_id)
        ->and($rows[0]->payment->amount_minor)->toBe(11000)
        ->and($rows[5]->status)->toBe(ImportRowStatus::Imported)
        ->and((int) $client->fresh()->currentAllocationRun->allocations()->sum('amount_minor'))->toBe(10000);
    foreach ($rows->slice(2, 3) as $row) {
        expect($row->status)->toBe(ImportRowStatus::Rejected)
            ->and($row->payment_id)->toBeNull()
            ->and($row->message)->toMatch('/[ა-ჰ]/u');
    }
    $this->assertDatabaseCount('payments', 2);
});

it('retains unmatched payments with a Georgian row explanation', function (?string $personalNumber): void {
    $import = processingImport();
    app(ProcessPaymentImport::class)->handle($import, [processingPaymentRow(['personal_number' => $personalNumber])]);
    $row = $import->rows()->firstOrFail();

    expect($import->fresh()->status)->toBe(ImportStatus::Completed)
        ->and($row->status)->toBe(ImportRowStatus::Imported)
        ->and($row->payment->client_id)->toBeNull()
        ->and($row->payment->amount_minor)->toBe(11000)
        ->and($row->message)->toBe('გადახდა შენახულია კლიენტთან დაკავშირების გარეშე.');
    $this->assertDatabaseCount('allocation_runs', 0);
})->with(['missing' => [null], 'unknown' => ['99999999999']]);

it('counts a payment from an earlier import as a duplicate in a new import', function (): void {
    $first = processingImport();
    $second = processingImport();
    $action = app(ProcessPaymentImport::class);
    $action->handle($first, [processingPaymentRow()]);
    $payment = Payment::query()->firstOrFail()->getAttributes();
    $action->handle($second, [processingPaymentRow()]);

    expect($second->fresh()->imported_rows)->toBe(0)
        ->and($second->fresh()->duplicate_rows)->toBe(1)
        ->and($second->rows()->firstOrFail()->status)->toBe(ImportRowStatus::Duplicate)
        ->and(Payment::query()->firstOrFail()->getAttributes())->toBe($payment);
    $this->assertDatabaseCount('payments', 1);
    $this->assertDatabaseCount('payment_import_rows', 2);
});

it('leaves completed imports unchanged on repeated processing', function (bool $withError): void {
    $import = processingImport();
    $action = app(ProcessPaymentImport::class);
    $rows = [processingPaymentRow($withError ? ['amount' => '-1'] : [])];
    $action->handle($import, $rows);
    $attributes = $import->fresh()->getAttributes();
    $this->travel(1)->minutes();

    $action->handle($import, [processingPaymentRow(['bank_reference' => 'SHOULD-NOT-BE-RECORDED'])]);

    expect($import->fresh()->getAttributes())->toBe($attributes);
    $this->assertDatabaseCount('payment_import_rows', 1);
    $this->assertDatabaseCount('payments', $withError ? 0 : 1);
})->with(['completed' => [false], 'with row errors' => [true]]);

it('rolls back a failed row and resumes without duplicating payments or counters', function (): void {
    $client = processingClient();
    $import = processingImport();
    $action = app(ProcessPaymentImport::class);
    $rows = [
        processingPaymentRow(['amount' => '60.00']),
        processingPaymentRow(['amount' => '40.00', 'bank_reference' => 'SECOND']),
    ];
    PaymentImportRow::creating(function (PaymentImportRow $row): void {
        if ($row->row_number === 3) {
            throw new RuntimeException('სტრიქონის შედეგის შენახვა ვერ მოხერხდა.');
        }
    });
    try {
        expect(fn () => $action->handle($import, $rows))->toThrow(RuntimeException::class);
    } finally {
        PaymentImportRow::flushEventListeners();
    }

    $previous = $client->fresh()->currentAllocationRun;
    expect($import->fresh()->imported_rows)->toBe(1)
        ->and((int) $previous->allocations()->sum('amount_minor'))->toBe(6000);
    $this->assertDatabaseCount('payments', 1);
    $this->assertDatabaseCount('allocation_runs', 1);
    $this->assertDatabaseCount('payment_import_rows', 1);
    $import->update(['status' => ImportStatus::Failed, 'completed_at' => now(), 'error_message' => 'დროებითი შეცდომა']);

    $action->handle($import, $rows);

    expect($import->fresh()->status)->toBe(ImportStatus::Completed)
        ->and($import->fresh()->imported_rows)->toBe(2)
        ->and($import->fresh()->error_message)->toBeNull()
        ->and((int) $client->fresh()->currentAllocationRun->allocations()->sum('amount_minor'))->toBe(10000)
        ->and((int) $previous->allocations()->sum('amount_minor'))->toBe(6000);
    $this->assertDatabaseCount('payments', 2);
    $this->assertDatabaseCount('allocation_runs', 2);
    $this->assertDatabaseCount('payment_import_rows', 2);
});

it('rejects invalid import row counts before changing the import', function (int $count): void {
    $import = processingImport();

    expect(fn () => app(ProcessPaymentImport::class)->handle($import, array_fill(0, $count, processingPaymentRow())))
        ->toThrow(InvalidArgumentException::class, 'ფაილში დასაშვებია 1-დან 2000-მდე მონაცემთა სტრიქონი.');
    expect($import->fresh()->status)->toBe(ImportStatus::Queued);
    $this->assertDatabaseCount('payment_import_rows', 0);
    $this->assertDatabaseCount('payments', 0);
})->with([0, 2001]);
