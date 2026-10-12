<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Opportunity;
use App\Models\TenantWorkflow;
use App\Models\User;
use App\Services\Tenancy;
use Illuminate\Support\Collection;

/**
 * 自动化工作流引擎（Phase 4）。
 *
 * 规则以 JSON 存储在 config/workflows.php 或数据库 tenant_workflows 表中，
 * 事件驱动（OpportunityObserver / ActivityObserver 触发 evaluate()）。
 *
 * 规则结构：
 * {
 *   "name": "商机停滞自动跟进",
 *   "model": "opportunity",
 *   "trigger": "updated|created|stalled|entered_stage",
 *   "conditions": [
 *     ["stalled_days", ">=", 14]
 *   ],
 *   "actions": [
 *     {"type": "create_activity", "activity_type": "task", "details": "自动跟进提醒（超过 {stalled_days} 天无活动）", "owner_id": "{owner_id}"}
 *   ]
 * }
 *
 * 占位符：{field} 被当前模型字段替换。
 */
class WorkflowEngine
{
    /** 可用触发器 */
    private const TRIGGERS = ['created', 'updated', 'entered_stage', 'stalled'];

    /** 防递归：set_stage/set_probability 动作会再次触发 observer 事件 */
    private array $evaluating = [];

    /**
     * 评估模型事件。
     *
     * @param  string  $event   事件名（created / updated / entered_stage / stalled）
     * @param  mixed   $model   被操作的模型实例
     */
    public function evaluate(string $event, mixed $model): void
    {
        $key = $event . ':' . get_class($model) . ':' . ($model->id ?? '?');
        if (isset($this->evaluating[$key])) {
            return; // 动作更新模型导致的递归触发，直接跳过
        }
        $this->evaluating[$key] = true;

        try {
            $modelClass = get_class($model);

            // 当前租户启用的规则
            $rules = $this->rulesForModel($modelClass);

            foreach ($rules as $rule) {
                if (! in_array($rule['trigger'], self::TRIGGERS, true)) {
                    continue;
                }
                if ($rule['trigger'] !== $event) {
                    continue;
                }
                if (! $this->matchConditions($rule['conditions'] ?? [], $model, $rule['match'] ?? 'all')) {
                    continue;
                }
                $this->executeActions($rule['actions'] ?? [], $model);

                // DB 规则记录最近触发时间（markTriggered 内部已旁路化）
                if (isset($rule['_id'])) {
                    TenantWorkflow::query()->find($rule['_id'])?->markTriggered();
                }
            }
        } finally {
            unset($this->evaluating[$key]);
        }
    }

    /**
     * 匹配条件列表。
     *
     * $match = all：全部满足（AND，默认，向后兼容）；any：任一满足（OR）。
     */
    public function matchConditions(array $conditions, mixed $model, string $match = 'all'): bool
    {
        if ($conditions === []) {
            return true;
        }

        $results = array_map(function ($condition) use ($model) {
            [$field, $operator, $expected] = array_pad($condition, 3, null);

            $actual = match ($field) {
                'stalled_days' => $this->stalledDays($model),
                'value' => $model->value,
                'probability' => $model->probability,
                'days_since_close' => optional($model->expected_close_date)->diffInDays(now()) ?? 0,
                'stage_id' => $model->stage_id,
                default => $model->$field ?? null,
            };

            return $this->compare($actual, $operator, $expected);
        }, $conditions);

        return $match === 'any'
            ? in_array(true, $results, true)
            : ! in_array(false, $results, true);
    }

    /**
     * 执行动作列表。
     */
    public function executeActions(array $actions, mixed $model): void
    {
        foreach ($actions as $action) {
            $this->execute($action['type'], $action['params'] ?? $action, $model);
        }
    }

    private function execute(string $type, array $params, mixed $model): void
    {
        $resolved = $this->resolvePlaceholders($params, $model);

        match ($type) {
            'create_activity' => $this->actionCreateActivity($resolved, $model),
            'notify_owner' => $this->actionNotifyOwner($resolved, $model),
            'set_stage' => $this->actionSetStage($resolved, $model),
            'set_probability' => $this->actionSetProbability($resolved, $model),
            'send_webhook' => $this->actionSendWebhook($resolved, $model),
            default => logger()->warning('Workflow 未知动作类型', ['type' => $type]),
        };
    }

    /**
     * 自动创建跟进活动（对齐 activities 表真实字段）。
     */
    private function actionCreateActivity(array $params, mixed $model): void
    {
        Activity::create([
            'activitable_type' => $params['activitable_type'],
            'activitable_id' => $params['activitable_id'],
            // Activity.user_id NOT NULL：优先显式 owner，回退商机负责人 / 当前用户
            'user_id' => $params['owner_id'] ?? $model->owner_id ?? auth()->id(),
            'type' => in_array($params['type'] ?? null, ['call', 'email', 'meeting', 'note', 'task'], true)
                ? $params['type'] : 'task',
            'subject' => $params['subject'] ?? '自动跟进任务',
            'notes' => $params['notes'] ?? null,
            'scheduled_at' => isset($params['scheduled_at']) ? $this->parseDate($params['scheduled_at']) : now()->addDay(),
        ]);
    }

