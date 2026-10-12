# 审计日志系统

本文档描述多租户 CRM 的审计日志机制，包括单模型 CRUD 自动记录、批量操作聚合审计、以及审计日志查询页面的使用。

---

## 一、系统构成

审计日志由两个核心零件协作完成：

| 组件 | 位置 | 职责 |
|------|------|------|
| `Auditable` Trait | `app/Models/Traits/Auditable.php` | 挂在模型上，监听 `created / updated / deleted / restored` 事件，自动调用 `AuditService::record()` |
| `AuditService` | `app/Services/AuditService.php` | 统一写入审计日志，旁路化（try-catch 吞异常），批量上下文管理 |

### 哪些模型被审计？

以下业务模型 use `Auditable` Trait：

- `Company`（公司）
- `Contact`（联系人）
- `Lead`（线索）
- `Opportunity`（商机）
- `Attachment`（附件，Phase 2）

> `Attachment` **没有 SoftDeletes**，因此只会产生 `created` / `updated` / `deleted` 三类日志（无 `restored`）。
> `Stage` / `Activity` / `TenantWebhook` / `TenantWorkflow` 未挂 `Auditable`：活动历史本身即操作记录，阶段与集成配置为低敏数据。
> `AuditLog` 模型**故意不挂** `Auditable`，否则写一条审计日志会触发另一条，形成无限递归。

---

## 二、单模型审计（自动）

### 用法

在业务模型上 use 一下就行，**不需要写任何额外代码**：

```php
class Company extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant, Auditable;
    // ...
}
```

之后每次 `Company::create()` / `$company->update()` / `$company->delete()` 都会自动产生一条审计日志。

### 产生的 action 类型

| action 值 | 触发时机 | before | after |
|-----------|----------|--------|-------|
| `created` | `Model::create()` / `$m->save()`（新记录） | `null` | 新记录 fillable 快照 |
| `updated` | `$m->save()`（已有记录字段变化） | 修改前 fillable 快照 | 修改后 fillable 快照 |
| `deleted` | `$m->delete()`（含软删） | 删除前 fillable 快照 | `null` |
| `restored` | `$m->restore()`（软删恢复） | `null` | 恢复后的 fillable 快照 |

### 已知限制

`Auditable` Trait 挂的是 **模型事件**，下列操作不触发：

```php
// ❌ 不会产生审计日志
Company::where('status', 'archived')->update(['status' => 'active']);
Contact::where('id', '<', 100)->delete();
```

批量操作（Query Builder 级别的 update/delete）会绕过模型事件。**正确做法**是逐条调用模型实例，或使用下文的 `AuditService::withBatch()`。

---

## 三、批量操作审计

### 核心 API：`AuditService::withBatch()`

```php
public static function withBatch(
    string $action,        // 单条动作：deleted / updated / created
    string $modelClass,    // 被操作的模型类名
    callable $callback,    // 批量操作闭包
): mixed
```

闭包执行期间，`Auditable` Trait 产生的每条单模型日志**自动带上同一个 `batch_id`**。闭包执行完毕后，额外写一条 `action = "batch_{$action}"` 的**汇总日志**。

### 典型用法

```php
use App\Services\AuditService;
use App\Models\Contact;

// 批量删除
AuditService::withBatch('deleted', Contact::class, function () use ($selectedIds) {
    foreach ($selectedIds as $id) {
        $contact = Contact::find($id);
        if ($contact) {
            $contact->delete();  // 逐条触发 Auditable trait
        }
    }
    return count($selectedIds);  // 返回的数字会写入汇总日志
});

// 批量更新（比如把一批线索状态改为 qualified）
AuditService::withBatch('updated', Lead::class, function () use ($ids) {
    foreach ($ids as $id) {
        $lead = Lead::find($id);
        if ($lead) {
            $lead->update(['status' => 'qualified']);
        }
    }
    return count($ids);
});
```

### 产生的日志条数

一次 `withBatch` 调用会产生 **N + 1** 条日志：

- **N 条单模型日志**：每条带相同 `batch_id`
- **1 条汇总日志**：`auditable_type = 模型类名`，`auditable_id = 0`，`after` 字段存 JSON 摘要

### 汇总日志的 `after` 结构

```json
{
    "count": 5,
    "action": "deleted",
    "status": "success"
}
```

如果闭包内抛异常，汇总日志仍会写（`status = "partial"`），且包含错误信息：

```json
{
    "count": 3,
    "action": "deleted",
    "status": "partial",
    "error": "Some specific error message"
}
```

异常会继续向外抛出，汇总日志只是旁路记录。

### 闭包的返回值

闭包的返回值会被当作「处理条数」写入汇总日志的 `after.count`。返回 `int` 最合理；返回 `null` 或非数字时 `count` 记为 0。

### 批量操作的 UI（内置）

四个 Livewire Index 组件（Company / Contact / Lead / Opportunity）都已内置批量删除：

1. 列表左侧出现复选框
2. 勾选若干行 → 顶部出现蓝色批量操作栏，显示「已选中 N 条」
3. 点击「批量删除」→ 二次确认 → 调用 `AuditService::withBatch('deleted', ...)`

---

## 四、审计日志查询页面

路由：`GET /audit-logs`，需要已登录且属于某个租户。

### 筛选

