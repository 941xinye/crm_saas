# 架构详解

本文档描述多租户 CRM 的核心架构设计、请求生命周期、RBAC 模型与扩展点。

---

## 一、多租户设计核心

### 方案选择：单库 + `tenant_id` 隔离

| 维度 | 描述 |
|------|------|
| 实现方式 | 所有业务表加 `tenant_id` 列，查询自动 `WHERE tenant_id = ?` |
| 隔离强度 | 逻辑隔离（数据库层面可被绕过，依赖 Scope） |
| 优点 | 运维简单、备份成本低、跨租户查询（超管）方便 |
| 缺点 | 无法利用原生数据库级隔离，需要严谨的 Scope 覆盖 |
| 适用场景 | SaaS 初期 / 中小企业客户为主 / 超管需要跨租户管理 |

### 不选其他方案的理由

- **一租户一库**：运维复杂度高，新增租户需建库/迁移，超管跨租户聚合麻烦
- **Schema-per-tenant**：MySQL 原生支持差，PostgreSQL 才能发挥优势
- **行级安全（RLS）**：MySQL 不原生支持，需要代理层

---

## 二、三大核心零件

### 1. `TenantScope`（全局 Scope）

```
app/Models/Scopes/TenantScope.php
```

- 实现 Laravel 的 `Illuminate\Database\Eloquent\Scope` 接口
- `apply()` 在每次查询前被 Eloquent 调用
- **逃生舱**：`app(Tenancy::class)->id() === null` 时直接 return，不附加任何条件
- 逃生舱让 Seeder / Tinker / Artisan Command 等 console 环境下自动关闭隔离

```php
public function apply(Builder $builder, Model $model): void
{
    $tenancy = app(Tenancy::class);
    if ($tenancy->id() === null) {
        return;  // ← console / seeding 逃生舱
    }
    $builder->where($model->getTable() . '.tenant_id', $tenancy->id());
}
```

### 2. `BelongsToTenant` Trait

```
app/Models/Traits/BelongsToTenant.php
```

所有业务模型（Company / Contact / Lead / Opportunity / Activity）都 use 这个 Trait。

```php
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);   // 查询时自动过滤
        static::creating(function ($model) {       // 创建时自动注入 tenant_id
            $model->tenant_id ??= app(Tenancy::class)->id();
        });
    }

    public function tenant() { return $this->belongsTo(Tenant::class); }
    public function scopeWithoutTenant($query) { return $query->withoutGlobalScope(TenantScope::class); }
}
```

**关键设计：**
- `??=` 允许 Seeder 显式指定 `tenant_id` 而不被覆盖
- `withoutTenant()` scope 给超管跨租户查看用（很少用，但必要）

### 3. `Tenancy` 单例服务

```
app/Services/Tenancy.php
```

**必须注册为 singleton**，否则每次 `app(Tenancy::class)` 拿到新实例、租户 ID 丢失！

注册位置：`app/Providers/AppServiceProvider.php::register()`
```php
$this->app->singleton(Tenancy::class);
```

```php
class Tenancy
{
    private ?Tenant $current = null;
    private ?User $user = null;

    public function forUser(?User $user): self;     // 从用户初始化（主入口）
    public function setTenant(?Tenant $tenant): self; // 手动覆盖（罕见场景）
    public function current(): ?Tenant;              // 当前租户对象
    public function id(): ?int;                      // 当前租户 ID（null 表示未初始化）
    public function user(): ?User;
    public function isPlatformAdmin(): bool;
}
```

---

## 三、中间件链

中间件在 `bootstrap/app.php` 中注册：

### Web 请求

```
HTTP 请求（Web）
  │
  ▼ Laravel 默认中间件（TrustProxies / HandleCors / StartSession / ... / AuthenticateSession）
  │
  ▼ BootstrapTenancy  ← 全局 web 组末尾追加
  │      └─ 从 Auth::user() 初始化 Tenancy::forUser()
  │      └─ 未登录则跳过（Tenancy->id() 保持 null，Scope 逃生舱开启）
  │
  ▼ EnsureUserHasTenant  ← 需 auth 的路由组里单独加
  │      └─ 检查 $user->tenant 是否存在
  │      └─ 平台超管自动跳过
  │      └─ 否则 403
  │
  ▼ EnsureUserIsPlatformAdmin  ← /platform/* 路由组守卫
  │      └─ 仅 $user->is_platform_admin 通过
  │
  ▼ 业务中间件（ThrottleRequests 等）
  │
  ▼ 路由 Controller / Livewire 组件
  │
  ▼ Tenancy->id() 已有值 → TenantScope 自动附加 WHERE → 查询安全
```

### API 请求（Phase 3 新增）

```
HTTP 请求（/api/v1/*）
  │
  ▼ Laravel API 默认中间件（ThrottleRequestsApi / ...）
  │
  ▼ auth:sanctum  ← 解析 Authorization: Bearer <token>
  │      └─ Sanctum 查 personal_access_tokens 表
  │      └─ 找到 → $request->user() = User（有 tenant_id）
  │      └─ 没找到 → 401
  │
  ▼ BootstrapTenancy  ← Phase 3 新增！api 组末尾追加
  │      └─ 从 $request->user() 初始化 Tenancy::forUser()
  │      └─ Tenancy->id() 有值了
  │
  ▼ verified / ensure.has.tenant  ← 业务路由组中间件
  │
  ▼ TenantScope 自动过滤 → API 跨租户隔离生效 ✅
```

> **关键设计**：BootstrapTenancy 同时在 `web` 和 `api` 组末尾执行，API 无需额外中间件！
> `TenantScope` + `BelongsToTenant` 的租户隔离机制对 Web 和 API 完全复用，无需两套代码。

