<?php

namespace Tests\Feature;

use App\Livewire\Reports;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Stage;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CsvService;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 报表 CSV 导出（趋势/业绩/阶段/漏斗）测试。
 */
class ReportsExportTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private ReportService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => '报表租户',
            'slug' => 'r-' . Str::lower(Str::random(8)),
            'plan' => 'free',
            'is_active' => true,
            'trial_ends_at' => now()->addDays(14),
        ]);
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'admin']);
        $this->actingAs($this->user);
        app(\App\Services\Tenancy::class)->forUser($this->user);

        $this->service = app(ReportService::class);

        // 造一条完整链路数据：线索 → 公司/联系人 → 商机
        Lead::factory()->for($this->tenant, 'tenant')->create(['status' => 'converted']);
        $stage = Stage::factory()->for($this->tenant, 'tenant')->create();
        $company = Company::factory()->for($this->tenant, 'tenant')->create();
        Contact::factory()->for($this->tenant, 'tenant')->create(['company_id' => $company->id]);
        Opportunity::factory()->for($this->tenant, 'tenant')->create([
            'company_id' => $company->id,
            'stage_id' => $stage->id,
            'value' => 50000,
            'owner_id' => $this->user->id,
        ]);
    }

    public function test_reports_page_shows_export_buttons(): void
    {
        Livewire::test(Reports::class)
            ->assertSee('导出：')
            ->assertSee('业绩');
    }

    public function test_download_report_builds_csv_from_assoc_rows(): void
    {
        $rows = [
            ['负责人' => '张三', '商机数' => 2, '赢率(%)' => 50],
            ['负责人' => '李四', '商机数' => 1, '赢率(%)' => 0],
        ];

        $response = app(CsvService::class)->downloadReport('团队业绩.csv', $rows);
        $content = $response->getContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertStringContainsString('负责人,商机数', $content);
        $this->assertStringContainsString('张三', $content);
    }

    public function test_export_rows_builders_return_flat_rows(): void
    {
        $performance = Reports::performanceRows($this->service);
        $this->assertNotEmpty($performance);
        $this->assertArrayHasKey('负责人', $performance[0]);

        $stage = Reports::stageRows($this->service);
        $this->assertNotEmpty($stage);
        $this->assertArrayHasKey('阶段', $stage[0]);

        $funnel = Reports::funnelRows($this->service);
        $this->assertNotEmpty($funnel);
        $this->assertSame(1, $funnel[0]['数值']); // 1 条已转化线索

        $trend = Reports::trendRows($this->service, 6);
        $this->assertNotEmpty($trend);
        $this->assertArrayHasKey('月份', $trend[0]);
    }
}
