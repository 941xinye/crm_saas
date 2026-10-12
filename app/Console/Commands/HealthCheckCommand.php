<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\QueueFailureAlertNotification;
use Illuminate\Cache\Repository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * 系统健康检查（定时任务，建议每小时跑一次）：
 *  1. 队列失败监控 — 发现冷却窗口内新增 failed_jobs 时站内信告警平台超管
 *     （Cache 冷却去重，同一批失败只告警一次）
 *  2. 导出文件清理 — 清理超过保留期的 GDPR 租户数据导出 ZIP（见 docs/gdpr.md）
 */
class HealthCheckCommand extends Command
{
    protected $signature = 'crm:health-check {--notify : 发送告警通知（默认按配置 crm.health.queue_failure_notify）}';

    protected $description = '系统健康检查：队列失败告警 + 过期租户导出 ZIP 清理';

    public function handle(Repository $cache): int
    {
        $failures = $this->checkQueueFailures($cache);
        $cleaned = $this->pruneStaleExports();

        $hasFailure = $failures > 0;
        $this->info($hasFailure
            ? "队列失败：冷却窗口内新增 {$failures} 个任务" . ($cleaned > 0 ? "；已清理 {$cleaned} 个过期导出 ZIP" : '')
            : '队列正常' . ($cleaned > 0 ? "；已清理 {$cleaned} 个过期导出 ZIP" : ''));

        // 告警走 --notify 显式开启，或配置开启（定时任务场景）
        $shouldNotify = $this->option('notify')
            || filter_var(config('crm.health.queue_failure_notify', true), FILTER_VALIDATE_BOOL);

        if ($hasFailure && $shouldNotify) {
            $this->notifyPlatformAdmins($cache, $failures);
        }

        return $hasFailure ? self::FAILURE : self::SUCCESS;
    }

    /**
     * 冷却窗口内新增的队列失败任务数。
     */
    private function checkQueueFailures(Repository $cache): int
    {
        $hours = max(1, (int) config('crm.reminder.cooldown_hours', 24));

        return (int) DB::table('failed_jobs')
            ->where('failed_at', '>=', now()->subHours($hours))
            ->count();
    }

    /**
     * 清理超过保留期的租户导出 ZIP。
     * 目录与 TenantDataService::exportZip 一致（storage/app/tenant-exports，非 local 磁盘）。
     */
    private function pruneStaleExports(): int
    {
        $retentionHours = max(1, (int) config('crm.health.export_retention_hours', 24));
        $deadline = now()->subHours($retentionHours)->getTimestamp();
        $dir = storage_path('app/tenant-exports');

        if (! is_dir($dir)) {
            return 0;
        }

        $cleaned = 0;
        foreach (glob($dir . '/*.zip') ?: [] as $file) {
            if (filemtime($file) < $deadline) {
                @unlink($file);
                $cleaned++;
            }
        }

        return $cleaned;
    }

    /**
     * 通知平台超管（Cache 冷却去重：同一窗口只告警一次）。
     */
    private function notifyPlatformAdmins(Repository $cache, int $count): void
    {
        $hours = max(1, (int) config('crm.reminder.cooldown_hours', 24));

        // 窗口内已告警过则跳过
        if (! $cache->add("crm:health:queue_alert", true, now()->addHours($hours))) {
            $this->line('本冷却窗口已告警过，跳过重复通知');

            return;
        }

        $admins = User::query()->where('is_platform_admin', true)->get();
        foreach ($admins as $admin) {
            $admin->notify(new QueueFailureAlertNotification($count, "{$hours} 小时"));
        }

        $this->info("已通知 {$admins->count()} 位平台超管");
    }
}
