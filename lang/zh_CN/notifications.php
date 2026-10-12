<?php

// 中文（简体）翻译：通知（邮件 / 站内信 / 群机器人）
return [
    'greeting' => '您好！',

    'robot_title' => 'CRM通知',
    'view_details' => '查看详情：:url',

    'role_admin' => '管理员',
    'role_manager' => '经理',
    'role_sales' => '销售',

    'tenant_invitation' => [
        'subject' => '邀请您加入「:tenant_name」团队',
        'body' => ':tenant_name 邀请您加入其团队，角色为：:role。',
        'action' => '接受邀请',
        'expire_notice' => '此邀请链接将在 7 天后失效。',
        'ignore_notice' => '如果您没有预期收到此邀请，请忽略此邮件。',
    ],

    'trial_ending' => [
        'mail_subject' => '试用期即将结束：:tenant_name',
        'mail_body' => '租户「:tenant_name」的试用期将于 :ends_at（还剩 :days_left 天）结束。',
        'action' => '查看套餐订阅',
        'mail_footer' => '试用期结束后将无法新增数据，升级套餐可继续使用全部功能。',
        'db_message' => '租户「:tenant_name」试用期将于 :ends_at 结束（还剩 :days_left 天）',
    ],

    'queue_failure' => [
        'mail_subject' => '系统告警：发现 :count 个队列失败任务',
        'mail_body' => '过去 :window_hours 小时内新增 :count 个队列失败任务，请尽快排查。',
        'mail_commands' => '排查命令：`php artisan queue:failed`（查看详情）/ `php artisan queue:retry all`（全部重试）。',
        'db_message' => '过去 :window_hours 小时内新增 :count 个队列失败任务，请排查（queue:failed / queue:retry）',
    ],

    'opportunity_stalled' => [
        'mail_subject' => '商机停滞提醒：:opportunity_title',
        'mail_intro' => '您负责的商机自 :created_date 起未更新：',
        'action' => '查看商机',
        'mail_footer' => '该商机已长期无进展，建议尽快跟进。',
        'db_message' => '商机「:opportunity_title」自 :created_date 起未更新，建议跟进',
    ],

    'opportunity_score_drop' => [
        'mail_subject' => '商机评分骤降：:title',
        'mail_body' => '商机「:title」的智能评分由 :old_score 降至 :new_score，可能存在成交风险，建议尽快跟进。',
        'action' => '查看商机',
        'db_message' => '商机「:title」评分由 :old_score 降至 :new_score，建议尽快跟进',
    ],

    'opportunity_no_activity' => [
        'mail_subject' => '商机跟进提醒：:opportunity_title',
        'mail_intro' => '您负责的商机已有 :days_since_last 天无任何活动记录：',
        'action' => '查看商机',
        'mail_footer' => '建议尽快安排跟进活动，避免商机流失。',
        'db_message' => '商机「:opportunity_title」已有 :days_since_last 天无活动记录，建议跟进',
    ],

    'opportunity_close_date' => [
        'mail_subject' => '商机成交提醒：:opportunity_title',
        'mail_intro' => '您负责的商机预计于 :expected_close_date 成交：',
        'action' => '查看商机',
        'mail_footer' => '请跟进推进，确保按期成交。',
        'db_message' => '商机「:opportunity_title」预计于 :expected_close_date 成交',
    ],

    'activity_due' => [
        'mail_subject' => '活动到期提醒：:activity_subject',
        'mail_intro' => '您有一个活动将于 :scheduled_at 到期：',
        'action' => '查看活动',
        'mail_footer' => '请及时处理。',
        'db_message' => '活动「:activity_subject」将于 :scheduled_at 到期',
    ],
];
