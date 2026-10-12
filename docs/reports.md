# 数据报表

租户级销售数据聚合看板，路由 `/reports`。由 [app/Livewire/Reports.php](../app/Livewire/Reports.php) 展示、[app/Services/ReportService.php](../app/Services/ReportService.php) 聚合。

**套餐门控**：`plan.feature:reports` 中间件保护，专业版（pro）及以上可用（见 [docs/plans.md](plans.md)）。

---

## 一、四组报表

### 1. 销售漏斗（funnel）

线索各状态（new / contacted / qualified / converted / lost）数量 → 联系人总数 → 商机总数，逐级展示转化率：

- 线索转化率 = converted 线索 / 总线索
- 线索→联系人比率、联系人→商机比率

### 2. 按人业绩（performanceByOwner）

每个有商机所有权的成员：商机数、总成交额、赢单数、赢率，按成交额降序。赢单判定使用 Stage 的 `is_won` 标记（而非"最后阶段"假设）。

### 3. 月度趋势（monthlyTrend）

近 N 个月（URL 参数 `?period=`，默认 6）每月新增：线索数、公司数、商机数、商机金额合计。

### 4. 商机阶段分布（stageBreakdown）

按销售阶段统计：商机数、总金额、平均金额。

### 可视化（ECharts，已内置）

报表页通过 `@push('scripts')` 引入 ECharts 5（CDN）渲染三张图：

- **月度趋势**：双 Y 轴折线 + 柱状（数量 / 金额）
- **销售漏斗**：漏斗图（线索状态逐级收敛）
- **阶段金额分布**：环形饼图

> 注意：图表数据随 Livewire 渲染注入，切换 `?period=` 后整页刷新重绘。

---

## 二、CSV 导出（Phase 6）

报表页右上角提供四个导出按钮（趋势 / 业绩 / 阶段 / 漏斗），`Reports::exportCsv($dataset)` → `CsvService::downloadReport()`：

- 中文表头（月份 / 负责人 / 阶段 / 指标…），UTF-8 BOM 兼容 Excel
- 内容与当前 `?period=` 一致；行构建方法为静态 `Reports::trendRows()` 等，便于单测
- 套餐门控沿用 reports 中间件，非 pro 租户无法到达该页

---

## 三、数据权限

所有聚合查询经过两层过滤：

1. **租户隔离**：查询走 TenantScope，仅统计当前租户数据
2. **角色权限**：`scopeByOwner()` 按登录用户角色过滤——sales 仅统计 `owner_id = 自己` 的数据，admin/manager 统计全租户（与列表页数据权限一致，见 [docs/architecture.md](architecture.md)）

---

## 四、扩展方向

- 按来源（source）统计线索质量
- 商机停留时长分析（各阶段平均滞留天数）
- 图表数据随 Livewire 局部刷新（当前依赖整页重绘）
