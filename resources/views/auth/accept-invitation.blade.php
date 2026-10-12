<!-- 接受邀请页面 -->
<div>
    @if(session('error'))
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm mb-4">
            {{ session('error') }}
        </div>
    @endif

    @if(!$invitation)
        {{-- 邀请不存在 --}}
        <h2 class="text-2xl font-bold text-gray-900 text-center mb-2">{{ __('auth.invitation_not_found') }}</h2>
        <p class="text-gray-500 text-center text-sm mb-6">
            {{ __('auth.invitation_not_found_hint') }}
        </p>
        <div class="text-center">
            <a href="{{ route('login') }}" class="text-sm text-indigo-600 hover:text-indigo-500 font-medium">{{ __('auth.back_to_login') }}</a>
        </div>

    @elseif(!$invitation->isValid())
        {{-- 邀请已失效 --}}
        <h2 class="text-2xl font-bold text-gray-900 text-center mb-2">{{ __('auth.invitation_invalid') }}</h2>
        <p class="text-gray-500 text-center text-sm mb-6">
            @if($invitation->isAccepted())
                {{ __('auth.invitation_accepted') }}
            @else
                {{ __('auth.invitation_expired') }}
            @endif
        </p>
        <div class="text-center">
            <a href="{{ route('login') }}" class="text-sm text-indigo-600 hover:text-indigo-500 font-medium">{{ __('auth.back_to_login') }}</a>
        </div>

    @else
        {{-- 有效邀请 --}}
        @php
            $existingUser = \App\Models\User::where('email', $invitation->email)->first();
        @endphp

        @if($existingUser)
            {{-- 邮箱已注册 --}}
            <h2 class="text-2xl font-bold text-gray-900 text-center mb-2">{{ __('auth.accept_invitation') }}</h2>
            <p class="text-gray-500 text-center text-sm mb-6">
                {{ __('auth.email_already_registered', ['email' => $invitation->email]) }}
                @if($existingUser->tenant_id === $invitation->tenant_id)
                    {{ __('auth.already_member', ['tenant' => $invitation->tenant?->name]) }}
                @else
                    {{ __('auth.please_login_first') }}<a href="{{ route('login') }}" class="text-indigo-600 hover:text-indigo-500 font-medium">{{ __('auth.login') }}</a>{{ __('auth.then_accept_invitation') }}
                @endif
            </p>
            @if($existingUser->tenant_id === $invitation->tenant_id)
                <div class="text-center">
                    <a href="{{ route('login') }}" class="inline-block w-full bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2.5 rounded-lg transition-colors">
                        {{ __('auth.go_to_login') }}
                    </a>
                </div>
            @endif
        @else
            {{-- 注册新账号 --}}
            <h2 class="text-2xl font-bold text-gray-900 text-center mb-2">{{ __('auth.accept_invitation') }}</h2>
            <p class="text-gray-500 text-center text-sm mb-6">
                {{ __('auth.invited_to_team_prefix') }}<span class="font-medium text-gray-700">{{ $invitation->tenant?->name }}</span>{{ __('auth.invited_to_team_suffix') }}
                {{ __('auth.role_is', ['role' => match($invitation->role) { 'admin' => __('auth.role_admin'), 'manager' => __('auth.role_manager'), 'sales' => __('auth.role_sales'), default => $invitation->role }]) }}
            </p>

            @if(session('error'))
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm mb-4">
                    {{ session('error') }}
                </div>
            @endif

            <form wire:submit="register" class="space-y-4">
                <!-- 邮箱（只读） -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('auth.email_field') }}</label>
                    <input type="email" value="{{ $invitation->email }}" disabled
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg bg-gray-50 text-gray-500">
                </div>

                <!-- 姓名 -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('auth.name_label') }}</label>
                    <input wire:model="name" type="text"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors"
                           placeholder="{{ __('auth.your_name') }}" autocomplete="name">
                    @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <!-- 密码 -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('auth.password') }}</label>
                    <input wire:model="password" type="password"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors"
                           placeholder="{{ __('auth.password_min_hint') }}" autocomplete="new-password">
                    @error('password') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <!-- 确认密码 -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('auth.confirm_password') }}</label>
                    <input wire:model="password_confirmation" type="password"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors"
                           placeholder="{{ __('auth.password_confirmation_placeholder') }}" autocomplete="new-password">
                    @error('password_confirmation') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <button type="submit"
                        class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2.5 rounded-lg transition-colors focus:ring-4 focus:ring-indigo-300 disabled:opacity-50"
                        wire:loading.attr="disabled">
                    <span wire:loading.remove>{{ __('auth.register_and_accept') }}</span>
                    <span wire:loading class="flex items-center justify-center">
                        <svg class="animate-spin h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </span>
                </button>
            </form>
        @endif
    @endif
</div>
