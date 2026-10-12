# 数据库设计

## ER 关系（文字描述）

```
Tenant 1 ──────── N User
  │                  │
  │                  │ (company_owner_id / lead_owner_id / ...)
  │                  │
  │                  ▼
  │              User 是多个业务记录的负责人
  │
  ▼
Tenant 1 ──────── N Company
                    │
                    │ 1
                    │
                    ▼
               Contact   (Company 1:N Contact)

Tenant 1 ──────── N Lead
  （独立线索，可关联 Company/Contact，也可不关联）

Tenant 1 ──────── N Opportunity ──────── N Stage
                        │
                        │ N:1
                        ├── Company（可选）
                        └── Contact（可选）

Activity (多态 morphs)
  ├── activitable_type = Company, activitable_id = X
  ├── activitable_type = Contact, activitable_id = Y
  ├── activitable_type = Lead, activitable_id = Z
  └── activitable_type = Opportunity, activitable_id = W
  └── 同时 belongsTo User（谁做的跟进）
```

```
Attachment (多态 morphs，Phase 2)
  ├── attachable_type = Company / Contact / Opportunity
  └── belongsTo User（uploaded_by，谁上传的）

Tenant 1 ── N TenantInvitation   团队邀请（token + expires_at，接受后创建 User，Phase 1）
Tenant 1 ── N TenantWebhook      Webhook 配置（secret + events + 推送可观测字段，Phase 3）
Tenant 1 ── N TenantWorkflow     租户自定义自动化规则（与 config/workflows.php 双层共存，Phase 5）
Tenant 1 ── N AuditLog           审计日志（tenant_id 从被操作对象取，batch_id 聚合批量操作）
```

---

## 关键表与索引

### `tenants`（租户表）

| 列 | 类型 | 说明 |
|----|------|------|
| id | bigint PK | |
| name | varchar(255) | 租户名称 |
| slug | varchar(100) | 子域名预留（暂未启用） |
| plan | enum('free','pro','enterprise') | 套餐 |
| settings | json nullable | 语言/货币等租户设置 |
| custom_fields | json nullable | 自定义字段定义（按实体分组，Phase 6，见 docs/custom-fields.md） |
| trial_ends_at | timestamp nullable | 试用截止 |
| is_active | boolean | 是否启用 |
| created_at / updated_at | timestamps | |

**重要：`tenants` 不挂 `BelongsToTenant` Trait**，它本身是隔离的根。

### `users`（用户表）

Laravel 默认表 + tenant 扩展：

| 列 | 类型 | 说明 |
|----|------|------|
| id | bigint PK | |
| name | varchar(255) | |
| email | varchar(255) | unique 全局唯一 |
| password | varchar(255) | |
| tenant_id | bigint nullable | 平台超管可 null |
| role | enum('admin','manager','sales') | 租户内角色 |
| is_platform_admin | boolean | 平台超管标记 |
| notification_prefs | json nullable | 用户级通知偏好（键→布尔，null 默认全收，Phase 6） |
| locale | varchar(10) nullable | 界面语言偏好（zh_CN / en，null 跟随 APP_LOCALE，Phase 6） |
| two_factor_secret | text nullable | 2FA Base32 密钥（Phase 6） |
| two_factor_recovery_codes | text nullable | 2FA 恢复码 bcrypt 哈希 JSON 数组（Phase 6） |
| two_factor_confirmed_at | timestamp nullable | 2FA 首次动态码确认时间（Phase 6） |
| ... | | 其他 Laravel 默认列 |

- 索引：`users(tenant_id)` 普通索引
- **用户表也不挂 BelongsToTenant**（否则超管登录会查不到用户），但业务查询中通过 `tenant_id` 关联。

### 所有业务表均含 `tenant_id`

`stages`, `companies`, `contacts`, `leads`, `opportunities`, `activities`, `attachments`, `tenant_webhooks`, `tenant_workflows` 每张表：

```sql
-- 公共结构
tenant_id bigint unsigned NOT NULL
  REFERENCES tenants(id) ON DELETE CASCADE,
INDEX (tenant_id)
```

