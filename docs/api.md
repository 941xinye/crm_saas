# REST API 文档（Phase 3）

多租户 CRM 提供 RESTful HTTP API，支持 Sanctum Token 认证，所有端点走租户隔离。

---

## 一、基础信息

| 项 | 值 |
|----|----|
| Base URL | `/api/v1` |
| 认证 | Bearer Token（Sanctum） |
| Content-Type | `application/json` |
| 响应格式 | 统一 `{status, data}` / `{status, message, errors}` |
| 限流 | 见下表 |

### 限流（throttle）

| 范围 | 限制 | 超限响应 |
|------|------|----------|
| `POST /login`、`POST /register` | 10 次/分钟/IP | `429 Too Many Attempts`（响应含 `Retry-After` 头） |
| 认证后的全部业务端点 | 60 次/分钟/用户 | 同上 |
| `POST /inbound-email/{slug}` | 60 次/分钟/用户 | 同上 |

限流按 Laravel 标准 `RateLimiter` 计数（认证后按用户 ID，认证前按 IP）。客户端应读取 `Retry-After` 响应头等待后重试；高频集成场景建议批量合并请求而非循环单发。

### 路由命名（api. 前缀）

`routes/api.php` 中所有 API 路由名统一带 `api.` 前缀，与 Web 路由名隔离：

| 路由名 | URL |
|--------|-----|
| `api.login` / `api.register` | `POST /api/v1/login`、`POST /api/v1/register` |
| `api.logout` / `api.user` | `POST /api/v1/logout`、`GET /api/v1/user` |
| `api.companies.*` 等 5 组资源 | `apiResource` 默认五动作（index/store/show/update/destroy） |
| `api.attachments.index/show/download` | 附件查询与下载 |
| `api.webhooks.*` / `api.webhooks.test` | Webhook CRUD 与测试事件 |
| `api.inbound-email` | `POST /api/v1/inbound-email/{tenantSlug}` |

> **约定**：新增 API 路由必须放在 `name('api.')` 分组内并显式命名。历史教训：apiResource 默认生成 `companies.index` 等名称，与 Web 路由同名时后注册的 API 路由会覆盖 Laravel 路由名称映射，导致页面 `route('companies.index')` 错误生成 `/api/v1/...` 链接（点击返回 `{"message":"Unauthenticated."}`）。`RouteNamingTest` 会校验全应用路由名唯一。

---

## 二、认证

### 2.1 登录（游客）

```
POST /api/v1/login
```

请求体：
```json
{
  "email": "admin@acme.test",
  "password": "password",
  "device_name": "my-phone"   // 可选
}
```

成功响应（200）：
```json
{
  "status": "ok",
  "data": {
    "token": "1|abc123...",
    "token_type": "Bearer",
    "user": {
      "id": 5,
      "name": "Admin",
      "email": "admin@acme.test",
      "role": "admin",
      "tenant_id": 3
    }
  }
}
```

错误响应（422）：
```json
{
  "status": "error",
  "message": "邮箱或密码不正确",
  "errors": []
}
```

### 2.2 注册（游客）

```
POST /api/v1/register
```

请求体：
```json
{
  "tenant_name": "Acme Corp",
  "tenant_slug": "acme",
  "name": "John Doe",
  "email": "john@acme.com",
  "password": "password123",
  "password_confirmation": "password123"
}
```

成功响应（201）：同登录响应。注册时自动创建租户 + 管理员用户，tenant 状态为 `free` 套餐 + 14 天试用期，用户 `email_verified_at` 自动设置。

### 2.3 注销（需 Token）

```
POST /api/v1/logout
Authorization: Bearer <token>
```

成功响应（200）：
```json
{ "status": "ok", "data": { "message": "已注销" } }
```

注销后当前 Token 从 `personal_access_tokens` 表删除，后续请求返回 401。

### 2.4 Token 权限范围（Scopes）

所有业务端点挂 `token.scope` 中间件（[EnsureTokenScope.php](../app/Http/Middleware/EnsureTokenScope.php)），按 HTTP 方法检查 Token ability：

| 请求方法 | 所需 ability |
|----------|--------------|
| `GET` / `HEAD` | `read` |
| `POST` / `PUT` / `PATCH` / `DELETE` | `write` |

- 缺少对应 scope 返回 `403`：`{"message": "API Token 缺少 read scope"}`
- 仅对 Token 认证生效；`/login` `/register` 签发的 Token 固定含 `read + write`（向后兼容）
- 只读集成（报表/BI）建议在 Web 端 `/settings/tokens` 创建仅勾选 `read` 的 Token，泄露时无法篡改数据

示例：

```bash
# 只读 Token 调用列表 → 正常
curl -H "Authorization: Bearer <read-only-token>" https://example.com/api/v1/companies

# 只读 Token 创建 → 403
curl -X POST -H "Authorization: Bearer <read-only-token>" \
  -H "Content-Type: application/json" \
  -d '{"name":"新公司"}' https://example.com/api/v1/companies
# {"message": "API Token 缺少 write scope"}
```

