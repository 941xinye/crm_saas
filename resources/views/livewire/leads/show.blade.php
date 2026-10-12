<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <nav class="text-sm text-gray-500 mb-1">
                <a href="{{ route('leads.index') }}" class="hover:text-indigo-600">{{ __('leads.title') }}</a>
                <span class="mx-2">/</span>
                <span class="text-gray-700 font-medium">{{ $lead->name }}</span>
            </nav>
            <h1 class="text-2xl font-bold text-gray-900">{{ $lead->name }}</h1>
            <p class="text-sm text-gray-500 mt-1">
                {{ __('leads.company_label') }}{{ $lead->company_name ?? '-' }} · {{ __('leads.status_label') }}
                @if($lead->status === 'new')
                    <span class="px-2 py-0.5 text-xs rounded-full bg-blue-100 text-blue-700">{{ __('leads.status_new') }}</span>
                @elseif($lead->status === 'contacted')
                    <span class="px-2 py-0.5 text-xs rounded-full bg-yellow-100 text-yellow-700">{{ __('leads.status_contacted') }}</span>
                @elseif($lead->status === 'qualified')
                    <span class="px-2 py-0.5 text-xs rounded-full bg-indigo-100 text-indigo-700">{{ __('leads.status_qualified') }}</span>
                @elseif($lead->status === 'converted')
                    <span class="px-2 py-0.5 text-xs rounded-full bg-green-100 text-green-700">{{ __('leads.status_converted') }}</span>
                @elseif($lead->status === 'lost')
                    <span class="px-2 py-0.5 text-xs rounded-full bg-red-100 text-red-700">{{ __('leads.status_lost') }}</span>
                @else
                    {{ $lead->status }}
                @endif
            </p>
        </div>
        <div class="space-x-2">
            @if(!in_array($lead->status, ['converted', 'lost']))
                <a href="{{ route('leads.convert', $lead) }}"
                   class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg">{{ __('leads.convert_to_customer') }}</a>
            @endif
            <a href="{{ route('leads.edit', $lead) }}" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-medium rounded-lg">{{ __('leads.edit') }}</a>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
        <h3 class="text-sm font-semibold text-gray-900 uppercase tracking-wider mb-4">{{ __('leads.details') }}</h3>
        <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-4">
            <div><dt class="text-sm text-gray-500">{{ __('leads.field_email') }}</dt><dd class="text-sm text-gray-900 font-medium">{{ $lead->email ?? '-' }}</dd></div>
            <div><dt class="text-sm text-gray-500">{{ __('leads.field_phone') }}</dt><dd class="text-sm text-gray-900 font-medium">{{ $lead->phone ?? '-' }}</dd></div>
            <div><dt class="text-sm text-gray-500">{{ __('leads.col_source') }}</dt><dd class="text-sm text-gray-900 font-medium">{{ $lead->source ?? '-' }}</dd></div>
            <div><dt class="text-sm text-gray-500">{{ __('leads.col_owner') }}</dt><dd class="text-sm text-gray-900 font-medium">{{ $lead->owner?->name ?? '-' }}</dd></div>
        </dl>
    </div>
</div>
