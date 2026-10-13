<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessStripeEvent;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stripe Webhook 接收控制器：接收订阅事件回调。
 * 生产环境：配置 Stripe Dashboard → Developers → Webhooks 指向此 URL。
 *
 * 职责仅限签名验证 + 快速回执：事件体入队（ProcessStripeEvent）由 worker
 * 异步处理，避免业务耗时导致 Stripe 5s 超时重推。QUEUE_CONNECTION=sync
 * （本地/测试）时自动退化为同步执行，无需额外开关。
 */
class StripeWebhookController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature', '');

        // 验证签名（非法签名直接拒绝，事件不入队）
        if (! app(StripeSubscriptionService::class)->verifyWebhook($payload, $signature)) {
            return response(__('api.stripe_invalid_signature'), 400);
        }

        ProcessStripeEvent::dispatch($payload);

        return response('', 200);
    }
}
