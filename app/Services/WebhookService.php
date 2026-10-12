<?php

namespace App\Services;

use App\Jobs\SendWebhookJob;
use App\Models\TenantWebhook;
use Illuminate\Support\Facades\Http;

/**
 * Webhook 推送服务：签名计算、事件分发、重试。
 *
 * 签名算法：HMAC-SHA256(secret, timestamp + "." + body)
 * Header:
 *   X-CRM-Event:   事件名
 *   X-CRM-Tenant-Id: 租户 ID
 *   X-CRM-Timestamp: 请求时间戳（秒）
 *   X-CRM-Signature: 签名
 *   Content-Type:   application/json
 */
class WebhookService
{
    /**
     * 推送事件到当前租户所有订阅的 Webhook。
     * TenantWebhook 挂 BelongsToTenant，TenantScope 自动过滤当前租户。
     *
     * @param  string  $event  事件名，如 company.created
     * @param  array  $payload  载荷数据
     */
    public function dispatch(string $event, array $payload): void
    {
        $webhooks = TenantWebhook::query()
            ->where('is_active', true)
            ->whereJsonContains('events', $event)
            ->get();

        // CRM_WEBHOOK_QUEUE=true 时走队列（生产推荐），默认同步旁路发送
        $queueEnabled = (bool) config('crm.queue.webhooks', false);

        foreach ($webhooks as $webhook) {
            if ($queueEnabled) {
                SendWebhookJob::dispatch($webhook->id, $event, $payload);
            } else {
                $this->send($webhook, $event, $payload);
            }
        }
    }

    /**
     * 向单个 Webhook 发送请求（带重试）。
     *
     * @return bool 是否成功
     */
    public function send(TenantWebhook $webhook, string $event, array $payload): bool
    {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp . '.' . $body, $webhook->secret);

        $headers = [
            'X-CRM-Event' => $event,
            'X-CRM-Tenant-Id' => (string) $webhook->tenant_id,
            'X-CRM-Timestamp' => (string) $timestamp,
            'X-CRM-Signature' => $signature,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        try {
            // withBody 显式设置原始 JSON 字符串，避免默认 json 格式把字符串二次编码，
            // 导致实际传输 body 与 HMAC 签名所用的 body 不一致、接收方验签失败
            $response = Http::timeout(15)
                ->withHeaders($headers)
                ->withBody($body, 'application/json')
                ->post($webhook->url);

            $success = $response->successful();
        } catch (\Throwable $e) {
            logger()->warning('Webhook 推送异常', ['error' => $e->getMessage(), 'url' => $webhook->url]);
            $success = false;
            $response = null;
        }

        $webhook->update([
            'last_sent_at' => now(),
            'last_response' => $response ? mb_substr($response->body(), 0, 500) : 'connection_error',
            'failure_count' => $success ? 0 : $webhook->failure_count + 1,
            'last_failed_at' => $success ? null : now(),
        ]);

        return $success;
    }

    /**
     * 供 TenantWebhookApiController::test() 使用的便捷入口。
     */
    public static function sendTo(TenantWebhook $webhook, string $event, array $payload): bool
    {
        return app(self::class)->send($webhook, $event, $payload);
    }

    /**
     * 验证传入 Webhook 请求的签名（供接收方使用）。
     */
    public static function verifySignature(string $secret, string $timestamp, string $body, string $signature): bool
    {
        $expected = hash_hmac('sha256', $timestamp . '.' . $body, $secret);

        return hash_equals($expected, $signature);
    }
}
