<?php

use Illuminate\Support\Facades\Route;

Route::get('/login', \App\Livewire\Auth\Login::class)
    ->middleware('guest')
    ->name('login');

Route::get('/register', \App\Livewire\Auth\Register::class)
    ->middleware('guest')
    ->name('register');

Route::get('/forgot-password', \App\Livewire\Auth\ForgotPassword::class)
    ->middleware('guest')
    ->name('password.request');

Route::get('/reset-password/{token}', \App\Livewire\Auth\ResetPassword::class)
    ->middleware('guest')
    ->name('password.reset');

Route::post('/logout', function () {
    auth()->logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();
    return redirect('/login');
})->middleware('auth')->name('logout');
