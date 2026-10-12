<!-- 两步验证挑战页 -->
<div>
    <h2 class="text-2xl font-bold text-gray-900 text-center mb-2">{{ __('auth.two_factor') }}</h2>
    <p class="text-gray-500 text-center text-sm mb-6">{{ __('auth.two_factor_hint') }}</p>

    <form wire:submit="verify" class="space-y-4">
        <div>
            <label for="code" class="block text-sm font-medium text-gray-700 mb-1">{{ __('auth.code_label') }}</label>
            <input wire:model="code" type="text" id="code" inputmode="numeric" autofocus
                   autocomplete="one-time-code"
                   class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors text-center font-mono tracking-widest"
                   placeholder="{{ __('auth.code_placeholder') }}">
            @error('code') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <button type="submit"
                class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2.5 rounded-lg transition-colors focus:ring-4 focus:ring-indigo-300 disabled:opacity-50"
                wire:loading.attr="disabled">
            {{ __('auth.verify_and_login') }}
        </button>
    </form>

    <div class="mt-6 pt-6 border-t border-gray-200 text-center">
        <button wire:click="cancel" type="button" class="text-sm text-gray-500 hover:text-gray-700">
            {{ __('auth.back_to_login') }}
        </button>
    </div>
</div>
