<?php

namespace App\Livewire\Settings;

use App\Services\Tenancy;
use App\Models\User;
use Livewire\Component;

class TeamMembers extends Component
{
    public function updateRole(int $userId, string $role): void
    {
        $user = User::findOrFail($userId);
        $user->update(['role' => $role]);
        session()->flash('success', "已将 {$user->name} 的角色更新为 {$role}");
    }

    public function removeMember(int $userId): void
    {
        $user = User::findOrFail($userId);
        if ($user->id === auth()->id()) {
            session()->flash('error', '您不能移除自己');
            return;
        }
        $user->delete();
        session()->flash('success', "已移除成员 {$user->name}");
    }

    public function render()
    {
        $tenant = app(Tenancy::class)->current();
        $users = $tenant ? $tenant->users()->orderBy('name')->get() : collect();

        return view('livewire.settings.team-members', [
            'users' => $users,
        ])->layout('layouts.app', ['title' => '团队成员']);
    }
}
