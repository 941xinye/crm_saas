<?php

namespace App\Livewire\Concerns;

use App\Services\CsvAsyncImport;

/**
 * CSV 大文件后台导入（P1）：各列表页 import() 前置调用。
 * 行数超过 crm.import.async_threshold 时落盘转 ImportCsvJob，
 * 返回 true 表示已转后台（组件应直接 return）；false 走原同步路径。
 */
trait WithCsvAsyncImport
{
    public function queueLargeImport(string $modelClass): bool
    {
        if (! $this->importFile) {
            return false;
        }

        $dispatcher = app(CsvAsyncImport::class);
        $content = $this->importFile->get();

        if (! $dispatcher->shouldQueue($content)) {
            return false;
        }

        $record = $dispatcher->dispatch($modelClass, $content, $this->importFile->getClientOriginalName());

        $this->importFile = null;
        session()->flash('info', __('common.csv_import_queued', ['rows' => $record->total_rows]));

        return true;
    }
}
