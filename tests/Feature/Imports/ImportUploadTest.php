<?php

declare(strict_types=1);

use App\Actions\Imports\StartPaymentImport;
use App\Enums\ImportStatus;
use App\Jobs\ProcessPaymentImportJob;
use App\Models\PaymentImport;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\PaymentSpreadsheet;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('local');
});

function importUploadFile(): UploadedFile
{
    return PaymentSpreadsheet::upload([['2026-02-01', '100.25', '01005098765', 'გიორგი ბერიძე', null, 'UPLOAD-001']]);
}

function importUploadEditor(): User
{
    $user = User::factory()->create();
    $user->assignRole('editor');

    return $user;
}

it('redirects guests and does not store their uploads', function (): void {
    Queue::fake();
    $this->post(route('imports.store'), ['file' => importUploadFile()])->assertRedirect(route('login'));

    $this->assertDatabaseCount('payment_imports', 0);
    expect(Storage::disk('local')->allFiles())->toBe([]);
    Queue::assertNothingPushed();
});

it('returns unauthorized for JSON requests from guests', function (): void {
    $this->postJson(route('imports.store'))->assertUnauthorized();
});

it('forbids users without the payment import permission', function (?string $role): void {
    Queue::fake();
    $user = User::factory()->create();
    if ($role !== null) {
        $user->assignRole($role);
    }
    $this->actingAs($user)->post(route('imports.store'), ['file' => importUploadFile()])->assertForbidden();

    $this->assertDatabaseCount('payment_imports', 0);
    expect(Storage::disk('local')->allFiles())->toBe([]);
    Queue::assertNothingPushed();
})->with(['viewer' => ['viewer'], 'without a role' => [null]]);

it('stores a private file and queues its import for an editor', function (): void {
    Queue::fake();
    $editor = importUploadEditor();
    $file = importUploadFile();
    $hash = hash_file('sha256', $file->getRealPath());

    $this->actingAs($editor)->from(route('clients.index'))
        ->post(route('imports.store'), ['file' => $file])
        ->assertRedirect(route('clients.index'))
        ->assertSessionHas('success', 'ფაილი დაემატა დამუშავების რიგს.');

    $import = PaymentImport::query()->firstOrFail();
    expect($import->status)->toBe(ImportStatus::Queued)
        ->and($import->user_id)->toBe($editor->id)
        ->and($import->filename)->toBe('payments.xlsx')
        ->and($import->file_hash)->toBe($hash)
        ->and($import->total_rows)->toBe(0);
    Queue::assertPushed(ProcessPaymentImportJob::class, function (ProcessPaymentImportJob $job) use ($import): bool {
        expect($job)->toBeInstanceOf(ShouldQueueAfterCommit::class);
        Storage::disk('local')->assertExists($job->path);

        return $job->importId === $import->id && str_starts_with($job->path, 'payment-imports/');
    });
    $this->assertDatabaseCount('payments', 0);
    $this->get(route('clients.index'))->assertInertia(fn (Assert $page) => $page
        ->where('flash.success', 'ფაილი დაემატა დამუშავების რიგს.'));
});

it('permits uploads with a directly granted permission', function (): void {
    Queue::fake();
    $user = User::factory()->create();
    $user->givePermissionTo('payments.import');

    $this->actingAs($user)->from(route('clients.index'))
        ->post(route('imports.store'), ['file' => importUploadFile()])->assertRedirect(route('clients.index'));

    Queue::assertPushed(ProcessPaymentImportJob::class, 1);
});

