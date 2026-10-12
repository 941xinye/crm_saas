<div class="max-w-3xl space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">安全设置</h1>
        <p class="text-sm text-gray-500 mt-1">两步验证（2FA）为登录增加一道动态码保护</p>
    </div>

    @if($recoveryCodes)
        <div class="bg-green-50 border border-green-200 rounded-lg p-5">
            <p class="font-medium text-green-800 mb-2">恢复码已生成，请立即保存（关闭后将无法再次查看）：</p>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                @foreach($recoveryCodes as $rc)
                    <code class="bg-white border border-green-200 rounded px-2 py-1 font-mono text-xs text-center">{{ $rc }}</code>
                @endforeach
            </div>
            <p class="text-xs text-green-700 mt-3">每个恢复码仅可使用一次；登录时无认证器可用恢复码，耗尽后可在本页重新生成。</p>
        </div>
    @endif

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-medium text-gray-900">两步验证</h2>
                <p class="text-sm text-gray-500">
                    @if(auth()->user()->hasTwoFactorEnabled())
                        <span class="text-green-600 font-medium">已启用</span>
                    @else
                        <span class="text-gray-500">未启用</span>
                    @endif
                </p>
            </div>
        </div>

        {{-- 未启用且无待确认配置：显示启用入口 --}}
        @if(!auth()->user()->hasTwoFactorEnabled() && !$pendingSecret)
            <p class="text-sm text-gray-500">
                启用后，登录除密码外还需输入认证器 App（如 Google Authenticator、Microsoft Authenticator、1Password）中的 6 位动态码。
            </p>
            <button wire:click="enable" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-5 py-2 rounded-lg">
                启用两步验证
            </button>
        @endif

        {{-- 待确认：展示 otpauth URI / 密钥 + 动态码确认表单 --}}
        @if($pendingSecret && !auth()->user()->hasTwoFactorEnabled())
            <div class="border border-gray-200 rounded-lg p-4 space-y-3">
                <p class="text-sm text-gray-700 font-medium">第一步：使用认证器 App 扫描或手动添加密钥</p>
                <code class="block bg-gray-50 border border-gray-200 rounded px-3 py-2 font-mono text-xs break-all">{{ $otpauthUri }}</code>
                <p class="text-xs text-gray-500">
                    手动输入密钥：
                    <code class="bg-gray-100 px-1.5 py-0.5 rounded font-mono">{{ $pendingSecret }}</code>
                </p>
                <form wire:submit="confirm" class="flex gap-3">
                    <input type="text" wire:model="code" inputmode="numeric" autocomplete="one-time-code"
                           class="w-40 border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono tracking-widest text-center"
                           placeholder="6 位动态码">
                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-5 py-2 rounded-lg">
                        确认启用
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
                <h2 class="font-medium text-gray-900 mb-1">恢复码</h2>
                <p class="text-sm text-gray-500 mb-3">认证器丢失时可用恢复码登录，每个仅限一次。剩余
                    <span class="font-medium text-gray-700">{{ count(auth()->user()->two_factor_recovery_codes ?? []) }}</span> 个。</p>
                <form wire:submit="regenerate" class="flex gap-3">
                    <input type="password" wire:model="password" class="w-56 border border-gray-300 rounded-lg px-3 py-2 text-sm"
                           placeholder="当前密码">
                    <button type="submit" class="bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium px-5 py-2 rounded-lg">
                        重新生成恢复码
                    </button>
                </form>
                @error('password') <p class="text-red-600 text-xs mt-2">{{ $message }}</p> @enderror
            </div>

            <div class="pt-4 border-t border-gray-100">
                <h2 class="font-medium text-gray-900 mb-1">关闭两步验证</h2>
                <p class="text-sm text-gray-500 mb-3">关闭后登录仅凭密码验证，动态码与恢复码将全部失效。</p>
                <form wire:submit="disable" class="flex gap-3">
                    <input type="password" wire:model="password" class="w-56 border border-gray-300 rounded-lg px-3 py-2 text-sm"
                           placeholder="当前密码">
                    <button type="submit" class="bg-red-600 hover:bg-red-700 text-white text-sm font-medium px-5 py-2 rounded-lg">
                        关闭两步验证
                    </button>
                </form>
            </div>
        </div>
    @endif
</div>
