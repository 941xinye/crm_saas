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
            ->subject("商机跟进提醒：{$this->opportunityTitle}")
            ->greeting('您好！')
            ->line("您负责的商机已有 {$this->daysSinceLast} 天无任何活动记录：")
            ->line("**{$this->opportunityTitle}**")
            ->action('查看商机', $this->resourceUrl)
            ->line('建议尽快安排跟进活动，避免商机流失。');
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'opportunity_no_activity',
            'message' => "商机「{$this->opportunityTitle}」已有 {$this->daysSinceLast} 天无活动记录，建议跟进",
            'opportunity_id' => $this->opportunityId,
            'opportunity_title' => $this->opportunityTitle,
            'days_since_last' => $this->daysSinceLast,
            'url' => $this->resourceUrl,
        ];
    }
}
