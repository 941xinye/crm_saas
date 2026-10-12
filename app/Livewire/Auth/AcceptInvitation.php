<?php

namespace App\Livewire\Auth;

use App\Models\TenantInvitation;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;

/**
 * 接受租户邀请页面：通过 token 加载邀请，注册新账号或提示登录。
 *
 * 邀请链接通过邮件发送，点击后到达此页面。被邀请人无需已有账号。
 */
class AcceptInvitation extends Component
{
    public ?TenantInvitation $invitation = null;
    public string $token = '';
    public string $name = '';
    public string $password = '';
    public string $password_confirmation = '';

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->invitation = TenantInvitation::where('token', $token)->first();

        if (! $this->invitation) {
            return; // 视图展示"邀请不存在"
        }
    }

    public function register(): void
    {
        $this->validate([
            'name'     => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! $this->invitation || ! $this->invitation->isValid()) {
            session()->flash('error', '邀请已失效或已被接受');
            return;
        }

        // 检查邮箱是否已注册
        if (User::where('email', $this->invitation->email)->exists()) {
            session()->flash('error', '该邮箱已注册，请先登录后再接受邀请。');
            return;
        }

        // 创建用户：邮箱已通过邀请链接验证
        $user = User::create([
            'name'              => $this->name,
            'email'             => $this->invitation->email,
            'password'          => Hash::make($this->password),
            'tenant_id'         => $this->invitation->tenant_id,
            'role'              => $this->invitation->role,
            'is_platform_admin' => false,
            'email_verified_at' => now(), // 邀请链接本身验证了邮箱
        ]);

        // 标记邀请已接受
        $this->invitation->update([
            'accepted_at' => now(),
            'accepted_by' => $user->id,
        ]);

        Auth::login($user);
        if (request()->hasSession()) {
            request()->session()->regenerate();
        }

        $this->redirect(route('dashboard'), navigate: true);
    }

    public function render()
    {
        return view('auth.accept-invitation')->layout('layouts.guest');
    }
}
