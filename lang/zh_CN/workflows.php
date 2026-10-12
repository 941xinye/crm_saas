<?php

// 中文（简体）翻译：自动化工作流 config 示例规则（{title} / {stalled_days} 等大括号占位符由 WorkflowEngine 替换）
return [
    'rule_stalled' => [
        'name' => '商机停滞自动跟进任务',
        'activity_subject' => '跟进停滞商机「{title}」',
        'activity_notes' => '自动提醒：商机「{title}」已超过 {stalled_days} 天无活动，请及时跟进。',
        'notify_message' => '商机「{title}」已停滞 {stalled_days} 天，已为您自动创建跟进任务。',
    ],

    'rule_high_probability_stage' => [
        'name' => '商机进入高概率阶段提醒',
        'activity_subject' => '商机「{title}」进入高概率阶段',
        'activity_notes' => '商机「{title}」赢单概率 ≥70%，建议尽快安排合同审阅与法务介入。',
    ],
];
