<?php

namespace App\Http\Controllers;

use App\Services\StripeSubscriptionService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stripe Webhook 接收控制器：接收订阅事件回调。
 * 生产环境：配置 Stripe Dashboard → Developers → Webhooks 指向此 URL。
 */
class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, StripeSubscriptionService $stripe): Response
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature', '');

        // 验证签名
        if (! $stripe->verifyWebhook($payload, $signature)) {
            return response('无效签名', 400);
        }

        try {
            $event = json_decode($payload, true);
            $type = $event['type'] ?? null;
            $data = $event['data']['object'] ?? [];

            $mapped = match ($type) {
                'customer.subscription.created',
                'customer.subscription.updated',
                'customer.subscription.deleted' => 'handleSubscriptionEvent',
                default => null,
            };

            if ($mapped) {
                $stripe->{$mapped}($type, $data);
            }

            return response('', 200);
        } catch (\Throwable $e) {
            report($e);
            return response('处理失败', 500);
        }
    }
}
