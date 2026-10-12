<?php

namespace App\Http\Middleware;

use App\Services\PlanService;
use App\Services\Tenancy;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 订阅计划功能权限校验中间件。
 *
 * 用法：
 *   Route::middleware('plan.feature:reports')->get('/reports', ...);
 *   Route::middleware('plan.feature:audit-logs')->get('/audit-logs', ...);
 *
 * 未授权时：
 *   - API 请求：返回 403 JSON，含友好提示文案
 *   - Web 请求：flash 提示并重定向到仪表盘（用户体验优先，而不是硬拒绝）
 *
 * 平台超管上下文、Tenant 为 null 时自动放行。
 */
class EnsurePlanHasFeature
{
    public function __construct(
        private PlanService $plans,
        private Tenancy $tenancy,
    ) {}

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        // 未登录用户跳过，由 auth middleware 处理
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        // 平台超管跳过功能限制
        if ($user->is_platform_admin) {
            return $next($request);
        }

        $tenant = $this->tenancy->current();

        // Tenant 为 null（理论上 ensure.has.tenant 已拦截），放行避免阻塞
        if (! $tenant) {
            return $next($request);
        }

        if ($this->plans->hasFeature($tenant, $feature)) {
            return $next($request);
        }

        $message = $this->plans->upgradeMessage($feature);

        // API 请求 → 返回 JSON 403
        if ($request->expectsJson() || $request->is('api/*')) {
            return new JsonResponse([
                'message' => $message,
                'feature' => $feature,
                'current_plan' => $tenant->plan,
                'upgrade_url' => route('settings.subscription'),
            ], 403);
        }

        // Web 请求 → flash + 重定向
        session()->flash('error', $message);

        return redirect()->route('dashboard');
    }
}
