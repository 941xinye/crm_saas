<?php

namespace App\Jobs;

use App\Models\CsvImportJob;
use App\Notifications\CsvImportCompletedNotification;
use App\Services\CsvService;
use App\Services\Tenancy;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

/**
 * CSV 大文件后台导入（P1）：
 * 读取私有磁盘上的上传文件，复用 CsvService::import 逐行导入，
 * 每 25 行回写一次进度（processed_rows），完成后站内信通知发起人并清理文件。
 *
 * 队列上下文无租户信息（Tenancy 为 null），显式 setTenant 注入并在
 * finally 复位 —— 长驻 worker 下不得泄漏到下一个任务（与白标 SMTP 同一约定）。
 */
class ImportCsvJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // 导入非幂等（逐行 create），失败重试会产生重复数据 —— 不重试，
    // 失败状态与原因落 csv_import_jobs 并站内信告知发起人
    public int $tries = 1;

    public function __construct(
        private readonly int $importJobId,
    ) {
    }

    public function jobId(): int
    {
        return $this->importJobId;
    }

    public function handle(CsvService $csv): void
    {
        $record = CsvImportJob::query()->find($this->importJobId);

        if (! $record || $record->status === 'completed') {
            return;
        }

        $tenancy = app(Tenancy::class);
        $tenancy->setTenant($record->tenant);
        $record->update(['status' => 'processing']);

        try {
            $content = Storage::disk('local')->get($record->path);

            $lastFlush = 0;
            $result = $csv->import($record->resource, $content, $record->tenant, function (int $processed) use ($record, &$lastFlush): void {
                if ($processed - $lastFlush >= 25) {
                    $record->update(['processed_rows' => $processed]);
                    $lastFlush = $processed;
                }
            });

            $record->update([
                'status' => 'completed',
                'processed_rows' => $record->total_rows,
                'created_count' => $result['created'],
                'skipped_rows' => $result['skipped'] ?? 0,
                'duplicate_rows' => $result['duplicates'] ?? 0,
                'failed_rows' => count($result['errors']),
                'errors' => $result['errors'],
            ]);

            Storage::disk('local')->delete($record->path);

            $record->user?->notify(new CsvImportCompletedNotification($record->fresh()));
        } catch (\Throwable $e) {
            report($e);

            $record->update([
                'status' => 'failed',
                'failed_rows' => 1,
                'errors' => [__('services.csv_import_aborted', ['reason' => $e->getMessage()])],
            ]);
            $record->user?->notify(new CsvImportCompletedNotification($record->fresh()));
        } finally {
            $tenancy->setTenant(null);
        }
    }
}
