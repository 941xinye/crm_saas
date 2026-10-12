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
            ->subject(__('notifications.activity_due.mail_subject', ['activity_subject' => $this->activitySubject]))
            ->greeting(__('notifications.greeting'))
            ->line(__('notifications.activity_due.mail_intro', ['scheduled_at' => $this->scheduledAt]))
            ->line("**{$this->activitySubject}**")
            ->action(__('notifications.activity_due.action'), $this->resourceUrl)
            ->line(__('notifications.activity_due.mail_footer'));
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'activity_due',
            'message' => __('notifications.activity_due.db_message', ['activity_subject' => $this->activitySubject, 'scheduled_at' => $this->scheduledAt]),
            'activity_id' => $this->activityId,
            'activity_subject' => $this->activitySubject,
            'scheduled_at' => $this->scheduledAt,
            'url' => $this->resourceUrl,
        ];
    }
}
