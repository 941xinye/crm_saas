<?php

namespace App\Services;

use App\Jobs\ImportCsvJob;
use App\Models\CsvImportJob;
use Illuminate\Support\Facades\Storage;

/**
 * CSV 大文件后台导入派发器（P1）：
 * 行数超过 crm.import.async_threshold 的导入文件不再同步逐行处理，
 * 而是落盘（私有磁盘）→ 建 csv_import_jobs 进度记录 → 派发 ImportCsvJob。
 *
 * 小文件（<= 阈值）仍走原同步路径：即时行级错误回显体验更好。
 */
class CsvAsyncImport
{
    public function __construct(private readonly CsvService $csv)
    {
    }

    public function shouldQueue(string $csvContent): bool
    {
        return $this->csv->exceedsAsyncThreshold($csvContent);
    }

    public function dispatch(string $modelClass, string $csvContent, string $originalName): CsvImportJob
    {
        $record = CsvImportJob::create([
            'user_id' => auth()->id(),
            'resource' => $modelClass,
            'original_name' => $originalName,
            'path' => '',
            'status' => 'pending',
            'total_rows' => $this->csv->countRows($csvContent),
        ]);

        $path = "imports/{$record->id}.csv";
        Storage::disk('local')->put($path, $csvContent);
        $record->update(['path' => $path]);

        ImportCsvJob::dispatch($record->id);

        return $record;
    }
}
