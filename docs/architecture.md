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

```
HTTP 请求
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

## 七、子域名扩展点（已预留）

当前 Tenancy 绑定到用户（user-bound），登录后确定租户。如果未来要支持 **子域名即租户** 模式（如 `acme.crm.servbay.host`），改动点：

1. **BootstrapTenancy** 中间件加一段子域名解析：
   ```php
   $host = $request->getHost(); // acme.crm.servbay.host
   $subdomain = explode('.', $host)[0]; // acme
   $tenant = Tenant::where('slug', $subdomain)->first();
   app(Tenancy::class)->setTenant($tenant);
   ```

2. 租户 slug 字段需在 `tenants` 表加唯一索引（当前 seeder 可快速补）

3. 用户可跨租户（User 的 tenant_id 可空，多对多 pivot 表关联）

当前架构天然支持扩展，不需要推翻重来。
