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
            ->subject("商机成交提醒：{$this->opportunityTitle}")
            ->greeting('您好！')
            ->line("您负责的商机预计于 {$this->expectedCloseDate} 成交：")
            ->line("**{$this->opportunityTitle}**")
            ->action('查看商机', $this->resourceUrl)
            ->line('请跟进推进，确保按期成交。');
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'opportunity_close_date',
            'message' => "商机「{$this->opportunityTitle}」预计于 {$this->expectedCloseDate} 成交",
            'opportunity_id' => $this->opportunityId,
            'opportunity_title' => $this->opportunityTitle,
            'expected_close_date' => $this->expectedCloseDate,
            'url' => $this->resourceUrl,
        ];
    }
}
