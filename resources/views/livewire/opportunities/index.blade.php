<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('opportunities.common_opportunity') }}</h1>
            <p class="text-sm text-gray-500 mt-1">{{ __('opportunities.index_subtitle') }}</p>
        </div>
        @unless($showTrashed)
        <div class="flex items-center gap-2">
            <a href="{{ route('opportunities.board') }}" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-medium rounded-lg">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                {{ __('opportunities.index_board_view') }}
            </a>
            <a href="{{ route('opportunities.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                {{ __('opportunities.common_create_opportunity') }}
            </a>
            <div class="flex items-center gap-1">
                <a href="#" wire:click.prevent="downloadTemplate" class="px-3 py-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-600 text-sm rounded-lg">{{ __('opportunities.index_template') }}</a>
                <a href="#" wire:click.prevent="export" class="px-3 py-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-600 text-sm rounded-lg">{{ __('opportunities.index_export') }}</a>
                <label class="px-3 py-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-600 text-sm rounded-lg cursor-pointer">
                    {{ __('opportunities.index_import') }}<input type="file" wire:model="importFile" wire:change="import" accept=".csv,.txt" class="hidden">
                </label>
            </div>
        </div>
        @endunless
    </div>

    <!-- Tab 切换：全部 / 已删除 -->
    <div class="flex items-center gap-1">
        <button wire:click="$set('showTrashed', false)"
                class="px-4 py-2 text-sm font-medium rounded-lg transition-colors @if(!$showTrashed) bg-indigo-600 text-white @else bg-white border border-gray-300 text-gray-600 hover:bg-gray-50 @endif">
            {{ __('opportunities.index_tab_all') }}
        </button>
        <button wire:click="$set('showTrashed', true)"
                class="px-4 py-2 text-sm font-medium rounded-lg transition-colors @if($showTrashed) bg-red-600 text-white @else bg-white border border-gray-300 text-gray-600 hover:bg-gray-50 @endif">
            {{ __('opportunities.index_tab_trashed') }}
        </button>
    </div>

    <div class="bg-white rounded-xl shadow-sm p-4 border border-gray-100 flex items-center gap-4 flex-wrap">
        <div class="relative max-w-md flex-1">
            <input wire:model.live="search" type="text" placeholder="{{ __('opportunities.index_search_placeholder') }}"
                   class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            <svg class="absolute left-3 top-2.5 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        </div>
        @unless($showTrashed)
        <select wire:model.live="stage_filter" class="px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            <option value="">{{ __('opportunities.index_filter_all_stages') }}</option>
            @foreach($stages as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
        </select>
        <select wire:model.live="owner_filter" class="px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm">
            <option value="">{{ __('opportunities.index_filter_all_owners') }}</option>
            @foreach($owners as $o)
                <option value="{{ $o->id }}">{{ $o->name }}</option>
            @endforeach
        </select>
        @if(count($cfDefinitions))
            <select wire:model.live="cf_key" class="px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                <option value="">{{ __('opportunities.index_filter_custom_field') }}</option>
                @foreach($cfDefinitions as $cf)
                    <option value="{{ $cf['key'] }}">{{ $cf['label'] }}</option>
                @endforeach
            </select>
            @if($cf_key)
                <input wire:model.live.debounce.300ms="cf_value" type="text" placeholder="{{ __('opportunities.index_filter_cf_value_placeholder') }}"
                       class="px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm max-w-[180px]">
            @endif
        @endif
        @endunless
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        @if(session('success'))
        <div class="bg-green-50 border-b border-green-100 px-6 py-3 text-sm text-green-700">
            {{ session('success') }}
        </div>
        @endif

        @unless($showTrashed)
        <!-- 批量操作栏 -->
        @if(count($selected) > 0)
        <div class="bg-indigo-50 border-b border-indigo-100 px-6 py-3 flex items-center justify-between flex-wrap gap-2">
            <div class="text-sm text-indigo-700">
                {{ __('opportunities.index_selected_prefix') }} <span class="font-semibold">{{ count($selected) }}</span> {{ __('opportunities.index_selected_suffix') }}
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <!-- 批量改阶段 -->
                <select wire:model="bulk_stage" class="px-2 py-1.5 text-xs border border-gray-300 rounded bg-white">
                    <option value="">{{ __('opportunities.index_bulk_stage_placeholder') }}</option>
                    @foreach($stages as $s)
                        <option value="{{ $s->id }}">{{ $s->name }}</option>
                    @endforeach
                </select>
                <button wire:click="bulkUpdateStage"
                        wire:confirm="{{ __('opportunities.index_bulk_stage_confirm') }}"
                        class="px-3 py-1.5 text-xs bg-indigo-600 hover:bg-indigo-700 text-white rounded">
                    {{ __('opportunities.index_bulk_apply_stage') }}
                </button>
                <!-- 批量改负责人 -->
                <select wire:model="bulk_owner" class="px-2 py-1.5 text-xs border border-gray-300 rounded bg-white">
                    <option value="">{{ __('opportunities.index_bulk_owner_placeholder') }}</option>
                    @foreach($owners as $o)
                        <option value="{{ $o->id }}">{{ $o->name }}</option>
                    @endforeach
                </select>
                <button wire:click="bulkUpdateOwner"
                        wire:confirm="{{ __('opportunities.index_bulk_owner_confirm') }}"
                        class="px-3 py-1.5 text-xs bg-blue-600 hover:bg-blue-700 text-white rounded">
                    {{ __('opportunities.index_bulk_assign') }}
                </button>
                <span class="w-px h-5 bg-indigo-200"></span>
                <button wire:click="$set('selected', [])" class="px-3 py-1.5 text-xs bg-white border border-gray-300 text-gray-600 rounded hover:bg-gray-50">
                    {{ __('opportunities.index_clear_selection') }}
                </button>
                <button wire:confirm="{{ __('opportunities.index_bulk_delete_confirm', ['count' => count($selected)]) }}"
                        wire:click="bulkDelete"
                        class="px-3 py-1.5 text-xs bg-red-600 hover:bg-red-700 text-white rounded">
                    {{ __('opportunities.index_bulk_delete') }}
                </button>
            </div>
        </div>
        @endif
        @endunless

        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    @unless($showTrashed)
                    <th class="px-4 py-3 w-10">
                        <input type="checkbox"
                               wire:model.live="selected"
                               value=""
                               class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    </th>
                    @endunless
                    @include('livewire.partials.sortable-th', ['column' => 'title', 'label' => __('opportunities.common_opportunity')])
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('opportunities.common_company') }}</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('opportunities.common_stage') }}</th>
                    @include('livewire.partials.sortable-th', ['column' => 'expected_close_date', 'label' => __('opportunities.index_col_expected_date')])
                    @include('livewire.partials.sortable-th', ['column' => 'value', 'label' => __('opportunities.common_value'), 'align' => 'right'])
                    @include('livewire.partials.sortable-th', ['column' => 'score', 'label' => __('opportunities.index_col_score')])
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('opportunities.index_col_actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($opportunities as $o)
                    <tr class="hover:bg-gray-50 @if($showTrashed) opacity-60 @endif">
                        @unless($showTrashed)
                        <td class="px-4 py-3">
                            <input type="checkbox"
                                   wire:model.live="selected"
                                   value="{{ $o->id }}"
                                   class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        </td>
                        @endunless
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if($showTrashed)
                                <span class="text-sm font-medium text-gray-500">{{ $o->title }}</span>
                            @else
                                <a href="{{ route('opportunities.show', $o) }}" class="text-sm font-medium text-gray-900 hover:text-indigo-600">{{ $o->title }}</a>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $o->company?->name ?? '-' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap"><span class="px-2 py-1 text-xs font-medium bg-indigo-100 text-indigo-700 rounded-full">{{ $o->stage?->name ?? '-' }}</span></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $o->expected_close_date?->format('Y-m-d') ?? '-' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-semibold text-gray-900">¥{{ number_format($o->value ?? 0, 2) }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if($o->score !== null)
                                @php
                                    $scoreColor = $o->score >= 80 ? 'bg-emerald-100 text-emerald-700'
                                        : ($o->score >= 60 ? 'bg-indigo-100 text-indigo-700'
                                        : ($o->score >= 40 ? 'bg-amber-100 text-amber-700'
                                        : 'bg-gray-100 text-gray-600'));
                                @endphp
                                <span class="px-2 py-1 text-xs font-semibold {{ $scoreColor }} rounded-full" title="{{ __('opportunities.common_score_tooltip', ['time' => $o->scored_at?->format('m-d H:i') ?? __('opportunities.common_score_pending')]) }}">{{ $o->score }}</span>
                            @else
                                <span class="text-xs text-gray-400">-</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                            @if($showTrashed)
                                <button wire:click="restore({{ $o->id }})" class="text-green-600 hover:text-green-900">{{ __('opportunities.index_restore') }}</button>
                                <button wire:confirm="{{ __('opportunities.index_force_delete_confirm') }}"
                                        wire:click="forceDelete({{ $o->id }})"
                                        class="text-red-600 hover:text-red-900">{{ __('opportunities.index_force_delete') }}</button>
                            @else
                                <a href="{{ route('opportunities.show', $o) }}" class="text-indigo-600 hover:text-indigo-900">{{ __('opportunities.common_view') }}</a>
                                <a href="{{ route('opportunities.edit', $o) }}" class="text-amber-600 hover:text-amber-900">{{ __('opportunities.common_edit') }}</a>
                                <button wire:confirm="{{ __('opportunities.index_delete_confirm') }}" wire:click="delete({{ $o->id }})" class="text-red-600 hover:text-red-900">{{ __('opportunities.common_delete') }}</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="{{ $showTrashed ? 7 : 8 }}" class="px-6 py-12 text-center text-gray-500 text-sm">{{ $showTrashed ? __('opportunities.index_empty_trashed') : __('opportunities.common_empty') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-6 py-4 border-t border-gray-100">{{ $opportunities->links() }}</div>
    </div>
</div>
