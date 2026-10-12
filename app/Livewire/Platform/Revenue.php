<?php

namespace App\Livewire\Platform;

use App\Models\Tenant;
use App\Models\TenantInvoice;
use Livewire\Component;

/**
 * 平台收入/流失看板（平台超管专用）：
 * MRR、付费/免费/试用租户数、累计流失率、近 12 个月发票收入
 * （按币种分列）、流失租户列表、最近发票。
 *
 * MRR 按套餐月价（config/plans.php monthly_price）× 未流失付费租户计算；
 * 流失判定：stripe_status = canceled，或付费套餐订阅已过期。
 */
class Revenue extends Component
{
    public function render()
    {
        $tenants = Tenant::query()->get();

        $paid = $tenants->where('plan', '!=', 'free');

        // 流失：订阅被取消，或付费套餐订阅已到期
        $churned = $paid->filter(function (Tenant $t) {
            return $t->stripe_status === 'canceled'
                || ($t->subscription_ends_at !== null && $t->subscription_ends_at->isPast());
        })->sortByDesc(fn (Tenant $t) => $t->subscription_ends_at ?? $t->updated_at);

        // 在营付费 = 未流失的付费租户（Stripe 未接入时 stripe_status 为 null，按套餐口径统计）
        $activePaid = $paid->reject(fn (Tenant $t) => $churned->contains($t));
        $mrr = $activePaid->sum(fn (Tenant $t) => (int) config("plans.{$t->plan}.monthly_price", 0));

        $trialing = $tenants->filter(fn (Tenant $t) => $t->trial_ends_at !== null && $t->trial_ends_at->isFuture());

        // 历史累计流失率 = 流失 / (在营付费 + 流失)
        $everPaid = $activePaid->count() + $churned->count();
        $churnRate = $everPaid > 0 ? round($churned->count() / $everPaid * 100, 1) : 0.0;

        // 近 12 个月已支付发票收入（金额为最小货币单位），按币种分列
        $invoices = TenantInvoice::query()
            ->where('status', 'paid')
            ->where('paid_at', '>=', now()->subMonths(11)->startOfMonth())
            ->orderBy('paid_at')
            ->get();

        $months = [];
        for ($i = 11; $i >= 0; $i--) {
            $months[now()->subMonths($i)->format('Y-m')] = 0.0;
        }

        $revenueByCurrency = [];
        foreach ($invoices->groupBy('currency') as $currency => $rows) {
            $series = $months;
            foreach ($rows as $invoice) {
                $key = $invoice->paid_at?->format('Y-m');
                if (isset($series[$key])) {
                    $series[$key] += $invoice->amount / 100;
                }
            }
            $revenueByCurrency[strtoupper($currency)] = $series;
        }

        $recentInvoices = TenantInvoice::query()
            ->with('tenant:id,name')
            ->latest('paid_at')
            ->limit(10)
            ->get();

        return view('livewire.platform.revenue', [
            'mrr' => $mrr,
            'arpu' => $activePaid->isNotEmpty() ? round($mrr / $activePaid->count(), 2) : 0,
            'activePaidCount' => $activePaid->count(),
            'freeCount' => $tenants->where('plan', 'free')->count(),
            'trialCount' => $trialing->count(),
            'tenantCount' => $tenants->count(),
            'churned' => $churned->take(10),
            'churnCount' => $churned->count(),
            'churnRate' => $churnRate,
            'revenueByCurrency' => $revenueByCurrency,
            'recentInvoices' => $recentInvoices,
            'maxRevenue' => collect($revenueByCurrency)->flatten()->max() ?: 0,
            'planPrices' => collect((array) config('plans'))
                ->except('upgrade_messages')
                ->mapWithKeys(fn ($plan, $key) => [$key => $plan['monthly_price'] ?? 0])
                ->all(),
        ])->layout('layouts.app', ['title' => __('platform_revenue.title')]);
    }
}
