<?php

namespace App\Notifications;

use App\Notifications\Concerns\ChannelsFromConfig;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * 商机无活动提醒：商机创建超过 N 天且在此期间无任何活动记录，推动跟进。
 */
class OpportunityNoActivityNotification extends Notification
{
    use Queueable, ChannelsFromConfig;

    public function __construct(
        private readonly int $opportunityId,
        private readonly string $opportunityTitle,
        private readonly int $daysSinceLast,
        private readonly string $resourceUrl,
    ) {
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('notifications.opportunity_no_activity.mail_subject', ['opportunity_title' => $this->opportunityTitle]))
            ->greeting(__('notifications.greeting'))
            ->line(__('notifications.opportunity_no_activity.mail_intro', ['days_since_last' => $this->daysSinceLast]))
            ->line("**{$this->opportunityTitle}**")
            ->action(__('notifications.opportunity_no_activity.action'), $this->resourceUrl)
            ->line(__('notifications.opportunity_no_activity.mail_footer'));
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'opportunity_no_activity',
            'message' => __('notifications.opportunity_no_activity.db_message', ['opportunity_title' => $this->opportunityTitle, 'days_since_last' => $this->daysSinceLast]),
            'opportunity_id' => $this->opportunityId,
            'opportunity_title' => $this->opportunityTitle,
            'days_since_last' => $this->daysSinceLast,
            'url' => $this->resourceUrl,
        ];
    }
}
