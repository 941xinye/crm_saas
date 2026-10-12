<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * 租户白标 SMTP 邮件（White-label Email）：
 * 租户在 tenants.settings['whitelabel'] 配置自己的 SMTP 服务器与发件人身份
 * （密码经 Crypt::encryptString 加密存储），提醒/邀请等系统邮件改从租户品牌发出。
 *
 * 机制：运行时注册 'tenant_whitelabel' mailer 并切换 mail.default；
 * 未配置（或配置不完整）时复位平台默认 —— 长驻 worker（queue/Octane）下
 * 前一个租户的 SMTP 配置不得泄漏给下一个租户。
 */
class TenantMailer
{
    /** 租户白标 mailer 的运行时名称 */
    public const MAILER = 'tenant_whitelabel';

    /** 平台默认 mailer 名：进程内首次激活时快照，之后始终复位到该值 */
    private static ?string $platformDefault = null;

    /**
     * 租户是否已配置白标 SMTP（host 与 from_address 为最低要求）。
     */
    public function configured(?Tenant $tenant): bool
    {
        $wl = $tenant?->settings['whitelabel'] ?? null;

        return is_array($wl)
            && filled($wl['smtp_host'] ?? null)
            && filled($wl['from_address'] ?? null);
    }

    /**
     * 按租户激活/复位运行时邮件配置。中间件与提醒命令在发送前调用；
     * 对未配置租户调用即等价于复位（graceful degradation）。
     */
    public function activate(?Tenant $tenant): void
    {
        if (! $this->configured($tenant)) {
            $this->resetToPlatformDefault();

            return;
        }

        self::$platformDefault ??= (string) config('mail.default');

        /** @var array<string, mixed> $wl */
        $wl = $tenant->settings['whitelabel'];

        config()->set('mail.mailers.' . self::MAILER, [
            'transport' => 'smtp',
            'host' => $wl['smtp_host'],
            'port' => (int) ($wl['smtp_port'] ?? 587),
            // tls/ssl 之外的值（含 none）视为不加密
            'encryption' => in_array($wl['smtp_encryption'] ?? null, ['tls', 'ssl'], true) ? $wl['smtp_encryption'] : null,
            'username' => $wl['smtp_username'] ?? null,
            'password' => $this->resolvePassword($wl),
            'timeout' => null,
            // 白标发件人：覆盖平台全局 mail.from
            'from' => [
                'address' => $wl['from_address'],
                'name' => $wl['from_name'] ?? $tenant->name,
            ],
        ]);
        config()->set('mail.default', self::MAILER);
    }

    /**
     * 以租户白标身份发送测试邮件到指定邮箱，发送后立即复位平台默认。
     * SMTP 连接失败时抛出原始异常，由调用方（Settings\WhiteLabel）捕获展示。
     */
    public function sendTestMail(Tenant $tenant, string $toEmail): void
    {
        $this->activate($tenant);

        try {
            Mail::raw(__('white_label.test_mail_body'), function ($message) use ($toEmail) {
                $message->to($toEmail)->subject(__('white_label.test_mail_subject'));
            });
        } finally {
            $this->resetToPlatformDefault();
        }
    }

    private function resetToPlatformDefault(): void
    {
        if (self::$platformDefault !== null) {
            config()->set('mail.default', self::$platformDefault);
        }

        config()->set('mail.mailers.' . self::MAILER, null);
    }

    /**
     * 解密存储的 SMTP 密码；解密失败（如 APP_KEY 轮换）视为未配置密码而非中断发送。
     */
    private function resolvePassword(array $wl): ?string
    {
        $stored = $wl['smtp_password'] ?? null;

        if (blank($stored)) {
            return null;
        }

        try {
            return Crypt::decryptString($stored);
        } catch (Throwable) {
            return null;
        }
    }
}
