<?php

namespace Database\Factories;

use App\Models\Stage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Stage>
 */
class StageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'order' => fake()->numberBetween(0, 20),
            'probability' => fake()->numberBetween(0, 100),
            'is_default' => false,
        ];
    }
}
