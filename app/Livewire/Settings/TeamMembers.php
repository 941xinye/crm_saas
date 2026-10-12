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
        session()->flash('success', __('settings_team.flash_role_updated', ['name' => $user->name, 'role' => $role]));
    }

    public function removeMember(int $userId): void
    {
        $user = $this->findTenantUser($userId);
        if ($user->id === auth()->id()) {
            session()->flash('error', __('settings_team.flash_cannot_remove_self'));
            return;
        }
        $user->delete();
        session()->flash('success', __('settings_team.flash_member_removed', ['name' => $user->name]));
    }

    /**
     * 邀请新成员：发邮件邀请，被邀请人通过链接注册并加入。
     */
    public function inviteMember(): void
    {
        $tenant = app(Tenancy::class)->current();

        if (! $tenant || auth()->user()->role !== 'admin') {
            session()->flash('error', __('settings_team.flash_unauthorized'));
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
            $this->addError('inviteEmail', __('settings_team.error_email_already_member'));
            return;
        }

        // 已有待接受邀请则阻止重复创建
        $pending = TenantInvitation::pending()
            ->where('tenant_id', $tenant->id)
            ->where('email', $this->inviteEmail)
            ->exists();
        if ($pending) {
            $this->addError('inviteEmail', __('settings_team.error_email_already_invited'));
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
        session()->flash('success', __('settings_team.flash_invitation_sent', ['email' => $invitation->email]));
    }

    /**
     * 取消待接受邀请。
     */
    public function cancelInvitation(int $invitationId): void
    {
        $tenant = app(Tenancy::class)->current();

        if (! $tenant || auth()->user()->role !== 'admin') {
            session()->flash('error', __('settings_team.flash_unauthorized'));
            return;
        }

        $invitation = TenantInvitation::pending()
            ->where('tenant_id', $tenant->id)
            ->findOrFail($invitationId);
        $email = $invitation->email;
        $invitation->delete();

        session()->flash('success', __('settings_team.flash_invitation_cancelled', ['email' => $email]));
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
        ])->layout('layouts.app', ['title' => __('settings_team.title')]);
    }
}
