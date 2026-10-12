<?php

namespace App\Livewire\Platform;

use App\Models\Tenant;
use App\Models\User;
use App\Services\PlanService;
use App\Services\TenantDataService;
use Livewire\Component;

class Tenants extends Component
{
    public string $search = '';

    protected $queryString = ['search'];

    /**
     * 导出租户全部数据（ZIP，GDPR 数据可携带权）。
     */
    public function exportData(int $tenantId)
    {
        $tenant = Tenant::findOrFail($tenantId);

        return response()->download(app(TenantDataService::class)->exportZip($tenant));
    }

    /**
     * 清空租户业务数据与个人数据（GDPR 删除权，审计日志合规保留）。
     */
    public function purgeData(int $tenantId): void
    {
        $tenant = Tenant::findOrFail($tenantId);
        $counts = app(TenantDataService::class)->purgeData($tenant);

        session()->flash('success', '已清空「' . $tenant->name . '」业务数据：'
            . collect($counts)->filter()->map(fn ($count, $table) => "{$table} {$count} 条")->implode('，'));
    }

    public function toggleActive(int $id): void
    {
        // Tenant 模型不挂 TenantScope（它自身就是租户根），无需逃生舱
        $tenant = Tenant::findOrFail($id);
        $tenant->update(['is_active' => ! $tenant->is_active]);
        session()->flash('success', "租户「{$tenant->name}」状态已更新");
    }

    public function changePlan(int $id, string $plan): void
    {
        if (! app(PlanService::class)->exists($plan)) {
            session()->flash('error', "未知套餐：{$plan}");
            return;
        }

        $tenant = Tenant::findOrFail($id);
        $tenant->update([
            'plan' => $plan,
            // 付费套餐无试用期限制；降回免费立即结束试用（需升级才能新增数据）
            'trial_ends_at' => $plan === 'free' ? now() : null,
        ]);

        session()->flash('success', "租户「{$tenant->name}」已切换至" . app(PlanService::class)->name($tenant));
    }

    public function render()
    {
        $tenants = Tenant::query()
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%")->orWhere('slug', 'like', "%{$this->search}%"))
            ->withCount('users')
            ->orderByDesc('created_at')
            ->get();

        // 各资源按租户聚合计数（4 次分组查询，避免逐租户 N+1）
        $usageByTable = [];
        foreach (['companies', 'contacts', 'leads', 'opportunities'] as $table) {
            $usageByTable[$table] = \Illuminate\Support\Facades\DB::table($table)
                ->select('tenant_id', \Illuminate\Support\Facades\DB::raw('count(*) as total'))
                ->groupBy('tenant_id')
                ->pluck('total', 'tenant_id');
        }

        // 各租户最近一次跟进活动时间（运营活跃度指标）
        $lastActivity = \Illuminate\Support\Facades\DB::table('activities')
            ->select('tenant_id', \Illuminate\Support\Facades\DB::raw('max(created_at) as last_at'))
            ->groupBy('tenant_id')
            ->pluck('last_at', 'tenant_id');

        $plans = app(PlanService::class);
        $resources = ['companies' => '公司', 'contacts' => '联系人', 'leads' => '线索', 'opportunities' => '商机'];

        $tenants->each(function (Tenant $tenant) use ($usageByTable, $lastActivity, $plans, $resources) {
            $tenant->usage = collect($resources)->map(function ($label, $resource) use ($tenant, $usageByTable, $plans) {
                $quota = $plans->quota($tenant, $resource);
                $current = (int) ($usageByTable[$resource][$tenant->id] ?? 0);

                return [
                    'label' => $label,
                    'current' => $current,
                    'quota' => $quota,
                    'pct' => $quota > 0 ? min(100, (int) round($current / $quota * 100)) : 0,
                ];
            })->all();
            $tenant->last_activity_at = $lastActivity[$tenant->id] ?? null;
        });

        $totalUsers = User::whereNotNull('tenant_id')->count();
        $paidTenants = $tenants->where('plan', '!=', 'free')->count();

        return view('livewire.platform.tenants', [
            'tenants' => $tenants,
            'totalTenants' => $tenants->count(),
            'totalUsers' => $totalUsers,
            'paidTenants' => $paidTenants,
            'planNames' => collect((array) config('plans'))
                ->except('upgrade_messages')
                ->map(fn ($plan) => $plan['name'])
                ->all(),
        ])->layout('layouts.app', ['title' => '租户管理']);
    }
}
