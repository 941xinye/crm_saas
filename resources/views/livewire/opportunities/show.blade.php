<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <nav class="text-sm text-gray-500 mb-1">
                <a href="{{ route('opportunities.index') }}" class="hover:text-indigo-600">商机</a>
                <span class="mx-2">/</span>
                <span class="text-gray-700 font-medium">{{ $opportunity->title }}</span>
            </nav>
            <h1 class="text-2xl font-bold text-gray-900">{{ $opportunity->title }}</h1>
            <p class="text-sm text-gray-500 mt-1">
                <span class="px-2 py-0.5 text-xs font-medium bg-indigo-100 text-indigo-700 rounded-full">{{ $opportunity->stage?->name ?? '-' }}</span>
                @if($opportunity->score !== null)
                    @php
                        $scoreColor = $opportunity->score >= 80 ? 'bg-emerald-100 text-emerald-700'
                            : ($opportunity->score >= 60 ? 'bg-indigo-100 text-indigo-700'
                            : ($opportunity->score >= 40 ? 'bg-amber-100 text-amber-700'
                            : 'bg-gray-100 text-gray-600'));
                    @endphp
                    <span class="mx-1 px-2 py-0.5 text-xs font-semibold {{ $scoreColor }} rounded-full" title="智能评分（{{ $opportunity->scored_at?->format('Y-m-d H:i') ?? '待计算' }}）">评分 {{ $opportunity->score }}</span>
                @endif
                · {{ $opportunity->company?->name ?? '-' }}
            </p>
        </div>
        <a href="{{ route('opportunities.edit', $opportunity) }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg">编辑</a>
    </div>

    @php
        $tenant = app(\App\Services\Tenancy::class)->current();
        $plan = app(\App\Services\PlanService::class);
    @endphp
    @if($plan->hasFeature($tenant, 'ai_followup'))
    <!-- AI 跟进邮件建议 -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-sm font-semibold text-gray-900 uppercase tracking-wider">AI 跟进邮件建议</h3>
                <p class="text-xs text-gray-400 mt-1">基于商机阶段、金额与最近活动自动生成草稿</p>
            </div>
            <button
                wire:click="generateFollowUp"
                wire:loading.attr="disabled"
                wire:target="generateFollowUp"
                class="inline-flex items-center px-4 py-2 bg-violet-600 hover:bg-violet-700 disabled:opacity-50 text-white text-sm font-medium rounded-lg"
            >
                <span wire:loading.remove wire:target="generateFollowUp">✦ 生成草稿</span>
                <span wire:loading wire:target="generateFollowUp">生成中…</span>
            </button>
        </div>

        @if ($aiMessage)
            <div class="mt-4 px-4 py-3 text-sm rounded-lg {{ str_contains($aiMessage, '已保存') ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">
                {{ $aiMessage }}
            </div>
        @endif

        @if ($suggestion)
            <div class="mt-4 space-y-3">
                <div>
                    <label class="text-xs font-medium text-gray-500">主题</label>
                    <div class="mt-1 px-3 py-2 text-sm bg-gray-50 border border-gray-200 rounded-lg">{{ $suggestion['subject'] }}</div>
                </div>
                <div>
                    <label class="text-xs font-medium text-gray-500">正文</label>
                    <textarea readonly rows="10" class="mt-1 w-full px-3 py-2 text-sm bg-gray-50 border border-gray-200 rounded-lg whitespace-pre-wrap">{{ $suggestion['body'] }}</textarea>
                </div>
                <button wire:click="saveSuggestionAsActivity" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-lg">
                    保存到跟进时间线
                </button>
            </div>
        @endif
    </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
            <h3 class="text-sm font-semibold text-gray-900 uppercase tracking-wider mb-4">基本信息</h3>
            <dl class="grid grid-cols-2 gap-x-8 gap-y-4">
                <div><dt class="text-sm text-gray-500">金额</dt><dd class="text-sm text-gray-900 font-bold text-lg text-green-600">¥{{ number_format($opportunity->value ?? 0, 2) }}</dd></div>
                <div><dt class="text-sm text-gray-500">成交概率</dt><dd class="text-sm text-gray-900 font-medium">{{ $opportunity->probability ?? 0 }}%</dd></div>
                <div>
                    <dt class="text-sm text-gray-500">智能评分</dt>
                    <dd class="text-sm text-gray-900 font-medium">
                        @if($opportunity->score !== null)
                            {{ $opportunity->score }} / 100
                            <span class="text-xs text-gray-400">（{{ $opportunity->scored_at?->format('m-d H:i') ?? '' }}）</span>
                        @else
                            <span class="text-xs text-gray-400">待计算（运行 php artisan crm:refresh-scores）</span>
                        @endif
                    </dd>
                </div>
                <div><dt class="text-sm text-gray-500">预计成交日期</dt><dd class="text-sm text-gray-900 font-medium">{{ $opportunity->expected_close_date?->format('Y-m-d') ?? '-' }}</dd></div>
                <div><dt class="text-sm text-gray-500">负责人</dt><dd class="text-sm text-gray-900 font-medium">{{ $opportunity->owner?->name ?? '-' }}</dd></div>
                <div><dt class="text-sm text-gray-500">所属公司</dt><dd class="text-sm text-gray-900 font-medium">{{ $opportunity->company?->name ?? '-' }}</dd></div>
                <div><dt class="text-sm text-gray-500">联系人</dt><dd class="text-sm text-gray-900 font-medium">{{ $opportunity->contact?->full_name ?? '-' }}</dd></div>
            </dl>
        </div>

        <!-- AI 活动摘要 -->
        @livewire('activity-summary', ['entityType' => \App\Models\Opportunity::class, 'entityId' => $opportunity->id], key('opportunity-summary-'.$opportunity->id))

        <!-- 自定义字段（动态定义） -->
        @include('livewire.partials.custom-fields-show', ['entity' => 'opportunity', 'model' => $opportunity])

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100">
                <h3 class="text-sm font-semibold text-gray-900 uppercase tracking-wider">跟进时间线</h3>
            </div>
            <div class="max-h-[600px] overflow-y-auto">
                @include('livewire.partials.activity-timeline')
            </div>
        </div>

        <!-- 附件 -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100">
                <h3 class="text-sm font-semibold text-gray-900 uppercase tracking-wider">附件</h3>
            </div>
            @livewire('attachments.attachment-panel', ['attachableType' => \App\Models\Opportunity::class, 'attachableId' => $opportunity->id], key('opportunity-attachments-'.$opportunity->id))
        </div>
    </div>
</div>
