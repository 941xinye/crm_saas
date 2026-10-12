<div class="max-w-4xl space-y-4">
    <h1 class="text-2xl font-bold text-gray-900">{{ __('misc.global_search') }}</h1>

    <div class="relative">
        <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
        </svg>
        <input type="text" wire:model.live.debounce.300ms="q"
               placeholder="{{ __('misc.search_placeholder') }}"
               class="w-full pl-10 pr-4 py-2.5 bg-white border border-gray-200 rounded-xl shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none"
               autofocus>
    </div>

    @if(trim($q) === '')
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-12 text-center">
            <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <p class="text-gray-500">{{ __('misc.enter_keyword') }}</p>
            <p class="text-xs text-gray-400 mt-1">{{ __('misc.search_hint') }}</p>
        </div>
    @elseif($total === 0)
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-12 text-center">
            <p class="text-gray-500">{{ __('misc.no_results_for', ['term' => $term]) }}</p>
        </div>
    @else
        <p class="text-sm text-gray-500">{{ __('misc.total_results', ['count' => $total]) }}</p>

        @php($sections = [
            'opportunities' => [__('misc.section_opportunities'), 'opportunities.show'],
            'companies' => [__('misc.section_companies'), 'companies.show'],
            'contacts' => [__('misc.section_contacts'), 'contacts.show'],
            'leads' => [__('misc.section_leads'), 'leads.show'],
        ])

        @foreach($sections as $key => [$label, $route])
            @php($items = $results[$key])
            @continue($items === null || $items->isEmpty())

            <div class="bg-white rounded-xl shadow-sm border border-gray-100">
                <div class="px-4 py-2.5 border-b border-gray-100 flex items-center justify-between">
                    <span class="text-xs font-semibold text-indigo-600 uppercase tracking-wide">{{ $label }}</span>
                    <span class="text-xs text-gray-400">{{ __('misc.count_items', ['count' => $items->count()]) }}</span>
                </div>
                <div class="divide-y divide-gray-50">
                    @foreach($items as $item)
                        <a href="{{ route($route, $item) }}"
                           class="flex items-center gap-3 px-4 py-3 hover:bg-indigo-50/40 transition-colors">
                            <div class="w-8 h-8 bg-indigo-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                <span class="text-xs font-bold text-indigo-600">{{ mb_substr($item->name ?? $item->title ?? $item->full_name ?? '?', 0, 1) }}</span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900 truncate">
                                    @if($key === 'contacts'){{ $item->full_name }}@else{{ $item->name ?? $item->title }}@endif
                                </p>
                                <p class="text-xs text-gray-500 truncate">
                                    @if($key === 'opportunities')
                                        {{ $item->company?->name ?? '—' }} · {{ $item->stage?->name ?? '—' }} · ¥{{ number_format((float) $item->value, 2) }}
                                    @elseif($key === 'companies')
                                        {{ $item->industry ?: __('misc.industry_not_set') }}{{ $item->phone ? " · {$item->phone}" : '' }}
                                    @elseif($key === 'contacts')
                                        {{ $item->company?->name ?? __('misc.no_company') }}{{ $item->email ? " · {$item->email}" : '' }}
                                    @elseif($key === 'leads')
                                        {{ $item->company_name ?: '—' }} · {{ $item->status }}
                                    @endif
                                </p>
                            </div>
                            <svg class="w-4 h-4 text-gray-300 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </a>
                    @endforeach
                </div>
            </div>
        @endforeach
    @endif
</div>
