<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('activities.title') }}</h1>
            <p class="text-sm text-gray-500 mt-1">{{ __('activities.subtitle') }}</p>
        </div>
        <a href="{{ route('activities.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            {{ __('activities.create') }}
        </a>
        <div class="flex items-center gap-1">
            <a href="#" wire:click.prevent="downloadTemplate" class="px-3 py-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-600 text-sm rounded-lg">{{ __('activities.template') }}</a>
            <a href="#" wire:click.prevent="export" class="px-3 py-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-600 text-sm rounded-lg">{{ __('activities.export') }}</a>
            <label class="px-3 py-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-600 text-sm rounded-lg cursor-pointer">
                {{ __('activities.import') }}<input type="file" wire:model="importFile" wire:change="import" accept=".csv,.txt" class="hidden">
            </label>
        </div>
    </div>

    <!-- 搜索框 -->
    <div class="bg-white rounded-xl shadow-sm p-4 border border-gray-100">
        <div class="relative max-w-md">
            <input wire:model.live="search" type="text" placeholder="{{ __('activities.search_placeholder') }}"
                   class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors">
            <svg class="absolute left-3 top-2.5 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <!-- 批量操作栏 -->
        @if(count($selected) > 0)
        <div class="bg-indigo-50 border-b border-indigo-100 px-6 py-3 flex items-center justify-between">
            <div class="text-sm text-indigo-700">
                {{ __('activities.selected_prefix') }} <span class="font-semibold">{{ count($selected) }}</span> {{ __('activities.selected_suffix') }}
            </div>
            <div class="flex items-center gap-2">
                <button wire:click="$set('selected', [])" class="px-3 py-1.5 text-xs bg-white border border-gray-300 text-gray-600 rounded hover:bg-gray-50">
                    {{ __('activities.deselect_all') }}
                </button>
                <button wire:confirm="{{ __('activities.bulk_delete_confirm', ['count' => count($selected)]) }}"
                        wire:click="bulkDelete"
                        class="px-3 py-1.5 text-xs bg-red-600 hover:bg-red-700 text-white rounded">
                    {{ __('activities.bulk_delete') }}
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
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('activities.col_type') }}</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('activities.col_subject') }}</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('activities.col_scheduled_at') }}</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('activities.col_owner') }}</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('activities.col_actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($activities as $a)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <input type="checkbox"
                                   wire:model.live="selected"
                                   value="{{ $a->id }}"
                                   class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 py-1 text-xs font-medium bg-blue-100 text-blue-700 rounded-full">{{ $a->type }}</span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $a->subject }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $a->scheduled_at?->format('Y-m-d H:i') ?? '-' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $a->user?->name ?? '-' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                            <a href="{{ route('activities.edit', $a) }}" class="text-amber-600 hover:text-amber-900">{{ __('activities.edit') }}</a>
                            <button wire:confirm="{{ __('activities.delete_confirm') }}" wire:click="delete({{ $a->id }})" class="text-red-600 hover:text-red-900">{{ __('activities.delete') }}</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-6 py-12 text-center text-gray-500 text-sm">{{ __('activities.empty') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-6 py-4 border-t border-gray-100">{{ $activities->links() }}</div>
    </div>
</div>
