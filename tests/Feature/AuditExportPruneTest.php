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

    // ------------------------------------------------------------------
    // 归档（Phase 10）
    // ------------------------------------------------------------------

    /** 直写一条过期审计日志（绕过 created_at 自动填充） */
    private function createExpiredLog(array $attributes = []): AuditLog
    {
        $log = AuditLog::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'action' => 'created',
            'auditable_type' => Company::class,
            'auditable_id' => 999,
            'before' => ['name' => '旧名'],
            'after' => ['name' => '新名'],
        ], $attributes));
        \Illuminate\Support\Facades\DB::table('audit_logs')
            ->where('id', $log->id)
            ->update(['created_at' => now()->subDays(400)]);

        return $log->refresh();
    }

    public function test_archive_command_exports_gzip_and_deletes_expired(): void
    {
        $expired = $this->createExpiredLog();
        // 新日志不受影响
        Company::factory()->for($this->tenant, 'tenant')->create();
        $this->assertSame(2, AuditLog::count());

        $this->artisan('crm:archive-audit-logs', ['--days' => 365]);

        // 源记录已删除（仅剩新日志）
        $this->assertSame(1, AuditLog::count());
        $this->assertNull(AuditLog::find($expired->id));

        // gzip 归档文件存在且内容完整（zlib 流包装器可直接读）
        $path = storage_path('app/audit-archive/audit-logs-'.now()->format('Y-m-d').'.csv.gz');
        $this->assertFileExists($path);

        $content = file_get_contents('compress.zlib://'.$path);
        $rows = explode("\n", trim($content));
        $this->assertStringContainsString('tenant_id', $rows[0], '首行应为表头');
        $this->assertStringContainsString((string) $expired->id, $rows[1]);
        $this->assertStringContainsString('created', $rows[1]);

        // 测试后清理归档目录，不污染其他用例/本地环境
        unlink($path);
        @rmdir(storage_path('app/audit-archive'));
    }

    public function test_archive_dry_run_exports_nothing_and_deletes_nothing(): void
    {
        $this->createExpiredLog();

        $this->artisan('crm:archive-audit-logs', ['--days' => 365, '--dry-run' => true]);

        $this->assertSame(1, AuditLog::count(), 'dry-run 不应删除');
        $this->assertFileDoesNotExist(storage_path('app/audit-archive/audit-logs-'.now()->format('Y-m-d').'.csv.gz'));
    }

    public function test_archive_skips_when_no_expired_logs(): void
    {
        Company::factory()->for($this->tenant, 'tenant')->create();

        $this->artisan('crm:archive-audit-logs', ['--days' => 365]);

        $this->assertSame(1, AuditLog::count());
        $this->assertFileDoesNotExist(storage_path('app/audit-archive/audit-logs-'.now()->format('Y-m-d').'.csv.gz'));
    }
}
