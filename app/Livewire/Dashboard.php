<?php

namespace App\Livewire;

use App\Models\Activity;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Stage;
use App\Models\TenantInvitation;
use App\Models\User;
use App\Services\Tenancy;
use Livewire\Component;

class Dashboard extends Component
{
    protected $listeners = ['refresh' => '$refresh'];

    public function mount(): void
    {
        $this->authorize('viewAny', Company::class);
    }

    /**
     * 关闭新手引导（存租户 settings，全租户生效）。
     */
    public function dismissOnboarding(): void
    {
        $tenant = app(Tenancy::class)->current();
        if (! $tenant) {
            return;
        }

        $settings = $tenant->settings ?? [];
        $settings['onboarding_dismissed'] = true;
        $tenant->update(['settings' => $settings]);
    }

    public function render()
    {
        $companyCount = Company::count();
        $leadCount = Lead::count();
        $opportunityValue = Opportunity::sum('value');
        $monthNewCount = Company::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        // 商机阶段分布
        $stageStats = Stage::withCount('opportunities')->ordered()->get();

        // 最近活动
        $recentActivities = Activity::with(['user', 'activitable'])
            ->latest()
            ->take(5)
            ->get();

        // 最近商机
        $recentOpportunities = Opportunity::with(['company', 'stage'])
            ->latest()
            ->take(5)
            ->get();

        // 高优先级商机（按智能评分排序，排除已赢单/输单）
        $topScoredOpportunities = Opportunity::with(['company', 'stage'])
            ->whereNotIn('stage_id', Stage::where(function ($q) {
                $q->where('is_won', true)->orWhere('is_lost', true);
            })->pluck('id')->toArray())
            ->orderByScore()
            ->take(5)
            ->get();

        // --- 待我处理 ---
        $userId = auth()->id();

        // 1. 当前用户负责的、未完成且 7 天内到期的活动
        $myDueActivities = Activity::with(['activitable'])
            ->where('user_id', $userId)
            ->whereNull('completed_at')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now()->addDays(7))
            ->where('scheduled_at', '>=', now())
            ->orderBy('scheduled_at')
            ->take(5)
            ->get();

        // 2. 当前用户负责的、停滞超 14 天的商机（未更新超 14 天）
        $wonLostStageIds = Stage::where(function ($q) {
            $q->where('is_won', true)->orWhere('is_lost', true);
        })->pluck('id')->toArray();

        $myStalledOpportunities = Opportunity::with(['company', 'stage'])
            ->where('owner_id', $userId)
            ->where('updated_at', '<=', now()->subDays(14))
            ->whereNotIn('stage_id', $wonLostStageIds)
            ->orderBy('updated_at')
            ->take(5)
            ->get();

        // 3. 当前用户负责的、本周应收的商机
        $myClosingOpportunities = Opportunity::with(['company', 'stage'])
            ->where('owner_id', $userId)
            ->whereNotNull('expected_close_date')
            ->whereBetween('expected_close_date', [now()->startOfWeek(), now()->endOfWeek()])
            ->whereNotIn('stage_id', $wonLostStageIds)
            ->orderBy('expected_close_date')
            ->take(5)
            ->get();

        // --- 新手引导 checklist（全部完成后自动消失，可手动关闭存租户 settings）---
        $tenant = app(Tenancy::class)->current();
        $onboarding = null;

        if ($tenant && ! ($tenant->settings['onboarding_dismissed'] ?? false)) {
            // 团队功能为 pro+ 权益，无权限时步骤渲染为纯徽章（不出链接，防泄漏菜单）
            $teamUrl = app(\App\Services\PlanService::class)->hasFeature($tenant, 'settings.team')
                ? route('settings.team')
                : null;

            $onboarding = [
                ['label' => __('dashboard.onboarding_company'), 'done' => Company::count() > 0, 'url' => route('companies.create')],
                ['label' => __('dashboard.onboarding_contact'), 'done' => Contact::count() > 0, 'url' => route('contacts.create')],
                ['label' => __('dashboard.onboarding_lead'), 'done' => Lead::count() > 0, 'url' => route('leads.create')],
                ['label' => __('dashboard.onboarding_convert'), 'done' => Opportunity::count() > 0, 'url' => route('opportunities.create')],
                ['label' => __('dashboard.onboarding_team'), 'done' => User::whereKeyNot(auth()->id())->exists() || TenantInvitation::where('tenant_id', $tenant->id)->exists(), 'url' => $teamUrl],
            ];

            // 全部完成则不展示
            if (! collect($onboarding)->contains(fn ($step) => ! $step['done'])) {
                $onboarding = null;
            }
        }

        return view('livewire.dashboard', [
            'companyCount' => $companyCount,
            'leadCount' => $leadCount,
            'opportunityValue' => $opportunityValue,
            'monthNewCount' => $monthNewCount,
            'stageStats' => $stageStats,
            'recentActivities' => $recentActivities,
            'recentOpportunities' => $recentOpportunities,
            'topScoredOpportunities' => $topScoredOpportunities,
            'myDueActivities' => $myDueActivities,
            'myStalledOpportunities' => $myStalledOpportunities,
            'myClosingOpportunities' => $myClosingOpportunities,
            'onboarding' => $onboarding,
        ])->layout('layouts.app', ['title' => '控制台']);
    }
}
