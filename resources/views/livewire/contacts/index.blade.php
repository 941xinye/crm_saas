<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">联系人</h1>
            <p class="text-sm text-gray-500 mt-1">管理您的客户联系人</p>
        </div>
        <a href="{{ route('contacts.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            新建联系人
        </a>
    </div>

    <div class="bg-white rounded-xl shadow-sm p-4 border border-gray-100">
        <div class="relative max-w-md">
            <input wire:model.live="search" type="text" placeholder="搜索姓名或邮箱..."
                   class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            <svg class="absolute left-3 top-2.5 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">姓名</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">职位</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">公司</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">联系方式</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">操作</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($contacts as $c)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap"><a href="{{ route('contacts.show', $c) }}" class="text-sm font-medium text-gray-900 hover:text-indigo-600">{{ $c->full_name }}</a></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $c->position ?? '-' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $c->company?->name ?? '-' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                            <div>{{ $c->email ?? '-' }}</div>
                            <div class="text-xs text-gray-500">{{ $c->phone ?? '' }}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                            <a href="{{ route('contacts.show', $c) }}" class="text-indigo-600 hover:text-indigo-900">查看</a>
                            <a href="{{ route('contacts.edit', $c) }}" class="text-amber-600 hover:text-amber-900">编辑</a>
                            <button wire:confirm="确定删除？" wire:click="delete({{ $c->id }})" class="text-red-600 hover:text-red-900">删除</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-6 py-12 text-center text-gray-500 text-sm">暂无联系人</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-6 py-4 border-t border-gray-100">{{ $contacts->links() }}</div>
    </div>
</div>
