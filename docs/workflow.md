# 自动化工作流（Phase 4 / Phase 5 租户化）

工作流引擎监听业务模型事件，按配置的规则自动执行动作（建任务 / 发通知 / 改阶段 / 改概率）。

规则来源分两层，合并执行：

1. **租户自定义规则**（`tenant_workflows` 表）：租户管理员在 UI 中配置，TenantScope 按租户隔离；
2. **平台示例规则**（`config/workflows.php`）：平台内置，随代码部署，所有租户默认可用。

---

## 一、架构

```
业务事件（Opportunity created/updated）
        │
        ▼
WorkflowObserver（app/Observers/WorkflowObserver.php，旁路 try-catch）
        │  created / updated 直接透传
        │  stage_id 变化 → 额外触发 entered_stage
        ▼
WorkflowEngine::evaluate()（app/Services/WorkflowEngine.php）
        │  rulesForModel(): tenant_workflows 表（TenantScope 隔离、is_active、priority 降序）
        │                  + config/workflows.php（示例规则）
        │  匹配 trigger + conditions（AND 逻辑）
        ▼
执行 actions（create_activity / notify_owner / set_stage / set_probability）
        │
        ▼
DB 规则命中后回写 last_triggered_at（旁路，失败不影响业务）
```

**关键设计**：

| 机制 | 说明 |
|------|------|
| 旁路化 | Observer 全程 try-catch，工作流失败只 `report()` 不阻塞主业务 |
| 防递归 | 引擎内 `evaluating` 映射表，动作更新模型再次触发事件时直接跳过 |
| 租户隔离 | DB 规则经 TenantScope 限定当前租户；无租户上下文时不加载任何规则 |
| 无静态缓存 | 每次事件实时查库——长驻 worker（Octane / queue）下规则变更即时生效 |
| stalled 触发 | 非事件驱动，由 `crm:run-workflows` 命令每日扫描（带 24h 防重） |

---

## 二、规则结构

### 租户自定义规则（tenant_workflows 表）

| 字段 | 类型 | 说明 |
|------|------|------|
| `name` | string | 规则名 |
| `model` | string | 目标模型 FQCN（当前仅 `App\Models\Opportunity`） |
| `trigger` | string | created / updated / entered_stage / stalled |
| `conditions` | json | `[[field, operator, expected], ...]`，AND 关系，可为空 |
| `actions` | json | `[{type, params}, ...]`，顺序执行 |
| `is_active` | bool | 停用的规则不参与评估 |
| `priority` | int | 越大越先执行（DB 规则整体优先于 config 规则） |
| `last_triggered_at` | datetime | 最近命中时间（UI 展示） |

管理入口：**设置 → 自动化工作流**（路由 `/settings/automation`，企业版 `automation` feature 门控；admin/manager 可管理，sales 只读）。

UI 支持可视化配置：条件下拉选择（字段/运算符/值），动作按类型显示对应参数表单（活动类型/标题/备注/计划时间、通知内容、目标阶段、概率）。创建 `create_activity` 动作时 UI 自动注入 `{activitable_type}` / `{activitable_id}` / `{owner_id}` 占位符，无需手填。

### 平台示例规则（config/workflows.php）

```php
[
    'name' => '商机停滞自动跟进任务',       // 展示用规则名
    'model' => \App\Models\Opportunity::class, // 目标模型（FQCN）
    'trigger' => 'stalled',                  // 触发事件
    'conditions' => [                        // 条件列表（AND）
        ['stalled_days', '>=', 14],
    ],
    'actions' => [                           // 动作列表（顺序执行）
        [
            'type' => 'create_activity',
            'params' => [
                'activitable_type' => '{activitable_type}',
                'activitable_id' => '{activitable_id}',
                'owner_id' => '{owner_id}',
                'type' => 'task',
                'subject' => '跟进停滞商机「{title}」',
                'notes' => '已超过 {stalled_days} 天无活动',
                'scheduled_at' => '+1 day',
            ],
        ],
        [
            'type' => 'notify_owner',
            'params' => ['message' => '商机「{title}」已停滞 {stalled_days} 天'],
        ],
    ],
]
```

---

## 三、触发器（trigger）

