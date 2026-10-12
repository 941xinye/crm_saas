<?php

namespace App\Notifications;

use App\Notifications\Concerns\ChannelsFromConfig;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * 试用期即将到期提醒：租户 trial_ends_at 在未来 N 天内。
 * 收件人：租户全部 admin + 平台超管（is_platform_admin）。
 * 防重复：以 tenant_id 为对象键走 ReminderService 冷却期去重。
 */
class TrialEndingNotification extends Notification
{
    use Queueable, ChannelsFromConfig;

    public function __construct(
        private readonly int $tenantId,
        private readonly string $tenantName,
        private readonly string $endsAt,
        private readonly int $daysLeft,
    ) {
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('notifications.trial_ending.mail_subject', ['tenant_name' => $this->tenantName]))
            ->greeting(__('notifications.greeting'))
            ->line(__('notifications.trial_ending.mail_body', [
                'tenant_name' => $this->tenantName,
                'ends_at' => $this->endsAt,
                'days_left' => $this->daysLeft,
            ]))
            ->action(__('notifications.trial_ending.action'), route('settings.subscription'))
            ->line(__('notifications.trial_ending.mail_footer'));
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'trial_ending',
            'message' => __('notifications.trial_ending.db_message', [
                'tenant_name' => $this->tenantName,
                'ends_at' => $this->endsAt,
                'days_left' => $this->daysLeft,
            ]),
            'tenant_id' => $this->tenantId,
            'tenant_name' => $this->tenantName,
            'ends_at' => $this->endsAt,
            'days_left' => $this->daysLeft,
            'url' => route('settings.subscription'),
        ];
    }
}
