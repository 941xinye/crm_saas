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

---

## 关键表与索引

### `tenants`（租户表）

| 列 | 类型 | 说明 |
|----|------|------|
| id | bigint PK | |
| name | varchar(255) | 租户名称 |
| slug | varchar(100) | 子域名预留（暂未启用） |
| plan | enum('free','pro','enterprise') | 套餐 |
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
| ... | | 其他 Laravel 默认列 |

- 索引：`users(tenant_id)` 普通索引
- **用户表也不挂 BelongsToTenant**（否则超管登录会查不到用户），但业务查询中通过 `tenant_id` 关联。

### 所有业务表均含 `tenant_id`

`companies`, `contacts`, `leads`, `opportunities`, `activities` 每张表：

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

不挂 BelongsToTenant — 跨租户共用一套阶段（或加 tenant_id 后隔离，当前设计为全局配置）。

| 列 | 类型 | 说明 |
|----|------|------|
| id | bigint PK | |
| name | varchar(100) | 如「初步接触」「需求确认」 |
| sort_order | int | 排序（越小越靠前） |
| created_at / updated_at | | |

### `activities`（跟进活动，多态）

| 列 | 类型 | 说明 |
|----|------|------|
| id | bigint PK | |
| tenant_id | bigint | 业务表，挂 BelongsToTenant |
| activitable_type | varchar | morphs type |
| activitable_id | bigint | morphs id |
| user_id | bigint | 谁做的跟进 |
| type | enum('call','email','meeting','note','task') | 活动类型 |
| content | text | 跟进内容 |
| created_at / updated_at | | |

索引：
```sql
INDEX (tenant_id),
INDEX (activitable_type, activitable_id),
INDEX (user_id)
```

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
| 2026_10_12_000008 | create_activities_table.php | 跟进活动（最后） |

> **注意**：2026_10_12_000002 是修改 users 表，不是新建表。顺序必须是 tenants → users 加 tenant_id → 业务表。

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
