{{-- 可排序表头：入参 $column（字段名）$label（显示名）$align（可选 left/right/center） --}}
@php
    $active = ($sort_by ?? 'created_at') === $column;
    $isAsc = ($sort_dir ?? 'desc') === 'asc';
    $align = $align ?? 'left';
@endphp
<th class="px-6 py-3 text-{{ $align }} text-xs font-medium uppercase tracking-wider {{ $active ? 'text-indigo-600' : 'text-gray-500' }}">
    <button type="button" wire:click="sortBy('{{ $column }}')"
            class="inline-flex items-center gap-1 hover:text-indigo-600 transition-colors {{ $align === 'right' ? 'flex-row-reverse w-full justify-start' : '' }}">
        {{ $label }}
        @if($active)
            @if($isAsc)
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
            @else
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            @endif
        @endif
    </button>
</th>
