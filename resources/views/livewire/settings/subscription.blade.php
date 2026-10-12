<div class="max-w-4xl space-y-6">
    <!-- 页面标题 -->
    <div>
        <h1 class="text-2xl font-bold text-gray-900">{{ __('settings_subscription.title') }}</h1>
        <p class="text-sm text-gray-500 mt-1">{{ __('settings_subscription.subtitle') }}</p>
    </div>

    @if(session('error'))
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">
            {{ session('error') }}
        </div>
    @endif

    @unless($canCreate)
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">
            {{ __('settings_subscription.trial_ended_deactivated') }}
        </div>
    @endunless

    @if(in_array($tenant->stripe_status, ['past_due', 'unpaid']))
        <div class="bg-amber-50 border border-amber-200 text-amber-700 px-4 py-3 rounded-lg text-sm">
            {{ __('settings_subscription.stripe_past_due', ['status' => $tenant->stripe_status]) }}
        </div>
    @endif

    <!-- 当前套餐 -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <div class="flex items-start justify-between">
            <div>
                <div class="flex items-center gap-3">
                    <h2 class="text-lg font-semibold text-gray-900">{{ $planName }}</h2>
                    @if($tenant->plan === 'free')
                        @if($canCreate)
                            <span class="px-2 py-0.5 text-xs rounded-full bg-green-100 text-green-700">{{ __('settings_subscription.trial_active', ['days' => (int) $trialDaysLeft]) }}</span>
                        @else
                            <span class="px-2 py-0.5 text-xs rounded-full bg-red-100 text-red-700">{{ __('settings_subscription.trial_ended') }}</span>
                        @endif
                    @else
                        <span class="px-2 py-0.5 text-xs rounded-full bg-purple-100 text-purple-700">{{ __('settings_subscription.paid_plan') }}</span>
                    @endif
                </div>
                @if($tenant->plan === 'free' && $canCreate)
                    <p class="text-sm text-gray-500 mt-1">{{ __('settings_subscription.trial_until', ['date' => $trialEndsAt->format('Y-m-d')]) }}</p>
                @endif
            </div>
        </div>

        @if($tenant->plan === 'free')
            <div class="mt-4 bg-indigo-50 border border-indigo-100 text-indigo-700 px-4 py-3 rounded-lg text-sm">
                @if($stripeEnabled && $isAdmin)
                    {{ __('settings_subscription.upgrade_online') }}
                    <button wire:click="upgrade('pro')" wire:loading.attr="disabled"
                            class="mx-1 px-3 py-1 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium rounded transition-colors">{{ __('settings_subscription.upgrade_pro_btn') }}</button>
                    <button wire:click="upgrade('enterprise')" wire:loading.attr="disabled"
                            class="mx-1 px-3 py-1 bg-purple-600 hover:bg-purple-700 text-white text-xs font-medium rounded transition-colors">{{ __('settings_subscription.upgrade_enterprise_btn') }}</button>
                @else
                    {{ __('settings_subscription.contact_admin_upgrade') }}
                    <span class="font-semibold">{{ __('settings_subscription.plan_pro') }}</span>
                    {{ __('settings_subscription.or') }}
                    <span class="font-semibold">{{ __('settings_subscription.plan_enterprise') }}</span>{{ __('settings_subscription.sentence_end') }}
                @endif
            </div>
        @elseif($tenant->plan === 'pro' && $stripeEnabled && $isAdmin)
            <div class="mt-4 bg-purple-50 border border-purple-100 text-purple-700 px-4 py-3 rounded-lg text-sm">
                {{ __('settings_subscription.upgrade_online') }}
                <button wire:click="upgrade('enterprise')" wire:loading.attr="disabled"
                        class="mx-1 px-3 py-1 bg-purple-600 hover:bg-purple-700 text-white text-xs font-medium rounded transition-colors">{{ __('settings_subscription.upgrade_enterprise_webhook') }}</button>
            </div>
        @endif
        @if($tenant->stripe_status)
            <p class="mt-3 text-xs text-gray-400">{{ __('settings_subscription.stripe_status', ['status' => $tenant->stripe_status]) }}</p>
        @endif
        @if($tenant->plan !== 'free' && $stripeEnabled && $isAdmin)
            <button wire:click="portal" wire:loading.attr="disabled"
                    class="mt-3 px-3 py-1.5 bg-white border border-gray-300 hover:bg-gray-50 text-gray-600 text-xs font-medium rounded transition-colors">
                {{ __('settings_subscription.manage_billing') }}
            </button>
        @endif
    </div>

    <!-- 用量明细 -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h3 class="text-sm font-medium text-gray-700 mb-4">{{ __('settings_subscription.usage_detail') }}</h3>

        <!-- 成员 -->
        <div class="mb-5">
            <div class="flex justify-between text-sm mb-1.5">
                <span class="text-gray-700">{{ __('settings_subscription.usage_members') }}</span>
                <span class="text-gray-500">{{ $memberCount }} / {{ $maxUsers < 0 ? __('settings_subscription.unlimited') : $maxUsers }}</span>
            </div>
            <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                @if($maxUsers > 0)
                    @php $pct = min(100, (int) round($memberCount / $maxUsers * 100)); @endphp
                    <div class="h-full rounded-full {{ $pct >= 100 ? 'bg-red-500' : 'bg-indigo-500' }}" style="width: {{ $pct }}%"></div>
                @else
                    <div class="h-full bg-indigo-500" style="width: 100%"></div>
                @endif
            </div>
        </div>

        @foreach($usage as $item)
            <div class="mb-5 last:mb-0">
                <div class="flex justify-between text-sm mb-1.5">
                    <span class="text-gray-700">{{ $item['label'] }}</span>
                    <span class="text-gray-500">{{ $item['current'] }} / {{ $item['quota'] < 0 ? __('settings_subscription.unlimited') : $item['quota'] }}</span>
                </div>
                <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                    @if($item['quota'] > 0)
                        @php $pct = min(100, (int) round($item['current'] / $item['quota'] * 100)); @endphp
                        <div class="h-full rounded-full {{ $pct >= 100 ? 'bg-red-500' : 'bg-indigo-500' }}" style="width: {{ $pct }}%"></div>
                    @else
                        <div class="h-full bg-indigo-500" style="width: 100%"></div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    <!-- 账单历史 -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h3 class="text-sm font-medium text-gray-700 mb-4">{{ __('settings_subscription.billing_history') }}</h3>

        @if($invoices->isEmpty())
            <p class="text-sm text-gray-400">{{ __('settings_subscription.no_invoices') }}</p>
        @else
            <div class="divide-y divide-gray-100">
                @foreach($invoices as $invoice)
                    <div class="flex items-center justify-between py-3 first:pt-0 last:pb-0">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-medium text-gray-900">{{ strtoupper($invoice->currency) }} {{ number_format($invoice->amount / 100, 2) }}</span>
                                <span class="px-2 py-0.5 text-xs rounded-full {{ $invoice->badgeClass() }}">{{ __('settings_subscription.invoice_status_'.$invoice->status) }}</span>
                            </div>
                            <p class="text-xs text-gray-400 mt-0.5">
                                @if($invoice->period_start && $invoice->period_end)
                                    {{ $invoice->period_start->format('Y-m-d') }} ~ {{ $invoice->period_end->format('Y-m-d') }}
                                @endif
                                @if($invoice->paid_at)
                                    · {{ __('settings_subscription.paid_on', ['date' => $invoice->paid_at->format('Y-m-d')]) }}
                                @endif
                            </p>
                        </div>
                        @if($invoice->hosted_invoice_url)
                            <a href="{{ $invoice->hosted_invoice_url }}" target="_blank" rel="noopener"
                               class="text-xs font-medium text-indigo-600 hover:text-indigo-700">{{ __('settings_subscription.view_invoice') }}</a>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
