<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">租户设置</h1>
        <p class="text-sm text-gray-500 mt-1">管理您的租户基本信息</p>
    </div>

    @if($tenant)
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 max-w-2xl space-y-5">
            <!-- 只读信息 -->
            <div class="bg-gray-50 rounded-lg p-4 mb-4">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">订阅信息</h3>
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <span class="text-gray-500">计划：</span>
                        <span class="font-medium text-gray-900">
                            @if($tenant->plan === 'free') <span class="px-2 py-0.5 bg-gray-200 text-gray-700 rounded">免费版</span>
                            @elseif($tenant->plan === 'pro') <span class="px-2 py-0.5 bg-blue-100 text-blue-700 rounded">专业版</span>
                            @elseif($tenant->plan === 'enterprise') <span class="px-2 py-0.5 bg-purple-100 text-purple-700 rounded">企业版</span>
                            @else {{ $tenant->plan }} @endif
                        </span>
                    </div>
                    <div>
                        <span class="text-gray-500">状态：</span>
                        @if($tenant->is_active) <span class="font-medium text-green-600">✓ 活跃</span>
                        @else <span class="font-medium text-red-600">✗ 已冻结</span> @endif
                    </div>
                </div>
            </div>

            <form wire:submit="save" class="space-y-5">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">租户名称</label>
                    <input wire:model="name" type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">语言偏好</label>
                    <select wire:model="language" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="zh-CN">简体中文</option>
                        <option value="en-US">English</option>
                        <option value="ja-JP">日本語</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">默认货币</label>
                    <select wire:model="currency" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="CNY">人民币 (CNY)</option>
                        <option value="USD">美元 (USD)</option>
                        <option value="EUR">欧元 (EUR)</option>
                        <option value="GBP">英镑 (GBP)</option>
                        <option value="JPY">日元 (JPY)</option>
                    </select>
                </div>
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-6 py-2.5 rounded-lg">保存设置</button>
            </form>
        </div>
    @else
        <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-6 text-yellow-700">
            未找到租户信息。
        </div>
    @endif
</div>
