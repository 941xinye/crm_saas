<?php

namespace Tests\Feature;

use App\Models\Opportunity;
use App\Models\Stage;
use App\Models\Tenant;
use App\Models\User;
use App\Services\OpportunityScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * 评分骤降提醒（crm:refresh-scores 检测降幅 → 通知负责人，冷却期去重 + 用户可静音）。
 */
class ScoreDropNotificationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $owner;
    private Opportunity $opportunity;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => '评分租户',
            'slug' => 's-' . Str::lower(Str::random(8)),
            'plan' => 'free',
            'is_active' => true,
            'trial_ends_at' => now()->addDays(14),
        ]);
        $this->owner = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'admin']);
        $stage = Stage::factory()->for($this->tenant, 'tenant')->create();
        $this->opportunity = Opportunity::factory()->for($this->tenant, 'tenant')->create([
            'stage_id' => $stage->id,
            'owner_id' => $this->owner->id,
        ]);

        // 预置旧评分 85（1 天前已算过）
        DB::table('opportunities')->where('id', $this->opportunity->id)->update([
            'score' => 85,
            'scored_at' => now()->subDay(),
        ]);
    }

    private function mockScoring(int $newScore): void
    {
        $mock = \Mockery::mock(OpportunityScoringService::class);
        $mock->shouldReceive('scoreBatch')->andReturn([$this->opportunity->id => $newScore]);
        $this->instance(OpportunityScoringService::class, $mock);
    }

    public function test_score_drop_notifies_owner_once(): void
    {
        $this->mockScoring(50); // 85 → 50，降幅 35 > 15

        $this->artisan('crm:refresh-scores', ['--tenant' => $this->tenant->slug]);

        $this->assertSame(1, $this->owner->notifications()->count());
        $this->assertStringContainsString(
            'OpportunityScoreDropNotification',
            $this->owner->notifications()->first()->type,
        );

        // 冷却期内再刷新（50 → 50 无降幅，也不应重复通知）
        $this->artisan('crm:refresh-scores', ['--tenant' => $this->tenant->slug]);
        $this->assertSame(1, $this->owner->notifications()->count());
    }

    public function test_small_drop_does_not_notify(): void
    {
        $this->mockScoring(75); // 85 → 75，降幅 10 < 15

        $this->artisan('crm:refresh-scores', ['--tenant' => $this->tenant->slug]);

        $this->assertSame(0, $this->owner->notifications()->count());
    }

    public function test_muted_owner_not_notified(): void
    {
        $this->owner->update(['notification_prefs' => ['score_drop' => false]]);
        $this->mockScoring(50);

        $this->artisan('crm:refresh-scores', ['--tenant' => $this->tenant->slug]);

        $this->assertSame(0, $this->owner->notifications()->count());
    }
}
