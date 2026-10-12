<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API Token scope 检查（Phase 6 / P3）：
 * - GET / HEAD 请求 → 需要 read ability
 * - 其他方法（POST/PUT/PATCH/DELETE）→ 需要 write ability
 *
 * Token 建立时经「设置 → API Tokens」勾选 scope（Settings\Tokens）或
 * /api/v1/login、/api/v1/register 默认签发 ['read', 'write']。
 * 无对应 ability 返回 403（Token scope 不足）。
 */
class EnsureTokenScope
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // 非 Token 认证（如 session 调试）直接放行，scope 仅约束 Token
        if (! $user || ! method_exists($user, 'currentAccessToken') || ! $user->currentAccessToken()) {
            return $next($request);
        }

        $required = $request->isMethod('GET') || $request->isMethod('HEAD') ? 'read' : 'write';

        if (! $user->tokenCan($required)) {
            abort(403, __('api.token_missing_scope', ['scope' => $required]));
        }

        return $next($request);
    }
}
