<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('settings_customfields.title') }}</h1>
            <p class="text-sm text-gray-500 mt-1">{{ __('settings_customfields.subtitle') }}</p>
        </div>
        <button wire:click="addRow" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">{{ __('settings_customfields.add_field_btn') }}</button>
    </div>

    <form wire:submit="save" class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-4">
        @if(count($rows) === 0)
            <p class="text-sm text-gray-500 py-6 text-center">{{ __('settings_customfields.empty') }}</p>
        @endif

        @foreach($rows as $index => $row)
            <div class="grid grid-cols-12 gap-3 items-end border-b border-gray-50 pb-4">
                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('settings_customfields.entity') }}</label>
                    <select wire:model="rows.{{ $index }}.entity" class="w-full px-2 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        @foreach($entities as $entityKey => $entityLabel)
                            <option value="{{ $entityKey }}">{{ $entityLabel }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('settings_customfields.key_label') }}</label>
                    <input wire:model="rows.{{ $index }}.key" type="text" placeholder="{{ __('settings_customfields.key_placeholder') }}" class="w-full px-2 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('settings_customfields.display_name') }}</label>
                    <input wire:model="rows.{{ $index }}.label" type="text" placeholder="{{ __('settings_customfields.display_name_placeholder') }}" class="w-full px-2 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('settings_customfields.type') }}</label>
                    <select wire:model="rows.{{ $index }}.type" class="w-full px-2 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        @foreach($types as $typeKey => $typeLabel)
                            <option value="{{ $typeKey }}">{{ $typeLabel }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('settings_customfields.options_label') }}</label>
                    <input wire:model="rows.{{ $index }}.options" type="text" placeholder="{{ __('settings_customfields.options_placeholder') }}" class="w-full px-2 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <div class="col-span-1">
                    <label class="flex items-center gap-1 text-xs text-gray-600 whitespace-nowrap">
                        <input wire:model="rows.{{ $index }}.required" type="checkbox" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"> {{ __('settings_customfields.required') }}
                    </label>
                </div>
                <div class="col-span-1 flex justify-end">
                    <button type="button" wire:click="removeRow({{ $index }})" class="text-red-500 hover:text-red-700 text-sm">{{ __('settings_customfields.delete') }}</button>
                </div>
            </div>
        @endforeach

        <div class="flex items-center gap-3 pt-2">
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-6 py-2.5 rounded-lg transition-colors disabled:opacity-50" wire:loading.attr="disabled">
                <span wire:loading.remove>{{ __('settings_customfields.save_btn') }}</span>
                <span wire:loading>{{ __('settings_customfields.saving') }}</span>
            </button>
            <p class="text-xs text-gray-400">{{ __('settings_customfields.key_hint') }}</p>
        </div>
    </form>
</div>
