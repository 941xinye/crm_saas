<?php

use Illuminate\Support\Facades\Route;

// 首页重定向
Route::get('/', function () {
    return redirect('/dashboard');
})->middleware('auth');

// 认证路由（guest middleware 保护）
require __DIR__.'/auth.php';

// Stripe Webhook（不需要 auth，Stripe 自己签名验证；CSRF 豁免）
Route::post('/webhooks/stripe', \App\Http\Controllers\StripeWebhookController::class)
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
    ->name('webhooks.stripe');

// 邀请接受路由（游客可访问，通过 token 验证）
Route::get('/invitation/accept/{token}', \App\Livewire\Auth\AcceptInvitation::class)
    ->name('invitation.accept');

// 租户业务路由（需要 auth + 邮箱验证 + has tenant）
Route::middleware(['auth', 'verified', 'ensure.has.tenant'])->group(function () {
    // 附件下载（租户归属由 TenantScope 自动校验）
    Route::get('/attachments/{attachment}/download', [\App\Http\Controllers\AttachmentController::class, 'download'])
        ->name('attachments.download');

    Route::get('/dashboard', \App\Livewire\Dashboard::class)->name('dashboard');
    Route::get('/search', \App\Livewire\GlobalSearch::class)->name('search');

    // 界面语言切换（i18n）：保存到 users.locale
    Route::post('/locale', [\App\Http\Controllers\LocaleController::class, 'update'])->name('locale.update');
    Route::get('/reports', \App\Livewire\Reports::class)->middleware('plan.feature:reports')->name('reports');
    Route::get('/audit-logs', \App\Livewire\AuditLogs::class)->middleware('plan.feature:audit-logs')->name('audit-logs');

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
        Route::get('/{lead}/convert', \App\Livewire\Leads\Convert::class)->name('convert');
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
        Route::get('/notifications', \App\Livewire\Settings\NotificationPreferences::class)->name('notifications');
        Route::get('/tokens', \App\Livewire\Settings\Tokens::class)->name('tokens');
        Route::get('/team', \App\Livewire\Settings\TeamMembers::class)->middleware('plan.feature:settings.team')->name('team');
        Route::get('/custom-fields', \App\Livewire\Settings\CustomFields::class)->middleware('plan.feature:custom_fields')->name('custom-fields');
        Route::get('/webhooks', \App\Livewire\Settings\Webhooks::class)->middleware('plan.feature:webhooks')->name('webhooks');
        Route::get('/automation', \App\Livewire\Settings\Automation::class)->middleware('plan.feature:automation')->name('automation');
        Route::get('/subscription', \App\Livewire\Settings\Subscription::class)->name('subscription');
    });

    // 商机阶段
    Route::prefix('stages')->name('stages.')->group(function () {
        Route::get('/', \App\Livewire\Stages\Index::class)->name('index');
        Route::get('/create', \App\Livewire\Stages\Form::class)->name('create');
        Route::get('/{stage}/edit', \App\Livewire\Stages\Form::class)->name('edit');
    });

    // 通知中心
    Route::get('/notifications', \App\Livewire\NotificationsCenter::class)->name('notifications');
});

// 安全设置（两步验证）——平台超管也可启用，因此不挂 ensure.has.tenant
Route::get('/settings/security', \App\Livewire\Settings\TwoFactor::class)
    ->middleware(['auth'])
    ->name('settings.security');

// 平台管理路由（超管专用）
Route::middleware(['auth', 'verified', 'platform.admin'])->prefix('platform')->name('platform.')->group(function () {
    Route::get('/tenants', \App\Livewire\Platform\Tenants::class)->name('tenants');
});
