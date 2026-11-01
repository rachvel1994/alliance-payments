<?php

declare(strict_types=1);

namespace App\Actions\Imports;

use App\Enums\ImportStatus;
use App\Jobs\ProcessPaymentImportJob;
use App\Models\PaymentImport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

final class StartPaymentImport
{
    public function handle(UploadedFile $file, int $userId): PaymentImport
    {
        $path = null;
        $import = null;
        try {
            $path = $file->store('payment-imports', 'local');
            if (! is_string($path)) {
                throw new RuntimeException('ატვირთული ფაილის შენახვა ვერ მოხერხდა.');
            }
            $import = DB::transaction(fn (): PaymentImport => PaymentImport::query()->create([
                'user_id' => $userId,
                'filename' => mb_substr(basename($file->getClientOriginalName()), 0, 255),
                'file_hash' => hash_file('sha256', $file->getRealPath()),
                'status' => ImportStatus::Queued,
            ]), 3);
            ProcessPaymentImportJob::dispatch($import->id, $path);

            return $import;
        } catch (Throwable $error) {
            report($error);
            $import?->update([
                'status' => ImportStatus::Failed,
                'completed_at' => now(),
                'error_message' => 'იმპორტის რიგში დამატება ვერ მოხერხდა. ატვირთე ფაილი თავიდან.',
            ]);
            if (is_string($path)) {
                Storage::disk('local')->delete($path);
            }
            throw ValidationException::withMessages([
                'file' => 'იმპორტის რიგში დამატება ვერ მოხერხდა. სცადე ხელახლა.',
            ]);
        }
    }
}