### 中间件注册代码

```php
// bootstrap/app.php
$middleware->alias([
    'ensure.has.tenant' => EnsureUserHasTenant::class,
    'platform.admin'    => EnsureUserIsPlatformAdmin::class,
]);

// 全局 Tenancy 初始化（在 AuthenticateSession 之后）
$middleware->web(append: [
    BootstrapTenancy::class,
]);
```

---

## 四、角色权限模型

### 两级 RBAC

```
┌─ 平台超管（is_platform_admin = true）
│     └─ 管理所有租户，跨租户聚合查询
│     └─ 使用 EnsureUserIsPlatformAdmin 中间件守卫
│
└─ 租户内用户
      ├─ admin    — 可删除、可改任意记录
      ├─ manager  — 可更新任意记录，不可删除
      └─ sales    — 只能更新自己的记录（owner_id === $user->id）
```

### Policy + Gate

每个业务模型有独立 Policy，统一使用 Laravel Gate 机制：

```php
// CompanyPolicy 示例（其他 Policy 结构一致）
public function update(User $user, Company $company): bool
{
    return $user->role === 'admin'
        || $user->role === 'manager'
        || $company->owner_id === $user->id;
}
```

**Livewire 4 中调用方式**：
```php
public function mount(?Company $company = null): void
{
    $this->company = $company ?? new Company();
    $this->authorize($company ? 'update' : 'create', $this->company);
}
```

> Policy 类在 `app/Policies/` 目录下，Laravel 默认按模型 + Policy 约定自动发现。

---

## 五、请求生命周期（文字版）

```
1. 用户浏览器发 HTTP 请求到 crm.servbay.host
2. ServBay → Nginx → PHP-FPM → Laravel index.php
3. Laravel Kernel 启动容器（此时 Tenancy 尚未初始化，id() = null）
4. 全局中间件依次执行：
   a. TrustProxies → HandleCors → ... → StartSession
   b. AuthenticateSession：从 session 恢复 Auth::user()
   c. BootstrapTenancy：Auth::user() 存在 → Tenancy::forUser($user) → Tenancy->id() 有值了
5. 路由分发（Route → Controller/Livewire 组件）
6. Livewire 组件 mount()/render() 中调用 Eloquent：
   a. Eloquent 启动查询 Builder
   b. Builder 检查 Model 上的 Global Scope
   c. TenantScope::apply()：Tenancy->id() 存在 → 附加 WHERE tenant_id = ?
   d. SQL 执行 → 返回当前租户的数据（隔离生效）
7. 响应返回浏览器
```

---

## 六、安全网总结

| 场景 | Tenancy->id() | Scope 行为 | 结果 |
|------|---------------|------------|------|
| HTTP 请求（已登录租户用户） | 有值 | 附加 WHERE | ✅ 隔离生效 |
| HTTP 请求（平台超管） | 有值（超管 tenant_id 通常为 null，取决于业务设计） | 附加 WHERE（如果超管也有 tenant_id）或跳过（超管可能挂 withoutTenant） | ✅ 安全 |
| HTTP 请求（未登录） | null | 逃生舱关闭 | ⚠️ 但这种情况不会查业务表 |
| `php artisan migrate --seed` | null | 逃生舱关闭 | ✅ Seeder 正常创建所有租户的数据 |
| Tinker `php artisan tinker` | null | 逃生舱关闭 | ✅ 交互式调试方便 |
| 忘记注册 singleton（Bug） | 每次新建实例 | 同一请求内但多次调用 app(Tenancy) 会丢失状态 | ❌ 必须修正 |

---

## 七、子域名租户识别（Phase 6 已实现）

配置 `crm.subdomain_root`（环境变量 `CRM_SUBDOMAIN_ROOT`，如 `crm.example.com`）后启用，**为空时完全禁用**（本地开发/测试零影响，走用户所属租户的默认逻辑）。

```
请求 acme.crm.example.com
  │
  ▼ BootstrapTenancy（解析顺序）
  │      ├─ host = 主站 crm.example.com        → 回退：登录用户所属租户（forUser）
  │      ├─ host = acme.crm.example.com        → 查 tenants(slug=acme, is_active)
  │      │      ├─ 不存在/已停用                → 404
  │      │      ├─ 登录用户 tenant_id ≠ 租户    → 403（平台超管豁免）
  │      │      └─ 匹配                        → Tenancy::lockTenant() 锁定上下文
  │      └─ host = localhost / 未配置 root     → 完全不识别（no-op）
  │
  ▼ EnsureUserHasTenant 等后续中间件再次 forUser 时，
    Tenancy 检测到 tenantLocked=true，不把租户覆盖回用户所属租户 ✅
```

要点：

- **租户锁定**：`Tenancy::lockTenant()` 设置 `tenantLocked` 标志，保证子域名上下文穿透整个中间件链（`ensure.has.tenant` 会再调一次 `forUser`）
- **归属校验**：登录用户（平台超管除外）只能访问自己所属租户的子域名，防止通过他人子域名探测数据
- `tenants.slug` 已有唯一索引（建表迁移自带）
- slug 格式校验 `[a-z0-9][a-z0-9-]{0,62}`，多级子域名只取第一段
- 生产部署：Nginx/Caddy 需配置泛域名解析（`*.crm.example.com` → 应用），并设置 `CRM_SUBDOMAIN_ROOT=crm.example.com`
- 测试：`tests/Feature/SubdomainTenancyTest.php`（8 个用例，含端到端数据隔离）
