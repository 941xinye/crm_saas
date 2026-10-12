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
            ->subject("试用期即将结束：{$this->tenantName}")
            ->greeting('您好！')
            ->line("租户「{$this->tenantName}」的试用期将于 {$this->endsAt}（还剩 {$this->daysLeft} 天）结束。")
            ->action('查看套餐订阅', route('settings.subscription'))
            ->line('试用期结束后将无法新增数据，升级套餐可继续使用全部功能。');
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'trial_ending',
            'message' => "租户「{$this->tenantName}」试用期将于 {$this->endsAt} 结束（还剩 {$this->daysLeft} 天）",
            'tenant_id' => $this->tenantId,
            'tenant_name' => $this->tenantName,
            'ends_at' => $this->endsAt,
            'days_left' => $this->daysLeft,
            'url' => route('settings.subscription'),
        ];
    }
}
