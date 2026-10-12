# 多租户 CRM SaaS 平台

一个基于 Laravel 13 的多租户 CRM（客户关系管理）SaaS 平台，灵感来自 HubSpot 的简化版实现。

## 技术栈

| 分类 | 技术 |
|------|------|
| 后端框架 | Laravel 13.17 |
| PHP | 8.3+ |
| 前端驱动 | Livewire 4.4 + Volt 1.11 |
| 数据库 | MySQL 8.4 |
| UI | Tailwind CSS |
| 本地环境 | ServBay |
| 队列/缓存 | Laravel 默认（数据库/文件） |

## 功能清单

### 核心（MVP 原有）
- **多租户隔离** — 单库 + `tenant_id` 全局 Scope，自动注入，console/Tinker 安全逃生舱
- **客户公司管理** — Company CRUD、行业分类、负责人分配
- **联系人** — Contact CRUD、关联公司、多联系人归属同一公司
- **线索（Leads）** — 潜在客户录入、来源追踪、状态流转
- **商机（Opportunities）** — 金额、预计成交日期、赢单概率、关联公司/联系人
- **销售漏斗（Kanban）** — 商机看板，按阶段拖拽实时移动
- **销售漏斗阶段** — Stage 可配置、有序排列（**Phase 1 已改为租户隔离**）
- **跟进活动** — Activity 多态关联（morphs），记录跟进历史
- **仪表板** — 公司数、线索数、商机总金额、本月新增、阶段分布、最近活动
- **CSV 导入导出** — 五个业务模型的批量导入/导出/模板下载（UTF-8 BOM 兼容 Excel）
- **数据报表** — 销售漏斗转化率、按人业绩、月度趋势、阶段分布
- **通知中心** — 站内通知列表、已读管理
- **平台超管后台** — 租户管理（新建/编辑/禁用/套餐变更）
- **租户设置** — 租户资料、套餐状态
- **团队成员管理** — 角色（admin / manager / sales）、负责人分配

### Phase 1 — 生产加固
- **邮箱验证** — 注册后自动发送验证邮件，未验证用户无法进入业务区
- **团队邀请流程** — 管理员通过 email 发送邀请链接，被邀请人注册后自动加入租户
- **商机软删除恢复** — 已删除商机 tab + 恢复 + 彻底删除
- **审计日志 diff 对比** — 展开详情时显示字段级 before/after diff，变更行高亮
- **仪表板待我处理** — 7 天内到期活动 / 14 天未更新商机 / 本周到期商机
- **商机无活动提醒** — 超过 N 天无活动跟进的商机自动通知（可配置阈值）
- **邮件通知渠道** — 所有提醒可叠加发邮件（config 开关）

### Phase 2 — 业务升级
- **跟进时间线** — 公司/联系人/商机详情页聚合展示活动历史，类型图标 + 状态徽章
- **高级筛选/排序** — 列表页表头点击排序、搜索、负责人筛选（白名单防注入）
- **批量改阶段/负责人** — 商机列表批量操作，走 AuditService::withBatch 审计
- **数据权限细化** — sales 仅可见/操作自己负责的数据（列表/详情/报表同步收紧）
- **附件管理** — 公司/联系人/商机上传附件（私有磁盘存储，10MB 限制）

### Phase 3 — 商业化扩展
- **REST API** — Sanctum Token 认证 + `/api/v1` 完整 CRUD，租户隔离自动生效
- **API 跨租户隔离验证** — Sanctum → BootstrapTenancy → TenantScope 完整链路
- **Webhook 事件推送** — 5 个业务模型的 CRUD 事件自动推送，HMAC-SHA256 签名
- **邮件转线索** — Mailgun/SendGrid Inbound Parse → 自动创建 Lead
- **Stripe 订阅计费** — Checkout Session 创建 + Webhook 事件同步租户状态

### Phase 4 — 智能化
- **智能商机评分** — 多维度加权评分（金额/阶段/活跃度/紧迫度/停滞惩罚，权重可配置），`crm:refresh-scores` 批量刷新
- **LLM 通用适配器** — 兼容 OpenAI 及各类兼容接口（阿里云百炼 / DeepSeek / 硅基流动），无配置时静默降级
- **AI 跟进建议** — 根据商机上下文生成跟进邮件草稿（限流 10 次/分钟，prompt 自动截断）
- **自动化工作流引擎** — 事件驱动规则（created/updated/entered_stage/stalled），支持条件匹配与动作执行（create_activity / notify_owner / set_stage / set_probability），规则存于 config/workflows.php

