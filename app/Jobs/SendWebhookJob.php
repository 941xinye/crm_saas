<?php

namespace App\Jobs;

use App\Models\Scopes\TenantScope;
use App\Models\TenantWebhook;
use App\Services\WebhookService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Webhook 推送队列任务（CRM_WEBHOOK_QUEUE=true 时启用）。
 *
 * - 队列 worker 无租户上下文，按主键直查（绕过 TenantScope），
 *   webhook ID 在 dispatch 时已由 TenantScope 隔离，不存在越权面
 * - HTTP 层失败由 WebhookService::send 内 try-catch 记账（failure_count），
 *   不触发队列重试；tries 仅兜底意外异常（DB 瞬断等）
 * - 目标已停用（is_active=false）时静默跳过
 */
class SendWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int> 重试间隔（秒） */
    public array $backoff = [30, 120];

    public function __construct(
        public int $webhookId,
        public string $event,
        public array $payload,
    ) {}

    public function handle(WebhookService $service): void
    {
        $webhook = TenantWebhook::query()
            ->withoutGlobalScope(TenantScope::class)
            ->find($this->webhookId);

        if (! $webhook || ! $webhook->is_active) {
            return;
        }

        $service->send($webhook, $this->event, $this->payload);
    }
}
