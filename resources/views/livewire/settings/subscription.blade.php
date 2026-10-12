<div class="max-w-4xl space-y-6">
    <!-- 页面标题 -->
    <div>
        <h1 class="text-2xl font-bold text-gray-900">套餐订阅</h1>
        <p class="text-sm text-gray-500 mt-1">查看当前套餐与用量</p>
    </div>

    @if(session('error'))
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">
            {{ session('error') }}
        </div>
    @endif

    @unless($canCreate)
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">
            试用期已结束，新增数据功能已停用。现有数据仍可正常查看和编辑，升级套餐后即可恢复新增。
        </div>
    @endunless

    @if(in_array($tenant->stripe_status, ['past_due', 'unpaid']))
        <div class="bg-amber-50 border border-amber-200 text-amber-700 px-4 py-3 rounded-lg text-sm">
            订阅付款失败或已逾期（状态：{{ $tenant->stripe_status }}），请尽快更新支付方式以免服务受限。
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
                            <span class="px-2 py-0.5 text-xs rounded-full bg-green-100 text-green-700">试用中，剩余 {{ (int) $trialDaysLeft }} 天</span>
                        @else
                            <span class="px-2 py-0.5 text-xs rounded-full bg-red-100 text-red-700">试用已结束</span>
                        @endif
                    @else
                        <span class="px-2 py-0.5 text-xs rounded-full bg-purple-100 text-purple-700">付费套餐</span>
                    @endif
                </div>
                @if($tenant->plan === 'free' && $canCreate)
                    <p class="text-sm text-gray-500 mt-1">试用期至 {{ $trialEndsAt->format('Y-m-d') }}</p>
                @endif
            </div>
        </div>

        @if($tenant->plan === 'free')
            <div class="mt-4 bg-indigo-50 border border-indigo-100 text-indigo-700 px-4 py-3 rounded-lg text-sm">
                @if($stripeEnabled && $isAdmin)
                    在线升级：
                    <button wire:click="upgrade('pro')" wire:loading.attr="disabled"
                            class="mx-1 px-3 py-1 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium rounded transition-colors">开通专业版</button>
                    <button wire:click="upgrade('enterprise')" wire:loading.attr="disabled"
                            class="mx-1 px-3 py-1 bg-purple-600 hover:bg-purple-700 text-white text-xs font-medium rounded transition-colors">开通企业版</button>
                @else
                    需要更多配额？请联系平台管理员开通
                    <span class="font-semibold">专业版</span>
                    或
                    <span class="font-semibold">企业版</span>。
                @endif
            </div>
        @elseif($tenant->plan === 'pro' && $stripeEnabled && $isAdmin)
            <div class="mt-4 bg-purple-50 border border-purple-100 text-purple-700 px-4 py-3 rounded-lg text-sm">
                在线升级：
                <button wire:click="upgrade('enterprise')" wire:loading.attr="disabled"
                        class="mx-1 px-3 py-1 bg-purple-600 hover:bg-purple-700 text-white text-xs font-medium rounded transition-colors">升级企业版（解锁 Webhook / 自动化工作流）</button>
            </div>
        @endif
        @if($tenant->stripe_status)
            <p class="mt-3 text-xs text-gray-400">订阅状态：{{ $tenant->stripe_status }}</p>
        @endif
        @if($tenant->plan !== 'free' && $stripeEnabled && $isAdmin)
            <button wire:click="portal" wire:loading.attr="disabled"
                    class="mt-3 px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-medium rounded transition-colors">
                管理订阅与账单（Stripe 客户门户）
            </button>
        @endif
    </div>

    <!-- 用量明细 -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h3 class="text-sm font-medium text-gray-700 mb-4">用量明细</h3>

        <!-- 成员 -->
        <div class="mb-5">
            <div class="flex justify-between text-sm mb-1.5">
                <span class="text-gray-700">团队成员</span>
                <span class="text-gray-500">{{ $memberCount }} / {{ $maxUsers < 0 ? '不限' : $maxUsers }}</span>
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
                    <span class="text-gray-500">{{ $item['current'] }} / {{ $item['quota'] < 0 ? '不限' : $item['quota'] }}</span>
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
</div>
