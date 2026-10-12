<!-- 注册页面 -->
<div>
    <h2 class="text-2xl font-bold text-gray-900 text-center mb-2">{{ __('auth.create_account') }}</h2>
    <p class="text-gray-500 text-center text-sm mb-6">{{ __('auth.start_crm_journey') }}</p>

    <form wire:submit="register" class="space-y-4">
        <!-- 租户名称 -->
        <div>
            <label for="tenant_name" class="block text-sm font-medium text-gray-700 mb-1">{{ __('auth.tenant_name') }}</label>
            <input wire:model="tenant_name" type="text" id="tenant_name"
                   class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors"
                   placeholder="{{ __('auth.tenant_name_placeholder') }}" autofocus>
            @error('tenant_name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <!-- 姓名 -->
        <div>
            <label for="name" class="block text-sm font-medium text-gray-700 mb-1">{{ __('auth.your_name') }}</label>
            <input wire:model="name" type="text" id="name"
                   class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors"
                   placeholder="{{ __('auth.name_placeholder') }}" autocomplete="name">
            @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <!-- 邮箱 -->
        <div>
            <label for="email" class="block text-sm font-medium text-gray-700 mb-1">{{ __('auth.email') }}</label>
            <input wire:model="email" type="email" id="email"
                   class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors"
                   placeholder="name@example.com" autocomplete="email">
            @error('email') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <!-- 密码 -->
        <div>
            <label for="password" class="block text-sm font-medium text-gray-700 mb-1">{{ __('auth.password') }}</label>
            <input wire:model="password" type="password" id="password"
                   class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors"
                   placeholder="{{ __('auth.password_min_hint') }}" autocomplete="new-password">
            @error('password') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <!-- 确认密码 -->
        <div>
            <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1">{{ __('auth.confirm_password') }}</label>
            <input wire:model="password_confirmation" type="password" id="password_confirmation"
                   class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors"
                   placeholder="{{ __('auth.password_confirmation_placeholder') }}" autocomplete="new-password">
            @error('password_confirmation') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <!-- 提交按钮 -->
        <button type="submit"
                class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2.5 rounded-lg transition-colors focus:ring-4 focus:ring-indigo-300 disabled:opacity-50"
                wire:loading.attr="disabled">
            <span wire:loading.remove>{{ __('auth.create_account_and_login') }}</span>
            <span wire:loading class="flex items-center justify-center">
                <svg class="animate-spin h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </span>
        </button>
    </form>

    <!-- 底部链接 -->
    <div class="mt-6 pt-6 border-t border-gray-200 text-center">
        <p class="text-sm text-gray-600">
            {{ __('auth.has_account') }}
            <a href="{{ route('login') }}" class="text-indigo-600 hover:text-indigo-500 font-medium">{{ __('auth.login_now') }}</a>
        </p>
    </div>
</div>
