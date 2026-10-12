<?php

namespace App\Livewire\Platform;

use App\Models\Tenant;
use App\Models\User;
use Livewire\Component;

class Tenants extends Component
{
    public string $search = '';

    protected $queryString = ['search'];

    public function toggleActive(int $id): void
    {
        // Tenant 模型不挂 TenantScope（它自身就是租户根），无需逃生舱
        $tenant = Tenant::findOrFail($id);
        $tenant->update(['is_active' => ! $tenant->is_active]);
        session()->flash('success', "租户「{$tenant->name}」状态已更新");
    }

    public function render()
    {
        $tenants = Tenant::query()
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%")->orWhere('slug', 'like', "%{$this->search}%"))
            ->withCount('users')
            ->orderByDesc('created_at')
            ->get();

        $totalUsers = User::whereNotNull('tenant_id')->count();
        $paidTenants = $tenants->where('plan', '!=', 'free')->count();

        return view('livewire.platform.tenants', [
            'tenants' => $tenants,
            'totalTenants' => $tenants->count(),
            'totalUsers' => $totalUsers,
            'paidTenants' => $paidTenants,
        ])->layout('layouts.app', ['title' => '租户管理']);
    }
}
