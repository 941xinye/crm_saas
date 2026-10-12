<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div><h1 class="text-2xl font-bold text-gray-900">{{ $lead ? '编辑' : '新建' }}线索</h1></div>
        <a href="{{ url()->previous() }}" class="text-sm text-gray-600 hover:text-gray-900">← 返回</a>
    </div>

    <form wire:submit="save" class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 max-w-2xl space-y-5">
        <div><label class="block text-sm font-medium text-gray-700 mb-1">名称 <span class="text-red-500">*</span></label>
            <input wire:model="name" type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div><label class="block text-sm font-medium text-gray-700 mb-1">公司名</label>
                <input wire:model="company_name" type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">来源</label>
                <input wire:model="source" type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"></div>
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div><label class="block text-sm font-medium text-gray-700 mb-1">邮箱</label>
                <input wire:model="email" type="email" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">电话</label>
                <input wire:model="phone" type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"></div>
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div><label class="block text-sm font-medium text-gray-700 mb-1">状态</label>
                <select wire:model="status" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="new">新线索</option><option value="contacted">已联系</option>
                    <option value="qualified">已确认</option><option value="converted">已转化</option>
                    <option value="lost">已流失</option>
                </select>
            </div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">负责人</label>
                <select wire:model="owner_id" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="">-- 选择 --</option>
                    @foreach($owners as $o)<option value="{{ $o->id }}">{{ $o->name }}</option>@endforeach
                </select>
            </div>
        </div>
        <div class="flex items-center space-x-3 pt-4">
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-6 py-2.5 rounded-lg disabled:opacity-50" wire:loading.attr="disabled">
                <span wire:loading.remove>保存</span><span wire:loading>保存中...</span>
            </button>
            <a href="{{ route('leads.index') }}" class="text-gray-600 hover:text-gray-900 font-medium px-4 py-2.5">取消</a>
        </div>
    </form>
</div>
