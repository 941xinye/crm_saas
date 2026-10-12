<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div><h1 class="text-2xl font-bold text-gray-900">{{ $activity ? __('activities.edit_title') : __('activities.create') }}</h1></div>
        <a href="{{ url()->previous() }}" class="text-sm text-gray-600 hover:text-gray-900">{{ __('activities.back') }}</a>
    </div>

    <form wire:submit="save" class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 max-w-2xl space-y-5">
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('activities.col_type') }} <span class="text-red-500">*</span></label>
                <select wire:model="type" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="call">{{ __('activities.type_call') }}</option>
                    <option value="meeting">{{ __('activities.type_meeting') }}</option>
                    <option value="email">{{ __('activities.type_email') }}</option>
                    <option value="task">{{ __('activities.type_task') }}</option>
                    <option value="note">{{ __('activities.note') }}</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('activities.col_scheduled_at') }}</label>
                <input wire:model="scheduled_at" type="datetime-local" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('activities.col_subject') }} <span class="text-red-500">*</span></label>
            <input wire:model="subject" type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            @error('subject') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('activities.note') }}</label>
            <textarea wire:model="notes" rows="3" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"></textarea>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('activities.col_owner') }}</label>
            <select wire:model="user_id" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                <option value="">{{ __('activities.select_placeholder') }}</option>
                @foreach($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach
            </select>
        </div>

        <div class="flex items-center space-x-3 pt-4">
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-6 py-2.5 rounded-lg disabled:opacity-50" wire:loading.attr="disabled">
                <span wire:loading.remove>{{ __('activities.save') }}</span><span wire:loading>{{ __('activities.saving') }}</span>
            </button>
            <a href="{{ route('activities.index') }}" class="text-gray-600 hover:text-gray-900 font-medium px-4 py-2.5">{{ __('activities.cancel') }}</a>
        </div>
    </form>
</div>
