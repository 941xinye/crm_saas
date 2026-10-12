<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">线索</h1>
            <p class="text-sm text-gray-500 mt-1">管理潜在客户线索</p>
        </div>
        <a href="{{ route('leads.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            新建线索
        </a>
    </div>

    <div class="bg-white rounded-xl shadow-sm p-4 border border-gray-100">
        <div class="relative max-w-md">
            <input wire:model.live="search" type="text" placeholder="搜索线索..."
                   class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            <svg class="absolute left-3 top-2.5 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">名称</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">公司</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">来源</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">状态</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">负责人</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">操作</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($leads as $l)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap"><a href="{{ route('leads.show', $l) }}" class="text-sm font-medium text-gray-900 hover:text-indigo-600">{{ $l->name }}</a></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $l->company_name ?? '-' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $l->source ?? '-' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap"><span class="px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-700 rounded-full">{{ $l->status }}</span></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $l->owner?->name ?? '-' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                            <a href="{{ route('leads.show', $l) }}" class="text-indigo-600 hover:text-indigo-900">查看</a>
                            <a href="{{ route('leads.edit', $l) }}" class="text-amber-600 hover:text-amber-900">编辑</a>
                            <button wire:confirm="确定删除？" wire:click="delete({{ $l->id }})" class="text-red-600 hover:text-red-900">删除</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-6 py-12 text-center text-gray-500 text-sm">暂无线索</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-6 py-4 border-t border-gray-100">{{ $leads->links() }}</div>
    </div>
</div>
