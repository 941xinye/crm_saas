<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ $company ? __('companies.form_edit_title') : __('companies.form_create_title') }}</h1>
            <p class="text-sm text-gray-500 mt-1">{{ __('companies.form_subtitle') }}</p>
        </div>
        <a href="{{ url()->previous() }}" class="text-sm text-gray-600 hover:text-gray-900">{{ __('companies.back') }}</a>
    </div>

    <form wire:submit="save" class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 max-w-2xl space-y-5">
        <!-- 公司名称 -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('companies.field_name') }} <span class="text-red-500">*</span></label>
            <input wire:model="name" type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <!-- 行业 -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('companies.field_industry') }}</label>
            <input wire:model="industry" type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" placeholder="{{ __('companies.industry_placeholder') }}">
            @error('industry') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <!-- 网站 -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('companies.field_website') }}</label>
            <input wire:model="website" type="url" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" placeholder="https://example.com">
            @error('website') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <!-- 电话 -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('companies.field_phone') }}</label>
            <input wire:model="phone" type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" placeholder="{{ __('companies.phone_placeholder') }}">
            @error('phone') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <!-- 地址 -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('companies.field_address') }}</label>
            <textarea wire:model="address" rows="3" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" placeholder="{{ __('companies.address_placeholder') }}"></textarea>
            @error('address') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <!-- 负责人 -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('companies.field_owner') }}</label>
            <select wire:model="owner_id" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                <option value="">{{ __('companies.owner_placeholder') }}</option>
                @foreach($owners as $owner)
                    <option value="{{ $owner->id }}">{{ $owner->name }}</option>
                @endforeach
            </select>
            @error('owner_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        @if($duplicateWarning)
            <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 flex items-start gap-2">
                <svg class="w-5 h-5 text-amber-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5 19h14a2 2 0 001.153-3.592L14.153 5.592A2 2 0 0012 5.592 2 2 0 009.847 5.592L3.847 15.408A2 2 0 005 19z"/></svg>
                <p class="text-sm text-amber-700">{{ $duplicateWarning }}</p>
            </div>
        @endif

        <!-- 自定义字段（动态定义） -->
        @include('livewire.partials.custom-fields-inputs', ['entity' => 'company'])

        <!-- 按钮 -->
        <div class="flex items-center space-x-3 pt-4">
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-6 py-2.5 rounded-lg transition-colors disabled:opacity-50" wire:loading.attr="disabled">
                <span wire:loading.remove>{{ __('companies.save') }}</span>
                <span wire:loading>{{ __('companies.saving') }}</span>
            </button>
            <a href="{{ route('companies.index') }}" class="text-gray-600 hover:text-gray-900 font-medium px-4 py-2.5">{{ __('companies.cancel') }}</a>
        </div>
    </form>
</div>
