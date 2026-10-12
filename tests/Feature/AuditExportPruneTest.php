<?php

namespace Tests\Feature;

use App\Livewire\AuditLogs;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 审计日志 CSV 导出与保留策略清理命令测试。
 */
class AuditExportPruneTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => '审计测试租户',
            'slug' => 'a-' . Str::lower(Str::random(8)),
            'plan' => 'free',
            'is_active' => true,
            'trial_ends_at' => now()->addDays(14),
        ]);
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'admin']);
        $this->actingAs($this->user);
        app(\App\Services\Tenancy::class)->forUser($this->user);
    }

    public function test_export_csv_contains_current_tenant_logs_only(): void
    {
        // 当前租户产生一条审计日志
        Company::factory()->for($this->tenant, 'tenant')->create(['name' => '导出测试公司']);

        // 其他租户的日志不应出现在导出中
        $otherTenant = Tenant::create([
            'name' => '别家租户',
            'slug' => 'b-' . Str::lower(Str::random(8)),
            'plan' => 'free',
            'is_active' => true,
            'trial_ends_at' => now()->addDays(14),
        ]);
        $otherUser = User::factory()->create(['tenant_id' => $otherTenant->id, 'role' => 'admin']);
        AuditLog::create([
            'tenant_id' => $otherTenant->id,
            'user_id' => $otherUser->id,
            'action' => 'created',
            'auditable_type' => Company::class,
            'auditable_id' => 999,
            'after' => ['name' => '别家数据'],
        ]);

        // 组件暴露导出按钮
        Livewire::test(AuditLogs::class)->assertSee('导出 CSV');

        // 导出内容（Web 请求上下文，TenantScope 自动过滤其他租户）
        $csv = app(\App\Services\CsvService::class)->exportAuditLogs(
            AuditLog::query()->orderByDesc('created_at'),
        );

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv, '应含 UTF-8 BOM');
        $this->assertStringContainsString('导出测试公司', $csv);
        $this->assertStringContainsString('created', $csv);
        $this->assertStringNotContainsString('别家数据', $csv);
    }

    public function test_prune_command_deletes_only_expired_logs(): void
    {
        // 每次创建公司都会产生一条"新增"审计日志（Auditable trait）
        Company::factory()->for($this->tenant, 'tenant')->create();

        // 一条过期日志（直接写入，400 天前；绕过 fillable 用 DB 层更新 created_at）
        $expired = AuditLog::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'action' => 'created',
            'auditable_type' => Company::class,
            'auditable_id' => 1,
        ]);
        \Illuminate\Support\Facades\DB::table('audit_logs')
            ->where('id', $expired->id)
            ->update(['created_at' => now()->subDays(400)]);

        $this->assertSame(2, AuditLog::count());

        // dry-run 不删除
        $this->artisan('crm:prune-audit-logs', ['--days' => 365, '--dry-run' => true]);
        $this->assertSame(2, AuditLog::count());

        $this->artisan('crm:prune-audit-logs', ['--days' => 365]);
        $this->assertSame(1, AuditLog::count(), '只应删除过期日志');
    }
}
