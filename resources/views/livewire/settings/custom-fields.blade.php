<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">自定义字段</h1>
            <p class="text-sm text-gray-500 mt-1">为公司 / 联系人 / 商机定义额外字段，保存后表单、详情页与 API 立即生效</p>
        </div>
        <button wire:click="addRow" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">+ 添加字段</button>
    </div>

    <form wire:submit="save" class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-4">
        @if(count($rows) === 0)
            <p class="text-sm text-gray-500 py-6 text-center">尚未定义自定义字段，点击右上角「添加字段」开始。</p>
        @endif

        @foreach($rows as $index => $row)
            <div class="grid grid-cols-12 gap-3 items-end border-b border-gray-50 pb-4">
                <div class="col-span-2">
                    <label class="block text-xs text-gray-500 mb-1">实体</label>
                    <select wire:model="rows.{{ $index }}.entity" class="w-full px-2 py-2 border border-gray-300 rounded-lg text-sm">
                        @foreach($entities as $entityKey => $entityLabel)
                            <option value="{{ $entityKey }}">{{ $entityLabel }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-span-2">
                    <label class="block text-xs text-gray-500 mb-1">标识 key</label>
                    <input wire:model="rows.{{ $index }}.key" type="text" placeholder="如 customer_level" class="w-full px-2 py-2 border border-gray-300 rounded-lg text-sm">
                </div>
                <div class="col-span-2">
                    <label class="block text-xs text-gray-500 mb-1">显示名称</label>
                    <input wire:model="rows.{{ $index }}.label" type="text" placeholder="如 客户等级" class="w-full px-2 py-2 border border-gray-300 rounded-lg text-sm">
                </div>
                <div class="col-span-2">
                    <label class="block text-xs text-gray-500 mb-1">类型</label>
                    <select wire:model="rows.{{ $index }}.type" class="w-full px-2 py-2 border border-gray-300 rounded-lg text-sm">
                        @foreach($types as $typeKey => $typeLabel)
                            <option value="{{ $typeKey }}">{{ $typeLabel }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-span-2">
                    <label class="block text-xs text-gray-500 mb-1">选项（逗号分隔，下拉/多选必填）</label>
                    <input wire:model="rows.{{ $index }}.options" type="text" placeholder="如 A,B,C" class="w-full px-2 py-2 border border-gray-300 rounded-lg text-sm">
                </div>
                <div class="col-span-1">
                    <label class="flex items-center gap-1 text-xs text-gray-600 whitespace-nowrap">
                        <input wire:model="rows.{{ $index }}.required" type="checkbox" class="rounded border-gray-300"> 必填
                    </label>
                </div>
                <div class="col-span-1 flex justify-end">
                    <button type="button" wire:click="removeRow({{ $index }})" class="text-red-500 hover:text-red-700 text-sm">删除</button>
                </div>
            </div>
        @endforeach

        <div class="flex items-center gap-3 pt-2">
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-6 py-2.5 rounded-lg transition-colors disabled:opacity-50" wire:loading.attr="disabled">
                <span wire:loading.remove>保存定义</span>
                <span wire:loading>保存中...</span>
            </button>
            <p class="text-xs text-gray-400">key 仅允许字母、数字与下划线，保存时自动规范化并去重</p>
        </div>
    </form>
</div>
