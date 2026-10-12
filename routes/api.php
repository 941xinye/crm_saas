<?php

use App\Http\Controllers\Api\ActivityApiController;
use App\Http\Controllers\Api\AttachmentApiController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CompanyApiController;
use App\Http\Controllers\Api\ContactApiController;
use App\Http\Controllers\Api\LeadApiController;
use App\Http\Controllers\Api\OpportunityApiController;
use App\Http\Controllers\Api\StageApiController;
use App\Http\Controllers\Api\TenantWebhookApiController;
use App\Http\Controllers\Api\UserApiController;
use Illuminate\Support\Facades\Route;

// API 认证（Sanctum Token 签发）
// 注意：API 路由名统一加 api. 前缀，避免与 web 路由名冲突
// （apiResource 默认生成 companies.index 等名称，且 API 路由后注册会覆盖
//   RouteCollection 的名称映射，导致 route('companies.index') 错误生成 /api/v1 链接）
Route::prefix('v1')->name('api.')->group(function () {
    // 认证端点限流：防暴力破解（每 IP 每分钟 10 次）
    Route::middleware('throttle:10,1')->group(function () {
        Route::post('/login', [AuthController::class, 'login'])->name('login');
        Route::post('/register', [AuthController::class, 'register'])->name('register');
    });
});

// 邮件转线索：接收 Mailgun/SendGrid Inbound Parse JSON，按租户 slug 路由
// 需要 Sanctum Token（防止任意人乱塞线索），不经过 ensure.has.tenant（按 slug 找租户）
Route::post('/v1/inbound-email/{tenantSlug}', [\App\Http\Controllers\Api\InboundEmailController::class, 'handle'])
    ->middleware(['auth:sanctum', 'throttle:60,1'])->name('api.inbound-email');

// 需要 Sanctum Token 认证 + 邮箱验证 + 租户绑定
// token.scope：按 HTTP 方法检查 Token ability（GET→read，写操作→write），仅对 Token 认证生效
Route::prefix('v1')->middleware(['auth:sanctum', 'verified', 'ensure.has.tenant', 'token.scope', 'throttle:60,1'])->name('api.')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/user', [AuthController::class, 'me'])->name('user');

    // 公司
    Route::apiResource('companies', CompanyApiController::class);

    // 联系人
    Route::apiResource('contacts', ContactApiController::class);

    // 线索
    Route::apiResource('leads', LeadApiController::class);

    // 商机
    Route::apiResource('opportunities', OpportunityApiController::class);

    // 活动
    Route::apiResource('activities', ActivityApiController::class);

    // 阶段 / 成员（只读：集成方发现 stage_id / owner_id）
    Route::get('stages', [StageApiController::class, 'index'])->name('stages.index');
    Route::get('users', [UserApiController::class, 'index'])->name('users.index');

    // 附件（列表/详情/上传/下载）
    Route::post('attachments', [AttachmentApiController::class, 'store'])->name('attachments.store');
    Route::get('attachments', [AttachmentApiController::class, 'index'])->name('attachments.index');
    Route::get('attachments/{attachment}', [AttachmentApiController::class, 'show'])->name('attachments.show');
    Route::get('attachments/{attachment}/download', [AttachmentApiController::class, 'download'])->name('attachments.download');

    // Webhook 配置管理（企业版专属）
    Route::middleware('plan.feature:webhooks')->group(function () {
        Route::apiResource('webhooks', TenantWebhookApiController::class);
        Route::post('webhooks/{id}/test', [TenantWebhookApiController::class, 'test'])->name('webhooks.test');
    });
});

// OpenAPI 规范与交互式文档（公开访问：规范不含敏感数据，试用需自行签发 Token）
// 规范文件由 `php artisan api:generate-docs` 生成并提交入库，见 docs/api-reference.md
Route::get('/openapi.json', fn () => response()->json(
    Illuminate\Support\Facades\File::json(public_path('api/openapi.json'))
))->name('api.openapi');
Route::get('/docs', fn () => view('api.docs'))->name('api.docs');
