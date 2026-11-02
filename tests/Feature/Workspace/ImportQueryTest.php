<?php

declare(strict_types=1);

use App\Enums\ImportRowStatus;
use App\Enums\ImportStatus;
use App\Http\Requests\WorkspaceRequest;
use App\Models\Payment;
use App\Models\PaymentImport;
use App\Queries\WorkspaceQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function queriedImport(string $filename = 'payments.xlsx', ImportStatus $status = ImportStatus::Queued): PaymentImport
{
    return PaymentImport::query()->create([
        'filename' => $filename, 'file_hash' => str_repeat('f', 64), 'status' => $status,
    ]);
}

it('lists newest imports first and reports when polling is required', function (): void {
    queriedImport('first.xlsx');
    $latest = queriedImport('latest.xlsx', ImportStatus::Failed);

    $result = app(WorkspaceQuery::class)->imports([]);

    expect($result['total'])->toBe(2)
        ->and($result['rows'][0]['id'])->toBe($latest->id)
        ->and($result['rows'][0]['status'])->toBe('failed')
        ->and($result['rows'][0])->not->toHaveKey('file_hash')
        ->and($result['importStatus'])->toBe('processing');
});

it('filters import names and statuses and handles a zero search', function (): void {
    $target = queriedImport('bank-0.xlsx');
    queriedImport('other.xlsx', ImportStatus::Completed);
    $query = app(WorkspaceQuery::class);

    $result = $query->imports(['q' => '0', 'import_status' => 'queued']);
    $empty = $query->imports(['q' => 'BANK-0', 'import_status' => 'completed']);

    expect($result['total'])->toBe(1)->and($result['rows'][0]['id'])->toBe($target->id)
        ->and($empty['total'])->toBe(0)->and($empty['importStatus'])->toBeNull();
});

it('refreshes completion status counters and user-facing errors', function (): void {
    $import = queriedImport();
    PaymentImport::query()->whereKey($import->id)->update([
        'status' => ImportStatus::Failed,
        'total_rows' => 3, 'imported_rows' => 1, 'duplicate_rows' => 1, 'rejected_rows' => 1,
        'error_message' => 'იმპორტი შეწყდა.',
    ]);

    $result = app(WorkspaceQuery::class)->import($import, []);

    expect($result['importStatus'])->toBe('failed')
        ->and($result['summary']['total'])->toBe(3)
        ->and($result['summary']['imported'])->toBe(1)
        ->and($result['summary']['duplicates'])->toBe(1)
        ->and($result['summary']['rejected'])->toBe(1)
        ->and($result['summary']['error'])->toBe('იმპორტი შეწყდა.');
});

it('exposes row outcomes and payment links without raw payloads or file hashes', function (): void {
    $import = queriedImport();
    $payment = Payment::factory()->create();
    $import->rows()->create([
        'row_number' => 2, 'payment_id' => $payment->id, 'status' => ImportRowStatus::Imported,
        'raw_data' => ['bank_reference' => '0000123', 'internal_note' => 'private-raw-content'],
    ]);

    $result = app(WorkspaceQuery::class)->import($import, []);

    expect($result['rows'][0]['bank_reference'])->toBe('0000123')
        ->and($result['rows'][0]['_url'])->toEndWith('/payments/'.$payment->id)
        ->and($result['rows'][0])->not->toHaveKey('raw_data')
        ->and(json_encode($result))->not->toContain('private-raw-content', str_repeat('f', 64));
});

it('paginates only the chosen imports rows in Excel row order', function (): void {
    $import = queriedImport();
    foreach (range(22, 2) as $number) {
        $import->rows()->create([
            'row_number' => $number, 'status' => ImportRowStatus::Rejected,
            'raw_data' => [], 'message' => 'არასწორი სტრიქონი.',
        ]);
    }
    queriedImport('other.xlsx')->rows()->create([
        'row_number' => 999, 'status' => ImportRowStatus::Rejected, 'raw_data' => [],
    ]);

    $result = app(WorkspaceQuery::class)->import($import, ['page' => 2]);

    expect($result['total'])->toBe(21)->and($result['rows'])->toHaveCount(1)
        ->and($result['rows'][0]['row'])->toBe(22)
        ->and($result['rows'][0]['_url'])->toBeNull();
});

it('keeps invalid raw reference shapes out of the page props', function (): void {
    $import = queriedImport();
    $import->rows()->create([
        'row_number' => 2, 'status' => ImportRowStatus::Rejected,
        'raw_data' => ['bank_reference' => ['invalid']], 'message' => 'საბანკო ნომერი უნდა იყოს ტექსტი.',
    ]);

    expect(app(WorkspaceQuery::class)->import($import, [])['rows'][0]['bank_reference'])->toBeNull();
});

it('validates import status filters in Georgian', function (): void {
    $request = new WorkspaceRequest;
    $validator = Validator::make(['import_status' => 'invalid'], $request->rules(), $request->messages(), $request->attributes());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->first('import_status'))->toMatch('/[ა-ჰ]/u');
});
