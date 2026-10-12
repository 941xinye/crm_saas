<?php

namespace App\Livewire\Auth;

use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * 两步验证挑战页：登录密码校验通过但用户启用了 2FA 时，
 * 会话中仅暂存 2fa:user:id（未建立完整登录态），在此校验动态码/恢复码后才真正登录。
 */
class TwoFactorChallenge extends Component
{
    public string $code = '';

    public function mount(): void
    {
        if (! session()->has('2fa:user:id')) {
            $this->redirect(route('login'), navigate: true);
        }
    }

    public function verify(): void
    {
        $this->validate(['code' => ['required', 'string']]);

        $user = User::find(session('2fa:user:id'));

        if (! $user || ! $user->hasTwoFactorEnabled()) {
            $this->logoutPending();
            $this->redirect(route('login'), navigate: true);

            return;
        }

        $throttleKey = '2fa:' . $user->id;

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                'code' => __('auth.too_many_code_attempts', ['seconds' => RateLimiter::availableIn($throttleKey)]),
            ]);
        }

        $service = app(TwoFactorService::class);
        $code = trim($this->code);
        $valid = $service->verify($user->two_factor_secret, $code);
        $usedRecoveryCode = false;

        // 非 6 位数字时尝试恢复码（xxxx-xxxx 格式，bcrypt 比对，命中后用后即焚）
        if (! $valid && preg_match('/^[\da-f]{4}-[\da-f]{4}$/i', $code)) {
            $codes = $user->two_factor_recovery_codes ?? [];

            foreach ($codes as $index => $hashed) {
                if (Hash::check($code, $hashed)) {
                    unset($codes[$index]);
                    $user->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();
                    $valid = true;
                    $usedRecoveryCode = true;
                    break;
                }
            }
        }

        if (! $valid) {
            RateLimiter::hit($throttleKey, 300);

            throw ValidationException::withMessages(['code' => __('auth.code_invalid')]);
        }

        RateLimiter::clear($throttleKey);

        $remember = (bool) session('2fa:remember', false);
        $this->logoutPending();

        Auth::login($user, $remember);
        session()->regenerate();

        $remaining = count($user->two_factor_recovery_codes ?? []);

        if ($usedRecoveryCode && $remaining > 0 && $remaining <= 2) {
            session()->flash('warning', __('auth.recovery_codes_low', ['remaining' => $remaining]));
        }

        $this->redirect(route('dashboard'), navigate: true);
    }

    /**
     * 放弃本次登录，返回登录页。
     */
    public function cancel(): void
    {
        $this->logoutPending();
        $this->redirect(route('login'), navigate: true);
    }

    private function logoutPending(): void
    {
        session()->forget(['2fa:user:id', '2fa:remember']);
    }

    public function render()
    {
        return view('auth.two-factor-challenge')->layout('layouts.guest');
    }
}
