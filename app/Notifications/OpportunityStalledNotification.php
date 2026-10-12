<?php

namespace App\Notifications;

use App\Notifications\Concerns\ChannelsFromConfig;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * 商机停滞提醒：商机创建超过 N 天未更新的商机，推动跟进。
 */
class OpportunityStalledNotification extends Notification
{
    use Queueable, ChannelsFromConfig;

    public function __construct(
        private readonly int $opportunityId,
        private readonly string $opportunityTitle,
        private readonly string $createdDate,
        private readonly string $resourceUrl,
    ) {
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('notifications.opportunity_stalled.mail_subject', ['opportunity_title' => $this->opportunityTitle]))
            ->greeting(__('notifications.greeting'))
            ->line(__('notifications.opportunity_stalled.mail_intro', ['created_date' => $this->createdDate]))
            ->line("**{$this->opportunityTitle}**")
            ->action(__('notifications.opportunity_stalled.action'), $this->resourceUrl)
            ->line(__('notifications.opportunity_stalled.mail_footer'));
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'opportunity_stalled',
            'message' => __('notifications.opportunity_stalled.db_message', ['opportunity_title' => $this->opportunityTitle, 'created_date' => $this->createdDate]),
            'opportunity_id' => $this->opportunityId,
            'opportunity_title' => $this->opportunityTitle,
            'created_date' => $this->createdDate,
            'url' => $this->resourceUrl,
        ];
    }
}