    /**
     * 解析动作中的日期：支持 '+1 day' 相对格式 / 日期字符串 / Carbon。
     */
    private function parseDate(mixed $value): ?\Illuminate\Support\Carbon
    {
        if ($value instanceof \Illuminate\Support\Carbon) {
            return $value;
        }
        if (is_string($value)) {
            try {
                return now()->modify($value);
            } catch (\Throwable) {
                return \Illuminate\Support\Carbon::parse($value);
            }
        }

        return now()->addDay();
    }

    private function actionNotifyOwner(array $params, mixed $model): void
    {
        // 用 Laravel Notification 发送站内信
        $owner = $model->owner ?? User::query()->find($model->owner_id ?? null);
        if (! $owner) {
            return;
        }
        $owner->notify(new \App\Notifications\AutomationTriggeredNotification($params['message'] ?? '自动化工作流已触发', [
            'model_class' => get_class($model),
            'model_id' => $model->id,
        ]));
    }

    private function actionSetStage(array $params, mixed $model): void
    {
        $model->update(['stage_id' => $params['stage_id']]);
    }

    private function actionSetProbability(array $params, mixed $model): void
    {
        $model->update(['probability' => (int) $params['probability']]);
    }

    /**
     * 推送 Webhook（复用 WebhookService：HMAC 签名 + 队列化/失败计数均由其处理）。
     *
     * params：event（事件名，默认 workflow.triggered）、message（可选附加信息）。
     * 仅推送到租户订阅了该事件的 Webhook；整体 try-catch 旁路化，失败不影响其他动作。
     */
    private function actionSendWebhook(array $params, mixed $model): void
    {
        try {
            app(WebhookService::class)->dispatch(
                $params['event'] ?? 'workflow.triggered',
                [
                    'entity_type' => class_basename($model),
                    'entity_id' => $model->id,
                    'title' => $model->title ?? $model->name ?? null,
                    'message' => $params['message'] ?? null,
                ],
            );
        } catch (\Throwable $e) {
            logger()->warning('Workflow send_webhook 动作推送失败（旁路）', [
                'error' => $e->getMessage(),
                'entity' => class_basename($model) . ':' . ($model->id ?? '?'),
            ]);
        }
    }

    private function compare(mixed $actual, string $operator, mixed $expected): bool
    {
        return match ($operator) {
            '=' , '==' => $actual == $expected,
            '!=' => $actual != $expected,
            '>' => $actual > $expected,
            '>=' => $actual >= $expected,
            '<' => $actual < $expected,
            '<=' => $actual <= $expected,
            'contains' => str_contains((string) $actual, (string) $expected),
            default => false,
        };
    }

    /**
     * 商机停滞天数（无活动 + 未更新）。
     */
    private function stalledDays(mixed $model): int
    {
        if (! $model instanceof Opportunity) {
            return 0;
        }
        $latestActivity = $model->activities()->latest('created_at')->value('created_at');
        $lastDate = $latestActivity && $latestActivity > $model->updated_at
            ? $latestActivity
            : $model->updated_at;

        return $lastDate?->diffInDays(now()) ?? 0;
    }

    /**
     * {field_name} → 实际值替换。
     *
     * 直接对解析后的数组值做替换（而非 JSON 字符串）：
     * 模型类名含反斜杠（App\Models\Opportunity），
     * 替换进 JSON 后 \O \M 是非法转义序列，json_decode 会整体失败。
     */
    private function resolvePlaceholders(array $params, mixed $model): array
    {
        $replacements = [];
        foreach (['tenant_id', 'activitable_type', 'activitable_id', 'owner_id', 'stalled_days', 'title'] as $field) {
            $value = match ($field) {
                'stalled_days' => $this->stalledDays($model),
                'activitable_type' => get_class($model),
                'activitable_id' => $model->id,
                default => $model->$field ?? '',
            };
            $replacements['{' . $field . '}'] = (string) $value;
        }

        $resolve = function ($value) use (&$resolve, $replacements) {
            if (is_array($value)) {
                return array_map($resolve, $value);
            }
            if (is_string($value)) {
                return strtr($value, $replacements);
            }

            return $value;
        };

        return array_map($resolve, $params);
    }

    /**
     * 获取当前租户的规则（数据库 tenant_workflows 表 + config 全局示例规则合并）。
     *
     * - DB 规则按 priority 降序优先执行，config 规则随后
     * - TenantScope 自动限定当前租户，无跨租户泄露
     * - 注意：不做静态缓存——长驻 worker（Octane / queue）下规则变更必须即时生效
     */
    private function rulesForModel(string $modelClass): Collection
    {
        if (app(Tenancy::class)->id() === null) {
            return collect();
        }

        $dbRules = TenantWorkflow::query()
            ->where('is_active', true)
            ->where('model', $modelClass)
            ->orderByDesc('priority')
            ->get()
            ->map(fn (TenantWorkflow $wf) => [
                'name' => $wf->name,
                'model' => $wf->model,
                'trigger' => $wf->trigger,
                'conditions' => $wf->conditions ?? [],
                'match' => $wf->condition_match ?? 'all',
                'actions' => $wf->actions ?? [],
                '_id' => $wf->id,
            ]);

        $configRules = collect(config('workflows.rules', []))
            ->where('model', $modelClass);

        return $dbRules->concat($configRules);
    }
}
