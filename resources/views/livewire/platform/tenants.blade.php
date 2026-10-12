<div class="space-y-6">
    <!-- 页面标题 -->
    <div>
        <h1 class="text-2xl font-bold text-gray-900">平台管理</h1>
        <p class="text-sm text-gray-500 mt-1">管理所有租户</p>
    </div>

    <!-- 统计卡片 -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
            <p class="text-sm text-gray-500">租户总数</p>
            <p class="text-3xl font-bold text-gray-900 mt-1">{{ $totalTenants }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
            <p class="text-sm text-gray-500">用户总数</p>
            <p class="text-3xl font-bold text-gray-900 mt-1">{{ $totalUsers }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
            <p class="text-sm text-gray-500">付费租户</p>
            <p class="text-3xl font-bold text-green-600 mt-1">{{ $paidTenants }}</p>
        </div>
    </div>

    <!-- 搜索 -->
    <div class="bg-white rounded-xl shadow-sm p-4 border border-gray-100">
        <div class="relative max-w-md">
            <input wire:model.live="search" type="text" placeholder="搜索租户名称或 slug..."
                   class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            <svg class="absolute left-3 top-2.5 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        </div>
    </div>

    <!-- 租户表格 -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">租户名称</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Slug</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">计划</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">用户数</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">状态</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">操作</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($tenants as $tenant)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $tenant->name }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $tenant->slug }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if($tenant->plan === 'free') <span class="px-2 py-1 text-xs bg-gray-100 text-gray-700 rounded-full">免费</span>
                            @elseif($tenant->plan === 'pro') <span class="px-2 py-1 text-xs bg-blue-100 text-blue-700 rounded-full">专业</span>
                            @elseif($tenant->plan === 'enterprise') <span class="px-2 py-1 text-xs bg-purple-100 text-purple-700 rounded-full">企业</span>
                            @else {{ $tenant->plan }} @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $tenant->users_count }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if($tenant->is_active) <span class="px-2 py-1 text-xs bg-green-100 text-green-700 rounded-full">活跃</span>
                            @else <span class="px-2 py-1 text-xs bg-red-100 text-red-700 rounded-full">已冻结</span> @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <button wire:confirm="确定要{{ $tenant->is_active ? '冻结' : '激活' }}该租户吗？"
                                    wire:click="toggleActive({{ $tenant->id }})"
                                    class="{{ $tenant->is_active ? 'text-red-600 hover:text-red-900' : 'text-green-600 hover:text-green-900' }}">
                                {{ $tenant->is_active ? '冻结' : '激活' }}
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-6 py-12 text-center text-gray-500 text-sm">暂无租户</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
