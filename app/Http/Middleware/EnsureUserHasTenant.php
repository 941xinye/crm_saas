<?php

namespace App\Http\Middleware;

use App\Services\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // 未登录用户跳过，由 auth middleware 处理
        if (!$user) {
            return $next($request);
        }

        // 平台超管跳过租户绑定
        if ($user->is_platform_admin) {
            app(Tenancy::class)->forUser($user);
            return $next($request);
        }

        if (!$user->tenant) {
            abort(403, '您尚未关联租户，请联系管理员');
        }

        app(Tenancy::class)->forUser($user);

        return $next($request);
    }
}
