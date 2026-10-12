<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name') }} - @yield('title', '控制台')</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; }
        /* Livewire sortable 样式 */
        [wire\\:sortable-group].sortable-ghost { opacity: 0.4; }
        [wire\\:sortable-group].sortable-drag { opacity: 0.9; transform: rotate(2deg); }
    </style>
    @livewireStyles
</head>
<body class="bg-gray-100 min-h-screen">

<div class="flex h-screen overflow-hidden">

    <!-- 侧边栏 -->
    <aside class="w-64 bg-gray-900 text-gray-300 flex flex-col flex-shrink-0">
        <!-- Logo -->
        <div class="h-16 flex items-center px-6 border-b border-gray-800">
            <div class="flex items-center space-x-3">
                <div class="w-8 h-8 bg-indigo-600 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                    </svg>
                </div>
                <span class="font-bold text-white text-lg">CRM 平台</span>
            </div>
        </div>

        <!-- 导航菜单 -->
        <nav class="flex-1 px-4 py-4 overflow-y-auto">
            <ul class="space-y-1">
                {{-- 平台超管看到的菜单 --}}
                @if(auth()->user()?->is_platform_admin)
                    <li>
                        <a href="{{ route('platform.tenants') }}"
                           class="flex items-center px-3 py-2.5 rounded-lg transition-colors
                                  {{ request()->routeIs('platform.tenants') ? 'bg-indigo-600 text-white' : 'hover:bg-gray-800 hover:text-white' }}">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                            </svg>
                            租户管理
                        </a>
                    </li>
                @else
                    {{-- 普通租户用户看到的菜单 --}}
                    <li>
                        <a href="{{ route('dashboard') }}"
                           class="flex items-center px-3 py-2.5 rounded-lg transition-colors
                                  {{ request()->routeIs('dashboard') ? 'bg-indigo-600 text-white' : 'hover:bg-gray-800 hover:text-white' }}">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                            </svg>
                            控制台
                        </a>
                    </li>

                    <li>
                        <a href="{{ route('companies.index') }}"
                           class="flex items-center px-3 py-2.5 rounded-lg transition-colors
                                  {{ request()->routeIs('companies.*') ? 'bg-indigo-600 text-white' : 'hover:bg-gray-800 hover:text-white' }}">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                            </svg>
                            公司
                        </a>
                    </li>

                    <li>
                        <a href="{{ route('contacts.index') }}"
                           class="flex items-center px-3 py-2.5 rounded-lg transition-colors
                                  {{ request()->routeIs('contacts.*') ? 'bg-indigo-600 text-white' : 'hover:bg-gray-800 hover:text-white' }}">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                            联系人
                        </a>
                    </li>

                    <li>
                        <a href="{{ route('leads.index') }}"
                           class="flex items-center px-3 py-2.5 rounded-lg transition-colors
                                  {{ request()->routeIs('leads.*') ? 'bg-indigo-600 text-white' : 'hover:bg-gray-800 hover:text-white' }}">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                            </svg>
                            线索
                        </a>
                    </li>

                    <li>
                        <a href="{{ route('opportunities.index') }}"
                           class="flex items-center px-3 py-2.5 rounded-lg transition-colors
                                  {{ request()->routeIs('opportunities.*') ? 'bg-indigo-600 text-white' : 'hover:bg-gray-800 hover:text-white' }}">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                            商机
                        </a>
                    </li>

                    <li>
                        <a href="{{ route('opportunities.board') }}"
                           class="flex items-center px-3 py-2.5 rounded-lg transition-colors
                                  {{ request()->routeIs('opportunities.board') ? 'bg-indigo-600 text-white' : 'hover:bg-gray-800 hover:text-white' }}">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                            </svg>
                            商机看板
                        </a>
                    </li>

                    <li>
                        <a href="{{ route('activities.index') }}"
                           class="flex items-center px-3 py-2.5 rounded-lg transition-colors
                                  {{ request()->routeIs('activities.*') ? 'bg-indigo-600 text-white' : 'hover:bg-gray-800 hover:text-white' }}">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            活动
                        </a>
                    </li>

                    <li class="pt-4 mt-4 border-t border-gray-800">
                        <a href="{{ route('settings.profile') }}"
                           class="flex items-center px-3 py-2.5 rounded-lg transition-colors
                                  {{ request()->routeIs('settings.*') ? 'bg-indigo-600 text-white' : 'hover:bg-gray-800 hover:text-white' }}">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            设置
                        </a>
                    </li>
                @endif
            </ul>
        </nav>
    </aside>

    <!-- 主内容区 -->
    <div class="flex-1 flex flex-col overflow-hidden">

        <!-- 顶栏 -->
        <header class="h-16 bg-white shadow-sm flex items-center justify-between px-6 flex-shrink-0">
            <!-- 左侧：面包屑或页面标题 -->
            <div>
                @if(auth()->user() && !auth()->user()->is_platform_admin && app(\App\Services\Tenancy::class)->current())
                    <span class="text-sm text-gray-500">当前租户：</span>
                    <span class="font-semibold text-gray-800">{{ app(\App\Services\Tenancy::class)->current()->name }}</span>
                    <span class="ml-2 px-2 py-0.5 text-xs rounded-full bg-indigo-100 text-indigo-700">
                        {{ app(\App\Services\Tenancy::class)->current()->plan }}
                    </span>
                @elseif(auth()->user() && auth()->user()->is_platform_admin)
                    <span class="font-semibold text-gray-800">平台管理后台</span>
                @endif
            </div>

            <!-- 右侧：用户下拉 -->
            <div class="relative" x-data="{ open: false }">
                <button @click="open = !open" class="flex items-center space-x-3 hover:bg-gray-100 rounded-lg px-3 py-2 transition-colors">
                    <div class="w-8 h-8 bg-indigo-600 rounded-full flex items-center justify-center text-white text-sm font-medium">
                        {{ strtoupper(mb_substr(auth()->user()?->name ?? '?', 0, 1)) }}
                    </div>
                    <div class="text-left">
                        <p class="text-sm font-medium text-gray-800">{{ auth()->user()?->name ?? 'Guest' }}</p>
                        <p class="text-xs text-gray-500">{{ auth()->user()?->email }}</p>
                    </div>
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>

                <div x-show="open" @click.outside="open = false"
                     class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-100 py-1 z-50">
                    <div class="px-4 py-2 border-b border-gray-100">
                        <p class="text-xs text-gray-500">角色：{{ auth()->user()?->role ?? '-' }}</p>
                    </div>
                    @if(!auth()->user()?->is_platform_admin)
                        <a href="{{ route('settings.profile') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                            租户设置
                        </a>
                        <a href="{{ route('settings.team') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                            团队成员
                        </a>
                    @endif
                    <div class="border-t border-gray-100"></div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-gray-100">
                            退出登录
                        </button>
                    </form>
                </div>
            </div>
        </header>

        <!-- Flash Messages -->
        @if(session('success'))
            <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
                 class="mx-6 mt-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg flex items-center justify-between">
                <span class="text-sm font-medium">{{ session('success') }}</span>
                <button @click="show = false" class="text-green-700 hover:text-green-900">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        @endif

        @if(session('error'))
            <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
                 class="mx-6 mt-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg flex items-center justify-between">
                <span class="text-sm font-medium">{{ session('error') }}</span>
                <button @click="show = false" class="text-red-700 hover:text-red-900">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        @endif

        <!-- 主内容 -->
        <main class="flex-1 overflow-y-auto p-6">
            {{ $slot }}
        </main>
    </div>
</div>

{{-- Sortable.js (Livewire 4 sortable 功能依赖) --}}
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>

@livewireScripts
</body>
</html>
