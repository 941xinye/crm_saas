<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">商机阶段</h1>
            <p class="text-sm text-gray-500 mt-1">管理销售流程阶段、赢输标记与排序</p>
        </div>
        <a href="{{ route('stages.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            新建阶段
        </a>
    </div>

    <!-- 搜索框 -->
    <div class="bg-white rounded-xl shadow-sm p-4 border border-gray-100">
        <div class="relative max-w-md">
            <input wire:model.live="search" type="text" placeholder="搜索阶段名称..."
                   class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors">
            <svg class="absolute left-3 top-2.5 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">顺序</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">阶段名称</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">赢率假设</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">赢单</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">输单</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">商机数</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">操作</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($stages as $s)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $s->order }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                            {{ $s->name }}
                            @if($s->is_default)
                                <span class="ml-2 px-1.5 py-0.5 text-xs bg-gray-100 text-gray-600 rounded">默认</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center gap-2">
                                <div class="w-24 bg-gray-200 rounded-full h-2">
                                    <div class="bg-indigo-500 h-2 rounded-full" style="width: {{ $s->probability }}%"></div>
                                </div>
                                <span class="text-sm text-gray-600">{{ $s->probability }}%</span>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @can('update', $s)
                                <button wire:click="toggleWon({{ $s->id }})"
                                        class="px-2 py-1 text-xs rounded {{ $s->is_won ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-400 hover:bg-gray-200' }}">
                                    {{ $s->is_won ? '✓ 赢单' : '赢单' }}
                                </button>
                            @else
                                <span class="px-2 py-1 text-xs rounded {{ $s->is_won ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-400' }}">
                                    {{ $s->is_won ? '✓ 赢单' : '赢单' }}
                                </span>
                            @endcan
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @can('update', $s)
                                <button wire:click="toggleLost({{ $s->id }})"
                                        class="px-2 py-1 text-xs rounded {{ $s->is_lost ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-400 hover:bg-gray-200' }}">
                                    {{ $s->is_lost ? '✓ 输单' : '输单' }}
                                </button>
                            @else
                                <span class="px-2 py-1 text-xs rounded {{ $s->is_lost ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-400' }}">
                                    {{ $s->is_lost ? '✓ 输单' : '输单' }}
                                </span>
                            @endcan
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $s->opportunities_count }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                            <a href="{{ route('stages.edit', $s) }}" class="text-amber-600 hover:text-amber-900">编辑</a>
                            @can('delete', $s)
                                <button wire:confirm="确定删除阶段「{{ $s->name }}」吗？" wire:click="delete({{ $s->id }})" class="text-red-600 hover:text-red-900">删除</button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-6 py-12 text-center text-gray-500 text-sm">暂无阶段数据</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
