<!-- 邮箱验证提示页 -->
<div>
    <h2 class="text-2xl font-bold text-gray-900 text-center mb-2">验证您的邮箱</h2>
    <p class="text-gray-500 text-center text-sm mb-6">
        我们已向您的注册邮箱发送了验证链接，请查收并点击链接完成验证。
    </p>

    @if (session('success'))
        <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg text-sm mb-4">
            {{ session('success') }}
        </div>
    @endif

    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
        <div class="flex items-start">
            <svg class="w-5 h-5 text-blue-500 mt-0.5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
            </svg>
            <div class="text-sm text-blue-700">
                <p class="font-medium mb-1">未收到邮件？</p>
                <p>请检查垃圾邮件文件夹，或点击下方按钮重新发送验证邮件。</p>
            </div>
        </div>
    </div>

    <!-- 重新发送按钮 -->
    <button
        type="button"
        wire:click="resend"
        wire:loading.attr="disabled"
        class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2.5 rounded-lg transition-colors focus:ring-4 focus:ring-indigo-300 disabled:opacity-50 mb-3"
    >
        <span wire:loading.remove>重新发送验证邮件</span>
        <span wire:loading class="flex items-center justify-center">
            <svg class="animate-spin h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
        </span>
    </button>

    <!-- 退出登录 -->
    <div class="mt-6 pt-6 border-t border-gray-200 text-center">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="text-sm text-gray-500 hover:text-gray-700 font-medium">
                退出登录
            </button>
        </form>
    </div>
</div>