### Phase 5 — 生产化扩展
- **工作流规则租户化** — 规则存入 tenant_workflows 表（TenantScope 隔离），Settings 页可视化编辑，与 config 示例规则双层共存
- **机器人 Webhook 通知** — 钉钉 / 企业微信 / Slack 自定义机器人渠道（config 开关，无配置静默跳过）
- **全局跨实体搜索** — 顶栏搜索框一键检索公司 / 联系人 / 线索 / 商机，租户隔离 + sales 数据权限同步生效（详见 [docs/search.md](docs/search.md)）
- **Webhook 推送队列化** — `CRM_WEBHOOK_QUEUE` 开关，SendWebhookJob 队列任务（tries=3 + backoff），默认同步旁路
- **API 限流** — 登录/注册 10 次/分钟/IP，认证端点 60 次/分钟/用户，防暴力破解
- **推送可观测** — Webhook 管理页展示失败计数 / 最近失败时间 / 最近响应；工作流列表展示最近触发时间

### Phase 6 — 完整度加固（P0-P3）

- **REST API 补全** — Stages / Users 只读端点、附件 multipart 上传（10MB + 类型白名单 + morph 校验），api.md 全量更新
- **Webhook Secret 脱敏** — API 响应默认隐藏签名密钥，仅创建时一次性返回
- **Stripe Customer Portal** — 订阅页一键跳转自助管理账单/支付方式；past_due 状态横幅提示
- **试用到期提醒** — 试用期剩余 ≤3 天时通知租户 admin 与平台超管（冷却去重 + 可静音）
- **用户级通知偏好** — 「设置 → 通知偏好」按类型开关站内信/邮件提醒
- **平台超管面板增强** — 各租户配额使用率、成员数、最近活跃一目了然
- **审计日志导出与保留策略** — 高级筛选结果一键导出 CSV（含 batch_id / IP / before/after）；`crm:prune-audit-logs` 定时清理
- **租户数据导出/删除（GDPR）** — 平台面板一键导出全租户数据 ZIP（8 表 CSV）或清除业务数据（保留审计），详见 [docs/gdpr.md](docs/gdpr.md)
- **API Token 自助管理** — 「设置 → API Tokens」创建（明文一次性展示）/ 列表 / 吊销
- **报表可视化 + CSV 导出** — ECharts 趋势/漏斗图表（原有）+ 趋势/业绩/阶段/漏斗四类数据一键导出
- **商机评分骤降提醒** — `crm:refresh-scores` 刷新时降幅超阈值自动通知负责人（冷却去重 + 可静音）
- **两步验证（2FA）** — 无依赖 RFC 6238 TOTP（兼容 Google Authenticator 等）+ 8 个用后即焚恢复码；登录挑战页校验，恢复码丢失可重新生成（详见 [docs/security.md](docs/security.md)）
- **子域名租户识别** — 配置 `CRM_SUBDOMAIN_ROOT` 后 `acme.crm.example.com` 自动锁定租户上下文，归属校验防跨租户访问（详见 [docs/architecture.md](docs/architecture.md) 第七章）
- **自定义字段** — 租户自定义为公司/联系人/商机增加业务字段（6 种类型 + 必填验证 + 列表/API 筛选），专业版及以上（详见 [docs/custom-fields.md](docs/custom-fields.md)）
- **数据查重** — 公司按名称 / 联系人按邮箱：表单实时查重提示（非阻塞）、CSV 导入重复行自动跳过计数
- **新手引导** — 仪表板快速上手 checklist（建公司→录联系人→录线索→转商机→邀请成员），完成后自动隐藏、可手动关闭
- **AI 活动摘要** — 详情页一键生成跟进历史要点（限流 + 缓存 + prompt 截断 + 静默降级，详见 [docs/llm.md](docs/llm.md)）
- **工作流扩展** — 新增 `send_webhook` 动作（复用 WebhookService，失败旁路化）与条件匹配模式 `all/any`（详见 [docs/workflow.md](docs/workflow.md)）
- **API Token scope** — 创建 Token 可勾选 read/write 权限范围，中间件按 HTTP 方法校验 ability（GET→read、写→write），只读集成最小授权（详见 [docs/security.md](docs/security.md)）
- **界面多语言（i18n）** — 内置中文（默认）/ 英文两套界面语言，顶栏一键切换存 `users.locale`（详见 [docs/i18n.md](docs/i18n.md)）

## 快速开始

