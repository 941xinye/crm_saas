<div class="max-w-3xl space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">API Tokens</h1>
        <p class="text-sm text-gray-500 mt-1">创建与管理访问 REST API 的令牌，请求时携带
            <code class="bg-gray-100 px-1 rounded">Authorization: Bearer &lt;token&gt;</code></p>
    </div>

    @if($newToken)
        <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg text-sm">
            <p class="font-medium mb-1">Token 已创建，请立即保存（关闭后将无法再次查看）：</p>
            <code class="block bg-white border border-green-200 rounded px-3 py-2 font-mono text-xs break-all">{{ $newToken }}</code>
        </div>
    @endif

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <form wire:submit="create" class="space-y-4">
            <div class="flex gap-3">
                <input type="text" wire:model="name" placeholder="Token 名称（如：报表集成）"
                       class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-5 py-2 rounded-lg whitespace-nowrap">
                    创建 Token
                </button>
            </div>
            @error('name') <p class="text-red-600 text-xs mt-2">{{ $message }}</p> @enderror

            {{-- 权限范围勾选：按需最小授权 --}}
            <div>
                <p class="text-xs font-medium text-gray-500 uppercase mb-2">权限范围（Scopes）</p>
                <div class="flex gap-6">
                    @foreach($scopes as $scope => $label)
                        <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" wire:model="scopes.{{ $scope }}"
                                   class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
                @error('scopes') <p class="text-red-600 text-xs mt-2">{{ $message }}</p> @enderror
                <p class="text-xs text-gray-400 mt-2">只读集成（如报表）建议仅勾选「读取」；读写操作（创建/更新/删除/上传）需要「写入」。</p>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">名称</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">权限范围</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">创建时间</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">最近使用</th>
                    <th class="px-6 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($tokens as $token)
                    <tr>
                        <td class="px-6 py-4 text-sm text-gray-900">{{ $token->name }}</td>
                        <td class="px-6 py-4 text-sm">
                            @foreach($token->abilities ?? [] as $ability)
                                <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 mr-1">{{ $ability }}</span>
                            @endforeach
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500">{{ $token->created_at?->format('Y-m-d H:i') }}</td>
                        <td class="px-6 py-4 text-sm text-gray-500">
                            {{ $token->last_used_at?->format('Y-m-d H:i') ?? '从未使用' }}
                        </td>
                        <td class="px-6 py-4 text-right">
                            <button wire:confirm="确定吊销「{{ $token->name }}」？使用该 Token 的集成将立即失效。"
                                    wire:click="revoke({{ $token->id }})"
                                    class="text-red-600 hover:text-red-900 text-sm">吊销</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-6 py-12 text-center text-gray-500 text-sm">还没有 Token</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
