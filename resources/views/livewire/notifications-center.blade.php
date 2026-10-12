<div class="max-w-3xl space-y-4">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900">{{ __('misc.notifications_center') }}</h1>
        <div class="flex gap-2 text-sm">
            @if($unreadCount > 0)
                <button wire:click="markAllAsRead"
                        class="px-3 py-1.5 text-indigo-600 hover:text-indigo-700 border border-indigo-200 hover:bg-indigo-50 rounded-lg">
                    {{ __('misc.mark_all_read') }}
                </button>
            @endif
            @if($notifications->count() > 0)
                <button wire:confirm="{{ __('misc.confirm_clear_all') }}" wire:click="clearAll"
                        class="px-3 py-1.5 text-red-600 hover:text-red-700 border border-red-200 hover:bg-red-50 rounded-lg">
                    {{ __('misc.clear_all') }}
                </button>
            @endif
        </div>
    </div>

    @if($notifications->isEmpty())
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-12 text-center">
            <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
            </svg>
            <p class="text-gray-500">{{ __('misc.no_notifications') }}</p>
        </div>
    @else
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 divide-y divide-gray-100">
            @foreach($notifications as $notification)
                <div @class([
                    'p-4 hover:bg-gray-50 transition-colors cursor-pointer',
                    !$notification->read_at ? 'bg-indigo-50/30' : '',
                ])
                     @if(($notification->data['url'] ?? null)) onclick="window.location.href='{{ $notification->data['url'] }}'" @endif>
                    <div class="flex items-start gap-3">
                        <div class="flex-shrink-0">
                            @if(!$notification->read_at)
                                <span class="inline-block w-2 h-2 mt-1.5 rounded-full bg-indigo-600"></span>
                            @else
                                <span class="inline-block w-2 h-2 mt-1.5 rounded-full bg-gray-300"></span>
                            @endif
                        </div>
                        <div class="flex-1 min-w-0">
                            <p @class([
                                'text-sm',
                                !$notification->read_at ? 'font-medium text-gray-900' : 'text-gray-600',
                            ])">
                                {{ $notification->data['message'] ?? __('misc.system_notification') }}
                            </p>
                            <p class="text-xs text-gray-400 mt-0.5">{{ $notification->created_at->diffForHumans() }}</p>
                        </div>
                        <div class="flex-shrink-0 flex gap-1" onclick="event.stopPropagation()">
                            @if(!$notification->read_at)
                                <button wire:click="markAsRead('{{ $notification->getKey() }}')"
                                        class="text-xs text-gray-500 hover:text-indigo-600 px-2 py-1">{{ __('misc.mark_read') }}</button>
                            @endif
                            <button wire:confirm="{{ __('misc.confirm_delete_notification') }}" wire:click="delete('{{ $notification->getKey() }}')"
                                    class="text-xs text-gray-400 hover:text-red-600 px-2 py-1">{{ __('misc.delete') }}</button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
