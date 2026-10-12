<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
        <h3 class="text-sm font-semibold text-gray-900 uppercase tracking-wider">{{ __('reports.summary_title') }}</h3>
        <button wire:click="generate" wire:loading.attr="disabled"
                class="text-xs font-medium text-indigo-600 hover:text-indigo-800 disabled:opacity-50">
            <span wire:loading.remove wire:target="generate">{{ $summary ? __('reports.summary_regenerate') : __('reports.summary_generate') }}</span>
            <span wire:loading wire:target="generate">{{ __('reports.summary_generating') }}</span>
        </button>
    </div>
    <div class="px-6 py-4">
        @if($summary)
            <div class="text-sm text-gray-700 whitespace-pre-line leading-6">{{ $summary }}</div>
            <p class="text-xs text-gray-400 mt-2">{{ __('reports.summary_hint') }}</p>
        @elseif($unavailable)
            <p class="text-sm text-gray-500">{{ __('reports.summary_unavailable') }}</p>
        @else
            <p class="text-sm text-gray-400">{{ __('reports.summary_empty_hint') }}</p>
        @endif
    </div>
</div>
