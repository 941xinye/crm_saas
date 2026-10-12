<!-- 接受邀请页面 -->
<div>
    @if(session('error'))
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm mb-4">
            {{ session('error') }}
        </div>
    @endif

    @if(!$invitation)
        {{-- 邀请不存在 --}}
        <h2 class="text-2xl font-bold text-gray-900 text-center mb-2">邀请不存在</h2>
        <p class="text-gray-500 text-center text-sm mb-6">
            未找到此邀请，可能链接已失效。
        </p>
        <div class="text-center">
            <a href="{{ route('login') }}" class="text-sm text-indigo-600 hover:text-indigo-500 font-medium">返回登录</a>
        </div>

    @elseif(!$invitation->isValid())
        {{-- 邀请已失效 --}}
        <h2 class="text-2xl font-bold text-gray-900 text-center mb-2">邀请已失效</h2>
        <p class="text-gray-500 text-center text-sm mb-6">
            @if($invitation->isAccepted())
                此邀请已被接受。
            @else
                此邀请已过期，请联系管理员重新发送。
            @endif
        </p>
        <div class="text-center">
            <a href="{{ route('login') }}" class="text-sm text-indigo-600 hover:text-indigo-500 font-medium">返回登录</a>
        </div>

    @else
        {{-- 有效邀请 --}}
        @php
            $existingUser = \App\Models\User::where('email', $invitation->email)->first();
        @endphp

        @if($existingUser)
            {{-- 邮箱已注册 --}}
            <h2 class="text-2xl font-bold text-gray-900 text-center mb-2">接受邀请</h2>
            <p class="text-gray-500 text-center text-sm mb-6">
                该邮箱 ({{ $invitation->email }}) 已注册。
                @if($existingUser->tenant_id === $invitation->tenant_id)
                    您已是「{{ $invitation->tenant?->name }}」的成员。
                @else
                    请先<a href="{{ route('login') }}" class="text-indigo-600 hover:text-indigo-500 font-medium">登录</a>后接受邀请。
                @endif
            </p>
            @if($existingUser->tenant_id === $invitation->tenant_id)
                <div class="text-center">
                    <a href="{{ route('login') }}" class="inline-block w-full bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2.5 rounded-lg transition-colors">
                        前往登录
                    </a>
                </div>
            @endif
        @else
            {{-- 注册新账号 --}}
            <h2 class="text-2xl font-bold text-gray-900 text-center mb-2">接受邀请</h2>
            <p class="text-gray-500 text-center text-sm mb-6">
                您被邀请加入「<span class="font-medium text-gray-700">{{ $invitation->tenant?->name }}</span>」团队
                （角色：{{ match($invitation->role) { 'admin' => '管理员', 'manager' => '经理', 'sales' => '销售', default => $invitation->role } }}）
            </p>

            @if(session('error'))
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm mb-4">
                    {{ session('error') }}
                </div>
            @endif

            <form wire:submit="register" class="space-y-4">
                <!-- 邮箱（只读） -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">邮箱</label>
                    <input type="email" value="{{ $invitation->email }}" disabled
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg bg-gray-50 text-gray-500">
                </div>

                <!-- 姓名 -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">姓名</label>
                    <input wire:model="name" type="text"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors"
                           placeholder="您的姓名" autocomplete="name">
                    @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <!-- 密码 -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">密码</label>
                    <input wire:model="password" type="password"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors"
                           placeholder="至少 8 位" autocomplete="new-password">
                    @error('password') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <!-- 确认密码 -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">确认密码</label>
                    <input wire:model="password_confirmation" type="password"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors"
                           placeholder="再次输入密码" autocomplete="new-password">
                    @error('password_confirmation') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <button type="submit"
                        class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2.5 rounded-lg transition-colors focus:ring-4 focus:ring-indigo-300 disabled:opacity-50"
                        wire:loading.attr="disabled">
                    <span wire:loading.remove>注册并接受邀请</span>
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