it('rejects missing or invalid files with Georgian messages', function (string $case, string $message): void {
    Queue::fake();
    $file = match ($case) {
        'missing' => null,
        'array' => ['not a file'],
        'text' => UploadedFile::fake()->createWithContent('payments.xlsx', 'plain text')->mimeType('text/plain'),
        'extension' => PaymentSpreadsheet::upload([['2026-02-01', 100, null, 'გადამხდელი', null, 'BAD-EXT']], 'payments.txt'),
        'size' => UploadedFile::fake()->create('payments.xlsx', 5121, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
    };

    $this->actingAs(importUploadEditor())->from(route('clients.index'))
        ->post(route('imports.store'), $file === null ? [] : ['file' => $file])
        ->assertRedirect(route('clients.index'))->assertSessionHasErrors(['file' => $message]);

    $this->assertDatabaseCount('payment_imports', 0);
    expect(Storage::disk('local')->allFiles())->toBe([]);
    Queue::assertNothingPushed();
})->with([
    'missing' => ['missing', 'აირჩიე Excel ფაილი.'],
    'array' => ['array', 'ატვირთე ფაილი.'],
    'text disguised as XLSX' => ['text', 'დასაშვებია მხოლოდ XLSX ფორმატის ფაილი.'],
    'wrong extension' => ['extension', 'ფაილის გაფართოება უნდა იყოს .xlsx.'],
    'oversized' => ['size', 'ფაილის ზომა არ უნდა აღემატებოდეს 5 MiB-ს.'],
]);

it('preserves separate upload history for identical file contents', function (): void {
    Queue::fake();
    $editor = importUploadEditor();
    $bytes = PaymentSpreadsheet::bytes([['2026-02-01', 100, null, 'გადამხდელი', null, 'REPEAT']]);
    foreach (['first.xlsx', 'second.xlsx'] as $filename) {
        $this->actingAs($editor)->from(route('clients.index'))
            ->post(route('imports.store'), ['file' => UploadedFile::fake()->createWithContent($filename, $bytes)])
            ->assertRedirect(route('clients.index'));
    }

    $this->assertDatabaseCount('payment_imports', 2);
    expect(PaymentImport::query()->distinct()->count('file_hash'))->toBe(1)
        ->and(Storage::disk('local')->allFiles())->toHaveCount(2);
    Queue::assertPushed(ProcessPaymentImportJob::class, 2);
});

it('waits for the surrounding transaction to commit before queuing', function (): void {
    config(['queue.default' => 'database']);
    $editor = importUploadEditor();
    $file = importUploadFile();

    $import = DB::transaction(function () use ($editor, $file): PaymentImport {
        $import = app(StartPaymentImport::class)->handle($file, $editor->id);
        $this->assertDatabaseCount('jobs', 0);

        return $import;
    });

    $this->assertDatabaseCount('jobs', 1);
    expect($import->fresh()->status)->toBe(ImportStatus::Queued);
});

it('marks an import failed and removes the file when queue dispatch fails', function (): void {
    config(['queue.default' => 'database']);
    Schema::drop('jobs');

    $this->actingAs(importUploadEditor())->from(route('clients.index'))
        ->post(route('imports.store'), ['file' => importUploadFile()])
        ->assertRedirect(route('clients.index'))
        ->assertSessionHasErrors(['file' => 'იმპორტის რიგში დამატება ვერ მოხერხდა. სცადე ხელახლა.'])
        ->assertSessionMissing('success');

    $import = PaymentImport::query()->firstOrFail();
    expect($import->status)->toBe(ImportStatus::Failed)
        ->and($import->completed_at)->not->toBeNull()
        ->and($import->error_message)->toMatch('/[ა-ჰ]/u')
        ->and($import->error_message)->not->toContain('SQLSTATE')
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

it('removes stored files when the import record cannot be saved', function (): void {
    Queue::fake();
    expect(fn () => app(StartPaymentImport::class)->handle(importUploadFile(), 999999))
        ->toThrow(ValidationException::class);

    $this->assertDatabaseCount('payment_imports', 0);
    expect(Storage::disk('local')->allFiles())->toBe([]);
    Queue::assertNothingPushed();
});

it('limits each authenticated user to ten uploads per minute', function (): void {
    Queue::fake();
    $editor = importUploadEditor();
    $file = importUploadFile();
    for ($index = 0; $index < 10; $index++) {
        $this->actingAs($editor)->from(route('clients.index'))
            ->post(route('imports.store'), ['file' => $file])->assertRedirect(route('clients.index'));
    }
    $this->post(route('imports.store'), ['file' => $file])->assertTooManyRequests();

    $this->assertDatabaseCount('payment_imports', 10);
    Queue::assertPushed(ProcessPaymentImportJob::class, 10);
});
