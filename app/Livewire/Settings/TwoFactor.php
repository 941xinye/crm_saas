<?php

namespace App\Livewire\Settings;

use App\Services\TwoFactorService;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;

class TwoFactor extends Component
{
    public string $code = '';

    /** 待确认的新密钥（enable 后、confirm 前），确认成功后置空 */
    public ?string $pendingSecret = null;

    public ?string $otpauthUri = null;

    /** 新恢复码明文，仅生成后一次性展示 */
    public ?array $recoveryCodes = null;

    public string $password = '';

    /**
     * 第一步：生成待确认密钥并展示 otpauth URI。
     * 确认前用户可反复重新生成（覆盖旧密钥），不影响已启用的 2FA。
     */
    public function enable(): void
    {
        $user = auth()->user();

        if ($user->hasTwoFactorEnabled()) {
            $this->addError('code', __('settings_security.flash_already_enabled'));
            return;
        }

        $service = app(TwoFactorService::class);
        $this->pendingSecret = $service->generateSecret();

        $user->forceFill(['two_factor_secret' => $this->pendingSecret])->save();

        $this->otpauthUri = $service->otpauthUri($this->pendingSecret, $user->email);
    }

    /**
     * 第二步：校验动态码确认启用，随后生成恢复码（明文仅展示一次）。
     */
    public function confirm(): void
    {
        $this->validate(['code' => ['required', 'string']]);

        $user = auth()->user();

        if (! $this->pendingSecret || $user->hasTwoFactorEnabled()) {
            $this->addError('code', __('settings_security.flash_no_pending'));
            return;
        }

        $service = app(TwoFactorService::class);

        if (! $service->verify($this->pendingSecret, $this->code)) {
            $this->addError('code', __('settings_security.flash_code_invalid'));
            return;
        }

        $plainCodes = $service->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => $service->hashRecoveryCodes($plainCodes),
        ])->save();

        $this->recoveryCodes = $plainCodes;
        $this->code = '';
    }

    /**
     * 关闭两步验证（需验证当前密码）。
     */
    public function disable(): void
    {
        $user = auth()->user();

        if (! Hash::check($this->password, $user->password)) {
            $this->addError('password', __('settings_security.flash_password_wrong'));
            return;
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        $this->password = '';
    }

    /**
     * 重新生成恢复码（旧码全部失效，需验证当前密码）。
     */
    public function regenerate(): void
    {
        $user = auth()->user();

        if (! $user->hasTwoFactorEnabled()) {
            return;
        }

        if (! Hash::check($this->password, $user->password)) {
            $this->addError('password', __('settings_security.flash_password_wrong'));
            return;
        }

        $service = app(TwoFactorService::class);
        $plainCodes = $service->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_recovery_codes' => $service->hashRecoveryCodes($plainCodes),
        ])->save();

        $this->recoveryCodes = $plainCodes;
        $this->password = '';
    }

    public function render()
    {
        return view('livewire.settings.two-factor')->layout('layouts.app', ['title' => __('settings_security.title')]);
    }
}
