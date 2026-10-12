# Stripe 账单与发票同步

租户在 Stripe 完成订阅付费后，发票（Invoice）自动同步到平台并在「设置 → 套餐订阅 → 账单历史」展示，无需人工对账。

## 工作原理

```
Stripe Dashboard ──(invoice.paid / invoice.payment_failed)──▶ /webhooks/stripe
                                                                   │
                                              StripeWebhookController（签名验证）
                                                                   │
                                        StripeSubscriptionService::handleInvoiceEvent
                                                                   │
                                                   tenant_invoices 表（幂等 upsert）
                                                                   │
                                     订阅页「账单历史」区块（TenantScope 租户隔离）
```

## 数据表 `tenant_invoices`

| 字段 | 说明 |
|------|------|
| `tenant_id` | 租户归属（BelongsToTenant + TenantScope 自动隔离） |
| `stripe_invoice_id` | Stripe 发票 ID（`in_xxx`），唯一键，保证幂等 |
| `amount` | 金额，最小货币单位（如美分），展示时 ÷100 |
| `currency` | 三位货币码（usd / cny 等） |
| `status` | paid（已支付）/ open（待支付）/ failed（支付失败）/ void / uncollectible |
| `hosted_invoice_url` | Stripe 托管账单页链接，租户可在线查看 / 下载 PDF |
| `period_start` / `period_end` | 账期 |
| `paid_at` | 实际支付时间 |

## 处理的事件

在 `config/stripe.php` 已配置 `secret_key` 与 `webhook_secret` 的前提下：

| 事件 | 行为 |
|------|------|
| `invoice.paid` | 落库一条已支付账单（含支付时间、账期、账单链接） |
| `invoice.payment_failed` | 落库一条 failed 账单，提醒租户付款失败 |

同一发票重复推送（Stripe 自动重试）会按 `stripe_invoice_id` **幂等更新**而非重复创建。

### 租户归属解析（两级兜底）

1. 优先读发票 `metadata.tenant_id`（Checkout Session 创建时已写入 metadata）；
2. 缺失时按发票 `customer` 反查 `tenants.stripe_customer_id`；
3. 两者都解析不到租户时静默跳过（graceful degradation，不阻塞 Webhook 返回 200）。

> 注意：Stripe Dashboard 配置 Webhook 端点时无需额外设置 metadata，Checkout Session 与订阅均已携带 `tenant_id`。

## 租户侧入口

「设置 → 套餐订阅」页面底部新增 **账单历史** 区块：

- 展示最近 12 条账单：金额、货币、状态徽章（已支付 / 待支付 / 支付失败…）、账期、支付日期；
- 点击「查看账单」跳转 Stripe 托管账单页（可在线查看或下载 PDF 发票）；
- 数据经 TenantScope 自动隔离，租户只能看到自己的账单。

## 配置与上线步骤

1. `.env` 配置 Stripe 密钥：

   ```env
   STRIPE_SECRET_KEY=sk_live_xxx
   STRIPE_WEBHOOK_SECRET=whsec_xxx
   ```

2. Stripe Dashboard → Developers → Webhooks → Add endpoint：
   - URL：`https://你的域名/webhooks/stripe`
   - 事件：`invoice.paid`、`invoice.payment_failed`（以及原有的 `customer.subscription.*`）
3. 复制签名密钥到 `STRIPE_WEBHOOK_SECRET`。

## 降级行为

- 未配置 `STRIPE_WEBHOOK_SECRET`：Webhook 直接拒绝（签名验证失败），不会写入脏数据；
- 未配置 Stripe：订阅页账单历史为空集合，页面正常渲染（显示"暂无账单记录"）；
- 发票事件解析失败：异常已捕获并 report()，返回 500 让 Stripe 重试。

## 测试

`tests/Feature/StripeInvoiceTest.php`（7 项）覆盖：

- invoice.paid 落库字段完整性（金额 / 货币 / 状态 / 账期 / 支付时间）
- 同一发票幂等 upsert（不重复、金额更新）
- metadata 缺失时按 customer 反查租户
- 未知租户发票跳过
- payment_failed → status=failed、paid_at 为空
- 订阅页账单历史渲染（金额格式 / 状态徽章 / 账单链接）
- 跨租户账单不可见（TenantScope 隔离）
