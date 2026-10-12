<?php

namespace Database\Factories;

use App\Models\Activity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Activity>
 */
class ActivityFactory extends Factory
{
    public function definition(): array
    {
        $type = fake()->randomElement(['call', 'email', 'meeting', 'note', 'task']);

        return [
            'activitable_type' => \App\Models\Opportunity::class,
            'activitable_id' => 1,
            'type' => $type,
            'subject' => match ($type) {
                'call' => fake()->sentence(2),
                'email' => fake()->sentence(3),
                'meeting' => 'Meeting: ' . fake()->sentence(2),
                'note' => fake()->paragraph(),
                'task' => 'Task: ' . fake()->sentence(2),
            },
            'scheduled_at' => fake()->dateTimeBetween('now', '+3 months'),
            'completed_at' => fake()->optional(0.7)->dateTimeBetween('-1 month', 'now'),
            'notes' => fake()->optional(0.6)->paragraph(),
        ];
    }
}
