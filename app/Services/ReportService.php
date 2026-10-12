<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Stage;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * 报表数据聚合服务：统一漏斗转化率、按人业绩、月度趋势数据。
 * 所有查询走 Tenancy 全局作用域，天然隔离当前租户。
 *
 * 数据权限：sales 角色仅统计自己负责的数据，admin/manager 统计全租户。
 */
class ReportService
{
    /**
     * 按当前登录用户的数据权限过滤查询：
     * sales 仅见自己负责的数据，admin/manager/平台超管不限制。
     */
    private function scopeByOwner($query)
    {
        $user = auth()->user();

        if ($user && ! $user->canSeeAllRecords()) {
            $query->where('owner_id', $user->id);
        }

        return $query;
    }

    /**
     * 销售漏斗：线索各状态数量 → 联系人数 → 商机数，逐级转化率。
     *
     * @return array{
     *   lead_by_status: array<string, int>,
     *   total_leads: int,
     *   converted_leads: int,
     *   conversion_rate: float,
     *   total_contacts: int,
     *   total_opportunities: int,
     *   contact_rate_from_lead: float,
     *   opportunity_rate_from_contact: float,
     * }
     */
    public function funnel(): array
    {
        $leadStatuses = ['new', 'contacted', 'qualified', 'converted', 'lost'];
        $leadByStatus = [];
        foreach ($leadStatuses as $status) {
            $leadByStatus[$status] = $this->scopeByOwner(Lead::query())->where('status', $status)->count();
        }
        $totalLeads = array_sum($leadByStatus);
        $convertedLeads = $leadByStatus['converted'] ?? 0;
        $totalContacts = Contact::count();
        $totalOpportunities = $this->scopeByOwner(Opportunity::query())->count();

        $safeDiv = fn ($num, $den) => $den > 0 ? round($num / $den * 100, 1) : 0.0;

        return [
            'lead_by_status' => $leadByStatus,
            'total_leads' => $totalLeads,
            'converted_leads' => $convertedLeads,
            'conversion_rate' => $safeDiv($convertedLeads, $totalLeads),
            'total_contacts' => $totalContacts,
            'total_opportunities' => $totalOpportunities,
            'contact_rate_from_lead' => $safeDiv($totalContacts, $totalLeads),
            'opportunity_rate_from_contact' => $safeDiv($totalOpportunities, $totalContacts),
        ];
    }

    /**
     * 按人业绩：每个有商机所有权的用户，商机数、成交额、赢率（is_won=true 的阶段视为赢单）。
     *
     * @return array<int, array{user_name: string, user_id: int, opp_count: int, total_value: float, won_count: int, win_rate: float}>
     */
    public function performanceByOwner(): array
    {
        // 使用 Stage 上的 is_won 标记，替代"最后阶段视为赢单"的简化假设
        $wonStageIds = Stage::won()->pluck('id')->toArray();

        $rows = $this->scopeByOwner(Opportunity::query())
            ->selectRaw('owner_id, COUNT(*) as opp_count, COALESCE(SUM(value), 0) as total_value')
            ->groupBy('owner_id')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $user = User::find($row->owner_id);
            if (! $user) {
                continue;
            }
            $wonCount = $this->scopeByOwner(Opportunity::query())
                ->where('owner_id', $row->owner_id)
                ->whereIn('stage_id', $wonStageIds)
                ->count();
            $result[] = [
                'user_id' => $row->owner_id,
                'user_name' => $user->name,
                'opp_count' => (int) $row->opp_count,
                'total_value' => (float) $row->total_value,
                'won_count' => $wonCount,
                'win_rate' => $row->opp_count > 0 ? round($wonCount / $row->opp_count * 100, 1) : 0.0,
            ];
        }

        // 按成交额降序
        usort($result, fn ($a, $b) => $b['total_value'] <=> $a['total_value']);

        return $result;
    }

    /**
     * 月度趋势：近 N 个月每月新增线索/公司/商机/成交额。
     *
     * @return array{
     *   labels: array<int, string>,
     *   leads: array<int, int>,
     *   companies: array<int, int>,
     *   opportunities: array<int, int>,
     *   values: array<int, float>,
     * }
     */
    public function monthlyTrend(int $months = 6): array
    {
        $labels = [];
        $leads = $companies = $opportunities = $values = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $labels[] = $month->format('Y-m');

            $leads[] = $this->scopeByOwner(Lead::query())
                ->whereYear('created_at', $month->year)
                ->whereMonth('created_at', $month->month)
                ->count();
            $companies[] = $this->scopeByOwner(Company::query())
                ->whereYear('created_at', $month->year)
                ->whereMonth('created_at', $month->month)
                ->count();
            $opportunities[] = $this->scopeByOwner(Opportunity::query())
                ->whereYear('created_at', $month->year)
                ->whereMonth('created_at', $month->month)
                ->count();
            $values[] = (float) $this->scopeByOwner(Opportunity::query())
                ->whereYear('created_at', $month->year)
                ->whereMonth('created_at', $month->month)
                ->sum('value');
        }

        return compact('labels', 'leads', 'companies', 'opportunities', 'values');
    }

    /**
     * 商机阶段分布（带金额）：阶段名、商机数、总金额、平均金额。
     *
     * @return array<int, array{stage_name: string, stage_id: int, count: int, total_value: float, avg_value: float}>
     */
    public function stageBreakdown(): array
    {
        $stages = Stage::ordered()->get();
        $result = [];

        foreach ($stages as $stage) {
            $stats = $this->scopeByOwner(Opportunity::query())
                ->where('stage_id', $stage->id)
                ->selectRaw('COUNT(*) as count, COALESCE(SUM(value), 0) as total, COALESCE(AVG(value), 0) as avg')
                ->first();

            $result[] = [
                'stage_id' => $stage->id,
                'stage_name' => $stage->name,
                'count' => (int) $stats->count,
                'total_value' => (float) $stats->total,
                'avg_value' => (float) $stats->avg,
            ];
        }

        return $result;
    }
}
