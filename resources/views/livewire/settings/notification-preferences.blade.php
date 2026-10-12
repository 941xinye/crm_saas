<div class="max-w-2xl space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">{{ __('settings_notifpref.title') }}</h1>
        <p class="text-sm text-gray-500 mt-1">{{ __('settings_notifpref.subtitle') }}</p>
    </div>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg text-sm">
            {{ session('success') }}
        </div>
    @endif

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <form wire:submit="save" class="space-y-4">
            @foreach($types as $key => $label)
                <label class="flex items-center justify-between py-2 border-b border-gray-50 last:border-0">
                    <span class="text-sm text-gray-700">{{ $label }}</span>
                    <input type="hidden" wire:model="prefs.{{ $key }}">
                    <button type="button" wire:click="$toggle('prefs.{{ $key }}')"
                            class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors {{ $prefs[$key] ? 'bg-indigo-600' : 'bg-gray-200' }}">
                        <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform {{ $prefs[$key] ? 'translate-x-6' : 'translate-x-1' }}"></span>
                    </button>
                </label>
            @endforeach

            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-6 py-2.5 rounded-lg">
                {{ __('settings_notifpref.save_btn') }}
            </button>
        </form>
    </div>
</div>
