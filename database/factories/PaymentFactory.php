<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Client;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * @var string
     */
    protected $model = Payment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => null,
            'paid_at' => fake()->dateTimeBetween('-18 months', 'now')->format('Y-m-d'),
            'amount_minor' => fake()->numberBetween(10000, 5000000),
            'personal_number' => fn (array $attributes): ?string => $attributes['client_id'] === null
                ? fake()->optional()->numerify('###########')
                : Client::query()->findOrFail($attributes['client_id'])->personal_number,
            'payer_name' => fn (array $attributes): string => $attributes['client_id'] === null
                ? fake()->name()
                : Client::query()->findOrFail($attributes['client_id'])->name,
            'purpose' => fake()->optional(0.8)->randomElement(['ბინის საწყისი შენატანი', 'განვადება', 'ნაწილობრივი გადახდა', 'ბოლო შენატანი']),
            'bank_reference' => fake()->unique()->uuid(),
        ];
    }

    public function matched(): static
    {
        return $this->state(['client_id' => Client::factory()]);
    }

    public function unmatched(): static
    {
        return $this->state(['client_id' => null]);
    }
}
