<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;

class PruneAuditLogsCommand extends Command
{
    protected $signature = 'crm:prune-audit-logs
                            {--days= : 保留天数（默认读取 config crm.audit.retention_days）}
                            {--dry-run : 仅统计，不实际删除}';

    protected $description = '按保留策略清理过期审计日志（console 逃生舱下全租户执行）';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: config('crm.audit.retention_days', 365));
        $cutoff = now()->subDays($days);

        $query = AuditLog::query()->where('created_at', '<', $cutoff);
        $count = (clone $query)->count();

        if ($this->option('dry-run')) {
            $this->info("将清理 {$count} 条早于 {$cutoff->format('Y-m-d')} 的审计日志（dry-run，未删除）");

            return self::SUCCESS;
        }

        if ($count === 0) {
            $this->info('没有需要清理的审计日志');

            return self::SUCCESS;
        }

        // 分批删除避免大事务锁表
        $deleted = 0;
        $query->select('id')->chunkById(1000, function ($logs) use (&$deleted) {
            AuditLog::whereIn('id', $logs->pluck('id')->all())->delete();
            $deleted += $logs->count();
        });

        $this->info("已清理 {$deleted} 条审计日志（早于 {$cutoff->format('Y-m-d')}）");

        return self::SUCCESS;
    }
}
