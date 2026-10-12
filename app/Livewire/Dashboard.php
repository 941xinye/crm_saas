<?php

namespace App\Livewire;

use App\Models\Activity;
use App\Models\Company;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Stage;
use App\Models\User;
use Livewire\Component;

class Dashboard extends Component
{
    protected $listeners = ['refresh' => '$refresh'];

    public function mount(): void
    {
        $this->authorize('viewAny', Company::class);
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

        return view('livewire.dashboard', [
            'companyCount' => $companyCount,
            'leadCount' => $leadCount,
            'opportunityValue' => $opportunityValue,
            'monthNewCount' => $monthNewCount,
            'stageStats' => $stageStats,
            'recentActivities' => $recentActivities,
            'recentOpportunities' => $recentOpportunities,
        ])->layout('layouts.app', ['title' => '控制台']);
    }
}
