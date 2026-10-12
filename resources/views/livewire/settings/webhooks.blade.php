<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('settings_webhooks.title') }}</h1>
            <p class="text-sm text-gray-500 mt-1">{{ __('settings_webhooks.subtitle') }}</p>
        </div>
        @if($canManage && !$showForm)
        <button wire:click="showCreateForm"
                class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition-colors">
            {{ __('settings_webhooks.create_webhook') }}
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
        <p class="font-semibold mb-1">{{ __('settings_webhooks.new_secret_title') }}</p>
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
            {{ $editingId ? __('settings_webhooks.edit_webhook') : __('settings_webhooks.create_webhook') }}
        </h3>
        <form wire:submit="save" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('settings_webhooks.name_label') }}</label>
                    <input wire:model="name" type="text"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                           placeholder="{{ __('settings_webhooks.name_placeholder') }}">
                    @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('settings_webhooks.url_label') }}</label>
                    <input wire:model="url" type="url"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                           placeholder="https://example.com/webhooks/crm">
                    @error('url') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('settings_webhooks.events_label') }}</label>
                <div class="border border-gray-200 rounded-lg overflow-hidden">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500"></th>
                                @foreach($actions as $action)
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">
                                    {{ ['created' => __('settings_webhooks.event_created'), 'updated' => __('settings_webhooks.event_updated'), 'deleted' => __('settings_webhooks.event_deleted')][$action] }}
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
                <label class="text-sm text-gray-700">{{ __('settings_webhooks.enable') }}</label>
            </div>

            <div class="flex gap-3">
                <button type="submit"
                        class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition-colors">
                    {{ __('settings_webhooks.save') }}
                </button>
                <button type="button" wire:click="closeForm"
                        class="px-4 py-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-600 text-sm font-medium rounded-lg transition-colors">
                    {{ __('settings_webhooks.cancel') }}
                </button>
            </div>
        </form>
    </div>
    @endif

    {{-- Webhook 列表 --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-3 border-b border-gray-100">
            <h3 class="text-sm font-semibold text-gray-700">{{ __('settings_webhooks.list_title', ['count' => $webhooks->count()]) }}</h3>
        </div>
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('settings_webhooks.name_label') }}</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('settings_webhooks.url_label') }}</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('settings_webhooks.events_label') }}</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('settings_webhooks.col_status') }}</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('settings_webhooks.col_last_sent') }}</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">{{ __('settings_webhooks.col_actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($webhooks as $webhook)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 text-sm font-medium text-gray-800">{{ $webhook->name }}</td>
                    <td class="px-6 py-4 text-sm text-gray-500 max-w-[220px] truncate" title="{{ $webhook->url }}">
                        {{ $webhook->url }}
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600">
                        <span class="px-2 py-0.5 text-xs rounded-full bg-indigo-50 text-indigo-700">
                            {{ __('settings_webhooks.event_count', ['count' => count($webhook->events ?? [])]) }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm">
                        @if($webhook->is_active)
                        <span class="px-2 py-0.5 text-xs rounded-full bg-green-100 text-green-700">{{ __('settings_webhooks.enable') }}</span>
                        @else
                        <span class="px-2 py-0.5 text-xs rounded-full bg-gray-100 text-gray-500">{{ __('settings_webhooks.disable') }}</span>
                        @endif
                        @if($webhook->failure_count > 0)
                        <span class="ml-1 px-2 py-0.5 text-xs rounded-full bg-red-100 text-red-700"
                              title="{{ __('settings_webhooks.last_failed', ['time' => $webhook->last_failed_at?->format('Y-m-d H:i')]) }}{{ $webhook->last_response ? "\n".__('settings_webhooks.last_response', ['response' => $webhook->last_response]) : '' }}">
                            {{ __('settings_webhooks.failed_count', ['count' => $webhook->failure_count]) }}
                        </span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-500">
                        {{ $webhook->last_sent_at?->format('m-d H:i') ?? __('settings_webhooks.never') }}
                    </td>
                    <td class="px-6 py-4 text-right space-x-2 whitespace-nowrap">
                        @if($canManage)
                        <button wire:click="sendTest({{ $webhook->id }})" wire:loading.attr="disabled"
                                class="text-indigo-600 hover:text-indigo-900 text-sm">{{ __('settings_webhooks.test') }}</button>
                        <button wire:click="edit({{ $webhook->id }})"
                                class="text-gray-600 hover:text-gray-900 text-sm">{{ __('settings_webhooks.edit') }}</button>
                        <button wire:confirm="{{ __('settings_webhooks.confirm_reset_secret') }}" wire:click="resetSecret({{ $webhook->id }})"
                                class="text-amber-600 hover:text-amber-900 text-sm">{{ __('settings_webhooks.reset_secret') }}</button>
                        <button wire:click="toggleActive({{ $webhook->id }})"
                                class="text-indigo-600 hover:text-indigo-900 text-sm">
                            {{ $webhook->is_active ? __('settings_webhooks.disable') : __('settings_webhooks.enable') }}
                        </button>
                        <button wire:confirm="{{ __('settings_webhooks.confirm_delete') }}" wire:click="delete({{ $webhook->id }})"
                                class="text-red-600 hover:text-red-900 text-sm">{{ __('settings_webhooks.delete') }}</button>
                        @else
                        <span class="text-gray-400 text-xs">{{ __('settings_webhooks.read_only') }}</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-6 py-12 text-center text-sm text-gray-500">
                        {{ __('settings_webhooks.empty_state') }}
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- 签名验证提示 --}}
    <div class="bg-gray-50 border border-gray-200 rounded-lg px-4 py-3 text-xs text-gray-500 space-y-1">
        <p class="font-semibold text-gray-600">{{ __('settings_webhooks.signature_title') }}</p>
        <p>{!! __('settings_webhooks.signature_hint') !!}</p>
    </div>
</div>
