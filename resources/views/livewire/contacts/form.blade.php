<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ $contact ? __('contacts.form_edit_title') : __('contacts.form_create_title') }}</h1>
        </div>
        <a href="{{ url()->previous() }}" class="text-sm text-gray-600 hover:text-gray-900">{{ __('contacts.back') }}</a>
    </div>

    <form wire:submit="save" class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 max-w-2xl space-y-5">
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('contacts.given_name') }} <span class="text-red-500">*</span></label>
                <input wire:model="first_name" type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                @error('first_name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('contacts.surname') }} <span class="text-red-500">*</span></label>
                <input wire:model="last_name" type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                @error('last_name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('contacts.field_email') }}</label>
            <input wire:model="email" type="email" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            @error('email') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('contacts.field_phone') }}</label>
            <input wire:model="phone" type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            @error('phone') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('contacts.field_position') }}</label>
            <input wire:model="position" type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            @error('position') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('contacts.field_company') }}</label>
            <select wire:model="company_id" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                <option value="">{{ __('contacts.company_placeholder') }}</option>
                @foreach($companies as $c)
                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                @endforeach
            </select>
        </div>

        @if($duplicateWarning)
            <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 flex items-start gap-2">
                <svg class="w-5 h-5 text-amber-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5 19h14a2 2 0 001.153-3.592L14.153 5.592A2 2 0 0012 5.592 2 2 0 009.847 5.592L3.847 15.408A2 2 0 005 19z"/></svg>
                <p class="text-sm text-amber-700">{{ $duplicateWarning }}</p>
            </div>
        @endif

        <!-- 自定义字段（动态定义） -->
        @include('livewire.partials.custom-fields-inputs', ['entity' => 'contact'])

        <div class="flex items-center space-x-3 pt-4">
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-6 py-2.5 rounded-lg disabled:opacity-50" wire:loading.attr="disabled">
                <span wire:loading.remove>{{ __('contacts.save') }}</span><span wire:loading>{{ __('contacts.saving') }}</span>
            </button>
            <a href="{{ route('contacts.index') }}" class="text-gray-600 hover:text-gray-900 font-medium px-4 py-2.5">{{ __('contacts.cancel') }}</a>
        </div>
    </form>
</div>
