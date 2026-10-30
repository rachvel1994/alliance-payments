<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Apartment;
use App\Models\Client;
use App\Models\Contract;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contract>
 */
class ContractFactory extends Factory
{
    /**
     * @var string
     */
    protected $model = Contract::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'apartment_id' => Apartment::factory()->sold(),
            'contract_date' => fake()->dateTimeBetween('-18 months', 'now')->format('Y-m-d'),
            'price_minor' => fn (array $attributes): int => max(1, intdiv(
                (int) Apartment::query()->findOrFail($attributes['apartment_id'])->price_minor * fake()->numberBetween(90, 100),
                100,
            )),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Contract $contract): void {
            $contract->apartment()->where('status', '!=', 'sold')->update(['status' => 'sold']);
        });
    }
}
