<!-- 两步验证挑战页 -->
<div>
    <h2 class="text-2xl font-bold text-gray-900 text-center mb-2">两步验证</h2>
    <p class="text-gray-500 text-center text-sm mb-6">请输入认证器 App 中的 6 位动态码，或使用恢复码登录</p>

    <form wire:submit="verify" class="space-y-4">
        <div>
            <label for="code" class="block text-sm font-medium text-gray-700 mb-1">验证码</label>
            <input wire:model="code" type="text" id="code" inputmode="numeric" autofocus
                   autocomplete="one-time-code"
                   class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors text-center font-mono tracking-widest"
                   placeholder="123456 或恢复码">
            @error('code') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <button type="submit"
                class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2.5 rounded-lg transition-colors focus:ring-4 focus:ring-indigo-300 disabled:opacity-50"
                wire:loading.attr="disabled">
            验证并登录
        </button>
    </form>

    <div class="mt-6 pt-6 border-t border-gray-200 text-center">
        <button wire:click="cancel" type="button" class="text-sm text-gray-500 hover:text-gray-700">
            返回登录
        </button>
    </div>
</div>
