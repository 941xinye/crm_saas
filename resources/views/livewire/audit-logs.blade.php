<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">{{ __('audit.title') }}</h1>
        <p class="text-sm text-gray-500 mt-1">{{ __('audit.subtitle') }}</p>
    </div>

    <!-- 筛选栏 -->
    <div class="bg-white rounded-xl shadow-sm p-4 border border-gray-100">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <input wire:model.live="search" type="text" placeholder="{{ __('audit.search_placeholder') }}"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
            <select wire:model.live="action_filter" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">{{ __('audit.filter_all_actions') }}</option>
                @foreach($actions as $k => $v)
                    <option value="{{ $k }}">{{ $v }}</option>
                @endforeach
            </select>
            <select wire:model.live="model_filter" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">{{ __('audit.filter_all_models') }}</option>
                @foreach($models as $k => $v)
                    <option value="{{ $k }}">{{ $v }}</option>
                @endforeach
            </select>
            <div class="flex gap-2">
                <input wire:model.live="date_from" type="date" class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <input wire:model.live="date_to" type="date" class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
            <button wire:click="exportCsv" wire:loading.attr="disabled"
                    class="ml-auto px-3 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-lg transition-colors whitespace-nowrap">
                {{ __('audit.export_csv') }}
            </button>
        </div>
    </div>

    <!-- 表格 -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('audit.col_time') }}</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('audit.col_action') }}</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('audit.col_object') }}</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('audit.col_operator') }}</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">IP</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('audit.col_detail') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($logs as $log)
                    @php
                        $isBatchSummary = str_starts_with($log->action, 'batch_');
                        $batchRowClass = $log->batch_id && !$isBatchSummary ? 'bg-purple-50/30' : '';
                        $hasDetail = ($log->before || $log->after) && !empty(array_filter(array_merge($log->before ?? [], $log->after ?? [])));
                    @endphp
                    <tr class="hover:bg-gray-50 {{ $batchRowClass }}" x-data="{ open: false }">
                        <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-600">
                            {{ $log->created_at->format('Y-m-d H:i:s') }}
                            @if($log->batch_id)
                                <div class="mt-0.5 text-[10px] text-purple-500 font-mono">
                                    📦 {{ substr($log->batch_id, 0, 8) }}…
                                </div>
                            @endif
                        </td>
                        <td class="px-6 py-3 whitespace-nowrap">
                            @if($isBatchSummary)
                                <span class="px-2 py-0.5 text-xs rounded-full bg-purple-100 text-purple-700 font-medium">
                                    {{ __('audit.batch_summary') }}
                                </span>
                                @if($log->action === 'batch_deleted')
                                    <span class="px-2 py-0.5 text-xs rounded-full bg-red-100 text-red-700 ml-1">{{ __('audit.action_batch_deleted') }}</span>
                                @elseif($log->action === 'batch_updated')
                                    <span class="px-2 py-0.5 text-xs rounded-full bg-blue-100 text-blue-700 ml-1">{{ __('audit.action_batch_updated') }}</span>
                                @else
                                    <span class="px-2 py-0.5 text-xs rounded-full bg-gray-100 text-gray-700 ml-1">{{ $log->action }}</span>
                                @endif
                            @elseif($log->action === 'created')
                                <span class="px-2 py-0.5 text-xs rounded-full bg-green-100 text-green-700">{{ __('audit.action_created') }}</span>
                            @elseif($log->action === 'updated')
                                <span class="px-2 py-0.5 text-xs rounded-full bg-blue-100 text-blue-700">{{ __('audit.action_updated') }}</span>
                            @elseif($log->action === 'restored')
                                <span class="px-2 py-0.5 text-xs rounded-full bg-teal-100 text-teal-700">{{ __('audit.action_restored') }}</span>
                            @else
                                <span class="px-2 py-0.5 text-xs rounded-full bg-red-100 text-red-700">{{ __('audit.action_deleted') }}</span>
                            @endif
                        </td>
                        <td class="px-6 py-3 whitespace-nowrap text-sm">
                            @php
                                $modelShort = basename(str_replace('\\', '/', $log->auditable_type));
                                $modelLabel = $models[$log->auditable_type] ?? $modelShort;
                            @endphp
                            <span class="text-gray-500">{{ $modelLabel }}</span>
                            @if($log->auditable_id > 0)
                                <span class="text-gray-900 font-medium">#{{ $log->auditable_id }}</span>
                            @else
                                <span class="text-gray-400 text-xs">{{ __('audit.batch_level') }}</span>
                            @endif
                        </td>
                        <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-700">
                            {{ $log->user?->name ?? __('misc.system') }}
                        </td>
                        <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-500 font-mono">{{ $log->ip ?? '-' }}</td>
                        <td class="px-6 py-3 text-sm">
                            {{-- 摘要 --}}
                            <div class="flex items-center gap-2">
                                @if($isBatchSummary && $log->after)
                                    <span class="text-gray-700">
                                        <span class="font-medium">{{ $log->after['count'] ?? 0 }}</span> {{ __('audit.count_suffix') }}
                                        @if(($log->after['status'] ?? '') === 'partial')
                                            <span class="text-amber-600 text-xs ml-1">{{ __('audit.partial_success') }}</span>
                                        @endif
                                    </span>
                                @elseif($log->action === 'created' && $log->after)
                                    @foreach(array_slice($log->after, 0, 2, true) as $k => $v)
                                        <span class="inline-block mr-2 text-gray-500">{{ $k }}:</span><span class="text-gray-700">{{ is_scalar($v) ? $v : json_encode($v) }}</span>
                                    @endforeach
                                @elseif($log->action === 'updated' || $log->action === 'restored')
                                    @php $changedCount = 0; @endphp
                                    @foreach($log->before ?? [] as $k => $old)
                                        @php $new = $log->after[$k] ?? null; @endphp
                                        @if($old !== $new)
                                            @php $changedCount++; @endphp
                                            @if($changedCount <= 2)
                                                <span class="inline-block mr-2 text-gray-500">{{ $k }}:</span>
                                                <span class="text-red-500 line-through">{{ is_scalar($old) ? $old : json_encode($old) }}</span>
                                                →
                                                <span class="text-green-600">{{ is_scalar($new) ? $new : json_encode($new) }}</span>
                                            @endif
                                        @endif
                                    @endforeach
                                    @if($changedCount > 2)
                                        <span class="text-xs text-gray-400">{{ __('audit.changed_more', ['count' => $changedCount - 2]) }}</span>
                                    @endif
                                @elseif($log->action === 'deleted' && $log->before)
                                    @foreach(array_slice($log->before, 0, 2, true) as $k => $v)
                                        <span class="inline-block mr-2 text-gray-500">{{ $k }}:</span><span class="text-gray-700">{{ is_scalar($v) ? $v : json_encode($v) }}</span>
                                    @endforeach
                                @endif
                                {{-- 展开按钮 --}}
                                @if($hasDetail)
                                    <button x-on:click="open = !open"
                                            class="ml-1 text-indigo-500 hover:text-indigo-700 text-xs whitespace-nowrap">
                                        <span x-show="!open">{{ __('audit.expand') }}</span>
                                        <span x-show="open" x-cloak>{{ __('audit.collapse') }}</span>
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    {{-- 展开详情行：字段级 diff 对比 --}}
                    @if($hasDetail)
                    <tr x-show="open" x-cloak class="bg-gray-50/50">
                        <td colspan="6" class="px-6 py-4">
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm border border-gray-200 rounded-lg">
                                    <thead>
                                        <tr class="bg-gray-100">
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase w-1/3">{{ __('audit.col_field') }}</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase w-1/3">
                                                @if($log->action === 'created') {{ __('audit.col_new_value') }}
                                                @elseif($log->action === 'deleted') {{ __('audit.col_old_value') }}
                                                @else {{ __('audit.col_old_value') }}
                                                @endif
                                            </th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase w-1/3">{{ __('audit.col_new_value') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        @php
                                            $allKeys = array_unique(array_merge(array_keys($log->before ?? []), array_keys($log->after ?? [])));
                                            sort($allKeys);
                                        @endphp
                                        @foreach($allKeys as $key)
                                            @php
                                                $oldVal = $log->before[$key] ?? null;
                                                $newVal = $log->after[$key] ?? null;
                                                $isChanged = $oldVal !== $newVal;
                                            @endphp
                                            <tr class="{{ $isChanged ? 'bg-amber-50' : '' }}">
                                                <td class="px-4 py-2 font-medium text-gray-700">{{ $key }}</td>
                                                <td class="px-4 py-2">
                                                    @if($log->action === 'created')
                                                        <span class="text-gray-400">—</span>
                                                    @else
                                                        <span class="{{ $isChanged ? 'text-red-600 line-through' : 'text-gray-600' }}">
                                                            {{ is_scalar($oldVal) ? ($oldVal ?? '(null)') : json_encode($oldVal) }}
                                                        </span>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-2">
                                                    @if($log->action === 'deleted')
                                                        <span class="text-gray-400">—</span>
                                                    @else
                                                        <span class="{{ $isChanged ? 'text-green-700 font-medium' : 'text-gray-600' }}">
                                                            {{ is_scalar($newVal) ? ($newVal ?? '(null)') : json_encode($newVal) }}
                                                        </span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </td>
                    </tr>
                    @endif
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center text-gray-500 text-sm">{{ __('audit.empty_logs') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-6 py-4 border-t border-gray-100">
            {{ $logs->links() }}
        </div>
    </div>
</div>

<style>
    [x-cloak] { display: none !important; }
</style>