```bash
# 1. 克隆代码
git clone <your-repo> crm
cd crm

# 2. 安装依赖
composer install

# 3. 复制环境变量
cp .env.example .env
php artisan key:generate

# 4. 编辑 .env（重点项）
#   DB_DATABASE=crm
#   DB_USERNAME=root
#   DB_PASSWORD=你的密码
#   APP_URL=http://crm.servbay.host

# 5. 迁移并填充演示数据
php artisan migrate:fresh --seed

# 6. 在 ServBay 中添加网站
#    域名：crm.servbay.host
#    Root 指向：项目目录的 public/
#    PHP 版本：8.3+

# 7. 访问 http://crm.servbay.host
```

## 演示账号

| 角色 | 邮箱 | 密码 | 租户 |
|------|------|------|------|
| 平台超管 | `admin@platform.test` | `password` | — |
| 租户 admin | `admin@acme.test` | `password` | Acme Corporation（pro） |
| 租户 admin | `admin@globex.test` | `password` | Globex Industries（enterprise） |
| 租户 admin | `admin@initech.test` | `password` | Initech Solutions（free，trial 已过期） |

> 平台超管登录后进入 `/platform/tenants` 管理租户。
> 租户用户登录后自动进入自己的 CRM 仪表板，数据被严格隔离。

## 目录速览

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── AttachmentController.php     # Phase 2 附件下载
│   │   ├── StripeWebhookController.php   # Phase 3 Stripe Webhook
│   │   └── Api/                         # Phase 3 REST API 控制器
│   │       ├── ApiController.php         # 基类（统一响应格式）
│   │       ├── AuthController.php       # login/register/logout/me
│   │       ├── CompanyApiController.php
│   │       ├── ContactApiController.php
│   │       ├── LeadApiController.php
│   │       ├── OpportunityApiController.php
│   │       ├── InboundEmailController.php  # 邮件转线索
│   │       └── Concerns/RestCrud.php     # 通用 CRUD trait
│   └── Middleware/
│       ├── BootstrapTenancy.php         # auth 后初始化 Tenancy（web + api 组末尾）
│       ├── EnsureUserHasTenant.php       # 检查用户是否绑定租户
│       └── EnsureUserIsPlatformAdmin.php # 平台超管守卫
├── Livewire/
│   ├── Dashboard.php                     # 仪表板（含待我处理区）
│   ├── Reports.php                       # 数据报表
│   ├── NotificationsCenter.php           # 通知中心
│   ├── GlobalSearch.php                  # Phase 5 全局跨实体搜索
│   ├── AuditLogs.php                     # 审计日志（含 diff 对比）
│   ├── Companies/  Index / Form / Show
│   ├── Contacts/   Index / Form / Show
│   ├── Leads/      Index / Form / Show / Convert（线索转换）
│   ├── Opportunities/ Index / Form / Show / Board
│   ├── Activities/ Index / Form
│   ├── Stages/     Index / Form（租户级销售阶段管理）
│   ├── Attachments/ AttachmentPanel.php   # Phase 2 附件面板
│   ├── Platform/   Tenants.php
│   ├── Settings/   TenantProfile.php / TeamMembers.php（含邀请流程）/ Subscription.php（Stripe 订阅）/ Webhooks.php（企业版 Webhook 管理）/ Automation.php（企业版工作流规则）
│   ├── AuditLogs.php                     # 审计日志
│   ├── Concerns/
│   │   ├── WithAdvancedFiltering.php     # Phase 2 高级筛选/排序 trait
│   │   └── WithDataPermissions.php       # Phase 2 数据权限 trait
│   └── Auth/       Login / Register / ForgotPassword / ResetPassword
│                   VerifyEmail.php        # Phase 1 邮箱验证
│                   AcceptInvitation.php   # Phase 1 邀请注册
├── Models/
│   ├── Tenant.php           # 租户模型（**不**挂 BelongsToTenant）
│   ├── User.php             # 用户模型（Phase 3 加 HasApiTokens）
│   ├── Attachment.php       # Phase 2 附件
│   ├── TenantInvitation.php # Phase 1 租户邀请
│   ├── TenantWebhook.php    # Phase 3 Webhook 配置
│   ├── Company / Contact / Lead / Opportunity / Stage / Activity
│   ├── Scopes/TenantScope.php          # 全局 Scope 实现
│   └── Traits/BelongsToTenant.php      # 业务模型通用 Trait
├── Notifications/
│   ├── Concerns/ChannelsFromConfig.php # Phase 1 邮件渠道配置
│   ├── ActivityDueNotification.php
│   ├── OpportunityCloseDateNotification.php
│   ├── OpportunityNoActivityNotification.php  # Phase 1 新增
│   ├── OpportunityStalledNotification.php
│   └── TenantInvitationNotification.php       # Phase 1 新增
├── Observers/
│   ├── WebhookObserver.php               # Phase 3 业务事件→Webhook 推送
│   └── WorkflowObserver.php              # Phase 4 模型事件→工作流引擎（旁路 try-catch）
├── NotificationChannels/
│   └── RobotWebhookChannel.php           # Phase 5 群机器人推送（钉钉/企业微信/Slack）
├── Console/Commands/
│   ├── SendRemindersCommand.php          # crm:send-reminders（五类提醒扫描）
│   ├── RunWorkflowsCommand.php           # crm:run-workflows（stalled 规则扫描）
│   └── RefreshOpportunityScores.php      # crm:refresh-scores（智能评分批量刷新）
├── Policies/                # RBAC 策略（每模型一个 Policy）
├── Providers/AppServiceProvider.php  # Tenancy 单例 + Webhook Observer 注册
└── Services/
    ├── Tenancy.php          # 租户上下文服务（必须 singleton！）
    ├── PlanService.php      # 套餐配额检查
    ├── CsvService.php       # CSV 导入导出
    ├── AuditService.php     # 审计日志旁路记录
    ├── ReminderService.php  # Phase 1 四类提醒扫描
    ├── ReportService.php    # Phase 2 加数据权限 scope
    ├── WebhookService.php   # Phase 3 HMAC-SHA256 签名推送
    ├── StripeSubscriptionService.php  # Phase 3 Stripe 订阅
    ├── OpportunityScoringService.php  # Phase 4 智能商机评分
    ├── FollowUpSuggestionService.php  # Phase 4 AI 跟进建议
    ├── WorkflowEngine.php   # Phase 4 自动化工作流引擎
    └── LLM/                 # Phase 4 LLM 适配器（LLMProvider 接口 + OpenAICompatible）

