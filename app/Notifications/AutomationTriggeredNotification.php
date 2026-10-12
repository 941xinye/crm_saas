<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * 自动化工作流触发通知（Phase 4）。
 */
class AutomationTriggeredNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $message,
        private readonly array $context = [],
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'message' => $this->message,
            'context' => $this->context,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
