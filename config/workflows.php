<?php

/**
 * 自动化工作流规则（Phase 4）。
 *
 * 每条规则结构：
 *   name:       规则名（展示用）
 *   model:      目标模型类（FQCN）
 *   trigger:    触发事件：created / updated / entered_stage / stalled
 *   conditions: 条件列表，每项 [field, operator, expected]
 *   match:      条件匹配模式：all（全部满足，默认）/ any（任一满足）
 *   actions:    动作列表，每项 {type, params}
 *
 * 可用 field：stalled_days, value, probability, days_since_close, stage_id, tenant_id, owner_id, title
 * 可用 operator：= == != > >= < <= contains
 * 可用 action type：create_activity, notify_owner, set_stage, set_probability, send_webhook
 *
 * create_activity 参数（对齐 activities 表）：type(call/email/meeting/note/task)、subject、notes、scheduled_at、owner_id
 * notify_owner 参数：message
 * send_webhook 参数：event（事件名，默认 workflow.triggered，需与租户 Webhook 订阅一致）、message（可选附加信息）
 * 可用占位符：{tenant_id}, {activitable_type}, {activitable_id}, {owner_id}, {stalled_days}, {title}
 *
 * send_webhook 示例（推送到租户订阅了该事件的 Webhook）：
 *   ['type' => 'send_webhook', 'params' => ['event' => 'workflow.triggered', 'message' => '商机「{title}」触发自动化']]
 */
return [
    'rules' => [
        [
            'name' => 'workflows.rule_stalled.name',
            'model' => \App\Models\Opportunity::class,
            'trigger' => 'stalled',
            'conditions' => [
                ['stalled_days', '>=', 14],
            ],
            'actions' => [
                [
                    'type' => 'create_activity',
                    'params' => [
                        'activitable_type' => '{activitable_type}',
                        'activitable_id' => '{activitable_id}',
                        'owner_id' => '{owner_id}',
                        'type' => 'task',
                        'subject' => 'workflows.rule_stalled.activity_subject',
                        'notes' => 'workflows.rule_stalled.activity_notes',
                        'scheduled_at' => '+1 day',
                    ],
                ],
                [
                    'type' => 'notify_owner',
                    'params' => [
                        'message' => 'workflows.rule_stalled.notify_message',
                    ],
                ],
            ],
        ],
        [
            'name' => 'workflows.rule_high_probability_stage.name',
            'model' => \App\Models\Opportunity::class,
            'trigger' => 'entered_stage',
            'conditions' => [
                ['probability', '>=', 70],
            ],
            'actions' => [
                [
                    'type' => 'create_activity',
                    'params' => [
                        'activitable_type' => '{activitable_type}',
                        'activitable_id' => '{activitable_id}',
                        'owner_id' => '{owner_id}',
                        'type' => 'note',
                        'subject' => 'workflows.rule_high_probability_stage.activity_subject',
                        'notes' => 'workflows.rule_high_probability_stage.activity_notes',
                    ],
                ],
            ],
        ],
    ],
];
