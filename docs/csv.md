# CSV 导入导出

统一的 CSV 批量数据处理能力，覆盖公司、联系人、线索、商机、活动五个业务模型。实现在 [app/Services/CsvService.php](../app/Services/CsvService.php)，入口在五个列表页（Livewire）。

---

## 一、功能入口

每个列表页（公司 / 联系人 / 线索 / 商机 / 活动）提供三个操作：

| 操作 | 行为 |
|------|------|
| 导出 | 下载当前租户全部（含当前筛选）记录 CSV |
| 下载模板 | 只有表头的空 CSV，供填写后导入 |
| 导入 | 上传 CSV 文件，逐行创建记录，返回成功数与行级错误 |

以公司页为例见 [app/Livewire/Companies/Index.php](../app/Livewire/Companies/Index.php)，其余列表页同构。

---

## 二、列配置

列配置单一来源为 `CsvService::COLUMNS`（`[中文表头 => db_column]`），同时作为**导入字段白名单**——CSV 中出现映射之外的列会被忽略。新增模型支持 CSV 只需在常量中加一组映射。

导出时关联字段（如商机的公司名/联系人名/阶段名）自动解析为可读名称；导入时反向按名称匹配已有记录（详见下文"关联解析"）。

---

## 三、导出规范

- **UTF-8 BOM**：文件头写入 `\xEF\xBB\xBF`，Windows Excel 直接打开中文不乱码
- 特殊字符（逗号、引号、换行）自动加引号转义
- 日期字段输出 `Y-m-d`
- 查询走 TenantScope，只导出当前租户数据；传入自定义 Builder 时支持按当前筛选导出

---

## 四、导入规则

### 1. 前置拦截（整个批次拒绝）

- **试用期检查**：free 套餐试用过期，整批拒绝
- **配额检查**：`当前用量 + CSV 行数 > 配额` 时整批拒绝（避免导到一半失败）

### 2. 表头校验（整批拒绝）

缺少必需列时报错，如商机的 `标题`、`阶段`。

### 3. 行级处理（单行失败不影响其他行）

- 空行自动跳过
- 必填字段行级校验（如公司必须有名称、联系人姓/名至少一项）
- **查重跳过（Phase 6）**：Company 按名称 / Contact 按邮箱精确匹配租户内已有记录，重复行跳过并计数（含同文件内前序行产生的重复）；导入完成提示「跳过重复 N 条」
- 逐行 `PlanService::ensureCanCreate` 二次配额校验
- 逐行 try-catch，错误按**含表头的真实行号**收集，导入完成后一次性返回 `{created, skipped, duplicates, errors}`

### 4. 安全：跨租户注入防护

以下字段一律从 CSV 数据中剥离，无论表头怎么写都无法覆盖：

```
tenant_id, owner_id, user_id, stage_id, company_id, contact_id, activitable_type, activitable_id
```

### 5. 关联名称解析

CSV 中写名称而非 ID，导入时自动解析为租户内记录：

| 列 | 解析规则 |
|------|---------|
| 联系人/商机的 `公司` | 按名称精确匹配 Company，未匹配到则留空 |
| 商机的 `阶段` | 按名称匹配 Stage，**找不到直接报行错误**（阶段必填） |
| 商机的 `联系人` | 全名匹配（空格拆分 first/last 或中式姓+名），失败后模糊匹配，未匹配留空 |
| 活动的 `负责人` | 按**当前租户**的 User 姓名匹配，找不到报行错误 |

同时自动做类型转换：金额转 float、日期转 `Y-m-d` / datetime。

---

## 五、大文件后台导入（Phase 10）

行数超过阈值时，导入自动转为**后台队列执行**，避免大文件上传请求超时：

- **阈值**：`config('crm.import.async_threshold')`（env `CRM_IMPORT_ASYNC_THRESHOLD`，默认 200 行），设为 `0` 表示全部同步执行
- **判定入口**：五个列表页的导入方法通过 `WithCsvAsyncImport` trait 的 `queueLargeImport()` 前置判断，命中阈值时创建后台任务记录、flash 提示「已转入后台导入」并终止同步流程
- **进度记录**：`csv_import_jobs` 表记录状态（`pending → processing → completed / failed`）、总行数、已处理行数（每 25 行回写一次）、成功/跳过/重复/失败行数与错误明细（JSON）
- **队列任务**：[app/Jobs/ImportCsvJob.php](../app/Jobs/ImportCsvJob.php) 复用 `CsvService::import` 逐行导入；通过 `Tenancy::setTenant()` 显式注入租户上下文并在 `finally` 复位（与白标 SMTP 同一 worker 隔离约定）；**`tries=1` 不重试**（逐行 create 非幂等，重试会产生重复数据）
- **完成通知**：导入完成/失败后向发起人发送站内信（`CsvImportCompletedNotification`，database 频道），失败时附错误明细下载链接
- **失败明细下载**：`GET /csv-imports/{job}/errors`（[app/Http/Controllers/CsvImportController.php](../app/Http/Controllers/CsvImportController.php)）输出带行号的错误 CSV（UTF-8 BOM），**仅发起人本人可下载**（同租户其他成员 403），且仅在存在行错误时可用
- **文件存储**：上传文件落 `storage/app` 私有磁盘（`imports/{id}.csv`），导入完成后删除

测试见 [tests/Feature/CsvAsyncImportTest.php](../tests/Feature/CsvAsyncImportTest.php)。

---

## 六、模板示例

商机导入模板（下载自商机列表页）：

```csv
标题,金额,预计成交日,阶段,公司,联系人
ERP 采购合同,120000,2026-11-30,谈判中,Acme 公司,张 三
```

---

## 七、扩展新模型

1. 在 `CsvService::COLUMNS` 加列映射
2. `validateRow()` 加必填校验分支
3. 有关联字段则在 `resolveRelations()` 加解析逻辑；需配额控制则在 `resourceKey()` 注册资源键
4. 列表页组件添加导出/导入方法（参考 Companies\Index）
