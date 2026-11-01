<?php

declare(strict_types=1);

use App\Enums\ImportRowStatus;
use App\Enums\ImportStatus;
use App\Models\Payment;
use App\Models\PaymentImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('creates imports with queued database defaults and integer counters', function (): void {
    $import = PaymentImport::query()->create([
        'filename' => 'payments.xlsx', 'file_hash' => str_repeat('a', 64),
    ])->fresh();

    expect($import->status)->toBe(ImportStatus::Queued)
        ->and($import->total_rows)->toBe(0)
        ->and($import->imported_rows)->toBe(0)
        ->and($import->duplicate_rows)->toBe(0)
        ->and($import->rejected_rows)->toBe(0)
        ->and($import->error_message)->toBeNull()
        ->and($import->completed_at)->toBeNull();
});

it('round trips import statuses through the database', function (ImportStatus $status): void {
    $import = PaymentImport::query()->create([
        'filename' => 'payments.xlsx', 'file_hash' => str_repeat('a', 64),
        'status' => $status,
    ]);

    expect($import->fresh()->status)->toBe($status);
    $this->assertDatabaseHas('payment_imports', ['id' => $import->id, 'status' => $status->value]);
})->with(ImportStatus::cases());

it('round trips row statuses and preserves the original personal number', function (ImportRowStatus $status): void {
    $import = PaymentImport::query()->create([
        'filename' => 'payments.xlsx', 'file_hash' => str_repeat('a', 64),
    ]);
    $row = $import->rows()->create([
        'payment_id' => $status === ImportRowStatus::Rejected ? null : Payment::factory()->create()->id,
        'row_number' => 2,
        'raw_data' => ['personal_number' => '01005098765'],
        'status' => $status,
    ])->fresh();

    expect($row->status)->toBe($status)
        ->and($row->row_number)->toBe(2)
        ->and($row->raw_data)->toBe(['personal_number' => '01005098765']);
})->with(ImportRowStatus::cases());

it('persists file errors and completion time without losing row counters', function (): void {
    $import = PaymentImport::query()->create([
        'filename' => 'payments.xlsx', 'file_hash' => str_repeat('a', 64),
        'status' => ImportStatus::Failed,
        'total_rows' => 3, 'imported_rows' => 1, 'duplicate_rows' => 1, 'rejected_rows' => 1,
        'completed_at' => '2026-11-02 03:00:00',
        'error_message' => 'იმპორტი შეწყდა. სცადე ხელახლა.',
    ])->fresh();

    expect($import->status)->toBe(ImportStatus::Failed)
        ->and($import->error_message)->toBe('იმპორტი შეწყდა. სცადე ხელახლა.')
        ->and($import->completed_at->format('Y-m-d H:i:s'))->toBe('2026-11-02 03:00:00')
        ->and($import->total_rows)->toBe(3)
        ->and($import->imported_rows)->toBe(1)
        ->and($import->duplicate_rows)->toBe(1)
        ->and($import->rejected_rows)->toBe(1);
});
