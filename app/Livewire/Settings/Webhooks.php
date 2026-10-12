<?php

namespace App\Livewire\Settings;

use App\Models\TenantWebhook;
use App\Services\Tenancy;
use App\Services\WebhookService;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Webhook 管理页（Settings）— 企业版专属。
 * 与 REST API（TenantWebhookApiController）共用 TenantWebhook 模型与 WebhookService。
 * admin / manager 可管理，sales 只读。
 */
class Webhooks extends Component
{
    public bool $showForm = false;
    public ?int $editingId = null;

    public string $name = '';
    public string $url = '';
    /** @var array<int, string> 订阅事件列表，如 ['company.created', ...] */
    public array $selectedEvents = [];
    public bool $isActive = true;

    /** 可订阅事件矩阵：模型前缀 => 中文名 */
    public const MODELS = [
        'company' => '公司',
        'contact' => '联系人',
        'lead' => '线索',
        'opportunity' => '商机',
        'activity' => '活动',
    ];

    public const ACTIONS = ['created', 'updated', 'deleted'];

    /** 全部合法事件名 */
    public static function validEvents(): array
    {
        $events = [];
        foreach (self::MODELS as $prefix => $label) {
            foreach (self::ACTIONS as $action) {
                $events[] = "{$prefix}.{$action}";
            }
        }

        return $events;
    }

    private function canManage(): bool
    {
        return in_array(auth()->user()->role, ['admin', 'manager'], true);
    }

    public function showCreateForm(): void
    {
        if (! $this->canManage()) {
            return;
        }
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        if (! $this->canManage()) {
            return;
        }
        $webhook = TenantWebhook::query()->findOrFail($id);

        $this->editingId = $webhook->id;
        $this->name = $webhook->name;
        $this->url = $webhook->url;
        $this->selectedEvents = $webhook->events ?? [];
        $this->isActive = $webhook->is_active;
        $this->showForm = true;
    }

    public function save(): void
    {
        if (! $this->canManage()) {
            return;
        }

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url', 'max:500'],
            'selectedEvents' => ['required', 'array', 'min:1'],
            'selectedEvents.*' => ['string', 'in:' . implode(',', self::validEvents())],
        ]);

        if ($this->editingId) {
            $webhook = TenantWebhook::query()->findOrFail($this->editingId);
            $webhook->update([
                'name' => $this->name,
                'url' => $this->url,
                'events' => $this->selectedEvents,
                'is_active' => $this->isActive,
            ]);
            session()->flash('success', __('settings_webhooks.saved_update', ['name' => $webhook->name]));
        } else {
            TenantWebhook::create([
                'name' => $this->name,
                'url' => $this->url,
                'events' => $this->selectedEvents,
                'is_active' => $this->isActive,
                'secret' => 'whsec_' . bin2hex(random_bytes(24)),
            ]);
            session()->flash('success', __('settings_webhooks.saved_create', ['name' => $this->name]));
        }

        $this->resetForm();
    }

    public function delete(int $id): void
    {
        if (! $this->canManage()) {
            return;
        }
        $webhook = TenantWebhook::query()->findOrFail($id);
        $webhook->delete();
        session()->flash('success', __('settings_webhooks.saved_delete', ['name' => $webhook->name]));
    }

    public function toggleActive(int $id): void
    {
        if (! $this->canManage()) {
            return;
        }
        $webhook = TenantWebhook::query()->findOrFail($id);
        $webhook->update(['is_active' => ! $webhook->is_active]);
    }

    /**
     * 重置签名密钥：旧 secret 立即失效，新 secret 一次性展示。
     */
    public function resetSecret(int $id): void
    {
        if (! $this->canManage()) {
            return;
        }
        $webhook = TenantWebhook::query()->findOrFail($id);
        $webhook->update(['secret' => 'whsec_' . bin2hex(random_bytes(24))]);

        session()->flash('success', __('settings_webhooks.secret_reset'));
        session()->flash('new_secret', $webhook->fresh()->secret);
    }

    /**
     * 发送测试事件验证接收端连通性。
     */
    public function sendTest(int $id): void
    {
        if (! $this->canManage()) {
            return;
        }
        $webhook = TenantWebhook::query()->findOrFail($id);

        $payload = [
            'event' => 'webhook.test',
            'model' => 'tenant_webhook',
            'action' => 'test',
            'id' => $webhook->id,
            'data' => [
                'id' => $webhook->id,
                'name' => $webhook->name,
                'tenant_id' => $webhook->tenant_id,
                'message' => 'This is a test event from CRM.',
            ],
            'timestamp' => now()->toIso8601String(),
        ];

        $ok = app(WebhookService::class)->send($webhook, 'webhook.test', $payload);

        if ($ok) {
            session()->flash('success', __('settings_webhooks.test_sent', ['url' => $webhook->url]));
        } else {
            session()->flash('error', __('settings_webhooks.test_failed'));
        }
    }

    public function closeForm(): void
    {
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
        $this->reset('name', 'url', 'selectedEvents', 'isActive');
        $this->resetErrorBag();
    }

    /**
     * 可订阅模型显示标签（翻译键约定：settings_webhooks.model_{prefix}）。
     */
    private function modelLabels(): array
    {
        return collect(self::MODELS)->mapWithKeys(
            fn ($label, $prefix) => [$prefix => __("settings_webhooks.model_{$prefix}")]
        )->all();
    }

    public function render()
    {
        $webhooks = TenantWebhook::query()->orderByDesc('created_at')->get();

        return view('livewire.settings.webhooks', [
            'webhooks' => $webhooks,
            'models' => $this->modelLabels(),
            'actions' => self::ACTIONS,
            'canManage' => $this->canManage(),
        ])->layout('layouts.app', ['title' => __('settings_webhooks.title')]);
    }
}
