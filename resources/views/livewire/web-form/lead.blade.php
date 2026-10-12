<div>
    @if($unavailable)
        <div class="text-center py-6">
            <div class="inline-flex items-center justify-center w-12 h-12 bg-gray-100 rounded-full mb-4">
                <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
            </div>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('web_form.unavailable_title') }}</h2>
            <p class="text-sm text-gray-500 mt-2">{{ __('web_form.unavailable_desc') }}</p>
        </div>
    @elseif($submitted)
        <div class="text-center py-6">
            <div class="inline-flex items-center justify-center w-12 h-12 bg-green-100 rounded-full mb-4">
                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
            </div>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('web_form.success_title') }}</h2>
            <p class="text-sm text-gray-500 mt-2">{{ __('web_form.success_desc') }}</p>
        </div>
    @else
        <h2 class="text-lg font-semibold text-gray-900">{{ __('web_form.form_title') }}</h2>
        <p class="text-sm text-gray-500 mt-1 mb-5">{{ __('web_form.form_subtitle', ['tenant' => $tenantName]) }}</p>

        @if(session('web_form_error'))
            <div class="bg-amber-50 border border-amber-200 text-amber-700 px-4 py-3 rounded-lg text-sm mb-4">
                {{ session('web_form_error') }}
            </div>
        @endif

        <form wire:submit="submit" class="space-y-4">
            <!-- 蜜罐字段：视觉隐藏，机器人易填写 -->
            <div class="hidden" aria-hidden="true">
                <label for="website">Website</label>
                <input id="website" type="text" wire:model="website" tabindex="-1" autocomplete="off">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('web_form.field_name') }} <span class="text-red-500">*</span></label>
                <input wire:model="name" type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('web_form.field_email') }} <span class="text-red-500">*</span></label>
                <input wire:model="email" type="email" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                @error('email') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('web_form.field_company') }}</label>
                <input wire:model="companyName" type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                @error('companyName') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('web_form.field_phone') }}</label>
                <input wire:model="phone" type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                @error('phone') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <button type="submit" wire:loading.attr="disabled"
                    class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-6 py-2.5 rounded-lg transition-colors">
                {{ __('web_form.submit_btn') }}
            </button>
        </form>
    @endif
</div>
