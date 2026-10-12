<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('contacts.index_title') }}</h1>
            <p class="text-sm text-gray-500 mt-1">{{ __('contacts.index_subtitle') }}</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('contacts.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                {{ __('contacts.create') }}
            </a>
            <div class="flex items-center gap-1">
                <a href="#" wire:click.prevent="downloadTemplate" class="px-3 py-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-600 text-sm rounded-lg">{{ __('contacts.template') }}</a>
                <a href="#" wire:click.prevent="export" class="px-3 py-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-600 text-sm rounded-lg">{{ __('contacts.export') }}</a>
                <label class="px-3 py-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-600 text-sm rounded-lg cursor-pointer">
                    {{ __('contacts.import') }}<input type="file" wire:model="importFile" wire:change="import" accept=".csv,.txt" class="hidden">
                </label>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm p-4 border border-gray-100">
        <div class="relative max-w-md">
            <input wire:model.live="search" type="text" placeholder="{{ __('contacts.search_placeholder') }}"
                   class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            <svg class="absolute left-3 top-2.5 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        </div>
        @if(count($cfDefinitions))
            <div class="flex items-center gap-3 mt-3">
                <select wire:model.live="cf_key" class="px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                    <option value="">{{ __('contacts.filter_custom_field') }}</option>
                    @foreach($cfDefinitions as $cf)
                        <option value="{{ $cf['key'] }}">{{ $cf['label'] }}</option>
                    @endforeach
                </select>
                @if($cf_key)
                    <input wire:model.live.debounce.300ms="cf_value" type="text" placeholder="{{ __('contacts.filter_field_value_placeholder') }}"
                           class="px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm max-w-[180px]">
                @endif
            </div>
        @endif
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <!-- 批量操作栏 -->
        @if(count($selected) > 0)
        <div class="bg-indigo-50 border-b border-indigo-100 px-6 py-3 flex items-center justify-between">
            <div class="text-sm text-indigo-700">
                {{ __('contacts.selected_prefix') }} <span class="font-semibold">{{ count($selected) }}</span> {{ __('contacts.selected_suffix') }}
            </div>
            <div class="flex items-center gap-2">
                <button wire:click="$set('selected', [])" class="px-3 py-1.5 text-xs bg-white border border-gray-300 text-gray-600 rounded hover:bg-gray-50">
                    {{ __('contacts.deselect_all') }}
                </button>
                <button wire:confirm="{{ __('contacts.bulk_delete_confirm', ['count' => count($selected)]) }}"
                        wire:click="bulkDelete"
                        class="px-3 py-1.5 text-xs bg-red-600 hover:bg-red-700 text-white rounded">
                    {{ __('contacts.bulk_delete') }}
                </button>
            </div>
        </div>
        @endif

        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 w-10">
                        <input type="checkbox"
                               wire:model.live="selected"
                               value=""
                               class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    </th>
                    @include('livewire.partials.sortable-th', ['column' => 'first_name', 'label' => __('contacts.surname')])
                    @include('livewire.partials.sortable-th', ['column' => 'last_name', 'label' => __('contacts.given_name')])
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('contacts.position') }}</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('contacts.company') }}</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('contacts.col_contact_info') }}</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('contacts.col_actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($contacts as $c)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <input type="checkbox"
                                   wire:model.live="selected"
                                   value="{{ $c->id }}"
                                   class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap"><a href="{{ route('contacts.show', $c) }}" class="text-sm font-medium text-gray-900 hover:text-indigo-600">{{ $c->full_name }}</a></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $c->position ?? '-' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $c->company?->name ?? '-' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                            <div>{{ $c->email ?? '-' }}</div>
                            <div class="text-xs text-gray-500">{{ $c->phone ?? '' }}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                            <a href="{{ route('contacts.show', $c) }}" class="text-indigo-600 hover:text-indigo-900">{{ __('contacts.view') }}</a>
                            <a href="{{ route('contacts.edit', $c) }}" class="text-amber-600 hover:text-amber-900">{{ __('contacts.edit') }}</a>
                            <button wire:confirm="{{ __('contacts.delete_confirm') }}" wire:click="delete({{ $c->id }})" class="text-red-600 hover:text-red-900">{{ __('contacts.delete') }}</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-6 py-12 text-center text-gray-500 text-sm">{{ __('contacts.empty') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-6 py-4 border-t border-gray-100">{{ $contacts->links() }}</div>
    </div>
</div>
