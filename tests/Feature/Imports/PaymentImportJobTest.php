<?php

declare(strict_types=1);

use App\Enums\ImportStatus;
use App\Jobs\ProcessPaymentImportJob;
use App\Models\Payment;
use App\Models\PaymentImport;
use App\Models\PaymentImportRow;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\Support\PaymentSpreadsheet;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('local');
});

function queuedImport(array $rows, ?array $headings = null): array
{
    $path = PaymentSpreadsheet::store($rows, $headings);
    $import = PaymentImport::query()->create([
        'filename' => 'payments.xlsx',
        'file_hash' => hash('sha256', Storage::disk('local')->get($path)),
        'status' => ImportStatus::Queued,
    ]);

    return [$import, new ProcessPaymentImportJob($import->id, $path)];
}

it('runs an actual spreadsheet through the queue and cleans up completed files', function (): void {
    $first = ['2026-02-01', '100.25', null, 'გადამხდელი', null, 'FIRST'];
    [$import, $job] = queuedImport([
        $first,
        $first,
        ['2026-02-01', '-1', null, 'გადამხდელი', null, 'INVALID'],
        ['2026-02-02', '50.00', null, 'გადამხდელი', null, 'SECOND'],
    ]);

    Bus::dispatchSync($job);

    $import->refresh();
    expect($import->status)->toBe(ImportStatus::CompletedWithErrors)
        ->and($import->total_rows)->toBe(4)
        ->and($import->imported_rows)->toBe(2)
        ->and($import->duplicate_rows)->toBe(1)
        ->and($import->rejected_rows)->toBe(1)
        ->and($import->completed_at)->not->toBeNull()
        ->and($import->error_message)->toBeNull();
    Storage::disk('local')->assertMissing($job->path);
    $this->assertDatabaseCount('payments', 2);
});

it('does not reread completed imports or downgrade them on late failure callbacks', function (): void {
    [$import, $job] = queuedImport([['2026-02-01', 100, null, 'გადამხდელი', null, 'ONCE']]);
    Bus::dispatchSync($job);
    $attributes = $import->fresh()->getAttributes();
    $this->travel(1)->minutes();

    Bus::dispatchSync($job);
    $job->failed(new RuntimeException('დაგვიანებული შეცდომა'));

    expect($import->fresh()->getAttributes())->toBe($attributes);
    $this->assertDatabaseCount('payments', 1);
    $this->assertDatabaseCount('payment_import_rows', 1);
});

it('records a Georgian file error and retains invalid spreadsheets for inspection', function (): void {
    [$import, $job] = queuedImport([['invalid']], ['wrong_heading']);

    expect(fn () => Bus::dispatchSync($job))->toThrow(InvalidArgumentException::class);

    $import->refresh();
    expect($import->status)->toBe(ImportStatus::Failed)
        ->and($import->completed_at)->not->toBeNull()
        ->and($import->error_message)->toBe('იმპორტი შეწყდა. შეამოწმე ფაილი და სცადე ხელახლა ან ატვირთე თავიდან.');
    Storage::disk('local')->assertExists($job->path);
    $this->assertDatabaseCount('payments', 0);
});

it('retains committed row progress and resumes after a technical failure', function (): void {
    [$import, $job] = queuedImport([
        ['2026-02-01', '60.00', null, 'გადამხდელი', null, 'PROGRESS-1'],
        ['2026-02-02', '40.00', null, 'გადამხდელი', null, 'PROGRESS-2'],
    ]);
    PaymentImportRow::creating(function (PaymentImportRow $row): void {
        if ($row->row_number === 3) {
            throw new RuntimeException('SQLSTATE internal technical details');
        }
    });
    try {
        expect(fn () => Bus::dispatchSync($job))->toThrow(RuntimeException::class);
    } finally {
        PaymentImportRow::flushEventListeners();
    }

    $import->refresh();
    expect($import->status)->toBe(ImportStatus::Failed)
        ->and($import->total_rows)->toBe(2)
        ->and($import->imported_rows)->toBe(1)
        ->and($import->error_message)->not->toContain('SQLSTATE');
    Storage::disk('local')->assertExists($job->path);
    $this->assertDatabaseCount('payments', 1);

    Bus::dispatchSync($job);

    expect($import->fresh()->status)->toBe(ImportStatus::Completed)
        ->and($import->fresh()->imported_rows)->toBe(2)
        ->and($import->fresh()->error_message)->toBeNull()
        ->and((int) Payment::query()->sum('amount_minor'))->toBe(10000);
    Storage::disk('local')->assertMissing($job->path);
    $this->assertDatabaseCount('payments', 2);
    $this->assertDatabaseCount('payment_import_rows', 2);
});

it('skips an overlapping job and allows processing once the lock is released', function (): void {
    [$import, $job] = queuedImport([['2026-02-01', 100, null, 'გადამხდელი', null, 'LOCKED']]);
    $lock = Cache::lock($job->middleware()[0]->getLockKey($job), 120);
    expect($lock->get())->toBeTrue();
    try {
        Bus::dispatchSync($job);
        expect($import->fresh()->status)->toBe(ImportStatus::Queued);
        $this->assertDatabaseCount('payments', 0);
        Storage::disk('local')->assertExists($job->path);
    } finally {
        $lock->release();
    }

    Bus::dispatchSync($job);

    expect($import->fresh()->status)->toBe(ImportStatus::Completed);
    $this->assertDatabaseCount('payments', 1);
});

it('fails safely when the import record no longer exists', function (): void {
    $job = new ProcessPaymentImportJob(999999, 'missing.xlsx');

    expect(fn () => Bus::dispatchSync($job))->toThrow(ModelNotFoundException::class);
    $this->assertDatabaseCount('payments', 0);
    $this->assertDatabaseCount('payment_imports', 0);
});
