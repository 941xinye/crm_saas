<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <nav class="text-sm text-gray-500 mb-1">
                <a href="{{ route('leads.index') }}" class="hover:text-indigo-600">线索</a>
                <span class="mx-2">/</span>
                <span class="text-gray-700 font-medium">{{ $lead->name }}</span>
            </nav>
            <h1 class="text-2xl font-bold text-gray-900">{{ $lead->name }}</h1>
            <p class="text-sm text-gray-500 mt-1">
                公司：{{ $lead->company_name ?? '-' }} · 状态：
                @if($lead->status === 'new')
                    <span class="px-2 py-0.5 text-xs rounded-full bg-blue-100 text-blue-700">新线索</span>
                @elseif($lead->status === 'contacted')
                    <span class="px-2 py-0.5 text-xs rounded-full bg-yellow-100 text-yellow-700">已联系</span>
                @elseif($lead->status === 'qualified')
                    <span class="px-2 py-0.5 text-xs rounded-full bg-indigo-100 text-indigo-700">已验证</span>
                @elseif($lead->status === 'converted')
                    <span class="px-2 py-0.5 text-xs rounded-full bg-green-100 text-green-700">已转化</span>
                @elseif($lead->status === 'lost')
                    <span class="px-2 py-0.5 text-xs rounded-full bg-red-100 text-red-700">已丢失</span>
                @else
                    {{ $lead->status }}
                @endif
            </p>
        </div>
        <div class="space-x-2">
            @if(!in_array($lead->status, ['converted', 'lost']))
                <a href="{{ route('leads.convert', $lead) }}"
                   class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg">转化为客户</a>
            @endif
            <a href="{{ route('leads.edit', $lead) }}" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-medium rounded-lg">编辑</a>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
        <h3 class="text-sm font-semibold text-gray-900 uppercase tracking-wider mb-4">详细信息</h3>
        <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-4">
            <div><dt class="text-sm text-gray-500">邮箱</dt><dd class="text-sm text-gray-900 font-medium">{{ $lead->email ?? '-' }}</dd></div>
            <div><dt class="text-sm text-gray-500">电话</dt><dd class="text-sm text-gray-900 font-medium">{{ $lead->phone ?? '-' }}</dd></div>
            <div><dt class="text-sm text-gray-500">来源</dt><dd class="text-sm text-gray-900 font-medium">{{ $lead->source ?? '-' }}</dd></div>
            <div><dt class="text-sm text-gray-500">负责人</dt><dd class="text-sm text-gray-900 font-medium">{{ $lead->owner?->name ?? '-' }}</dd></div>
        </dl>
    </div>
</div>
