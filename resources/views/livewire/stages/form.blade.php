<div class="max-w-2xl mx-auto">
    <div class="space-y-6">
        <div class="flex items-center gap-2 text-sm text-gray-500">
            <a href="{{ route('stages.index') }}" class="hover:text-indigo-600">{{ __('stages.title') }}</a>
            <span>/</span>
            <span>{{ $stage->exists ? __('stages.breadcrumb_edit') : __('stages.breadcrumb_new') }}</span>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h2 class="text-xl font-bold text-gray-900 mb-6">{{ $stage->exists ? __('stages.edit_title') : __('stages.create_title') }}</h2>

            <form wire:submit="save" class="space-y-5">
                <!-- 阶段名称 -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('stages.col_name') }} <span class="text-red-500">*</span></label>
                    <input wire:model="name" type="text"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors"
                           placeholder="{{ __('stages.name_placeholder') }}">
                    @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <!-- 排序 -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('stages.field_order') }}</label>
                        <input wire:model="order" type="number" min="0"
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors">
                        @error('order') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <!-- 赢率假设 -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('stages.field_probability') }}</label>
                        <input wire:model="probability" type="number" min="0" max="100"
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors">
                        @error('probability') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <!-- 赢/输标记 -->
                <div class="border-t border-gray-100 pt-5">
                    <p class="text-sm font-medium text-gray-700 mb-3">{{ __('stages.flow_markers') }}</p>
                    <div class="flex items-center gap-6">
                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input wire:model="is_won" type="checkbox"
                                   class="rounded border-gray-300 text-green-600 focus:ring-green-500 w-4 h-4">
                            <span class="text-sm text-gray-700">{{ __('stages.won_hint') }}</span>
                        </label>
                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input wire:model="is_lost" type="checkbox"
                                   class="rounded border-gray-300 text-red-600 focus:ring-red-500 w-4 h-4">
                            <span class="text-sm text-gray-700">{{ __('stages.lost_hint') }}</span>
                        </label>
                    </div>
                </div>

                <!-- 默认阶段 -->
                <div class="border-t border-gray-100 pt-5">
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input wire:model="is_default" type="checkbox"
                               class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 w-4 h-4">
                        <span class="text-sm text-gray-700">{{ __('stages.default_hint') }}</span>
                    </label>
                </div>

                <div class="flex items-center gap-3 pt-5 border-t border-gray-100">
                    <button type="submit"
                            class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition-colors">
                        {{ __('stages.save') }}
                    </button>
                    <a href="{{ route('stages.index') }}"
                       class="px-6 py-2.5 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-medium rounded-lg transition-colors">
                        {{ __('stages.cancel') }}
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>
