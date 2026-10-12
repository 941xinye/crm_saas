<?php

namespace App\Livewire\Settings;

use App\Models\Opportunity;
use App\Models\Stage;
use App\Models\TenantWorkflow;
use Livewire\Component;

/**
 * 自动化工作流规则管理页（Settings）— 企业版专属（plan.feature:automation）。
 *
 * 规则结构对齐 WorkflowEngine：model / trigger / conditions / actions。
 * 条件为 [field, operator, value] 行编辑；动作按类型显示对应参数表单。
 * admin / manager 可管理，sales 只读。
 */
class Automation extends Component
{
    public bool $showForm = false;
    public ?int $editingId = null;

    public string $name = '';
    public string $model = Opportunity::class;
    public string $trigger = 'stalled';
    /** @var array<int, array{field: string, operator: string, value: string}> */
    public array $conditions = [];
    /** 条件匹配模式：all（全部满足，默认）/ any（任一满足） */
    public string $conditionMatch = 'all';
    /** @var array<int, array<string, mixed>> 每行含 type + 各类型参数字段 */
    public array $actions = [];
    public bool $isActive = true;
    public int $priority = 0;

    /** 可选目标模型（Observer 当前仅注册在 Opportunity 上） */
    public const MODELS = [
        Opportunity::class => '商机',
    ];

    public const TRIGGERS = ['created', 'updated', 'entered_stage', 'stalled'];

    public const TRIGGER_LABELS = [
        'created' => '创建时',
        'updated' => '更新时',
        'entered_stage' => '进入阶段时',
        'stalled' => '停滞扫描（每日）',
    ];

    public const CONDITION_FIELDS = [
        'stalled_days' => '停滞天数',
        'value' => '商机金额',
        'probability' => '赢单概率',
        'days_since_close' => '距预计成交日',
        'stage_id' => '阶段 ID',
        'owner_id' => '负责人 ID',
    ];

    public const OPERATORS = ['=', '!=', '>', '>=', '<', '<=', 'contains'];

    public const MATCH_MODES = [
        'all' => '全部条件满足（AND）',
        'any' => '任一条件满足（OR）',
    ];

    public const ACTION_TYPES = ['create_activity', 'notify_owner', 'set_stage', 'set_probability', 'send_webhook'];

    public const ACTION_LABELS = [
        'create_activity' => '创建跟进活动',
        'notify_owner' => '通知负责人',
        'set_stage' => '更新阶段',
        'set_probability' => '更新赢单概率',
        'send_webhook' => '推送 Webhook',
    ];

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

    public function addCondition(): void
    {
        if (! $this->canManage()) {
            return;
        }
        $this->conditions[] = ['field' => 'stalled_days', 'operator' => '>=', 'value' => ''];
    }

    public function removeCondition(int $index): void
    {
        if (! $this->canManage()) {
            return;
        }
        unset($this->conditions[$index]);
        $this->conditions = array_values($this->conditions);
    }

    public function addAction(): void
    {
        if (! $this->canManage()) {
            return;
        }
        $this->actions[] = [
            'type' => 'create_activity',
            'activity_type' => 'task',
            'subject' => '',
            'notes' => '',
            'scheduled_at' => '+1 day',
            'message' => '',
            'stage_id' => '',
            'probability' => '',
            'event' => '',
        ];
    }

    public function removeAction(int $index): void
    {
        if (! $this->canManage()) {
            return;
        }
        unset($this->actions[$index]);
        $this->actions = array_values($this->actions);
    }

    public function edit(int $id): void
    {
        if (! $this->canManage()) {
            return;
        }
        $workflow = TenantWorkflow::query()->findOrFail($id);

        $this->editingId = $workflow->id;
        $this->name = $workflow->name;
        $this->model = $workflow->model;
        $this->trigger = $workflow->trigger;
        $this->conditionMatch = $workflow->condition_match ?? 'all';
        $this->conditions = collect($workflow->conditions ?? [])
            ->map(fn ($c) => [
                'field' => (string) ($c[0] ?? ''),
                'operator' => (string) ($c[1] ?? '>='),
                'value' => (string) ($c[2] ?? ''),
            ])->all();
        $this->actions = collect($workflow->actions ?? [])
            ->map(fn ($a) => $this->actionRowFromEngine($a))->all();
        $this->isActive = $workflow->is_active;
        $this->priority = $workflow->priority;
        $this->showForm = true;
    }

