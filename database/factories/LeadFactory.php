<?php

namespace Database\Factories;

use App\Models\Lead;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'company_name' => fake()->company(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'source' => fake()->randomElement(['website', 'referral', 'social', 'event', 'cold_call', null]),
            'status' => fake()->randomElement(['new', 'contacted', 'qualified', 'converted', 'lost']),
        ];
    }
}
