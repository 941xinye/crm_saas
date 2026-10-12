<?php

namespace App\Notifications;

use App\Notifications\Concerns\ChannelsFromConfig;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * 活动到期提醒：scheduled_at 在未来 N 小时内、未完成的活动。
 */
class ActivityDueNotification extends Notification
{
    use Queueable, ChannelsFromConfig;

    public function __construct(
        private readonly int $activityId,
        private readonly string $activitySubject,
        private readonly string $scheduledAt,
        private readonly string $resourceUrl,
    ) {
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("活动到期提醒：{$this->activitySubject}")
            ->greeting('您好！')
            ->line("您有一个活动将于 {$this->scheduledAt} 到期：")
            ->line("**{$this->activitySubject}**")
            ->action('查看活动', $this->resourceUrl)
            ->line('请及时处理。');
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'activity_due',
            'message' => "活动「{$this->activitySubject}」将于 {$this->scheduledAt} 到期",
            'activity_id' => $this->activityId,
            'activity_subject' => $this->activitySubject,
            'scheduled_at' => $this->scheduledAt,
            'url' => $this->resourceUrl,
        ];
    }
}
