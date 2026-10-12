# 全局搜索

顶栏搜索框输入关键词回车，跳转 `/search` 页面（`?q=` 参数驱动，URL 可分享），一次检索**公司 / 联系人 / 线索 / 商机**四类实体。实现：[app/Livewire/GlobalSearch.php](../app/Livewire/GlobalSearch.php)。

## 一、覆盖字段

| 实体 | 搜索字段 |
|------|----------|
| 公司 | name、industry、website、phone |
| 联系人 | first_name、last_name、email、phone、position |
| 线索 | name、company_name、email、phone |
| 商机 | title |

匹配方式为 `LIKE %keyword%`，输入中的 `%` / `_` / `\` 已转义防通配符注入。

## 二、权限与隔离

- **租户隔离**：全部查询经 TenantScope 自动附加 `WHERE tenant_id = ?`，跨租户数据不可见
- **数据权限**：公司 / 线索 / 商机经 `applyDataScope` 过滤，sales 角色仅命中本人负责的数据；联系人无负责人字段，全员可见（与列表页权限一致）
- 每类实体最多返回 **10 条**最新记录（按 `created_at` 倒序），结果区显示总命中数

## 三、与 REST API 的关系

搜索页面向 Web UI；集成方需程序化检索时使用各资源的 `?search=` 参数（见 [api.md](api.md)），两者字段口径一致。
