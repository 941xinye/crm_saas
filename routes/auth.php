<?php

use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Livewire\Auth\VerifyEmail;

Route::get('/login', \App\Livewire\Auth\Login::class)
    ->middleware('guest')
    ->name('login');

// 两步验证挑战页（密码已验证、动态码未验证的中间态，此时用户尚未通过认证）
Route::get('/two-factor-challenge', \App\Livewire\Auth\TwoFactorChallenge::class)
    ->middleware('guest')
    ->name('two-factor.challenge');

Route::get('/register', \App\Livewire\Auth\Register::class)
    ->middleware('guest')
    ->name('register');

Route::get('/forgot-password', \App\Livewire\Auth\ForgotPassword::class)
    ->middleware('guest')
    ->name('password.request');

Route::get('/reset-password/{token}', \App\Livewire\Auth\ResetPassword::class)
    ->middleware('guest')
    ->name('password.reset');

// 邮箱验证路由（仅需登录，无需 verified / ensure.has.tenant）
Route::middleware('auth')->group(function () {
    Route::get('/email/verify', VerifyEmail::class)
        ->name('verification.notice');

    Route::get('/email/verify/{id}/{hash}', function (Request $request) {
        $user = $request->user();

        if (! hash_equals((string) $request->route('id'), (string) $user->getKey())) {
            abort(403);
        }

        if (! hash_equals((string) $request->route('hash'), sha1($user->getEmailForVerification()))) {
            abort(403);
        }

        if ($user->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard'));
        }

        $user->markEmailAsVerified();
        event(new Verified($user));

        return redirect()->intended(route('dashboard'));
    })->middleware(['signed', 'throttle:60,1'])->name('verification.verify');
});

Route::post('/logout', function () {
    auth()->logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();
    return redirect('/login');
})->middleware('auth')->name('logout');
