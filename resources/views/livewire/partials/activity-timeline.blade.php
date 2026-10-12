{{-- 跟进时间线 partial：入参 $activities（已 eager load user） --}}
<div class="px-6 py-4">
    @forelse($activities as $act)
        <div class="relative pb-6 last:pb-0">
            {{-- 连接线 --}}
            @if(!$loop->last)
                <div class="absolute left-[11px] top-7 bottom-0 w-px bg-gray-200"></div>
            @endif
            <div class="flex items-start gap-3">
                {{-- 类型图标 --}}
                @php
                    $typeMeta = match($act->type) {
                        'call'   => ['label' => __('misc.type_call'), 'bg' => 'bg-blue-100 text-blue-600', 'path' => 'M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z'],
                        'email'  => ['label' => __('misc.type_email'), 'bg' => 'bg-amber-100 text-amber-600', 'path' => 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z'],
                        'meeting'=> ['label' => __('misc.type_meeting'), 'bg' => 'bg-purple-100 text-purple-600', 'path' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z'],
                        'task'   => ['label' => __('misc.type_task'), 'bg' => 'bg-green-100 text-green-600', 'path' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
                        default  => ['label' => __('misc.type_note'), 'bg' => 'bg-gray-100 text-gray-600', 'path' => 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z'],
                    };
                @endphp
                <div class="w-6 h-6 rounded-full {{ $typeMeta['bg'] }} flex items-center justify-center flex-shrink-0 z-10">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $typeMeta['path'] }}"/>
                    </svg>
                </div>
                {{-- 内容 --}}
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <p class="text-sm font-medium text-gray-900">{{ $act->subject }}</p>
                        <span class="px-1.5 py-0.5 text-[10px] font-medium rounded {{ $typeMeta['bg'] }}">{{ $typeMeta['label'] }}</span>
                        @if($act->completed_at)
                            <span class="px-1.5 py-0.5 text-[10px] font-medium rounded bg-green-100 text-green-700">{{ __('misc.completed') }}</span>
                        @elseif($act->scheduled_at && $act->scheduled_at->isPast())
                            <span class="px-1.5 py-0.5 text-[10px] font-medium rounded bg-red-100 text-red-700">{{ __('misc.overdue') }}</span>
                        @elseif($act->scheduled_at)
                            <span class="px-1.5 py-0.5 text-[10px] font-medium rounded bg-blue-100 text-blue-700">{{ __('misc.scheduled') }}</span>
                        @endif
                    </div>
                    @if($act->content)
                        <p class="text-xs text-gray-600 mt-1 whitespace-pre-line">{{ $act->content }}</p>
                    @endif
                    <p class="text-xs text-gray-400 mt-1">
                        {{ $act->user?->name ?? __('misc.system') }}
                        @if($act->scheduled_at)
                            {{ __('misc.planned_at', ['time' => $act->scheduled_at->format('Y-m-d H:i')]) }}
                        @endif
                        {{ __('misc.recorded_at', ['time' => $act->created_at->format('Y-m-d H:i')]) }}
                    </p>
                </div>
            </div>
        </div>
    @empty
        <p class="py-8 text-center text-sm text-gray-500">{{ __('misc.no_activities') }}</p>
    @endforelse
</div>
