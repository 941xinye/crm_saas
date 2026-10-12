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
                · {{ $opportunity->company?->name ?? '-' }}
            </p>
        </div>
        <a href="{{ route('opportunities.edit', $opportunity) }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg">编辑</a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
            <h3 class="text-sm font-semibold text-gray-900 uppercase tracking-wider mb-4">基本信息</h3>
            <dl class="grid grid-cols-2 gap-x-8 gap-y-4">
                <div><dt class="text-sm text-gray-500">金额</dt><dd class="text-sm text-gray-900 font-bold text-lg text-green-600">¥{{ number_format($opportunity->value ?? 0, 2) }}</dd></div>
                <div><dt class="text-sm text-gray-500">成交概率</dt><dd class="text-sm text-gray-900 font-medium">{{ $opportunity->probability ?? 0 }}%</dd></div>
                <div><dt class="text-sm text-gray-500">预计成交日期</dt><dd class="text-sm text-gray-900 font-medium">{{ $opportunity->expected_close_date?->format('Y-m-d') ?? '-' }}</dd></div>
                <div><dt class="text-sm text-gray-500">负责人</dt><dd class="text-sm text-gray-900 font-medium">{{ $opportunity->owner?->name ?? '-' }}</dd></div>
                <div><dt class="text-sm text-gray-500">所属公司</dt><dd class="text-sm text-gray-900 font-medium">{{ $opportunity->company?->name ?? '-' }}</dd></div>
                <div><dt class="text-sm text-gray-500">联系人</dt><dd class="text-sm text-gray-900 font-medium">{{ $opportunity->contact?->full_name ?? '-' }}</dd></div>
            </dl>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100">
                <h3 class="text-sm font-semibold text-gray-900 uppercase tracking-wider">相关活动</h3>
            </div>
            <div class="divide-y divide-gray-100 max-h-96 overflow-y-auto">
                @forelse($activities as $act)
                    <div class="px-6 py-3">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-medium text-gray-900">{{ $act->subject }}</p>
                            <span class="text-xs text-gray-500">{{ $act->type }}</span>
                        </div>
                        <p class="text-xs text-gray-500 mt-0.5">{{ $act->user?->name ?? '-' }} · {{ $act->scheduled_at?->format('Y-m-d H:i') ?? '-' }}</p>
                    </div>
                @empty
                    <p class="px-6 py-8 text-center text-sm text-gray-500">暂无活动</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