### `contacts` 复合唯一

```sql
-- 同一租户内邮箱唯一，不同租户可重
UNIQUE KEY (tenant_id, email)
```

### `opportunities.stage_id` 外键

```sql
stage_id bigint unsigned NOT NULL
  REFERENCES stages(id) ON DELETE RESTRICT  -- 不允许删被引用的阶段
```

### `stages`（阶段表）

挂 `BelongsToTenant` — 按租户隔离，每个租户维护自己的销售阶段。

| 列 | 类型 | 说明 |
|----|------|------|
| id | bigint PK | |
| tenant_id | bigint | 租户隔离（TenantScope） |
| name | varchar(100) | 如「初步接触」「需求确认」 |
| order | unsigned int | 排序（越小越靠前，注意列名是 `order` 不是 `sort_order`） |
| probability | unsigned tinyint | 默认赢单概率 0-100 |
| is_default | boolean | 新商机默认阶段 |
| is_won | boolean | 赢单阶段标记（报表赢率判定用，Phase 2） |
| is_lost | boolean | 输单阶段标记 |
| created_at / updated_at | | |

### `activities`（跟进活动，多态）

| 列 | 类型 | 说明 |
|----|------|------|
| id | bigint PK | |
| tenant_id | bigint | 业务表，挂 BelongsToTenant |
| activitable_type | varchar | morphs type |
| activitable_id | bigint | morphs id |
| user_id | bigint | 谁做的跟进（CASCADE 删除） |
| type | enum('call','email','meeting','note','task') | 活动类型 |
| subject | varchar(255) | 活动主题 |
| scheduled_at | timestamp nullable | 计划时间 |
| completed_at | timestamp nullable | 完成时间 |
| notes | text nullable | 跟进备注 |
| deleted_at | timestamp nullable | 软删除（Phase 1 加入，可恢复） |
| created_at / updated_at | | |

索引：
```sql
INDEX (tenant_id, user_id),
INDEX (tenant_id),
INDEX (activitable_type, activitable_id)
```

### `audit_logs`（审计日志，多态 + 批量聚合）

| 列 | 类型 | 说明 |
|----|------|------|
| id | bigint PK | |
| tenant_id | bigint unsigned | **从被审计对象取**，不是当前用户 |
| user_id | bigint nullable | 操作人 |
| action | varchar | `created / updated / deleted / restored` 或 `batch_{action}` |
| auditable_type | varchar | morphs type |
| auditable_id | bigint | morphs id；**批量汇总日志**固定为 `0` |
| batch_id | uuid nullable | 同一批量操作的关联 ID（uuid），普通单模型日志为 null |
| before | json nullable | 修改前快照（仅 `created` 时为 null） |
| after | json nullable | 修改后快照（仅 `deleted` 时为 null；批量汇总时存 count/status JSON） |
| ip | varchar(45) nullable | IPv4 / IPv6 |
| user_agent | varchar(500) nullable | |
| route | varchar nullable | 路由名（便于按功能过滤） |
| created_at / updated_at | | |

> **重要**：`AuditLog` 模型不挂 `Auditable` Trait，避免写入审计日志时再触发一条审计日志形成无限递归。

索引：
```sql
INDEX (tenant_id, auditable_type, auditable_id),
INDEX (tenant_id, action),
INDEX (batch_id),
INDEX (created_at)
```

批量聚合的约定：
- 所有同一次批量操作产生的日志（N 条单模型 + 1 条汇总）共享**同一个 `batch_id`**
- 汇总日志 `action` 为 `batch_deleted` / `batch_updated` / `batch_created`
- 汇总日志 `auditable_id = 0`，`after` 存 `{count, status, error?}` JSON

### `tenant_invitations`（团队邀请，Phase 1）

