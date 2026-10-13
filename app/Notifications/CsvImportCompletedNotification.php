<?php

namespace App\Notifications;

use App\Models\CsvImportJob;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * CSV 后台导入完成通知（P1）：仅站内信。
 * 有失败行时携带失败明细下载链接（发起人本人可访问）。
 */
class CsvImportCompletedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly CsvImportJob $record,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $resource = class_basename($this->record->resource);

        if ($this->record->status === 'failed') {
            return [
                'type' => 'csv_import',
                'message' => __('notifications.csv_import.failed', ['resource' => $resource]),
                'url' => null,
            ];
        }

        $failed = $this->record->failed_rows;

        return [
            'type' => 'csv_import',
            'message' => $failed > 0
                ? __('notifications.csv_import.done_with_errors', [
                    'resource' => $resource,
                    'created' => $this->record->created_count,
                    'failed' => $failed,
                ])
                : __('notifications.csv_import.done', [
                    'resource' => $resource,
                    'created' => $this->record->created_count,
                ]),
            'url' => $failed > 0 ? route('csv-imports.errors', ['importJob' => $this->record->id]) : null,
        ];
    }
}
