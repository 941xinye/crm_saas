<div class="max-w-3xl space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">{{ __('settings_tokens.title') }}</h1>
        <p class="text-sm text-gray-500 mt-1">{{ __('settings_tokens.subtitle') }}
            <code class="bg-gray-100 px-1 rounded">Authorization: Bearer &lt;token&gt;</code></p>
    </div>

    @if($newToken)
        <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg text-sm">
            <p class="font-medium mb-1">{{ __('settings_tokens.new_token_title') }}</p>
            <code class="block bg-white border border-green-200 rounded px-3 py-2 font-mono text-xs break-all">{{ $newToken }}</code>
        </div>
    @endif

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <form wire:submit="create" class="space-y-4">
            <div class="flex gap-3">
                <input type="text" wire:model="name" placeholder="{{ __('settings_tokens.name_placeholder') }}"
                       class="flex-1 border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2.5 rounded-lg whitespace-nowrap transition-colors">
                    {{ __('settings_tokens.create_token') }}
                </button>
            </div>
            @error('name') <p class="text-red-600 text-xs mt-2">{{ $message }}</p> @enderror

            {{-- 权限范围勾选：按需最小授权 --}}
            <div>
                <p class="text-xs font-medium text-gray-500 uppercase mb-2">{{ __('settings_tokens.scopes_label') }}</p>
                <div class="flex gap-6">
                    @foreach($scopeOptions as $scope => $label)
                        <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" wire:model="scopes.{{ $scope }}"
                                   class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
                @error('scopes') <p class="text-red-600 text-xs mt-2">{{ $message }}</p> @enderror
                <p class="text-xs text-gray-400 mt-2">{{ __('settings_tokens.scopes_hint') }}</p>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('settings_tokens.col_name') }}</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('settings_tokens.col_scopes') }}</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('settings_tokens.col_created_at') }}</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('settings_tokens.col_last_used') }}</th>
                    <th class="px-6 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($tokens as $token)
                    <tr>
                        <td class="px-6 py-4 text-sm text-gray-900">{{ $token->name }}</td>
                        <td class="px-6 py-4 text-sm">
                            @foreach($token->abilities ?? [] as $ability)
                                <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 mr-1">{{ $ability }}</span>
                            @endforeach
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500">{{ $token->created_at?->format('Y-m-d H:i') }}</td>
                        <td class="px-6 py-4 text-sm text-gray-500">
                            {{ $token->last_used_at?->format('Y-m-d H:i') ?? __('settings_tokens.never_used') }}
                        </td>
                        <td class="px-6 py-4 text-right">
                            <button wire:confirm="{{ __('settings_tokens.confirm_revoke', ['name' => $token->name]) }}"
                                    wire:click="revoke({{ $token->id }})"
                                    class="text-red-600 hover:text-red-900 text-sm">{{ __('settings_tokens.revoke') }}</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-6 py-12 text-center text-gray-500 text-sm">{{ __('settings_tokens.empty_state') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
