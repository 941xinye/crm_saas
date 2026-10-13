<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">{{ __('platform_revenue.title') }}</h1>
        <p class="text-sm text-gray-500 mt-1">{{ __('platform_revenue.subtitle') }}</p>
    </div>

    @if(session('success'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-lg px-4 py-3 text-sm">{{ session('success') }}</div>
    @endif

    <!-- 核心指标 -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
            <p class="text-sm text-gray-500">{{ __('platform_revenue.mrr') }}</p>
            <p class="text-3xl font-bold text-gray-900 mt-1">¥{{ number_format($mrr, 2) }}</p>
            <p class="text-xs text-gray-400 mt-2">{{ __('platform_revenue.arpu') }}：¥{{ number_format($arpu, 2) }}</p>
            @if(count($mrrByCurrency) > 1)
                {{-- 多币种：MRR 合计跨币种无意义，按币种分行展示 --}}
                <div class="mt-3 space-y-1 border-t border-gray-100 pt-3">
                    @foreach($mrrByCurrency as $currency => $group)
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-medium text-gray-600">{{ $currency }}</span>
                            <span class="text-gray-500">{{ number_format($group['mrr'], 2) }} · {{ $group['count'] }} {{ __('platform_revenue.tenants_unit') }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
            <p class="text-sm text-gray-500">{{ __('platform_revenue.active_paid') }}</p>
            <p class="text-3xl font-bold text-indigo-600 mt-1">{{ $activePaidCount }}</p>
            <p class="text-xs text-gray-400 mt-2">{{ __('platform_revenue.free_count') }}：{{ $freeCount }}　{{ __('platform_revenue.trial_count') }}：{{ $trialCount }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
            <p class="text-sm text-gray-500">{{ __('platform_revenue.churn_rate') }}</p>
            <p class="text-3xl font-bold {{ $churnRate > 10 ? 'text-red-600' : 'text-gray-900' }} mt-1">{{ $churnRate }}%</p>
            <p class="text-xs text-gray-400 mt-2">{{ __('platform_revenue.churn_count') }}：{{ $churnCount }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
            <p class="text-sm text-gray-500">{{ __('platform_revenue.total_tenants') }}</p>
            <p class="text-3xl font-bold text-gray-900 mt-1">{{ $tenantCount }}</p>
            <p class="text-xs text-gray-400 mt-2">
                {{ __('platform_revenue.plan_price_hint') }}：
                @foreach(['pro' => 'P', 'enterprise' => 'E'] as $planKey => $short)
                    @if(($planPrices[$planKey] ?? 0) > 0)
                        <span class="ml-1">¥{{ number_format($planPrices[$planKey]) }}</span>
                    @endif
                @endforeach
            </p>
        </div>
    </div>

    <!-- 近 12 个月发票收入（按币种） -->
    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
        <h2 class="text-sm font-semibold text-gray-700 mb-4">{{ __('platform_revenue.revenue_12m') }}</h2>
        @forelse($revenueByCurrency as $currency => $series)
            <div class="mb-5">
                <p class="text-xs text-gray-500 mb-2">{{ $currency }}</p>
                <div class="flex items-end gap-1.5 h-32">
                    @foreach($series as $month => $value)
                        <div class="flex-1 flex flex-col items-center gap-1" data-tooltip="{{ $month }}：{{ number_format($value, 2) }} {{ $currency }}">
                            <div class="w-full bg-indigo-500/85 hover:bg-indigo-600 rounded-t transition-colors"
                                 style="height: {{ $maxRevenue > 0 ? max(2, (int) round($value / $maxRevenue * 100)) : 2 }}%"></div>
                            <span class="text-[10px] text-gray-400 whitespace-nowrap">{{ substr($month, 5) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @empty
            <p class="text-gray-400 text-sm text-center py-6">{{ __('platform_revenue.no_revenue') }}</p>
        @endforelse
        <p class="text-xs text-gray-400 mt-2">{{ __('platform_revenue.revenue_hint') }}</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- 流失租户 -->
        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
            <h2 class="text-sm font-semibold text-gray-700 mb-4">{{ __('platform_revenue.churned_list') }}</h2>
            <div class="space-y-2">
                @forelse($churned as $tenant)
                    <div class="flex items-center justify-between p-2.5 rounded-lg hover:bg-gray-50 transition-colors">
                        <div>
                            <p class="text-sm font-medium text-gray-800">{{ $tenant->name }}</p>
                            <p class="text-xs text-gray-400">
                                {{ __("settings_profile.plan_{$tenant->plan}") }}
                                @if($tenant->stripe_status === 'canceled')
                                    　· {{ __('platform_revenue.churn_reason_canceled') }}
                                @else
                                    　· {{ __('platform_revenue.churn_reason_expired') }} {{ $tenant->subscription_ends_at?->format('Y-m-d') }}
                                @endif
                            </p>
                        </div>
                        <span class="px-2 py-0.5 text-xs font-medium bg-red-50 text-red-600 rounded-full">{{ __('platform_revenue.churned_badge') }}</span>
                    </div>
                @empty
                    <p class="text-gray-400 text-sm text-center py-6">{{ __('platform_revenue.no_churn') }}</p>
                @endforelse
            </div>
        </div>

        <!-- 最近发票 -->
        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
            <h2 class="text-sm font-semibold text-gray-700 mb-4">{{ __('platform_revenue.recent_invoices') }}</h2>
            <div class="space-y-2">
                @forelse($recentInvoices as $invoice)
                    <div class="flex items-center justify-between p-2.5 rounded-lg hover:bg-gray-50 transition-colors">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-gray-800 truncate">{{ $invoice->tenant?->name ?? '-' }}</p>
                            <p class="text-xs text-gray-400">{{ $invoice->paid_at?->format('Y-m-d') }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-semibold text-gray-900">{{ strtoupper($invoice->currency) }} {{ number_format($invoice->amount / 100, 2) }}</span>
                            <span class="px-2 py-0.5 text-xs rounded-full {{ $invoice->badgeClass() }}">{{ __('settings_subscription.invoice_status_' . $invoice->status) }}</span>
                        </div>
                    </div>
                @empty
                    <p class="text-gray-400 text-sm text-center py-6">{{ __('platform_revenue.no_invoices') }}</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
