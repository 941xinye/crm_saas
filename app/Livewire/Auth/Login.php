<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Auth;
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

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages([
                'email' => '邮箱或密码不正确。',
            ]);
        }

        RateLimiter::clear($throttleKey);
        request()->session()->regenerate();

        $this->redirect(route('dashboard'), navigate: true);
    }

    public function render()
    {
        return view('auth.login')->layout('layouts.guest');
    }
}
