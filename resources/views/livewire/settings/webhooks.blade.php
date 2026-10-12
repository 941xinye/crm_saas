<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Webhook 管理</h1>
            <p class="text-sm text-gray-500 mt-1">配置业务事件推送到外部系统（企业版功能）</p>
        </div>
        @if($canManage && !$showForm)
        <button wire:click="showCreateForm"
                class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition-colors">
            新建 Webhook
        </button>
        @endif
    </div>

    @if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg text-sm">
        {{ session('success') }}
    </div>
    @endif
    @if(session('new_secret'))
    <div class="bg-amber-50 border border-amber-300 text-amber-800 px-4 py-3 rounded-lg text-sm">
        <p class="font-semibold mb-1">新的 Signing Secret（仅此一次展示，请立即复制保存）：</p>
        <code class="block bg-white/70 px-3 py-2 rounded font-mono text-xs break-all">{{ session('new_secret') }}</code>
    </div>
    @endif
    @if(session('error'))
    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">
        {{ session('error') }}
    </div>
    @endif

    {{-- 创建/编辑表单 --}}
    @if($showForm)
    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
        <h3 class="text-sm font-semibold text-gray-700 mb-4">
            {{ $editingId ? '编辑 Webhook' : '新建 Webhook' }}
        </h3>
        <form wire:submit="save" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">名称</label>
                    <input wire:model="name" type="text"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                           placeholder="如：ERP 同步">
                    @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">接收 URL</label>
                    <input wire:model="url" type="url"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                           placeholder="https://example.com/webhooks/crm">
                    @error('url') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-500 mb-2">订阅事件</label>
                <div class="border border-gray-200 rounded-lg overflow-hidden">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500"></th>
                                @foreach($actions as $action)
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">
                                    {{ ['created' => '创建', 'updated' => '更新', 'deleted' => '删除'][$action] }}
                                </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($models as $prefix => $label)
                            <tr>
                                <td class="px-4 py-2 font-medium text-gray-700">{{ $label }}</td>
                                @foreach($actions as $action)
                                <td class="px-4 py-2">
                                    <input type="checkbox" value="{{ $prefix }}.{{ $action }}"
                                           wire:model="selectedEvents"
                                           class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                </td>
                                @endforeach
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @error('selectedEvents') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="flex items-center gap-2">
                <input type="checkbox" wire:model="isActive"
                       class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                <label class="text-sm text-gray-700">启用</label>
            </div>

            <div class="flex gap-3">
                <button type="submit"
                        class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition-colors">
                    保存
                </button>
                <button type="button" wire:click="closeForm"
                        class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-lg transition-colors">
                    取消
                </button>
            </div>
        </form>
    </div>
    @endif

    {{-- Webhook 列表 --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-3 border-b border-gray-100">
            <h3 class="text-sm font-semibold text-gray-700">Webhook 列表 ({{ $webhooks->count() }})</h3>
        </div>
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">名称</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">接收 URL</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">订阅事件</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">状态</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">最近推送</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">操作</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($webhooks as $webhook)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-3 text-sm font-medium text-gray-800">{{ $webhook->name }}</td>
                    <td class="px-6 py-3 text-sm text-gray-500 max-w-[220px] truncate" title="{{ $webhook->url }}">
                        {{ $webhook->url }}
                    </td>
                    <td class="px-6 py-3 text-sm text-gray-600">
                        <span class="px-2 py-0.5 text-xs rounded-full bg-indigo-50 text-indigo-700">
                            {{ count($webhook->events ?? []) }} 个事件
                        </span>
                    </td>
                    <td class="px-6 py-3 text-sm">
                        @if($webhook->is_active)
                        <span class="px-2 py-0.5 text-xs rounded-full bg-green-100 text-green-700">启用</span>
                        @else
                        <span class="px-2 py-0.5 text-xs rounded-full bg-gray-100 text-gray-500">停用</span>
                        @endif
                        @if($webhook->failure_count > 0)
                        <span class="ml-1 px-2 py-0.5 text-xs rounded-full bg-red-100 text-red-700"
                              title="最近失败：{{ $webhook->last_failed_at?->format('Y-m-d H:i') }}{{ $webhook->last_response ? "\n最近响应：" . $webhook->last_response : '' }}">
                            失败 {{ $webhook->failure_count }} 次
                        </span>
                        @endif
                    </td>
                    <td class="px-6 py-3 text-sm text-gray-500">
                        {{ $webhook->last_sent_at?->format('m-d H:i') ?? '从未' }}
                    </td>
                    <td class="px-6 py-3 text-right space-x-2 whitespace-nowrap">
                        @if($canManage)
                        <button wire:click="sendTest({{ $webhook->id }})" wire:loading.attr="disabled"
                                class="text-indigo-600 hover:text-indigo-900 text-sm">测试</button>
                        <button wire:click="edit({{ $webhook->id }})"
                                class="text-gray-600 hover:text-gray-900 text-sm">编辑</button>
                        <button wire:confirm="重置后旧 Secret 立即失效，确定？" wire:click="resetSecret({{ $webhook->id }})"
                                class="text-amber-600 hover:text-amber-900 text-sm">重置 Secret</button>
                        <button wire:click="toggleActive({{ $webhook->id }})"
                                class="text-blue-600 hover:text-blue-900 text-sm">
                            {{ $webhook->is_active ? '停用' : '启用' }}
                        </button>
                        <button wire:confirm="确定删除此 Webhook？" wire:click="delete({{ $webhook->id }})"
                                class="text-red-600 hover:text-red-900 text-sm">删除</button>
                        @else
                        <span class="text-gray-400 text-xs">只读</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-6 py-8 text-center text-sm text-gray-400">
                        尚未配置 Webhook。创建后业务事件（创建/更新/删除）将实时推送到您的系统。
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- 签名验证提示 --}}
    <div class="bg-gray-50 border border-gray-200 rounded-lg px-4 py-3 text-xs text-gray-500 space-y-1">
        <p class="font-semibold text-gray-600">接收端签名验证（HMAC-SHA256）：</p>
        <p>签名串 = <code class="font-mono">{{ '{timestamp}.{request_body}' }}</code>，密钥为 Webhook 的 Secret；请求头 <code class="font-mono">X-CRM-Signature</code> / <code class="font-mono">X-CRM-Timestamp</code> / <code class="font-mono">X-CRM-Event</code>。详见 <a href="/docs" class="text-indigo-600">Webhook 集成文档</a>。</p>
    </div>
</div>
