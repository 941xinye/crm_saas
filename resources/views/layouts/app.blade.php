<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name') }} - @yield('title', __('common.default_title'))</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; }
        /* Livewire sortable 样式 */
        [wire\\:sortable-group].sortable-ghost { opacity: 0.4; }
        [wire\\:sortable-group].sortable-drag { opacity: 0.9; transform: rotate(2deg); }

        /* 统一 Tooltip：纯 CSS，hover 显示，无需额外 JS */
        [data-tooltip] { position: relative; cursor: pointer; }
        [data-tooltip]::after {
            content: attr(data-tooltip);
            position: absolute;
            left: 50%;
            top: calc(100% + 8px);
            transform: translateX(-50%);
            background-color: #1f2937;
            color: #f9fafb;
            font-size: 12px;
            font-weight: normal;
            line-height: 1.4;
            padding: 6px 10px;
            border-radius: 6px;
            white-space: nowrap;
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.15s ease, transform 0.15s ease;
            z-index: 100;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        [data-tooltip]::before {
            content: '';
            position: absolute;
            left: 50%;
            top: calc(100% + 3px);
            transform: translateX(-50%);
            border: 5px solid transparent;
            border-bottom-color: #1f2937;
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.15s ease;
            z-index: 100;
        }
        [data-tooltip]:hover::after,
        [data-tooltip]:hover::before,
        [data-tooltip]:focus-visible::after,
        [data-tooltip]:focus-visible::before {
            opacity: 1;
        }
        /* 激活状态的菜单项 tooltip 也显示 */
        [data-tooltip].active::after { background-color: #374151; }
        [data-tooltip].active::before { border-bottom-color: #374151; }

        /* 侧边栏导航项：统一 hover / active 效果 */
        .nav-item {
            position: relative;
            display: flex;
            align-items: center;
            padding: 0.625rem 0.75rem;
            border-radius: 0.5rem;
            color: #d1d5db;
            transition: background-color 0.2s ease, color 0.2s ease, transform 0.15s ease, box-shadow 0.2s ease;
            text-decoration: none;
            overflow: hidden;
        }
        /* 左侧渐变指示器 */
        .nav-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%) scaleY(0);
            width: 3px;
            height: 60%;
            background: linear-gradient(180deg, #818cf8, #6366f1);
            border-radius: 0 3px 3px 0;
            transition: transform 0.2s ease;
        }
        .nav-item svg {
            transition: color 0.2s ease, transform 0.2s ease;
        }
        .nav-item:hover {
            background-color: #1f2937;
            color: #ffffff;
            transform: translateX(2px);
            box-shadow: inset 0 0 0 1px rgba(99, 102, 241, 0.15);
        }
        .nav-item:hover::before {
            transform: translateY(-50%) scaleY(1);
        }
        .nav-item:hover svg {
            color: #a5b4fc;
        }
        .nav-item:focus-visible {
            outline: none;
            background-color: #1f2937;
            color: #ffffff;
            box-shadow: inset 0 0 0 2px #6366f1;
        }
        /* 激活状态 */
        .nav-item.active {
            background-color: #4f46e5;
            color: #ffffff;
            box-shadow: 0 2px 8px rgba(79, 70, 229, 0.3);
        }
        .nav-item.active::before {
            transform: translateY(-50%) scaleY(1);
            background: linear-gradient(180deg, #c7d2fe, #a5b4fc);
        }
        .nav-item.active svg {
            color: #e0e7ff;
        }
        .nav-item.active:hover {
            background-color: #4338ca;
            transform: none;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.4);
        }
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
                <span class="font-bold text-white text-lg">{{ __('common.app_name') }}</span>
            </div>
        </div>

        <!-- 导航菜单 -->
        <nav class="flex-1 px-4 py-4 overflow-y-auto">
            <ul class="space-y-1">
                {{-- 平台超管看到的菜单 --}}
                @if(auth()->user()?->is_platform_admin)
                    <li>
                        <a href="{{ route('platform.tenants') }}"
                           data-tooltip="{{ __('nav.platform_tenants_hint') }}"
                           class="nav-item {{ request()->routeIs('platform.tenants') ? 'active' : '' }}">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                            </svg>
                            {{ __('nav.platform_tenants') }}
                        </a>
                    </li>
                @else
                    {{-- 普通租户用户看到的菜单 --}}
                    @php
                        $tenant = app(\App\Services\Tenancy::class)->current();
                        $plan = app(\App\Services\PlanService::class);
                    @endphp

                    <li>
                        <a href="{{ route('dashboard') }}"
                           data-tooltip="{{ __('nav.dashboard_hint') }}"
                           class="nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                            </svg>
                            {{ __('nav.dashboard') }}
                        </a>
                    </li>

                    @if($plan->hasFeature($tenant, 'reports'))
                    <li>
                        <a href="{{ route('reports') }}"
                           data-tooltip="{{ __('nav.reports_hint') }}"
                           class="nav-item {{ request()->routeIs('reports') ? 'active' : '' }}">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                            {{ __('nav.reports') }}
                        </a>
                    </li>
                    @endif

                    @if($plan->hasFeature($tenant, 'audit-logs'))
                    <li>
                        <a href="{{ route('audit-logs') }}"
                           data-tooltip="{{ __('nav.audit_logs_hint') }}"
                           class="nav-item {{ request()->routeIs('audit-logs') ? 'active' : '' }}">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            {{ __('nav.audit_logs') }}
                        </a>
                    </li>
                    @endif

                    <li>
                        <a href="{{ route('companies.index') }}"
                           data-tooltip="{{ __('nav.companies_hint') }}"
                           class="nav-item {{ request()->routeIs('companies.*') ? 'active' : '' }}">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                            </svg>
                            {{ __('nav.companies') }}
                        </a>
                    </li>

                    <li>
                        <a href="{{ route('contacts.index') }}"
                           data-tooltip="{{ __('nav.contacts_hint') }}"
                           class="nav-item {{ request()->routeIs('contacts.*') ? 'active' : '' }}">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                            {{ __('nav.contacts') }}
                        </a>
                    </li>

                    <li>
                        <a href="{{ route('leads.index') }}"
                           data-tooltip="{{ __('nav.leads_hint') }}"
                           class="nav-item {{ request()->routeIs('leads.*') ? 'active' : '' }}">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                            </svg>
                            {{ __('nav.leads') }}
                        </a>
                    </li>

                    <li>
                        <a href="{{ route('opportunities.index') }}"
                           data-tooltip="{{ __('nav.opportunities_hint') }}"
                           class="nav-item {{ request()->routeIs('opportunities.*') && !request()->routeIs('opportunities.board') ? 'active' : '' }}">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                            {{ __('nav.opportunities') }}
                        </a>
                    </li>

                    <li>
                        <a href="{{ route('opportunities.board') }}"
                           data-tooltip="{{ __('nav.board_hint') }}"
                           class="nav-item {{ request()->routeIs('opportunities.board') ? 'active' : '' }}">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                            </svg>
                            {{ __('nav.board') }}
                        </a>
                    </li>

                    <li>
                        <a href="{{ route('stages.index') }}"
                           data-tooltip="{{ __('nav.stages_hint') }}"
                           class="nav-item {{ request()->routeIs('stages.*') ? 'active' : '' }}">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12"/>
                            </svg>
                            {{ __('nav.stages') }}
                        </a>
                    </li>

                    <li>
                        <a href="{{ route('activities.index') }}"
                           data-tooltip="{{ __('nav.activities_hint') }}"
                           class="nav-item {{ request()->routeIs('activities.*') ? 'active' : '' }}">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            {{ __('nav.activities') }}
                        </a>
                    </li>

                    <li class="pt-4 mt-4 border-t border-gray-800">
                        <a href="{{ route('settings.profile') }}"
                           data-tooltip="{{ __('nav.settings_hint') }}"
                           class="nav-item {{ request()->routeIs('settings.*') ? 'active' : '' }}">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            {{ __('nav.settings') }}
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
            <div class="flex items-center gap-4 min-w-0">
                @if(auth()->user() && !auth()->user()->is_platform_admin && app(\App\Services\Tenancy::class)->current())
                    <span class="text-sm text-gray-500">{{ __('common.current_tenant') }}</span>
                    <span class="font-semibold text-gray-800">{{ app(\App\Services\Tenancy::class)->current()->name }}</span>
                    <span class="ml-2 px-2 py-0.5 text-xs rounded-full bg-indigo-100 text-indigo-700">
                        {{ app(\App\Services\PlanService::class)->name(app(\App\Services\Tenancy::class)->current()) }}
                    </span>
                @elseif(auth()->user() && auth()->user()->is_platform_admin)
                    <span class="font-semibold text-gray-800">{{ __('common.platform_admin_area') }}</span>
                @endif

                @if(auth()->check() && !auth()->user()->is_platform_admin)
                    <form action="{{ route('search') }}" method="GET" class="relative hidden md:block">
                        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <input type="text" name="q" value="{{ request('q') }}"
                               placeholder="{{ __('common.search_placeholder') }}"
                               class="w-64 pl-9 pr-3 py-1.5 text-sm bg-gray-50 border border-gray-200 rounded-lg focus:bg-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-colors">
                    </form>
                @endif
            </div>

            <!-- 右侧：通知 + 用户下拉 -->
            <div class="flex items-center gap-3">
                @if(auth()->user() && !auth()->user()->is_platform_admin)
                    @php($unreadCount = auth()->user()->unreadNotifications()->count())
                    <a href="{{ route('notifications') }}"
                       class="relative p-2 hover:bg-gray-100 rounded-lg transition-colors"
                       title="{{ __('common.notifications') }}">
                        <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                        </svg>
                        @if($unreadCount > 0)
                            <span class="absolute -top-0.5 -right-0.5 min-w-[18px] h-[18px] px-1 flex items-center justify-center text-[10px] font-bold text-white bg-red-500 rounded-full">
                                {{ $unreadCount > 99 ? '99+' : $unreadCount }}
                            </span>
                        @endif
                    </a>
                @endif

                <div class="relative" x-data="{ open: false }">
                <button @click="open = !open" class="flex items-center space-x-3 hover:bg-gray-100 rounded-lg px-3 py-2 transition-colors">
                    <div class="w-8 h-8 bg-indigo-600 rounded-full flex items-center justify-center text-white text-sm font-medium">
                        {{ strtoupper(mb_substr(auth()->user()?->name ?? '?', 0, 1)) }}
                    </div>
                    <div class="text-left">
                        <p class="text-sm font-medium text-gray-800">{{ auth()->user()?->name ?? __('common.guest') }}</p>
                        <p class="text-xs text-gray-500">{{ auth()->user()?->email }}</p>
                    </div>
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>

                <div x-show="open" @click.outside="open = false"
                     class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-100 py-1 z-50">
                    <div class="px-4 py-2 border-b border-gray-100">
                        <p class="text-xs text-gray-500">{{ __('common.role_label') }}{{ auth()->user()?->role ?? '-' }}</p>
                    </div>
                    @if(!auth()->user()?->is_platform_admin)
                        <a href="{{ route('settings.profile') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                            {{ __('common.tenant_settings') }}
                        </a>
                        <a href="{{ route('settings.notifications') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                            {{ __('common.notification_prefs') }}
                        </a>
                        <a href="{{ route('settings.tokens') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                            API Tokens
                        </a>
                        @if(app(\App\Services\PlanService::class)->hasFeature(app(\App\Services\Tenancy::class)->current(), 'settings.team'))
                        <a href="{{ route('settings.team') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                            {{ __('common.team_members') }}
                        </a>
                        @endif
                        @if(app(\App\Services\PlanService::class)->hasFeature(app(\App\Services\Tenancy::class)->current(), 'custom_fields'))
                        <a href="{{ route('settings.custom-fields') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                            {{ __('common.custom_fields') }}
                        </a>
                        @endif
                        @if(app(\App\Services\PlanService::class)->hasFeature(app(\App\Services\Tenancy::class)->current(), 'webhooks'))
                        <a href="{{ route('settings.webhooks') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                            {{ __('common.webhook_manage') }}
                        </a>
                        @endif
                        @if(app(\App\Services\PlanService::class)->hasFeature(app(\App\Services\Tenancy::class)->current(), 'automation'))
                        <a href="{{ route('settings.automation') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                            {{ __('common.automation') }}
                        </a>
                        @endif
                        <a href="{{ route('settings.subscription') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                            {{ __('common.subscription') }}
                        </a>
                    @endif
                    <div class="border-t border-gray-100"></div>
                    <a href="{{ route('settings.security') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                        {{ __('common.security_2fa') }}
                    </a>
                    {{-- 界面语言切换（i18n）：提交到 LocaleController --}}
                    <div class="border-t border-gray-100"></div>
                    <div class="px-4 py-2">
                        <p class="text-xs text-gray-500 mb-1">{{ __('common.language') }}</p>
                        <form method="POST" action="{{ route('locale.update') }}">
                            @csrf
                            <input type="hidden" name="locale" value="{{ app()->getLocale() === 'en' ? 'zh_CN' : 'en' }}">
                            <button type="submit" class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">
                                {{ app()->getLocale() === 'en' ? __('common.lang_zh') : __('common.lang_en') }}
                            </button>
                        </form>
                    </div>
                    <div class="border-t border-gray-100"></div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-gray-100">
                            {{ __('common.logout') }}
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

@stack('scripts')

@livewireScripts
</body>
</html>
