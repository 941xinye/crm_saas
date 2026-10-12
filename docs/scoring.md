# 商机智能评分（Phase 4）

基于多维度加权算法为每个商机计算 0-100 的优先级分数，帮助销售团队识别高/低优先级商机。

---

## 一、评分维度

实现在 [app/Services/OpportunityScoringService.php](../app/Services/OpportunityScoringService.php)，权重可在 `config/crm.php` 的 `scoring` 节调整（各项之和应为 100）：

| 维度 | 默认权重 | 计算方式 |
|------|---------|----------|
| `value` 金额分 | 25 | `min(100, 商机金额 / 租户内最大金额 × 100)`，**租户内归一化** |
| `stage` 阶段分 | 25 | 赢单阶段 100 分；输单阶段 20 分；其他按阶段排序位置归一化 50-90 分 |
| `velocity` 活跃度分 | 20 | 活动数（5 分/个，封顶 30）+ 更新新鲜度（每天扣 1 分），归一化到 100 |
| `urgency` 紧迫感分 | 15 | 已过期 100；≤3 天 90；≤7 天 75；≤30 天 50；其余 25；无日期 50 |
| `stall` 停滞惩罚分 | 15 | ≥30 天 0 分；≥14 天 30；≥7 天 60；≥3 天 85；其余 100 |

总分 = Σ(维度分 × 权重/100)，四舍五入限制在 0-100。

> **租户隔离**：金额归一化的 max(value) 查询经过 TenantScope 自动过滤，仅统计本租户商机，不泄露跨租户数据。

---

## 二、落库字段

迁移 `2026_10_12_000016_add_score_to_opportunities_table.php`：

| 字段 | 类型 | 说明 |
|------|------|------|
| `score` | int nullable | 0-100 评分 |
| `scored_at` | timestamp nullable | 最后计算时间 |

---

## 三、批量刷新命令

评分不实时计算（避免列表页 N+1），通过命令批量刷新：

```bash
# 刷新指定租户
php artisan crm:refresh-scores --tenant=acme

# 刷新所有租户
php artisan crm:refresh-scores --all
```

命令逐租户 `Tenancy::setTenant()` 后调用 `scoreBatch()`，通过 Query Builder 直接写 `score` / `scored_at`（不触发模型事件，避免产生审计噪音）。

### 生产 Cron 建议

```bash
# 每天凌晨 1:30 刷新全租户评分（配合 crm:send-reminders / crm:run-workflows）
30 1 * * * cd /path/to/crm && php artisan crm:refresh-scores --all >> storage/logs/scoring.log 2>&1
```

---

## 四、API 使用

```php
use App\Services\OpportunityScoringService;

$scoring = app(OpportunityScoringService::class);

// 单条评分（实时，走 TenantScope）
$score = $scoring->score($opportunity);

// 批量评分（租户内金额归一化，返回 [id => score]）
$scores = $scoring->scoreBatch($opportunities);
```

---

## 五、调整权重示例

低价值客户为主的业务可提高活跃度权重：

```php
// config/crm.php
'scoring' => [
    'value'    => 15,
    'stage'    => 30,
    'velocity' => 30,
    'urgency'  => 15,
    'stall'    => 10,
],
```

修改后执行 `php artisan config:clear` 并重跑 `crm:refresh-scores --all`。

### 评分骤降提醒（Phase 6）

`crm:refresh-scores` 刷新时会对比新旧评分：**新评分 ≤ 旧评分 − `crm.scoring.drop_threshold`（默认 15）** 时向商机负责人发送 `OpportunityScoreDropNotification`（站内信/邮件）。

- 冷却期去重：同一商机 24h 内只提醒一次（`crm.reminder.cooldown_hours`）
- 用户级静音：负责人可在「设置 → 通知偏好」关闭 `score_drop`
- 首次评分（`scored_at` 为空）不触发提醒

---

## 六、扩展方向

- 仪表板评分分段分布图（0-20 / 21-40 / ... / 81-100）
- 引入 LLM 辅助评分维度（结合跟进建议内容综合判断）
- 评分变化写入活动时间线（score_change 事件）
