# 配置参考（Phase 1-4 新增）

本文档汇总 Phase 1 到 Phase 4 新增/修改的配置文件，供部署时快速查阅。

---

## crm.php（Phase 1 / Phase 4）

`config/crm.php` — CRM 业务配置

```php
return [
    'reminder' => [
        // 提醒发送渠道：database（站内信），可叠加 mail（邮件）、robot（群机器人 webhook）
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

    // 审计日志保留策略（crm:prune-audit-logs 定时清理）
    'audit' => [
        'retention_days' => env('CRM_AUDIT_RETENTION_DAYS', 365),
    ],

    // 智能商机评分权重（Phase 4，各项之和应为 100），详见 docs/scoring.md
    'scoring' => [
        'value'    => 25,
        'stage'    => 25,
        'velocity' => 20,
        'urgency'  => 15,
        'stall'    => 15,

        // 评分骤降提醒阈值（新评分 ≤ 旧评分 − 阈值时通知负责人）
        'drop_threshold' => env('CRM_SCORE_DROP_THRESHOLD', 15),
    ],

    // 子域名租户识别（Phase 6）：acme.crm.example.com → slug=acme 的租户。
    // 为空时完全禁用，走登录用户所属租户（本地开发/测试无需配置）
    'subdomain_root' => env('CRM_SUBDOMAIN_ROOT'),

    // 队列化配置（P3）
    'queue' => [
        // Webhook 推送是否走队列：true 时需保证 QUEUE_CONNECTION 非 sync 并运行 queue:work
        'webhooks' => env('CRM_WEBHOOK_QUEUE', false),
    ],

    // 系统健康检查（crm:health-check，每小时调度，见 docs/deployment.md）
    'health' => [
        // 队列失败告警：冷却窗口内新增 failed_jobs 时站内信通知平台超管
        'queue_failure_notify' => env('CRM_QUEUE_FAILURE_NOTIFY', true),

        // 平台导出的租户数据 ZIP（storage/app/tenant-exports）保留时长（小时）
        'export_retention_hours' => env('CRM_EXPORT_RETENTION_HOURS', 24),
    ],
];
```

### channels 可选值

- `['database']` — 默认，仅站内信（Notification 中心）
- `['database', 'mail']` — 同时发邮件（需配置 Laravel Mail 驱动）
- `['database', 'robot']` — 同时推送到群机器人（需配置 `robot.url`）
- `['mail']` — 仅邮件

### robot 渠道（Phase 5 群机器人推送）

复用通知的站内信载荷（message + 链接）推送文本消息；实现见 [RobotWebhookChannel](../app/NotificationChannels/RobotWebhookChannel.php)。

```ini
# .env 示例
CRM_ROBOT_TYPE=dingtalk              # dingtalk | wecom | slack
CRM_ROBOT_WEBHOOK_URL=https://oapi.dingtalk.com/robot/send?access_token=xxx
```

| 平台 | 获取 Webhook URL | 请求体格式 |
|------|------------------|-----------|
| 钉钉 | 群设置 → 智能群助手 → 添加机器人（自定义） | `{"msgtype":"text","text":{"content":"CRM通知\n..."}}` |
| 企业微信 | 群设置 → 群机器人 → 添加 | `{"msgtype":"text","text":{"content":"CRM通知\n..."}}` |
| Slack | Incoming Webhooks App | `{"text":"CRM通知\n..."}` |

**注意**：
- 钉钉机器人若开启「自定义关键词」安全设置，请将关键词设为 `CRM`（消息自动带此前缀）
- 推送超时 5s，失败仅记 `storage/logs/laravel.log`（warning），不影响提醒与工作流主流程
- 未配置 `CRM_ROBOT_WEBHOOK_URL` 时 robot 渠道自动从 via() 中剔除，零配置安全降级

### error_alerts（Phase 7 错误监控）

`config/crm.php` — 异常告警推送到群机器人（复用 `reminder.robot` 的 Webhook 配置），详见 [docs/monitoring.md](monitoring.md)。

```php
'error_alerts' => [
    'enabled' => env('CRM_ERROR_ALERTS', true),
    // 同一错误签名（类+文件+行）的告警冷却期（秒）
    'cooldown' => env('CRM_ERROR_ALERT_COOLDOWN', 600),
],
```

```ini
# .env
CRM_ERROR_ALERTS=true
CRM_ERROR_ALERT_COOLDOWN=600   # 秒；同一签名冷却期内不重复推送
```

未配置 `CRM_ROBOT_WEBHOOK_URL` 时仅记日志不推送（graceful degradation）。

---

## sanctum.php（Phase 3）

`config/sanctum.php` — Sanctum Token 认证配置（publish 后生成）

关键项：
```php
'expiration' => env('SANCTUM_TOKEN_EXPIRATION', null),  // null = 永不过期
'middleware' => [
    'stateful_api' => ['web'],  // 对 Sanctum SPA 不使用
],
```

