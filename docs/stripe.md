# Stripe 订阅计费文档（Phase 3）

CRM 内置 Stripe 集成，支持 Checkout Session 创建 + Webhook 事件驱动的租户状态同步。

---

## 一、环境变量

在 `.env` 中配置（生产必须）：

```ini
# Stripe 密钥
STRIPE_SECRET_KEY=sk_test_xxxxxxxxxxxxx
STRIPE_PUBLIC_KEY=pk_test_xxxxxxxxxxxxx
STRIPE_WEBHOOK_SECRET=whsec_xxxxxxxxxxxxx

# Stripe 价格 ID（对应 config/stripe.php 的 prices 映射）
STRIPE_PRICE_PRO=price_xxxxxxxxxxxxx
STRIPE_PRICE_ENTERPRISE=price_yyyyyyyyyyy

# 回调 URL（默认 APP_URL + /settings/billing?checkout=success/canceled）
# STRIPE_SUCCESS_URL=...
# STRIPE_CANCEL_URL=...
```

> **本地开发**：未配置密钥时 Stripe 服务自动降级（所有方法返回 null），不崩溃。

---

## 二、Checkout Session 流程

```
┌────────────┐     ┌──────────────┐     ┌──────────────┐     ┌──────────────┐
│  CRM 前端   │     │ StripeSession│     │  Stripe API  │     │  Stripe 结账页 │
│ Settings   │────▶│   Service    │────▶│              │────▶│  (用户付款)   │
│ Billing    │     │              │     │              │     └──────┬───────┘
└────────────┘     └──────────────┘     └──────────────┘            │
     │                                                                ▼
     │                         ┌──────────────────────────────────────────┐
     │   3. 用户回到 CRM 成功页 │ 4. Stripe Webhook → /webhooks/stripe    │
     │                         │  5. StripeSubscriptionService 处理事件   │
     └── ◀── 1. 返回 session.url ──────────────────────────────────────┘
            2. 前端跳转 Stripe 结账页                    │
                                                        ▼
                                          ┌──────────────────────┐
                                          │ 更新 tenants 表：      │
                                          │ plan=pro/free        │
                                          │ stripe_customer_id    │
                                          │ stripe_subscription_id │
                                          │ stripe_status         │
                                          └──────────────────────┘
```

### 自助升级入口（租户 UI）

`/settings/subscription` 套餐页提供自助升级（[app/Livewire/Settings/Subscription.php](../app/Livewire/Settings/Subscription.php)）：

- **仅 admin 角色**可发起升级；self-service 只能**升级**（free → pro / enterprise，pro → enterprise）
- 点击升级按钮 → `upgrade($plan)` → `StripeSubscriptionService::createCheckoutSession()` → 302 跳转 Stripe Checkout
- **Stripe 未配置时**（本地/未上线）返回 null，页面提示"在线支付暂未开通，请联系平台管理员手动变更套餐"，不阻塞页面（graceful degradation）
- **降级/取消/账单管理**：付费套餐 admin 可点击「管理订阅与账单」按钮 → `portal()` → `createPortalSession()` 跳转 **Stripe Customer Portal** 自助完成（未配置 Stripe 时降级提示）
- 已有 Stripe 订阅的租户在套餐页显示当前 `stripe_status`；状态为 `past_due` / `unpaid` 时页面顶部展示琥珀色警告横幅，提醒尽快更新支付方式
- Webhook 回调同步成功后，新套餐的配额/功能门控立即生效（PlanService 实时读 `tenants.plan`）

### 创建 Checkout Session（伪代码）

```php
use App\Services\StripeSubscriptionService;

$stripe = app(StripeSubscriptionService::class);
$url = $stripe->createCheckoutSession($tenant, 'pro');

if ($url) {
    // 前端 window.location.href = $url;
}
```

---

## 三、Webhook 事件处理

**端点**：`POST /webhooks/stripe`（CSRF 已豁免，Stripe 签名验证）

**需要在 Stripe Dashboard → Webhooks 中配置**：

1. 访问 [Stripe Webhooks](https://dashboard.stripe.com/webhooks)
2. Add endpoint
3. Endpoint URL：`https://your-crm-domain.com/webhooks/stripe`
4. Events：勾选 `customer.subscription.created` / `customer.subscription.updated` / `customer.subscription.deleted`
5. 复制 Signing Secret → 填入 `.env` 的 `STRIPE_WEBHOOK_SECRET`

### 事件 → 租户状态映射

| Stripe 事件 | 租户字段更新 |
|------------|-------------|
| `customer.subscription.created` | `stripe_subscription_id`, `stripe_status=active` |
| `customer.subscription.updated` | `stripe_price_id`, `stripe_status` |
| `customer.subscription.deleted` | `plan=free`, `stripe_status=canceled`, `subscription_ends_at=now()` |

---

## 四、Tenants 表 Stripe 字段

| 字段 | 类型 | 说明 |
|------|------|------|
| `stripe_customer_id` | string nullable | Stripe Customer ID（`cus_xxx`） |
| `stripe_subscription_id` | string nullable | Stripe Subscription ID（`sub_xxx`） |
| `stripe_price_id` | string nullable | 当前订阅的 Price ID（`price_xxx`） |
| `stripe_status` | string nullable | `active` / `canceled` / `past_due` / `unpaid` / `incomplete` |
| `subscription_ends_at` | timestamp nullable | 订阅结束时间（取消时设置） |

---

## 五、测试（Stripe Test Mode）

### 1. 创建 Stripe Test Account
[Stripe Dashboard](https://dashboard.stripe.com/test) 切换到 Test 模式

### 2. 创建 Price
```bash
curl -s https://api.stripe.com/v1/prices \
  -u sk_test_xxx: \
  -d "product_data[name]=CRM Pro" \
  -d "unit_amount=2900" \
  -d "currency=usd" \
  -d "recurring[interval]=month"
# → 拿到 price_xxx 填入 STRIPE_PRICE_PRO
```

### 3. 测试 Webhook（本地开发）
Stripe CLI 转发：
```bash
stripe listen --forward-to http://crm.servbay.host/webhooks/stripe
# 复制 webhook secret → 填入 STRIPE_WEBHOOK_SECRET
```

触发测试事件：
```bash
stripe trigger customer.subscription.created
```

### 4. 测试信用卡
| 卡号 | 场景 |
|------|------|
| `4242 4242 4242 4242` | 成功 |
| `4000 0000 0000 0002` | 需 3D 验证 |
| `4000 0000 0000 0069` | 订阅过期 |
| `4000 0000 0000 0119` | 发卡行拒绝 |

---

## 六、服务降级策略

`StripeSubscriptionService::configured()` 检测 `config('stripe.secret_key')`：

```php
if (! $stripe->configured()) {
    return null;  // 不崩溃，返回 null
}
```

- 无密钥时 `ensureCustomer()` / `createCheckoutSession()` 返回 null
- Webhook 端点无密钥时签名验证始终失败（安全）
- **本地开发/CI 无需 Stripe 账户**

---

## 七、计费策略扩展点

Stripe 计费切换的迁移代码：
```
database/migrations/2026_10_12_000015_add_stripe_fields_to_tenants_table.php
```

未来可扩展：
- **按座位计费**：`quantity` 参数 = 租户内用户数
- **按量计费**：Stripe Metered Billing + usage record 定期上报
- **自动试转付费**：`trial_ends_at` 到期前 7 天自动创建 Trial Checkout Session
- **多币种**：Stripe Price 的 `currency` 字段 + 汇率转换
