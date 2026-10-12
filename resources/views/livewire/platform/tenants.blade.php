<div class="space-y-6">
    <!-- 页面标题 -->
    <div>
        <h1 class="text-2xl font-bold text-gray-900">{{ __('platform.page_title') }}</h1>
        <p class="text-sm text-gray-500 mt-1">{{ __('platform.page_subtitle') }}</p>
    </div>

    <!-- 统计卡片 -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
            <p class="text-sm text-gray-500">{{ __('platform.stat_total_tenants') }}</p>
            <p class="text-3xl font-bold text-gray-900 mt-1">{{ $totalTenants }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
            <p class="text-sm text-gray-500">{{ __('platform.stat_total_users') }}</p>
            <p class="text-3xl font-bold text-gray-900 mt-1">{{ $totalUsers }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
            <p class="text-sm text-gray-500">{{ __('platform.stat_paid_tenants') }}</p>
            <p class="text-3xl font-bold text-green-600 mt-1">{{ $paidTenants }}</p>
        </div>
    </div>

    <!-- 搜索 -->
    <div class="bg-white rounded-xl shadow-sm p-4 border border-gray-100">
        <div class="relative max-w-md">
            <input wire:model.live="search" type="text" placeholder="{{ __('platform.search_placeholder') }}"
                   class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            <svg class="absolute left-3 top-2.5 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        </div>
    </div>

    <!-- 租户表格 -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('platform.col_name') }}</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Slug</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('platform.col_plan') }}</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('platform.col_trial_ends') }}</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('platform.col_users') }}</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('platform.col_quota_usage') }}</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('platform.col_last_active') }}</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('platform.col_status') }}</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('platform.col_actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($tenants as $tenant)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $tenant->name }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $tenant->slug }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center gap-1">
                                @foreach(['free', 'pro', 'enterprise'] as $planKey)
                                    <button wire:confirm="{{ __('platform.confirm_change_plan', ['name' => $tenant->name, 'plan' => $planNames[$planKey]]) }}"
                                            wire:click="changePlan({{ $tenant->id }}, '{{ $planKey }}')"
                                            {{ $tenant->plan === $planKey ? 'disabled' : '' }}
                                            class="px-2 py-1 text-xs rounded-full transition-colors
                                                   {{ $tenant->plan === $planKey
                                                       ? 'bg-indigo-600 text-white cursor-default'
                                                       : 'bg-gray-100 text-gray-600 hover:bg-indigo-100 hover:text-indigo-700' }}">
                                        {{ $planNames[$planKey] }}
                                    </button>
                                @endforeach
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            @if($tenant->plan === 'free' && $tenant->trial_ends_at)
                                @if($tenant->trial_ends_at->isFuture())
                                    {{ $tenant->trial_ends_at->format('Y-m-d') }}
                                @else
                                    <span class="text-red-600">{{ __('platform.expired') }}</span>
                                @endif
                            @elseif($tenant->plan === 'free')
                                <span class="text-red-600">{{ __('platform.expired') }}</span>
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $tenant->users_count }}</td>
                        <td class="px-6 py-4 text-xs text-gray-600">
                            <div class="flex flex-wrap gap-x-3 gap-y-1">
                                @foreach($tenant->usage as $usage)
                                    <span class="{{ $usage['pct'] >= 100 ? 'text-red-600 font-semibold' : ($usage['pct'] >= 80 ? 'text-amber-600' : '') }}">
                                        {{ $usage['label'] }} {{ $usage['current'] }}/{{ $usage['quota'] < 0 ? __('platform.unlimited') : $usage['quota'] }}
                                    </span>
                                @endforeach
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            @if($tenant->last_activity_at)
                                {{ \Illuminate\Support\Carbon::parse($tenant->last_activity_at)->format('Y-m-d H:i') }}
                            @else
                                <span class="text-gray-300">{{ __('platform.no_activity') }}</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if($tenant->is_active) <span class="px-2 py-1 text-xs bg-green-100 text-green-700 rounded-full">{{ __('platform.status_active') }}</span>
                            @else <span class="px-2 py-1 text-xs bg-red-100 text-red-700 rounded-full">{{ __('platform.status_frozen') }}</span> @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <button wire:click="exportData({{ $tenant->id }})"
                                    class="text-indigo-600 hover:text-indigo-900 mr-3">{{ __('platform.export_data') }}</button>
                            <button wire:confirm="{{ __('platform.purge_confirm', ['name' => $tenant->name]) }}"
                                    wire:click="purgeData({{ $tenant->id }})"
                                    class="text-red-600 hover:text-red-900 mr-3">{{ __('platform.purge_data') }}</button>
                            <button wire:confirm="{{ __('platform.confirm_toggle', ['action' => $tenant->is_active ? __('platform.freeze') : __('platform.activate')]) }}"
                                    wire:click="toggleActive({{ $tenant->id }})"
                                    class="{{ $tenant->is_active ? 'text-red-600 hover:text-red-900' : 'text-green-600 hover:text-green-900' }}">
                                {{ $tenant->is_active ? __('platform.freeze') : __('platform.activate') }}
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="px-6 py-12 text-center text-gray-500 text-sm">{{ __('platform.empty_tenants') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
