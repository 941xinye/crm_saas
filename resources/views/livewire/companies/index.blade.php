<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('companies.index_title') }}</h1>
            <p class="text-sm text-gray-500 mt-1">{{ __('companies.index_subtitle') }}</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('companies.create') }}"
               class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition-colors">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                {{ __('companies.create') }}
            </a>
            <div class="flex items-center gap-1">
                <a href="#" wire:click.prevent="downloadTemplate"
                   class="inline-flex items-center px-3 py-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-600 text-sm rounded-lg transition-colors"
                   title="{{ __('companies.template_title') }}">{{ __('companies.template') }}</a>
                <a href="#" wire:click.prevent="export"
                   class="inline-flex items-center px-3 py-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-600 text-sm rounded-lg transition-colors"
                   title="{{ __('companies.export_title') }}">{{ __('companies.export') }}</a>
                <label class="inline-flex items-center px-3 py-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-600 text-sm rounded-lg transition-colors cursor-pointer">
                    {{ __('companies.import') }}
                    <input type="file" wire:model="importFile" wire:change="import" accept=".csv,.txt" class="hidden">
                </label>
            </div>
        </div>
    </div>

    <!-- 搜索框 + 负责人筛选 -->
    <div class="bg-white rounded-xl shadow-sm p-4 border border-gray-100 flex items-center gap-4 flex-wrap">
        <div class="relative max-w-md flex-1 min-w-[240px]">
            <input wire:model.live="search" type="text" placeholder="{{ __('companies.search_placeholder') }}"
                   class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors">
            <svg class="absolute left-3 top-2.5 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
        </div>
        <select wire:model.live="owner_filter" class="px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm">
            <option value="">{{ __('companies.filter_all_owners') }}</option>
            @foreach($owners as $o)
                <option value="{{ $o->id }}">{{ $o->name }}</option>
            @endforeach
        </select>
        @if(count($cfDefinitions))
            <select wire:model.live="cf_key" class="px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                <option value="">{{ __('companies.filter_custom_field') }}</option>
                @foreach($cfDefinitions as $cf)
                    <option value="{{ $cf['key'] }}">{{ $cf['label'] }}</option>
                @endforeach
            </select>
            @if($cf_key)
                <input wire:model.live.debounce.300ms="cf_value" type="text" placeholder="{{ __('companies.filter_field_value_placeholder') }}"
                       class="px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm max-w-[180px]">
            @endif
        @endif
    </div>

    <!-- 表格 -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <!-- 批量操作栏 -->
        @if(count($selected) > 0)
        <div class="bg-indigo-50 border-b border-indigo-100 px-6 py-3 flex items-center justify-between">
            <div class="text-sm text-indigo-700">
                {{ __('companies.selected_prefix') }} <span class="font-semibold">{{ count($selected) }}</span> {{ __('companies.selected_suffix') }}
            </div>
            <div class="flex items-center gap-2">
                <button wire:click="$set('selected', [])" class="px-3 py-1.5 text-xs bg-white border border-gray-300 text-gray-600 rounded hover:bg-gray-50">
                    {{ __('companies.deselect_all') }}
                </button>
                <button wire:confirm="{{ __('companies.bulk_delete_confirm', ['count' => count($selected)]) }}"
                        wire:click="bulkDelete"
                        class="px-3 py-1.5 text-xs bg-red-600 hover:bg-red-700 text-white rounded">
                    {{ __('companies.bulk_delete') }}
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
                    @include('livewire.partials.sortable-th', ['column' => 'name', 'label' => __('companies.field_name')])
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('companies.field_industry') }}</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('companies.field_owner') }}</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('companies.field_contacts') }}</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('companies.col_actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($companies as $company)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <input type="checkbox"
                                   wire:model.live="selected"
                                   value="{{ $company->id }}"
                                   class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <a href="{{ route('companies.show', $company) }}" class="text-sm font-medium text-gray-900 hover:text-indigo-600">
                                {{ $company->name }}
                            </a>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $company->industry ?? '-' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $company->owner?->name ?? '-' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $company->contacts()->count() }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                            <a href="{{ route('companies.show', $company) }}" class="text-indigo-600 hover:text-indigo-900">{{ __('companies.view') }}</a>
                            <a href="{{ route('companies.edit', $company) }}" class="text-amber-600 hover:text-amber-900">{{ __('companies.edit') }}</a>
                            <button wire:confirm="{{ __('companies.delete_confirm') }}" wire:click="delete({{ $company->id }})"
                                    class="text-red-600 hover:text-red-900">{{ __('companies.delete') }}</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                            <p class="text-sm">{{ __('companies.empty') }}</p>
                            <a href="{{ route('companies.create') }}" class="text-indigo-600 hover:text-indigo-500 text-sm font-medium mt-2 inline-block">
                                {{ __('companies.create_first') }}
                            </a>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-6 py-4 border-t border-gray-100">
            {{ $companies->links() }}
        </div>
    </div>
</div>
