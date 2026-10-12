{{-- 附件面板：入参由组件自身提供 $attachments --}}
<div class="px-6 py-4 space-y-4">
    @if(session('attachment_success'))
        <div class="bg-green-50 border border-green-100 text-green-700 px-3 py-2 rounded-lg text-xs">
            {{ session('attachment_success') }}
        </div>
    @endif
    @error('file')
        <div class="bg-red-50 border border-red-100 text-red-700 px-3 py-2 rounded-lg text-xs">{{ $message }}</div>
    @enderror

    {{-- 上传表单 --}}
    <form wire:submit="upload" class="flex items-center gap-2 flex-wrap">
        <label class="inline-flex items-center px-3 py-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-600 text-xs rounded-lg cursor-pointer">
            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            {{ __('misc.choose_file') }}
            <input type="file" wire:model="file" class="hidden" accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.doc,.docx,.xls,.xlsx,.csv,.txt,.zip">
        </label>
        @if($file)
            <span class="text-xs text-gray-500">{{ __('misc.file_size', ['name' => $file->getClientOriginalName(), 'size' => number_format($file->getSize() / 1024, 0)]) }}</span>
            <button type="submit" class="px-3 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium rounded-lg disabled:opacity-50"
                    wire:loading.attr="disabled">
                <span wire:loading.remove>{{ __('misc.upload') }}</span>
                <span wire:loading>{{ __('misc.uploading') }}</span>
            </button>
        @endif
    </form>
    <p class="text-[11px] text-gray-400">{{ __('misc.upload_hint') }}</p>

    {{-- 附件列表 --}}
    @if($attachments->isEmpty())
        <p class="py-4 text-center text-sm text-gray-400">{{ __('misc.no_attachments') }}</p>
    @else
        <ul class="divide-y divide-gray-100">
            @foreach($attachments as $att)
                <li class="py-2.5 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center flex-shrink-0">
                            <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                        </div>
                        <div class="min-w-0">
                            <a href="{{ route('attachments.download', $att) }}" class="text-sm text-indigo-600 hover:text-indigo-800 truncate block">
                                {{ $att->original_name }}
                            </a>
                            <p class="text-[11px] text-gray-400">
                                {{ $att->readableSize() }} · {{ $att->uploader?->name ?? '-' }} · {{ $att->created_at->format('Y-m-d H:i') }}
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 flex-shrink-0">
                        <a href="{{ route('attachments.download', $att) }}" class="text-xs text-gray-500 hover:text-indigo-600">{{ __('misc.download') }}</a>
                        @if(auth()->id() === $att->uploaded_by || auth()->user()->canSeeAllRecords())
                            <button wire:confirm="{{ __('misc.confirm_delete_attachment', ['name' => $att->original_name]) }}" wire:click="remove({{ $att->id }})"
                                    class="text-xs text-red-500 hover:text-red-700">{{ __('misc.delete') }}</button>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</div>
