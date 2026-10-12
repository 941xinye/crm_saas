<?php

return [

    'reminder' => [
        // 提醒发送渠道：database（站内信），可加 mail（邮件）、robot（群机器人 webhook）
        'channels' => ['database'],

        // 群机器人推送配置（robot 渠道）：type ∈ dingtalk / wecom / slack
        // url 为空时 robot 渠道自动跳过（graceful degradation）
        'robot' => [
            'type' => env('CRM_ROBOT_TYPE', 'dingtalk'),
            'url' => env('CRM_ROBOT_WEBHOOK_URL'),
        ],

        // 活动到期提前多少小时提醒
        'activity_due_hours' => 24,

        // 商机成交日提前多少天提醒
        'opp_close_days' => 7,

        // 商机创建超过多少天算停滞
        'opp_stalled_days' => 30,

        // 商机无活动超过多少天提醒
        'opp_no_activity_days' => 14,

        // 试用期到期前多少天提醒（通知租户 admin 与平台超管）
        'trial_notice_days' => 3,

        // 同一对象+类型的通知冷却期（小时）
        'cooldown_hours' => 24,
    ],

    // 队列化配置（P3）
    'queue' => [
        // Webhook 推送是否走队列：true 时需保证 QUEUE_CONNECTION 非 sync 并运行 queue:work
        'webhooks' => env('CRM_WEBHOOK_QUEUE', false),
    ],

    // 系统健康检查（crm:health-check 定时任务，见 docs/deployment.md）
    'health' => [
        // 队列失败告警：发现新失败任务时站内信通知平台超管（true 时生效）
        'queue_failure_notify' => env('CRM_QUEUE_FAILURE_NOTIFY', true),

        // 平台导出的租户数据 ZIP（storage/app/tenant-exports）保留时长（小时），超时自动清理
        'export_retention_hours' => env('CRM_EXPORT_RETENTION_HOURS', 24),
    ],

    // 子域名租户识别：配置平台根域名后启用（如 acme.crm.example.com → slug=acme 的租户）。
    // 为空时完全禁用，走「登录用户所属租户」的默认逻辑（本地开发/测试无需配置）
    'subdomain_root' => env('CRM_SUBDOMAIN_ROOT'),

    // 错误监控告警（5xx 异常 → 群机器人，复用 reminder.robot 的 type/url 配置）
    // 见 app/Services/ErrorReporter.php 与 docs/monitoring.md
    'error_alerts' => [
        'enabled' => env('CRM_ERROR_ALERTS', true),
        // 同一错误签名（类+文件+行）的告警冷却期（秒）
        'cooldown' => env('CRM_ERROR_ALERT_COOLDOWN', 600),
    ],

    // 审计日志保留策略（crm:prune-audit-logs 定时清理）
    'audit' => [
        'retention_days' => env('CRM_AUDIT_RETENTION_DAYS', 365),
    ],

    // 智能商机评分权重（Phase 4，各项之和应为 100），详见 docs/scoring.md
    'scoring' => [
        'value'    => 25, // 金额分（租户内归一化）
        'stage'    => 25, // 阶段分（赢单 100 / 输单 20 / 位置归一化 50-90）
        'velocity' => 20, // 活跃度分（活动数 + 更新新鲜度）
        'urgency'  => 15, // 紧迫感分（预计成交日临近）
        'stall'    => 15, // 停滞惩罚分（超过 14 天无活动扣分）

        // 评分骤降提醒阈值：新评分比旧评分低超过该值时通知负责人（crm:refresh-scores）
        'drop_threshold' => env('CRM_SCORE_DROP_THRESHOLD', 15),
    ],
];
