# 平台收入/流失看板

平台超管专用的经营看板，路由 `platform.revenue`（侧边栏「收入看板」入口），组件 [Platform\Revenue](../app/Livewire/Platform/Revenue.php)。

## 指标口径

| 指标 | 口径 |
| --- | --- |
| MRR | `config/plans.php` 各套餐 `monthly_price` × 未流失付费租户数 |
| ARPU | MRR / 在营付费租户数 |
| 试用中 | `trial_ends_at` 在未来的租户数 |
| 流失租户 | 付费套餐且满足其一：`stripe_status = canceled`；或 `subscription_ends_at` 已过期 |
| 累计流失率 | 流失数 / (在营付费数 + 流失数) |
| 12 个月收入 | `tenant_invoices` 中 `status = paid` 的发票按 `paid_at` 月份汇总，金额由最小货币单位换算（÷100），按币种分列 |

注意：

- Stripe 未接入或租户未经 Stripe 订阅（`stripe_status` 为 null）时，MRR 按「租户当前套餐」口径统计（graceful degradation），流失判定仅由 `subscription_ends_at` 触发
- 未支付（open/failed）发票不计入收入汇总
- 免费（free）租户永远不计入流失

## 套餐价格配置

MRR 与 ARPU 依赖 [config/plans.php](../config/plans.php) 中每个套餐的 `monthly_price`（单位：元，CNY 口径）。修改后需 `php artisan config:clear`。

## 界面构成

1. 核心指标卡片：MRR（含 ARPU）、在营付费/免费/试用租户数、累计流失率、租户总数（含套餐月价提示）
2. 近 12 个月发票收入柱状图（纯 CSS，按币种分列，hover 显示月份明细）
3. 流失租户列表（最近 10 个，标注流失原因：取消 / 到期）
4. 最近发票列表（最近 10 条，租户名、金额、状态徽章）

## 测试

`tests/Feature/PlatformRevenueTest.php`（9 个用例）：访问控制（平台超管可见、租户用户 403）、MRR/ARPU 汇总、canceled 与到期两类流失判定及 MRR 排除、试用计数、免费租户不流失、发票按月/币种汇总、12 个月窗口过滤。