自定义 Token 过期时间：
```ini
# .env
SANCTUM_TOKEN_EXPIRATION=60  # 分钟
```

---

## llm.php（Phase 4）

`config/llm.php` — LLM 集成配置，详见 [docs/llm.md](llm.md)。

```php
return [
    'api_key' => env('LLM_API_KEY', ''),
    'base_url' => env('LLM_BASE_URL', 'https://api.openai.com/v1'),
    'model' => env('LLM_MODEL', 'gpt-4o-mini'),
    'timeout' => (int) env('LLM_TIMEOUT', 20),
];
```

未配置 `LLM_API_KEY` 时 AI 功能自动降级（返回提示，不崩溃）。

---

## workflows.php（Phase 4）

`config/workflows.php` — 自动化工作流的**平台级示例规则**，详见 [docs/workflow.md](workflow.md)。

规则来源分两层（合并执行，DB 规则按 priority 优先）：

| 来源 | 说明 | 管理方式 |
|------|------|----------|
| `tenant_workflows` 表 | 租户自定义规则，按租户隔离（TenantScope） | Settings → 自动化工作流 UI（企业版 `automation` feature） |
| `config/workflows.php` | 平台内置示例规则，所有租户可见可触发 | 改代码部署 |

规则结构：`name` / `model`（FQCN）/ `trigger`（created|updated|entered_stage|stalled）/ `conditions`（[field, operator, expected] AND 列表）/ `actions`（create_activity | notify_owner | set_stage | set_probability）/ `is_active` / `priority`（DB 规则专属，越大越先执行）。

支持占位符：`{tenant_id}` `{activitable_type}` `{activitable_id}` `{owner_id}` `{stalled_days}` `{title}`。

stalled 类规则需配合 Cron：`php artisan crm:run-workflows --all`。

---

## stripe.php（Phase 3）

`config/stripe.php` — Stripe 订阅计费配置

参见 [docs/stripe.md](stripe.md)。

---

## plans.php（原 MVP 已有）

`config/plans.php` — 套餐配额（以下为实际生效值，与 [docs/plans.md](plans.md) 一致）。`monthly_price`（元/CNY）用于平台收入看板 MRR 计算（Phase 8），修改后需 `php artisan config:clear`：

```php
return [
    'free' => [
        'name' => '免费版',
        'monthly_price' => 0,
        'max_users' => 3,
        'trial_days' => 14,
        'quotas' => [
            'companies' => 50,
            'contacts' => 200,
            'leads' => 100,
            'opportunities' => 100,
        ],
        'features' => [/* dashboard/companies/contacts/leads/opportunities(.board)/activities/stages/settings.profile/notifications */],
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
        'features' => [/* free 全部 + reports/audit-logs/ai_followup/settings.team */],
    ],
    'enterprise' => [
        'name' => '企业版',
        'monthly_price' => 999,
        'max_users' => -1,
        'quotas' => [/* -1 不限 */],
        'features' => [/* pro 全部 + webhooks/automation */],
    ],
    'upgrade_messages' => [
        'reports'     => '数据报表功能仅对专业版及以上开放，请升级套餐。',
        'audit-logs'  => '审计日志功能仅对专业版及以上开放，请升级套餐。',
        'ai_followup' => 'AI 跟进建议功能仅对专业版及以上开放，请升级套餐。',
        'webhooks'    => 'Webhook 推送功能仅对企业版开放，请升级套餐。',
        'automation'  => '自动化工作流功能仅对企业版开放，请升级套餐。',
    ],
];
```

---

## 完整 .env 模板

```ini
# ---- 应用 ----
APP_NAME="多租户 CRM"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://crm.yourdomain.com

# ---- 数据库 ----
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=crm
DB_USERNAME=
DB_PASSWORD=

# ---- 邮件（Phase 1 邮箱验证 + Phase 3 inbound email）----
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailgun.org
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@crm.yourdomain.com
MAIL_FROM_NAME="${APP_NAME}"

# ---- 队列（推荐，邮件/Webhook/Stripe 都依赖）----
QUEUE_CONNECTION=database
# Webhook 推送队列化开关（P3，默认 false 同步旁路；true 需保证 worker 运行）
CRM_WEBHOOK_QUEUE=false

# ---- Sanctum ----
SANCTUM_TOKEN_EXPIRATION=

# ---- Stripe（Phase 3，生产必须）----
STRIPE_SECRET_KEY=
STRIPE_PUBLIC_KEY=
STRIPE_WEBHOOK_SECRET=
STRIPE_PRICE_PRO=
STRIPE_PRICE_ENTERPRISE=

# ---- LLM（Phase 4，可选，不配置则 AI 功能降级）----
LLM_API_KEY=
LLM_BASE_URL=https://api.openai.com/v1
LLM_MODEL=gpt-4o-mini
LLM_TIMEOUT=20

# ---- 监控与告警（Phase 7，复用 robot 渠道 Webhook）----
CRM_ERROR_ALERTS=true
CRM_ERROR_ALERT_COOLDOWN=600
CRM_ROBOT_TYPE=dingtalk
CRM_ROBOT_WEBHOOK_URL=
```

