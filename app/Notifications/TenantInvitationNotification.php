<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * 租户邀请邮件通知：发至被邀请邮箱（被邀请人尚未注册）。
 */
class TenantInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $tenantName,
        private readonly string $role,
        private readonly string $acceptUrl,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $roleLabel = match ($this->role) {
            'admin' => '管理员',
            'manager' => '经理',
            'sales' => '销售',
            default => $this->role,
        };

        return (new MailMessage)
            ->subject("邀请您加入「{$this->tenantName}」团队")
            ->greeting("您好！")
            ->line("{$this->tenantName} 邀请您加入其团队，角色为：{$roleLabel}。")
            ->action('接受邀请', $this->acceptUrl)
            ->line('此邀请链接将在 7 天后失效。')
            ->line('如果您没有预期收到此邀请，请忽略此邮件。');
    }
}
