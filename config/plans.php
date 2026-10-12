<?php

return [

    /*
    |--------------------------------------------------------------------------
    | 套餐定义
    |--------------------------------------------------------------------------
    | max_users：租户成员上限；quotas：各资源条数上限。
    | -1 表示不限制。修改后需执行 php artisan config:clear。
    */

    'free' => [
        'name' => '免费版',
        // 月度价格（CNY），平台收入看板 MRR 计算（platform/revenue）
        'monthly_price' => 0,
        'max_users' => 3,
        'trial_days' => 14,
        'quotas' => [
            'companies' => 50,
            'contacts' => 200,
            'leads' => 100,
            'opportunities' => 100,
        ],
        'features' => [
            'dashboard',
            'companies',
            'contacts',
            'leads',
            'opportunities',
            'opportunities.board',
            'activities',
            'stages',
            'settings.profile',
            'notifications',
        ],
    ],

    'pro' => [
        'name' => '专业版',
        'monthly_price' => 199,
        'max_users' => 20,
        'quotas' => [
            'companies' => 2000,
            'contacts' => 10000,
            'leads' => 5000,
            'opportunities' => 5000,
        ],
        'features' => [
            'dashboard',
            'companies',
            'contacts',
            'leads',
            'opportunities',
            'opportunities.board',
            'activities',
            'stages',
            'reports',
            'audit-logs',
            'ai_followup',
            'custom_fields',
            'settings.profile',
            'settings.team',
            'notifications',
        ],
    ],

    'enterprise' => [
        'name' => '企业版',
        'monthly_price' => 999,
        'max_users' => -1,
        'quotas' => [
            'companies' => -1,
            'contacts' => -1,
            'leads' => -1,
            'opportunities' => -1,
        ],
        'features' => [
            'dashboard',
            'companies',
            'contacts',
            'leads',
            'opportunities',
            'opportunities.board',
            'activities',
            'stages',
            'reports',
            'audit-logs',
            'ai_followup',
            'webhooks',
            'automation',
            'custom_fields',
            'settings.profile',
            'settings.team',
            'notifications',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 功能 → 升级提示文案（按最低可解锁 plan 分组）
    |--------------------------------------------------------------------------
    | 前端和中间件统一使用，避免多处硬编码。
    */
    'upgrade_messages' => [
        'reports'        => '数据报表功能仅对专业版及以上开放，请升级套餐。',
        'audit-logs'     => '审计日志功能仅对专业版及以上开放，请升级套餐。',
        'ai_followup'    => 'AI 跟进建议功能仅对专业版及以上开放，请升级套餐。',
        'custom_fields'  => '自定义字段功能仅对专业版及以上开放，请升级套餐。',
        'webhooks'       => 'Webhook 推送功能仅对企业版开放，请升级套餐。',
        'automation'     => '自动化工作流功能仅对企业版开放，请升级套餐。',
    ],
];
