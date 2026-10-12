<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * 租户自动化工作流规则。
 *
 * 与 config/workflows.php 的全局示例规则结构一致：
 *   model       目标模型 FQCN
 *   trigger     created / updated / entered_stage / stalled
 *   conditions  [[field, operator, expected], ...]
 *   condition_match  all（全部满足，默认）/ any（任一满足）
 *   actions     [{type, params}, ...]（顺序执行）
 *
 * 合法 trigger / action 由 WorkflowEngine 白名单约束，非法值在评估时被跳过。
 */
class TenantWorkflow extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'name',
        'model',
        'trigger',
        'conditions',
        'condition_match',
        'actions',
        'is_active',
        'priority',
        'last_triggered_at',
    ];

    protected function casts(): array
    {
        return [
            'conditions' => 'array',
            'actions' => 'array',
            'is_active' => 'boolean',
            'last_triggered_at' => 'datetime',
        ];
    }

    /**
     * 标记规则已触发（供 UI 展示最近触发时间；失败静默不阻塞业务）。
     */
    public function markTriggered(): void
    {
        try {
            $this->forceFill(['last_triggered_at' => now()])->save();
        } catch (\Throwable) {
            // 旁路：统计字段失败不影响主流程
        }
    }
}
