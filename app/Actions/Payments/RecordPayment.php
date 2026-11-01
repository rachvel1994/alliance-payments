<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Data\PaymentData;
use App\Data\RecordedPayment;
use App\Models\Client;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class RecordPayment
{
    public function __construct(private ReallocateClientPayments $reallocate) {}

    public function handle(
        PaymentData $data,
        ?int $userId = null,
        bool $allocate = true,
    ): RecordedPayment {
        return DB::transaction(function () use ($data, $userId, $allocate): RecordedPayment {
            $client = $data->personalNumber === null ? null : Client::query()
                ->where('personal_number', $data->personalNumber)
                ->lockForUpdate()->first();

            $attributes = $data->attributes();
            $payment = Payment::query()->firstOrCreate(
                ['bank_reference' => $data->bankReference],
                [...$attributes, 'client_id' => $client?->id],
            );
            $created = $payment->wasRecentlyCreated;

            if (! $created && $payment->fill($attributes)->isDirty()) {
                throw ValidationException::withMessages([
                    'bank_reference' => 'ამ საბანკო ნომრით გადახდა უკვე არსებობს განსხვავებული მონაცემებით.',
                ]);
            }

            if ($created && $client !== null && $allocate) {
                $this->reallocate->handle($client->id, $userId, 'payment_import');
            }

            return new RecordedPayment($payment, $created);
        }, 3);
    }
}
