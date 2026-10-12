<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsPlatformAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user()?->is_platform_admin) {
            abort(403, __('api.auth_platform_admin_only'));
        }

        return $next($request);
    }
}
