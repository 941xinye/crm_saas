<?php

namespace Database\Factories;

use App\Models\Opportunity;
use App\Models\Stage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Opportunity>
 *
 * stage_id 兜底为嵌套 Stage::factory()（避免裸 create() 违反 NOT NULL/FK）。
 * 测试中应优先复用同租户 Stage，任选其一：
 *   Opportunity::factory()->recycle($stage)->create()   // recycle 替换嵌套 factory，同租户
 *   Opportunity::factory()->for($stage)                  // 关系方式
 *   Opportunity::factory()->create(['stage_id' => $stage->id])
 */
class OpportunityFactory extends Factory
{
    public function definition(): array
    {
        return [
            'stage_id' => Stage::factory(),
            'title' => fake()->sentence(3),
            'value' => fake()->randomFloat(2, 1000, 1000000),
            'expected_close_date' => fake()->dateTimeBetween('+1 week', '+6 months')->format('Y-m-d'),
            'probability' => fake()->numberBetween(0, 100),
        ];
    }
}
