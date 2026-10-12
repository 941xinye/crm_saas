<div class="max-w-3xl space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">{{ __('settings_security.title') }}</h1>
        <p class="text-sm text-gray-500 mt-1">{{ __('settings_security.subtitle') }}</p>
    </div>

    @if($recoveryCodes)
        <div class="bg-green-50 border border-green-200 rounded-lg p-5">
            <p class="font-medium text-green-800 mb-2">{{ __('settings_security.recovery_generated') }}</p>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                @foreach($recoveryCodes as $rc)
                    <code class="bg-white border border-green-200 rounded px-2 py-1 font-mono text-xs text-center">{{ $rc }}</code>
                @endforeach
            </div>
            <p class="text-xs text-green-700 mt-3">{{ __('settings_security.recovery_note') }}</p>
        </div>
    @endif

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-medium text-gray-900">{{ __('settings_security.two_factor') }}</h2>
                <p class="text-sm text-gray-500">
                    @if(auth()->user()->hasTwoFactorEnabled())
                        <span class="text-green-600 font-medium">{{ __('settings_security.enabled') }}</span>
                    @else
                        <span class="text-gray-500">{{ __('settings_security.disabled') }}</span>
                    @endif
                </p>
            </div>
        </div>

        {{-- 未启用且无待确认配置：显示启用入口 --}}
        @if(!auth()->user()->hasTwoFactorEnabled() && !$pendingSecret)
            <p class="text-sm text-gray-500">
                {{ __('settings_security.enable_intro') }}
            </p>
            <button wire:click="enable" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                {{ __('settings_security.enable_btn') }}
            </button>
        @endif

        {{-- 待确认：展示 otpauth URI / 密钥 + 动态码确认表单 --}}
        @if($pendingSecret && !auth()->user()->hasTwoFactorEnabled())
            <div class="border border-gray-200 rounded-lg p-4 space-y-3">
                <p class="text-sm text-gray-700 font-medium">{{ __('settings_security.step1') }}</p>
                <code class="block bg-gray-50 border border-gray-200 rounded px-3 py-2 font-mono text-xs break-all">{{ $otpauthUri }}</code>
                <p class="text-xs text-gray-500">
                    {{ __('settings_security.manual_key') }}
                    <code class="bg-gray-100 px-1.5 py-0.5 rounded font-mono">{{ $pendingSecret }}</code>
                </p>
                <form wire:submit="confirm" class="flex gap-3">
                    <input type="text" wire:model="code" inputmode="numeric" autocomplete="one-time-code"
                           class="w-40 border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono tracking-widest text-center"
                           placeholder="{{ __('settings_security.code_placeholder') }}">
                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                        {{ __('settings_security.confirm_btn') }}
                    </button>
                </form>
                @error('code') <p class="text-red-600 text-xs">{{ $message }}</p> @enderror
            </div>
        @endif
    </div>

    {{-- 已启用：管理区 --}}
    @if(auth()->user()->hasTwoFactorEnabled())
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-6">
            <div>
                <h2 class="font-medium text-gray-900 mb-1">{{ __('settings_security.recovery_codes') }}</h2>
                <p class="text-sm text-gray-500 mb-3">{{ __('settings_security.recovery_remaining_before') }}
                    <span class="font-medium text-gray-700">{{ count(auth()->user()->two_factor_recovery_codes ?? []) }}</span> {{ __('settings_security.recovery_remaining_after') }}</p>
                <form wire:submit="regenerate" class="flex gap-3">
                    <input type="password" wire:model="password" class="w-56 border border-gray-300 rounded-lg px-3 py-2 text-sm"
                           placeholder="{{ __('settings_security.current_password') }}">
                    <button type="submit" class="bg-white border border-gray-300 hover:bg-gray-50 text-gray-600 text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                        {{ __('settings_security.regenerate_btn') }}
                    </button>
                </form>
                @error('password') <p class="text-red-600 text-xs mt-2">{{ $message }}</p> @enderror
            </div>

            <div class="pt-4 border-t border-gray-100">
                <h2 class="font-medium text-gray-900 mb-1">{{ __('settings_security.disable_btn') }}</h2>
                <p class="text-sm text-gray-500 mb-3">{{ __('settings_security.disable_intro') }}</p>
                <form wire:submit="disable" class="flex gap-3">
                    <input type="password" wire:model="password" class="w-56 border border-gray-300 rounded-lg px-3 py-2 text-sm"
                           placeholder="{{ __('settings_security.current_password') }}">
                    <button type="submit" class="bg-red-600 hover:bg-red-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                        {{ __('settings_security.disable_btn') }}
                    </button>
                </form>
            </div>
        </div>
    @endif
</div>
