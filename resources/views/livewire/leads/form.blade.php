<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div><h1 class="text-2xl font-bold text-gray-900">{{ $lead ? __('leads.edit_title') : __('leads.create') }}</h1></div>
        <a href="{{ url()->previous() }}" class="text-sm text-gray-600 hover:text-gray-900">{{ __('leads.back') }}</a>
    </div>

    <form wire:submit="save" class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 max-w-2xl space-y-5">
        <div><label class="block text-sm font-medium text-gray-700 mb-1">{{ __('leads.col_name') }} <span class="text-red-500">*</span></label>
            <input wire:model="name" type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div><label class="block text-sm font-medium text-gray-700 mb-1">{{ __('leads.field_company_name') }}</label>
                <input wire:model="company_name" type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">{{ __('leads.col_source') }}</label>
                <input wire:model="source" type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"></div>
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div><label class="block text-sm font-medium text-gray-700 mb-1">{{ __('leads.field_email') }}</label>
                <input wire:model="email" type="email" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">{{ __('leads.field_phone') }}</label>
                <input wire:model="phone" type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"></div>
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div><label class="block text-sm font-medium text-gray-700 mb-1">{{ __('leads.col_status') }}</label>
                <select wire:model="status" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="new">{{ __('leads.status_new') }}</option><option value="contacted">{{ __('leads.status_contacted') }}</option>
                    <option value="qualified">{{ __('leads.status_qualified_option') }}</option><option value="converted">{{ __('leads.status_converted') }}</option>
                    <option value="lost">{{ __('leads.status_lost_option') }}</option>
                </select>
            </div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">{{ __('leads.col_owner') }}</label>
                <select wire:model="owner_id" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="">{{ __('leads.select_placeholder') }}</option>
                    @foreach($owners as $o)<option value="{{ $o->id }}">{{ $o->name }}</option>@endforeach
                </select>
            </div>
        </div>
        <div class="flex items-center space-x-3 pt-4">
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-6 py-2.5 rounded-lg disabled:opacity-50" wire:loading.attr="disabled">
                <span wire:loading.remove>{{ __('leads.save') }}</span><span wire:loading>{{ __('leads.saving') }}</span>
            </button>
            <a href="{{ route('leads.index') }}" class="text-gray-600 hover:text-gray-900 font-medium px-4 py-2.5">{{ __('leads.cancel') }}</a>
        </div>
    </form>
</div>
