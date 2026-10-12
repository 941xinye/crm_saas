<div class="space-y-6">
    <!-- 页面标题 -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('opportunities.board_title') }}</h1>
            <p class="text-sm text-gray-500 mt-1">{{ __('opportunities.board_subtitle') }}<span class="font-semibold text-green-600">¥{{ number_format($totalValue, 2) }}</span></p>
        </div>
        <div class="space-x-2">
            <a href="{{ route('opportunities.index') }}" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-medium rounded-lg">
                {{ __('opportunities.board_list_view') }}
            </a>
            <a href="{{ route('opportunities.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                {{ __('opportunities.common_create_opportunity') }}
            </a>
        </div>
    </div>

    <!-- Kanban 看板 -->
    <div class="overflow-x-auto pb-4">
        <div wire:sortable-group="onCardMoved"
             class="flex gap-4 min-w-max"
             x-data="{ dragging: null }">

            @foreach($stages as $stage)
                <!-- 阶段列 -->
                <div class="w-72 flex-shrink-0 bg-gray-100 rounded-xl p-4 flex flex-col">
                    <!-- 列头 -->
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-semibold text-gray-900 flex items-center">
                            <span class="w-3 h-3 rounded-full bg-indigo-500 mr-2"></span>
                            {{ $stage->name }}
                        </h3>
                        <span class="px-2 py-0.5 text-xs font-medium bg-white text-gray-600 rounded-full border border-gray-200">
                            {{ $stage->opportunities->count() }}
                        </span>
                    </div>

                    <!-- 卡片容器 -->
                    <div wire:sortable-group.item-group="{{ $stage->id }}"
                         class="flex-1 space-y-3 min-h-[200px]"
                         wire:sortable-group.handle>

                        @forelse($stage->opportunities as $opp)
                            <!-- 商机卡片 -->
                            <div wire:sortable-group.item="{{ $opp->id }}"
                                 class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 cursor-grab hover:shadow-md hover:border-indigo-300 transition-all duration-200 select-none group"
                                 draggable="true">
                                <!-- 卡片头部：标题 -->
                                <div class="flex items-start justify-between mb-2">
                                    <a href="{{ route('opportunities.show', $opp) }}"
                                       class="text-sm font-semibold text-gray-900 hover:text-indigo-600 leading-snug">
                                        {{ $opp->title }}
                                    </a>
                                    <svg class="w-4 h-4 text-gray-300 group-hover:text-gray-400 flex-shrink-0 ml-2" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M6 2a2 2 0 1 0 0 4 2 2 0 0 0 0-4zM6 8a2 2 0 1 0 0 4 2 2 0 0 0 0-4zM6 14a2 2 0 1 0 0 4 2 2 0 0 0 0-4zM14 2a2 2 0 1 0 0 4 2 2 0 0 0 0-4zM14 8a2 2 0 1 0 0 4 2 2 0 0 0 0-4zM14 14a2 2 0 1 0 0 4 2 2 0 0 0 0-4z"/>
                                    </svg>
                                </div>

                                <!-- 卡片内容 -->
                                <div class="space-y-1.5">
                                    <div class="flex items-center justify-between">
                                        <span class="text-xs text-gray-500">{{ $opp->company?->name ?? __('opportunities.board_no_company') }}</span>
                                        <span class="text-sm font-bold text-green-600">¥{{ number_format($opp->value ?? 0, 0) }}</span>
                                    </div>
                                    <div class="flex items-center justify-between text-xs text-gray-500">
                                        <span>{{ __('opportunities.board_expected_prefix') }}{{ $opp->expected_close_date?->format('m/d') ?? '-' }}</span>
                                        <span class="px-1.5 py-0.5 rounded bg-gray-100">{{ $opp->probability ?? 0 }}%</span>
                                    </div>
                                    <div class="flex items-center text-xs text-gray-500">
                                        <div class="w-5 h-5 bg-indigo-500 rounded-full flex items-center justify-center text-white text-xs mr-1.5">
                                            {{ strtoupper(mb_substr($opp->owner?->name ?? '?', 0, 1)) }}
                                        </div>
                                        {{ $opp->owner?->name ?? '-' }}
                                    </div>
                                </div>

                                <!-- 降级：快速改变阶段下拉框 -->
                                <div class="mt-3 pt-2 border-t border-gray-100 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <select wire:change="changeStage({{ $opp->id }}, $event.target.value)"
                                            class="w-full text-xs px-2 py-1 bg-gray-50 border border-gray-200 rounded focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500">
                                        @foreach($stages as $s)
                                            <option value="{{ $s->id }}" {{ $s->id == $stage->id ? 'selected' : '' }}>
                                                → {{ $s->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        @empty
                            <!-- 空状态提示 -->
                            <div class="border-2 border-dashed border-gray-300 rounded-lg p-4 text-center text-gray-400 text-sm">
                                {{ __('opportunities.common_empty') }}
                            </div>
                        @endforelse
                    </div>
                </div>
            @endforeach

            @if($stages->isEmpty())
                <div class="w-full text-center py-12 text-gray-500">
                    <p class="text-sm">{{ __('opportunities.board_no_stages') }}</p>
                </div>
            @endif
        </div>
    </div>

    <!-- 使用说明 -->
    <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
        <div class="flex items-start">
            <svg class="w-5 h-5 text-blue-600 mr-2 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <div class="text-sm text-blue-700">
                <p class="font-medium">{{ __('opportunities.board_hint_title') }}</p>
                <p class="mt-0.5 text-blue-600">{{ __('opportunities.board_hint_body') }}</p>
            </div>
        </div>
    </div>
</div>
