<div class="max-w-3xl space-y-6">
    <div>
        <nav class="text-sm text-gray-500 mb-1">
            <a href="{{ route('leads.index') }}" class="hover:text-indigo-600">{{ __('leads.title') }}</a>
            <span class="mx-2">/</span>
            <a href="{{ route('leads.show', $lead) }}" class="hover:text-indigo-600">{{ $lead->name }}</a>
            <span class="mx-2">/</span>
            <span class="text-gray-700 font-medium">{{ __('leads.convert_breadcrumb') }}</span>
        </nav>
        <h1 class="text-2xl font-bold text-gray-900">{{ __('leads.convert_to_customer') }}</h1>
        <p class="text-sm text-gray-500 mt-1">{{ __('leads.convert_description', ['name' => $lead->name]) }}</p>
    </div>

    <form wire:submit="convert" class="space-y-6">
        <!-- 联系人信息 -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h3 class="text-sm font-semibold text-gray-900 uppercase tracking-wider mb-4">{{ __('leads.contact_info') }}</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm text-gray-600 mb-1">{{ __('leads.last_name') }} <span class="text-red-500">*</span></label>
                    <input type="text" wire:model="last_name"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    @error('last_name') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">{{ __('leads.first_name') }} <span class="text-red-500">*</span></label>
                    <input type="text" wire:model="first_name"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    @error('first_name') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">{{ __('leads.field_email') }}</label>
                    <input type="email" wire:model="email"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    @error('email') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">{{ __('leads.field_phone') }}</label>
                    <input type="text" wire:model="phone"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    @error('phone') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <!-- 公司 -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h3 class="text-sm font-semibold text-gray-900 uppercase tracking-wider mb-4">{{ __('leads.col_company') }}</h3>
            <div class="space-y-3">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="radio" wire:model.live="company_option" value="existing" class="text-indigo-600">
                    <span class="text-sm text-gray-700">{{ __('leads.link_existing_company') }}</span>
                </label>
                @if($company_option === 'existing')
                    <select wire:model="company_id"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">{{ __('leads.select_company') }}</option>
                        @foreach($companies as $company)
                            <option value="{{ $company->id }}">{{ $company->name }}</option>
                        @endforeach
                    </select>
                    @error('company_id') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                @endif

                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="radio" wire:model.live="company_option" value="new" class="text-indigo-600">
                    <span class="text-sm text-gray-700">{{ __('leads.create_company') }}</span>
                </label>
                @if($company_option === 'new')
                    <input type="text" wire:model="new_company_name" placeholder="{{ __('leads.company_name_placeholder') }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    @error('new_company_name') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                @endif
            </div>
        </div>

        <!-- 商机 -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" wire:model.live="create_opportunity" class="rounded text-indigo-600">
                <span class="text-sm font-medium text-gray-700">{{ __('leads.also_create_opportunity') }}</span>
            </label>

            @if($create_opportunity)
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">{{ __('leads.stage_label') }} <span class="text-red-500">*</span></label>
                        <select wire:model="stage_id"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="">{{ __('leads.select_stage') }}</option>
                            @foreach($stages as $stage)
                                <option value="{{ $stage->id }}">{{ $stage->name }}</option>
                            @endforeach
                        </select>
                        @error('stage_id') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">{{ __('leads.amount') }}</label>
                        <input type="number" wire:model="value" min="0" step="0.01" placeholder="0.00"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        @error('value') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">{{ __('leads.expected_close_date') }}</label>
                        <input type="date" wire:model="expected_close_date"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        @error('expected_close_date') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
            @endif
        </div>

        <!-- 操作 -->
        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('leads.show', $lead) }}"
               class="px-4 py-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-medium rounded-lg">{{ __('leads.cancel') }}</a>
            <button type="submit"
                    class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg">{{ __('leads.confirm_convert') }}</button>
        </div>
    </form>
</div>
