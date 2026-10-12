<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Opportunity;
use App\Models\Stage;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * 性能基准命令测试：冒烟验证六类基准可执行、表格与 JSON 输出格式正确。
 * sqlite :memory: 数据量小，不验证绝对耗时（见 docs/performance.md）。
 */
class BenchmarkCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = Tenant::create([
            'name' => '基准租户',
            'slug' => 'bench',
            'plan' => 'pro',
            'is_active' => true,
        ]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $company = Company::create(['tenant_id' => $tenant->id, 'name' => '基准公司']);
        $stage = Stage::create(['tenant_id' => $tenant->id, 'name' => '待验证', 'order' => 1]);
        Opportunity::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'stage_id' => $stage->id,
            'title' => '基准商机',
            'value' => 100,
        ]);
        Activity::create([
            'tenant_id' => $tenant->id,
            'activitable_type' => Opportunity::class,
            'activitable_id' => 1,
            'user_id' => $user->id,
            'type' => 'call',
            'subject' => '基准活动',
            'scheduled_at' => now()->addHour(),
        ]);
        AuditLog::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'action' => 'created',
            'auditable_type' => Company::class,
            'auditable_id' => $company->id,
        ]);
    }

    public function test_benchmark_runs_all_cases_and_outputs_table(): void
    {
        $artisan = $this->artisan('crm:benchmark', ['--iterations' => 1])
            ->expectsOutputToContain('crm:benchmark');

        foreach (['dashboard_stats', 'opportunity_list', 'global_search', 'reminder_scan', 'audit_recent', 'reports_aggregate'] as $key) {
            $artisan->expectsOutputToContain($key);
        }

        $artisan->assertSuccessful();
    }

    public function test_benchmark_json_output_is_valid(): void
    {
        Artisan::call('crm:benchmark', ['--iterations' => 1, '--json' => true]);

        $json = json_decode(trim(Artisan::output()), true, flags: JSON_THROW_ON_ERROR);

        $this->assertCount(6, $json);
        $this->assertArrayHasKey('dashboard_stats', $json);
        $this->assertArrayHasKey('avg_ms', $json['dashboard_stats']);
        $this->assertArrayHasKey('median_ms', $json['dashboard_stats']);
    }
}