| 列 | 类型 | 说明 |
|----|------|------|
| id | bigint PK | |
| tenant_id | bigint | 所属租户（CASCADE） |
| email | varchar | 被邀请人邮箱 |
| role | varchar | 加入后的角色（admin / manager / sales） |
| token | varchar(64) unique | 邀请令牌（`/invitation/accept/{token}`） |
| expires_at | timestamp | 过期时间 |
| accepted_at | timestamp nullable | 接受时间 |
| accepted_by | bigint nullable | 接受人（users，SET NULL） |
| created_at / updated_at | | |

索引：`INDEX (tenant_id, accepted_at)`、`INDEX (email)`

### `attachments`（附件，Phase 2，多态）

| 列 | 类型 | 说明 |
|----|------|------|
| id | bigint PK | |
| tenant_id | bigint | 租户隔离 |
| attachable_type / attachable_id | morphs | 归属对象（Company / Contact / Opportunity） |
| original_name | varchar | 原始文件名 |
| file_path | varchar | 私有磁盘存储路径（`attachments/{tenant_id}/{resource_id}/`） |
| mime_type | varchar(127) nullable | MIME 类型 |
| size | bigint | 字节数（上传限制 10MB） |
| uploaded_by | bigint | 上传人（users，CASCADE） |
| created_at / updated_at | | |

> `Attachment` 模型挂 `BelongsToTenant + Auditable`，**无 SoftDeletes**（下载走认证端点 + TenantScope 校验）。

### `tenant_webhooks`（Webhook 配置，Phase 3）

| 列 | 类型 | 说明 |
|----|------|------|
| id | bigint PK | |
| tenant_id | bigint | 租户隔离 |
| name | varchar | 名称 |
| url | varchar(500) | 回调 URL |
| secret | varchar(64) | HMAC-SHA256 签名密钥（API 响应已脱敏） |
| events | json | 订阅事件数组 |
| is_active | boolean | 启停 |
| last_sent_at | timestamp nullable | 最近成功推送 |
| last_failed_at | timestamp nullable | 最近失败推送 |
| last_response | text nullable | 最近响应摘要 |
| failure_count | unsigned int | 连续失败计数 |
| created_at / updated_at | | |

索引：`INDEX (tenant_id, is_active)`

### `tenant_workflows`（租户自动化规则，Phase 5）

| 列 | 类型 | 说明 |
|----|------|------|
| id | bigint PK | |
| tenant_id | bigint | 租户隔离 |
| name | varchar | 规则名 |
| model | varchar(191) | 目标模型 FQCN（当前仅 Opportunity） |
| trigger | varchar(32) | created / updated / entered_stage / stalled |
| conditions | json nullable | `[[field, operator, expected], ...]` 条件列表 |
| condition_match | varchar(8) default 'all' | 条件匹配模式：all（AND）/ any（OR），Phase 6 |
| actions | json | `[{type, params}, ...]` 顺序执行 |
| is_active | boolean | 启停 |
| priority | unsigned int | 越大越先执行（DB 规则整体优先于 config 规则） |
| last_triggered_at | timestamp nullable | 最近命中时间 |
| created_at / updated_at | | |

索引：`INDEX (tenant_id, is_active)`

### 自定义字段（Phase 6）

定义存租户、值存业务表（见 [custom-fields.md](custom-fields.md)）：

```sql
-- tenants.custom_fields：字段定义，按实体分组
{"company":   [{"key","label","type","required","options"}],
 "contact":   [...],
 "opportunity": [...]}

-- companies / contacts / opportunities.custom_fields：字段值 {"key": value}
custom_fields json nullable
```

- 定义随租户天然隔离（tenants 表无 TenantScope，业务表值随行隔离）
- 值仅保留已定义 key（`CustomFieldService::sanitizeValues` 入库前过滤）
- 筛选走 `where("custom_fields->{key}", value)`（key 白名单校验防 JSON path 注入）

---

## 迁移顺序（按时间戳）

所有迁移在 `database/migrations/`：

