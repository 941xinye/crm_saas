<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

// 按用户偏好设置界面语言：users.locale（null 跟随应用默认 zh_CN）
class SetLocale
{
    public function handle(Request $request, Closure $next): mixed
    {
        $user = $request->user();

        if ($user instanceof User
            && in_array($user->locale, ['zh_CN', 'en'], true)) {
            App::setLocale($user->locale);
        }

        return $next($request);
    }
}
