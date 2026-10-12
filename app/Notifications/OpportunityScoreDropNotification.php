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
            ->subject(__('notifications.opportunity_score_drop.mail_subject', ['title' => $this->title]))
            ->greeting(__('notifications.greeting'))
            ->line(__('notifications.opportunity_score_drop.mail_body', [
                'title' => $this->title,
                'old_score' => $this->oldScore,
                'new_score' => $this->newScore,
            ]))
            ->action(__('notifications.opportunity_score_drop.action'), route('opportunities.show', $this->opportunityId));
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'score_drop',
            'message' => __('notifications.opportunity_score_drop.db_message', [
                'title' => $this->title,
                'old_score' => $this->oldScore,
                'new_score' => $this->newScore,
            ]),
            'opportunity_id' => $this->opportunityId,
            'old_score' => $this->oldScore,
            'new_score' => $this->newScore,
            'url' => route('opportunities.show', $this->opportunityId),
        ];
    }
}
