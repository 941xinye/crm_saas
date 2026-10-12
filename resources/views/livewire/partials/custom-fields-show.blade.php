{{-- 自定义字段详情展示区（Show 页共用）
     参数：$entity = company|contact|opportunity，$model = 带自定义字段值的记录 --}}
@php($service = app(\App\Services\CustomFieldService::class))
@php($definitions = $service->definitions($entity))
@php($values = $model->custom_fields ?? [])
@if(count($definitions))
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h3 class="text-sm font-semibold text-gray-700 mb-4">{{ __('misc.custom_fields') }}</h3>
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
            @foreach($definitions as $field)
                @php($value = $values[$field['key']] ?? null)
                @if($value !== null && $value !== '' && $value !== [])
                    <div>
                        <dt class="text-gray-500">{{ $field['label'] }}</dt>
                        <dd class="font-medium text-gray-900 mt-0.5">{{ $service->displayValue($field, $value) }}</dd>
                    </div>
                @endif
            @endforeach
        </dl>
    </div>
@endif
