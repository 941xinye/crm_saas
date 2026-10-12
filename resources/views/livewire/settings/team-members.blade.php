<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">团队成员</h1>
        <p class="text-sm text-gray-500 mt-1">管理租户成员及其角色</p>
    </div>

    @if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg text-sm">
        {{ session('success') }}
    </div>
    @endif
    @if(session('error'))
    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">
        {{ session('error') }}
    </div>
    @endif

    {{-- 邀请表单（仅管理员可见） --}}
    @if(auth()->user()->role === 'admin')
    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
        <h3 class="text-sm font-semibold text-gray-700 mb-4">邀请新成员</h3>
        <form wire:submit="inviteMember" class="flex items-end gap-3 flex-wrap">
            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs font-medium text-gray-500 mb-1">邮箱</label>
                <input wire:model="inviteEmail" type="email"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                       placeholder="name@example.com">
                @error('inviteEmail') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="w-32">
                <label class="block text-xs font-medium text-gray-500 mb-1">角色</label>
                <select wire:model="inviteRole"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="sales">销售</option>
                    <option value="manager">经理</option>
                    <option value="admin">管理员</option>
                </select>
            </div>
            <button type="submit"
                    class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition-colors disabled:opacity-50"
                    wire:loading.attr="disabled">
                <span wire:loading.remove>发送邀请</span>
                <span wire:loading>发送中...</span>
            </button>
        </form>
    </div>
    @endif

    {{-- 待接受邀请列表 --}}
    @if($pendingInvitations->isNotEmpty())
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-3 bg-amber-50 border-b border-amber-100">
            <h3 class="text-sm font-semibold text-amber-700">待接受邀请 ({{ $pendingInvitations->count() }})</h3>
        </div>
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-2 text-left text-xs font-medium text-gray-500 uppercase">邮箱</th>
                    <th class="px-6 py-2 text-left text-xs font-medium text-gray-500 uppercase">角色</th>
                    <th class="px-6 py-2 text-left text-xs font-medium text-gray-500 uppercase">发送时间</th>
                    <th class="px-6 py-2 text-left text-xs font-medium text-gray-500 uppercase">过期时间</th>
                    <th class="px-6 py-2 text-right text-xs font-medium text-gray-500 uppercase">操作</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($pendingInvitations as $invitation)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-3 text-sm text-gray-800">{{ $invitation->email }}</td>
                    <td class="px-6 py-3 text-sm">
                        <span class="px-2 py-0.5 text-xs rounded-full bg-indigo-100 text-indigo-700">
                            {{ match($invitation->role) { 'admin' => '管理员', 'manager' => '经理', 'sales' => '销售', default => $invitation->role } }}
                        </span>
                    </td>
                    <td class="px-6 py-3 text-sm text-gray-500">{{ $invitation->created_at->format('Y-m-d H:i') }}</td>
                    <td class="px-6 py-3 text-sm {{ $invitation->expires_at->isPast() ? 'text-red-600' : 'text-gray-500' }}">
                        {{ $invitation->expires_at->format('Y-m-d H:i') }}
                    </td>
                    <td class="px-6 py-3 text-right">
                        @if(auth()->user()->role === 'admin')
                        <button wire:confirm="确定取消此邀请？" wire:click="cancelInvitation({{ $invitation->id }})"
                                class="text-red-600 hover:text-red-900 text-sm">取消</button>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    {{-- 成员列表 --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-3 border-b border-gray-100">
            <h3 class="text-sm font-semibold text-gray-700">成员列表 ({{ $users->count() }})</h3>
        </div>
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">姓名</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">邮箱</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">角色</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">加入时间</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">操作</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($users as $user)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-3 text-sm text-gray-900 font-medium">
                        {{ $user->name }}
                        @if($user->id === auth()->id())
                            <span class="text-xs text-gray-400">(我)</span>
                        @endif
                    </td>
                    <td class="px-6 py-3 text-sm text-gray-600">{{ $user->email }}</td>
                    <td class="px-6 py-3 text-sm">
                        @if(auth()->user()->role === 'admin' && $user->id !== auth()->id())
                        <select wire:change="updateRole({{ $user->id }}, $event.target.value)"
                                class="text-sm border border-gray-300 rounded-lg px-2 py-1">
                            <option value="sales" @selected($user->role === 'sales')>销售</option>
                            <option value="manager" @selected($user->role === 'manager')>经理</option>
                            <option value="admin" @selected($user->role === 'admin')>管理员</option>
                        </select>
                        @else
                        <span class="px-2 py-0.5 text-xs rounded-full bg-indigo-100 text-indigo-700">
                            {{ match($user->role) { 'admin' => '管理员', 'manager' => '经理', 'sales' => '销售', default => $user->role } }}
                        </span>
                        @endif
                    </td>
                    <td class="px-6 py-3 text-sm text-gray-500">{{ $user->created_at->format('Y-m-d') }}</td>
                    <td class="px-6 py-3 text-right text-sm font-medium">
                        @if(auth()->user()->role === 'admin' && $user->id !== auth()->id())
                        <button wire:confirm="确定移除 {{ $user->name }}？" wire:click="removeMember({{ $user->id }})"
                                class="text-red-600 hover:text-red-900">移除</button>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="px-6 py-12 text-center text-gray-400 text-sm">暂无成员</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
