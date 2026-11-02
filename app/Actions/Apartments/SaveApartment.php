<?php

declare(strict_types=1);

namespace App\Actions\Apartments;

use App\Models\Apartment;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SaveApartment
{
    public function handle(array $data, ?Apartment $apartment = null): Apartment
    {
        try {
            return DB::transaction(function () use ($data, $apartment): Apartment {
                $apartment = $apartment === null ? new Apartment : Apartment::query()->lockForUpdate()->findOrFail($apartment->id);
                if ($apartment->exists && ($apartment->status !== 'free' || $apartment->contract()->exists())) {
                    throw ValidationException::withMessages(['number' => 'გაყიდული ბინის რედაქტირება დაუშვებელია.']);
                }
                $apartment->fill([
                    'project_id' => $data['project_id'], 'number' => $data['number'], 'floor' => $data['floor'],
                    'area' => $data['area'], 'price_minor' => $data['price_minor'], 'status' => 'free',
                ])->save();

                return $apartment;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['number' => 'ამ პროექტში ასეთი ბინის ნომერი უკვე არსებობს.']);
        }
    }
}
