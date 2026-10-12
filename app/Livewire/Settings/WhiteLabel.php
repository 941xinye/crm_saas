<?php

namespace App\Livewire\Settings;

use App\Services\Tenancy;
use App\Services\TenantMailer;
use Illuminate\Support\Facades\Crypt;
use Livewire\Component;
use Throwable;

/**
 * 白标 SMTP 邮件设置：租户配置自己的 SMTP 服务器与发件人身份，
 * 系统邮件（提醒/邀请）改从租户品牌发出。仅租户管理员可操作。
 *
 * 详见 docs/white-label.md。
 */
class WhiteLabel extends Component
{
    public string $smtpHost = '';
    public string $smtpPort = '587';
    public string $smtpEncryption = 'tls';
    public string $smtpUsername = '';
    /** 仅写入：留空表示保留已存密码；不回显已存密码 */
    public string $smtpPassword = '';
    public string $fromAddress = '';
    public string $fromName = '';

    public function mount(): void
    {
        $tenant = app(Tenancy::class)->current();
        $wl = $tenant?->settings['whitelabel'] ?? [];

        $this->smtpHost = $wl['smtp_host'] ?? '';
        $this->smtpPort = (string) ($wl['smtp_port'] ?? 587);
        $this->smtpEncryption = $wl['smtp_encryption'] ?? 'tls';
        $this->smtpUsername = $wl['smtp_username'] ?? '';
        $this->fromAddress = $wl['from_address'] ?? '';
        $this->fromName = $wl['from_name'] ?? '';
    }

    public function save(): void
    {
        $tenant = app(Tenancy::class)->current();

        if (! $tenant || auth()->user()?->role !== 'admin') {
            session()->flash('error', __('white_label.flash_only_admin'));

            return;
        }

        $this->validate([
            'smtpHost' => ['required', 'string', 'max:255'],
            'smtpPort' => ['required', 'integer', 'min:1', 'max:65535'],
            'smtpEncryption' => ['nullable', 'in:none,tls,ssl'],
            'smtpUsername' => ['nullable', 'string', 'max:255'],
            'smtpPassword' => ['nullable', 'string', 'max:255'],
            'fromAddress' => ['required', 'email', 'max:255'],
            'fromName' => ['nullable', 'string', 'max:100'],
        ]);

        $existing = $tenant->settings['whitelabel'] ?? [];

        $tenant->update(['settings' => [
            ...($tenant->settings ?? []),
            'whitelabel' => [
                'smtp_host' => $this->smtpHost,
                'smtp_port' => (int) $this->smtpPort,
                'smtp_encryption' => $this->smtpEncryption === 'none' ? null : $this->smtpEncryption,
                'smtp_username' => $this->smtpUsername ?: null,
                // 密码加密存储；留空保留原值
                'smtp_password' => filled($this->smtpPassword)
                    ? Crypt::encryptString($this->smtpPassword)
                    : ($existing['smtp_password'] ?? null),
                'from_address' => $this->fromAddress,
                'from_name' => $this->fromName ?: null,
            ],
        ]]);

        session()->flash('success', __('white_label.flash_saved'));
    }

    /**
     * 停用白标：移除配置，系统邮件恢复平台默认发件。
     */
    public function disable(): void
    {
        $tenant = app(Tenancy::class)->current();

        if (! $tenant || auth()->user()?->role !== 'admin') {
            session()->flash('error', __('white_label.flash_only_admin'));

            return;
        }

        $settings = $tenant->settings ?? [];
        unset($settings['whitelabel']);

        $tenant->update(['settings' => $settings]);
        session()->flash('success', __('white_label.flash_disabled'));

        // 重新加载表单（清空各字段）
        $this->mount();
    }

    /**
     * 发送测试邮件到当前管理员的邮箱；SMTP 失败时原样展示错误信息便于排查。
     */
    public function sendTest(): void
    {
        $tenant = app(Tenancy::class)->current();

        if (! $tenant || auth()->user()?->role !== 'admin') {
            session()->flash('error', __('white_label.flash_only_admin'));

            return;
        }

        if (! app(TenantMailer::class)->configured($tenant)) {
            session()->flash('error', __('white_label.flash_not_configured'));

            return;
        }

        try {
            app(TenantMailer::class)->sendTestMail($tenant, (string) auth()->user()->email);
            session()->flash('success', __('white_label.flash_test_sent', ['email' => auth()->user()->email]));
        } catch (Throwable $e) {
            session()->flash('error', __('white_label.flash_test_failed', ['error' => $e->getMessage()]));
        }
    }

    public function render()
    {
        $tenant = app(Tenancy::class)->current();

        return view('livewire.settings.white-label', [
            'tenant' => $tenant,
            'enabled' => app(TenantMailer::class)->configured($tenant),
        ])->layout('layouts.app', ['title' => __('white_label.title')]);
    }
}
