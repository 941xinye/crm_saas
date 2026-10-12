<?php

namespace Database\Factories;

use App\Models\Stage;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Stage>
 *
 * tenant_id 兜底为嵌套 Tenant::factory()（stages.tenant_id 为 NOT NULL，
 * 且 tenant 无 TenantScope 上下文可用）；测试中优先 ->for($tenant, 'tenant') 覆盖。
 */
class StageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->unique()->word(),
            'order' => fake()->numberBetween(0, 20),
            'probability' => fake()->numberBetween(0, 100),
            'is_default' => false,
            'is_won' => false,
            'is_lost' => false,
        ];
    }
}
