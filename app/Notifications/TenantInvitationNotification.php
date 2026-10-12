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
            'admin' => __('notifications.role_admin'),
            'manager' => __('notifications.role_manager'),
            'sales' => __('notifications.role_sales'),
            default => $this->role,
        };

        return (new MailMessage)
            ->subject(__('notifications.tenant_invitation.subject', ['tenant_name' => $this->tenantName]))
            ->greeting(__('notifications.greeting'))
            ->line(__('notifications.tenant_invitation.body', ['tenant_name' => $this->tenantName, 'role' => $roleLabel]))
            ->action(__('notifications.tenant_invitation.action'), $this->acceptUrl)
            ->line(__('notifications.tenant_invitation.expire_notice'))
            ->line(__('notifications.tenant_invitation.ignore_notice'));
    }
}
