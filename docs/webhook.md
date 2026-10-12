# Webhook 集成文档（Phase 3）

租户管理员可配置 Webhook，当 CRM 内发生业务事件时（创建/更新/删除），系统自动 POST 到指定 URL。

---

## 一、安全机制

### HMAC-SHA256 签名

每次请求附带签名 Header，接收方必须验证后才处理。

| Header | 说明 |
|--------|------|
| `X-CRM-Event` | 事件名，如 `company.created` |
| `X-CRM-Tenant-Id` | 触发事件的租户 ID |
| `X-CRM-Timestamp` | 请求时间戳（秒级 Unix 时间） |
| `X-CRM-Signature` | HMAC-SHA256 签名 |
| `Content-Type` | `application/json` |

**签名算法**：
```
signature = HMAC-SHA256(secret, timestamp "." body)
```
- `secret`：租户创建 Webhook 时生成的密钥
- `timestamp`：`X-CRM-Timestamp` 的值
- `body`：HTTP 请求体原始 JSON 字符串

**接收方验证示例（PHP）**：
```php
$body = file_get_contents('php://input');
$timestamp = $_SERVER['HTTP_X_CRM_TIMESTAMP'];
$signature = $_SERVER['HTTP_X_CRM_SIGNATURE'];

$expected = hash_hmac('sha256', $timestamp . '.' . $body, $secret);
if (! hash_equals($expected, $signature)) {
    http_response_code(403);
    exit('Invalid signature');
}

// 可选：检查 timestamp 是否在 5 分钟内（防重放）
if (abs(time() - $timestamp) > 300) {
    http_response_code(403);
    exit('Request expired');
}
```

**接收方验证示例（Node.js）**：
```javascript
const crypto = require('crypto');

const signature = crypto
  .createHmac('sha256', secret)
  .update(req.headers['x-crm-timestamp'] + '.' + body)
  .digest('hex');

if (signature !== req.headers['x-crm-signature']) {
  res.status(403).send('Invalid signature');
}
```

---

## 二、事件列表

### Company（公司）
| 事件 | 触发时机 |
|------|----------|
| `company.created` | 新建公司 |
| `company.updated` | 修改公司（跳过纯时间戳更新） |
| `company.deleted` | 软删除公司 |
| `company.restored` | 恢复软删除的公司 |

### Contact（联系人）
| 事件 |
|------|
| `contact.created` |
| `contact.updated` |
| `contact.deleted` |
| `contact.restored` |

### Lead（线索）
| 事件 |
|------|
| `lead.created` |
| `lead.updated` |
| `lead.deleted` |
| `lead.restored` |

### Opportunity（商机）
| 事件 |
|------|
| `opportunity.created` |
| `opportunity.updated` |
| `opportunity.deleted` |
| `opportunity.restored` |

### Activity（活动）
| 事件 |
|------|
| `activity.created` |
| `activity.updated` |
| `activity.deleted` |
| `activity.restored` |

---

## 三、Payload 格式

```json
{
  "event": "opportunity.updated",
  "model": "opportunity",
  "action": "updated",
  "id": 42,
  "data": {
    "id": 42,
    "tenant_id": 3,
    "title": "Enterprise Licensing Deal",
    "value": 50000,
    "stage_id": 3,
    "owner_id": 5,
    "expected_close_date": "2026-12-15",
    "probability": 70,
    "created_at": "2026-10-10T08:00:00.000000Z",
    "updated_at": "2026-10-12T10:30:00.000000Z"
  },
  "timestamp": "2026-10-12T10:30:00.000000Z"
}
```

> `data` 字段是完整的模型 JSON（无 TenantScope 过滤后的原始数据，接收方可直接使用）。

---

## 四、Webhook 管理 API

Webhook 配置有两种管理方式：**租户 UI**（推荐）和 REST API（程序化管理）。

### 租户 UI（Settings → Webhook 管理）

路由 `/settings/webhooks`，企业版专属（`plan.feature:webhooks` 门控）。实现在 [app/Livewire/Settings/Webhooks.php](../app/Livewire/Settings/Webhooks.php)。

- admin / manager 可管理（创建、编辑、删除、启停、重置 Secret、发送测试事件），sales 只读
- 订阅事件以"模型 × 动作"矩阵复选框选择，事件名走白名单校验（仅允许 15 个合法事件 + 无任意注入）
- 重置 Secret 后新 Secret 仅一次性展示
- 列表实时显示推送状态（最近推送时间、失败计数）
- 与 API 共用 `TenantWebhook` 模型，TenantScope 保证跨租户隔离

