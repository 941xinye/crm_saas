<?php

namespace App\Http\Middleware;

use App\Services\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BootstrapTenancy
{
    /**
     * 全局中间件：为已登录用户初始化 Tenancy 服务。
     * 未登录用户跳过，不影响 guest 页面（如 login/register）。
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            app(Tenancy::class)->forUser($user);
        }

        return $next($request);
    }
}