---

## 队列 worker

生产建议启用队列（`QUEUE_CONNECTION=database` 并运行 worker）：

```bash
php artisan queue:work --tries=3 --timeout=60
```

Supervisor 配置：
```ini
[program:crm-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/crm/artisan queue:work --sleep=3 --tries=3 --timeout=60
autostart=true
autorestart=true
user=www
numprocs=2
redirect_stderr=true
stdout_logfile=/path/to/crm/storage/logs/queue-worker.log
```

### 队列化范围（P3 评估结论）

| 任务 | 现状 | 说明 |
|------|------|------|
| Webhook 推送 | **可队列化**（`CRM_WEBHOOK_QUEUE=true`） | `App\Jobs\SendWebhookJob`，tries=3 + backoff 30s/120s 兜底意外异常；HTTP 层失败仍走 failure_count 记账 |
| 邮件验证 / 提醒通知 | Laravel 通知默认同步 | 队列连接非 sync 时 Laravel 自动走 `ShouldQueue` 通知队列，无需额外开关 |
| Stripe 事件 | **已队列化**（`App\Jobs\ProcessStripeEvent`） | 控制器仅验签 + 快速 200 回执，事件体入队由 worker 处理（tries=3 + backoff）；QUEUE_CONNECTION=sync（本地/测试）自动退化为同步执行。worker 未就绪前事件滞留 jobs 表，可 `queue:failed` 排查 |
| CSV 导入 | **保持同步** | 前置配额检查 + 逐行错误回显需要即时返回用户；行数量级小（<10k），同步可接受。超大文件场景建议后续拆分 `ImportCsvJob` + 进度表 |
| LLM 调用（评分/建议） | 同步 + 限流 | 评分通过 `crm:refresh-scores` 夜间批处理执行（已是"离线"模式）；AI 跟进建议为用户主动触发的低频操作，同步返回体验更好。已有限流 + prompt 截断 + 静默降级保护 |

> 队列化原则：外部服务调用必须 graceful degradation——无配置或连接失败时静默旁路，不阻塞主业务；队列 worker 不可用时同步模式仍可正常工作（开关回退 `false` 即可）。

---

## Cron（定时任务）

所有定时任务统一经由 Laravel 调度器触发（[routes/console.php](../routes/console.php)），服务器只需一条 crontab（见 [deployment.md](deployment.md) 第五节）：

```bash
* * * * * cd /path/to/crm && php artisan schedule:run >> /dev/null 2>&1
```

| 调度时刻 | 任务 | 说明 |
|----------|------|------|
| 每日 08:30 | `crm:refresh-scores` | 商机评分批量刷新（Phase 4） |
| 每日 08:45 | `crm:run-workflows` | 停滞商机工作流扫描（Phase 4） |
| 每日 09:00 | `crm:send-reminders` | 五类业务提醒扫描（Phase 1） |
| 每小时 | `crm:health-check` | 队列失败告警 + 过期导出清理（Phase 6） |
| 每日 08:15 | `crm:prune-audit-logs` | 审计日志按保留策略清理（Phase 6） |

手动触发（开发/测试）：
```bash
php artisan crm:send-reminders
php artisan crm:send-reminders --activity-hours=48 --close-days=14 --stalled-days=60 --no-activity-days=30
php artisan crm:run-workflows --tenant=acme
php artisan crm:refresh-scores --all
```

---

## CSRF 豁免

Phase 3 新增的 `POST /webhooks/stripe` 已通过 `withoutMiddleware(VerifyCsrfToken)` 豁免 CSRF 检查，不需要手动加到 `VerifyCsrfToken::$except`。

**原因**：Stripe Webhook 自带签名验证（更安全），CSRF Token 对 Stripe 服务器无意义。

---

## 存储目录（Phase 2 附件）

附件存 `storage/app/private/attachments/{tenant_id}/{resource_id}/`，访问通过 API 下载端点（TenantScope + Sanctum Token 校验）。

```bash
# 确保 storage:link 已创建（附件不用 public 但 Livewire 上传需要）
php artisan storage:link
```

---

## 生产部署检查清单

- [ ] `php artisan optimize`（config/route/view 全部缓存）
- [ ] `php artisan queue:restart`（改了代码后）
- [ ] `.env` 设置正确的 STRIPE_* 变量
- [ ] Stripe Dashboard 配置 Webhook → `/webhooks/stripe`
- [ ] Mail 驱动配置正确（邮箱验证 + 邀请邮件）
- [ ] 数据库 `charset=utf8mb4 collation=utf8mb4_unicode_ci`
- [ ] HTTPS 已启用（证书自动续期）
- [ ] `storage/` 目录可写
