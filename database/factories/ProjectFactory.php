<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * @var string
     */
    protected $model = Project::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company().' '.fake()->randomElement(['Residence', 'Tower', 'Park', 'Gardens']),
            'city' => fake()->randomElement(['Batumi', 'Tbilisi', 'Kutaisi', 'Kobuleti']),
        ];
    }
}
