<?php

namespace App\Notifications;

use App\Notifications\Concerns\ChannelsFromConfig;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * 队列失败告警：crm:health-check 发现新的 failed_jobs 时通知平台超管。
 * 站内信直达（平台超管无租户上下文，routes 可用 platform.tenants），
 * 邮件/机器人渠道复用 crm.reminder.channels 配置。
 */
class QueueFailureAlertNotification extends Notification
{
    use Queueable, ChannelsFromConfig;

    public function __construct(
        private readonly int $count,
        private readonly string $windowHours,
    ) {
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('notifications.queue_failure.mail_subject', ['count' => $this->count]))
            ->greeting(__('notifications.greeting'))
            ->line(__('notifications.queue_failure.mail_body', ['window_hours' => $this->windowHours, 'count' => $this->count]))
            ->line(__('notifications.queue_failure.mail_commands'));
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'queue_failure',
            'message' => __('notifications.queue_failure.db_message', ['window_hours' => $this->windowHours, 'count' => $this->count]),
            'count' => $this->count,
            'url' => route('platform.tenants'),
        ];
    }
}
