<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Stage;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ReportService;
use App\Services\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * 报表服务测试：数据聚合正确性 + 边界值。
 */
class ReportTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Tenant $otherTenant;
    private User $user;
    private User $otherUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => '报表测试租户',
            'slug' => 'r-' . Str::lower(Str::random(8)),
            'plan' => 'pro',
            'is_active' => true,
            'trial_ends_at' => null,
        ]);
        $this->otherTenant = Tenant::create([
            'name' => '其他租户',
            'slug' => 'o-' . Str::lower(Str::random(8)),
            'plan' => 'pro',
            'is_active' => true,
            'trial_ends_at' => null,
        ]);
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'admin']);
        $this->otherUser = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'sales']);
        app(Tenancy::class)->forUser($this->user);
    }

    private function seedData(): Stage
    {
        $stage = Stage::factory()->for($this->tenant, 'tenant')->create(['name' => '赢单', 'is_won' => true]);

        // 5 个不同状态的线索
        Lead::factory()->for($this->tenant, 'tenant')->create(['status' => 'new']);
        Lead::factory()->for($this->tenant, 'tenant')->create(['status' => 'new']);
        Lead::factory()->for($this->tenant, 'tenant')->create(['status' => 'contacted']);
        Lead::factory()->for($this->tenant, 'tenant')->create(['status' => 'qualified']);
        Lead::factory()->for($this->tenant, 'tenant')->create(['status' => 'converted']);

        // 联系人
        Contact::factory()->for($this->tenant, 'tenant')->count(3)->create();

        // 商机：2 个给 user，1 个给 otherUser（最后阶段=赢单）
        Opportunity::factory()->for($this->tenant, 'tenant')->create([
            'owner_id' => $this->user->id, 'stage_id' => $stage->id, 'value' => 50000,
        ]);
        Opportunity::factory()->for($this->tenant, 'tenant')->create([
            'owner_id' => $this->user->id, 'stage_id' => $stage->id, 'value' => 30000,
        ]);
        Opportunity::factory()->for($this->tenant, 'tenant')->create([
            'owner_id' => $this->otherUser->id, 'stage_id' => $stage->id, 'value' => 80000,
        ]);

        // 其他租户数据——不应出现在报表
        Lead::factory()->for($this->otherTenant, 'tenant')->count(5)->create(['status' => 'new']);

        return $stage;
    }

    // ------------------------------------------------------------------
    // 漏斗
    // ------------------------------------------------------------------

    public function test_funnel_aggregates_current_tenant_only(): void
    {
        $this->seedData();

        $funnel = app(ReportService::class)->funnel();

        // 本租户 5 条线索（其他租户 5 条被 Tenancy Scope 过滤）
        $this->assertSame(5, $funnel['total_leads']);
        $this->assertSame(2, $funnel['lead_by_status']['new']);
        $this->assertSame(1, $funnel['lead_by_status']['contacted']);
        $this->assertSame(1, $funnel['lead_by_status']['qualified']);
        $this->assertSame(1, $funnel['lead_by_status']['converted']);
        $this->assertSame(0, $funnel['lead_by_status']['lost']);
        $this->assertSame(20.0, $funnel['conversion_rate']); // 1/5*100
        $this->assertSame(3, $funnel['total_contacts']);
        $this->assertSame(3, $funnel['total_opportunities']);
    }

    public function test_funnel_empty_tenant_zero_division_safe(): void
    {
        $funnel = app(ReportService::class)->funnel();

        $this->assertSame(0, $funnel['total_leads']);
        $this->assertSame(0.0, $funnel['conversion_rate']); // 0/0 不应报错
        $this->assertSame(0.0, $funnel['contact_rate_from_lead']);
    }

    // ------------------------------------------------------------------
    // 按人业绩
    // ------------------------------------------------------------------

    public function test_performance_by_owner(): void
    {
        $this->seedData();

        $perf = app(ReportService::class)->performanceByOwner();

        $this->assertCount(2, $perf);
        // 按 total_value 降序
        $this->assertGreaterThanOrEqual($perf[1]['total_value'], $perf[0]['total_value']);

        $adminRow = collect($perf)->firstWhere('user_id', $this->user->id);
        $this->assertNotNull($adminRow);
        $this->assertSame(2, $adminRow['opp_count']);
        $this->assertEqualsWithDelta(80000.0, $adminRow['total_value'], 0.01);
        $this->assertSame(100.0, $adminRow['win_rate']); // 2/2 都在赢单阶段
    }

    public function test_performance_no_opportunities_returns_empty(): void
    {
        $perf = app(ReportService::class)->performanceByOwner();
        $this->assertSame([], $perf);
    }

    // ------------------------------------------------------------------
    // 月度趋势
    // ------------------------------------------------------------------

    public function test_monthly_trend_returns_right_months(): void
    {
        // 当前月创建一些数据
        Lead::factory()->for($this->tenant, 'tenant')->count(3)->create();

        $trend = app(ReportService::class)->monthlyTrend(3);

        $this->assertCount(3, $trend['labels']);
        $this->assertCount(3, $trend['leads']);
        // 最后一个月（当前月）应有 3 条线索
        $this->assertSame(3, end($trend['leads']));
    }

    // ------------------------------------------------------------------
    // 阶段分布
    // ------------------------------------------------------------------

    public function test_stage_breakdown_with_values(): void
    {
        $stage = Stage::factory()->for($this->tenant, 'tenant')->create(['name' => '赢单', 'is_won' => true]);
        Opportunity::factory()->for($this->tenant, 'tenant')->create([
            'stage_id' => $stage->id, 'value' => 100000,
        ]);
        Opportunity::factory()->for($this->tenant, 'tenant')->create([
            'stage_id' => $stage->id, 'value' => 50000,
        ]);

        $breakdown = app(ReportService::class)->stageBreakdown();

        $wonStage = collect($breakdown)->firstWhere('stage_id', $stage->id);
        $this->assertNotNull($wonStage);
        $this->assertSame(2, $wonStage['count']);
        $this->assertEqualsWithDelta(150000.0, $wonStage['total_value'], 0.01);
        $this->assertEqualsWithDelta(75000.0, $wonStage['avg_value'], 0.01);
    }

    // ------------------------------------------------------------------
    // 路由 & 页面
    // ------------------------------------------------------------------

    public function test_reports_page_renders(): void
    {
        $this->actingAs($this->user)
            ->get(route('reports'))
            ->assertOk()
            ->assertSee('数据报表')
            ->assertSee('销售漏斗');
    }

    public function test_reports_page_requires_tenant(): void
    {
        $orphan = User::factory()->create(['tenant_id' => null]);
        $this->actingAs($orphan)
            ->get(route('reports'))
            ->assertForbidden();
    }
}
