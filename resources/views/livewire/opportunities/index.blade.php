<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">商机</h1>
            <p class="text-sm text-gray-500 mt-1">管理销售商机</p>
        </div>
        <div class="space-x-2">
            <a href="{{ route('opportunities.board') }}" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-medium rounded-lg">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                看板视图
            </a>
            <a href="{{ route('opportunities.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                新建商机
            </a>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm p-4 border border-gray-100 flex items-center gap-4 flex-wrap">
        <div class="relative max-w-md flex-1">
            <input wire:model.live="search" type="text" placeholder="搜索商机..."
                   class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            <svg class="absolute left-3 top-2.5 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        </div>
        <select wire:model.live="stage_filter" class="px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            <option value="">全部阶段</option>
            @foreach($stages as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
        </select>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">商机</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">公司</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">阶段</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">预计日期</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">金额</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">操作</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($opportunities as $o)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap"><a href="{{ route('opportunities.show', $o) }}" class="text-sm font-medium text-gray-900 hover:text-indigo-600">{{ $o->title }}</a></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $o->company?->name ?? '-' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap"><span class="px-2 py-1 text-xs font-medium bg-indigo-100 text-indigo-700 rounded-full">{{ $o->stage?->name ?? '-' }}</span></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $o->expected_close_date?->format('Y-m-d') ?? '-' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-semibold text-gray-900">¥{{ number_format($o->value ?? 0, 2) }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                            <a href="{{ route('opportunities.show', $o) }}" class="text-indigo-600 hover:text-indigo-900">查看</a>
                            <a href="{{ route('opportunities.edit', $o) }}" class="text-amber-600 hover:text-amber-900">编辑</a>
                            <button wire:confirm="确定删除？" wire:click="delete({{ $o->id }})" class="text-red-600 hover:text-red-900">删除</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-6 py-12 text-center text-gray-500 text-sm">暂无商机</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-6 py-4 border-t border-gray-100">{{ $opportunities->links() }}</div>
    </div>
</div>
