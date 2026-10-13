<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;

/**
 * 审计日志归档（Phase 10）：将早于保留期的审计日志导出为 gzip CSV
 * （storage/app/audit-archive/audit-logs-YYYY-MM-DD.csv.gz），导出成功后
 * 分批删除源记录。crm:prune-audit-logs（08:15）保留作为兜底清理。
 */
class ArchiveAuditLogsCommand extends Command
{
    protected $signature = 'crm:archive-audit-logs
                            {--days= : 归档早于该天数的日志（默认读取 config crm.audit.retention_days）}
                            {--dry-run : 仅统计，不导出不删除}';

    protected $description = '归档过期审计日志为 gzip CSV 后删除源记录（console 逃生舱下全租户执行）';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: config('crm.audit.retention_days', 365));
        $cutoff = now()->subDays($days);

        $query = AuditLog::query()->where('created_at', '<', $cutoff);
        $count = (clone $query)->count();

        if ($count === 0) {
            $this->info('没有需要归档的审计日志');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info("将归档 {$count} 条早于 {$cutoff->format('Y-m-d')} 的审计日志（dry-run，未导出未删除）");

            return self::SUCCESS;
        }

        $directory = storage_path('app/audit-archive');
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        $path = $directory.'/audit-logs-'.now()->format('Y-m-d').'.csv.gz';

        // zlib 流包装器：分批写 gzip，内存占用与批次大小相关而非总量
        $stream = fopen('compress.zlib://'.$path, 'wb');
        fputcsv($stream, ['id', 'tenant_id', 'user_id', 'action', 'auditable_type', 'auditable_id', 'batch_id', 'before', 'after', 'ip', 'user_agent', 'route', 'created_at']);

        $exported = 0;
        $query->select('id')->chunkById(1000, function ($logs) use (&$exported, $stream) {
            AuditLog::whereIn('id', $logs->pluck('id')->all())
                ->get()
                ->each(function (AuditLog $log) use ($stream, &$exported) {
                    fputcsv($stream, [
                        $log->id,
                        $log->tenant_id,
                        $log->user_id,
                        $log->action,
                        $log->auditable_type,
                        $log->auditable_id,
                        $log->batch_id,
                        json_encode($log->before, JSON_UNESCAPED_UNICODE),
                        json_encode($log->after, JSON_UNESCAPED_UNICODE),
                        $log->ip,
                        $log->user_agent,
                        $log->route,
                        $log->created_at?->toDateTimeString(),
                    ]);
                    $exported++;
                });
        });
        fclose($stream);

        // 导出成功后才删除源记录；删除失败不影响归档文件（残留交给 crm:prune-audit-logs 兜底）
        $deleted = 0;
        AuditLog::query()->where('created_at', '<', $cutoff)->select('id')
            ->chunkById(1000, function ($logs) use (&$deleted) {
                AuditLog::whereIn('id', $logs->pluck('id')->all())->delete();
                $deleted += $logs->count();
            });

        $this->info("已归档 {$exported} 条（删除 {$deleted} 条）审计日志到 {$path}");

        return self::SUCCESS;
    }
}