    public function save(): void
    {
        if (! $this->canManage()) {
            return;
        }

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'model' => ['required', 'in:' . implode(',', array_keys(self::MODELS))],
            'trigger' => ['required', 'in:' . implode(',', self::TRIGGERS)],
            'conditionMatch' => ['required', 'in:all,any'],
            'conditions' => ['array'],
            'conditions.*.field' => ['required', 'in:' . implode(',', array_keys(self::CONDITION_FIELDS))],
            'conditions.*.operator' => ['required', 'in:' . implode(',', self::OPERATORS)],
            'conditions.*.value' => ['required', 'string', 'max:255'],
            'actions' => ['required', 'array', 'min:1'],
            'actions.*.type' => ['required', 'in:' . implode(',', self::ACTION_TYPES)],
            'actions.*.stage_id' => ['required_if:actions.*.type,set_stage'],
            'actions.*.probability' => ['required_if:actions.*.type,set_probability', 'integer', 'min:0', 'max:100'],
            'actions.*.event' => ['required_if:actions.*.type,send_webhook', 'regex:/^[A-Za-z0-9._-]{1,100}$/'],
        ]);

        $actions = collect($this->actions)->map(fn ($row) => $this->buildEngineAction($row))->all();

        $attributes = [
            'name' => $this->name,
            'model' => $this->model,
            'trigger' => $this->trigger,
            'condition_match' => $this->conditionMatch,
            'conditions' => collect($this->conditions)
                ->map(fn ($c) => [$c['field'], $c['operator'], $this->castConditionValue($c['value'])])
                ->values()->all(),
            'actions' => $actions,
            'is_active' => $this->isActive,
            'priority' => $this->priority,
        ];

        if ($this->editingId) {
            $workflow = TenantWorkflow::query()->findOrFail($this->editingId);
            $workflow->update($attributes);
            session()->flash('success', "工作流规则「{$workflow->name}」已更新");
        } else {
            TenantWorkflow::create($attributes);
            session()->flash('success', "工作流规则「{$this->name}」已创建");
        }

        $this->resetForm();
    }

    public function delete(int $id): void
    {
        if (! $this->canManage()) {
            return;
        }
        $workflow = TenantWorkflow::query()->findOrFail($id);
        $workflow->delete();
        session()->flash('success', "工作流规则「{$workflow->name}」已删除");
    }

    public function toggleActive(int $id): void
    {
        if (! $this->canManage()) {
            return;
        }
        $workflow = TenantWorkflow::query()->findOrFail($id);
        $workflow->update(['is_active' => ! $workflow->is_active]);
    }

    public function closeForm(): void
    {
        $this->resetForm();
    }

    /**
     * 表单动作行 → 引擎动作结构（占位符在引擎运行时解析）。
     */
    private function buildEngineAction(array $row): array
    {
        $type = $row['type'];

        $params = match ($type) {
            'create_activity' => [
                'activitable_type' => '{activitable_type}',
                'activitable_id' => '{activitable_id}',
                'owner_id' => '{owner_id}',
                'type' => in_array($row['activity_type'] ?? '', ['call', 'email', 'meeting', 'note', 'task'], true)
                    ? $row['activity_type'] : 'task',
                'subject' => trim((string) ($row['subject'] ?? '')) ?: '自动跟进任务',
                'notes' => trim((string) ($row['notes'] ?? '')) ?: null,
                'scheduled_at' => trim((string) ($row['scheduled_at'] ?? '')) ?: '+1 day',
            ],
            'notify_owner' => [
                'message' => trim((string) ($row['message'] ?? '')) ?: '自动化工作流已触发',
            ],
            'set_stage' => [
                'stage_id' => (int) $row['stage_id'],
            ],
            'set_probability' => [
                'probability' => (int) $row['probability'],
            ],
            'send_webhook' => [
                'event' => trim((string) ($row['event'] ?? '')) ?: 'workflow.triggered',
                'message' => trim((string) ($row['message'] ?? '')) ?: null,
            ],
            default => [],
        };

        return ['type' => $type, 'params' => $params];
    }

    /**
     * 引擎动作结构 → 表单动作行（编辑回填）。
     */
    private function actionRowFromEngine(array $action): array
    {
        $type = (string) ($action['type'] ?? 'create_activity');
        $p = $action['params'] ?? $action;

        return [
            'type' => $type,
            'activity_type' => (string) ($p['type'] ?? 'task'),
            'subject' => (string) ($p['subject'] ?? ''),
            'notes' => (string) ($p['notes'] ?? ''),
            'scheduled_at' => (string) ($p['scheduled_at'] ?? '+1 day'),
            'message' => (string) ($p['message'] ?? ''),
            'stage_id' => (string) ($p['stage_id'] ?? ''),
            'probability' => (string) ($p['probability'] ?? ''),
            'event' => (string) ($p['event'] ?? ''),
        ];
    }

    /**
     * 数字条件转 int，其余保留字符串（引擎 compare 用宽松 ==，类型兼容即可）。
     */
    private function castConditionValue(string $value): mixed
    {
        return is_numeric($value) ? $value + 0 : $value;
    }

    private function resetForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
        $this->reset('name', 'model', 'trigger', 'conditions', 'conditionMatch', 'actions', 'isActive', 'priority');
        $this->resetErrorBag();
    }

    public function render()
    {
        $workflows = TenantWorkflow::query()
            ->orderByDesc('priority')
            ->orderByDesc('created_at')
            ->get();
        $stages = Stage::query()->orderBy('order')->get();

        return view('livewire.settings.automation', [
            'workflows' => $workflows,
            'models' => self::MODELS,
            'triggers' => self::TRIGGERS,
            'triggerLabels' => self::TRIGGER_LABELS,
            'conditionFields' => self::CONDITION_FIELDS,
            'operators' => self::OPERATORS,
            'actionLabels' => self::ACTION_LABELS,
            'stages' => $stages,
            'canManage' => $this->canManage(),
        ])->layout('layouts.app', ['title' => '自动化工作流']);
    }
}
