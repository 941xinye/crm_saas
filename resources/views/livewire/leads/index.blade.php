<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('leads.title') }}</h1>
            <p class="text-sm text-gray-500 mt-1">{{ __('leads.subtitle') }}</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('leads.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                {{ __('leads.create') }}
            </a>
            <div class="flex items-center gap-1">
                <a href="#" wire:click.prevent="downloadTemplate" class="px-3 py-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-600 text-sm rounded-lg">{{ __('leads.template') }}</a>
                <a href="#" wire:click.prevent="export" class="px-3 py-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-600 text-sm rounded-lg">{{ __('leads.export') }}</a>
                <label class="px-3 py-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-600 text-sm rounded-lg cursor-pointer">
                    {{ __('leads.import') }}<input type="file" wire:model="importFile" wire:change="import" accept=".csv,.txt" class="hidden">
                </label>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm p-4 border border-gray-100 flex items-center gap-4 flex-wrap">
        <div class="relative max-w-md flex-1 min-w-[240px]">
            <input wire:model.live="search" type="text" placeholder="{{ __('leads.search_placeholder') }}"
                   class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            <svg class="absolute left-3 top-2.5 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        </div>
        <select wire:model.live="owner_filter" class="px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm">
            <option value="">{{ __('leads.all_owners') }}</option>
            @foreach($owners as $o)
                <option value="{{ $o->id }}">{{ $o->name }}</option>
            @endforeach
        </select>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <!-- 批量操作栏 -->
        @if(count($selected) > 0)
        <div class="bg-indigo-50 border-b border-indigo-100 px-6 py-3 flex items-center justify-between">
            <div class="text-sm text-indigo-700">
                {{ __('leads.selected_prefix') }} <span class="font-semibold">{{ count($selected) }}</span> {{ __('leads.selected_suffix') }}
            </div>
            <div class="flex items-center gap-2">
                <button wire:click="$set('selected', [])" class="px-3 py-1.5 text-xs bg-white border border-gray-300 text-gray-600 rounded hover:bg-gray-50">
                    {{ __('leads.deselect_all') }}
                </button>
                <button wire:confirm="{{ __('leads.bulk_delete_confirm', ['count' => count($selected)]) }}"
                        wire:click="bulkDelete"
                        class="px-3 py-1.5 text-xs bg-red-600 hover:bg-red-700 text-white rounded">
                    {{ __('leads.bulk_delete') }}
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
                    @include('livewire.partials.sortable-th', ['column' => 'name', 'label' => __('leads.col_name')])
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('leads.col_company') }}</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('leads.col_source') }}</th>
                    @include('livewire.partials.sortable-th', ['column' => 'status', 'label' => __('leads.col_status')])
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('leads.col_owner') }}</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('leads.col_actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($leads as $l)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <input type="checkbox"
                                   wire:model.live="selected"
                                   value="{{ $l->id }}"
                                   class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap"><a href="{{ route('leads.show', $l) }}" class="text-sm font-medium text-gray-900 hover:text-indigo-600">{{ $l->name }}</a></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $l->company_name ?? '-' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $l->source ?? '-' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if($l->status === 'new')
                                <span class="px-2 py-1 text-xs font-medium bg-blue-100 text-blue-700 rounded-full">{{ __('leads.status_new') }}</span>
                            @elseif($l->status === 'contacted')
                                <span class="px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-700 rounded-full">{{ __('leads.status_contacted') }}</span>
                            @elseif($l->status === 'qualified')
                                <span class="px-2 py-1 text-xs font-medium bg-indigo-100 text-indigo-700 rounded-full">{{ __('leads.status_qualified') }}</span>
                            @elseif($l->status === 'converted')
                                <span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-700 rounded-full">{{ __('leads.status_converted') }}</span>
                            @elseif($l->status === 'lost')
                                <span class="px-2 py-1 text-xs font-medium bg-red-100 text-red-700 rounded-full">{{ __('leads.status_lost') }}</span>
                            @else
                                <span class="px-2 py-1 text-xs font-medium bg-gray-100 text-gray-700 rounded-full">{{ $l->status }}</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $l->owner?->name ?? '-' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                            <a href="{{ route('leads.show', $l) }}" class="text-indigo-600 hover:text-indigo-900">{{ __('leads.view') }}</a>
                            <a href="{{ route('leads.edit', $l) }}" class="text-amber-600 hover:text-amber-900">{{ __('leads.edit') }}</a>
                            <button wire:confirm="{{ __('leads.delete_confirm') }}" wire:click="delete({{ $l->id }})" class="text-red-600 hover:text-red-900">{{ __('leads.delete') }}</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-6 py-12 text-center text-gray-500 text-sm">{{ __('leads.empty') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-6 py-4 border-t border-gray-100">{{ $leads->links() }}</div>
    </div>
</div>
