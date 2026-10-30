<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Apartment;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Apartment>
 */
class ApartmentFactory extends Factory
{
    /**
     * @var string
     */
    protected $model = Apartment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'number' => strtoupper(fake()->unique()->bothify('?-####')),
            'floor' => fake()->numberBetween(1, 25),
            'area' => number_format(fake()->numberBetween(3500, 18000) / 100, 2, '.', ''),
            'price_minor' => function (array $attributes): int {
                $city = Project::query()->findOrFail($attributes['project_id'])->city;

                $range = match ($city) {
                    'Tbilisi' => [1800, 3200],
                    'Batumi' => [1400, 2600],
                    'Kobuleti' => [1100, 1900],
                    default => [900, 1600],
                };

                $areaHundredths = (int) round((float) $attributes['area'] * 100);

                return $areaHundredths * fake()->numberBetween(...$range);
            },
            'status' => fake()->randomElement(['free', 'sold']),
        ];
    }

    public function free(): static
    {
        return $this->state(['status' => 'free']);
    }

    public function sold(): static
    {
        return $this->state(['status' => 'sold']);
    }
}
