<?php

declare(strict_types=1);

namespace App\Actions\Imports;

use App\Actions\Payments\RecordPayment;
use App\Data\PaymentData;
use App\Enums\ImportRowStatus;
use App\Enums\ImportStatus;
use App\Models\PaymentImport;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final readonly class ProcessPaymentImport
{
    public function __construct(private RecordPayment $record) {}

    public function handle(PaymentImport $import, array $rows): void
    {
        if ($rows === [] || count($rows) > 2000) {
            throw new InvalidArgumentException('ფაილში დასაშვებია 1-დან 2000-მდე მონაცემთა სტრიქონი.');
        }

        $import->refresh();
        if (in_array($import->status, [ImportStatus::Completed, ImportStatus::CompletedWithErrors], true)) {
            return;
        }
        $import->update([
            'status' => ImportStatus::Processing,
            'total_rows' => count($rows),
            'completed_at' => null,
            'error_message' => null,
        ]);

        foreach ($rows as $index => $raw) {
            DB::transaction(function () use ($import, $index, $raw): void {
                $locked = PaymentImport::query()->lockForUpdate()->findOrFail($import->id);
                $number = $index + 2;
                if ($locked->rows()->where('row_number', $number)->exists()) {
                    return;
                }

                $paymentId = null;
                try {
                    $result = $this->record->handle(PaymentData::fromArray($raw), $locked->user_id);
                    $paymentId = $result->payment->id;
                    $status = $result->created ? ImportRowStatus::Imported : ImportRowStatus::Duplicate;
                    $message = $result->payment->client_id === null
                        ? 'გადახდა შენახულია კლიენტთან დაკავშირების გარეშე.' : null;
                } catch (ValidationException $error) {
                    $status = ImportRowStatus::Rejected;
                    $message = implode(' ', array_merge(...array_values($error->errors())));
                }

                $locked->rows()->create([
                    'payment_id' => $paymentId,
                    'row_number' => $number,
                    'raw_data' => $raw,
                    'status' => $status,
                    'message' => $message,
                ]);
                $locked->increment(match ($status) {
                    ImportRowStatus::Imported => 'imported_rows',
                    ImportRowStatus::Duplicate => 'duplicate_rows',
                    ImportRowStatus::Rejected => 'rejected_rows',
                });
            }, 3);
        }

        $import->refresh();
        $import->update([
            'status' => $import->rejected_rows > 0 ? ImportStatus::CompletedWithErrors : ImportStatus::Completed,
            'completed_at' => now(),
        ]);
    }
}