### 2.5 当前用户（需 Token）

```
GET /api/v1/user
Authorization: Bearer <token>
```

成功响应（200）：
```json
{
  "status": "ok",
  "data": {
    "id": 5,
    "name": "Admin",
    "email": "admin@acme.test",
    "role": "admin",
    "tenant": {
      "id": 3,
      "name": "Acme Corporation",
      "plan": "pro"
    }
  }
}
```

---

## 三、业务资源

### 3.1 通用响应格式

**成功列表**：
```json
{
  "status": "ok",
  "data": [/* 资源数组 */],
  "meta": {
    "total": 42,
    "per_page": 15,
    "current_page": 1,
    "last_page": 3
  }
}
```

**成功详情/创建/更新**：
```json
{ "status": "ok", "data": { /* 资源 */ } }
```

**成功删除**（204）：
```json
{ "status": "ok", "data": null }
```

**错误**：
```json
{
  "status": "error",
  "message": "无权操作此资源",
  "errors": []
}
```

### 3.2 支持的查询参数

| 参数 | 说明 | 示例 |
|------|------|------|
| `search` | 搜索（模型内定义搜索字段） | `?search=acme` |
| `sort_by` | 排序字段（白名单，防注入） | `?sort_by=value` |
| `sort_dir` | 排序方向 | `?sort_dir=asc` / `desc` |
| `per_page` | 每页数量（默认 15） | `?per_page=50` |
| `cf_key` + `cf_value` | 自定义字段值筛选（Companies / Contacts / Opportunities，key 须为租户已定义字段） | `?cf_key=customer_level&cf_value=A` |

### 自定义字段（Companies / Contacts / Opportunities）

三实体支持 `custom_fields` JSON 对象读写，key 必须为租户已定义字段（见 [custom-fields.md](custom-fields.md)），
未定义的 key 静默丢弃；`select` 类型值不在选项内返回 422。

```bash
POST /api/v1/companies {"name": "云图科技", "custom_fields": {"customer_level": "A"}}
GET  /api/v1/companies?cf_key=customer_level&cf_value=A   # 按字段值筛选
```

### 3.3 Companies

```
GET    /api/v1/companies          列表
POST   /api/v1/companies          创建
GET    /api/v1/companies/{id}     详情
PUT    /api/v1/companies/{id}     更新
DELETE /api/v1/companies/{id}     删除（软删除）
```

搜索字段：`name`, `industry`
可排序：`name`, `created_at`

创建请求体：
```json
{
  "name": "Acme Corp",
  "industry": "Technology",
  "website": "https://acme.com",
  "phone": "+1-555-0123",
  "address": "123 Main St",
  "owner_id": 5,
  "custom_fields": {"customer_level": "A"}
}
```

> **owner_id 必须属于当前租户**，否则返回 422 验证错误。

### 3.4 Contacts

```
GET    /api/v1/contacts
POST   /api/v1/contacts
GET    /api/v1/contacts/{id}
PUT    /api/v1/contacts/{id}
DELETE /api/v1/contacts/{id}
```

搜索字段：`first_name`, `last_name`, `email`
可排序：`first_name`, `last_name`, `email`, `created_at`

### 3.5 Leads

```
GET    /api/v1/leads
POST   /api/v1/leads
GET    /api/v1/leads/{id}
PUT    /api/v1/leads/{id}
DELETE /api/v1/leads/{id}
```

搜索字段：`name`, `email`, `company_name`
可排序：`name`, `status`, `created_at`
status 可选值：`new`, `contacted`, `qualified`, `converted`, `lost`

### 3.6 Opportunities

```
GET    /api/v1/opportunities
POST   /api/v1/opportunities
GET    /api/v1/opportunities/{id}
PUT    /api/v1/opportunities/{id}
DELETE /api/v1/opportunities/{id}
```

搜索字段：`title`
可排序：`title`, `value`, `expected_close_date`, `created_at`

创建请求体：
```json
{
  "title": "Enterprise Licensing Deal",
  "value": 50000.00,
  "expected_close_date": "2026-12-15",
  "probability": 70,
  "company_id": 12,
  "contact_id": 34,
  "stage_id": 3,
  "owner_id": 5
}
```

> **stage_id / company_id / contact_id / owner_id 都必须属于当前租户**。

### 3.7 Activities

```
GET    /api/v1/activities
POST   /api/v1/activities
GET    /api/v1/activities/{id}
PUT    /api/v1/activities/{id}
DELETE /api/v1/activities/{id}
```

搜索字段：`subject`, `notes`
可排序：`subject`, `type`, `scheduled_at`, `created_at`
数据权限：sales 仅可操作 `user_id = 自己` 的活动

创建请求体：
```json
{
  "type": "call",
  "subject": "电话回访",
  "notes": "客户有意向",
  "scheduled_at": "2026-10-20 14:00:00",
  "user_id": 5,
  "activitable_type": "company",
  "activitable_id": 12
}
```

