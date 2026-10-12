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
            ->subject("商机停滞提醒：{$this->opportunityTitle}")
            ->greeting('您好！')
            ->line("您负责的商机自 {$this->createdDate} 起未更新：")
            ->line("**{$this->opportunityTitle}**")
            ->action('查看商机', $this->resourceUrl)
            ->line('该商机已长期无进展，建议尽快跟进。');
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'opportunity_stalled',
            'message' => "商机「{$this->opportunityTitle}」自 {$this->createdDate} 起未更新，建议跟进",
            'opportunity_id' => $this->opportunityId,
            'opportunity_title' => $this->opportunityTitle,
            'created_date' => $this->createdDate,
            'url' => $this->resourceUrl,
        ];
    }
}
