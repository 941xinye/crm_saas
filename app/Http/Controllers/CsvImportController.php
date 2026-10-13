<?php

namespace App\Http\Controllers;

use App\Models\CsvImportJob;

/**
 * CSV 后台导入：失败明细下载（P1）。
 * 仅发起人本人可下载；路由模型绑定经 TenantScope 保证租户归属。
 */
class CsvImportController extends Controller
{
    public function errors(CsvImportJob $importJob)
    {
        abort_unless(auth()->id() === $importJob->user_id, 403);
        abort_if($importJob->failed_rows === 0, 404);

        $escape = fn ($v) => '"'.str_replace('"', '""', (string) $v).'"';

        $lines = [implode(',', [($escape)(__('services.csv_error_row')), ($escape)(__('services.csv_error_message'))])];
        foreach ($importJob->errors ?? [] as $row => $message) {
            $lines[] = implode(',', [($escape)($row), ($escape)($message)]);
        }

        return response("\xEF\xBB\xBF".implode("\n", $lines))
            ->header('Content-Type', 'text/csv; charset=utf-8')
            ->header('Content-Disposition', "attachment; filename=\"import_errors_{$importJob->id}.csv\"");
    }
}
