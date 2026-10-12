<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
        <h3 class="text-sm font-semibold text-gray-900 uppercase tracking-wider">AI 活动摘要</h3>
        <button wire:click="generate" wire:loading.attr="disabled"
                class="text-xs font-medium text-indigo-600 hover:text-indigo-800 disabled:opacity-50">
            <span wire:loading.remove wire:target="generate">{{ $summary ? '重新生成' : '生成摘要' }}</span>
            <span wire:loading wire:target="generate">生成中...</span>
        </button>
    </div>
    <div class="px-6 py-4">
        @if($summary)
            <div class="text-sm text-gray-700 whitespace-pre-line leading-6">{{ $summary }}</div>
            <p class="text-xs text-gray-400 mt-2">由 AI 生成，仅供参考；新活动产生后摘要将自动失效</p>
        @elseif($unavailable)
            <p class="text-sm text-gray-500">AI 服务未配置、触发限流或生成失败，请稍后重试（详见 docs/llm.md 配置说明）</p>
        @else
            <p class="text-sm text-gray-400">点击右上角「生成摘要」，AI 将总结该记录的跟进历史要点</p>
        @endif
    </div>
</div>
