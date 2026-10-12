<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RestCrud;
use App\Models\TenantWebhook;
use App\Services\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Webhook 配置管理 REST API — admin / manager 角色可 CRUD，sales 只读。
 */
class TenantWebhookApiController extends ApiController
{
    use RestCrud;

    protected string $model = TenantWebhook::class;

    protected array $searchFields = ['name'];

    protected array $sortableColumns = ['name', 'created_at'];

    protected ?string $ownerColumn = null; // Webhook 全租户可见，按角色控制

    protected function beforeStore(Request $request, array $data): array
    {
        // 自动生成 secret（除非用户显式传入）
        if (empty($data['secret'])) {
            $data['secret'] = 'whsec_' . bin2hex(random_bytes(24));
        }

        return $data;
    }

    /**
     * 重写 store：创建响应一次性展示 secret（模型默认 $hidden 脱敏）。
     */
    public function store(Request $request)
    {
        $data = $request->validate($this->rulesForStore($request));

        if (method_exists($this, 'beforeStore')) {
            $data = $this->beforeStore($request, $data);
        }

        $modelClass = $this->model;
        $model = new $modelClass($data);
        $model->save();

        return $this->created($model->makeVisible('secret'));
    }

    protected function rulesForStore(Request $request): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url', 'max:500'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
            'secret' => ['nullable', 'string', 'max:128'],
        ];
    }

    protected function rulesForUpdate(Request $request): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'url' => ['sometimes', 'url', 'max:500'],
            'events' => ['sometimes', 'array', 'min:1'],
            'events.*' => ['string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
            'reset_secret' => ['nullable', 'boolean'], // true → 自动重新生成
        ];
    }

    /**
     * 重写 update：支持 reset_secret 自动重生成 secret。
     */
    public function update(Request $request, int $id)
    {
        $webhook = TenantWebhook::query()->findOrFail($id);

        if ($request->boolean('reset_secret')) {
            $webhook->update([
                'secret' => 'whsec_' . bin2hex(random_bytes(24)),
            ]);

            return $this->success([
                'id' => $webhook->id,
                'secret' => $webhook->secret,
                'message' => __('api.webhook_secret_regenerated'),
            ]);
        }

        return parent::update($request, $id);
    }

    /**
     * 发送测试事件（用于验证接收端是否正确）。
     *
     * POST /api/v1/webhooks/{id}/test
     */
    public function test(int $id): \Illuminate\Http\JsonResponse
    {
        $webhook = TenantWebhook::query()->where('id', $id)->first();

        if (! $webhook) {
            return $this->error(__('api.webhook_not_found'), 404);
        }

        $event = 'webhook.test';
        $payload = [
            'event' => $event,
            'model' => 'tenant_webhook',
            'action' => 'test',
            'id' => $webhook->id,
            'data' => [
                'id' => $webhook->id,
                'name' => $webhook->name,
                'tenant_id' => $webhook->tenant_id,
                'message' => 'This is a test event from CRM Phase 4.',
            ],
            'timestamp' => now()->toIso8601String(),
        ];

        $result = \App\Services\WebhookService::sendTo($webhook, $event, $payload);

        if ($result) {
            return $this->success(['status' => 'sent', 'url' => $webhook->url, 'event' => $event]);
        }

        return $this->error(__('api.webhook_test_failed'), 502);
    }
}
