<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Notifications\QueueFailureAlertNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * crm:health-check 命令测试：队列失败告警 + 过期租户导出 ZIP 清理。
 */
class HealthCheckCommandTest extends TestCase
{
    use RefreshDatabase;

    private User $platformAdmin;
    private User $tenantUser;

    protected function setUp(): void
    {
        parent::setUp();

        // 清理缓存，避免冷却去重跨测试污染
        config(['cache.default' => 'array']);
        cache()->clear();

        $this->platformAdmin = User::factory()->create([
            'tenant_id' => null,
            'is_platform_admin' => true,
        ]);
        $tenant = Tenant::create([
            'name' => '健康检查租户',
            'slug' => 'h-' . Str::lower(Str::random(8)),
            'plan' => 'free',
            'is_active' => true,
            'trial_ends_at' => now()->addDays(14),
        ]);
        $this->tenantUser = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'admin']);
    }

    private function insertFailedJob(): void
    {
        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => '{}',
            'exception' => 'test exception',
            'failed_at' => now(),
        ]);
    }

    public function test_alerts_platform_admins_on_new_queue_failures(): void
    {
        $this->insertFailedJob();

        $this->artisan('crm:health-check', ['--notify' => true]);

        $this->assertTrue(
            $this->platformAdmin->notifications()
                ->where('type', QueueFailureAlertNotification::class)
                ->exists(),
            '平台超管应收到队列失败告警',
        );
        $this->assertFalse(
            $this->tenantUser->notifications()->exists(),
            '普通租户用户不应收到平台告警',
        );
    }

    public function test_no_alert_when_queue_healthy(): void
    {
        $this->artisan('crm:health-check', ['--notify' => true]);

        $this->assertFalse(
            $this->platformAdmin->notifications()->exists(),
            '队列正常时不应告警',
        );
    }

    public function test_alerts_only_once_per_cooldown_window(): void
    {
        $this->insertFailedJob();

        $this->artisan('crm:health-check', ['--notify' => true]);
        $this->artisan('crm:health-check', ['--notify' => true]);

        $this->assertSame(
            1,
            $this->platformAdmin->notifications()
                ->where('type', QueueFailureAlertNotification::class)
                ->count(),
            '同一冷却窗口内只应告警一次',
        );
    }

    public function test_cleans_stale_export_zips(): void
    {
        // 目录与 TenantDataService::exportZip 一致（storage/app/tenant-exports）
        $dir = storage_path('app/tenant-exports');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents("{$dir}/tenant_1_old.zip", 'old');
        file_put_contents("{$dir}/tenant_1_new.zip", 'new');
        touch("{$dir}/tenant_1_old.zip", now()->subHours(48)->getTimestamp());

        $this->artisan('crm:health-check', ['--notify' => true]);

        $this->assertFileDoesNotExist("{$dir}/tenant_1_old.zip", '过期导出 ZIP 应被清理');
        $this->assertFileExists("{$dir}/tenant_1_new.zip", '新鲜导出 ZIP 应保留');
    }

    protected function tearDown(): void
    {
        $dir = storage_path('app/tenant-exports');
        if (is_dir($dir)) {
            foreach (glob("{$dir}/*.zip") ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($dir);
        }
        parent::tearDown();
    }
}
