<!-- 登录页面 -->
<div>
    <h2 class="text-2xl font-bold text-gray-900 text-center mb-2">{{ __('auth.welcome_back') }}</h2>
    <p class="text-gray-500 text-center text-sm mb-6">{{ __('auth.login_hint') }}</p>

    @if (session('success'))
        <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg text-sm mb-4">
            {{ session('success') }}
        </div>
    @endif

    <form wire:submit="login" class="space-y-4">
        <!-- 邮箱 -->
        <div>
            <label for="email" class="block text-sm font-medium text-gray-700 mb-1">{{ __('auth.email') }}</label>
            <input wire:model="email" type="email" id="email"
                   class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors"
                   placeholder="name@example.com" autofocus autocomplete="email">
            @error('email') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <!-- 密码 -->
        <div>
            <label for="password" class="block text-sm font-medium text-gray-700 mb-1">{{ __('auth.password') }}</label>
            <input wire:model="password" type="password" id="password"
                   class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors"
                   placeholder="{{ __('auth.password_placeholder') }}" autocomplete="current-password">
            @error('password') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <!-- 记住我 -->
        <div class="flex items-center justify-between">
            <label class="flex items-center">
                <input wire:model="remember" type="checkbox"
                       class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
                <span class="ml-2 text-sm text-gray-600">{{ __('auth.remember_me') }}</span>
            </label>
            <a href="{{ route('password.request') }}" class="text-sm text-indigo-600 hover:text-indigo-500 font-medium">
                {{ __('auth.forgot_password') }}
            </a>
        </div>

        <!-- 提交按钮 -->
        <button type="submit"
                class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2.5 rounded-lg transition-colors focus:ring-4 focus:ring-indigo-300 disabled:opacity-50"
                wire:loading.attr="disabled">
            <span wire:loading.remove>{{ __('auth.login') }}</span>
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
            {{ __('auth.no_account') }}
            <a href="{{ route('register') }}" class="text-indigo-600 hover:text-indigo-500 font-medium">{{ __('auth.register_now') }}</a>
        </p>
    </div>

    <!-- 演示账号提示 -->
    <div class="mt-6 p-4 bg-gray-50 rounded-lg border border-gray-200">
        <p class="text-xs text-gray-500 font-medium mb-2">🧪 {{ __('auth.demo_accounts') }}</p>
        <div class="space-y-1 text-xs text-gray-600">
            <p>{{ __('auth.demo_platform') }}<code class="bg-gray-200 px-1.5 py-0.5 rounded">admin@platform.test</code> / <code class="bg-gray-200 px-1.5 py-0.5 rounded">password</code></p>
            <p>Acme：<code class="bg-gray-200 px-1.5 py-0.5 rounded">admin@acme.test</code> / <code class="bg-gray-200 px-1.5 py-0.5 rounded">password</code></p>
            <p>Globex：<code class="bg-gray-200 px-1.5 py-0.5 rounded">admin@globex.test</code> / <code class="bg-gray-200 px-1.5 py-0.5 rounded">password</code></p>
            <p>Initech：<code class="bg-gray-200 px-1.5 py-0.5 rounded">admin@initech.test</code> / <code class="bg-gray-200 px-1.5 py-0.5 rounded">password</code></p>
        </div>
    </div>
</div>
