<?php

namespace Tests\Unit;

use App\Jobs\SendWebhookJob;
use App\Models\Tenant;
use App\Models\TenantWebhook;
use App\Services\WebhookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Webhook 推送队列化测试：
 * 开关开启时 dispatch 入队、关闭时同步发送、
 * Job 执行更新状态、停用目标跳过、签名 Header 正确。
 */
class SendWebhookJobTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private TenantWebhook $webhook;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => '队列测试租户',
            'slug' => 'queue-' . Str::lower(Str::random(8)),
            'plan' => 'pro',
            'is_active' => true,
            'trial_ends_at' => now()->addDays(14),
        ]);

        $this->webhook = TenantWebhook::create([
            'tenant_id' => $this->tenant->id,
            'name' => '外部接收器',
            'url' => 'https://receiver.example.com/hook',
            'secret' => 'test-secret-123',
            'events' => ['company.created'],
            'is_active' => true,
        ]);
    }

    private function dispatchEvent(): void
    {
        app(WebhookService::class)->dispatch('company.created', [
            'id' => 1,
            'name' => '测试公司',
        ]);
    }

    public function test_dispatch_pushes_job_when_queue_enabled(): void
    {
        config(['crm.queue.webhooks' => true]);
        Queue::fake();
        Http::fake();

        $this->dispatchEvent();

        Queue::assertPushed(SendWebhookJob::class, fn (SendWebhookJob $job) => $job->webhookId === $this->webhook->id);

        // 入队后不立即发送
        $this->assertNull($this->webhook->fresh()->last_sent_at);
    }

    public function test_dispatch_sends_sync_when_queue_disabled(): void
    {
        // 默认 crm.queue.webhooks = false
        Queue::fake();
        Http::fake();

        $this->dispatchEvent();

        Queue::assertNotPushed(SendWebhookJob::class);
        Http::assertSent(fn ($request) => $request->url() === 'https://receiver.example.com/hook');
        $this->assertNotNull($this->webhook->fresh()->last_sent_at);
    }

    public function test_job_sends_request_and_updates_webhook(): void
    {
        Http::fake();

        (new SendWebhookJob($this->webhook->id, 'company.created', ['id' => 1]))
            ->handle(app(WebhookService::class));

        $fresh = $this->webhook->fresh();
        $this->assertNotNull($fresh->last_sent_at);
        $this->assertSame(0, $fresh->failure_count);
        Http::assertSent(fn ($request) => $request->url() === 'https://receiver.example.com/hook');
    }

    public function test_job_skips_inactive_webhook(): void
    {
        $this->webhook->update(['is_active' => false]);
        Http::fake();

        (new SendWebhookJob($this->webhook->id, 'company.created', ['id' => 1]))
            ->handle(app(WebhookService::class));

        Http::assertNothingSent();
        $this->assertNull($this->webhook->fresh()->last_sent_at);
    }

    public function test_job_skips_missing_webhook(): void
    {
        Http::fake();

        (new SendWebhookJob(999999, 'company.created', ['id' => 1]))
            ->handle(app(WebhookService::class));

        Http::assertNothingSent();
    }

    public function test_job_request_carries_signature_headers(): void
    {
        Http::fake();

        (new SendWebhookJob($this->webhook->id, 'company.created', ['id' => 1]))
            ->handle(app(WebhookService::class));

        Http::assertSent(function ($request) {
            return $request->hasHeader('X-CRM-Event', 'company.created')
                && $request->hasHeader('X-CRM-Tenant-Id', (string) $this->tenant->id)
                && $request->hasHeader('X-CRM-Signature')
                && $request->hasHeader('X-CRM-Timestamp');
        });
    }
}
