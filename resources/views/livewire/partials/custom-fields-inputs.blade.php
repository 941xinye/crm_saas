{{-- 自定义字段动态输入区（Livewire 表单共用）
     参数：$entity = company|contact|opportunity
     依赖宿主组件的 public array $custom_fields --}}
@php($service = app(\App\Services\CustomFieldService::class))
@php($definitions = $service->definitions($entity))
@if(count($definitions))
    <div class="border-t border-gray-100 pt-5 space-y-5">
        <h3 class="text-sm font-semibold text-gray-700">{{ __('misc.custom_fields') }}</h3>
        @foreach($definitions as $field)
            @php($errorKey = 'custom_fields.' . $field['key'])
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    {{ $field['label'] }} @if($field['required'])<span class="text-red-500">*</span>@endif
                </label>
                @if($field['type'] === 'textarea')
                    <textarea wire:model="custom_fields.{{ $field['key'] }}" rows="2" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                @elseif($field['type'] === 'number')
                    <input wire:model="custom_fields.{{ $field['key'] }}" type="number" step="any" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                @elseif($field['type'] === 'date')
                    <input wire:model="custom_fields.{{ $field['key'] }}" type="date" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                @elseif($field['type'] === 'select')
                    <select wire:model="custom_fields.{{ $field['key'] }}" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">{{ __('misc.select_placeholder') }}</option>
                        @foreach($field['options'] as $option)
                            <option value="{{ $option }}">{{ $option }}</option>
                        @endforeach
                    </select>
                @elseif($field['type'] === 'multiselect')
                    <select wire:model="custom_fields.{{ $field['key'] }}" multiple size="3" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        @foreach($field['options'] as $option)
                            <option value="{{ $option }}">{{ $option }}</option>
                        @endforeach
                    </select>
                @else
                    <input wire:model="custom_fields.{{ $field['key'] }}" type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                @endif
                @error($errorKey) <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
        @endforeach
    </div>
@endif
