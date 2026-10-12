<?php

namespace App\Notifications;

use App\Notifications\Concerns\ChannelsFromConfig;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * 商机评分骤降提醒：刷新评分时发现降幅超过 crm.scoring.drop_threshold。
 * 防重复：以 opportunity_id 为对象键走冷却期去重。
 */
class OpportunityScoreDropNotification extends Notification
{
    use Queueable, ChannelsFromConfig;

    public function __construct(
        private readonly int $opportunityId,
        private readonly string $title,
        private readonly int $oldScore,
        private readonly int $newScore,
    ) {
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("商机评分骤降：{$this->title}")
            ->greeting('您好！')
            ->line("商机「{$this->title}」的智能评分由 {$this->oldScore} 降至 {$this->newScore}，可能存在成交风险，建议尽快跟进。")
            ->action('查看商机', route('opportunities.show', $this->opportunityId));
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'score_drop',
            'message' => "商机「{$this->title}」评分由 {$this->oldScore} 降至 {$this->newScore}，建议尽快跟进",
            'opportunity_id' => $this->opportunityId,
            'old_score' => $this->oldScore,
            'new_score' => $this->newScore,
            'url' => route('opportunities.show', $this->opportunityId),
        ];
    }
}
