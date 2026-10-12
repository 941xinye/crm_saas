<div class="space-y-6">
    <!-- 面包屑 + 操作 -->
    <div class="flex items-center justify-between">
        <div>
            <nav class="text-sm text-gray-500 mb-1">
                <a href="{{ route('companies.index') }}" class="hover:text-indigo-600">公司</a>
                <span class="mx-2">/</span>
                <span class="text-gray-700 font-medium">{{ $company->name }}</span>
            </nav>
            <h1 class="text-2xl font-bold text-gray-900">{{ $company->name }}</h1>
            <p class="text-sm text-gray-500 mt-1">{{ $company->industry ?? '未填写行业' }}</p>
        </div>
        <div class="space-x-2">
            <a href="{{ route('companies.edit', $company) }}" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-medium rounded-lg transition-colors">编辑</a>
        </div>
    </div>

    <!-- 基本信息卡 -->
    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
        <h3 class="text-sm font-semibold text-gray-900 uppercase tracking-wider mb-4">基本信息</h3>
        <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-4">
            <div>
                <dt class="text-sm text-gray-500">网站</dt>
                <dd class="text-sm text-gray-900 font-medium">{{ $company->website ?? '-' }}</dd>
            </div>
            <div>
                <dt class="text-sm text-gray-500">电话</dt>
                <dd class="text-sm text-gray-900 font-medium">{{ $company->phone ?? '-' }}</dd>
            </div>
            <div>
                <dt class="text-sm text-gray-500">地址</dt>
                <dd class="text-sm text-gray-900 font-medium">{{ $company->address ?? '-' }}</dd>
            </div>
            <div>
                <dt class="text-sm text-gray-500">负责人</dt>
                <dd class="text-sm text-gray-900 font-medium">{{ $company->owner?->name ?? '-' }}</dd>
            </div>
        </dl>
    </div>

    <!-- 关联信息 -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- 联系人 -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-gray-900 uppercase tracking-wider">关联联系人</h3>
                <span class="text-xs text-gray-500">{{ $company->contacts()->count() }} 人</span>
            </div>
            <div class="divide-y divide-gray-100">
                @forelse($contacts as $contact)
                    <div class="px-6 py-4 hover:bg-gray-50">
                        <p class="text-sm font-medium text-gray-900">{{ $contact->full_name }}</p>
                        <p class="text-xs text-gray-500">{{ $contact->position ?? '-' }} · {{ $contact->email ?? '-' }}</p>
                    </div>
                @empty
                    <p class="px-6 py-8 text-center text-sm text-gray-500">暂无联系人</p>
                @endforelse
            </div>
        </div>

        <!-- 商机 -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-gray-900 uppercase tracking-wider">关联商机</h3>
                <span class="text-xs text-gray-500">{{ $company->opportunities()->count() }} 个</span>
            </div>
            <div class="divide-y divide-gray-100">
                @forelse($opportunities as $opp)
                    <div class="px-6 py-4 hover:bg-gray-50">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-medium text-gray-900">{{ $opp->title }}</p>
                            <span class="text-xs font-semibold text-green-600">¥{{ number_format($opp->value, 2) }}</span>
                        </div>
                        <p class="text-xs text-gray-500">{{ $opp->stage?->name ?? '-' }}</p>
                    </div>
                @empty
                    <p class="px-6 py-8 text-center text-sm text-gray-500">暂无商机</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
