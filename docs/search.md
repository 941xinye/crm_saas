# 全局搜索

顶栏搜索框输入关键词回车，跳转 `/search` 页面（`?q=` 参数驱动，URL 可分享），一次检索**公司 / 联系人 / 线索 / 商机**四类实体。实现：[app/Livewire/GlobalSearch.php](../app/Livewire/GlobalSearch.php)。

## 一、覆盖字段

| 实体 | 搜索字段 |
|------|----------|
| 公司 | name、industry、website、phone |
| 联系人 | first_name、last_name、email、phone、position |
| 线索 | name、company_name、email、phone |
| 商机 | title |

匹配方式由 `config('crm.search.driver')` 决定（见下文"搜索驱动"），LIKE 模式下输入中的 `%` / `_` / `\` 已转义防通配符注入。

## 二、搜索驱动（Phase 10）

| 驱动 | 配置值 | 行为 | 适用场景 |
|------|--------|------|----------|
| LIKE（默认） | `like` | `LIKE %keyword%`，全表扫描 | sqlite / 小数据量 / 未跑索引迁移时 |
| FULLTEXT | `fulltext` | MySQL `MATCH...AGAINST` BOOLEAN MODE，ngram 分词（支持中文） | MySQL 生产环境 |

- FULLTEXT 索引由迁移 [2026_10_13_000011_add_fulltext_search_indexes.php](../database/migrations/2026_10_13_000011_add_fulltext_search_indexes.php) 添加（`WITH PARSER ngram`，四实体各一个复合索引）；**索引列与列顺序必须与 `GlobalSearch::SEARCH_FIELDS` 一致**，否则 `MATCH(...)` 无法命中索引
- **自动回退**：驱动配置为 `fulltext` 但当前连接不是 mysql（如测试用 sqlite）时，自动回退 LIKE，行为不变
- **BOOLEAN MODE 安全**：用户输入先剥离布尔操作符（`+ - > < ( ) * ~ "`）防止语法错误，再按空白拆词、逐词双引号包裹（词组精确匹配、AND 语义）
- `.env` 切换：`CRM_SEARCH_DRIVER=fulltext`（`.env.example` 已默认 fulltext，生产 MySQL 部署跑完迁移即生效）

## 三、权限与隔离

- **租户隔离**：全部查询经 TenantScope 自动附加 `WHERE tenant_id = ?`，跨租户数据不可见
- **数据权限**：公司 / 线索 / 商机经 `applyDataScope` 过滤，sales 角色仅命中本人负责的数据；联系人无负责人字段，全员可见（与列表页权限一致）
- 每类实体最多返回 **10 条**最新记录（按 `created_at` 倒序），结果区显示总命中数

## 四、与 REST API 的关系

搜索页面向 Web UI；集成方需程序化检索时使用各资源的 `?search=` 参数（见 [api.md](api.md)），两者字段口径一致。
