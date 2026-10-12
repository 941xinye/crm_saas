<?php

use Illuminate\Support\Facades\Route;

// 首页重定向
Route::get('/', function () {
    return redirect('/dashboard');
})->middleware('auth');

// 认证路由（guest middleware 保护）
require __DIR__.'/auth.php';

// 租户业务路由（需要 auth + has tenant）
Route::middleware(['auth', 'ensure.has.tenant'])->group(function () {
    Route::get('/dashboard', \App\Livewire\Dashboard::class)->name('dashboard');

    // 公司
    Route::prefix('companies')->name('companies.')->group(function () {
        Route::get('/', \App\Livewire\Companies\Index::class)->name('index');
        Route::get('/create', \App\Livewire\Companies\Form::class)->name('create');
        Route::get('/{company}', \App\Livewire\Companies\Show::class)->name('show');
        Route::get('/{company}/edit', \App\Livewire\Companies\Form::class)->name('edit');
    });

    // 联系人
    Route::prefix('contacts')->name('contacts.')->group(function () {
        Route::get('/', \App\Livewire\Contacts\Index::class)->name('index');
        Route::get('/create', \App\Livewire\Contacts\Form::class)->name('create');
        Route::get('/{contact}', \App\Livewire\Contacts\Show::class)->name('show');
        Route::get('/{contact}/edit', \App\Livewire\Contacts\Form::class)->name('edit');
    });

    // 线索
    Route::prefix('leads')->name('leads.')->group(function () {
        Route::get('/', \App\Livewire\Leads\Index::class)->name('index');
        Route::get('/create', \App\Livewire\Leads\Form::class)->name('create');
        Route::get('/{lead}', \App\Livewire\Leads\Show::class)->name('show');
        Route::get('/{lead}/edit', \App\Livewire\Leads\Form::class)->name('edit');
    });

    // 商机
    Route::prefix('opportunities')->name('opportunities.')->group(function () {
        Route::get('/', \App\Livewire\Opportunities\Index::class)->name('index');
        Route::get('/board', \App\Livewire\Opportunities\Board::class)->name('board');
        Route::get('/create', \App\Livewire\Opportunities\Form::class)->name('create');
        Route::get('/{opportunity}', \App\Livewire\Opportunities\Show::class)->name('show');
        Route::get('/{opportunity}/edit', \App\Livewire\Opportunities\Form::class)->name('edit');
    });

    // 活动
    Route::prefix('activities')->name('activities.')->group(function () {
        Route::get('/', \App\Livewire\Activities\Index::class)->name('index');
        Route::get('/create', \App\Livewire\Activities\Form::class)->name('create');
        Route::get('/{activity}/edit', \App\Livewire\Activities\Form::class)->name('edit');
    });

    // 设置（普通租户用户）
    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('/', \App\Livewire\Settings\TenantProfile::class)->name('profile');
        Route::get('/team', \App\Livewire\Settings\TeamMembers::class)->name('team');
    });
});

// 平台管理路由（超管专用）
Route::middleware(['auth', 'platform.admin'])->prefix('platform')->name('platform.')->group(function () {
    Route::get('/tenants', \App\Livewire\Platform\Tenants::class)->name('tenants');
});
