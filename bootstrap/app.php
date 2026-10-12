<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'ensure.has.tenant' => \App\Http\Middleware\EnsureUserHasTenant::class,
            'platform.admin' => \App\Http\Middleware\EnsureUserIsPlatformAdmin::class,
            'plan.feature' => \App\Http\Middleware\EnsurePlanHasFeature::class,
            'token.scope' => \App\Http\Middleware\EnsureTokenScope::class,
        ]);

        // 全局初始化 Tenancy 服务（web 组末尾，在 AuthenticateSession 之后执行），
        // 随后按用户偏好设置界面语言（i18n）
        $middleware->web(append: [
            \App\Http\Middleware\BootstrapTenancy::class,
            \App\Http\Middleware\SetLocale::class,
        ]);

        // API 组末尾：Sanctum 认证后初始化 Tenancy，TenantScope 自动过滤
        $middleware->api(append: [
            \App\Http\Middleware\BootstrapTenancy::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // 错误监控告警：5xx 级异常推送群机器人（冷却去重 + 无配置静默降级，见 docs/monitoring.md）
        $exceptions->reportable(fn (\Throwable $e) => app(\App\Services\ErrorReporter::class)->report($e));
    })->create();
