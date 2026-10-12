<?php

namespace App\Observers;

use App\Services\WorkflowEngine;

/**
 * 工作流观察者：业务模型事件 → WorkflowEngine 评估。
 *
 * - created / updated 直接透传
 * - updated 时 stage_id 变化 → 额外触发 entered_stage
 * - stalled 由 crm:run-workflows 命令每日扫描触发（非事件驱动）
 * - 旁路 try-catch：工作流失败不影响主业务
 */
class WorkflowObserver
{
    public function __construct(private WorkflowEngine $engine)
    {
    }

    public function created(mixed $model): void
    {
        $this->evaluate('created', $model);
    }

    public function updated(mixed $model): void
    {
        // 阶段变更 → entered_stage 事件（供"进入高概率阶段"类规则使用）
        if ($model->isDirty('stage_id')) {
            $this->evaluate('entered_stage', $model);
        }

        $this->evaluate('updated', $model);
    }

    private function evaluate(string $event, mixed $model): void
    {
        try {
            $this->engine->evaluate($event, $model);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
