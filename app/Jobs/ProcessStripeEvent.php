<?php

namespace App\Jobs;

use App\Services\StripeSubscriptionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Stripe Webhook 事件异步处理（P1 队列化）：
 * 控制器只做签名验证并快速返回 200，事件体入队后由 worker 解析执行，
 * 避免 Stripe 因业务处理超时而重推（事件端点要求 5s 内响应）。
 *
 * 依赖：QUEUE_CONNECTION 非 sync + Supervisor worker（deployment.md 上线检查清单）。
 * 未消费的事件滞留 jobs 表可排查（queue:failed / crm:health-check 告警覆盖）。
 */
class ProcessStripeEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 120];

    public function __construct(
        private readonly string $payload,
    ) {
    }

    public function handle(StripeSubscriptionService $stripe): void
    {
        $event = json_decode($this->payload, true);
        $type = $event['type'] ?? null;
        $data = $event['data']['object'] ?? [];

        $mapped = match ($type) {
            'customer.subscription.created',
            'customer.subscription.updated',
            'customer.subscription.deleted' => 'handleSubscriptionEvent',
            'invoice.paid',
            'invoice.payment_failed' => 'handleInvoiceEvent',
            default => null,
        };

        if ($mapped === null) {
            Log::info('ProcessStripeEvent: 忽略未监听的事件', ['type' => $type]);

            return;
        }

        $stripe->{$mapped}($type, $data);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('ProcessStripeEvent: 事件处理最终失败', ['error' => $e->getMessage()]);
    }
}
