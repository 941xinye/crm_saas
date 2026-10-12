# 自定义字段（Custom Fields）

> Phase 6 / P1 功能。租户可为公司、联系人、商机三类实体定义自己的业务字段，
> 无需改代码、改表结构。专业版（pro）及以上可用。

## 功能概览

- **定义管理**：「设置 → 自定义字段」可视化配置（仅 admin 可保存）
- **表单渲染**：三实体的创建/编辑表单动态渲染，含必填验证
- **详情展示**：详情页「自定义字段」卡片展示已填值
- **列表筛选**：列表页按自定义字段值过滤（Web + REST API）
- **API 读写**：`custom_fields` 随 `/api/v1` 三个实体端点读写，未定义 key 自动丢弃
- **套餐门禁**：`plan.feature:custom_fields` 中间件 + 菜单按套餐显隐

## 数据模型

**定义存租户（`tenants.custom_fields`），值存业务表行（`custom_fields` JSON 列）。**

```json
// tenants.custom_fields — 按实体分组
{
  "company": [
    {"key": "customer_level", "label": "客户等级", "type": "select", "required": true, "options": ["A", "B", "C"]},
    {"key": "annual_revenue", "label": "年营收（万）", "type": "number", "required": false, "options": []}
  ],
  "contact": [...],
  "opportunity": [...]
}

// companies / contacts / opportunities.custom_fields — 字段值
{"customer_level": "A", "annual_revenue": 800}
```

实体：`company`（公司）/ `contact`（联系人）/ `opportunity`（商机）。
类型：`text` 单行文本 / `textarea` 多行文本 / `number` 数字 / `date` 日期 / `select` 单选下拉 / `multiselect` 多选。

## 核心服务

`App\Services\CustomFieldService`（单一入口，Web 与 API 共用）：

| 方法 | 用途 |
|------|------|
| `definitions($entity)` | 某实体的字段定义列表 |
| `definition($entity, $key)` | 单个字段定义 |
| `validationRules($entity)` | 生成 `custom_fields.key => rules`，与 Livewire / API 验证规则合并 |
| `sanitizeValues($entity, $values)` | 入库前清洗：类型规范化（number 转数值、multiselect 转数组）、丢弃未定义 key |
| `saveDefinitions($rows)` | 保存定义（key 规范化小写下划线、去重、select 必须有选项） |
| `isFilterableKey($entity, $key)` | 筛选 key 白名单校验（防 JSON path 注入） |
| `displayValue($definition, $value)` | 展示值格式化（多选数组转顿号拼接） |

## 使用方式

### Web 表单 / 详情 / 列表

三个 Form 组件挂 `public array $custom_fields`，`rules()` 合并服务规则，`save()` 经
`sanitizeValues` 清洗后入库。视图侧共用两个 partial：

```blade
{{-- 表单输入区（Companies/Contacts/Opportunities Form） --}}
@include('livewire.partials.custom-fields-inputs', ['entity' => 'company'])

{{-- 详情展示区（Show） --}}
@include('livewire.partials.custom-fields-show', ['entity' => 'company', 'model' => $company])
```

列表筛选由 `WithAdvancedFiltering` trait 提供（`cf_key` + `cf_value` 查询参数，
key 必须在租户已定义字段内，多选/下拉值按字符串精确匹配）。

### REST API

```bash
# 写入（未定义的 key 会被静默丢弃；select 值不在选项内返回 422）
POST /api/v1/companies
{"name": "云图科技", "custom_fields": {"customer_level": "A", "annual_revenue": 800}}

# 读取筛选
GET /api/v1/companies?cf_key=customer_level&cf_value=A
```

`cf_key` 仅接受合法标识符（`[A-Za-z0-9_]`），防止 JSON path 注入。

## 套餐与权限

- `config/plans.php`：`custom_fields` 加入 pro / enterprise 的 features 数组，
  free 租户访问时被 `plan.feature:custom_fields` 中间件拦截并提示升级
- 角色：任何角色都能**填写**字段值（跟随实体自身的增删改权限）；**定义管理**仅租户 admin
- 路由：`settings.custom-fields` → Livewire `Settings\CustomFields`

## 测试

`tests/Feature/CustomFieldsTest.php`（11 个用例）：定义保存/规范化/重复 key/选项校验、
sales 无权保存、必填与 select 选项验证、值清洗（未定义 key 丢弃、number 转数值）、
列表筛选与未定义 key 忽略、API 写入 + 筛选 + 非法选项 422。

## 已知限制

- CSV 导入导出不含自定义字段（后续按需扩展 `CsvService`）
- 列表筛选为精确匹配（不模糊、不范围），date 类型按字符串比较
- `order` 语义：多选/下拉选项顺序即展示顺序