| 时间戳 | 文件 | 内容 |
|--------|------|------|
| 0001_01_01_000000 | create_users_table.php | Laravel 默认 users 表 |
| 0001_01_01_000001 | create_cache_table.php | Laravel 默认 |
| 0001_01_01_000002 | create_jobs_table.php | Laravel 默认 |
| **2026_10_12_000001** | **create_tenants_table.php** | **租户表（最先，业务表依赖）** |
| **2026_10_12_000002** | **add_tenancy_to_users_table.php** | **users 表加 tenant_id / role / is_platform_admin** |
| 2026_10_12_000003 | create_stages_table.php | 销售阶段 |
| 2026_10_12_000004 | create_companies_table.php | 公司 |
| 2026_10_12_000005 | create_contacts_table.php | 联系人 |
| 2026_10_12_000006 | create_leads_table.php | 线索 |
| 2026_10_12_000007 | create_opportunities_table.php | 商机 |
| **2026_10_12_000008** | create_activities_table.php | 跟进活动（多态） |
| **2026_10_12_000008** | create_notifications_table.php | 系统通知 |
| **2026_10_12_000009** | create_audit_logs_table.php | 审计日志（含 morphs） |
| **2026_10_12_000010** | add_batch_id_to_audit_logs_table.php | 审计日志加 `batch_id` (uuid) 列，批量操作聚合用 |
| 2026_10_12_000010 | add_won_lost_to_stages_table.php | 阶段表加 `is_won` / `is_lost` 标记 |
| 2026_10_12_000011 | add_soft_deletes_to_activities_table.php | 活动表软删除 |
| 2026_10_12_000012 | create_tenant_invitations_table.php | 团队邀请（Phase 1） |
| 2026_10_12_000013 | create_attachments_table.php | 附件管理（Phase 2，多态） |
| 2026_10_12_000014 | create_tenant_webhooks_table.php | Webhook 配置（Phase 3） |
| 2026_10_12_000015 | add_stripe_fields_to_tenants_table.php | Stripe 订阅字段（Phase 3） |
| 2026_10_12_000016 | add_score_to_opportunities_table.php | 商机评分字段 `score` / `scored_at`（Phase 4） |
| **2026_10_12_000017** | **create_tenant_workflows_table.php** | **租户自动化工作流规则（Phase 5）** |
| 2026_10_12_075103 | create_personal_access_tokens_table.php | Sanctum Token 表（Phase 3） |
| 2026_10_13_000001 | add_notification_prefs_to_users_table.php | 用户级通知偏好 `notification_prefs`（Phase 6） |
| 2026_10_13_000002 | add_two_factor_to_users_table.php | 2FA 字段：secret / 恢复码哈希 / 确认时间（Phase 6） |
| 2026_10_13_000003 | add_custom_fields.php | 自定义字段：tenants 定义 + 三业务表值列（Phase 6） |
| 2026_10_13_000004 | add_condition_match_to_tenant_workflows_table.php | 工作流条件匹配模式 all/any（Phase 6） |
| 2026_10_13_000005 | add_locale_to_users_table.php | 用户界面语言偏好 `locale`（Phase 6） |

> **注意**：2026_10_12_000002 是修改 users 表，不是新建表。顺序必须是 tenants → users 加 tenant_id → 业务表 → activities → notifications → audit_logs。

---

## 级联策略

| 关系 | 策略 | 理由 |
|------|------|------|
| Tenant → 所有业务表 | `ON DELETE CASCADE` | 删租户即清理全部数据，演示/测试方便 |
| Company → Contact | `ON DELETE CASCADE` | 删公司 → 联系人随之删除（联系人归属公司） |
| Stage → Opportunity | `ON DELETE RESTRICT` | 不允许删被商机引用的阶段，保护数据完整性 |
| User → Company（owner） | `NULL ON DELETE` | 负责人删除后 owner_id 置 null，公司保留 |
| User → Lead（owner） | `NULL ON DELETE` | 同上 |
| User → Opportunity（owner） | `NULL ON DELETE` | 同上 |
| User → Activity | `CASCADE` | 跟进记录跟随用户删除 |

如果未来要改生产级级联策略（比如租户删除改为软删除或归档），只需改 migration 中的 ON DELETE，配合 `Tenant::withTrashed()` 即可。
