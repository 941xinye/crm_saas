<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class Login extends Component
{
    public string $email = '';
    public string $password = '';
    public bool $remember = false;

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['boolean'],
        ];
    }

    public function login(): void
    {
        $this->validate();

        $throttleKey = Str::transliterate(Str::lower($this->email) . '|' . request()->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                'email' => '登录尝试过多，请在 ' . RateLimiter::availableIn($throttleKey) . ' 秒后重试。',
            ]);
        }

        $user = \App\Models\User::where('email', $this->email)->first();

        if (! $user || ! Hash::check($this->password, $user->password)) {
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages([
                'email' => '邮箱或密码不正确。',
            ]);
        }

        // 已启用两步验证：会话仅暂存用户 ID，待挑战页校验动态码后才建立登录态
        if ($user->hasTwoFactorEnabled()) {
            RateLimiter::clear($throttleKey);
            session([
                '2fa:user:id' => $user->id,
                '2fa:remember' => $this->remember,
            ]);
            session()->regenerate();

            $this->redirect(route('two-factor.challenge'), navigate: true);

            return;
        }

        Auth::login($user, $this->remember);

        RateLimiter::clear($throttleKey);
        session()->regenerate();

        $this->redirect(route('dashboard'), navigate: true);
    }

    public function render()
    {
        return view('auth.login')->layout('layouts.guest');
    }
}
