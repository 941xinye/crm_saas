<?php

namespace App\Livewire\Settings;

use App\Models\TenantInvitation;
use App\Models\User;
use App\Notifications\TenantInvitationNotification;
use App\Services\Tenancy;
use Illuminate\Support\Facades\Notification;
use Livewire\Component;

class TeamMembers extends Component
{
    public string $inviteEmail = '';
    public string $inviteRole = 'sales';

    public function updateRole(int $userId, string $role): void
    {
        $user = $this->findTenantUser($userId);
        $user->update(['role' => $role]);
        session()->flash('success', "已将 {$user->name} 的角色更新为 {$role}");
    }

    public function removeMember(int $userId): void
    {
        $user = $this->findTenantUser($userId);
        if ($user->id === auth()->id()) {
            session()->flash('error', '您不能移除自己');
            return;
        }
        $user->delete();
        session()->flash('success', "已移除成员 {$user->name}");
    }

    /**
     * 邀请新成员：发邮件邀请，被邀请人通过链接注册并加入。
     */
    public function inviteMember(): void
    {
        $tenant = app(Tenancy::class)->current();

        if (! $tenant || auth()->user()->role !== 'admin') {
            session()->flash('error', '无权执行此操作');
            return;
        }

        $this->validate([
            'inviteEmail' => ['required', 'email', 'max:255'],
            'inviteRole'  => ['required', 'in:admin,manager,sales'],
        ]);

        // 已是本租户成员则阻止重复邀请
        $existing = User::where('email', $this->inviteEmail)
            ->where('tenant_id', $tenant->id)
            ->exists();
        if ($existing) {
            $this->addError('inviteEmail', '该邮箱已是本团队成员');
            return;
        }

        // 已有待接受邀请则阻止重复创建
        $pending = TenantInvitation::pending()
            ->where('tenant_id', $tenant->id)
            ->where('email', $this->inviteEmail)
            ->exists();
        if ($pending) {
            $this->addError('inviteEmail', '该邮箱已有待接受的邀请');
            return;
        }

        $invitation = TenantInvitation::createFor($tenant, $this->inviteEmail, $this->inviteRole);

        Notification::route('mail', $this->inviteEmail)
            ->notify(new TenantInvitationNotification(
                tenantName: $tenant->name,
                role: $this->inviteRole,
                acceptUrl: route('invitation.accept', $invitation->token),
            ));

        $this->reset('inviteEmail', 'inviteRole');
        session()->flash('success', "邀请已发送至 {$invitation->email}");
    }

    /**
     * 取消待接受邀请。
     */
    public function cancelInvitation(int $invitationId): void
    {
        $tenant = app(Tenancy::class)->current();

        if (! $tenant || auth()->user()->role !== 'admin') {
            session()->flash('error', '无权执行此操作');
            return;
        }

        $invitation = TenantInvitation::pending()
            ->where('tenant_id', $tenant->id)
            ->findOrFail($invitationId);
        $email = $invitation->email;
        $invitation->delete();

        session()->flash('success', "已取消对 {$email} 的邀请");
    }

    /**
     * User 模型未挂 TenantScope，必须显式限定当前租户，
     * 防止按 ID 跨租户提权/删除其他租户的用户。
     */
    private function findTenantUser(int $userId): User
    {
        return app(Tenancy::class)->users()->findOrFail($userId);
    }

    public function render()
    {
        $tenant = app(Tenancy::class)->current();
        $users = $tenant ? $tenant->users()->orderBy('name')->get() : collect();

        $pendingInvitations = $tenant
            ? TenantInvitation::pending()
                ->where('tenant_id', $tenant->id)
                ->orderByDesc('created_at')
                ->get()
            : collect();

        return view('livewire.settings.team-members', [
            'users'               => $users,
            'pendingInvitations' => $pendingInvitations,
        ])->layout('layouts.app', ['title' => '团队成员']);
    }
}
