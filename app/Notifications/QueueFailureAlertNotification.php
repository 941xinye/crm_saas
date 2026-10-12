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
            ->subject("系统告警：发现 {$this->count} 个队列失败任务")
            ->greeting('您好！')
            ->line("过去 {$this->windowHours} 小时内新增 {$this->count} 个队列失败任务，请尽快排查。")
            ->line('排查命令：`php artisan queue:failed`（查看详情）/ `php artisan queue:retry all`（全部重试）。');
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'queue_failure',
            'message' => "过去 {$this->windowHours} 小时内新增 {$this->count} 个队列失败任务，请排查（queue:failed / queue:retry）",
            'count' => $this->count,
            'url' => route('platform.tenants'),
        ];
    }
}
