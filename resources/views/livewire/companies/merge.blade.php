<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
    <h3 class="text-sm font-semibold text-gray-900 uppercase tracking-wider mb-2">{{ __('merge.company_title') }}</h3>

    @if($candidates->isEmpty())
        <p class="text-sm text-gray-400">{{ __('merge.company_no_candidates') }}</p>
    @else
        <p class="text-xs text-gray-500 mb-3">{{ __('merge.company_desc') }}</p>
        @error('sourceId') <p class="text-red-500 text-xs mb-2">{{ $message }}</p> @enderror

        <div class="flex flex-wrap items-center gap-2">
            <select wire:model="sourceId" class="text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 max-w-md">
                <option value="">{{ __('merge.select_placeholder') }}</option>
                @foreach($candidates as $candidate)
                    <option value="{{ $candidate->id }}">{{ $candidate->name }} (#{{ $candidate->id }})</option>
                @endforeach
            </select>
            <button wire:click="confirm" wire:loading.attr="disabled"
                    class="text-sm font-medium text-red-600 hover:text-red-700">{{ __('merge.confirm_btn') }}</button>
        </div>

        <div wire:transition class="mt-4 bg-red-50 border border-red-200 rounded-lg p-4" @if(!$confirming) style="display:none" @endif>
            <p class="text-sm text-red-700">{{ __('merge.company_confirm_warning') }}</p>
            <div class="mt-3 space-x-2">
                <button wire:click="merge" wire:loading.attr="disabled"
                        class="bg-red-600 hover:bg-red-700 text-white text-sm font-medium px-4 py-2 rounded-lg">{{ __('merge.company_btn') }}</button>
                <button wire:click="cancel"
                        class="bg-white border border-gray-300 text-gray-700 text-sm font-medium px-4 py-2 rounded-lg">{{ __('merge.cancel_btn') }}</button>
            </div>
        </div>
    @endif
</div>
