<?php

namespace App\Livewire\Auth;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;

class Register extends Component
{
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';
    public string $tenant_name = '';

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'tenant_name' => ['required', 'string', 'max:255'],
        ];
    }

    public function register(): void
    {
        $this->validate();

        $tenantSlug = \Illuminate\Support\Str::slug($this->tenant_name) . '-' . \Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(6));
        $tenant = Tenant::create([
            'name' => $this->tenant_name,
            'slug' => $tenantSlug,
            'plan' => 'free',
            'is_active' => true,
            'trial_ends_at' => now()->addDays((int) config('plans.free.trial_days', 14)),
        ]);

        $user = User::create([
            'name' => $this->name,
            'email' => $this->email,
            'password' => Hash::make($this->password),
            'tenant_id' => $tenant->id,
            'role' => 'admin',
            'is_platform_admin' => false,
        ]);

        // 触发 Registered 事件，框架监听器自动发送邮箱验证通知
        event(new \Illuminate\Auth\Events\Registered($user));

        Auth::login($user);
        // 测试/控制台等无会话环境跳过重建（生产 Web 请求必有会话）
        if (request()->hasSession()) {
            request()->session()->regenerate();
        }

        // 未验证邮箱时跳转到验证提示页，已验证（测试环境）直接进仪表板
        if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail()) {
            $this->redirect(route('verification.notice'), navigate: true);
        } else {
            $this->redirect(route('dashboard'), navigate: true);
        }
    }

    public function render()
    {
        return view('auth.register')->layout('layouts.guest');
    }
}
