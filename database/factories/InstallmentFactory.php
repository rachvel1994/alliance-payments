<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Contract;
use App\Models\Installment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\Sequence;
use InvalidArgumentException;

/**
 * @extends Factory<Installment>
 */
class InstallmentFactory extends Factory
{
    /**
     * @var string
     */
    protected $model = Installment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'contract_id' => Contract::factory(),
            'sequence' => 1,
            'due_date' => fn (array $attributes): string => Contract::query()
                ->findOrFail($attributes['contract_id'])->contract_date
                ->addDays(fake()->numberBetween(0, 30))->toDateString(),
            'amount_minor' => fn (array $attributes): int => fake()->numberBetween(
                1,
                (int) Contract::query()->findOrFail($attributes['contract_id'])->price_minor,
            ),
        ];
    }

    public function schedule(Contract $contract, int $count = 12): static
    {
        $price = (int) $contract->price_minor;

        if ($count < 1 || $count > $price) {
            throw new InvalidArgumentException('შენატანების რაოდენობა უნდა იყოს დადებითი და თითოეულ შენატანზე მინიმუმ 1 თეთრი უნდა ნაწილდებოდეს.');
        }

        $amount = intdiv($price, $count);
        $remainder = $price % $count;

        return $this->for($contract)->count($count)->sequence(fn (Sequence $sequence): array => [
            'sequence' => $sequence->index + 1,
            'due_date' => $contract->contract_date->copy()->addMonthsNoOverflow($sequence->index)->toDateString(),
            'amount_minor' => $amount + ($sequence->index === $count - 1 ? $remainder : 0),
        ]);
    }
}
