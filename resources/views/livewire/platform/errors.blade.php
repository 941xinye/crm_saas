<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">{{ __('platform_errors.title') }}</h1>
        <p class="text-sm text-gray-500 mt-1">{{ __('platform_errors.subtitle') }}</p>
    </div>

    <!-- 统计 -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
            <p class="text-sm text-gray-500">{{ __('platform_errors.signatures') }}</p>
            <p class="text-3xl font-bold text-gray-900 mt-1">{{ number_format($totalSignatures) }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
            <p class="text-sm text-gray-500">{{ __('platform_errors.occurrences') }}</p>
            <p class="text-3xl font-bold text-gray-900 mt-1">{{ number_format($totalOccurrences) }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
            <p class="text-sm text-gray-500">{{ __('platform_errors.recent_24h') }}</p>
            <p class="text-3xl font-bold {{ $recentCount > 0 ? 'text-red-600' : 'text-emerald-600' }} mt-1">{{ number_format($recentCount) }}</p>
        </div>
    </div>

    <!-- 聚合明细 -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('platform_errors.type') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('platform_errors.message') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('platform_errors.location') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('platform_errors.tenant') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('platform_errors.count') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('platform_errors.first_seen') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('platform_errors.last_seen') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($events as $event)
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4 text-sm font-medium text-gray-900 whitespace-nowrap">{{ class_basename($event->type) }}</td>
                            <td class="px-6 py-4 text-sm text-gray-700 max-w-xs">
                                <span class="line-clamp-2">{{ $event->message }}</span>
                            </td>
                            <td class="px-6 py-4 text-xs text-gray-500 max-w-xs">
                                <span class="break-all">{{ $event->file }}:{{ $event->line }}</span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-700 whitespace-nowrap">{{ $event->tenant?->name ?? '-' }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-0.5 text-xs font-medium rounded-full {{ $event->count > 10 ? 'bg-red-50 text-red-600' : 'bg-gray-100 text-gray-600' }}">{{ $event->count }}</span>
                            </td>
                            <td class="px-6 py-4 text-xs text-gray-500 whitespace-nowrap">{{ $event->first_seen_at?->format('Y-m-d H:i') }}</td>
                            <td class="px-6 py-4 text-xs text-gray-500 whitespace-nowrap">{{ $event->last_seen_at?->format('Y-m-d H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-6 py-12 text-center text-sm text-gray-500">{{ __('platform_errors.no_events') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
