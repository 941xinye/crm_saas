# 性能基准与索引评估

## 基准命令

```bash
php artisan crm:benchmark                 # 每项执行 5 次，输出表格
php artisan crm:benchmark --iterations=10
php artisan crm:benchmark --json          # 机器可读输出（CI/脚本）
```

覆盖六类核心查询路径（[CrmBenchmark](../app/Console/Commands/CrmBenchmark.php)）：

| 基准项 | 对应业务 |
| --- | --- |
| dashboard_stats | 仪表板统计卡片（计数/求和） |
| opportunity_list | 商机列表分页（关联预载入） |
| global_search | 全局搜索（4 表 LIKE） |
| reminder_scan | 提醒扫描（活动到期窗口） |
| audit_recent | 审计日志按租户倒序分页 |
| reports_aggregate | 报表按阶段分组聚合 |

注意事项：

- 测试环境 sqlite `:memory:` 数据量小，结果仅作冒烟验证；**生产评估请在 MySQL + 真实数据量上运行**，并用 `EXPLAIN` 验证索引命中
- 命令在控制台上下文运行（TenantScope 逃生舱），基准查询用显式 `tenant_id` 过滤模拟租户内查询
- 首轮为预热不计时（连接建立、词法缓存）；耗时取平均与中位数

## 索引评估

查询模式 → 索引映射（基于各列表页筛选/排序、提醒扫描、报表聚合的真实查询路径）：

| 表 | 查询模式 | 索引 | 状态 |
| --- | --- | --- | --- |
| companies | tenant 过滤 | `tenant_id` | 已有 |
| contacts | tenant + company 关联 | `tenant_id, company_id` / `unique(tenant_id, email)` | 已有 |
| leads | tenant + owner + status 筛选 | `tenant_id, owner_id, status` | 已有 |
| opportunities | 看板（stage/owner 筛选） | `tenant_id, stage_id, owner_id` | 已有 |
| opportunities | 成交日提醒 / 报表日期聚合 | `tenant_id, expected_close_date` | 本次补充 |
| opportunities | 列表默认排序 / 月度新增 | `tenant_id, created_at` | 本次补充 |
| activities | 到期提醒时间窗扫描 | `scheduled_at` | 本次补充 |
| activities | 时间线/待办按主体聚合 | `activitable_type, activitable_id` | 本次补充 |
| activities | 「待我处理」 | `tenant_id, user_id` | 已有 |
| audit_logs | 审计页按租户倒序分页 | `tenant_id, created_at` | 本次补充 |
| audit_logs | 按对象 / 按动作过滤 | `tenant_id, auditable_*` / `tenant_id, action` / `batch_id` | 已有 |
| notifications | 通知中心未读过滤 | `notifiable_id, notifiable_type, read_at` | 已有 |
| tenant_webhooks | 活跃 webhook 匹配事件 | `tenant_id, is_active` | 已有 |

补充索引由迁移 [2026_10_13_000009_add_performance_indexes.php](../database/migrations/2026_10_13_000009_add_performance_indexes.php) 负责（`Schema::hasIndex` 防御式执行，对已手工补建的环境幂等）。

## 已知优化边界

- **LIKE '%kw%' 全局搜索**无法用 B-tree 索引；数据量大时可迁移 FULLTEXT（MySQL）或 Meilisearch / Typesense（ServBay 已内置）
- 多租户隔离依赖 `TenantScope` 每查询追加 `tenant_id` 条件——所有租户级表必须有 `tenant_id` 前导索引（已满足）
- 报表聚合（sum/groupBy）在千万行级别建议转为汇总表或物化视图，当前规模直接查询即可
- 队列通知（Queueable）在生产建议 `database` 驱动 + supervisor；jobs 表已有 `(connection, queue, failed_at)` 索引
- Livewire 列表页已通过 `with()`/`withCount()` 消除 N+1（见各 Index 组件），新增列表字段时注意保持预载入
