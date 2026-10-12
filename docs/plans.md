# 套餐与配额体系

SaaS 商业化的核心控制层：套餐定义、资源配额、功能门控三类检查，统一由 [app/Services/PlanService.php](../app/Services/PlanService.php) 提供，配置单一来源为 [config/plans.php](../config/plans.php)。

---

## 一、套餐定义

| | 免费版 free | 专业版 pro | 企业版 enterprise |
|------|------|------|------|
| 成员上限 | 3 | 20 | 不限 |
| 试用期 | 14 天 | — | — |
| 公司配额 | 50 | 2,000 | 不限 |
| 联系人配额 | 200 | 10,000 | 不限 |
| 线索配额 | 100 | 5,000 | 不限 |
| 商机配额 | 100 | 5,000 | 不限 |
| 报表 / 审计日志 / AI 跟进建议 | — | ✓ | ✓ |
| Webhook / 自动化工作流 | — | — | ✓ |

> 配额值 `-1` 表示不限制。修改配置后需 `php artisan config:clear`。

---

## 二、三类检查

### 1. 配额检查（数量上限）

```php
app(PlanService::class)->ensureCanCreate($tenant, 'companies');
// 超限抛 ValidationException，Livewire 表单自动展示：
// "已达到「专业版」公司数量上限（2000 条），请升级套餐。"
```

- `quota($tenant, $resource)`：读取配额，-1 为不限
- `usage($tenant, $resource)`：显式按 `tenant_id` 统计用量（不依赖 Tenancy 上下文，平台后台同样正确）
- **统一拦截入口**：表单提交、CSV 导入（整批前置 + 逐行二次）等数据创建入口都必须调用，见 [docs/csv.md](csv.md)

### 2. 试用期检查

free 套餐在 `trial_ends_at` 过期后禁止新增任何业务数据（付费套餐不受影响）。`canCreate()` 返回布尔，`ensureCanCreate()` 抛异常。

### 3. 功能门控（feature flags）

```php
// 中间件方式（路由级）
Route::get('/reports', Reports::class)->middleware('plan.feature:reports');

// 代码方式
PlanService::hasFeature($tenant, 'webhooks');
```

租户无该 feature 时跳转到升级提示页，文案来自 `plans.upgrade_messages`（按最低可解锁套餐分组，前端与中间件共用，避免硬编码）。

当前 feature 键：`reports`、`audit-logs`、`ai_followup`、`custom_fields`、`webhooks`、`automation`、`settings.team` 等，完整清单见 [config/plans.php](../config/plans.php)。

---

## 三、套餐与租户的关系

- 租户的 `plan` 字段决定套餐：平台超管在 `/platform/tenants` 手动变更，或通过 Stripe 订阅 Webhook 自动同步（见 [docs/stripe.md](stripe.md)）
- `$tenant` 为 null（平台超管上下文）时所有检查放行
- Stripe Checkout 的价格 ID ↔ 套餐映射配置在 `config/stripe.php`

---

## 四、扩展

- 新增 feature：`config/plans.php` 各套餐 `features` 数组加键 + `upgrade_messages` 加文案 + 路由挂 `plan.feature:` 中间件
- 新增配额资源：`PlanService::RESOURCES` 注册模型 + 各套餐 `quotas` 加键 + 创建入口调用 `ensureCanCreate`
