<?php

namespace App\Observers;

use App\Models\Activity;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Services\WebhookService;

/**
 * Webhook 观察者：监听业务模型事件，异步推送已订阅的 Webhook。
 *
 * 通过 boot 注册到 AppServiceProvider，避免手动为每个模型注册。
 */
class WebhookObserver
{
    /**
     * 允许 Webhook 推送的模型类 + 事件前缀映射。
     */
    private const MAP = [
        Company::class => 'company',
        Contact::class => 'contact',
        Lead::class => 'lead',
        Opportunity::class => 'opportunity',
        Activity::class => 'activity',
    ];

    public function __construct(private WebhookService $webhook)
    {
    }

    public function created(mixed $model): void
    {
        $this->push($model, 'created');
    }

    public function updated(mixed $model): void
    {
        // 跳过只更新 timestamps 的假更新
        if ($model->isDirty(['created_at', 'updated_at', 'deleted_at']) && count($model->getDirty()) === count(['created_at', 'updated_at', 'deleted_at'])) {
            return;
        }

        $this->push($model, 'updated');
    }

    public function deleted(mixed $model): void
    {
        $this->push($model, 'deleted');
    }

    public function restored(mixed $model): void
    {
        $this->push($model, 'restored');
    }

    private function push(mixed $model, string $action): void
    {
        $class = $model::class;
        $prefix = self::MAP[$class] ?? null;
        if ($prefix === null) {
            return;
        }

        $event = "{$prefix}.{$action}";
        $payload = [
            'event' => $event,
            'model' => $prefix,
            'action' => $action,
            'id' => $model->id,
            'data' => $model->toArray(),
            'timestamp' => now()->toIso8601String(),
        ];

        // 旁路 try-catch：Webhook 推送失败不影响主业务
        try {
            $this->webhook->dispatch($event, $payload);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
