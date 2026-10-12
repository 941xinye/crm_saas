<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">团队成员</h1>
        <p class="text-sm text-gray-500 mt-1">管理您租户下的所有用户</p>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">成员</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">邮箱</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">角色</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">操作</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($users as $u)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="w-8 h-8 bg-indigo-500 rounded-full flex items-center justify-center text-white text-xs font-medium mr-3">
                                    {{ strtoupper(mb_substr($u->name, 0, 1)) }}
                                </div>
                                <span class="text-sm font-medium text-gray-900">{{ $u->name }}</span>
                                @if($u->id === auth()->id())
                                    <span class="ml-2 text-xs bg-indigo-100 text-indigo-700 px-2 py-0.5 rounded">我</span>
                                @endif
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $u->email }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if($u->id === auth()->id())
                                <span class="px-2 py-1 text-xs font-medium bg-indigo-100 text-indigo-700 rounded-full">{{ $u->role }}</span>
                            @else
                                <select wire:change="updateRole({{ $u->id }}, $event.target.value)"
                                        class="text-sm px-3 py-1 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                                    <option value="admin" @selected($u->role === 'admin')>管理员</option>
                                    <option value="manager" @selected($u->role === 'manager')>经理</option>
                                    <option value="sales" @selected($u->role === 'sales')>销售</option>
                                </select>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            @if($u->id !== auth()->id())
                                <button wire:confirm="确定要移除 {{ $u->name }} 吗？" wire:click="removeMember({{ $u->id }})"
                                        class="text-red-600 hover:text-red-900">移除</button>
                            @else
                                <span class="text-gray-300">-</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-6 py-12 text-center text-gray-500 text-sm">暂无成员</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
