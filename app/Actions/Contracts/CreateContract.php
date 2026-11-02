<?php

declare(strict_types=1);

namespace App\Actions\Contracts;

use App\Actions\Payments\ReallocateClientPayments;
use App\Data\NewContractData;
use App\Models\Apartment;
use App\Models\Client;
use App\Models\Contract;
use App\Services\Contracts\MonthlySchedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final readonly class CreateContract
{
    public function __construct(private MonthlySchedule $schedule, private ReallocateClientPayments $reallocate) {}

    public function handle(NewContractData $data, ?int $userId = null): Contract
    {
        try {
            $installments = $this->schedule->generate($data->priceMinor, $data->installmentsCount, $data->firstDueDate);
        } catch (InvalidArgumentException $error) {
            throw ValidationException::withMessages(['installments_count' => $error->getMessage()]);
        }

        return DB::transaction(function () use ($data, $userId, $installments): Contract {
            $client = Client::query()->lockForUpdate()->findOrFail($data->clientId);
            $apartment = Apartment::query()->lockForUpdate()->findOrFail($data->apartmentId);
            if ($apartment->status !== 'free' || $apartment->contract()->exists()) {
                throw ValidationException::withMessages(['apartment_id' => 'ეს ბინა უკვე გაყიდულია. აირჩიე თავისუფალი ბინა.']);
            }

            $contract = $client->contracts()->create([
                'apartment_id' => $apartment->id,
                'contract_date' => $data->contractDate,
                'price_minor' => $data->priceMinor,
            ]);
            $contract->installments()->createMany($installments);
            $apartment->update(['status' => 'sold']);
            $this->reallocate->handle($client->id, $userId, 'contract_created');

            return $contract;
        }, 3);
    }
}
