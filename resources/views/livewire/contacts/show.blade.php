<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <nav class="text-sm text-gray-500 mb-1">
                <a href="{{ route('contacts.index') }}" class="hover:text-indigo-600">联系人</a>
                <span class="mx-2">/</span>
                <span class="text-gray-700 font-medium">{{ $contact->full_name }}</span>
            </nav>
            <h1 class="text-2xl font-bold text-gray-900">{{ $contact->full_name }}</h1>
            <p class="text-sm text-gray-500 mt-1">{{ $contact->position ?? '未填写职位' }} · {{ $contact->company?->name ?? '无公司' }}</p>
        </div>
        <div class="space-x-2">
            <a href="{{ route('contacts.edit', $contact) }}" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-medium rounded-lg">编辑</a>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
        <h3 class="text-sm font-semibold text-gray-900 uppercase tracking-wider mb-4">联系信息</h3>
        <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-4">
            <div><dt class="text-sm text-gray-500">邮箱</dt><dd class="text-sm text-gray-900 font-medium">{{ $contact->email ?? '-' }}</dd></div>
            <div><dt class="text-sm text-gray-500">电话</dt><dd class="text-sm text-gray-900 font-medium">{{ $contact->phone ?? '-' }}</dd></div>
            <div><dt class="text-sm text-gray-500">职位</dt><dd class="text-sm text-gray-900 font-medium">{{ $contact->position ?? '-' }}</dd></div>
            <div><dt class="text-sm text-gray-500">所属公司</dt><dd class="text-sm text-gray-900 font-medium">{{ $contact->company?->name ?? '-' }}</dd></div>
        </dl>
    </div>
</div>