bootstrap/app.php             # 中间件链（web+api 组均追加 BootstrapTenancy）、路由注册
routes/
├── web.php                   # Web 路由 + Stripe Webhook + 邀请路由
├── api.php                   # Phase 3 REST API 路由
└── auth.php                  # 认证路由 + 邮箱验证路由
database/migrations/          # 所有迁移（含 Sanctum / attachments / tenant_invitations / tenant_webhooks / stripe_fields）
database/seeders/             # 演示数据填充（StageSeeder 已改为 per-tenant）
config/
├── crm.php                   # 提醒渠道/阈值 + 商机评分权重（Phase 1/4）
├── plans.php                 # 套餐配额
├── sanctum.php               # Phase 3 Sanctum 发布
├── llm.php                   # Phase 4 LLM 适配器配置
├── workflows.php             # Phase 4 工作流规则
└── stripe.php                # Phase 3 Stripe 密钥/价格/回调
```

## 架构一句话

**单库 + `tenant_id` 列 + 全局 `TenantScope` + `BelongsToTenant` Trait + `Tenancy` 单例服务。**

登录时中间件把当前用户注入 Tenancy，Tenancy 单例持有当前租户 ID，TenantScope 在每次查询自动附加 `WHERE tenant_id = ?`，业务模型通过 Trait 在创建时自动填 tenant_id。Seeder/Tinker 等 console 环境下 Tenancy::id() 为 null → Scope 逃生舱自动关闭 → 正常工作。

详细设计见 [docs/architecture.md](docs/architecture.md)。

## 更多文档

- [架构详解](docs/architecture.md)
- [数据库设计](docs/database.md)
- [本地环境搭建](docs/setup.md)
- [部署手册（生产上线/备份/监控）](docs/deployment.md)
- [套餐与配额体系](docs/plans.md)
- [通知体系（提醒/渠道/偏好）](docs/notifications.md)
- [全局搜索](docs/search.md)
- [自定义字段](docs/custom-fields.md) — Phase 6
- [租户数据导出与删除（GDPR）](docs/gdpr.md)
- [CSV 导入导出](docs/csv.md)
- [数据报表](docs/reports.md)
- [审计日志](docs/audit.md)
- [REST API 文档](docs/api.md) — Phase 3
- [Webhook 集成](docs/webhook.md) — Phase 3
- [Stripe 订阅计费](docs/stripe.md) — Phase 3
- [LLM 集成](docs/llm.md) — Phase 4
- [自动化工作流](docs/workflow.md) — Phase 4
- [商机智能评分](docs/scoring.md) — Phase 4
- [安全设计（2FA / Token / 安全要点）](docs/security.md) — Phase 6
- [界面多语言（i18n）](docs/i18n.md) — Phase 6
- [配置参考](docs/config.md) — 全阶段配置汇总

## License

MIT