- `activitable_type` / `activitable_id` 必填且成对，类型仅允许 `company` / `contact` / `opportunity`（白名单），存储时自动归一化为完整类名
- **关联对象必须属于当前租户**，否则 422
- `user_id` 未传时默认当前登录用户

### 3.8 Attachments

```
GET    /api/v1/attachments                      列表（可过滤）
POST   /api/v1/attachments                      上传（multipart/form-data）
GET    /api/v1/attachments/{id}                 详情
GET    /api/v1/attachments/{id}/download        下载（流式返回私有磁盘文件）
```

列表过滤参数：`attachable_type`（`company` / `contact` / `opportunity`）、`attachable_id`；`per_page` 上限 100。

上传请求体（`multipart/form-data`）：

| 字段 | 说明 |
|------|------|
| `file` | 文件本体；10MB 限制，类型白名单 pdf/jpg/jpeg/png/gif/webp/doc/docx/xls/xlsx/csv/txt/zip |
| `attachable_type` | `company` / `contact` / `opportunity`（白名单） |
| `attachable_id` | 归属对象 ID，必须属于当前租户（跨租户 404） |

成功响应（201）同详情格式。下载需认证 + TenantScope 租户归属校验，跨租户返回 404。

### 3.9 Stages（只读）

```
GET /api/v1/stages
```

商机创建必填 `stage_id`，集成方由此端点发现当前租户的销售阶段。

查询参数：`search`（阶段名）、`sort_by`（`name` / `order` / `created_at`，默认 `order` 升序）、`sort_dir`、`per_page`（上限 100）。

### 3.10 Users（只读，租户成员）

```
GET /api/v1/users
```

供集成方分配 `owner_id` 时发现当前租户成员。

查询参数：`search`（姓名）、`sort_by`（`name` / `role` / `created_at`）、`sort_dir`、`per_page`（上限 100）。

> 出于隐私考虑，响应**仅包含** `id` / `name` / `role` / `created_at`，不返回邮箱等敏感字段。

---

## 四、权限模型（API 与 Web 共享）

| 角色 | 列表 | 详情 | 创建 | 更新 | 删除 |
|------|------|------|------|------|------|
| admin | ✅ 全租户 | ✅ 全租户 | ✅ | ✅ 任意 | ✅ |
| manager | ✅ 全租户 | ✅ 全租户 | ✅ | ✅ 任意 | ❌ |
| sales | ✅ 仅自己负责 | ✅ 仅自己负责 | ✅ | ✅ 仅自己 | ❌ |
| 平台超管 | N/A（不走 API） | N/A | N/A | N/A | N/A |

> sales 的数据权限在 API 层通过 `WithDataPermissions` trait 的 `applyDataScope()` 方法实现，与 Web Livewire 完全一致。

### API 特定的跨租户隔离

Sanctum Token 绑定的 User 有 `tenant_id`，`BootstrapTenancy` 中间件在 api 组末尾初始化 Tenancy，TenantScope 自动过滤。跨租户访问返回 404。

```
User A (tenant_id=1) 用 token 请求 GET /api/v1/companies/{tenant2_company_id}
→ TenantScope 自动附加 WHERE tenant_id=1
→ 查不到 → 404
```

---

## 五、邮件转线索

```
POST /api/v1/inbound-email/{tenantSlug}
Authorization: Bearer <token>
```

接收 Mailgun / SendGrid / Postmark 的 Inbound Parse JSON，自动创建 Lead。

请求体（Mailgun 风格）：
```json
{
  "sender": "John Doe <john@example.com>",
  "subject": "Interested in your product",
  "body-plain": "Hi, our company Acme would like to schedule a demo..."
}
```

成功响应（201）：
```json
{
  "status": "ok",
  "data": {
    "lead_id": 42,
    "message": "已从邮件创建线索「John Doe」"
  }
}
```

创建的 Lead：
- `source`: `inbound_email`
- `status`: `new`
- `company_name`: 从 subject/body 中自动提取

---

## 六、错误码速查

| HTTP | 场景 |
|------|------|
| 200 | 成功（列表/详情/更新） |
| 201 | 成功创建 |
| 204 | 成功删除 |
| 401 | 未认证 / Token 无效或已撤销 |
| 403 | 无权限（sales 看他人数据、未验证邮箱、无租户） |
| 404 | 资源不存在（TenantScope 过滤后的跨租户访问也返回 404） |
| 422 | 验证失败（`errors` 字段含具体字段错误） |
| 429 | 频率限制（默认 60 次/分钟） |

---

## 七、cURL 示例

```bash
# 登录获取 token
curl -X POST /api/v1/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@acme.test","password":"password"}'

# 带 token 访问
curl /api/v1/companies \
  -H "Authorization: Bearer 1|abc123..."

# 搜索 + 排序 + 分页
curl "/api/v1/opportunities?search=enterprise&sort_by=value&sort_dir=desc&per_page=20" \
  -H "Authorization: Bearer 1|abc123..."
```
