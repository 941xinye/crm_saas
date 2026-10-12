<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ $company ? '编辑' : '新建' }}公司</h1>
            <p class="text-sm text-gray-500 mt-1">填写公司信息</p>
        </div>
        <a href="{{ url()->previous() }}" class="text-sm text-gray-600 hover:text-gray-900">← 返回</a>
    </div>

    <form wire:submit="save" class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 max-w-2xl space-y-5">
        <!-- 公司名称 -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">公司名称 <span class="text-red-500">*</span></label>
            <input wire:model="name" type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <!-- 行业 -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">行业</label>
            <input wire:model="industry" type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" placeholder="如：互联网、制造业">
            @error('industry') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <!-- 网站 -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">网站</label>
            <input wire:model="website" type="url" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" placeholder="https://example.com">
            @error('website') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <!-- 电话 -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">电话</label>
            <input wire:model="phone" type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" placeholder="如：+86 138 0000 0000">
            @error('phone') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <!-- 地址 -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">地址</label>
            <textarea wire:model="address" rows="3" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" placeholder="完整地址"></textarea>
            @error('address') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <!-- 负责人 -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">负责人</label>
            <select wire:model="owner_id" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                <option value="">-- 选择负责人 --</option>
                @foreach($owners as $owner)
                    <option value="{{ $owner->id }}">{{ $owner->name }}</option>
                @endforeach
            </select>
            @error('owner_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <!-- 按钮 -->
        <div class="flex items-center space-x-3 pt-4">
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-6 py-2.5 rounded-lg transition-colors disabled:opacity-50" wire:loading.attr="disabled">
                <span wire:loading.remove>保存</span>
                <span wire:loading>保存中...</span>
            </button>
            <a href="{{ route('companies.index') }}" class="text-gray-600 hover:text-gray-900 font-medium px-4 py-2.5">取消</a>
        </div>
    </form>
</div>