### REST API

REST API 管理（Sanctum Token 认证，全租户共享，admin/manager 操作）：

| 端点 | 说明 |
|------|------|
| `GET /api/v1/webhooks` | 列表（支持 `search` / `sort_by` / `sort_dir` / `per_page`） |
| `POST /api/v1/webhooks` | 创建（`secret` 不传则自动生成 `whsec_` + 48 位随机串） |
| `GET /api/v1/webhooks/{id}` | 详情 |
| `PUT /api/v1/webhooks/{id}` | 更新（支持 `reset_secret=true` 重新生成 secret） |
| `DELETE /api/v1/webhooks/{id}` | 删除（硬删除） |
| `POST /api/v1/webhooks/{id}/test` | 发送测试事件（`webhook.test`）验证接收端连通性 |

### 创建请求体

```json
{
  "name": "My ERP Sync",
  "url": "https://erp.example.com/callback",
  "events": ["company.created", "opportunity.updated"],
  "is_active": true
}
```

响应（201）：
```json
{
  "status": "ok",
  "data": {
    "id": 1,
    "name": "My ERP Sync",
    "url": "https://erp.example.com/callback",
    "secret": "whsec_3fa9c2b8...",
    "events": ["company.created", "opportunity.updated"],
    "is_active": true
  }
}
```

> 🔒 `secret` 已脱敏：列表 / 详情 / 更新响应**不返回**明文密钥（模型 `$hidden`），仅**创建**（201）与 `reset_secret`（200）响应一次性返回，请妥善保存；遗失后可通过 `reset_secret` 轮换。

### 重新生成 Secret

```json
PUT /api/v1/webhooks/1
{ "reset_secret": true }
```

响应（200）：
```json
{
  "status": "ok",
  "data": {
    "id": 1,
    "secret": "whsec_新secret...",
    "message": "Secret 已重新生成，请及时更新接收方。"
  }
}
```

### 发送测试事件

```
POST /api/v1/webhooks/1/test
```

成功（200）：`{ "status": "ok", "data": { "status": "sent", "url": "...", "event": "webhook.test" } }`
失败（502）：`{ "status": "error", "message": "测试事件推送失败，请检查 URL、签名验证和网络连通性" }`

> 测试事件同样携带 HMAC-SHA256 签名 Header，可用于端到端验证接收方的签名校验逻辑。

### 数据库手动创建（备选）

```sql
INSERT INTO tenant_webhooks
  (tenant_id, name, url, secret, events, is_active)
VALUES
  (3, 'My ERP Sync', 'https://erp.example.com/callback',
   'whsec_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
   '["company.created","opportunity.updated"]', 1);
```

`secret` 建议用 64 位随机字符串：
```php
echo bin2hex(random_bytes(32));
```

---

## 五、可靠性

- **队列化推送（可选）**：设置 `CRM_WEBHOOK_QUEUE=true` 后，`WebhookService::dispatch()` 会将推送任务入队（`App\Jobs\SendWebhookJob`），由 queue worker 异步执行；默认 `false` 走同步推送。队列模式下 Job 按主键直查目标 Webhook（绕过 TenantScope，ID 在分发时已完成租户过滤），`is_active=false` 或已删除的目标静默跳过
- **同步推送（默认）**：`Http::post` 旁路执行，推送失败不阻塞主业务
- **失败计数**：`failure_count` 递增，`last_failed_at` 记录时间
- **重试边界**：HTTP 层失败（连接错误 / 4xx / 5xx）由 `send()` 内 try-catch 记账，**不触发队列重试**；队列 `tries=3`（backoff 30s/120s）仅兜底意外异常（如 DB 瞬断）。HTTP 失败需手动重推或监听 `last_failed_at` + `failure_count`
- **建议 200 OK**：接收方返回 2xx 状态码视为成功，4xx/5xx 视为失败

---

## 六、订阅过滤

`events` 字段是 JSON 数组，只有数组中的事件才会推送到此 Webhook：

```json
["company.created", "company.updated", "opportunity.created", "opportunity.updated"]
```

空数组 = 不订阅任何事件（推荐停用而非留空）。
