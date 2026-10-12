<?php

namespace App\Notifications;

use App\Notifications\Concerns\ChannelsFromConfig;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * 商机成交日提醒：expected_close_date 在未来 N 天内的商机。
 */
class OpportunityCloseDateNotification extends Notification
{
    use Queueable, ChannelsFromConfig;

    public function __construct(
        private readonly int $opportunityId,
        private readonly string $opportunityTitle,
        private readonly string $expectedCloseDate,
        private readonly string $resourceUrl,
    ) {
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('notifications.opportunity_close_date.mail_subject', ['opportunity_title' => $this->opportunityTitle]))
            ->greeting(__('notifications.greeting'))
            ->line(__('notifications.opportunity_close_date.mail_intro', ['expected_close_date' => $this->expectedCloseDate]))
            ->line("**{$this->opportunityTitle}**")
            ->action(__('notifications.opportunity_close_date.action'), $this->resourceUrl)
            ->line(__('notifications.opportunity_close_date.mail_footer'));
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'opportunity_close_date',
            'message' => __('notifications.opportunity_close_date.db_message', ['opportunity_title' => $this->opportunityTitle, 'expected_close_date' => $this->expectedCloseDate]),
            'opportunity_id' => $this->opportunityId,
            'opportunity_title' => $this->opportunityTitle,
            'expected_close_date' => $this->expectedCloseDate,
            'url' => $this->resourceUrl,
        ];
    }
}