| 值 | 触发时机 |
|----|----------|
| `created` | 模型创建时（Observer 事件驱动） |
| `updated` | 模型更新时（Observer 事件驱动） |
| `entered_stage` | `stage_id` 字段变化时（Observer 检测 isDirty） |
| `stalled` | 商机停滞扫描（`crm:run-workflows` 命令每日触发） |

---

## 四、条件（conditions）

每项 `[field, operator, expected]`，匹配模式由规则的 `match` 字段控制（见下）。

### 可用字段

| 字段 | 说明 |
|------|------|
| `stalled_days` | 停滞天数（最近活动 vs 更新时间取较近者距今天数） |
| `value` | 商机金额 |
| `probability` | 赢单概率 |
| `days_since_close` | 距预计成交日天数 |
| `stage_id` | 当前阶段 ID |
| 其他模型字段 | 透传 `$model->$field`（如 `owner_id`、`title`） |

### 可用运算符

`= == != > >= < <= contains`（`contains` 对字符串做 `str_contains`）

### 匹配模式（Phase 6 / P2）

规则的 `match` 字段（DB 规则列 `condition_match`，config 规则键 `match`）：

| 值 | 语义 |
|------|------|
| `all` | **默认**，全部条件满足（AND），向后兼容：缺省即为 all |
| `any` | 任一条件满足（OR） |

设置页「自动化工作流」编辑表单提供下拉选择。

---

## 五、动作（actions）

| type | 参数 | 效果 |
|------|------|------|
| `create_activity` | `type`(call/email/meeting/note/task)、`subject`、`notes`、`scheduled_at`、`owner_id` | 为商机/模型创建跟进活动，`scheduled_at` 支持 `'+1 day'` 相对格式 |
| `notify_owner` | `message` | 给模型负责人发站内信（`AutomationTriggeredNotification`） |
| `set_stage` | `stage_id` | 更新商机阶段（再次触发 Observer，由防递归机制拦截） |
| `set_probability` | `probability` | 更新赢单概率 |
| `send_webhook` | `event`（默认 `workflow.triggered`，需与租户 Webhook 订阅的事件一致）、`message`（可选，支持占位符） | 复用 `WebhookService::dispatch()` 推送到租户订阅该事件的 Webhook（HMAC 签名 / 队列化 / 失败计数均由 WebhookService 处理）；整体 try-catch 旁路化，失败不影响后续动作 |

`send_webhook` 载荷：`entity_type`（模型短名）、`entity_id`、`title`（title/name）、`message`。

### 占位符

`{field}` 在动作参数中被当前模型字段值替换：

```
{tenant_id} {activitable_type} {activitable_id} {owner_id} {stalled_days} {title}
```

---

## 六、运行方式

### 事件驱动规则（created / updated / entered_stage）

自动触发，无需额外配置。`Opportunity` 模型已挂 `WorkflowObserver`（见 [AppServiceProvider](../app/Providers/AppServiceProvider.php)）。

### stalled 规则（每日扫描）

```bash
# 手动运行（需指定范围）
php artisan crm:run-workflows --tenant=acme
php artisan crm:run-workflows --all

# Cron（生产推荐，凌晨 2 点）
0 2 * * * cd /path/to/crm && php artisan crm:run-workflows --all >> storage/logs/workflows.log 2>&1
```

**防重逻辑**：24 小时内已为该商机自动创建过「跟进停滞商机」类活动则跳过，避免重复建任务。

---

## 七、当前限制与扩展点

| 限制 | 说明 |
|------|------|
| 仅 Opportunity | Observer 仅注册在 `Opportunity` 模型上，其他模型需自行注册（UI 的"目标对象"下拉同步受限） |
| 动作同步执行 | 动作在请求周期内同步执行，量大时建议改队列派发（见 Phase 5 队列化章节） |
| 触发统计仅时间 | `last_triggered_at` 只记录最近命中时间，完整执行日志见"可观测性"路线图 |

### 已完成：租户自定义规则（原路线图项）

`tenant_workflows` 表 + 引擎 DB 规则加载 + Livewire 管理 UI 已落地（Phase 5），见本文第二节。租户规则与 config 示例规则合并执行、互不干扰；停用/优先级/租户隔离均有测试覆盖（`tests/Unit/TenantWorkflowRulesTest.php`、`tests/Feature/SettingsAutomationUiTest.php`）。
