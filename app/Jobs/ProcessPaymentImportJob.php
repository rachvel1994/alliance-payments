<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Imports\ProcessPaymentImport;
use App\Enums\ImportStatus;
use App\Models\PaymentImport;
use App\Services\Imports\ReadPaymentRows;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class ProcessPaymentImportJob implements ShouldQueueAfterCommit
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(public int $importId, public string $path) {}

    public function middleware(): array
    {
        return [(new WithoutOverlapping('payment-import-'.$this->importId))
            ->dontRelease()->expireAfter(120)];
    }

    public function handle(ReadPaymentRows $reader, ProcessPaymentImport $process): void
    {
        $import = PaymentImport::query()->findOrFail($this->importId);
        if (! in_array($import->status, [ImportStatus::Completed, ImportStatus::CompletedWithErrors], true)) {
            $import->update([
                'status' => ImportStatus::Processing,
                'error_message' => null,
                'completed_at' => null,
            ]);
            $process->handle($import, $reader->read($this->path));
        }

        Storage::disk('local')->delete($this->path);
    }

    public function failed(?Throwable $error): void
    {
        $import = PaymentImport::query()->find($this->importId);
        if ($import === null || in_array($import->status, [ImportStatus::Completed, ImportStatus::CompletedWithErrors], true)) {
            return;
        }
        $import->update([
            'status' => ImportStatus::Failed,
            'completed_at' => now(),
            'error_message' => 'იმპორტი შეწყდა. შეამოწმე ფაილი და სცადე ხელახლა ან ატვირთე თავიდან.',
        ]);
    }
}
