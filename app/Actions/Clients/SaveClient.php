<?php

declare(strict_types=1);

namespace App\Actions\Clients;

use App\Actions\Payments\ReallocateClientPayments;
use App\Models\Client;
use App\Models\Payment;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class SaveClient
{
    public function __construct(private ReallocateClientPayments $reallocate) {}

    public function handle(array $data, ?Client $client = null, ?int $userId = null): Client
    {
        try {
            return DB::transaction(function () use ($data, $client, $userId): Client {
                $client = $client === null ? new Client : Client::query()->lockForUpdate()->findOrFail($client->id);
                if ($client->exists && $client->personal_number !== $data['personal_number']
                    && ($client->contracts()->exists() || $client->payments()->exists())) {
                    throw ValidationException::withMessages([
                        'personal_number' => 'ხელშეკრულების ან გადახდის მქონე კლიენტის პირადი ნომრის შეცვლა დაუშვებელია.',
                    ]);
                }
                $client->fill([
                    'name' => $data['name'], 'personal_number' => $data['personal_number'], 'phone' => $data['phone'] ?? null,
                ])->save();

                $matched = Payment::query()->whereNull('client_id')->where('personal_number', $client->personal_number)
                    ->update(['client_id' => $client->id]);
                if ($matched > 0) {
                    $this->reallocate->handle($client->id, $userId, 'client_matched');
                }

                return $client;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['personal_number' => 'ასეთი პირადი ნომერი უკვე არსებობს.']);
        }
    }
}