| 筛选项 | 说明 |
|--------|------|
| 搜索 | 用户名 / IP / 路由名 / 批次号（`batch_id` 前 8 位即可） |
| 操作类型 | 新增 / 修改 / 删除 / 恢复 / **批量删除 / 批量修改 / 批量新增** |
| 模型 | 公司 / 联系人 / 线索 / 商机 / 附件 |
| 日期范围 | 创建时间 |

### 批量日志的视觉标记

- **同批次行**：背景有极淡的紫色高亮
- **批次号**：时间列下方显示 `📦 xxxxxxxx…`（uuid 前 8 位）
- **汇总日志**：紫色「批次汇总」标签 + 具体动作标签（如红色「批量删除」），`auditable_id` 为 0

### 详情列

| action | 详情展示 |
|--------|----------|
| `created` | `after` 快照的前 3 个字段 |
| `updated` | 逐字段 diff：旧值 ~~删除线~~ → 新值（绿色） |
| `deleted` | `before` 快照的前 3 个字段 |
| `restored` | 同 `updated`，对比 `before`（null）和 `after`（恢复后） |
| `batch_*` | 汇总：`count` + `status`（`success` / `partial`）+ 可选 `error` |

---

## 五、tenant_id 来源

审计日志的 `tenant_id` **永远从被操作对象身上取**，而非当前登录用户。

```php
private static function resolveTenantId(Model $auditable): int
{
    if (isset($auditable->tenant_id)) {
        return (int) $auditable->tenant_id;  // ← 目标对象的 tenant_id
    }
    // fallback：当前登录用户的 tenant
    $user = auth()->user();
    if ($user && ! empty($user->tenant_id)) {
        return (int) $user->tenant_id;
    }
    throw new \RuntimeException('无法解析审计日志的 tenant_id');
}
```

这个设计保证平台超管跨租户操作时，日志归属正确的目标租户，而非超管自己的租户（超管 `tenant_id` 通常为 `null`）。

---

## 六、旁路化设计

审计写入**绝不阻断主流程**。`AuditService::record()` 和 `AuditService::withBatch()` 内部全部 `try-catch`，任何异常只写 Laravel `warning` 日志：

```php
try {
    AuditLog::forceCreate([...]);
} catch (\Throwable $e) {
    Log::warning('审计日志写入失败', [
        'action' => $action,
        'model' => get_class($auditable),
        'id' => $auditable->getKey(),
        'error' => $e->getMessage(),
    ]);
}
```

生产环境可以安全地**禁用**审计日志写入（比如把 `audit_logs` 表备份后清空），业务逻辑不会受到任何影响。

---

## 七、新增批量操作的步骤

要给一个新模型或新操作类型添加批量审计支持：

1. **模型 use Auditable**（如果还没有）
2. **在 Controller/Livewire 中逐条操作 + withBatch 包裹**：

```php
AuditService::withBatch('deleted', \App\Models\NewModel::class, function () use ($ids) {
    $count = 0;
    foreach ($ids as $id) {
        $item = \App\Models\NewModel::find($id);
        if ($item) { $item->delete(); $count++; }
    }
    return $count;
});
```

3. **审计日志页面无需改动**：`action` 只要是 `batch_` 开头就能自动被 `audit-logs.blade.php` 识别为汇总日志。如果想让它出现在筛选下拉里，在 `app/Livewire/AuditLogs.php` 的 `actions` 数组里加一条：

```php
'actions' => [
    // ...
    'batch_deleted' => '批量删除',
    'batch_custom'  => '批量自定义操作',  // ← 新增
],
```

---

## 八、查询同一次批量操作的全部日志

```php
// 拿到某个 batch_id 的所有日志（汇总 + 所有单模型）
$batchId = '550e8400-e29b-41d4-a716-446655440000';
$logs = AuditLog::where('batch_id', $batchId)
    ->orderBy('auditable_id')  // 汇总在最后（auditable_id=0 在前）
    ->get();

// 或者快速聚合：
AuditLog::selectRaw('batch_id, COUNT(*) as cnt, action')
    ->whereNotNull('batch_id')
    ->groupBy('batch_id', 'action')
    ->get();
```

---

## 九、CSV 导出与保留策略

### CSV 导出（Phase 6）

审计页顶部「导出 CSV」按钮 → `AuditLogs::exportCsv()` → `CsvService::downloadAuditLogs()`：

- **导出内容与当前筛选一致**（搜索 / 操作类型 / 模型 / 日期范围全部生效）
- Web 请求下 `AuditLog` 挂 `TenantScope`，只导出当前租户日志
- 列：ID / 时间 / 操作者 / 动作（原始 action）/ 对象类型 / 对象 ID / Batch ID / IP / Route / 变更前 / 变更后
- UTF-8 BOM 兼容 Excel；diff JSON 压成单行避免破坏行结构；`chunkById(500)` 分块，大数据量安全

### 保留策略清理

```bash
# 按保留天数清理（默认读 config crm.audit.retention_days，默认 365）
php artisan crm:prune-audit-logs

# 显式天数 + 预览模式（只统计不删除）
php artisan crm:prune-audit-logs --days=180 --dry-run
```

```php
// config/crm.php
'audit' => [
    'retention_days' => env('CRM_AUDIT_RETENTION_DAYS', 365),
],
```

- Console 逃生舱下全租户执行（TenantScope 无上下文时不过滤）
- 分批删除（`chunkById(1000)`）避免大事务锁表
- 建议 cron 每周日凌晨执行一次（可与 `crm:send-reminders` 同机部署）
