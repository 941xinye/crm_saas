<?php

namespace Database\Factories;

use App\Models\Opportunity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Opportunity>
 */
class OpportunityFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'value' => fake()->randomFloat(2, 1000, 1000000),
            'expected_close_date' => fake()->dateTimeBetween('+1 week', '+6 months')->format('Y-m-d'),
            'probability' => fake()->numberBetween(0, 100),
        ];
    }
}
